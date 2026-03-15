<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Collection;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Collection\MaterializedCollection;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;
use PHPUnit\Framework\TestCase;

final class MaterializedCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        Algebra::reset();
    }

    private function make(array $rows, array $log = []): MaterializedCollection
    {
        return new MaterializedCollection($rows, $log);
    }

    public function testToArrayReturnsRows(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2]]);
        self::assertSame([['id' => 1], ['id' => 2]], $mat->toArray());
    }

    public function testCountReturnsRowCount(): void
    {
        self::assertSame(3, $this->make([['id' => 1], ['id' => 2], ['id' => 3]])->count());
    }

    public function testCountEmpty(): void
    {
        self::assertSame(0, $this->make([])->count());
    }

    public function testIsEmptyTrue(): void
    {
        self::assertTrue($this->make([])->isEmpty());
    }

    public function testIsEmptyFalse(): void
    {
        self::assertFalse($this->make([['id' => 1]])->isEmpty());
    }

    public function testFirstReturnsFirstRow(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2], ['id' => 3]]);
        self::assertSame(1, $mat->first()['id']);
    }

    public function testFirstEmptyReturnsNull(): void
    {
        self::assertNull($this->make([])->first());
    }

    public function testLastReturnsLastRow(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2], ['id' => 3]]);
        self::assertSame(3, $mat->last()['id']);
    }

    public function testLastEmptyReturnsNull(): void
    {
        self::assertNull($this->make([])->last());
    }

    public function testMaterializeReturnsSelf(): void
    {
        $mat = $this->make([['id' => 1]]);
        self::assertSame($mat, $mat->materialize());
    }

    public function testPipeExecutesImmediately(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2]]);
        $piped = $mat->pipe(new FilterOperation("item['id'] == 1", Algebra::evaluator()));

        self::assertCount(1, $piped->toArray());
    }

    public function testPipeReturnsNewInstance(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2]]);
        $piped = $mat->pipe(new FilterOperation("item['id'] == 1", Algebra::evaluator()));

        self::assertNotSame($mat, $piped);
        self::assertCount(2, $mat->toArray());   // original unchanged
        self::assertCount(1, $piped->toArray());
    }

    public function testGetIteratorReturnsArrayIterator(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2]]);
        self::assertInstanceOf(\ArrayIterator::class, $mat->getIterator());
    }

    public function testForeachIteratesAllRows(): void
    {
        $mat = $this->make([['id' => 1], ['id' => 2], ['id' => 3]]);
        $ids = [];
        foreach ($mat as $row) {
            $ids[] = $row['id'];
        }
        self::assertSame([1, 2, 3], $ids);
    }

    public function testExecutionLogEmptyByDefault(): void
    {
        self::assertSame([], $this->make([])->executionLog());
    }

    public function testExecutionLogContainsSteps(): void
    {
        $log = [
            [
                'operation' => 'FilterOperation',
                'signature' => "where(status == 'paid')",
                'input_rows' => 100,
                'output_rows' => 34,
                'duration_ms' => 0.5423,
            ],
        ];

        $mat = $this->make([['id' => 1]], $log);
        self::assertCount(1, $mat->executionLog());
        self::assertSame(100, $mat->executionLog()[0]['input_rows']);
    }

    public function testTotalDurationSumsAllSteps(): void
    {
        $log = [
            ['operation' => 'A', 'signature' => 'a', 'input_rows' => 10, 'output_rows' => 5, 'duration_ms' => 1.5],
            ['operation' => 'B', 'signature' => 'b', 'input_rows' => 5,  'output_rows' => 2, 'duration_ms' => 0.8],
        ];

        $mat = $this->make([], $log);
        self::assertEqualsWithDelta(2.3, $mat->totalDurationMs(), 0.001);
    }

    public function testTotalDurationZeroForEmptyLog(): void
    {
        self::assertSame(0.0, (float) $this->make([])->totalDurationMs());
    }

    public function testMaterializedFromPipeline(): void
    {
        $mat = Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]])
            ->where("item['id'] > 1")
            ->materialize();

        self::assertInstanceOf(MaterializedCollection::class, $mat);
        self::assertCount(2, $mat);
        self::assertCount(1, $mat->executionLog());
    }
}
