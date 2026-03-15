<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Aggregate;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;

/**
 * AGGREGATE — compute aggregate functions over groups or flat collections.
 *
 * ### Spec DSL
 * ```php
 * ->aggregate([
 *     'count'        => 'count(*)',
 *     'total'        => 'sum(amount)',
 *     'average'      => 'avg(amount)',
 *     'minimum'      => 'min(amount)',
 *     'maximum'      => 'max(amount)',
 *     'median_val'   => 'median(amount)',
 *     'std_dev'      => 'stddev(amount)',
 *     'p90'          => 'percentile(amount, 0.9)',
 *     'unique_users' => 'count_distinct(userId)',
 *     'product_list' => 'string_agg(name, ", ")',
 *     'all_sent'     => 'bool_and(sent)',
 *     'any_digital'  => 'bool_or(isDigital)',
 * ])
 * ```
 *
 * ### Input formats
 * - **Grouped** (from `groupBy`): `['paid' => [rows...], 'pending' => [rows...]]`
 *   → each group produces one output row with `_group` key
 * - **Flat** (plain array): treated as one group with `_group => '*'`
 *
 * ### Output
 * Each output row: `['_group' => 'groupKey', 'alias1' => value1, ...]`
 */
final class AggregateOperation implements OperationInterface
{
    /**
     * @param array<string, string> $specs     alias → spec string map
     * @param AggregateRegistry     $registry  registry of aggregate functions
     * @param ExpressionEvaluator   $evaluator used to resolve field paths in specs
     */
    public function __construct(
        private readonly array $specs,
        private readonly AggregateRegistry $registry,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(array $rows): array
    {
        $isGrouped = !empty($rows)
            && \is_array(\reset($rows))
            && \is_string(\array_key_first($rows));

        return $isGrouped
            ? $this->aggregateGroups($rows)
            : [$this->buildOutputRow('*', $rows)];
    }

    private function aggregateGroups(array $groups): array
    {
        return \array_values(\array_map(
            fn (string $key, array $groupRows): array => $this->buildOutputRow($key, $groupRows),
            \array_keys($groups),
            $groups
        ));
    }

    private function buildOutputRow(string $groupKey, array $rows): array
    {
        $row = ['_group' => $groupKey];

        foreach ($this->specs as $alias => $spec) {
            $row[$alias] = $this->resolveSpec($spec, $rows);
        }

        return $row;
    }

    private function resolveSpec(string $spec, array $rows): mixed
    {
        // string_agg(field, "separator")
        if (\preg_match('/^string_agg\((.+?),\s*["\'](.+?)["\']\)$/u', $spec, $m)) {
            $values = \array_map(fn (mixed $r): string => (string) $this->evaluator->resolve($r, $m[1]), $rows);
            $filled = \array_filter($values, static fn (string $v): bool => '' !== $v);

            return $filled ? \implode($m[2], $filled) : null;
        }

        // bool_and(field) | bool_or(field)
        if (\preg_match('/^(bool_and|bool_or)\((.+)\)$/', $spec, $m)) {
            $values = \array_map(fn (mixed $r): bool => (bool) $this->evaluator->resolve($r, $m[2]), $rows);

            return 'bool_and' === $m[1]
                ? !\in_array(false, $values, strict: true)
                : \in_array(true, $values, strict: true);
        }

        // percentile(field, 0.9)
        if (\preg_match('/^percentile\((.+?),\s*([0-9.]+)\)$/', $spec, $m)) {
            $values = \array_map(fn (mixed $r): float => (float) $this->evaluator->resolve($r, $m[1]), $rows);
            \sort($values);
            $idx = (int) \ceil((float) $m[2] * \count($values)) - 1;

            return $values[\max(0, $idx)] ?? null;
        }

        // Standard: fn(field) | fn(*)
        if (!\preg_match('/^(\w+)\((.+)\)$/', $spec, $m)) {
            throw new \InvalidArgumentException("Invalid aggregate spec: '{$spec}'. Expected format: fn(field) — e.g. 'sum(amount)', 'count(*)'.");
        }

        [$fn, $field] = [$m[1], $m[2]];

        $aggregate = $this->registry->get($fn);

        $values = '*' === $field
            ? \array_fill(0, \count($rows), 1)
            : \array_map(fn (mixed $r): mixed => $this->evaluator->resolve($r, $field), $rows);

        return $aggregate->compute(
            \array_values(\array_filter($values, static fn (mixed $v): bool => null !== $v))
        );
    }

    public function signature(): string
    {
        return 'aggregate('.\implode(', ', \array_keys($this->specs)).')';
    }

    public function selectivity(): float
    {
        return 0.1;
    }
}
