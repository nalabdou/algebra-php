<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

use Nalabdou\Algebra\Expression\Node\Node;

/**
 * High-level expression evaluator — the public API for the expression engine.
 *
 * Orchestrates {@see Lexer}, {@see Parser}, {@see Evaluator}, and
 * {@see ExpressionCache} into a single cohesive interface used by all
 * pipeline operations.
 *
 * Two expression styles are supported:
 *
 * String expressions (compiled to AST, then evaluated):
 * ```php
 * $evaluator->evaluate($row, "item['status'] == 'paid'");
 * $evaluator->evaluate($row, "amount > 100 and region == 'Nord'");
 * $evaluator->evaluate($row, "status in ['paid', 'refunded']");
 * $evaluator->evaluate($row, "amount > 500 ? 'high' : 'low'");
 * $evaluator->evaluate($row, "contains(lower(email), '@company.com')");
 * ```
 *
 * The row is available as `item`. All top-level array keys are also bound
 * as direct variables for shorthand access.
 *
 * Closure expressions (native PHP, zero overhead):
 * ```php
 * $evaluator->evaluate($row, fn($r) => $r['status'] === 'paid');
 * $evaluator->resolve($row, fn($r) => $r['user']['name']);
 * ```
 *
 * In strict mode (default), invalid expressions throw `\RuntimeException`.
 * In lenient mode, they return `false` / `null` silently.
 */
final class ExpressionEvaluator
{
    private readonly Lexer $lexer;
    private readonly Parser $parser;
    private readonly Evaluator $evaluator;

    public function __construct(
        private readonly PropertyAccessor $propertyAccessor,
        private readonly ExpressionCache $cache = new ExpressionCache(),
        private readonly bool $strictMode = true,
    ) {
        $this->lexer = new Lexer();
        $this->parser = new Parser();
        $this->evaluator = new Evaluator();
    }

    /**
     * Evaluate a boolean expression against a single row.
     *
     * @param mixed           $row        associative array or object
     * @param string|\Closure $expression string expression or `fn($row): bool`
     *
     * @throws \RuntimeException in strict mode when the expression is invalid
     */
    public function evaluate(mixed $row, string|\Closure $expression): bool
    {
        if ($expression instanceof \Closure) {
            return (bool) $expression($row);
        }

        try {
            return (bool) $this->evaluator->eval($this->getAst($expression), $this->buildContext($row));
        } catch (\Throwable $e) {
            if ($this->strictMode) {
                throw new \RuntimeException("algebra-php expression error.\nExpression : {$expression}\nReason     : {$e->getMessage()}", 0, $e);
            }

            return false;
        }
    }

    /**
     * Evaluate an expression that returns a scalar value.
     *
     * Used by map, groupBy, and sort operations.
     *
     * @param mixed           $row        associative array or object
     * @param string|\Closure $expression string expression or `fn($row): mixed`
     *
     * @throws \RuntimeException in strict mode when the expression is invalid
     */
    public function resolve(mixed $row, string|\Closure $expression): mixed
    {
        if ($expression instanceof \Closure) {
            return $expression($row);
        }

        if (\preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $expression)) {
            return $this->propertyAccessor->get($row, $expression);
        }

        try {
            return $this->evaluator->eval($this->getAst($expression), $this->buildContext($row));
        } catch (\Throwable $e) {
            if ($this->strictMode) {
                throw new \RuntimeException("algebra-php expression error.\nExpression : {$expression}\nReason     : {$e->getMessage()}", 0, $e);
            }

            return null;
        }
    }

    private function getAst(string $expression): Node
    {
        $ast = $this->cache->get($expression);

        if (null === $ast) {
            $ast = $this->parser->parse($this->lexer->tokenise($expression));
            $this->cache->set($expression, $ast);
        }

        return $ast;
    }

    private function buildContext(mixed $row): array
    {
        return [
            'item' => $row,
            ...(\is_array($row) ? $row : []),
        ];
    }
}
