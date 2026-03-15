<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Statistical;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** COUNT DISTINCT — number of unique non-null values within a group. */
final class CountDistinctAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'count_distinct';
    }

    public function compute(array $values): int
    {
        return \count(\array_unique($values));
    }
}
