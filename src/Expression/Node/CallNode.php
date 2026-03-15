<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A function call with zero or more argument expressions.
 *
 * Example: `length(name)`, `contains(email, '@example.com')`, `now()`.
 * The function is dispatched by name in
 * {@see \Nalabdou\Algebra\Expression\Evaluator}.
 *
 * @internal
 */
final class CallNode extends Node
{
    /** @param Node[] $arguments */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {
    }
}
