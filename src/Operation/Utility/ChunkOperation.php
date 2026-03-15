<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * CHUNK — split the collection into fixed-size sub-arrays.
 *
 * ```php
 * ->chunk(3)
 * // → [[row0,row1,row2], [row3,row4,row5], [row6]]
 * ```
 *
 * @throws \InvalidArgumentException when size < 1
 */
final class ChunkOperation implements OperationInterface
{
    public function __construct(private readonly int $size)
    {
        if ($this->size < 1) {
            throw new \InvalidArgumentException("Chunk size must be ≥ 1, got {$this->size}.");
        }
    }

    public function execute(array $rows): array
    {
        return \array_chunk(\array_values($rows), $this->size);
    }

    public function signature(): string
    {
        return "chunk(size={$this->size})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
