<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** MEDIAN — middle value after sorting. Averages two middle values on even counts. */
final class MedianAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'median';
    }

    public function compute(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        \sort($values);
        $count = \count($values);
        $middle = \intdiv($count, 2);

        return 0 === $count % 2
            ? ($values[$middle - 1] + $values[$middle]) / 2.0
            : (float) $values[$middle];
    }
}
