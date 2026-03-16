<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Collection;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Collection\CollectionFactory;
use Nalabdou\Algebra\Collection\MaterializedCollection;
use Nalabdou\Algebra\Collection\RelationalCollection;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelationalCollection::class)]
#[CoversClass(MaterializedCollection::class)]
#[CoversClass(CollectionFactory::class)]
final class RelationalCollectionTest extends TestCase
{
    protected function setUp(): void
    {
        Algebra::reset();
    }

    public function testFromArrayCreatesLazyCollection(): void
    {
        $col = Algebra::from([['id' => 1], ['id' => 2]]);

        self::assertInstanceOf(RelationalCollection::class, $col);
    }

    public function testFromGeneratorWrapsCorrectly(): void
    {
        $gen = (static function () {
            yield ['id' => 1];
            yield ['id' => 2];
        })();

        $result = Algebra::from($gen)->toArray();

        self::assertCount(2, $result);
    }

    public function testFromTraversableWrapsCorrectly(): void
    {
        $obj = new \ArrayObject([['id' => 1], ['id' => 2], ['id' => 3]]);
        $result = Algebra::from($obj)->toArray();

        self::assertCount(3, $result);
    }

    public function testFromSplFixedArray(): void
    {
        $fixed = new \SplFixedArray(2);
        $fixed[0] = ['id' => 1];
        $fixed[1] = ['id' => 2];

        self::assertCount(2, Algebra::from($fixed)->toArray());
    }

    public function testOperationsNotExecutedUntilIterated(): void
    {
        $executed = false;

        $col = Algebra::from([['id' => 1]])->pipe(
            new FilterOperation(static function (mixed $r) use (&$executed): bool {
                $executed = true;

                return true;
            }, Algebra::evaluator())
        );

        self::assertFalse($executed, 'Operation was executed before iteration');

        $col->toArray();

        self::assertTrue($executed, 'Operation was never executed');
    }

    public function testPipeReturnsNewImmutableInstance(): void
    {
        $original = Algebra::from([['id' => 1]]);
        $piped = $original->pipe(new FilterOperation("item['id'] == 1", Algebra::evaluator()));

        self::assertNotSame($original, $piped);
        self::assertCount(0, $original->operations());
        self::assertCount(1, $piped->operations());
    }

    public function testOriginalSourceUnchangedAfterBranch(): void
    {
        $base = Algebra::from([['v' => 1], ['v' => 2], ['v' => 3]]);
        $filtered1 = $base->where("item['v'] == 1");
        $filtered2 = $base->where("item['v'] == 2");

        self::assertCount(3, $base->toArray());
        self::assertCount(1, $filtered1->toArray());
        self::assertCount(1, $filtered2->toArray());
    }

    public function testMaterializeCachesResult(): void
    {
        $col = Algebra::from([['id' => 1], ['id' => 2]]);
        $mat1 = $col->materialize();
        $mat2 = $col->materialize();

        self::assertSame($mat1, $mat2, 'materialize() should return the same cached instance');
    }

    public function testPipeInvalidatesCache(): void
    {
        $col = Algebra::from([['id' => 1], ['id' => 2]]);
        $mat1 = $col->materialize(); // 2 rows

        $piped = $col->pipe(new FilterOperation("item['id'] == 1", Algebra::evaluator()));
        $mat2 = $piped->materialize(); // 1 row

        self::assertCount(2, $mat1);
        self::assertCount(1, $mat2);
    }

    public function testExecutionLogContainsExpectedKeys(): void
    {
        $log = Algebra::from([['id' => 1], ['id' => 2]])
            ->where("item['id'] == 1")
            ->materialize()
            ->executionLog();

        self::assertCount(1, $log);
        self::assertArrayHasKey('operation', $log[0]);
        self::assertArrayHasKey('signature', $log[0]);
        self::assertArrayHasKey('input_rows', $log[0]);
        self::assertArrayHasKey('output_rows', $log[0]);
        self::assertArrayHasKey('duration_ms', $log[0]);
    }

    public function testExecutionLogRowCountsAreCorrect(): void
    {
        $log = Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]])
            ->where("item['id'] > 1")
            ->materialize()
            ->executionLog();

        self::assertSame(3, $log[0]['input_rows']);
        self::assertSame(2, $log[0]['output_rows']);
    }

    public function testTotalDurationIsNonNegative(): void
    {
        $mat = Algebra::from(\range(1, 100))
            ->materialize();

        self::assertGreaterThanOrEqual(0.0, $mat->totalDurationMs());
    }

    public function testMaterializedFirstAndLast(): void
    {
        $mat = Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]])->materialize();

        self::assertSame(1, $mat->first()['id']);
        self::assertSame(3, $mat->last()['id']);
    }

    public function testMaterializedIsEmptyOnEmpty(): void
    {
        self::assertTrue(Algebra::from([])->materialize()->isEmpty());
    }

    public function testMaterializedIsNotEmpty(): void
    {
        self::assertFalse(Algebra::from([['id' => 1]])->materialize()->isEmpty());
    }

    public function testMaterializedFirstOnEmptyReturnsNull(): void
    {
        self::assertNull(Algebra::from([])->materialize()->first());
    }

    public function testMaterializedLastOnEmptyReturnsNull(): void
    {
        self::assertNull(Algebra::from([])->materialize()->last());
    }

    public function testForeachIteratesAllRows(): void
    {
        $ids = [];
        foreach (Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]]) as $row) {
            $ids[] = $row['id'];
        }

        self::assertSame([1, 2, 3], $ids);
    }

    public function testCountMethod(): void
    {
        self::assertSame(3, Algebra::from([['id' => 1], ['id' => 2], ['id' => 3]])->count());
    }

    public function testPartitionSplitsInSinglePass(): void
    {
        $result = Algebra::from([
            ['amount' => 100], ['amount' => 600],
            ['amount' => 300], ['amount' => 800],
        ])->partition("item['amount'] > 500");

        self::assertCount(2, $result->pass());
        self::assertCount(2, $result->fail());
        self::assertSame(4, $result->totalCount());
        self::assertSame(0.5, $result->passRate());
    }

    public function testOperationsReturnsEmptyForFreshCollection(): void
    {
        self::assertSame([], Algebra::from([])->operations());
    }

    public function testSourceReturnsRawArray(): void
    {
        $source = [['id' => 1], ['id' => 2]];
        self::assertSame($source, Algebra::from($source)->source());
    }
}
