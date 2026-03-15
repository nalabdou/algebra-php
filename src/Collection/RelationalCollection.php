<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Collection;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Contract\CollectionInterface;
use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PlannerInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;
use Nalabdou\Algebra\Operation\Aggregate\AggregateOperation;
use Nalabdou\Algebra\Operation\Aggregate\GroupByOperation;
use Nalabdou\Algebra\Operation\Aggregate\PartitionOperation;
use Nalabdou\Algebra\Operation\Aggregate\TallyOperation;
use Nalabdou\Algebra\Operation\Join\AntiJoinOperation;
use Nalabdou\Algebra\Operation\Join\CrossJoinOperation;
use Nalabdou\Algebra\Operation\Join\JoinOperation;
use Nalabdou\Algebra\Operation\Join\LeftJoinOperation;
use Nalabdou\Algebra\Operation\Join\SemiJoinOperation;
use Nalabdou\Algebra\Operation\Join\ZipOperation;
use Nalabdou\Algebra\Operation\Set\DiffByOperation;
use Nalabdou\Algebra\Operation\Set\ExceptOperation;
use Nalabdou\Algebra\Operation\Set\IntersectOperation;
use Nalabdou\Algebra\Operation\Set\UnionOperation;
use Nalabdou\Algebra\Operation\Utility\ChunkOperation;
use Nalabdou\Algebra\Operation\Utility\ExtractOperation;
use Nalabdou\Algebra\Operation\Utility\FillGapsOperation;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;
use Nalabdou\Algebra\Operation\Utility\MapOperation;
use Nalabdou\Algebra\Operation\Utility\PivotOperation;
use Nalabdou\Algebra\Operation\Utility\ReindexOperation;
use Nalabdou\Algebra\Operation\Utility\SampleOperation;
use Nalabdou\Algebra\Operation\Utility\SliceOperation;
use Nalabdou\Algebra\Operation\Utility\SortOperation;
use Nalabdou\Algebra\Operation\Utility\TransposeOperation;
use Nalabdou\Algebra\Operation\Utility\UniqueByOperation;
use Nalabdou\Algebra\Operation\Window\MovingAvgOperation;
use Nalabdou\Algebra\Operation\Window\NormalizeOperation;
use Nalabdou\Algebra\Operation\Window\WindowOperation;
use Nalabdou\Algebra\Result\PartitionResult;

/**
 * Lazy, immutable relational collection with a composable fluent API.
 *
 * Operations are queued and **not executed** until the collection is
 * iterated, {@see toArray()} is called, or {@see materialize()} is invoked.
 *
 * Every method that adds an operation returns a **new instance** — the
 * original collection is never mutated. This makes pipelines safe to
 * branch and reuse:
 *
 * ```php
 * $base = Algebra::from($orders)->filter("item['status'] == 'paid'");
 *
 * $byRegion  = $base->groupBy('region')->aggregate(['total' => 'sum(amount)']);
 * $top10     = $base->sortBy('amount', 'desc')->slice(limit: 10);
 * $withOwner = $base->joinOn($users, leftKey: 'userId', rightKey: 'id', as: 'owner');
 * // $base is unchanged — all three share the same filter step.
 * ```
 *
 * ### Query planner
 * Before execution the {@see PlannerInterface} reorders operations for
 * maximum efficiency. A filter declared after a join will automatically be
 * pushed before it, reducing the join's input size.
 *
 * ### Execution log
 * After materializing, the {@see MaterializedCollection::executionLog()} method
 * returns per-operation timing, input/output row counts, and signatures —
 * ready for a profiler panel or debug dump.
 */
final class RelationalCollection implements CollectionInterface
{
    /** @var OperationInterface[] */
    private array $operations = [];

    private ?MaterializedCollection $materialized = null;

    public function __construct(
        private readonly array $source,
        private readonly PlannerInterface $planner,
        private readonly ExpressionEvaluator $evaluator,
        private readonly PropertyAccessor $accessor,
        private readonly AggregateRegistry $aggregates,
    ) {
    }

    /**
     * Execute the full operation chain and return the evaluated result.
     *
     * The result is cached — subsequent calls with the same pipeline return
     * the same {@see MaterializedCollection} instance without re-executing.
     */
    public function materialize(): MaterializedCollection
    {
        if (null !== $this->materialized) {
            return $this->materialized;
        }

        $optimized = $this->planner->optimize($this->operations);

        $rows = $this->source;
        $log = [];

        foreach ($optimized as $operation) {
            $start = \hrtime(true);
            $inputCount = \count($rows);
            $rows = $operation->execute($rows);
            $outputCount = \count($rows);

            $log[] = [
                'operation' => $operation::class,
                'signature' => $operation->signature(),
                'input_rows' => $inputCount,
                'output_rows' => $outputCount,
                'duration_ms' => \round((\hrtime(true) - $start) / 1_000_000, 4),
            ];
        }

        return $this->materialized = new MaterializedCollection($rows, $log);
    }

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        return $this->materialize()->toArray();
    }

    public function count(): int
    {
        return $this->materialize()->count();
    }

    public function getIterator(): \ArrayIterator
    {
        return $this->materialize()->getIterator();
    }

    /**
     * Append a raw operation and return a new immutable collection.
     *
     * Prefer the named fluent methods below. Use `pipe()` only for custom
     * {@see OperationInterface} implementations.
     */
    public function pipe(OperationInterface $operation): static
    {
        $clone = clone $this;
        $clone->operations = [...$this->operations, $operation];

        return $clone;
    }

    /**
     * INNER JOIN — merge rows from two collections where keys match.
     *
     * Unmatched left rows are **dropped**. Uses a hash-index internally —
     * O(n+m) rather than O(n×m).
     *
     * ```php
     * Algebra::from($orders)
     *     ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
     *     ->toArray();
     * // Each row now contains: [...order fields, 'owner' => [...user fields]]
     * ```
     *
     * @param mixed  $right    the right-side collection (array, generator, Traversable)
     * @param string $leftKey  dot-path on the left row used for matching
     * @param string $rightKey dot-path on the right row used for matching
     * @param string $as       key name under which the matched right row is attached
     */
    public function innerJoin(
        mixed $right,
        string $leftKey = 'id',
        string $rightKey = 'id',
        string $as = 'related',
    ): static {
        return $this->pipe(new JoinOperation(
            right: $this->toRawArray($right),
            leftKey: $leftKey,
            rightKey: $rightKey,
            as: $as,
            accessor: $this->accessor,
        ));
    }

    /**
     * LEFT JOIN — keep all left rows; attach matched right row or null.
     *
     * Unlike {@see innerJoin()}, unmatched left rows are **preserved** with
     * `null` under the joined key.
     *
     * ```php
     * Algebra::from($orders)
     *     ->leftJoin($users, on: 'userId=id', as: 'owner')
     *     ->toArray();
     * // Rows without a matching user have $row['owner'] === null
     * ```
     *
     * @param mixed  $right the right-side collection
     * @param string $on    condition in `"leftKey=rightKey"` format
     * @param string $as    key name for the joined right row
     */
    public function leftJoin(
        mixed $right,
        string $on = 'id=id',
        string $as = 'related',
    ): static {
        [$leftKey, $rightKey] = $this->accessor->parseJoinCondition($on);

        return $this->pipe(new LeftJoinOperation(
            right: $this->toRawArray($right),
            leftKey: $leftKey,
            rightKey: $rightKey,
            as: $as,
            accessor: $this->accessor,
        ));
    }

    /**
     * SEMI JOIN — keep left rows that have at least one match on the right.
     *
     * Unlike {@see innerJoin()}, **no right-side data is attached**. This is
     * faster than a full join when you only need existence checking.
     *
     * ```php
     * // Orders that have at least one payment — without attaching payment data
     * Algebra::from($orders)->semiJoin($payments, leftKey: 'id', rightKey: 'orderId');
     * ```
     */
    public function semiJoin(
        mixed $right,
        string $leftKey = 'id',
        string $rightKey = 'id',
    ): static {
        return $this->pipe(new SemiJoinOperation(
            right: $this->toRawArray($right),
            leftKey: $leftKey,
            rightKey: $rightKey,
            accessor: $this->accessor,
        ));
    }

    /**
     * ANTI JOIN — keep left rows that have **no** match on the right.
     *
     * The inverse of {@see semiJoin()}.
     *
     * ```php
     * // Orders with zero payments recorded
     * Algebra::from($orders)->antiJoin($payments, leftKey: 'id', rightKey: 'orderId');
     * ```
     */
    public function antiJoin(
        mixed $right,
        string $leftKey = 'id',
        string $rightKey = 'id',
    ): static {
        return $this->pipe(new AntiJoinOperation(
            right: $this->toRawArray($right),
            leftKey: $leftKey,
            rightKey: $rightKey,
            accessor: $this->accessor,
        ));
    }

    /**
     * CROSS JOIN — cartesian product (every left row × every right row).
     *
     * Output size = `count(left) × count(right)`. Use only on small collections.
     * Optional prefixes prevent key collisions when both sides share field names.
     *
     * ```php
     * // All size+colour combinations
     * Algebra::from($sizes)->crossJoin($colours, leftPrefix: 'size_', rightPrefix: 'colour_');
     * // → [{size_name:'S', colour_name:'Red'}, {size_name:'S', colour_name:'Blue'}, ...]
     * ```
     */
    public function crossJoin(
        mixed $right,
        string $leftPrefix = '',
        string $rightPrefix = '',
    ): static {
        return $this->pipe(new CrossJoinOperation(
            right: $this->toRawArray($right),
            leftPrefix: $leftPrefix,
            rightPrefix: $rightPrefix,
        ));
    }

    /**
     * ZIP — merge two collections **by position** (index 0 with index 0, etc.).
     *
     * Output length = `min(count(left), count(right))`. No key matching involved.
     *
     * ```php
     * Algebra::from($labels)->zip($values);
     * // → [{label:'Revenue', value:5400}, {label:'Orders', value:120}]
     * ```
     */
    public function zip(
        mixed $right,
        string $leftAs = '',
        string $rightAs = '',
    ): static {
        return $this->pipe(new ZipOperation(
            right: $this->toRawArray($right),
            leftAs: $leftAs,
            rightAs: $rightAs,
        ));
    }

    /**
     * INTERSECT — keep only rows whose key exists in **both** collections (A ∩ B).
     *
     * ```php
     * Algebra::from($wishlist)->intersect($recommendations, by: 'productId');
     * // → items present in both lists
     * ```
     */
    public function intersect(mixed $right, string $by = 'id'): static
    {
        return $this->pipe(new IntersectOperation(
            right: $this->toRawArray($right),
            by: $by,
            accessor: $this->accessor,
        ));
    }

    /**
     * EXCEPT — keep rows from the left that are **absent** from the right (A − B).
     *
     * ```php
     * Algebra::from($notifications)->except($dismissed, by: 'id');
     * // → unread notifications only
     * ```
     */
    public function except(mixed $right, string $by = 'id'): static
    {
        return $this->pipe(new ExceptOperation(
            right: $this->toRawArray($right),
            by: $by,
            accessor: $this->accessor,
        ));
    }

    /**
     * UNION — merge two collections and deduplicate by key (A ∪ B).
     *
     * First occurrence wins on duplicate keys.
     *
     * ```php
     * Algebra::from($staff)->union($contractors, by: 'email');
     * // → everyone, deduplicated by email
     * ```
     *
     * @param string|null $by Key to deduplicate on. Null uses `SORT_REGULAR` array uniqueness.
     */
    public function union(mixed $right, ?string $by = null): static
    {
        return $this->pipe(new UnionOperation(
            right: $this->toRawArray($right),
            by: $by,
            accessor: $this->accessor,
        ));
    }

    /**
     * SYMMETRIC DIFFERENCE — rows in A **or** B but **not both** (A △ B).
     *
     * ```php
     * Algebra::from($listA)->symmetricDiff($listB, by: 'id');
     * // → rows exclusive to each side
     * ```
     */
    public function symmetricDiff(mixed $right, string $by = 'id'): static
    {
        return $this->pipe(new DiffByOperation(
            right: $this->toRawArray($right),
            by: $by,
            accessor: $this->accessor,
        ));
    }

    /**
     * WHERE — keep only rows matching an expression or closure.
     *
     * ```php
     * ->where("item['status'] == 'paid' and item['amount'] > 100")
     * ->where(fn($r) => $r['status'] === 'paid' && $r['amount'] > 100)
     * ```
     *
     * The row is exposed as `item` in string expressions.
     *
     * @param string|\Closure $expression string expression or `fn($row): bool`
     */
    public function where(string|\Closure $expression): static
    {
        return $this->pipe(new FilterOperation($expression, $this->evaluator));
    }

    /**
     * SELECT — project each row through an expression or closure.
     *
     * ```php
     * ->select('id') // pluck field
     * ->select(fn($r) => ['id' => $r['id'],'name' => strtoupper($r['name'])])
     * ```
     */
    public function select(string|\Closure $expression): static
    {
        return $this->pipe(new MapOperation($expression, $this->evaluator));
    }

    /**
     * GROUP BY — group rows by the resolved value of a field or expression.
     *
     * Returns an associative array: `['group_key' => [rows...]]`.
     * Chain with {@see aggregate()} to collapse groups into summary rows.
     *
     * ```php
     * ->groupBy('status')
     * ->groupBy("item['region'] ~ '-' ~ item['year']")
     * ```
     */
    public function groupBy(string|\Closure $key): static
    {
        return $this->pipe(new GroupByOperation($key, $this->evaluator));
    }

    /**
     * AGGREGATE — compute functions over groups or a flat collection.
     *
     * Takes a spec array mapping output keys to aggregate expressions:
     *
     * ```php
     * ->groupBy('status')
     * ->aggregate([
     *     'count'       => 'count(*)',
     *     'total'       => 'sum(amount)',
     *     'average'     => 'avg(amount)',
     *     'median_val'  => 'median(amount)',
     *     'top_90th'    => 'percentile(amount, 0.9)',
     *     'product_list'=> 'string_agg(name, ", ")',
     *     'all_sent'    => 'bool_and(sent)',
     * ])
     * ```
     *
     * Each output row has a `_group` key with the group identifier.
     *
     * @param array<string, string> $specs map of alias → aggregate spec
     */
    public function aggregate(array $specs): static
    {
        return $this->pipe(new AggregateOperation($specs, $this->aggregates, $this->evaluator));
    }

    /**
     * TALLY — count occurrences of each distinct value of a field.
     *
     * Returns an associative array sorted by count descending:
     * `['paid' => 42, 'pending' => 12, 'cancelled' => 3]`
     *
     * ```php
     * Algebra::from($orders)->tally('status')->toArray();
     * ```
     */
    public function tally(string $field): static
    {
        return $this->pipe(new TallyOperation($field, $this->accessor));
    }

    /**
     * WINDOW FUNCTION — enrich each row without collapsing the collection.
     *
     * All window functions annotate every row with a computed value under `$as`.
     * Row count is always preserved.
     *
     * ### Available functions
     * | Function         | Description                                          |
     * |------------------|------------------------------------------------------|
     * | `running_sum`    | Cumulative sum of `$field`                           |
     * | `running_avg`    | Cumulative average of `$field`                       |
     * | `running_count`  | Cumulative row count                                 |
     * | `running_diff`   | Delta vs previous row (`null` for first)             |
     * | `rank`           | Rank by `$field` descending (gaps on ties)           |
     * | `dense_rank`     | Dense rank (no gaps on ties)                         |
     * | `row_number`     | Sequential 1-based row number                        |
     * | `lag`            | Value of `$field` N rows before                      |
     * | `lead`           | Value of `$field` N rows after                       |
     * | `ntile`          | Bucket number 1–N (use `$buckets` to set N)          |
     * | `cume_dist`      | Cumulative distribution fraction                     |
     *
     * ### Partition support
     * Pass `partitionBy` to reset the window per group:
     * ```php
     * ->window('running_sum', field: 'amount', as: 'userTotal', partitionBy: 'userId')
     * ```
     *
     * @param string      $fn          one of the functions listed above
     * @param string      $field       the field to compute over
     * @param string|null $partitionBy reset the window per distinct value of this field
     * @param string      $as          output key name added to each row
     * @param int         $offset      offset for `lag`/`lead` (default 1)
     * @param int         $buckets     number of buckets for `ntile` (default 4)
     */
    public function window(
        string $fn,
        string $field = 'id',
        ?string $partitionBy = null,
        string $as = '_window',
        int $offset = 1,
        int $buckets = 4,
    ): static {
        return $this->pipe(new WindowOperation(
            fn: $fn,
            field: $field,
            partitionBy: $partitionBy,
            as: $as,
            offset: $offset,
            buckets: $buckets,
            accessor: $this->accessor,
        ));
    }

    /**
     * MOVING AVERAGE — sliding window average over N consecutive rows.
     *
     * Rows without enough prior context receive `null`.
     *
     * ```php
     * ->movingAverage(field: 'revenue', window: 7, as: 'avg_7d')
     * // First 6 rows: avg_7d = null
     * // Row 7 onwards: avg_7d = average of current + 6 prior rows
     * ```
     */
    public function movingAverage(string $field, int $window = 7, string $as = 'moving_avg'): static
    {
        return $this->pipe(new MovingAvgOperation($field, $window, $as, $this->accessor));
    }

    /**
     * NORMALIZE — scale all values of a field to the [0.0, 1.0] range.
     *
     * Uses min-max normalization: `(value - min) / (max - min)`.
     * When all values are identical (range = 0), every row receives 0.0.
     *
     * ```php
     * ->normalize(field: 'price', as: 'priceScore')
     * ```
     */
    public function normalize(string $field, string $as = 'normalized'): static
    {
        return $this->pipe(new NormalizeOperation($field, $as, $this->accessor));
    }

    /**
     * PIVOT — reshape a flat collection into a cross-tab matrix.
     *
     * Each distinct value of `$cols` becomes a column.
     * Each distinct value of `$rows` becomes a row.
     * Cell values are computed by `$aggregateFn` applied to all matching `$value` entries.
     *
     * ```php
     * Algebra::from($sales)->pivot(rows: 'month', cols: 'region', value: 'revenue');
     * // → [
     * //     ['_row'=>'Jan', 'Nord'=>4200, 'Sud'=>3100, 'Est'=>1800],
     * //     ['_row'=>'Feb', 'Nord'=>5100, 'Sud'=>2900, 'Est'=>2200],
     * // ]
     * ```
     *
     * @param string $rows        field whose values become row labels (`_row` key)
     * @param string $cols        field whose values become column headers
     * @param string $value       field to aggregate within each cell
     * @param string $aggregateFn aggregate function name (default `'sum'`)
     */
    public function pivot(
        string $rows,
        string $cols,
        string $value,
        string $aggregateFn = 'sum',
    ): static {
        return $this->pipe(new PivotOperation(
            rowsKey: $rows,
            colsKey: $cols,
            valueKey: $value,
            aggregateFn: $aggregateFn,
            registry: $this->aggregates,
            accessor: $this->accessor,
        ));
    }

    /**
     * ORDER BY — sort rows by one or multiple keys.
     *
     * ```php
     * ->orderBy('amount', 'desc')
     * ->orderBy([['status', 'asc'], ['amount', 'desc']])  // multi-key
     * ```
     *
     * @param string|array<array{string,string}> $key       field name or array of [field, direction] pairs
     * @param string                             $direction `'asc'` or `'desc'` (used when `$key` is a string)
     */
    public function orderBy(string|array $key, string $direction = 'asc'): static
    {
        $keys = \is_string($key) ? [[$key, $direction]] : $key;

        return $this->pipe(new SortOperation($keys, $this->accessor));
    }

    /**
     * LIMIT — return at most `$limit` rows, optionally skipping `$offset` rows first.
     *
     * ```php
     * ->limit(10)            // first 10 rows
     * ->limit(10, offset: 20) // rows 21–30 (page 3)
     * ```
     */
    public function limit(int $limit, int $offset = 0): static
    {
        return $this->pipe(new SliceOperation($limit, $offset));
    }

    /**
     * TOP N — shorthand for `orderBy($by, 'desc')->limit($n)`.
     *
     * ```php
     * ->topN(5, by: 'amount')  // 5 highest-amount rows
     * ```
     */
    public function topN(int $n, string $by): static
    {
        return $this
            ->pipe(new SortOperation([[$by, 'desc']], $this->accessor))
            ->pipe(new SliceOperation($n));
    }

    /**
     * BOTTOM N — shorthand for `orderBy($by, 'asc')->limit($n)`.
     *
     * ```php
     * ->bottomN(3, by: 'amount')  // 3 lowest-amount rows
     * ```
     */
    public function bottomN(int $n, string $by): static
    {
        return $this
            ->pipe(new SortOperation([[$by, 'asc']], $this->accessor))
            ->pipe(new SliceOperation($n));
    }

    /**
     * DISTINCT — deduplicate rows by a key. First occurrence wins.
     *
     * ```php
     * ->distinct('productId')
     * ```
     */
    public function distinct(string $key): static
    {
        return $this->pipe(new UniqueByOperation($key, $this->accessor));
    }

    /**
     * REINDEX — key the array by a field value for O(1) lookup.
     *
     * ```php
     * $map = Algebra::from($users)->reindex('id')->toArray();
     * $map['42']['name']; // O(1) — no loop needed
     * ```
     */
    public function reindex(string $key): static
    {
        return $this->pipe(new ReindexOperation($key, $this->accessor));
    }

    /**
     * PLUCK — extract a single column into a flat array.
     *
     * ```php
     * Algebra::from($orders)->pluck('id')->toArray();
     * // → [1, 2, 3, 4, 5]
     * ```
     */
    public function pluck(string $field): static
    {
        return $this->pipe(new ExtractOperation($field, $this->accessor));
    }

    /**
     * CHUNK — split the collection into fixed-size sub-arrays.
     *
     * ```php
     * ->chunk(3)
     * // → [[row0,row1,row2], [row3,row4,row5], [row6]]
     * ```
     */
    public function chunk(int $size): static
    {
        return $this->pipe(new ChunkOperation($size));
    }

    /**
     * FILL GAPS — insert default rows for missing entries in a sparse series.
     *
     * Preserves the exact order defined by `$series`.
     *
     * ```php
     * ->fillGaps(
     *     key:     'month',
     *     series:  ['Jan','Feb','Mar','Apr','May','Jun'],
     *     default: ['revenue' => 0, 'orders' => 0],
     * )
     * ```
     *
     * @param string               $key     the field that defines series membership
     * @param array<int, mixed>    $series  ordered list of all expected series values
     * @param array<string, mixed> $default default field values for inserted gap rows
     */
    public function fillGaps(string $key, array $series, array $default = []): static
    {
        return $this->pipe(new FillGapsOperation($key, $series, $default, $this->accessor));
    }

    /**
     * TRANSPOSE — flip rows ↔ columns of a 2-D array.
     *
     * ```php
     * $matrix = [['month'=>'Jan','nord'=>1000,'sud'=>800], ...];
     * ->transpose()->toArray();
     * // → ['month'=>['Jan','Feb'], 'nord'=>[1000,1200], 'sud'=>[800,900]]
     * ```
     */
    public function transpose(): static
    {
        return $this->pipe(new TransposeOperation());
    }

    /**
     * SAMPLE — return N random rows, preserving original relative order.
     *
     * ```php
     * ->sample(10)             // random 10
     * ->sample(10, seed: 42)   // reproducible — same seed = same selection
     * ```
     *
     * @param int      $count number of rows to sample
     * @param int|null $seed  optional seed for reproducible sampling
     */
    public function sample(int $count, ?int $seed = null): static
    {
        return $this->pipe(new SampleOperation($count, $seed));
    }

    /**
     * RANK BY — sort + annotate each row with a sequential rank number.
     *
     * ```php
     * ->rankBy('sales', direction: 'desc', as: 'salesRank')
     * // Each row now has a 'salesRank' field: 1 = highest, N = lowest.
     * ```
     */
    public function rankBy(string $field, string $direction = 'desc', string $as = 'rank'): static
    {
        return $this
            ->pipe(new SortOperation([[$field, $direction]], $this->accessor))
            ->pipe(new WindowOperation(
                fn: 'row_number',
                field: $field,
                partitionBy: null,
                as: $as,
                offset: 1,
                buckets: 4,
                accessor: $this->accessor,
            ));
    }

    /**
     * PARTITION — split rows into two groups in a single pass.
     *
     * Unlike chaining two `where()` calls, this iterates the collection
     * **once** and produces both groups simultaneously.
     *
     * ```php
     * $result = Algebra::from($orders)->partition("item['amount'] > 500");
     *
     * $result->pass();        // high-value orders
     * $result->fail();        // standard orders
     * $result->passRate();    // e.g. 0.27 (27%)
     * ```
     *
     * @param string|\Closure $expression boolean expression or closure
     *
     * @return PartitionResult structured pass/fail result
     */
    public function partition(string|\Closure $expression): PartitionResult
    {
        $op = new PartitionOperation($expression, $this->evaluator);
        $rows = $op->execute($this->source);

        return $rows[0];
    }

    /**
     * Return the declared (pre-optimization) operation list.
     *
     * @return OperationInterface[]
     */
    public function operations(): array
    {
        return $this->operations;
    }

    /**
     * Return the raw source array backing this collection.
     *
     * @return array<int, mixed>
     */
    public function source(): array
    {
        return $this->source;
    }

    /**
     * Normalize any input into a plain PHP array for use as a right-side operand.
     */
    private function toRawArray(mixed $input): array
    {
        return match (true) {
            $input instanceof self => $input->toArray(),
            \is_array($input) => \array_values($input),
            $input instanceof \Traversable => \iterator_to_array($input, preserve_keys: false),
            default => (array) $input,
        };
    }

    public function __clone()
    {
        $this->materialized = null;
    }
}
