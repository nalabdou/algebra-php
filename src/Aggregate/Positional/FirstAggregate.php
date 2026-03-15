<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Positional;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * FIRST — returns the first value in the group (input order preserved).
 *
 * Chain after `orderBy` to get the first value by a specific ordering.
 */
final class FirstAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'first';
    }

    public function compute(array $values): mixed
    {
        return $values[0] ?? null;
    }
}
