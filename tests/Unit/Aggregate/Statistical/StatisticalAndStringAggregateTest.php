<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Aggregate\Statistical;

use Nalabdou\Algebra\Aggregate\Positional\FirstAggregate;
use Nalabdou\Algebra\Aggregate\Positional\LastAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\CountDistinctAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\CumeDistAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\ModeAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\NtileAggregate;
use Nalabdou\Algebra\Aggregate\String\BoolAndAggregate;
use Nalabdou\Algebra\Aggregate\String\BoolOrAggregate;
use Nalabdou\Algebra\Aggregate\String\StringAggAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModeAggregate::class)]
#[CoversClass(CountDistinctAggregate::class)]
#[CoversClass(NtileAggregate::class)]
#[CoversClass(CumeDistAggregate::class)]
#[CoversClass(FirstAggregate::class)]
#[CoversClass(LastAggregate::class)]
#[CoversClass(StringAggAggregate::class)]
#[CoversClass(BoolAndAggregate::class)]
#[CoversClass(BoolOrAggregate::class)]
final class StatisticalAndStringAggregateTest extends TestCase
{
    public function testModeReturnsMostFrequent(): void
    {
        $agg = new ModeAggregate();
        self::assertSame('mode', $agg->name());
        self::assertSame('a', $agg->compute(['a', 'b', 'a', 'c', 'a']));
    }

    public function testModePreservesOriginalType(): void
    {
        // Must return int 1, not string '1'
        self::assertSame(1, (new ModeAggregate())->compute([1, 2, 1, 3]));
    }

    public function testModeEmptyReturnsNull(): void
    {
        self::assertNull((new ModeAggregate())->compute([]));
    }

    public function testModeSingleValue(): void
    {
        self::assertSame(42, (new ModeAggregate())->compute([42]));
    }

    public function testCountDistinctBasic(): void
    {
        $agg = new CountDistinctAggregate();
        self::assertSame('count_distinct', $agg->name());
        self::assertSame(3, $agg->compute([1, 2, 2, 3, 1]));
    }

    public function testCountDistinctEmptyIsZero(): void
    {
        self::assertSame(0, (new CountDistinctAggregate())->compute([]));
    }

    public function testCountDistinctAllSame(): void
    {
        self::assertSame(1, (new CountDistinctAggregate())->compute(['x', 'x', 'x']));
    }

    public function testCountDistinctAllUnique(): void
    {
        self::assertSame(4, (new CountDistinctAggregate())->compute([1, 2, 3, 4]));
    }

    public function testNtileReturnsBoundaries(): void
    {
        $agg = new NtileAggregate(4);
        self::assertSame('ntile', $agg->name());
        $result = $agg->compute([10, 20, 30, 40, 50, 60, 70, 80]);
        self::assertIsArray($result);
        self::assertCount(3, $result); // 4 buckets = 3 boundaries
    }

    public function testNtileEmptyReturnsNull(): void
    {
        self::assertNull((new NtileAggregate(4))->compute([]));
    }

    public function testNtileDefaultIs4Buckets(): void
    {
        $agg = new NtileAggregate();
        $result = $agg->compute(\range(1, 8));
        self::assertCount(3, $result);
    }

    public function testCumeDistReturnsArray(): void
    {
        $agg = new CumeDistAggregate();
        self::assertSame('cume_dist', $agg->name());
        $result = $agg->compute([1, 2, 3, 4, 5]);
        self::assertIsArray($result);
    }

    public function testCumeDistFractionsBetweenZeroAndOne(): void
    {
        $result = (new CumeDistAggregate())->compute([10, 20, 30]);
        foreach ($result as $fraction) {
            self::assertGreaterThan(0.0, $fraction);
            self::assertLessThanOrEqual(1.0, $fraction);
        }
    }

    public function testCumeDistMaxIsOne(): void
    {
        $result = (new CumeDistAggregate())->compute([1, 2, 3]);
        self::assertEqualsWithDelta(1.0, \max($result), 0.001);
    }

    public function testCumeDistEmptyReturnsNull(): void
    {
        self::assertNull((new CumeDistAggregate())->compute([]));
    }

    public function testFirstReturnsFirstValue(): void
    {
        $agg = new FirstAggregate();
        self::assertSame('first', $agg->name());
        self::assertSame('a', $agg->compute(['a', 'b', 'c']));
    }

    public function testFirstEmptyReturnsNull(): void
    {
        self::assertNull((new FirstAggregate())->compute([]));
    }

    public function testFirstSingleValue(): void
    {
        self::assertSame(42, (new FirstAggregate())->compute([42]));
    }

    public function testLastReturnsLastValue(): void
    {
        $agg = new LastAggregate();
        self::assertSame('last', $agg->name());
        self::assertSame('c', $agg->compute(['a', 'b', 'c']));
    }

    public function testLastEmptyReturnsNull(): void
    {
        self::assertNull((new LastAggregate())->compute([]));
    }

    public function testLastSingleValue(): void
    {
        self::assertSame(99, (new LastAggregate())->compute([99]));
    }

    public function testStringAggConcatenates(): void
    {
        $agg = new StringAggAggregate(', ');
        self::assertSame('string_agg', $agg->name());
        self::assertSame('Alice, Bob, Carol', $agg->compute(['Alice', 'Bob', 'Carol']));
    }

    public function testStringAggExcludesEmptyStrings(): void
    {
        self::assertSame('Alice, Carol',
            (new StringAggAggregate(', '))->compute(['Alice', '', 'Carol']));
    }

    public function testStringAggAllEmptyReturnsNull(): void
    {
        self::assertNull((new StringAggAggregate())->compute(['', '']));
    }

    public function testStringAggEmptyInputReturnsNull(): void
    {
        self::assertNull((new StringAggAggregate())->compute([]));
    }

    public function testStringAggCustomSeparator(): void
    {
        self::assertSame('a|b|c', (new StringAggAggregate('|'))->compute(['a', 'b', 'c']));
    }

    public function testBoolAndAllTruthy(): void
    {
        $agg = new BoolAndAggregate();
        self::assertSame('bool_and', $agg->name());
        self::assertTrue($agg->compute([true, true, 1, 'yes']));
    }

    public function testBoolAndAnyFalsyIsFalse(): void
    {
        self::assertFalse((new BoolAndAggregate())->compute([true, false, true]));
    }

    public function testBoolAndEmptyReturnsNull(): void
    {
        self::assertNull((new BoolAndAggregate())->compute([]));
    }

    public function testBoolAndSingleFalse(): void
    {
        self::assertFalse((new BoolAndAggregate())->compute([false]));
    }

    public function testBoolOrAnyTruthy(): void
    {
        $agg = new BoolOrAggregate();
        self::assertSame('bool_or', $agg->name());
        self::assertTrue($agg->compute([false, true, false]));
    }

    public function testBoolOrAllFalsyIsFalse(): void
    {
        self::assertFalse((new BoolOrAggregate())->compute([false, 0, '', null]));
    }

    public function testBoolOrEmptyReturnsNull(): void
    {
        self::assertNull((new BoolOrAggregate())->compute([]));
    }

    public function testBoolOrSingleTrue(): void
    {
        self::assertTrue((new BoolOrAggregate())->compute([true]));
    }
}
