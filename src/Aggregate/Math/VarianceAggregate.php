<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * VARIANCE — sample variance (Bessel's correction, n−1).
 *
 * Returns null when fewer than 2 values are present.
 */
final class VarianceAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'variance';
    }

    public function compute(array $values): ?float
    {
        $n = \count($values);
        if ($n < 2) {
            return null;
        }

        $values = \array_map('floatval', $values);
        $mean = \array_sum($values) / $n;

        $sumSq = 0.0;
        foreach ($values as $v) {
            $sumSq += ($v - $mean) ** 2;
        }

        return $sumSq / ($n - 1);
    }
}
