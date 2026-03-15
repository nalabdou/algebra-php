<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Window;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * MOVING AVERAGE — sliding window average over N consecutive rows.
 *
 * Rows without enough prior context receive `null`.
 *
 * ```php
 * ->movingAverage(field: 'revenue', window: 7, as: 'avg_7d')
 * // Row 0–5: avg_7d = null
 * // Row 6+:  avg_7d = avg of current row + 6 prior rows
 * ```
 */
final class MovingAvgOperation implements OperationInterface
{
    /**
     * @param string $field  field to average
     * @param int    $window number of rows in the sliding window (≥1)
     * @param string $as     output key added to each row
     *
     * @throws \InvalidArgumentException when window < 1
     */
    public function __construct(
        private readonly string $field,
        private readonly int $window,
        private readonly string $as,
        private readonly PropertyAccessor $accessor,
    ) {
        if ($this->window < 1) {
            throw new \InvalidArgumentException("Moving average window must be ≥ 1, got {$this->window}.");
        }
    }

    public function execute(array $rows): array
    {
        $count = \count($rows);
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $row = $rows[$i];

            if ($i < $this->window - 1) {
                $result[] = $this->annotate($row, null);
                continue;
            }

            $sum = 0.0;
            for ($j = $i - $this->window + 1; $j <= $i; ++$j) {
                $sum += (float) $this->accessor->get($rows[$j], $this->field);
            }

            $result[] = $this->annotate($row, \round($sum / $this->window, 6));
        }

        return $result;
    }

    private function annotate(mixed $row, mixed $value): array
    {
        return \array_merge(\is_array($row) ? $row : (array) $row, [$this->as => $value]);
    }

    public function signature(): string
    {
        return "moving_avg(field={$this->field}, window={$this->window}, as={$this->as})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
