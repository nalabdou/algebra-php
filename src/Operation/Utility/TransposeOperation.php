<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * TRANSPOSE — flip rows ↔ columns of a 2-D array.
 *
 * ```php
 * // Input:
 * [['month'=>'Jan','nord'=>1000,'sud'=>800],
 *  ['month'=>'Feb','nord'=>1200,'sud'=>900]]
 *
 * // Output:
 * ['month'=>['Jan','Feb'], 'nord'=>[1000,1200], 'sud'=>[800,900]]
 * ```
 *
 * Handles sparse rows — keys present in any row appear in the output.
 */
final class TransposeOperation implements OperationInterface
{
    public function execute(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Collect all keys across all rows (handles sparse rows)
        $keys = \array_unique(
            \array_merge(...\array_map(
                static fn (mixed $row): array => \is_array($row) ? \array_keys($row) : [],
                $rows
            ))
        );

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = \array_map(
                static fn (mixed $row): mixed => \is_array($row) ? ($row[$key] ?? null) : null,
                $rows
            );
        }

        return $result;
    }

    public function signature(): string
    {
        return 'transpose()';
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
