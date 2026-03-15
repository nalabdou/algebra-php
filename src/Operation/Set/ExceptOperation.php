<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Set;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * EXCEPT (A − B) — keep left rows whose key is **absent** from the right.
 *
 * ```php
 * Algebra::from($notifications)->except($dismissed, by: 'id');
 * ```
 */
final class ExceptOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly string $by,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $index = [];
        foreach ($this->right as $row) {
            $index[(string) $this->accessor->get($row, $this->by)] = true;
        }

        return \array_values(\array_filter(
            $rows,
            fn (mixed $row): bool => !isset($index[(string) $this->accessor->get($row, $this->by)])
        ));
    }

    public function signature(): string
    {
        return "except(by={$this->by})";
    }

    public function selectivity(): float
    {
        return 0.5;
    }
}
