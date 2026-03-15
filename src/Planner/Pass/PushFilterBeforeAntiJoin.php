<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Planner\Pass;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Operation\Join\AntiJoinOperation;
use Nalabdou\Algebra\Operation\Join\SemiJoinOperation;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;

/**
 * Push all WHERE clauses before SEMI JOIN / ANTI JOIN operations.
 *
 * When the pipeline contains at least one semi or anti join, all
 * {@see FilterOperation}s are moved to the front while all other operations
 * keep their relative order. This reduces rows that enter the join.
 *
 * The pass is a no-op when no semi/anti join is present.
 *
 * Example:
 * ```
 * Declared:  anti_join → where(amount > 100)
 * Optimized: where(amount > 100) → anti_join
 * ```
 */
final class PushFilterBeforeAntiJoin implements PassInterface
{
    /**
     * @param OperationInterface[] $operations
     *
     * @return OperationInterface[]
     */
    public function apply(array $operations): array
    {
        if (!$this->hasAntiJoin($operations)) {
            return $operations;
        }

        $filters = [];
        $nonFilters = [];

        foreach ($operations as $op) {
            if ($op instanceof FilterOperation) {
                $filters[] = $op;
            } else {
                $nonFilters[] = $op;
            }
        }

        return \array_merge($filters, $nonFilters);
    }

    private function hasAntiJoin(array $operations): bool
    {
        foreach ($operations as $op) {
            if ($op instanceof SemiJoinOperation || $op instanceof AntiJoinOperation) {
                return true;
            }
        }

        return false;
    }
}
