<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Collection;

use Nalabdou\Algebra\Contract\CollectionInterface;
use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * An evaluated, array-backed collection.
 *
 * This is the terminal result of a {@see RelationalCollection} pipeline.
 * All rows are already computed and held in memory. Further {@see pipe()} calls
 * execute immediately rather than lazily.
 *
 * ### Execution log
 * Every `MaterializedCollection` carries a per-operation execution log produced
 * during the pipeline run. Use it for debugging, profiling, or populating a
 * framework profiler panel.
 *
 * ```php
 * $result = Algebra::from($orders)->filter(...)->pivot(...)->materialize();
 *
 * foreach ($result->executionLog() as $step) {
 *     printf("%-40s %6.3fms  %d→%d rows\n",
 *         $step['signature'],
 *         $step['duration_ms'],
 *         $step['input_rows'],
 *         $step['output_rows'],
 *     );
 * }
 * ```
 */
final class MaterializedCollection implements CollectionInterface
{
    /**
     * @param array<int|string, mixed> $rows evaluated rows
     * @param array<int, array{
     *     operation:   string,
     *     signature:   string,
     *     input_rows:  int,
     *     output_rows: int,
     *     duration_ms: float,
     * }> $log Per-operation execution metrics
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $log = [],
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * Returns itself — already materialized.
     */
    public function materialize(): static
    {
        return $this;
    }

    /**
     * Apply one more operation to the already-evaluated rows.
     *
     * Executes immediately (no laziness) and returns a new instance.
     */
    public function pipe(OperationInterface $operation): static
    {
        return new static($operation->execute($this->rows), $this->log);
    }

    /** @return array<int|string, mixed> */
    public function toArray(): array
    {
        return $this->rows;
    }

    public function count(): int
    {
        return \count($this->rows);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->rows);
    }

    /**
     * Return the first row, or null when the collection is empty.
     */
    public function first(): mixed
    {
        return $this->rows[\array_key_first($this->rows) ?? 0] ?? null;
    }

    /**
     * Return the last row, or null when the collection is empty.
     */
    public function last(): mixed
    {
        return $this->rows[\array_key_last($this->rows) ?? 0] ?? null;
    }

    /**
     * Whether the collection has no rows.
     */
    public function isEmpty(): bool
    {
        return empty($this->rows);
    }

    /**
     * Per-operation execution metrics collected during the pipeline run.
     *
     * Each entry contains:
     *   - `operation`   — fully-qualified class name
     *   - `signature`   — compact human-readable description
     *   - `input_rows`  — row count before this operation
     *   - `output_rows` — row count after this operation
     *   - `duration_ms` — wall-clock time in milliseconds
     *
     * @return array<int, array{operation: string, signature: string, input_rows: int, output_rows: int, duration_ms: float}>
     */
    public function executionLog(): array
    {
        return $this->log;
    }

    /**
     * Total wall-clock time of the entire pipeline in milliseconds.
     */
    public function totalDurationMs(): float
    {
        return \array_sum(\array_column($this->log, 'duration_ms'));
    }
}
