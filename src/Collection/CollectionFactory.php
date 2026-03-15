<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Collection;

use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Contract\AdapterInterface;
use Nalabdou\Algebra\Contract\PlannerInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * Factory that converts any supported input into a {@see RelationalCollection}.
 *
 * Adapters are checked in registration order — the first one that returns
 * `true` from {@see AdapterInterface::supports()} is used. Built-in order:
 *
 *  1. {@see \Nalabdou\Algebra\Adapter\GeneratorAdapter}   — PHP generators
 *  2. {@see \Nalabdou\Algebra\Adapter\TraversableAdapter} — \Traversable
 *  3. {@see \Nalabdou\Algebra\Adapter\ArrayAdapter}       — plain PHP arrays
 *
 * Plain arrays are also handled inline before the adapter loop as a fast path.
 *
 * ### Framework-specific factories
 * Framework bundles (algebra-symfony, algebra-laravel) extend this factory
 * by injecting additional adapters (Doctrine, Eloquent, QueryBuilder…).
 */
final class CollectionFactory
{
    /**
     * @param AdapterInterface[] $adapters ordered list of adapters to try
     */
    public function __construct(
        private readonly PlannerInterface $planner,
        private readonly ExpressionEvaluator $evaluator,
        private readonly PropertyAccessor $accessor,
        private readonly AggregateRegistry $aggregates,
        private readonly array $adapters = [],
    ) {
    }

    /**
     * Wrap any supported input in a lazy {@see RelationalCollection}.
     *
     * @param mixed $input array, generator, Traversable, or any registered adapter type
     *
     * @return RelationalCollection ready-to-use lazy collection
     *
     * @throws \InvalidArgumentException when no adapter supports the given input
     */
    public function create(mixed $input): RelationalCollection
    {
        return new RelationalCollection(
            source: $this->resolve($input),
            planner: $this->planner,
            evaluator: $this->evaluator,
            accessor: $this->accessor,
            aggregates: $this->aggregates,
        );
    }

    private function resolve(mixed $input): array
    {
        if (\is_array($input)) {
            return \array_values($input);
        }

        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($input)) {
                return $adapter->toArray($input);
            }
        }

        if ($input instanceof \Traversable) {
            return \iterator_to_array($input, preserve_keys: false);
        }

        throw new \InvalidArgumentException(\sprintf('algebra-php cannot convert %s into a RelationalCollection. Register a custom %s implementation.', \get_debug_type($input), AdapterInterface::class));
    }
}
