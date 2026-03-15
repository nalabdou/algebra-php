<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;

/**
 * WHERE — keep only rows matching an expression or closure.
 *
 * ```php
 * ->where("item['status'] == 'paid' and item['amount'] > 100")
 * ->where(fn($r) => $r['status'] === 'paid' && $r['amount'] > 100)
 * ```
 *
 * The row is exposed as `item` in string expressions. Top-level keys
 * are also available directly: `->where("status == 'paid'")`.
 */
final class FilterOperation implements OperationInterface
{
    public function __construct(
        private readonly string|\Closure $expression,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(array $rows): array
    {
        return \array_values(\array_filter(
            $rows,
            fn (mixed $row): bool => $this->evaluator->evaluate($row, $this->expression)
        ));
    }

    /** @internal Used by the query planner to inspect the raw expression. */
    public function expression(): string|\Closure
    {
        return $this->expression;
    }

    public function signature(): string
    {
        return 'where('.($this->expression instanceof \Closure ? 'closure' : $this->expression).')';
    }

    public function selectivity(): float
    {
        return 0.5;
    }
}
