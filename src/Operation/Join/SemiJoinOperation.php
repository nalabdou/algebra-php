<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * SEMI JOIN — keep left rows that have at least one match on the right.
 *
 * No right-side data is attached. Faster than a full join when you
 * only need existence checking.
 *
 * ### Usage
 * ```php
 * // Orders that have at least one payment — without attaching payment data
 * Algebra::from($orders)->semiJoin($payments, leftKey: 'id', rightKey: 'orderId');
 * ```
 */
final class SemiJoinOperation implements OperationInterface
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
            fn (mixed $row): bool => isset($index[(string) $this->accessor->get($row, $this->leftKey)])
        ));
    }

    public function signature(): string
    {
        return \sprintf('semi_join(%s=%s)', $this->leftKey, $this->rightKey);
    }

    public function selectivity(): float
    {
        return 0.6;
    }
}
