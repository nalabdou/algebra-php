<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Integration;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Result\PartitionResult;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end integration tests using only the public Algebra::from() API.
 */
final class AlgebraIntegrationTest extends TestCase
{
    private array $orders;
    private array $users;

    protected function setUp(): void
    {
        Algebra::reset();

        $this->users = [
            ['id' => 10, 'name' => 'Alice', 'tier' => 'vip'],
            ['id' => 20, 'name' => 'Bob',   'tier' => 'standard'],
            ['id' => 30, 'name' => 'Carol', 'tier' => 'premium'],
        ];

        $this->orders = [
            ['id' => 1, 'userId' => 10, 'status' => 'paid',      'amount' => 500, 'region' => 'Nord', 'month' => 'Jan'],
            ['id' => 2, 'userId' => 20, 'status' => 'pending',   'amount' => 200, 'region' => 'Sud',  'month' => 'Jan'],
            ['id' => 3, 'userId' => 10, 'status' => 'paid',      'amount' => 300, 'region' => 'Nord', 'month' => 'Feb'],
            ['id' => 4, 'userId' => 30, 'status' => 'cancelled', 'amount' => 150, 'region' => 'Sud',  'month' => 'Feb'],
            ['id' => 5, 'userId' => 20, 'status' => 'paid',      'amount' => 800, 'region' => 'Est',  'month' => 'Mar'],
        ];
    }

    public function testWhereStringExpression(): void
    {
        $result = Algebra::from($this->orders)->where("item['status'] == 'paid'")->toArray();

        self::assertCount(3, $result);
        foreach ($result as $r) {
            self::assertSame('paid', $r['status']);
        }
    }

    public function testWhereClosure(): void
    {
        $result = Algebra::from($this->orders)->where(static fn ($r) => $r['amount'] > 400)->toArray();

        self::assertCount(2, $result);
    }

    public function testWhereInOperator(): void
    {
        $result = Algebra::from($this->orders)
            ->where("status in ['paid', 'cancelled']")
            ->toArray();

        self::assertCount(4, $result);
    }

    public function testWhereLogicalAnd(): void
    {
        $result = Algebra::from($this->orders)
            ->where("status == 'paid' and amount > 400")
            ->toArray();

        self::assertCount(2, $result); // 500 and 800
    }

    public function testWhereTernary(): void
    {
        $result = Algebra::from($this->orders)
            ->select(static fn ($r) => ['id' => $r['id'], 'label' => $r['amount'] > 500 ? 'high' : 'low'])
            ->where("label == 'high'")
            ->toArray();

        self::assertCount(1, $result);
    }

    public function testSelectClosureTransformsRows(): void
    {
        $result = Algebra::from($this->orders)
            ->select(static fn ($r) => ['id' => $r['id'], 'double' => $r['amount'] * 2])
            ->toArray();

        self::assertSame(1000, $result[0]['double']);
    }

    public function testInnerJoinAttachesRelatedRow(): void
    {
        $result = Algebra::from($this->orders)
            ->innerJoin($this->users, leftKey: 'userId', rightKey: 'id', as: 'owner')
            ->toArray();

        self::assertCount(5, $result);
        self::assertSame('Alice', $result[0]['owner']['name']);
    }

    public function testInnerJoinDropsUnmatched(): void
    {
        $partialUsers = [['id' => 10, 'name' => 'Alice']];

        $result = Algebra::from($this->orders)
            ->innerJoin($partialUsers, leftKey: 'userId', rightKey: 'id', as: 'owner')
            ->toArray();

        self::assertCount(2, $result); // only userId=10 orders
    }

    public function testLeftJoinPreservesAllLeftRows(): void
    {
        $partialUsers = [['id' => 10, 'name' => 'Alice']];

        $result = Algebra::from($this->orders)
            ->leftJoin($partialUsers, on: 'userId=id', as: 'owner')
            ->toArray();

        self::assertCount(5, $result);
        $nullOwners = \array_filter($result, static fn ($r) => null === $r['owner']);
        self::assertCount(3, $nullOwners);
    }

    public function testGroupAndAggregateByStatus(): void
    {
        $result = Algebra::from($this->orders)
            ->groupBy('status')
            ->aggregate(['count' => 'count(*)', 'total' => 'sum(amount)', 'avg' => 'avg(amount)'])
            ->toArray();

        $paid = \array_values(\array_filter($result, static fn ($r) => 'paid' === $r['_group']))[0];

        self::assertSame(3, $paid['count']);
        self::assertSame(1600, $paid['total']);
    }

    public function testPivotCreatesMatrix(): void
    {
        $result = Algebra::from($this->orders)
            ->where("item['status'] == 'paid'")
            ->pivot(rows: 'month', cols: 'region', value: 'amount')
            ->toArray();

        $jan = \array_values(\array_filter($result, static fn ($r) => 'Jan' === $r['_row']))[0];
        self::assertSame(500, $jan['Nord']);
    }

    public function testWindowRunningSum(): void
    {
        $result = Algebra::from($this->orders)
            ->orderBy('id', 'asc')
            ->window('running_sum', field: 'amount', as: 'cumulative')
            ->toArray();

        self::assertSame(500.0, $result[0]['cumulative']);
        self::assertSame(700.0, $result[1]['cumulative']);
    }

    public function testIntersect(): void
    {
        $a = [['id' => 1], ['id' => 2], ['id' => 3]];
        $b = [['id' => 2], ['id' => 3]];

        self::assertSame([2, 3], \array_column(
            Algebra::from($a)->intersect($b, by: 'id')->toArray(),
            'id'
        ));
    }

    public function testExcept(): void
    {
        $all = [['id' => 1], ['id' => 2], ['id' => 3]];
        $dismissed = [['id' => 2]];

        self::assertSame([1, 3], \array_column(
            Algebra::from($all)->except($dismissed, by: 'id')->toArray(),
            'id'
        ));
    }

    public function testUnionDeduplicates(): void
    {
        $a = [['id' => 1], ['id' => 2]];
        $b = [['id' => 2], ['id' => 3]];

        $result = Algebra::from($a)->union($b, by: 'id')->toArray();

        self::assertCount(3, $result);
        self::assertSame([1, 2, 3], \array_column($result, 'id'));
    }

    public function testSymmetricDiff(): void
    {
        $a = [['id' => 1], ['id' => 2]];
        $b = [['id' => 2], ['id' => 3]];

        $ids = \array_column(Algebra::from($a)->symmetricDiff($b, by: 'id')->toArray(), 'id');
        \sort($ids);
        self::assertSame([1, 3], $ids);
    }

    public function testTopN(): void
    {
        $result = Algebra::from($this->orders)->topN(3, by: 'amount')->toArray();

        self::assertCount(3, $result);
        self::assertSame(800, $result[0]['amount']);
    }

    public function testBottomN(): void
    {
        $result = Algebra::from($this->orders)->bottomN(2, by: 'amount')->toArray();

        self::assertCount(2, $result);
        self::assertSame(150, $result[0]['amount']);
    }

    public function testRankBy(): void
    {
        $result = Algebra::from($this->orders)->rankBy('amount')->toArray();
        $top = \array_values(\array_filter($result, static fn ($r) => 800 === $r['amount']))[0];

        self::assertSame(1, $top['rank']);
    }

    public function testPartition(): void
    {
        $result = Algebra::from($this->orders)->partition("item['amount'] > 400");

        self::assertInstanceOf(PartitionResult::class, $result);
        self::assertCount(2, $result->pass());
        self::assertCount(3, $result->fail());
    }

    public function testTally(): void
    {
        $result = Algebra::from($this->orders)->tally('status')->toArray();

        self::assertSame(3, $result['paid']);
        self::assertSame(1, $result['pending']);
    }

    public function testReindex(): void
    {
        $map = Algebra::from($this->users)->reindex('id')->toArray();

        self::assertSame('Alice', $map['10']['name']);
    }

    public function testPluck(): void
    {
        self::assertSame([1, 2, 3, 4, 5], Algebra::from($this->orders)->pluck('id')->toArray());
    }

    public function testDistinct(): void
    {
        $rows = [['cat' => 'a'], ['cat' => 'b'], ['cat' => 'a']];
        $result = Algebra::from($rows)->distinct('cat')->toArray();

        self::assertCount(2, $result);
    }

    public function testChunk(): void
    {
        $chunks = Algebra::from($this->orders)->chunk(2)->toArray();

        self::assertCount(3, $chunks);
        self::assertCount(2, $chunks[0]);
        self::assertCount(1, $chunks[2]);
    }

    public function testFillGaps(): void
    {
        $sparse = [['month' => 'Jan', 'v' => 1], ['month' => 'Mar', 'v' => 3]];

        $result = Algebra::from($sparse)
            ->fillGaps('month', ['Jan', 'Feb', 'Mar'], ['v' => 0])
            ->toArray();

        self::assertSame('Feb', $result[1]['month']);
        self::assertSame(0, $result[1]['v']);
    }

    public function testSample(): void
    {
        $result = Algebra::from($this->orders)->sample(3, seed: 42)->toArray();
        self::assertCount(3, $result);
    }

    public function testFullPipeline(): void
    {
        $result = Algebra::from($this->orders)
            ->where("item['status'] == 'paid'")
            ->innerJoin($this->users, leftKey: 'userId', rightKey: 'id', as: 'owner')
            ->groupBy('region')
            ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
            ->orderBy('revenue', 'desc')
            ->toArray();

        self::assertNotEmpty($result);
        self::assertGreaterThanOrEqual($result[1]['revenue'], $result[0]['revenue']);
    }

    public function testParallel(): void
    {
        $results = Algebra::parallel([
            'paid' => Algebra::from($this->orders)->where("item['status'] == 'paid'"),
            'tally' => Algebra::from($this->orders)->tally('status'),
        ]);

        self::assertCount(3, $results['paid']);
        self::assertSame(3, $results['tally']['paid']);
    }

    public function testPipeConvenience(): void
    {
        $result = Algebra::pipe(
            $this->orders,
            static fn ($c) => $c->where("item['status'] == 'paid'")->topN(2, by: 'amount')
        );

        self::assertCount(2, $result);
    }

    public function testResetCreatesFreshSingletons(): void
    {
        $before = Algebra::evaluator();
        Algebra::reset();
        $after = Algebra::evaluator();

        self::assertNotSame($before, $after);
    }
}
