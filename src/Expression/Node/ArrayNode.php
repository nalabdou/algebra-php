<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * An inline array literal used with the `in` operator.
 *
 * Example: `status in ['paid', 'refunded', 'completed']`.
 * Each element is itself a Node and evaluated at runtime.
 *
 * @internal
 */
final class ArrayNode extends Node
{
    /** @param Node[] $elements */
    public function __construct(public readonly array $elements)
    {
    }
}
