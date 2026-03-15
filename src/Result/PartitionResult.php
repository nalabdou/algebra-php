<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Result;

use Nalabdou\Algebra\Contract\ResultInterface;

/**
 * The result of a {@see \Nalabdou\Algebra\Collection\RelationalCollection::partition()} call.
 *
 * Rows are split into two groups in a single iteration:
 *   - **pass** — rows for which the expression evaluated to `true`
 *   - **fail** — rows for which the expression evaluated to `false`
 *
 * ### Example
 * ```php
 * $result = Algebra::from($orders)->partition("item['amount'] > 500");
 *
 * $highValue = $result->pass();  // orders with amount > 500
 * $standard  = $result->fail();  // orders with amount ≤ 500
 *
 * echo $result->passCount(); // e.g. 42
 * echo $result->failCount(); // e.g. 158
 * ```
 */
final class PartitionResult implements ResultInterface
{
    /**
     * @param array<int, mixed> $pass rows matching the partition expression
     * @param array<int, mixed> $fail rows NOT matching the partition expression
     */
    public function __construct(
        private readonly array $pass,
        private readonly array $fail,
    ) {
    }

    /**
     * Rows for which the partition expression evaluated to `true`.
     *
     * @return array<int, mixed>
     */
    public function pass(): array
    {
        return $this->pass;
    }

    /**
     * Rows for which the partition expression evaluated to `false`.
     *
     * @return array<int, mixed>
     */
    public function fail(): array
    {
        return $this->fail;
    }

    /**
     * Number of rows in the passing group.
     */
    public function passCount(): int
    {
        return \count($this->pass);
    }

    /**
     * Number of rows in the failing group.
     */
    public function failCount(): int
    {
        return \count($this->fail);
    }

    /**
     * Total number of rows processed (pass + fail).
     */
    public function totalCount(): int
    {
        return $this->passCount() + $this->failCount();
    }

    /**
     * Pass rate as a fraction between 0.0 and 1.0.
     *
     * Returns 0.0 when the collection is empty.
     */
    public function passRate(): float
    {
        $total = $this->totalCount();

        return $total > 0 ? $this->passCount() / $total : 0.0;
    }

    /**
     * Serialize both groups to an associative array.
     *
     * @return array{pass: array<int, mixed>, fail: array<int, mixed>}
     */
    public function toArray(): array
    {
        return [
            'pass' => $this->pass,
            'fail' => $this->fail,
        ];
    }
}
