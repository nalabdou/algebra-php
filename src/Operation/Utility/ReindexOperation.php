<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * REINDEX — key the output array by a field value for O(1) lookup.
 *
 * ```php
 * $map = Algebra::from($users)->reindex('id')->toArray();
 * $map['42']['name']; // O(1) access — no loop needed
 * ```
 *
 * On duplicate keys the **last** occurrence wins.
 */
final class ReindexOperation implements OperationInterface
{
    public function __construct(
        private readonly string $key,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(string) $this->accessor->get($row, $this->key)] = $row;
        }

        return $result;
    }

    public function signature(): string
    {
        return "reindex(key={$this->key})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
