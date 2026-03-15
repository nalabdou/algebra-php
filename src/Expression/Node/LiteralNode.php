<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A literal scalar value in an expression.
 *
 * Produced by the lexer for integers, floats, strings, booleans, and null:
 * `42`, `3.14`, `'paid'`, `true`, `false`, `null`.
 *
 * @internal
 */
final class LiteralNode extends Node
{
    public function __construct(public readonly mixed $value)
    {
    }
}
