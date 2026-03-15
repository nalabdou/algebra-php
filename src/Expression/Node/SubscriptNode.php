<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * Bracket-notation subscript access on an array or object.
 *
 * Examples: `item['key']`, `item["key"]`, chained `a['b']['c']`.
 * The key is itself a Node — it can be a literal, a variable, or an expression.
 *
 * @internal
 */
final class SubscriptNode extends Node
{
    public function __construct(
        public readonly Node $object,
        public readonly Node $key,
    ) {
    }
}
