<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\String;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * BOOL_AND — true when ALL values in the group are truthy.
 *
 * Equivalent to SQL's `BOOL_AND` / `EVERY` aggregate.
 *
 * Used via spec DSL: `'bool_and(shipped)'`
 */
final class BoolAndAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'bool_and';
    }

    public function compute(array $values): ?bool
    {
        if (empty($values)) {
            return null;
        }

        return !\in_array(false, \array_map('boolval', $values), strict: true);
    }
}
