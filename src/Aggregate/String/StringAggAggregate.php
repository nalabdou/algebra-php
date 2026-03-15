<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\String;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * STRING_AGG — concatenate string values within a group using a separator.
 *
 * Null and empty-string values are excluded from the result.
 * Returns null when all values are empty or the input is empty.
 *
 * Used via spec DSL: `'string_agg(name, ", ")'` → `'Alice, Bob, Carol'`
 */
final class StringAggAggregate implements AggregateInterface
{
    public function __construct(private readonly string $separator = ', ')
    {
    }

    public function name(): string
    {
        return 'string_agg';
    }

    public function compute(array $values): ?string
    {
        $strings = \array_filter(
            \array_map('strval', $values),
            static fn (string $v): bool => '' !== $v
        );

        return empty($strings) ? null : \implode($this->separator, $strings);
    }
}
