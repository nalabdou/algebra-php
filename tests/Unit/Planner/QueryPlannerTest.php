<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Planner;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Operation\Join\JoinOperation;
use Nalabdou\Algebra\Operation\Join\LeftJoinOperation;
use Nalabdou\Algebra\Operation\Join\SemiJoinOperation;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;
use Nalabdou\Algebra\Operation\Utility\MapOperation;
use Nalabdou\Algebra\Operation\Utility\SortOperation;
use Nalabdou\Algebra\Planner\Pass\CollapseConsecutiveMaps;
use Nalabdou\Algebra\Planner\Pass\EliminateRedundantSort;
use Nalabdou\Algebra\Planner\Pass\PushFilterBeforeAntiJoin;
use Nalabdou\Algebra\Planner\Pass\PushFilterBeforeJoin;
use Nalabdou\Algebra\Planner\QueryPlanner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryPlanner::class)]
#[CoversClass(PushFilterBeforeJoin::class)]
#[CoversClass(PushFilterBeforeAntiJoin::class)]
#[CoversClass(EliminateRedundantSort::class)]
#[CoversClass(CollapseConsecutiveMaps::class)]
final class QueryPlannerTest extends TestCase
{
    private QueryPlanner $planner;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->planner = Algebra::planner();
    }

    public function testPushesFilterBeforeInnerJoin(): void
    {
        $join = new JoinOperation([], 'userId', 'id', 'u', Algebra::accessor());
        $filter = new FilterOperation("item['status'] == 'paid'", Algebra::evaluator());

        $optimized = $this->planner->optimize([$join, $filter]);

        self::assertInstanceOf(FilterOperation::class, $optimized[0]);
        self::assertInstanceOf(JoinOperation::class, $optimized[1]);
    }

    public function testPushesFilterBeforeLeftJoin(): void
    {
        $join = new LeftJoinOperation([], 'userId', 'id', 'u', Algebra::accessor());
        $filter = new FilterOperation("item['amount'] > 100", Algebra::evaluator());

        $optimized = $this->planner->optimize([$join, $filter]);

        self::assertInstanceOf(FilterOperation::class, $optimized[0]);
        self::assertInstanceOf(LeftJoinOperation::class, $optimized[1]);
    }

    public function testAccumulatesMultipleFiltersBeforeJoin(): void
    {
        $join = new JoinOperation([], 'userId', 'id', 'u', Algebra::accessor());
        $filter1 = new FilterOperation("item['status'] == 'paid'", Algebra::evaluator());
        $filter2 = new FilterOperation("item['amount'] > 100", Algebra::evaluator());

        $optimized = $this->planner->optimize([$join, $filter1, $filter2]);

        self::assertInstanceOf(FilterOperation::class, $optimized[0]);
        self::assertInstanceOf(FilterOperation::class, $optimized[1]);
        self::assertInstanceOf(JoinOperation::class, $optimized[2]);
    }

    public function testPushesFilterBeforeSemiJoin(): void
    {
        $semi = new SemiJoinOperation([], 'userId', 'id', Algebra::accessor());
        $filter = new FilterOperation("item['amount'] > 100", Algebra::evaluator());

        $optimized = $this->planner->optimize([$semi, $filter]);

        self::assertInstanceOf(FilterOperation::class, $optimized[0]);
        self::assertInstanceOf(SemiJoinOperation::class, $optimized[1]);
    }

    public function testEliminatesConsecutiveSorts(): void
    {
        $sort1 = new SortOperation([['amount', 'asc']], Algebra::accessor());
        $sort2 = new SortOperation([['amount', 'desc']], Algebra::accessor());

        $optimized = $this->planner->optimize([$sort1, $sort2]);

        self::assertCount(1, $optimized);
        self::assertSame($sort2, $optimized[0]);
    }

    public function testKeepsNonConsecutiveSorts(): void
    {
        $sort1 = new SortOperation([['amount', 'asc']], Algebra::accessor());
        $filter = new FilterOperation("item['v'] > 1", Algebra::evaluator());
        $sort2 = new SortOperation([['name', 'asc']], Algebra::accessor());

        $optimized = $this->planner->optimize([$sort1, $filter, $sort2]);

        self::assertCount(3, $optimized);
    }

    public function testCollapsesConsecutiveClosureMaps(): void
    {
        $planner = new QueryPlanner(Algebra::evaluator());
        $map1 = new MapOperation(static fn ($r) => \array_merge($r, ['a' => 1]), Algebra::evaluator());
        $map2 = new MapOperation(static fn ($r) => \array_merge($r, ['b' => 2]), Algebra::evaluator());

        $optimized = $planner->optimize([$map1, $map2]);

        self::assertCount(1, $optimized);
        self::assertInstanceOf(MapOperation::class, $optimized[0]);

        // Verify the composed closure applies both transforms
        $result = $optimized[0]->execute([['id' => 1]]);
        self::assertSame(1, $result[0]['a']);
        self::assertSame(2, $result[0]['b']);
    }

    public function testDoesNotCollapseStringMaps(): void
    {
        $planner = new QueryPlanner(Algebra::evaluator());
        $map1 = new MapOperation('id', Algebra::evaluator());
        $map2 = new MapOperation('id', Algebra::evaluator());

        $optimized = $planner->optimize([$map1, $map2]);

        self::assertCount(2, $optimized); // string maps not collapsed
    }

    public function testExplainReportsChangedTrueWhenReordered(): void
    {
        $join = new JoinOperation([], 'userId', 'id', 'u', Algebra::accessor());
        $filter = new FilterOperation("item['status'] == 'paid'", Algebra::evaluator());

        $plan = $this->planner->explain([$join, $filter]);

        self::assertTrue($plan['changed']);
        self::assertNotEquals($plan['original'], $plan['optimized']);
        self::assertNotEmpty($plan['passes']);
    }

    public function testExplainReportsChangedFalseWhenAlreadyOptimal(): void
    {
        $filter = new FilterOperation("item['status'] == 'paid'", Algebra::evaluator());
        $join = new JoinOperation([], 'userId', 'id', 'u', Algebra::accessor());

        $plan = $this->planner->explain([$filter, $join]);

        self::assertFalse($plan['changed']);
    }

    public function testExplainContainsAllRequiredKeys(): void
    {
        $plan = $this->planner->explain([]);

        self::assertArrayHasKey('original', $plan);
        self::assertArrayHasKey('optimized', $plan);
        self::assertArrayHasKey('changed', $plan);
        self::assertArrayHasKey('passes', $plan);
    }
}
