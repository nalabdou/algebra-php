<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Operation\Aggregate;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Operation\Aggregate\AggregateOperation;
use Nalabdou\Algebra\Operation\Aggregate\GroupByOperation;
use Nalabdou\Algebra\Operation\Aggregate\PartitionOperation;
use Nalabdou\Algebra\Operation\Aggregate\TallyOperation;
use Nalabdou\Algebra\Result\PartitionResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GroupByOperation::class)]
#[CoversClass(AggregateOperation::class)]
#[CoversClass(TallyOperation::class)]
#[CoversClass(PartitionOperation::class)]
#[CoversClass(AggregateRegistry::class)]
#[CoversClass(PartitionResult::class)]
final class AggregateOperationTest extends TestCase
{
    private \Nalabdou\Algebra\Expression\ExpressionEvaluator $evaluator;
    private \Nalabdou\Algebra\Expression\PropertyAccessor $accessor;
    private AggregateRegistry $registry;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->evaluator = Algebra::evaluator();
        $this->accessor = Algebra::accessor();
        $this->registry = Algebra::aggregates();
    }

    public function testGroupByCreatesKeyedGroups(): void
    {
        $rows = [
            ['status' => 'paid',    'amount' => 100],
            ['status' => 'pending', 'amount' => 200],
            ['status' => 'paid',    'amount' => 300],
        ];

        $result = (new GroupByOperation('status', $this->evaluator))->execute($rows);

        self::assertArrayHasKey('paid', $result);
        self::assertArrayHasKey('pending', $result);
        self::assertCount(2, $result['paid']);
        self::assertCount(1, $result['pending']);
    }

    public function testGroupByClosure(): void
    {
        $rows = [['date' => '2024-01-15'], ['date' => '2024-02-10'], ['date' => '2024-01-20']];
        $result = (new GroupByOperation(static fn ($r) => \substr($r['date'], 0, 7), $this->evaluator))->execute($rows);

        self::assertCount(2, $result['2024-01']);
        self::assertCount(1, $result['2024-02']);
    }

    public function testGroupByPreservesAllRows(): void
    {
        $rows = [['v' => 1], ['v' => 2], ['v' => 1], ['v' => 3]];
        $result = (new GroupByOperation('v', $this->evaluator))->execute($rows);

        self::assertSame(4, \array_sum(\array_map('count', $result)));
    }

    public function testGroupBySignature(): void
    {
        $op = new GroupByOperation('status', $this->evaluator);
        self::assertStringContainsString('group_by', $op->signature());
    }

    public function testAggregateCountSumAvg(): void
    {
        $grouped = [
            'paid' => [['amount' => 100], ['amount' => 300]],
            'pending' => [['amount' => 200]],
        ];

        $result = (new AggregateOperation(
            ['count' => 'count(*)', 'total' => 'sum(amount)', 'avg' => 'avg(amount)'],
            $this->registry,
            $this->evaluator
        ))->execute($grouped);

        $paid = \array_values(\array_filter($result, static fn ($r) => 'paid' === $r['_group']))[0];

        self::assertSame(2, $paid['count']);
        self::assertSame(400, $paid['total']);
        self::assertEqualsWithDelta(200.0, $paid['avg'], 0.001);
    }

    public function testAggregateMinMaxMedian(): void
    {
        $rows = [['v' => 3], ['v' => 1], ['v' => 5], ['v' => 2], ['v' => 4]];
        $result = (new AggregateOperation(
            ['min' => 'min(v)', 'max' => 'max(v)', 'median' => 'median(v)'],
            $this->registry,
            $this->evaluator
        ))->execute($rows);

        self::assertSame(1, $result[0]['min']);
        self::assertSame(5, $result[0]['max']);
        self::assertSame(3.0, $result[0]['median']);
    }

    public function testAggregateStringAggSpec(): void
    {
        $grouped = ['order_1' => [['name' => 'Laptop'], ['name' => 'Mouse']]];

        $result = (new AggregateOperation(
            ['products' => 'string_agg(name, ", ")'],
            $this->registry,
            $this->evaluator
        ))->execute($grouped);

        self::assertSame('Laptop, Mouse', $result[0]['products']);
    }

    public function testAggregateBoolAndSpec(): void
    {
        $grouped = [
            'a' => [['shipped' => true],  ['shipped' => true]],
            'b' => [['shipped' => true],  ['shipped' => false]],
        ];

        $result = (new AggregateOperation(
            ['all_shipped' => 'bool_and(shipped)'],
            $this->registry,
            $this->evaluator
        ))->execute($grouped);

        $a = \array_values(\array_filter($result, static fn ($r) => 'a' === $r['_group']))[0];
        $b = \array_values(\array_filter($result, static fn ($r) => 'b' === $r['_group']))[0];

        self::assertTrue($a['all_shipped']);
        self::assertFalse($b['all_shipped']);
    }

    public function testAggregatePercentileSpec(): void
    {
        $rows = [['v' => 1], ['v' => 2], ['v' => 3], ['v' => 4], ['v' => 5]];
        $result = (new AggregateOperation(
            ['p80' => 'percentile(v, 0.8)'],
            $this->registry,
            $this->evaluator
        ))->execute($rows);

        self::assertSame(4.0, $result[0]['p80']);
    }

    public function testAggregateFlatArrayUsesStarGroup(): void
    {
        $rows = [['amount' => 100], ['amount' => 200]];
        $result = (new AggregateOperation(
            ['total' => 'sum(amount)'],
            $this->registry,
            $this->evaluator
        ))->execute($rows);

        self::assertSame('*', $result[0]['_group']);
        self::assertSame(300, $result[0]['total']);
    }

    public function testAggregateThrowsOnInvalidSpec(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AggregateOperation(
            ['bad' => 'not_a_valid_spec'],
            $this->registry,
            $this->evaluator
        ))->execute([['v' => 1]]);
    }

    public function testAggregateSignature(): void
    {
        $op = new AggregateOperation(['total' => 'sum(amount)'], $this->registry, $this->evaluator);
        self::assertStringContainsString('aggregate', $op->signature());
    }

    public function testTallyCountsAndSortsDescending(): void
    {
        $rows = [
            ['status' => 'paid'], ['status' => 'pending'], ['status' => 'paid'],
            ['status' => 'paid'], ['status' => 'cancelled'],
        ];

        $result = (new TallyOperation('status', $this->accessor))->execute($rows);

        self::assertSame(['paid' => 3, 'pending' => 1, 'cancelled' => 1], $result);
        self::assertSame('paid', \array_key_first($result));
    }

    public function testTallyReturnsEmptyOnEmptyInput(): void
    {
        $result = (new TallyOperation('status', $this->accessor))->execute([]);
        self::assertSame([], $result);
    }

    public function testTallySignature(): void
    {
        $op = new TallyOperation('status', $this->accessor);
        self::assertStringContainsString('tally', $op->signature());
    }

    public function testPartitionSplitsPassAndFail(): void
    {
        $rows = [['amount' => 800], ['amount' => 200], ['amount' => 1200], ['amount' => 450]];
        $op = new PartitionOperation("item['amount'] > 500", $this->evaluator);
        $result = $op->execute($rows);

        self::assertInstanceOf(PartitionResult::class, $result[0]);
        self::assertSame(2, $result[0]->passCount());
        self::assertSame(2, $result[0]->failCount());
    }

    public function testPartitionPassRate(): void
    {
        $rows = \array_merge(\array_fill(0, 3, ['v' => 1]), \array_fill(0, 1, ['v' => 0]));
        $result = (new PartitionOperation(static fn ($r) => 1 === $r['v'], $this->evaluator))
            ->execute($rows)[0];

        self::assertEqualsWithDelta(0.75, $result->passRate(), 0.001);
    }

    public function testPartitionResultToArray(): void
    {
        $rows = [['v' => 1], ['v' => 0]];
        $result = (new PartitionOperation(static fn ($r) => 1 === $r['v'], $this->evaluator))
            ->execute($rows)[0];

        $arr = $result->toArray();
        self::assertArrayHasKey('pass', $arr);
        self::assertArrayHasKey('fail', $arr);
    }

    public function testPartitionTotalCount(): void
    {
        $rows = [['v' => 1], ['v' => 0], ['v' => 1]];
        $result = (new PartitionOperation(static fn ($r) => 1 === $r['v'], $this->evaluator))
            ->execute($rows)[0];

        self::assertSame(3, $result->totalCount());
    }

    public function testRegistryHasAll18Functions(): void
    {
        $expected = [
            'count', 'sum', 'avg', 'min', 'max', 'median', 'stddev', 'variance', 'percentile',
            'mode', 'count_distinct', 'ntile', 'cume_dist',
            'first', 'last',
            'string_agg', 'bool_and', 'bool_or',
        ];

        foreach ($expected as $name) {
            self::assertTrue($this->registry->has($name), "Registry missing: {$name}");
        }
    }

    public function testRegistryGetThrowsOnUnknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->get('nonexistent_function');
    }

    public function testRegistryRegisterCustomOverwrites(): void
    {
        $custom = new class implements \Nalabdou\Algebra\Contract\AggregateInterface {
            public function name(): string
            {
                return 'sum';
            }

            public function compute(array $values): mixed
            {
                return 9999;
            }
        };

        $this->registry->register($custom);
        self::assertSame(9999, $this->registry->get('sum')->compute([1, 2, 3]));
    }

    public function testRegistryAllReturnsArray(): void
    {
        $all = $this->registry->all();
        self::assertIsArray($all);
        self::assertCount(18, $all);
    }
}
