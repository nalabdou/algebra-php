<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * A single step in a relational algebra pipeline.
 *
 * Each operation takes a materialized array of rows, transforms it,
 * and returns the result. Operations are stateless and idempotent —
 * the same input always produces the same output.
 *
 * Implementations live under:
 *   - {@see \Nalabdou\Algebra\Operation\Join\}   — JOIN family
 *   - {@see \Nalabdou\Algebra\Operation\Set\}    — Set algebra
 *   - {@see \Nalabdou\Algebra\Operation\Aggregate\} — GROUP BY, tally…
 *   - {@see \Nalabdou\Algebra\Operation\Window\} — Window functions
 *   - {@see \Nalabdou\Algebra\Operation\Utility\} — Filter, sort, slice…
 */
interface OperationInterface
{
    /**
     * Execute this operation on a materialized array of rows.
     *
     * Each row is either an associative array or an object with accessible
     * properties. The operation must never mutate its input.
     *
     * @param array<int, mixed> $rows input rows
     *
     * @return array<int|string, mixed> output rows (may be associative for GROUP BY)
     */
    public function execute(array $rows): array;

    /**
     * A compact, human-readable description of this operation and its parameters.
     *
     * Used by the {@see \Nalabdou\Algebra\Planner\QueryPlanner} for plan diffing,
     * by the cache key builder, and by the profiler panel.
     *
     * Example: `"join(userId=id,as=owner)"`, `"filter(item['status']=='paid')"`.
     */
    public function signature(): string;

    /**
     * Estimated output/input row ratio (0.0 – ∞).
     *
     * Used by the planner to order operations by selectivity:
     *   - 0.0–0.5  → highly selective (filter, anti-join)
     *   - 0.6–0.9  → moderately selective (inner join, intersect)
     *   - 1.0      → row-preserving (window, normalize, sort)
     *   - >1.0     → row-expanding (union, cross-join)
     */
    public function selectivity(): float;
}
