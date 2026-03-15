<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Planner\Pass;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Operation\Utility\MapOperation;

/**
 * Collapse two consecutive closure-based {@see MapOperation}s into one composed closure.
 *
 * Instead of iterating the collection twice:
 * ```
 * select(fn1) → select(fn2)
 * ```
 * Produces a single pass with O(n) cost instead of O(2n):
 * ```
 * select(fn2 ∘ fn1)
 * ```
 *
 * Only applies to closure-based maps. String-expression maps are left in place
 * because their ASTs cannot be composed without re-parsing both sides.
 */
final class CollapseConsecutiveMaps implements PassInterface
{
    public function __construct(
        private readonly ?ExpressionEvaluator $evaluator = null,
    ) {
    }

    /**
     * @param OperationInterface[] $operations
     *
     * @return OperationInterface[]
     */
    public function apply(array $operations): array
    {
        if (null === $this->evaluator) {
            return $operations;
        }

        $result = [];
        $count = \count($operations);
        $i = 0;

        while ($i < $count) {
            $current = $operations[$i];
            $next = $operations[$i + 1] ?? null;

            if (
                $current instanceof MapOperation
                && $current->isClosureBased()
                && $next instanceof MapOperation
                && $next->isClosureBased()
            ) {
                $fn1 = $current->getClosure();
                $fn2 = $next->getClosure();
                $composed = static fn (mixed $row): mixed => $fn2($fn1($row));
                $result[] = new MapOperation($composed, $this->evaluator);
                $i += 2;
                continue;
            }

            $result[] = $current;
            ++$i;
        }

        return $result;
    }
}
