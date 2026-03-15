<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** COUNT — number of non-null values. `count(*)` counts all rows. */
final class CountAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'count';
    }

    public function compute(array $values): int
    {
        return \count($values);
    }
}
