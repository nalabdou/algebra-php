<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Adapter;

use Nalabdou\Algebra\Contract\AdapterInterface;

/**
 * Generic \Traversable adapter (excluding generators, handled separately).
 *
 * Covers `ArrayObject`, `SplFixedArray`, `SplDoublyLinkedList`,
 * custom `Iterator` / `IteratorAggregate` implementations, and any other
 * class implementing `\Traversable` that is not a `\Generator`.
 */
final class TraversableAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof \Traversable && !($input instanceof \Generator);
    }

    public function toArray(mixed $input): array
    {
        return \iterator_to_array($input, preserve_keys: false);
    }
}
