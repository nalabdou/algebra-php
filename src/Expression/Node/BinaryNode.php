<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A binary infix operation between two sub-expressions.
 *
 * Operators: `==`, `!=`, `<`, `<=`, `>`, `>=`, `and`, `or`, `&&`, `||`,
 * `+`, `-`, `*`, `/`, `%`, `**`, `~` (concat), `in`.
 *
 * @internal
 */
final class BinaryNode extends Node
{
    public function __construct(
        public readonly string $operator,
        public readonly Node $left,
        public readonly Node $right,
    ) {
    }
}
