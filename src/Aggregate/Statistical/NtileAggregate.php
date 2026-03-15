<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Statistical;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * NTILE — returns the bucket boundary values for N equal-sized groups.
 *
 * Returns an array of N−1 boundary values (the thresholds between buckets).
 * For per-row bucket assignment use `WindowOperation('ntile')` instead.
 *
 * Example: 4 buckets of [100,200,300,400,500] → [175.0, 250.0, 375.0]
 */
final class NtileAggregate implements AggregateInterface
{
    public function __construct(private readonly int $buckets = 4)
    {
    }

    public function name(): string
    {
        return 'ntile';
    }

    public function compute(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }

        \sort($values);
        $count = \count($values);
        $boundaries = [];

        for ($i = 1; $i < $this->buckets; ++$i) {
            $idx = (int) \round(($i / $this->buckets) * $count) - 1;
            $boundaries[] = $values[\max(0, $idx)];
        }

        return $boundaries;
    }
}
