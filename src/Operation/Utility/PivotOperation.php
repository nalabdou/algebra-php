<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * PIVOT — reshape a flat collection into a cross-tab matrix.
 *
 * Each distinct value of `$colsKey` becomes a column.
 * Each distinct value of `$rowsKey` becomes a row.
 * Cell values are computed by `$aggregateFn` applied to all matching `$valueKey` entries.
 *
 * ```php
 * Algebra::from($sales)->pivot(rows: 'month', cols: 'region', value: 'revenue');
 * // → [
 * //     ['_row'=>'Jan', 'Nord'=>4200, 'Sud'=>3100, 'Est'=>1800],
 * //     ['_row'=>'Feb', 'Nord'=>5100, 'Sud'=>2900, 'Est'=>2200],
 * // ]
 * ```
 *
 * Missing cells (no data for a row/col combination) are `null`.
 */
final class PivotOperation implements OperationInterface
{
    public function __construct(
        private readonly string $rowsKey,
        private readonly string $colsKey,
        private readonly string $valueKey,
        private readonly string $aggregateFn,
        private readonly AggregateRegistry $registry,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        // Phase 1 — collect all distinct column values (encounter order preserved)
        $colValues = [];
        foreach ($rows as $row) {
            $colValues[(string) $this->accessor->get($row, $this->colsKey)] = true;
        }
        $cols = \array_keys($colValues);

        // Phase 2 — group by (rowKey × colKey) → accumulate cell values
        $matrix = [];
        foreach ($rows as $row) {
            $rowKey = (string) $this->accessor->get($row, $this->rowsKey);
            $colKey = (string) $this->accessor->get($row, $this->colsKey);
            $matrix[$rowKey][$colKey][] = $this->accessor->get($row, $this->valueKey);
        }

        // Phase 3 — aggregate cells and build output rows
        $aggregate = $this->registry->get($this->aggregateFn);

        $result = [];

        foreach ($matrix as $rowKey => $colData) {
            $outputRow = ['_row' => $rowKey];
            foreach ($cols as $col) {
                $outputRow[$col] = isset($colData[$col])
                    ? $aggregate->compute($colData[$col])
                    : null;
            }
            $result[] = $outputRow;
        }

        return $result;
    }

    public function signature(): string
    {
        return \sprintf(
            'pivot(rows=%s, cols=%s, value=%s, fn=%s)',
            $this->rowsKey,
            $this->colsKey,
            $this->valueKey,
            $this->aggregateFn
        );
    }

    public function selectivity(): float
    {
        return 0.3;
    }
}
