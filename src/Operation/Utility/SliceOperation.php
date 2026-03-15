<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * LIMIT / OFFSET — return at most `$limit` rows, skipping `$offset` first.
 *
 * ```php
 * ->limit(10)             // first 10 rows
 * ->limit(10, offset: 20) // rows 21–30 (page 3 of 10-per-page)
 * ```
 *
 * @throws \InvalidArgumentException when limit < 0
 */
final class SliceOperation implements OperationInterface
{
    public function __construct(
        private readonly int $limit,
        private readonly int $offset = 0,
    ) {
        if ($this->limit < 0) {
            throw new \InvalidArgumentException("Limit must be ≥ 0, got {$this->limit}.");
        }
    }

    public function execute(array $rows): array
    {
        return \array_slice(\array_values($rows), $this->offset, $this->limit);
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function offset(): int
    {
        return $this->offset;
    }

    public function signature(): string
    {
        return "limit({$this->limit}, offset={$this->offset})";
    }

    public function selectivity(): float
    {
        return 0.3;
    }
}
