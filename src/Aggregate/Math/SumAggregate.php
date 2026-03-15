<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** SUM — arithmetic sum of all non-null values. */
final class SumAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'sum';
    }

    public function compute(array $values): float|int
    {
        return \array_sum($values);
    }
}
