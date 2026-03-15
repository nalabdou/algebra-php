<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Statistical;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * CUME_DIST — cumulative distribution fractions for a set of values.
 *
 * Returns an array of [value => cumulativeFraction] pairs.
 * For per-row annotation use `WindowOperation('cume_dist')` instead.
 */
final class CumeDistAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'cume_dist';
    }

    public function compute(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        $total = \count($values);
        $sorted = $values;
        \sort($sorted);

        $result = [];
        foreach ($sorted as $value) {
            $rank = \count(\array_filter($sorted, static fn (mixed $v): bool => $v <= $value));
            $result[$value] = \round($rank / $total, 6);
        }

        return $result;
    }
}
