<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * Rewrites an operation chain into a more efficient execution order.
 *
 * The planner receives the operations exactly as declared in the pipeline
 * and returns a reordered (and possibly collapsed) list that produces the
 * same result with less work.
 *
 * @see \Nalabdou\Algebra\Planner\QueryPlanner
 */
interface PlannerInterface
{
    /**
     * Optimize an ordered list of operations.
     *
     * Must never change the semantic result of the pipeline —
     * only its execution efficiency.
     *
     * @param OperationInterface[] $operations declared operation order
     *
     * @return OperationInterface[] optimized execution order
     */
    public function optimize(array $operations): array;
}
