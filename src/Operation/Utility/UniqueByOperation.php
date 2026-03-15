<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * DISTINCT — deduplicate rows by a key. First occurrence wins.
 *
 * ```php
 * ->distinct('productId')
 * ```
 */
final class UniqueByOperation implements OperationInterface
{
    public function __construct(
        private readonly string $key,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $keyVal = (string) $this->accessor->get($row, $this->key);
            if (!isset($seen[$keyVal])) {
                $seen[$keyVal] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    public function signature(): string
    {
        return "distinct(key={$this->key})";
    }

    public function selectivity(): float
    {
        return 0.8;
    }
}
