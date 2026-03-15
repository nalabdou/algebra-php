<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Planner\Pass;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Operation\Utility\SortOperation;

/**
 * Remove a SortOperation immediately followed by another SortOperation.
 *
 * The second sort fully overwrites the first — keeping both wastes one O(n log n) pass.
 *
 * ### Example
 * ```
 * Declared:  order_by(region:asc) → order_by(amount:desc)
 * Optimized: order_by(amount:desc)
 * ```
 */
final class EliminateRedundantSort implements PassInterface
{
    /** @param OperationInterface[] $operations @return OperationInterface[] */
    public function apply(array $operations): array
    {
        $result = [];
        $count = \count($operations);

        for ($i = 0; $i < $count; ++$i) {
            $current = $operations[$i];
            $next = $operations[$i + 1] ?? null;

            // Drop the current sort when the very next op is also a sort
            if ($current instanceof SortOperation && $next instanceof SortOperation) {
                continue;
            }

            $result[] = $current;
        }

        return $result;
    }
}
