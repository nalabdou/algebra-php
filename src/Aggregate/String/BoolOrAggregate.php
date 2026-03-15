<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\String;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * BOOL_OR — true when AT LEAST ONE value in the group is truthy.
 *
 * Equivalent to SQL's `BOOL_OR` / `ANY` aggregate.
 *
 * Used via spec DSL: `'bool_or(isDigital)'`
 */
final class BoolOrAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'bool_or';
    }

    public function compute(array $values): ?bool
    {
        if (empty($values)) {
            return null;
        }

        return \in_array(true, \array_map('boolval', $values), strict: true);
    }
}
