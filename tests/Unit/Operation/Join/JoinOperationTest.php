<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Operation\Join;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Operation\Join\AntiJoinOperation;
use Nalabdou\Algebra\Operation\Join\CrossJoinOperation;
use Nalabdou\Algebra\Operation\Join\JoinOperation;
use Nalabdou\Algebra\Operation\Join\LeftJoinOperation;
use Nalabdou\Algebra\Operation\Join\SemiJoinOperation;
use Nalabdou\Algebra\Operation\Join\ZipOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JoinOperation::class)]
#[CoversClass(LeftJoinOperation::class)]
#[CoversClass(SemiJoinOperation::class)]
#[CoversClass(AntiJoinOperation::class)]
#[CoversClass(CrossJoinOperation::class)]
#[CoversClass(ZipOperation::class)]
final class JoinOperationTest extends TestCase
{
    private \Nalabdou\Algebra\Expression\PropertyAccessor $accessor;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->accessor = Algebra::accessor();
    }

    public function testInnerJoinMergesMatchedRows(): void
    {
        $orders = [['id' => 1, 'userId' => 10, 'amount' => 100]];
        $users = [['id' => 10, 'name' => 'Alice']];

        $result = (new JoinOperation($users, 'userId', 'id', 'owner', $this->accessor))
            ->execute($orders);

        self::assertCount(1, $result);
        self::assertSame('Alice', $result[0]['owner']['name']);
    }

    public function testInnerJoinDropsUnmatchedLeftRows(): void
    {
        $orders = [['id' => 1, 'userId' => 10], ['id' => 2, 'userId' => 99]];
        $users = [['id' => 10, 'name' => 'Alice']];

        $result = (new JoinOperation($users, 'userId', 'id', 'owner', $this->accessor))
            ->execute($orders);

        self::assertCount(1, $result);
    }

    public function testInnerJoinSupportsOneToMany(): void
    {
        $orders = [['id' => 1, 'tagId' => 5]];
        $tags = [['id' => 5, 'name' => 'A'], ['id' => 5, 'name' => 'B']];

        $result = (new JoinOperation($tags, 'tagId', 'id', 'tag', $this->accessor))
            ->execute($orders);

        self::assertCount(2, $result);
    }

    public function testInnerJoinEmptyRightReturnsEmpty(): void
    {
        $orders = [['id' => 1, 'userId' => 10]];

        $result = (new JoinOperation([], 'userId', 'id', 'owner', $this->accessor))
            ->execute($orders);

        self::assertEmpty($result);
    }

    public function testInnerJoinSignature(): void
    {
        $op = new JoinOperation([], 'userId', 'id', 'owner', $this->accessor);
        self::assertStringContainsString('inner_join', $op->signature());
        self::assertStringContainsString('userId', $op->signature());
    }

    public function testInnerJoinSelectivity(): void
    {
        $op = new JoinOperation([], 'userId', 'id', 'owner', $this->accessor);
        self::assertIsFloat($op->selectivity());
        self::assertGreaterThan(0.0, $op->selectivity());
    }

    public function testLeftJoinPreservesUnmatchedWithNull(): void
    {
        $orders = [['id' => 1, 'userId' => 10], ['id' => 2, 'userId' => 99]];
        $users = [['id' => 10, 'name' => 'Alice']];

        $result = (new LeftJoinOperation($users, 'userId', 'id', 'owner', $this->accessor))
            ->execute($orders);

        self::assertCount(2, $result);
        self::assertSame('Alice', $result[0]['owner']['name']);
        self::assertNull($result[1]['owner']);
    }

    public function testLeftJoinPreservesAllLeftRows(): void
    {
        $orders = [['id' => 1, 'userId' => 1], ['id' => 2, 'userId' => 2], ['id' => 3, 'userId' => 3]];

        $result = (new LeftJoinOperation([], 'userId', 'id', 'u', $this->accessor))
            ->execute($orders);

        self::assertCount(3, $result);
    }

    public function testLeftJoinSelectivityIsOne(): void
    {
        $op = new LeftJoinOperation([], 'a', 'b', 'c', $this->accessor);
        self::assertSame(1.0, $op->selectivity());
    }

    public function testSemiJoinKeepsMatchedWithoutMergingData(): void
    {
        $orders = [['id' => 1, 'userId' => 10], ['id' => 2, 'userId' => 99]];
        $payments = [['orderId' => 1]];

        $result = (new SemiJoinOperation($payments, 'id', 'orderId', $this->accessor))
            ->execute($orders);

        self::assertCount(1, $result);
        self::assertSame(1, $result[0]['id']);
        self::assertArrayNotHasKey('orderId', $result[0]);
    }

    public function testSemiJoinEmptyRightReturnsEmpty(): void
    {
        $orders = [['id' => 1], ['id' => 2]];

        $result = (new SemiJoinOperation([], 'id', 'id', $this->accessor))
            ->execute($orders);

        self::assertEmpty($result);
    }

    public function testSemiJoinSelectivityLessThanOne(): void
    {
        $op = new SemiJoinOperation([], 'id', 'id', $this->accessor);
        self::assertLessThan(1.0, $op->selectivity());
    }

    public function testAntiJoinKeepsUnmatchedRows(): void
    {
        $orders = [['id' => 1], ['id' => 2], ['id' => 3]];
        $payments = [['orderId' => 1], ['orderId' => 3]];

        $result = (new AntiJoinOperation($payments, 'id', 'orderId', $this->accessor))
            ->execute($orders);

        self::assertCount(1, $result);
        self::assertSame(2, $result[0]['id']);
    }

    public function testAntiJoinFullRightReturnsEmpty(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $right = [['id' => 1], ['id' => 2]];

        $result = (new AntiJoinOperation($right, 'id', 'id', $this->accessor))
            ->execute($rows);

        self::assertEmpty($result);
    }

    public function testAntiJoinEmptyRightReturnsAll(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $result = (new AntiJoinOperation([], 'id', 'id', $this->accessor))->execute($rows);

        self::assertCount(2, $result);
    }

    public function testCrossJoinProducesCartesianProduct(): void
    {
        $sizes = [['size' => 'S'], ['size' => 'M']];
        $colours = [['colour' => 'Red'], ['colour' => 'Blue']];

        $result = (new CrossJoinOperation($colours))->execute($sizes);

        self::assertCount(4, $result);
        self::assertSame('S', $result[0]['size']);
        self::assertSame('Red', $result[0]['colour']);
    }

    public function testCrossJoinPrefixesPreventKeyCollisions(): void
    {
        $a = [['id' => 1]];
        $b = [['id' => 2]];

        $result = (new CrossJoinOperation($b, leftPrefix: 'a_', rightPrefix: 'b_'))
            ->execute($a);

        self::assertArrayHasKey('a_id', $result[0]);
        self::assertArrayHasKey('b_id', $result[0]);
        self::assertArrayNotHasKey('id', $result[0]);
    }

    public function testCrossJoinEmptyRightReturnsEmpty(): void
    {
        $rows = [['id' => 1]];
        $result = (new CrossJoinOperation([]))->execute($rows);

        self::assertEmpty($result);
    }

    public function testCrossJoinSelectivityReflectsRightSize(): void
    {
        $right = \array_fill(0, 10, ['id' => 1]);
        $op = new CrossJoinOperation($right);
        self::assertSame(10.0, $op->selectivity());
    }

    public function testZipPairsRowsByPosition(): void
    {
        $labels = [['label' => 'Revenue'], ['label' => 'Orders']];
        $values = [['value' => 5400],      ['value' => 120]];

        $result = (new ZipOperation($values))->execute($labels);

        self::assertCount(2, $result);
        self::assertSame('Revenue', $result[0]['label']);
        self::assertSame(5400, $result[0]['value']);
    }

    public function testZipTruncatesToShorterSide(): void
    {
        $a = [['x' => 1], ['x' => 2], ['x' => 3]];
        $b = [['y' => 4]];

        $result = (new ZipOperation($b))->execute($a);

        self::assertCount(1, $result);
    }

    public function testZipWithNamedSides(): void
    {
        $a = [['v' => 1]];
        $b = [['v' => 2]];

        $result = (new ZipOperation($b, leftAs: 'l_', rightAs: 'r_'))->execute($a);

        self::assertArrayHasKey('l_v', $result[0]);
        self::assertArrayHasKey('r_v', $result[0]);
    }
}
