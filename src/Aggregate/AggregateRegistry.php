<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate;

use Nalabdou\Algebra\Aggregate\Math\AvgAggregate;
use Nalabdou\Algebra\Aggregate\Math\CountAggregate;
use Nalabdou\Algebra\Aggregate\Math\MaxAggregate;
use Nalabdou\Algebra\Aggregate\Math\MedianAggregate;
use Nalabdou\Algebra\Aggregate\Math\MinAggregate;
use Nalabdou\Algebra\Aggregate\Math\PercentileAggregate;
use Nalabdou\Algebra\Aggregate\Math\StddevAggregate;
use Nalabdou\Algebra\Aggregate\Math\SumAggregate;
use Nalabdou\Algebra\Aggregate\Math\VarianceAggregate;
use Nalabdou\Algebra\Aggregate\Positional\FirstAggregate;
use Nalabdou\Algebra\Aggregate\Positional\LastAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\CountDistinctAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\CumeDistAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\ModeAggregate;
use Nalabdou\Algebra\Aggregate\Statistical\NtileAggregate;
use Nalabdou\Algebra\Aggregate\String\BoolAndAggregate;
use Nalabdou\Algebra\Aggregate\String\BoolOrAggregate;
use Nalabdou\Algebra\Aggregate\String\StringAggAggregate;
use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * Registry of all aggregate functions available in the spec DSL.
 *
 * ### Built-in aggregates
 *
 * **Math** — `count`, `sum`, `avg`, `min`, `max`, `median`, `stddev`, `variance`, `percentile`
 *
 * **Statistical** — `mode`, `count_distinct`, `ntile`, `cume_dist`
 *
 * **Positional** — `first`, `last`
 *
 * **String / Bool** — `string_agg`, `bool_and`, `bool_or`
 *
 * ### Registering custom aggregates
 * ```php
 * Algebra::aggregates()->register(new GeomeanAggregate());
 *
 * // Then use in any pipeline
 * Algebra::from($data)->aggregate(['geo' => 'geomean(price)']);
 * ```
 */
final class AggregateRegistry
{
    /** @var array<string, AggregateInterface> */
    private array $aggregates = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    /**
     * Register a custom aggregate function.
     *
     * Overwrites any existing aggregate with the same name, which allows
     * replacing built-in implementations when needed.
     */
    public function register(AggregateInterface $aggregate): void
    {
        $this->aggregates[$aggregate->name()] = $aggregate;
    }

    /**
     * Retrieve a registered aggregate by name.
     *
     * @throws \InvalidArgumentException when the function name is unknown
     */
    public function get(string $name): AggregateInterface
    {
        return $this->aggregates[$name] ?? throw new \InvalidArgumentException(\sprintf("Unknown aggregate function '%s'. Available: %s.", $name, \implode(', ', \array_keys($this->aggregates))));
    }

    /** Whether a function name is registered. */
    public function has(string $name): bool
    {
        return isset($this->aggregates[$name]);
    }

    /** @return array<string, AggregateInterface> */
    public function all(): array
    {
        return $this->aggregates;
    }

    private function registerBuiltins(): void
    {
        foreach ([
            // Math
            new CountAggregate(),
            new SumAggregate(),
            new AvgAggregate(),
            new MinAggregate(),
            new MaxAggregate(),
            new MedianAggregate(),
            new StddevAggregate(),
            new VarianceAggregate(),
            new PercentileAggregate(),
            // Statistical
            new ModeAggregate(),
            new CountDistinctAggregate(),
            new NtileAggregate(),
            new CumeDistAggregate(),
            // Positional
            new FirstAggregate(),
            new LastAggregate(),
            // String / Bool
            new StringAggAggregate(),
            new BoolAndAggregate(),
            new BoolOrAggregate(),
        ] as $aggregate) {
            $this->register($aggregate);
        }
    }
}
