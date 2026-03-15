<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression\Node;

/**
 * Dot-notation property access on an object or nested array.
 *
 * Example: `user.name` — evaluates the left side, then reads the named
 * property from the result using getter discovery or direct property access.
 *
 * @internal
 */
final class PropertyNode extends Node
{
    public function __construct(
        public readonly Node $object,
        public readonly string $property,
    ) {
    }
}
