<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * ORDER BY — sort rows by one or multiple keys.
 *
 * ```php
 * ->orderBy('amount', 'desc')
 * ->orderBy([['status', 'asc'], ['amount', 'desc']])
 * ```
 *
 * @param array<array{string, string}> $keys [['field', 'asc|desc'], ...]
 */
final class SortOperation implements OperationInterface
{
    /** @param array<array{string, string}> $keys */
    public function __construct(
        private readonly array $keys,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        \usort($rows, function (mixed $a, mixed $b): int {
            foreach ($this->keys as [$field, $direction]) {
                $cmp = $this->accessor->get($a, $field) <=> $this->accessor->get($b, $field);
                if (0 !== $cmp) {
                    return 'desc' === \strtolower($direction) ? -$cmp : $cmp;
                }
            }

            return 0;
        });

        return $rows;
    }

    /** @return array<array{string, string}> */
    public function keys(): array
    {
        return $this->keys;
    }

    public function signature(): string
    {
        return 'order_by('.\implode(', ', \array_map(static fn ($k) => $k[0].':'.$k[1], $this->keys)).')';
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
