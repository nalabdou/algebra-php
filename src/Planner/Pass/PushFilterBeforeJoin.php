<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Planner\Pass;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Operation\Join\JoinOperation;
use Nalabdou\Algebra\Operation\Join\LeftJoinOperation;
use Nalabdou\Algebra\Operation\Utility\FilterOperation;

/**
 * Push all WHERE clauses before INNER JOIN / LEFT JOIN operations.
 *
 * When the pipeline contains at least one join, all {@see FilterOperation}s are
 * moved before the first join while all other operations keep their relative order.
 * This reduces the number of rows that enter the join, cutting O(n×m) work.
 *
 * The pass is a no-op when no join is present — it never reorders a pipeline
 * that contains only filters and non-join operations, which preserves correct
 * execution of patterns like `select(add_field) → where(on_added_field)`.
 *
 * Example:
 * ```
 * Declared:  inner_join → where(status=='paid') → orderBy(amount)
 * Optimized: where(status=='paid') → inner_join → orderBy(amount)
 * ```
 */
final class PushFilterBeforeJoin implements PassInterface
{
    /**
     * @param OperationInterface[] $operations
     *
     * @return OperationInterface[]
     */
    public function apply(array $operations): array
    {
        if (!$this->hasJoin($operations)) {
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

    private function hasJoin(array $operations): bool
    {
        foreach ($operations as $op) {
            if ($op instanceof JoinOperation || $op instanceof LeftJoinOperation) {
                return true;
            }
        }

        return false;
    }
}
