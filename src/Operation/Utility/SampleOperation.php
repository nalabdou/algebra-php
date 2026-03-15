<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * SAMPLE — return N random rows, preserving original relative order.
 *
 * ```php
 * ->sample(10)              // random 10 rows
 * ->sample(10, seed: 42)    // reproducible — same seed = same selection
 * ```
 *
 * @throws \InvalidArgumentException when count < 0
 */
final class SampleOperation implements OperationInterface
{
    public function __construct(
        private readonly int $count,
        private readonly ?int $seed = null,
    ) {
        if ($this->count < 0) {
            throw new \InvalidArgumentException("Sample count must be ≥ 0, got {$this->count}.");
        }
    }

    public function execute(array $rows): array
    {
        $rows = \array_values($rows);

        if ($this->count >= \count($rows)) {
            return $rows;
        }

        if (null !== $this->seed) {
            \srand($this->seed);
        }

        $picked = (array) \array_rand($rows, $this->count);
        \sort($picked); // preserve original relative order

        return \array_values(\array_intersect_key($rows, \array_flip($picked)));
    }

    public function signature(): string
    {
        return \sprintf('sample(count=%d, seed=%s)', $this->count, $this->seed ?? 'random');
    }

    public function selectivity(): float
    {
        return $this->count > 0 ? 0.3 : 0.0;
    }
}
