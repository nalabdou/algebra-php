<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Aggregate;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Result\PartitionResult;

/**
 * PARTITION — split rows into pass/fail groups in a **single iteration**.
 *
 * Returns a single-element array containing a {@see PartitionResult}.
 * The result is unwrapped by {@see \Nalabdou\Algebra\Collection\RelationalCollection::partition()}.
 *
 * Unlike running two `where()` calls, this iterates the collection once
 * and produces both groups simultaneously — O(n) vs O(2n).
 */
final class PartitionOperation implements OperationInterface
{
    public function __construct(
        private readonly string|\Closure $expression,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(array $rows): array
    {
        $pass = [];
        $fail = [];

        foreach ($rows as $row) {
            $this->evaluator->evaluate($row, $this->expression)
                ? $pass[] = $row
                : $fail[] = $row;
        }

        return [new PartitionResult($pass, $fail)];
    }

    public function signature(): string
    {
        $expr = $this->expression instanceof \Closure ? 'closure' : $this->expression;

        return "partition({$expr})";
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
