<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Operation\Utility;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Algebra;
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
use PHPUnit\Framework\TestCase;

final class UtilityOperationTest extends TestCase
{
    private \Nalabdou\Algebra\Expression\ExpressionEvaluator $evaluator;
    private \Nalabdou\Algebra\Expression\PropertyAccessor $accessor;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->evaluator = Algebra::evaluator();
        $this->accessor = Algebra::accessor();
    }

    public function testFilterStringExpression(): void
    {
        $rows = [['status' => 'paid'], ['status' => 'pending'], ['status' => 'paid']];
        $result = (new FilterOperation("item['status'] == 'paid'", $this->evaluator))->execute($rows);

        self::assertCount(2, $result);
    }

    public function testFilterClosure(): void
    {
        $rows = [['v' => 1], ['v' => 2], ['v' => 3]];
        $result = (new FilterOperation(static fn ($r) => $r['v'] > 1, $this->evaluator))->execute($rows);

        self::assertCount(2, $result);
    }

    public function testFilterEmptyInput(): void
    {
        $result = (new FilterOperation("item['v'] == 1", $this->evaluator))->execute([]);
        self::assertEmpty($result);
    }

    public function testFilterReindexesKeys(): void
    {
        $rows = [['v' => 1], ['v' => 2], ['v' => 3]];
        $result = (new FilterOperation(static fn ($r) => $r['v'] > 1, $this->evaluator))->execute($rows);

        self::assertArrayHasKey(0, $result);
        self::assertArrayHasKey(1, $result);
    }

    public function testFilterSelectivityLessThanOne(): void
    {
        $op = new FilterOperation('v == 1', $this->evaluator);
        self::assertLessThan(1.0, $op->selectivity());
    }

    public function testFilterSignature(): void
    {
        $op = new FilterOperation("item['status'] == 'paid'", $this->evaluator);
        self::assertStringContainsString('where', $op->signature());
    }

    public function testSelectTransformsRows(): void
    {
        $rows = [['name' => 'alice'], ['name' => 'bob']];
        $result = (new MapOperation(static fn ($r) => \strtoupper($r['name']), $this->evaluator))->execute($rows);

        self::assertSame(['ALICE', 'BOB'], $result);
    }

    public function testSelectClosureDetection(): void
    {
        $closureOp = new MapOperation(static fn ($r) => $r, $this->evaluator);
        $stringOp = new MapOperation('id', $this->evaluator);

        self::assertTrue($closureOp->isClosureBased());
        self::assertFalse($stringOp->isClosureBased());
    }

    public function testSelectGetClosure(): void
    {
        $fn = static fn ($r) => $r;
        $op = new MapOperation($fn, $this->evaluator);

        self::assertSame($fn, $op->getClosure());
    }

    public function testSelectGetClosureThrowsForString(): void
    {
        $this->expectException(\LogicException::class);
        (new MapOperation('id', $this->evaluator))->getClosure();
    }

    public function testOrderByAscending(): void
    {
        $rows = [['v' => 3], ['v' => 1], ['v' => 2]];
        $result = (new SortOperation([['v', 'asc']], $this->accessor))->execute($rows);

        self::assertSame([1, 2, 3], \array_column($result, 'v'));
    }

    public function testOrderByDescending(): void
    {
        $rows = [['v' => 1], ['v' => 3], ['v' => 2]];
        $result = (new SortOperation([['v', 'desc']], $this->accessor))->execute($rows);

        self::assertSame([3, 2, 1], \array_column($result, 'v'));
    }

    public function testOrderByMultiKey(): void
    {
        $rows = [
            ['status' => 'paid',    'amount' => 300],
            ['status' => 'paid',    'amount' => 100],
            ['status' => 'pending', 'amount' => 200],
        ];

        $result = (new SortOperation([['status', 'asc'], ['amount', 'asc']], $this->accessor))->execute($rows);

        self::assertSame('paid', $result[0]['status']);
        self::assertSame(100, $result[0]['amount']);
        self::assertSame('pending', $result[2]['status']);
    }

    public function testSortKeysAccessor(): void
    {
        $op = new SortOperation([['v', 'asc']], $this->accessor);
        self::assertSame([['v', 'asc']], $op->keys());
    }

    public function testLimitFirstN(): void
    {
        $result = (new SliceOperation(3))->execute(\range(1, 10));
        self::assertCount(3, $result);
    }

    public function testLimitWithOffset(): void
    {
        $rows = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]];
        $result = (new SliceOperation(2, 2))->execute($rows);

        self::assertCount(2, $result);
        self::assertSame(3, $result[0]['id']);
    }

    public function testLimitThrowsOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SliceOperation(-1);
    }

    public function testLimitOffsetAccessors(): void
    {
        $op = new SliceOperation(10, 5);
        self::assertSame(10, $op->limit());
        self::assertSame(5, $op->offset());
    }

    public function testDistinctRemovesDuplicates(): void
    {
        $rows = [['id' => 1, 'v' => 'first'], ['id' => 2], ['id' => 1, 'v' => 'second']];
        $result = (new UniqueByOperation('id', $this->accessor))->execute($rows);

        self::assertCount(2, $result);
        self::assertSame('first', $result[0]['v']);
    }

    public function testDistinctEmptyInput(): void
    {
        $result = (new UniqueByOperation('id', $this->accessor))->execute([]);
        self::assertEmpty($result);
    }

    public function testReindexKeysByField(): void
    {
        $rows = [['id' => 10, 'name' => 'Alice'], ['id' => 20, 'name' => 'Bob']];
        $result = (new ReindexOperation('id', $this->accessor))->execute($rows);

        self::assertArrayHasKey('10', $result);
        self::assertSame('Alice', $result['10']['name']);
    }

    public function testReindexLastWinsOnDuplicate(): void
    {
        $rows = [['id' => 1, 'v' => 'first'], ['id' => 1, 'v' => 'second']];
        $result = (new ReindexOperation('id', $this->accessor))->execute($rows);

        self::assertSame('second', $result['1']['v']);
    }

    public function testPluckExtractsColumn(): void
    {
        $rows = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];
        $result = (new ExtractOperation('id', $this->accessor))->execute($rows);

        self::assertSame([1, 2], $result);
    }

    public function testChunkSplitsIntoPages(): void
    {
        $result = (new ChunkOperation(2))->execute(\range(1, 5));

        self::assertCount(3, $result);
        self::assertCount(2, $result[0]);
        self::assertCount(1, $result[2]);
    }

    public function testChunkThrowsOnSizeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChunkOperation(0);
    }

    public function testFillGapsInsertsMissingEntries(): void
    {
        $rows = [['month' => 'Jan', 'revenue' => 1000], ['month' => 'Mar', 'revenue' => 1500]];

        $result = (new FillGapsOperation(
            'month', ['Jan', 'Feb', 'Mar'], ['revenue' => 0], $this->accessor
        ))->execute($rows);

        self::assertCount(3, $result);
        self::assertSame('Feb', $result[1]['month']);
        self::assertSame(0, $result[1]['revenue']);
        self::assertSame(1000, $result[0]['revenue']);
    }

    public function testFillGapsPreservesSeriesOrder(): void
    {
        $rows = [['m' => 'Mar'], ['m' => 'Jan']];
        $result = (new FillGapsOperation('m', ['Jan', 'Feb', 'Mar'], [], $this->accessor))->execute($rows);

        self::assertSame(['Jan', 'Feb', 'Mar'], \array_column($result, 'm'));
    }

    public function testTransposeFlipsRowsColumns(): void
    {
        $rows = [['month' => 'Jan', 'revenue' => 1000], ['month' => 'Feb', 'revenue' => 1200]];

        $result = (new TransposeOperation())->execute($rows);

        self::assertSame(['Jan', 'Feb'], $result['month']);
        self::assertSame([1000, 1200], $result['revenue']);
    }

    public function testTransposeEmptyInput(): void
    {
        $result = (new TransposeOperation())->execute([]);
        self::assertSame([], $result);
    }

    public function testTransposeSignature(): void
    {
        $op = new TransposeOperation();
        self::assertSame('transpose()', $op->signature());
    }

    public function testSampleReturnsCorrectCount(): void
    {
        $result = (new SampleOperation(10))->execute(\range(1, 100));
        self::assertCount(10, $result);
    }

    public function testSampleReproducibleWithSeed(): void
    {
        $rows = \range(1, 50);
        $r1 = (new SampleOperation(5, seed: 42))->execute($rows);
        $r2 = (new SampleOperation(5, seed: 42))->execute($rows);

        self::assertSame($r1, $r2);
    }

    public function testSampleReturnsAllWhenExceedsSize(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $result = (new SampleOperation(100))->execute($rows);

        self::assertCount(2, $result);
    }

    public function testSampleThrowsOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SampleOperation(-1);
    }

    public function testPivotCreatesCrossTab(): void
    {
        $sales = [
            ['month' => 'Jan', 'region' => 'Nord', 'revenue' => 1000],
            ['month' => 'Jan', 'region' => 'Sud',  'revenue' => 800],
            ['month' => 'Feb', 'region' => 'Nord', 'revenue' => 1200],
        ];

        $result = (new PivotOperation(
            'month', 'region', 'revenue', 'sum', new AggregateRegistry(), $this->accessor
        ))->execute($sales);

        $jan = \array_values(\array_filter($result, static fn ($r) => 'Jan' === $r['_row']))[0];

        self::assertSame(1000, $jan['Nord']);
        self::assertSame(800, $jan['Sud']);
    }

    public function testPivotMissingCellIsNull(): void
    {
        $sales = [['month' => 'Jan', 'region' => 'Nord', 'revenue' => 1000]];

        $result = (new PivotOperation(
            'month', 'region', 'revenue', 'sum', new AggregateRegistry(), $this->accessor
        ))->execute($sales);

        // Only Nord column exists — accessing Sud should not be set
        self::assertArrayNotHasKey('Sud', $result[0]);
    }

    public function testPivotSignature(): void
    {
        $op = new PivotOperation('month', 'region', 'revenue', 'sum', new AggregateRegistry(), $this->accessor);
        self::assertStringContainsString('pivot', $op->signature());
    }
}
