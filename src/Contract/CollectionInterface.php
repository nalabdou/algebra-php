<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

use Nalabdou\Algebra\Collection\MaterializedCollection;

/**
 * Represents a lazy or materialized collection of rows.
 *
 * A collection wraps a data source (array, generator, Doctrine collection…)
 * and exposes a composable pipeline of operations. Nothing executes until
 * the collection is iterated or {@see materialize()} is called.
 *
 * @template TRow of array<string, mixed>
 *
 * @extends \IteratorAggregate<int, TRow>
 */
interface CollectionInterface extends \Countable, \IteratorAggregate
{
    /**
     * Execute the full operation chain and return the evaluated result.
     *
     * Subsequent calls return the same cached instance unless the pipeline
     * has been modified via {@see pipe()}.
     */
    public function materialize(): MaterializedCollection;

    /**
     * Execute the pipeline and return a plain PHP array.
     *
     * Shorthand for {@see materialize()->toArray()}.
     */
    public function toArray(): array;

    /**
     * Append an operation to the pipeline and return a new immutable instance.
     *
     * The current collection is never mutated — this is a pure value object.
     *
     * @return static a new collection with the operation appended
     */
    public function pipe(OperationInterface $operation): static;
}
