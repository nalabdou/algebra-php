<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** AVG — arithmetic mean of all non-null values. Returns null on empty input. */
final class AvgAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'avg';
    }

    public function compute(array $values): ?float
    {
        return empty($values) ? null : \array_sum($values) / \count($values);
    }
}
