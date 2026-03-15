<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * A non-array terminal result from a pipeline operation.
 *
 * Some operations (e.g. {@see \Nalabdou\Algebra\Collection\RelationalCollection::partition()})
 * return structured results rather than a plain array. All such results
 * implement this interface so they can be serialized uniformly.
 *
 * Concrete implementations:
 *   - {@see \Nalabdou\Algebra\Result\PartitionResult} — pass/fail split
 */
interface ResultInterface
{
    /**
     * Serialize the result to a plain PHP array.
     *
     * The exact structure is defined by each concrete implementation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
