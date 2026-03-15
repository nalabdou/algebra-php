<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Set;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * SYMMETRIC DIFFERENCE (A △ B) — rows in A or B but **not both**.
 *
 * ```php
 * Algebra::from($listA)->symmetricDiff($listB, by: 'id');
 * // → rows exclusive to each side
 * ```
 */
final class DiffByOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly string $by,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $leftIndex = [];
        $rightIndex = [];

        foreach ($rows as $row) {
            $leftIndex[(string) $this->accessor->get($row, $this->by)] = $row;
        }
        foreach ($this->right as $row) {
            $rightIndex[(string) $this->accessor->get($row, $this->by)] = $row;
        }

        $result = [];

        foreach ($leftIndex as $key => $row) {
            if (!isset($rightIndex[$key])) {
                $result[] = $row;
            }
        }

        foreach ($rightIndex as $key => $row) {
            if (!isset($leftIndex[$key])) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function signature(): string
    {
        return "symmetric_diff(by={$this->by})";
    }

    public function selectivity(): float
    {
        return 0.5;
    }
}
