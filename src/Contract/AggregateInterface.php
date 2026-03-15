<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Contract;

/**
 * A single aggregate function applied over a set of scalar values.
 *
 * Aggregates are registered in {@see \Nalabdou\Algebra\Aggregate\AggregateRegistry}
 * and referenced by name inside the aggregate spec DSL:
 *
 * ```php
 * ->aggregate([
 *     'total'   => 'sum(amount)',
 *     'average' => 'avg(amount)',
 *     'labels'  => 'string_agg(name, ", ")',
 * ])
 * ```
 *
 * To create a custom aggregate, implement this interface and register it:
 *
 * ```php
 * Algebra::aggregates()->register(new MyCustomAggregate());
 * ```
 */
interface AggregateInterface
{
    /**
     * The function name used in the aggregate spec DSL.
     *
     * Must be unique within the {@see \Nalabdou\Algebra\Aggregate\AggregateRegistry}.
     * Use snake_case: `'count'`, `'string_agg'`, `'bool_and'`.
     */
    public function name(): string;

    /**
     * Compute the aggregate over a flat list of scalar values.
     *
     * Null values are pre-filtered by {@see \Nalabdou\Algebra\Operation\Aggregate\AggregateOperation}
     * before this method is called. An empty `$values` array means all group
     * values were null — handle gracefully by returning null.
     *
     * @param array<int, mixed> $values non-null scalar values from the group
     *
     * @return int|float|string|bool|array<mixed>|null
     */
    public function compute(array $values): mixed;
}
