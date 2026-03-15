<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * ANTI JOIN — keep left rows that have **no** match on the right.
 *
 * The inverse of {@see SemiJoinOperation}.
 *
 * ### Usage
 * ```php
 * // Orders with zero payments recorded
 * Algebra::from($orders)->antiJoin($payments, leftKey: 'id', rightKey: 'orderId');
 * ```
 */
final class AntiJoinOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly string $leftKey,
        private readonly string $rightKey,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $index = [];
        foreach ($this->right as $row) {
            $index[(string) $this->accessor->get($row, $this->rightKey)] = true;
        }

        return \array_values(\array_filter(
            $rows,
            fn (mixed $row): bool => !isset($index[(string) $this->accessor->get($row, $this->leftKey)])
        ));
    }

    public function signature(): string
    {
        return \sprintf('anti_join(%s=%s)', $this->leftKey, $this->rightKey);
    }

    public function selectivity(): float
    {
        return 0.4;
    }
}
