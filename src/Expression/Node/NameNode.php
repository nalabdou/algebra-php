<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * A bare variable name resolved against the evaluation context.
 *
 * Examples: `status`, `item`, `amount`.
 * The name is looked up as a key in the context array built by
 * {@see \Nalabdou\Algebra\Expression\ExpressionEvaluator}.
 *
 * @internal
 */
final class NameNode extends Node
{
    public function __construct(public readonly string $name)
    {
    }
}
