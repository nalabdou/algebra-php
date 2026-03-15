<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * LEFT JOIN — keep all left rows; attach matched right row or null.
 *
 * Unlike {@see JoinOperation}, unmatched left rows are **preserved** with
 * `null` set under the joined key. Row count equals left collection size.
 *
 * ### Usage
 * ```php
 * Algebra::from($orders)
 *     ->leftJoin($users, on: 'userId=id', as: 'owner')
 *     ->toArray();
 * // Orders without a matching user have $row['owner'] === null
 * ```
 */
final class LeftJoinOperation implements OperationInterface
{
    /**
     * @param array<int, mixed> $right    pre-resolved right-side rows
     * @param string            $leftKey  dot-path on left rows for matching
     * @param string            $rightKey dot-path on right rows for matching
     * @param string            $as       key under which the right row (or null) is attached
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
        $index = [];
        foreach ($this->right as $rightRow) {
            $key = (string) $this->accessor->get($rightRow, $this->rightKey);
            $index[$key][] = $rightRow;
        }

        $result = [];
        foreach ($rows as $leftRow) {
            $key = (string) $this->accessor->get($leftRow, $this->leftKey);
            $leftArray = \is_array($leftRow) ? $leftRow : (array) $leftRow;

            if (!isset($index[$key])) {
                $result[] = \array_merge($leftArray, [$this->as => null]);
                continue;
            }

            foreach ($index[$key] as $rightRow) {
                $result[] = \array_merge($leftArray, [$this->as => $rightRow]);
            }
        }

        return $result;
    }

    public function signature(): string
    {
        return \sprintf('left_join(%s=%s, as=%s)', $this->leftKey, $this->rightKey, $this->as);
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
