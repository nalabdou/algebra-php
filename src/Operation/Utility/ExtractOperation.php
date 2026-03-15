<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * PLUCK — extract a single column into a flat, zero-indexed array.
 *
 * ```php
 * Algebra::from($orders)->pluck('id')->toArray();
 * // → [1, 2, 3, 4, 5]
 * ```
 */
final class ExtractOperation implements OperationInterface
{
    public function __construct(
        private readonly string $field,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        return \array_values(\array_map(
            fn (mixed $row): mixed => $this->accessor->get($row, $this->field),
            $rows
        ));
    }

    public function signature(): string
    {
        return "pluck(field={$this->field})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
