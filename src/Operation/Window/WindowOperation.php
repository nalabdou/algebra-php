<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Window;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * WINDOW FUNCTION dispatcher — enriches each row without collapsing the collection.
 *
 * All functions annotate each row with a computed value under `$as`.
 * Row count is always preserved.
 *
 * ### Available functions
 * | Function         | Description                                          |
 * |------------------|------------------------------------------------------|
 * | `running_sum`    | Cumulative sum of `$field`                           |
 * | `running_avg`    | Cumulative average of `$field`                       |
 * | `running_count`  | Cumulative row count                                 |
 * | `running_diff`   | Delta vs previous row (null for first row)           |
 * | `rank`           | Rank descending by `$field` (gaps on ties)           |
 * | `dense_rank`     | Dense rank (no gaps on ties)                         |
 * | `row_number`     | Sequential 1-based row number                        |
 * | `lag`            | Value of `$field` N rows before (null if unavailable)|
 * | `lead`           | Value of `$field` N rows after (null if unavailable) |
 * | `ntile`          | Bucket number 1–N (`$buckets` controls N)            |
 * | `cume_dist`      | Cumulative distribution fraction (0.0–1.0)           |
 *
 * ### Partition support
 * Pass `$partitionBy` to reset the window per distinct group:
 * ```php
 * ->window('running_sum', field: 'amount', as: 'userTotal', partitionBy: 'userId')
 * ```
 *
 * ### Usage
 * ```php
 * Algebra::from($orders)
 *     ->orderBy('createdAt')
 *     ->window('running_sum',  field: 'amount',  as: 'cumulative')
 *     ->window('lag',          field: 'amount',  as: 'prevAmount', offset: 1)
 *     ->window('row_number',   field: 'id',      as: 'rowNum')
 *     ->toArray();
 * ```
 */
final class WindowOperation implements OperationInterface
{
    /**
     * @param string      $fn          window function name
     * @param string      $field       field to compute over
     * @param string|null $partitionBy reset window per distinct value of this field
     * @param string      $as          output key added to each row
     * @param int         $offset      rows to look back/forward for lag/lead
     * @param int         $buckets     number of equal-sized buckets for ntile
     */
    public function __construct(
        private readonly string $fn,
        private readonly string $field,
        private readonly ?string $partitionBy,
        private readonly string $as,
        private readonly int $offset,
        private readonly int $buckets,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        return null !== $this->partitionBy
            ? $this->executePartitioned($rows)
            : $this->applyFunction($rows);
    }

    private function executePartitioned(array $rows): array
    {
        $partitions = [];
        foreach ($rows as $i => $row) {
            $key = (string) $this->accessor->get($row, $this->partitionBy);
            $partitions[$key][] = ['row' => $row, 'idx' => $i];
        }

        $result = \array_fill(0, \count($rows), null);

        foreach ($partitions as $group) {
            $rawRows = \array_column($group, 'row');
            $windowed = $this->applyFunction($rawRows);

            foreach ($group as $j => ['idx' => $origIdx]) {
                $result[$origIdx] = $windowed[$j];
            }
        }

        return $result;
    }

    private function applyFunction(array $rows): array
    {
        return match ($this->fn) {
            'running_sum' => $this->runningSum($rows),
            'running_avg' => $this->runningAvg($rows),
            'running_count' => $this->runningCount($rows),
            'running_diff' => $this->runningDiff($rows),
            'rank' => $this->rank($rows),
            'dense_rank' => $this->denseRank($rows),
            'row_number' => $this->rowNumber($rows),
            'lag' => $this->lag($rows),
            'lead' => $this->lead($rows),
            'ntile' => $this->ntile($rows),
            'cume_dist' => $this->cumeDist($rows),
            default => throw new \InvalidArgumentException("Unknown window function: '{$this->fn}'. Supported: ".'running_sum, running_avg, running_count, running_diff, rank, dense_rank, row_number, lag, lead, ntile, cume_dist'),
        };
    }

    private function runningSum(array $rows): array
    {
        $sum = 0.0;

        return \array_map(function (mixed $row) use (&$sum): array {
            $sum += (float) $this->accessor->get($row, $this->field);

            return $this->annotate($row, $sum);
        }, $rows);
    }

    private function runningAvg(array $rows): array
    {
        $sum = 0.0;
        $count = 0;

        return \array_map(function (mixed $row) use (&$sum, &$count): array {
            $sum += (float) $this->accessor->get($row, $this->field);

            return $this->annotate($row, $sum / ++$count);
        }, $rows);
    }

    private function runningCount(array $rows): array
    {
        $count = 0;

        return \array_map(
            function (mixed $row) use (&$count): array {
                return $this->annotate($row, ++$count);
            },
            $rows
        );
    }

    private function runningDiff(array $rows): array
    {
        $prev = null;

        return \array_map(function (mixed $row) use (&$prev): array {
            $current = (float) $this->accessor->get($row, $this->field);
            $diff = null !== $prev ? $current - $prev : null;
            $prev = $current;

            return $this->annotate($row, $diff);
        }, $rows);
    }

    private function rank(array $rows): array
    {
        $values = \array_map(fn ($r) => $this->accessor->get($r, $this->field), $rows);
        $sorted = $values;
        \rsort($sorted);

        return \array_map(
            fn (mixed $row, int $i): array => $this->annotate(
                $row,
                \array_search($values[$i], $sorted, strict: true) + 1
            ),
            $rows,
            \array_keys($rows)
        );
    }

    private function denseRank(array $rows): array
    {
        $values = \array_map(fn ($r) => $this->accessor->get($r, $this->field), $rows);
        $unique = \array_values(\array_unique($values));
        \rsort($unique);

        return \array_map(
            fn (mixed $row, int $i): array => $this->annotate(
                $row,
                \array_search($values[$i], $unique, strict: true) + 1
            ),
            $rows,
            \array_keys($rows)
        );
    }

    private function rowNumber(array $rows): array
    {
        $n = 0;

        return \array_map(
            function (mixed $row) use (&$n): array {
                return $this->annotate($row, ++$n);
            },
            $rows
        );
    }

    private function lag(array $rows): array
    {
        return \array_map(function (mixed $row, int $i) use ($rows): array {
            $prev = $rows[$i - $this->offset] ?? null;
            $value = null !== $prev ? $this->accessor->get($prev, $this->field) : null;

            return $this->annotate($row, $value);
        }, $rows, \array_keys($rows));
    }

    private function lead(array $rows): array
    {
        return \array_map(function (mixed $row, int $i) use ($rows): array {
            $next = $rows[$i + $this->offset] ?? null;
            $value = null !== $next ? $this->accessor->get($next, $this->field) : null;

            return $this->annotate($row, $value);
        }, $rows, \array_keys($rows));
    }

    private function ntile(array $rows): array
    {
        $total = \count($rows);
        $buckets = $this->buckets;

        return \array_map(
            fn (mixed $row, int $i): array => $this->annotate(
                $row,
                \min((int) \floor(($i / $total) * $buckets) + 1, $buckets)
            ),
            $rows,
            \array_keys($rows)
        );
    }

    private function cumeDist(array $rows): array
    {
        $total = \count($rows);
        $values = \array_map(fn ($r) => $this->accessor->get($r, $this->field), $rows);

        return \array_map(function (mixed $row, int $i) use ($values, $total): array {
            $rank = \count(\array_filter($values, static fn ($v) => $v <= $values[$i]));

            return $this->annotate($row, \round($rank / $total, 6));
        }, $rows, \array_keys($rows));
    }

    private function annotate(mixed $row, mixed $value): array
    {
        return \array_merge(\is_array($row) ? $row : (array) $row, [$this->as => $value]);
    }

    public function signature(): string
    {
        return \sprintf(
            'window(fn=%s, field=%s, partition=%s, as=%s)',
            $this->fn,
            $this->field,
            $this->partitionBy ?? 'none',
            $this->as
        );
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
