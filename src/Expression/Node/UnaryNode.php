<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A unary prefix operation on a single sub-expression.
 *
 * Operators: `not`, `!` (logical negation), `-` (arithmetic negation).
 *
 * @internal
 */
final class UnaryNode extends Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $operand,
    ) {
    }
}
