<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Window;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * NORMALIZE — scale all values of a field to the [0.0, 1.0] range.
 *
 * Uses min-max normalization: `(value − min) / (max − min)`.
 * When all values are identical (range = 0), every row receives 0.0.
 *
 * ```php
 * ->normalize(field: 'price', as: 'priceScore')
 * // All priceScore values are between 0.0 (cheapest) and 1.0 (most expensive)
 * ```
 */
final class NormalizeOperation implements OperationInterface
{
    public function __construct(
        private readonly string $field,
        private readonly string $as,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $values = \array_map(
            fn (mixed $row): float => (float) $this->accessor->get($row, $this->field),
            $rows
        );

        $min = \min($values);
        $max = \max($values);
        $range = $max - $min;

        return \array_map(
            function (mixed $row, int $i) use ($values, $min, $range): array {
                $score = $range > 0.0 ? ($values[$i] - $min) / $range : 0.0;

                return \array_merge(
                    \is_array($row) ? $row : (array) $row,
                    [$this->as => \round($score, 6)]
                );
            },
            $rows,
            \array_keys($rows)
        );
    }

    public function signature(): string
    {
        return "normalize(field={$this->field}, as={$this->as})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
