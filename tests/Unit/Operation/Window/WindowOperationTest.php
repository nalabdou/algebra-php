<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Operation\Window;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Operation\Window\MovingAvgOperation;
use Nalabdou\Algebra\Operation\Window\NormalizeOperation;
use Nalabdou\Algebra\Operation\Window\WindowOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WindowOperation::class)]
#[CoversClass(MovingAvgOperation::class)]
#[CoversClass(NormalizeOperation::class)]
final class WindowOperationTest extends TestCase
{
    private \Nalabdou\Algebra\Expression\PropertyAccessor $accessor;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->accessor = Algebra::accessor();
    }

    private function window(
        string $fn,
        array $rows,
        string $field = 'amount',
        string $as = 'result',
        int $offset = 1,
        int $buckets = 4,
        ?string $partitionBy = null,
    ): array {
        return (new WindowOperation($fn, $field, $partitionBy, $as, $offset, $buckets, $this->accessor))
            ->execute($rows);
    }

    public function testRunningSum(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = $this->window('running_sum', $rows);

        self::assertSame(100.0, $result[0]['result']);
        self::assertSame(300.0, $result[1]['result']);
        self::assertSame(600.0, $result[2]['result']);
    }

    public function testRunningAvg(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = $this->window('running_avg', $rows);

        self::assertSame(100.0, $result[0]['result']);
        self::assertSame(150.0, $result[1]['result']);
        self::assertSame(200.0, $result[2]['result']);
    }

    public function testRunningCount(): void
    {
        $rows = [['v' => 'a'], ['v' => 'b'], ['v' => 'c']];
        $result = $this->window('running_count', $rows, field: 'v');

        self::assertSame(1, $result[0]['result']);
        self::assertSame(2, $result[1]['result']);
        self::assertSame(3, $result[2]['result']);
    }

    public function testRunningDiffNullForFirstRow(): void
    {
        $rows = [['amount' => 100], ['amount' => 150], ['amount' => 130]];
        $result = $this->window('running_diff', $rows);

        self::assertNull($result[0]['result']);
        self::assertSame(50.0, $result[1]['result']);
        self::assertSame(-20.0, $result[2]['result']);
    }

    public function testRowNumber(): void
    {
        $rows = [['v' => 'a'], ['v' => 'b'], ['v' => 'c']];
        $result = $this->window('row_number', $rows, field: 'v');

        self::assertSame(1, $result[0]['result']);
        self::assertSame(2, $result[1]['result']);
        self::assertSame(3, $result[2]['result']);
    }

    public function testRankWithGaps(): void
    {
        $rows = [['amount' => 300], ['amount' => 100], ['amount' => 200]];
        $result = $this->window('rank', $rows);

        self::assertSame(1, $result[0]['result']);
        self::assertSame(3, $result[1]['result']);
        self::assertSame(2, $result[2]['result']);
    }

    public function testDenseRankNoGaps(): void
    {
        $rows = [['v' => 3], ['v' => 1], ['v' => 3], ['v' => 2]];
        $result = $this->window('dense_rank', $rows, field: 'v');

        self::assertSame(1, $result[0]['result']); // 3 = rank 1
        self::assertSame(3, $result[1]['result']); // 1 = rank 3
        self::assertSame(1, $result[2]['result']); // 3 = rank 1 again (no gap)
        self::assertSame(2, $result[3]['result']); // 2 = rank 2
    }

    public function testLagNullForFirstRow(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = $this->window('lag', $rows);

        self::assertNull($result[0]['result']);
        self::assertSame(100, $result[1]['result']);
        self::assertSame(200, $result[2]['result']);
    }

    public function testLagCustomOffset(): void
    {
        $rows = [['v' => 10], ['v' => 20], ['v' => 30], ['v' => 40]];
        $result = $this->window('lag', $rows, field: 'v', offset: 2);

        self::assertNull($result[0]['result']);
        self::assertNull($result[1]['result']);
        self::assertSame(10, $result[2]['result']);
    }

    public function testLeadNullForLastRow(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = $this->window('lead', $rows);

        self::assertSame(200, $result[0]['result']);
        self::assertSame(300, $result[1]['result']);
        self::assertNull($result[2]['result']);
    }

    public function testNtileAssignsBuckets(): void
    {
        $rows = \array_map(static fn ($i) => ['v' => $i], \range(1, 8));
        $result = $this->window('ntile', $rows, field: 'v', buckets: 4);

        self::assertSame(1, $result[0]['result']);
        self::assertSame(1, $result[1]['result']);
        self::assertSame(4, $result[7]['result']);
    }

    public function testCumeDistBetweenZeroAndOne(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = $this->window('cume_dist', $rows);

        foreach ($result as $row) {
            self::assertGreaterThan(0.0, $row['result']);
            self::assertLessThanOrEqual(1.0, $row['result']);
        }
    }

    public function testRunningSumPartitionedByUser(): void
    {
        $rows = [
            ['userId' => 1, 'amount' => 100],
            ['userId' => 2, 'amount' => 200],
            ['userId' => 1, 'amount' => 150],
            ['userId' => 2, 'amount' => 50],
        ];

        $result = $this->window('running_sum', $rows, partitionBy: 'userId');

        $u1 = \array_values(\array_filter($result, static fn ($r) => 1 === $r['userId']));
        $u2 = \array_values(\array_filter($result, static fn ($r) => 2 === $r['userId']));

        self::assertSame(100.0, $u1[0]['result']);
        self::assertSame(250.0, $u1[1]['result']);
        self::assertSame(200.0, $u2[0]['result']);
        self::assertSame(250.0, $u2[1]['result']);
    }

    public function testThrowsOnUnknownFunction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->window('unknown_fn', [['v' => 1]]);
    }

    public function testWindowSelectivityIsOne(): void
    {
        $op = new WindowOperation('running_sum', 'v', null, 'r', 1, 4, $this->accessor);
        self::assertSame(1.0, $op->selectivity());
    }

    public function testWindowSignature(): void
    {
        $op = new WindowOperation('running_sum', 'amount', null, 'cum', 1, 4, $this->accessor);
        self::assertStringContainsString('window', $op->signature());
        self::assertStringContainsString('running_sum', $op->signature());
    }

    public function testMovingAvgNullUntilWindowFull(): void
    {
        $rows = [
            ['amount' => 100], ['amount' => 200],
            ['amount' => 300], ['amount' => 400],
        ];

        $result = (new MovingAvgOperation('amount', 3, 'ma', $this->accessor))
            ->execute($rows);

        self::assertNull($result[0]['ma']);
        self::assertNull($result[1]['ma']);
        self::assertEqualsWithDelta(200.0, $result[2]['ma'], 0.001);
        self::assertEqualsWithDelta(300.0, $result[3]['ma'], 0.001);
    }

    public function testMovingAvgWindowOneEqualsOriginal(): void
    {
        $rows = [['amount' => 100], ['amount' => 200], ['amount' => 300]];
        $result = (new MovingAvgOperation('amount', 1, 'ma', $this->accessor))
            ->execute($rows);

        self::assertEqualsWithDelta(100.0, $result[0]['ma'], 0.001);
        self::assertEqualsWithDelta(200.0, $result[1]['ma'], 0.001);
    }

    public function testMovingAvgThrowsOnWindowBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MovingAvgOperation('amount', 0, 'ma', $this->accessor);
    }

    public function testMovingAvgEmptyInput(): void
    {
        $result = (new MovingAvgOperation('amount', 3, 'ma', $this->accessor))->execute([]);
        self::assertSame([], $result);
    }

    public function testNormalizeScalesToZeroOne(): void
    {
        $rows = [['price' => 100], ['price' => 200], ['price' => 300]];
        $result = (new NormalizeOperation('price', 'score', $this->accessor))->execute($rows);

        self::assertSame(0.0, $result[0]['score']);
        self::assertSame(0.5, $result[1]['score']);
        self::assertSame(1.0, $result[2]['score']);
    }

    public function testNormalizeUniformValuesReturnsZero(): void
    {
        $rows = [['price' => 50], ['price' => 50], ['price' => 50]];
        $result = (new NormalizeOperation('price', 'score', $this->accessor))->execute($rows);

        foreach ($result as $row) {
            self::assertSame(0.0, $row['score']);
        }
    }

    public function testNormalizeEmptyInputReturnsEmpty(): void
    {
        $result = (new NormalizeOperation('price', 'score', $this->accessor))->execute([]);
        self::assertSame([], $result);
    }

    public function testNormalizeAllValuesBetweenZeroAndOne(): void
    {
        $rows = \array_map(static fn ($i) => ['v' => $i], \range(1, 100));
        $result = (new NormalizeOperation('v', 's', $this->accessor))->execute($rows);

        foreach ($result as $row) {
            self::assertGreaterThanOrEqual(0.0, $row['s']);
            self::assertLessThanOrEqual(1.0, $row['s']);
        }
    }
}
