<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Aggregate;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;

/**
 * GROUP BY — group rows by the resolved value of a key or expression.
 *
 * Returns an associative array: `['group_key' => [rows...]]`.
 * Chain with {@see AggregateOperation} to collapse groups into summary rows.
 *
 * ```php
 * ->groupBy('status')
 * ->groupBy("item['region'] ~ '-' ~ item['year']")
 * ->groupBy(fn($r) => substr($r['createdAt'], 0, 7)) // YYYY-MM
 * ```
 */
final class GroupByOperation implements OperationInterface
{
    public function __construct(
        private readonly string|\Closure $key,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(array $rows): array
    {
        $groups = [];

        foreach ($rows as $row) {
            $groupKey = (string) $this->evaluator->resolve($row, $this->key);
            $groups[$groupKey][] = $row;
        }

        return $groups;
    }

    public function signature(): string
    {
        $key = $this->key instanceof \Closure ? 'closure' : $this->key;

        return "group_by({$key})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
