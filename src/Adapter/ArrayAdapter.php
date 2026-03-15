<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Adapter;

use Nalabdou\Algebra\Contract\AdapterInterface;

/**
 * Plain PHP array adapter.
 *
 * Handled inline in {@see \Nalabdou\Algebra\Collection\CollectionFactory} as a fast
 * path before the adapter loop. This class exists for completeness and
 * explicit registration in custom factory configurations.
 */
final class ArrayAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return \is_array($input);
    }

    public function toArray(mixed $input): array
    {
        return \is_array($input) ? \array_values($input) : [];
    }
}
