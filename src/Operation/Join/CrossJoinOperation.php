<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * CROSS JOIN — cartesian product of two collections.
 *
 * Every left row is combined with every right row.
 * Output size = `count(left) × count(right)`.
 *
 * ⚠ Use only on small collections. 1 000 × 1 000 = 1 000 000 rows.
 *
 * Optional prefixes prevent key collisions when both sides share field names:
 *
 * ### Usage
 * ```php
 * Algebra::from($sizes)->crossJoin($colours, leftPrefix: 'size_', rightPrefix: 'colour_');
 * // [{size_name:'S', colour_name:'Red'}, {size_name:'S', colour_name:'Blue'}, ...]
 * ```
 */
final class CrossJoinOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly string $leftPrefix = '',
        private readonly string $rightPrefix = '',
    ) {
    }

    public function execute(array $rows): array
    {
        $result = [];

        foreach ($rows as $leftRow) {
            foreach ($this->right as $rightRow) {
                $left = $this->applyPrefix(\is_array($leftRow) ? $leftRow : (array) $leftRow, $this->leftPrefix);
                $right = $this->applyPrefix(\is_array($rightRow) ? $rightRow : (array) $rightRow, $this->rightPrefix);
                $result[] = \array_merge($left, $right);
            }
        }

        return $result;
    }

    private function applyPrefix(array $row, string $prefix): array
    {
        if ('' === $prefix) {
            return $row;
        }

        $out = [];
        foreach ($row as $key => $value) {
            $out[$prefix.$key] = $value;
        }

        return $out;
    }

    public function signature(): string
    {
        return \sprintf(
            'cross_join(left=%s, right=%s, size=%d)',
            $this->leftPrefix ?: 'none',
            $this->rightPrefix ?: 'none',
            \count($this->right)
        );
    }

    public function selectivity(): float
    {
        return \max(1.0, (float) \count($this->right));
    }
}
