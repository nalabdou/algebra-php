<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;

/**
 * SELECT / MAP — project each row through an expression or closure.
 *
 * ```php
 * ->select('id') // pluck single field
 * ->select(fn($r) => ['id' => $r['id'], 'name' => strtoupper($r['name'])])
 * ```
 */
final class MapOperation implements OperationInterface
{
    public function __construct(
        private readonly string|\Closure $expression,
        private readonly ExpressionEvaluator $evaluator,
    ) {
    }

    public function execute(array $rows): array
    {
        return \array_map(
            fn (mixed $row): mixed => $this->evaluator->resolve($row, $this->expression),
            $rows
        );
    }

    /** Whether this operation holds a native closure. */
    public function isClosureBased(): bool
    {
        return $this->expression instanceof \Closure;
    }

    /** @throws \LogicException When the expression is not a closure. */
    public function getClosure(): \Closure
    {
        if (!$this->expression instanceof \Closure) {
            throw new \LogicException('MapOperation does not hold a closure expression.');
        }

        return $this->expression;
    }

    public function signature(): string
    {
        return 'select('.($this->expression instanceof \Closure ? 'closure' : $this->expression).')';
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
