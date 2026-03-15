<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Positional;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * LAST — returns the last value in the group (input order preserved).
 *
 * Chain after `orderBy` to get the last value by a specific ordering.
 */
final class LastAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'last';
    }

    public function compute(array $values): mixed
    {
        return empty($values) ? null : $values[\count($values) - 1];
    }
}
