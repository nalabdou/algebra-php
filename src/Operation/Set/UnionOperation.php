<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Set;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * UNION (A ∪ B) — merge two collections and deduplicate by key.
 *
 * First occurrence wins on duplicate keys.
 * Pass `$by = null` to use PHP's native `SORT_REGULAR` array uniqueness.
 *
 * ```php
 * Algebra::from($staff)->union($contractors, by: 'email');
 * ```
 */
final class UnionOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly ?string $by,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $merged = \array_merge($rows, $this->right);

        if (null === $this->by) {
            return \array_unique($merged, \SORT_REGULAR);
        }

        $seen = [];
        $result = [];

        foreach ($merged as $row) {
            $key = (string) $this->accessor->get($row, $this->by);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    public function signature(): string
    {
        return \sprintf('union(by=%s)', $this->by ?? 'value');
    }

    public function selectivity(): float
    {
        return 1.5;
    }
}
