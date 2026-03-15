<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Planner;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Contract\PlannerInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Planner\Pass\CollapseConsecutiveMaps;
use Nalabdou\Algebra\Planner\Pass\EliminateRedundantSort;
use Nalabdou\Algebra\Planner\Pass\PushFilterBeforeAntiJoin;
use Nalabdou\Algebra\Planner\Pass\PushFilterBeforeJoin;

/**
 * Rewrites an operation chain into a more efficient execution order.
 *
 * Optimization passes run sequentially. Each pass receives the full chain
 * and returns a reordered or collapsed version. All passes are idempotent —
 * running the planner twice on the same chain produces the same result.
 *
 * ### Built-in optimization passes (run in this order)
 * 1. {@see PushFilterBeforeJoin}     — reduces join input size
 * 2. {@see PushFilterBeforeAntiJoin} — reduces semi/anti-join input size
 * 3. {@see EliminateRedundantSort}   — drops back-to-back sorts
 * 4. {@see CollapseConsecutiveMaps}  — merges adjacent closure maps (requires evaluator)
 *
 * Pass #4 is only active when an {@see ExpressionEvaluator} is injected.
 * {@see Algebra} injects the singleton evaluator automatically.
 *
 * ### Usage
 * ```php
 * // Automatic — the planner runs transparently before every pipeline execution
 *
 * // Manual inspection
 * $plan = Algebra::planner()->explain($collection->operations());
 * ```
 */
final class QueryPlanner implements PlannerInterface
{
    /** @var PassInterface[] */
    private readonly array $passes;

    public function __construct(?ExpressionEvaluator $evaluator = null)
    {
        $this->passes = [
            new PushFilterBeforeJoin(),
            new PushFilterBeforeAntiJoin(),
            new EliminateRedundantSort(),
            new CollapseConsecutiveMaps($evaluator),
        ];
    }

    /**
     * Optimise an ordered list of operations.
     *
     * @param OperationInterface[] $operations declared pipeline order
     *
     * @return OperationInterface[] optimised execution order
     */
    public function optimize(array $operations): array
    {
        foreach ($this->passes as $pass) {
            $operations = $pass->apply($operations);
        }

        return $operations;
    }

    /**
     * Return a human-readable diff between original and optimised chains.
     *
     * Useful for debugging, testing, and profiler panels.
     *
     * @param OperationInterface[] $operations the declared (pre-optimisation) chain
     *
     * @return array{original: string[], optimized: string[], changed: bool, passes: string[]}
     */
    public function explain(array $operations): array
    {
        $original = \array_map(static fn (OperationInterface $op): string => $op->signature(), $operations);
        $optimized = \array_map(static fn (OperationInterface $op): string => $op->signature(), $this->optimize($operations));

        return [
            'original' => $original,
            'optimized' => $optimized,
            'changed' => $original !== $optimized,
            'passes' => \array_map(static fn (PassInterface $p): string => $p::class, $this->passes),
        ];
    }
}
