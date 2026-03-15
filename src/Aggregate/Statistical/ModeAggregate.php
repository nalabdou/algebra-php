<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Statistical;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * MODE — most frequently occurring value in the group.
 *
 * On ties, returns the first value encountered in the original array.
 * Preserves the original PHP type of the winning value.
 */
final class ModeAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'mode';
    }

    public function compute(array $values): mixed
    {
        if (empty($values)) {
            return null;
        }

        $counts = [];
        foreach ($values as $v) {
            $key = \is_object($v) ? \spl_object_hash($v) : $v;
            if (!isset($counts[$key])) {
                $counts[$key] = ['value' => $v, 'count' => 0];
            }
            ++$counts[$key]['count'];
        }

        \usort($counts, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $counts[0]['value'];
    }
}
