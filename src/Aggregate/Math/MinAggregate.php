<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** MIN — smallest value in the group. */
final class MinAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'min';
    }

    public function compute(array $values): mixed
    {
        return empty($values) ? null : \min($values);
    }
}
