<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * STDDEV — sample standard deviation (Bessel's correction, n−1).
 *
 * Returns null when fewer than 2 values are present.
 */
final class StddevAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'stddev';
    }

    public function compute(array $values): ?float
    {
        $n = \count($values);
        if ($n < 2) {
            return null;
        }

        $mean = \array_sum($values) / $n;
        $variance = \array_sum(\array_map(static fn (mixed $v): float => ($v - $mean) ** 2, $values)) / ($n - 1);

        return \sqrt($variance);
    }
}
