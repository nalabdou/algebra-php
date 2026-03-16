<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Collection;

use Nalabdou\Algebra\Adapter\AdapterRegistry;
use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Contract\AdapterInterface;
use Nalabdou\Algebra\Contract\PlannerInterface;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * Factory that converts any supported input into a {@see RelationalCollection}.
 *
 * Adapters are resolved from an {@see AdapterRegistry} in priority order.
 * The first adapter whose {@see AdapterInterface::supports()} returns `true`
 * is used to convert the input.
 *
 * ### Default resolution order
 *
 * 1. Fast path — plain PHP `array` (no adapter overhead)
 * 2. `GeneratorAdapter` (priority 20)
 * 3. `TraversableAdapter` (priority 10)
 * 4. `ArrayAdapter` (priority 0)
 * 5. Any custom adapters registered via `Algebra::adapters()->register()`
 *
 * ### Custom adapter registration
 *
 * ```php
 * // Register once at application bootstrap
 * Algebra::adapters()->register(new CsvFileAdapter(), priority: 50);
 * Algebra::adapters()->register(new DoctrineQueryBuilderAdapter(), priority: 100);
 *
 * // Then Algebra::from() accepts the new input types automatically
 * Algebra::from('/data/orders.csv')->where(...)->toArray();
 * Algebra::from($queryBuilder)->groupBy('region')->toArray();
 * ```
 *
 * ### Framework-specific factories
 * Framework bundles (algebra-symfony, algebra-laravel) extend this factory
 * by injecting additional adapters into the `AdapterRegistry` singleton.
 */
final class CollectionFactory
{
    public function __construct(
        private readonly PlannerInterface $planner,
        private readonly ExpressionEvaluator $evaluator,
        private readonly PropertyAccessor $accessor,
        private readonly AggregateRegistry $aggregates,
        private readonly AdapterRegistry $adapterRegistry,
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
        // Fast path — arrays are by far the most common input
        if (\is_array($input)) {
            return \array_values($input);
        }

        $adapter = $this->adapterRegistry->find($input);

        if (null !== $adapter) {
            return $adapter->toArray($input);
        }

        throw new \InvalidArgumentException(\sprintf('algebra-php cannot convert %s into a RelationalCollection. Register a custom %s via Algebra::adapters()->register(new YourAdapter()).', \get_debug_type($input), AdapterInterface::class));
    }
}
