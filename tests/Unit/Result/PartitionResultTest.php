<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Result;

use Nalabdou\Algebra\Result\PartitionResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PartitionResult::class)]
final class PartitionResultTest extends TestCase
{
    private PartitionResult $result;

    protected function setUp(): void
    {
        $this->result = new PartitionResult(
            pass: [['id' => 1, 'amount' => 600], ['id' => 2, 'amount' => 800]],
            fail: [['id' => 3, 'amount' => 200], ['id' => 4, 'amount' => 100], ['id' => 5, 'amount' => 300]],
        );
    }

    public function testPassReturnsPassingRows(): void
    {
        $pass = $this->result->pass();
        self::assertCount(2, $pass);
        self::assertSame(1, $pass[0]['id']);
    }

    public function testFailReturnsFailingRows(): void
    {
        $fail = $this->result->fail();
        self::assertCount(3, $fail);
        self::assertSame(3, $fail[0]['id']);
    }

    public function testPassCount(): void
    {
        self::assertSame(2, $this->result->passCount());
    }

    public function testFailCount(): void
    {
        self::assertSame(3, $this->result->failCount());
    }

    public function testTotalCount(): void
    {
        self::assertSame(5, $this->result->totalCount());
    }

    public function testPassRate(): void
    {
        self::assertEqualsWithDelta(0.4, $this->result->passRate(), 0.001);
    }

    public function testPassRateAllPass(): void
    {
        $r = new PartitionResult([['id' => 1]], []);
        self::assertSame(1.0, $r->passRate());
    }

    public function testPassRateAllFail(): void
    {
        $r = new PartitionResult([], [['id' => 1]]);
        self::assertSame(0.0, $r->passRate());
    }

    public function testPassRateEmptyIsZero(): void
    {
        $r = new PartitionResult([], []);
        self::assertSame(0.0, $r->passRate());
    }

    public function testToArrayStructure(): void
    {
        $arr = $this->result->toArray();
        self::assertArrayHasKey('pass', $arr);
        self::assertArrayHasKey('fail', $arr);
        self::assertCount(2, $arr['pass']);
        self::assertCount(3, $arr['fail']);
    }

    public function testPassPlusFailEqualsTotal(): void
    {
        self::assertSame(
            $this->result->totalCount(),
            $this->result->passCount() + $this->result->failCount()
        );
    }

    public function testEmptyPartition(): void
    {
        $r = new PartitionResult([], []);
        self::assertEmpty($r->pass());
        self::assertEmpty($r->fail());
        self::assertSame(0, $r->totalCount());
    }
}
