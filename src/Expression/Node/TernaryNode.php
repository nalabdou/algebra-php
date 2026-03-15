<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A ternary conditional expression.
 *
 * Syntax: `condition ? then : else`.
 * The condition is evaluated first; only the selected branch is evaluated,
 * allowing safe short-circuit behaviour.
 *
 * @internal
 */
final class TernaryNode extends Node
{
    public function __construct(
        public readonly Node $condition,
        public readonly Node $then,
        public readonly Node $else,
    ) {
    }
}
