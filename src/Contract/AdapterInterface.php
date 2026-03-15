<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * Converts an arbitrary input type into a plain PHP array of rows.
 *
 * Adapters are registered in {@see \Nalabdou\Algebra\Collection\CollectionFactory}
 * and checked in priority order. The first adapter that returns `true` from
 * {@see supports()} is used to convert the input.
 *
 * Built-in adapters:
 *   - {@see \Nalabdou\Algebra\Adapter\GeneratorAdapter}    — PHP generators
 *   - {@see \Nalabdou\Algebra\Adapter\TraversableAdapter}  — \Traversable
 *   - {@see \Nalabdou\Algebra\Adapter\ArrayAdapter}        — plain arrays
 *
 * Third-party adapters (separate packages):
 *   - `nalabdou/algebra-doctrine` — [*Comming soon*] Doctrine collections, QueryBuilder
 *   - `nalabdou/algebra-laravel`  — [*Comming soon*] Eloquent collections
 *   - `nalabdou/algebra-csv`      — [*Comming soon*] CSV file streaming
 */
interface AdapterInterface
{
    /**
     * Returns true if this adapter can handle the given input.
     */
    public function supports(mixed $input): bool;

    /**
     * Convert the input to a zero-indexed array of rows.
     *
     * Each row should be an associative array or an object whose properties
     * can be accessed via {@see \Nalabdou\Algebra\Expression\PropertyAccessor}.
     *
     * @return array<int, mixed>
     */
    public function toArray(mixed $input): array;
}
