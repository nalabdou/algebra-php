<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * A single query planner optimization pass.
 *
 * Each pass receives the full operation chain and returns a reordered
 * (or collapsed) version. Passes must be idempotent — running a pass
 * twice on an already-optimized chain must produce the same result.
 *
 * Built-in passes (run in order):
 *   1. {@see \Nalabdou\Algebra\Planner\Pass\PushFilterBeforeJoin}
 *   2. {@see \Nalabdou\Algebra\Planner\Pass\PushFilterBeforeAntiJoin}
 *   3. {@see \Nalabdou\Algebra\Planner\Pass\EliminateRedundantSort}
 *   4. {@see \Nalabdou\Algebra\Planner\Pass\CollapseConsecutiveMaps}
 */
interface PassInterface
{
    /**
     * Apply this optimization to the operation chain.
     *
     * @param OperationInterface[] $operations current operation chain
     *
     * @return OperationInterface[] optimized chain (same or reordered)
     */
    public function apply(array $operations): array;
}
