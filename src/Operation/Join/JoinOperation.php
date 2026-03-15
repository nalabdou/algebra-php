<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * INNER JOIN — merge rows from two collections where a key matches.
 *
 * Unmatched left rows are **dropped**. Uses a hash-index on the right
 * collection for O(n+m) complexity instead of the naive O(n×m).
 *
 * ### Algorithm
 * 1. Build a hash-map: `rightKey → [right_rows]` — O(m)
 * 2. For each left row, perform a single O(1) hash lookup
 * 3. Merge left row with each matched right row
 *
 * ### One-to-many support
 * When multiple right rows share the same key, the left row is
 * duplicated once per match (standard SQL INNER JOIN behaviour).
 *
 * ### Usage
 * ```php
 * Algebra::from($orders)
 *     ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
 *     ->toArray();
 * // Each row: [...order_fields, 'owner' => [...user_fields]]
 * ```
 */
final class JoinOperation implements OperationInterface
{
    /**
     * @param array<int, mixed> $right    pre-resolved right-side rows
     * @param string            $leftKey  dot-path on left rows for matching
     * @param string            $rightKey dot-path on right rows for matching
     * @param string            $as       key under which the right row is attached
     * @param PropertyAccessor  $accessor used to read key values from rows/objects
     */
    public function __construct(
        private readonly array $right,
        private readonly string $leftKey,
        private readonly string $rightKey,
        private readonly string $as,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        // Phase 1 — build right-side hash index: O(m)
        $index = [];
        foreach ($this->right as $rightRow) {
            $key = (string) $this->accessor->get($rightRow, $this->rightKey);
            $index[$key][] = $rightRow;
        }

        // Phase 2 — probe index for each left row: O(n)
        $result = [];
        foreach ($rows as $leftRow) {
            $key = (string) $this->accessor->get($leftRow, $this->leftKey);

            if (!isset($index[$key])) {
                continue; // INNER JOIN: drop unmatched
            }

            $leftArray = \is_array($leftRow) ? $leftRow : (array) $leftRow;

            foreach ($index[$key] as $rightRow) {
                $result[] = \array_merge($leftArray, [$this->as => $rightRow]);
            }
        }

        return $result;
    }

    public function signature(): string
    {
        return \sprintf('inner_join(%s=%s, as=%s)', $this->leftKey, $this->rightKey, $this->as);
    }

    public function selectivity(): float
    {
        return 0.7;
    }
}
