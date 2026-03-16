<?php

declare(strict_types=1);

namespace Nalabdou\Algebra;

use Nalabdou\Algebra\Adapter\AdapterRegistry;
use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Collection\CollectionFactory;
use Nalabdou\Algebra\Collection\RelationalCollection;
use Nalabdou\Algebra\Expression\ExpressionCache;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;
use Nalabdou\Algebra\Planner\QueryPlanner;

/**
 * algebra-php — pure PHP relational algebra engine.
 *
 * This class is the **single public entry point**. All infrastructure
 * (factory, evaluator, planner, aggregates, adapters) is created lazily on
 * first use and reused for the lifetime of the process.
 *
 * **Zero runtime dependencies** — no Symfony, no Doctrine, no framework.
 * The expression engine (Lexer → Parser → Evaluator) is written in pure PHP.
 *
 * ---
 *
 * ### Quick start
 * ```php
 * use Nalabdou\Algebra\Algebra;
 *
 * $result = Algebra::from($orders)
 *     ->where("item['status'] == 'paid'")
 *     ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
 *     ->groupBy('region')
 *     ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
 *     ->orderBy('revenue', 'desc')
 *     ->toArray();
 * ```
 *
 * ---
 *
 * ### Registering custom adapters
 *
 * Call once at application bootstrap — no factory configuration needed:
 * ```php
 * Algebra::adapters()->register(new CsvFileAdapter(), priority: 50);
 * Algebra::adapters()->register(new DoctrineQueryBuilderAdapter(), priority: 100);
 *
 * // Now accepted by Algebra::from()
 * Algebra::from('/data/orders.csv')->where(...)->toArray();
 * Algebra::from($queryBuilder)->groupBy('region')->toArray();
 * ```
 *
 * ### Registering custom aggregates
 * ```php
 * Algebra::aggregates()->register(new GeomeanAggregate());
 *
 * Algebra::from($products)->aggregate(['geo' => 'geomean(price)'])->toArray();
 * ```
 *
 * ---
 *
 * ### All available operations (on the returned {@see RelationalCollection})
 *
 * **Joins** — `innerJoin`, `leftJoin`, `semiJoin`, `antiJoin`, `crossJoin`, `zip`
 *
 * **Set ops** — `intersect`, `except`, `union`, `symmetricDiff`
 *
 * **Filter/project** — `where(expr)`, `select(expr)`
 *
 * **Grouping** — `groupBy(key)`, `aggregate([specs])`, `tally(field)`, `partition(expr)`
 *
 * **Window** — `window(fn, ...)`, `movingAverage(...)`, `normalize(...)`
 *
 * **Pivot** — `pivot(rows:, cols:, value:, aggregateFn:)`
 *
 * **Sort/slice** — `orderBy(key, dir)`, `limit(n, offset)`, `topN(n, by)`, `bottomN(n, by)`
 *
 * **Structural** — `distinct(key)`, `reindex(key)`, `pluck(field)`, `chunk(size)`,
 *                  `fillGaps(key, series, default)`, `transpose()`, `sample(n, seed)`,
 *                  `rankBy(field, direction, as)`
 *
 * **Terminal** — `toArray()`, `materialize()`, `count()`, `partition(expr)`
 *
 * ---
 *
 * ### Expression language (built-in, zero dependencies)
 * ```php
 * ->where("item['status'] == 'paid' and item['amount'] > 100")
 * ->where("status in ['paid', 'refunded']")
 * ->where("contains(item['email'], '@company.com')")
 * ->where("amount > 500 ? true : false")
 * ->where(fn($r) => $r['status'] === 'paid')  // closures always work
 * ```
 */
final class Algebra
{
    private static ?CollectionFactory $factory = null;
    private static ?ExpressionEvaluator $evaluator = null;
    private static ?PropertyAccessor $accessor = null;
    private static ?AggregateRegistry $aggregates = null;
    private static ?AdapterRegistry $adapters = null;
    private static ?QueryPlanner $planner = null;
    private static ?ExpressionCache $cache = null;

    /**
     * Create a lazy {@see RelationalCollection} from any supported input.
     *
     * Accepts: plain PHP array, `\Generator`, any `\Traversable`,
     * or any custom adapter registered via {@see Algebra::adapters()}.
     *
     * ```php
     * Algebra::from($orders)
     * Algebra::from($generator)
     * Algebra::from(new ArrayObject($rows))
     * Algebra::from('/data/orders.csv')  // after registering CsvFileAdapter
     * Algebra::from($queryBuilder)       // after registering DoctrineQueryBuilderAdapter
     * ```
     */
    public static function from(mixed $input): RelationalCollection
    {
        return self::factory()->create($input);
    }

    /**
     * Build and immediately execute a pipeline in one expression.
     *
     * ```php
     * $result = Algebra::pipe($orders, fn($c) =>
     *     $c->where("item['status'] == 'paid'")->orderBy('amount', 'desc')
     * );
     * ```
     *
     * @param callable(RelationalCollection): RelationalCollection $pipeline
     *
     * @return array<int|string, mixed>
     */
    public static function pipe(mixed $input, callable $pipeline): array
    {
        return $pipeline(self::from($input))->toArray();
    }

    /**
     * Run multiple independent pipelines concurrently using PHP 8.1 Fibers.
     *
     * ```php
     * $results = Algebra::parallel([
     *     'paid'    => Algebra::from($orders)->where("item['status'] == 'paid'"),
     *     'pivoted' => Algebra::from($sales)->pivot(rows: 'month', cols: 'region', value: 'amount'),
     * ]);
     * ```
     *
     * @param array<string|int, RelationalCollection> $pipelines
     *
     * @return array<string|int, array<int|string, mixed>>
     */
    public static function parallel(array $pipelines): array
    {
        $fibers = [];
        $results = [];

        foreach ($pipelines as $key => $collection) {
            $fiber = new \Fiber(static fn (): array => $collection->toArray());
            $fibers[$key] = $fiber;
            $fiber->start();
        }

        foreach ($fibers as $key => $fiber) {
            $results[$key] = $fiber->getReturn();
        }

        return $results;
    }

    /**
     * The collection factory — creates {@see RelationalCollection} from any input.
     */
    public static function factory(): CollectionFactory
    {
        return self::$factory ??= new CollectionFactory(
            planner: self::planner(),
            evaluator: self::evaluator(),
            accessor: self::accessor(),
            aggregates: self::aggregates(),
            adapterRegistry: self::adapters(),
        );
    }

    /**
     * The adapter registry — register custom input adapters here.
     *
     * Built-in adapters (Generator, Traversable, Array) are pre-registered.
     * Custom adapters are tried before built-ins when given a higher priority.
     *
     * ```php
     * // Register once at bootstrap — works everywhere after that
     * Algebra::adapters()->register(new CsvFileAdapter(), priority: 50);
     * Algebra::adapters()->register(new DoctrineQueryBuilderAdapter(), priority: 100);
     *
     * // All pipelines automatically support the new input types
     * Algebra::from('/data/orders.csv')->where(...)->toArray();
     * ```
     */
    public static function adapters(): AdapterRegistry
    {
        return self::$adapters ??= new AdapterRegistry();
    }

    /**
     * The expression evaluator — pure PHP, zero dependencies.
     *
     * Supports string expressions (compiled to AST) and closures.
     */
    public static function evaluator(): ExpressionEvaluator
    {
        return self::$evaluator ??= new ExpressionEvaluator(
            propertyAccessor: self::accessor(),
            cache: self::cache(),
        );
    }

    /**
     * The expression AST cache (APCu when available, in-process array otherwise).
     */
    public static function cache(): ExpressionCache
    {
        return self::$cache ??= new ExpressionCache();
    }

    /**
     * The property accessor — resolves dot-path expressions on arrays and objects.
     */
    public static function accessor(): PropertyAccessor
    {
        return self::$accessor ??= new PropertyAccessor();
    }

    /**
     * The aggregate registry — register custom aggregate functions here.
     *
     * ```php
     * Algebra::aggregates()->register(new GeomeanAggregate());
     * ```
     */
    public static function aggregates(): AggregateRegistry
    {
        return self::$aggregates ??= new AggregateRegistry();
    }

    /**
     * The query planner — inspect or extend optimization passes.
     *
     * ```php
     * $plan = Algebra::planner()->explain($collection->operations());
     * ```
     */
    public static function planner(): QueryPlanner
    {
        return self::$planner ??= new QueryPlanner();
    }

    /**
     * Reset all singletons to their initial state.
     *
     * Call this in test `setUp()` to get a clean slate between test runs.
     */
    public static function reset(): void
    {
        self::$factory = null;
        self::$evaluator = null;
        self::$accessor = null;
        self::$aggregates = null;
        self::$adapters = null;
        self::$planner = null;
        self::$cache = null;
    }

    private function __construct()
    {
    }
}
