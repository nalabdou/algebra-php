<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * FILL GAPS — insert default rows for missing entries in a sparse series.
 *
 * Preserves the exact order defined by `$series`. Existing rows are kept
 * as-is; missing positions get `$default` merged with the series value.
 *
 * ```php
 * ->fillGaps(
 *     key:     'month',
 *     series:  ['Jan','Feb','Mar','Apr','May','Jun'],
 *     default: ['revenue' => 0, 'orders' => 0],
 * )
 * // Feb was missing → inserted as ['month'=>'Feb','revenue'=>0,'orders'=>0]
 * ```
 */
final class FillGapsOperation implements OperationInterface
{
    /**
     * @param string               $key     field that identifies series membership
     * @param array<int, mixed>    $series  ordered list of all expected series values
     * @param array<string, mixed> $default default fields for gap rows
     */
    public function __construct(
        private readonly string $key,
        private readonly array $series,
        private readonly array $default,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        // Index existing rows by key — O(n)
        $index = [];
        foreach ($rows as $row) {
            $index[(string) $this->accessor->get($row, $this->key)] = $row;
        }

        $result = [];
        foreach ($this->series as $value) {
            $result[] = $index[(string) $value]
                ?? \array_merge($this->default, [$this->key => $value]);
        }

        return $result;
    }

    public function signature(): string
    {
        return \sprintf(
            'fill_gaps(key=%s, series=[%s])',
            $this->key,
            \implode(',', \array_map('strval', $this->series))
        );
    }

    public function selectivity(): float
    {
        return 1.2;
    }
}
