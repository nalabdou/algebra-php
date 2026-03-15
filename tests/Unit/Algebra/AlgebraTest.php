<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Algebra;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Collection\CollectionFactory;
use Nalabdou\Algebra\Collection\RelationalCollection;
use Nalabdou\Algebra\Expression\ExpressionCache;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;
use Nalabdou\Algebra\Planner\QueryPlanner;
use PHPUnit\Framework\TestCase;

final class AlgebraTest extends TestCase
{
    protected function setUp(): void
    {
        Algebra::reset();
    }

    public function testFactoryReturnsCollectionFactory(): void
    {
        self::assertInstanceOf(CollectionFactory::class, Algebra::factory());
    }

    public function testFactoryIsSingleton(): void
    {
        self::assertSame(Algebra::factory(), Algebra::factory());
    }

    public function testEvaluatorReturnsExpressionEvaluator(): void
    {
        self::assertInstanceOf(ExpressionEvaluator::class, Algebra::evaluator());
    }

    public function testEvaluatorIsSingleton(): void
    {
        self::assertSame(Algebra::evaluator(), Algebra::evaluator());
    }

    public function testCacheReturnsExpressionCache(): void
    {
        self::assertInstanceOf(ExpressionCache::class, Algebra::cache());
    }

    public function testCacheIsSingleton(): void
    {
        self::assertSame(Algebra::cache(), Algebra::cache());
    }

    public function testAccessorReturnsPropertyAccessor(): void
    {
        self::assertInstanceOf(PropertyAccessor::class, Algebra::accessor());
    }

    public function testAccessorIsSingleton(): void
    {
        self::assertSame(Algebra::accessor(), Algebra::accessor());
    }

    public function testAggregatesReturnsRegistry(): void
    {
        self::assertInstanceOf(AggregateRegistry::class, Algebra::aggregates());
    }

    public function testAggregatesIsSingleton(): void
    {
        self::assertSame(Algebra::aggregates(), Algebra::aggregates());
    }

    public function testPlannerReturnsQueryPlanner(): void
    {
        self::assertInstanceOf(QueryPlanner::class, Algebra::planner());
    }

    public function testPlannerIsSingleton(): void
    {
        self::assertSame(Algebra::planner(), Algebra::planner());
    }

    public function testResetClearsAllSingletons(): void
    {
        $before = [
            'factory' => Algebra::factory(),
            'evaluator' => Algebra::evaluator(),
            'cache' => Algebra::cache(),
            'accessor' => Algebra::accessor(),
            'aggregates' => Algebra::aggregates(),
            'planner' => Algebra::planner(),
        ];

        Algebra::reset();

        self::assertNotSame($before['factory'], Algebra::factory());
        self::assertNotSame($before['evaluator'], Algebra::evaluator());
        self::assertNotSame($before['cache'], Algebra::cache());
        self::assertNotSame($before['accessor'], Algebra::accessor());
        self::assertNotSame($before['aggregates'], Algebra::aggregates());
        self::assertNotSame($before['planner'], Algebra::planner());
    }

    public function testFromArrayReturnsRelationalCollection(): void
    {
        self::assertInstanceOf(RelationalCollection::class, Algebra::from([]));
    }

    public function testFromEmptyArrayWorks(): void
    {
        self::assertSame([], Algebra::from([])->toArray());
    }

    public function testFromWithPipeline(): void
    {
        $result = Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]])
            ->where("item['id'] > 1")
            ->toArray();

        self::assertCount(2, $result);
    }

    public function testPipeBuildsAndExecutes(): void
    {
        $result = Algebra::pipe(
            [['v' => 1], ['v' => 2], ['v' => 3]],
            static fn ($c) => $c->where(static fn ($r) => $r['v'] > 1)
        );

        self::assertCount(2, $result);
    }

    public function testPipeReturnsArray(): void
    {
        $result = Algebra::pipe([['id' => 1]], static fn ($c) => $c);
        self::assertIsArray($result);
    }

    public function testParallelRunsMultiplePipelines(): void
    {
        $data = [['status' => 'paid', 'amount' => 100], ['status' => 'pending', 'amount' => 200]];

        $results = Algebra::parallel([
            'paid' => Algebra::from($data)->where("item['status'] == 'paid'"),
            'pending' => Algebra::from($data)->where("item['status'] == 'pending'"),
        ]);

        self::assertArrayHasKey('paid', $results);
        self::assertArrayHasKey('pending', $results);
        self::assertCount(1, $results['paid']);
        self::assertCount(1, $results['pending']);
    }

    public function testParallelEmptyPipelines(): void
    {
        $results = Algebra::parallel([]);
        self::assertSame([], $results);
    }

    public function testParallelPreservesKeys(): void
    {
        $results = Algebra::parallel([
            'first' => Algebra::from([['id' => 1]]),
            'second' => Algebra::from([['id' => 2]]),
        ]);

        self::assertArrayHasKey('first', $results);
        self::assertArrayHasKey('second', $results);
    }

    public function testExpressionCachePopulatedAfterEvaluation(): void
    {
        $cache = Algebra::cache();
        self::assertSame(0, $cache->size());
    }

    public function testExpressionCacheResetClearsEntries(): void
    {
        Algebra::reset();
        self::assertSame(0, Algebra::cache()->size());
    }
}
