<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/** MAX — largest value in the group. */
final class MaxAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'max';
    }

    public function compute(array $values): mixed
    {
        return empty($values) ? null : \max($values);
    }
}
