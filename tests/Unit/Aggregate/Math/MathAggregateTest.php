<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Aggregate\Math;

use Nalabdou\Algebra\Aggregate\Math\AvgAggregate;
use Nalabdou\Algebra\Aggregate\Math\CountAggregate;
use Nalabdou\Algebra\Aggregate\Math\MaxAggregate;
use Nalabdou\Algebra\Aggregate\Math\MedianAggregate;
use Nalabdou\Algebra\Aggregate\Math\MinAggregate;
use Nalabdou\Algebra\Aggregate\Math\PercentileAggregate;
use Nalabdou\Algebra\Aggregate\Math\StddevAggregate;
use Nalabdou\Algebra\Aggregate\Math\SumAggregate;
use Nalabdou\Algebra\Aggregate\Math\VarianceAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CountAggregate::class)]
#[CoversClass(SumAggregate::class)]
#[CoversClass(AvgAggregate::class)]
#[CoversClass(MinAggregate::class)]
#[CoversClass(MaxAggregate::class)]
#[CoversClass(MedianAggregate::class)]
#[CoversClass(StddevAggregate::class)]
#[CoversClass(VarianceAggregate::class)]
#[CoversClass(PercentileAggregate::class)]
final class MathAggregateTest extends TestCase
{
    public function testCountNonEmpty(): void
    {
        $agg = new CountAggregate();
        self::assertSame('count', $agg->name());
        self::assertSame(3, $agg->compute([1, 2, 3]));
    }

    public function testCountEmpty(): void
    {
        self::assertSame(0, (new CountAggregate())->compute([]));
    }

    public function testCountSingleValue(): void
    {
        self::assertSame(1, (new CountAggregate())->compute([42]));
    }

    public function testSumIntegers(): void
    {
        $agg = new SumAggregate();
        self::assertSame('sum', $agg->name());
        self::assertSame(6, $agg->compute([1, 2, 3]));
    }

    public function testSumEmptyIsZero(): void
    {
        self::assertSame(0, (new SumAggregate())->compute([]));
    }

    public function testSumFloats(): void
    {
        self::assertEqualsWithDelta(6.5, (new SumAggregate())->compute([1.5, 2.0, 3.0]), 0.001);
    }

    public function testSumNegativeValues(): void
    {
        self::assertSame(0, (new SumAggregate())->compute([-5, 5]));
    }

    public function testAvgBasic(): void
    {
        $agg = new AvgAggregate();
        self::assertSame('avg', $agg->name());
        self::assertSame(2.0, $agg->compute([1, 2, 3]));
    }

    public function testAvgEmptyReturnsNull(): void
    {
        self::assertNull((new AvgAggregate())->compute([]));
    }

    public function testAvgSingleValue(): void
    {
        self::assertSame(42.0, (new AvgAggregate())->compute([42]));
    }

    public function testMinBasic(): void
    {
        $agg = new MinAggregate();
        self::assertSame('min', $agg->name());
        self::assertSame(1, $agg->compute([3, 1, 2]));
    }

    public function testMinEmptyReturnsNull(): void
    {
        self::assertNull((new MinAggregate())->compute([]));
    }

    public function testMinSingleValue(): void
    {
        self::assertSame(5, (new MinAggregate())->compute([5]));
    }

    public function testMinAllSame(): void
    {
        self::assertSame(3, (new MinAggregate())->compute([3, 3, 3]));
    }

    public function testMaxBasic(): void
    {
        $agg = new MaxAggregate();
        self::assertSame('max', $agg->name());
        self::assertSame(3, $agg->compute([1, 3, 2]));
    }

    public function testMaxEmptyReturnsNull(): void
    {
        self::assertNull((new MaxAggregate())->compute([]));
    }

    public function testMaxNegativeValues(): void
    {
        self::assertSame(-1, (new MaxAggregate())->compute([-5, -1, -3]));
    }

    public function testMedianOddCount(): void
    {
        $agg = new MedianAggregate();
        self::assertSame('median', $agg->name());
        self::assertSame(3.0, $agg->compute([5, 1, 3]));
    }

    public function testMedianEvenCountAveragesMiddles(): void
    {
        self::assertSame(2.5, (new MedianAggregate())->compute([1, 2, 3, 4]));
    }

    public function testMedianEmptyReturnsNull(): void
    {
        self::assertNull((new MedianAggregate())->compute([]));
    }

    public function testMedianSingleValue(): void
    {
        self::assertSame(7.0, (new MedianAggregate())->compute([7]));
    }

    public function testMedianAlreadySorted(): void
    {
        self::assertSame(3.0, (new MedianAggregate())->compute([1, 2, 3, 4, 5]));
    }

    public function testStddevKnownDataset(): void
    {
        $agg = new StddevAggregate();
        self::assertSame('stddev', $agg->name());
        // Sample stddev with Bessel's correction (n-1): sqrt(variance) = sqrt(32/7) ≈ 2.138
        self::assertEqualsWithDelta(2.138089935299395, $agg->compute([2, 4, 4, 4, 5, 5, 7, 9]), 0.0001);
    }

    public function testStddevReturnsNullForSingleValue(): void
    {
        self::assertNull((new StddevAggregate())->compute([42]));
    }

    public function testStddevReturnsNullForEmpty(): void
    {
        self::assertNull((new StddevAggregate())->compute([]));
    }

    public function testStddevZeroForUniformValues(): void
    {
        // All same value → stddev = 0
        $result = (new StddevAggregate())->compute([5, 5, 5, 5]);
        self::assertEqualsWithDelta(0.0, $result, 0.001);
    }

    public function testVarianceKnownDataset(): void
    {
        $agg = new VarianceAggregate();
        self::assertSame('variance', $agg->name());
        // Sample variance with Bessel's correction (n-1): sum_sq/7 = 32/7 ≈ 4.571
        self::assertEqualsWithDelta(4.571428571428571, $agg->compute([2, 4, 4, 4, 5, 5, 7, 9]), 0.0001);
    }

    public function testVarianceReturnsNullForSingle(): void
    {
        self::assertNull((new VarianceAggregate())->compute([1]));
    }

    public function testVarianceReturnsNullForEmpty(): void
    {
        self::assertNull((new VarianceAggregate())->compute([]));
    }

    public function testVarianceEqualsStddevSquared(): void
    {
        $values = [2, 4, 4, 4, 5, 5, 7, 9];
        $std = (new StddevAggregate())->compute($values);
        $var = (new VarianceAggregate())->compute($values);

        self::assertEqualsWithDelta($std ** 2, $var, 0.0001);
    }

    public function testPercentileP50MatchesMedian(): void
    {
        $agg = new PercentileAggregate(0.5);
        self::assertSame('percentile', $agg->name());
        $values = [1, 2, 3, 4, 5];
        $p50 = $agg->compute($values);
        $median = (new MedianAggregate())->compute($values);

        self::assertSame($median, $p50);
    }

    public function testPercentileP90(): void
    {
        self::assertSame(9.0, (new PercentileAggregate(0.9))->compute(\range(1, 10)));
    }

    public function testPercentileP0IsMinimum(): void
    {
        $values = [5, 2, 8, 1, 9];
        $p0 = (new PercentileAggregate(0.0))->compute($values);
        self::assertSame(1.0, $p0);
    }

    public function testPercentileP100IsMaximum(): void
    {
        $values = [5, 2, 8, 1, 9];
        $p100 = (new PercentileAggregate(1.0))->compute($values);
        self::assertSame(9.0, $p100);
    }

    public function testPercentileEmptyReturnsNull(): void
    {
        self::assertNull((new PercentileAggregate(0.5))->compute([]));
    }

    public function testPercentileThrowsOnOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PercentileAggregate(1.5);
    }

    public function testPercentileThrowsOnNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PercentileAggregate(-0.1);
    }
}
