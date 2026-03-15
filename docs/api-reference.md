# API Reference

Complete reference for all public methods on `RelationalCollection`.

---

## Entry point

### `Algebra::from(mixed $input): RelationalCollection`

Create a lazy collection from any supported input.

```php
Algebra::from($array)
Algebra::from($generator)
Algebra::from($traversable)
```

### `Algebra::pipe(mixed $input, callable $fn): array`

Build and immediately execute a pipeline.

```php
Algebra::pipe($orders, fn($c) => $c->where(...)->orderBy('amount', 'desc'))
```

### `Algebra::parallel(array $pipelines): array`

Run multiple pipelines concurrently via PHP Fibers.

```php
Algebra::parallel(['paid' => $c1, 'report' => $c2])
```

### `Algebra::aggregates(): AggregateRegistry`

Access the aggregate registry to register custom functions.

```php
Algebra::aggregates()->register(new GeomeanAggregate());
```

### `Algebra::planner(): QueryPlanner`

Access the query planner to inspect optimization plans.

```php
Algebra::planner()->explain($collection->operations())
```

### `Algebra::reset(): void`

Reset all singletons. Useful in tests.

---

## Join operations

### `innerJoin(mixed $right, string $leftKey, string $rightKey, string $as): static`

INNER JOIN — merge rows where keys match. Drops unmatched left rows.

### `leftJoin(mixed $right, string $on, string $as): static`

LEFT JOIN — keep all left rows; attach matched right row or `null`.
`$on` format: `"leftKey=rightKey"`.

### `semiJoin(mixed $right, string $leftKey, string $rightKey): static`

EXISTS — keep left rows that have at least one match. No data merged.

### `antiJoin(mixed $right, string $leftKey, string $rightKey): static`

NOT EXISTS — keep left rows that have no match.

### `crossJoin(mixed $right, string $leftPrefix, string $rightPrefix): static`

Cartesian product. All left × all right.

### `zip(mixed $right, string $leftAs, string $rightAs): static`

Positional merge by index. Output length = min(left, right).

---

## Set operations

### `intersect(mixed $right, string $by): static`

A ∩ B — rows whose key exists in both collections.

### `except(mixed $right, string $by): static`

A − B — rows whose key exists only in the left collection.

### `union(mixed $right, string|null $by): static`

A ∪ B — merged and deduplicated. First occurrence wins.

### `symmetricDiff(mixed $right, string $by): static`

A △ B — rows exclusive to each side.

---

## Filter & projection

### `where(string|\Closure $expression): static`

Keep only matching rows. Row exposed as `item` in string expressions.

### `select(string|\Closure $expression): static`

Transform each row via expression or closure.

---

## Grouping & aggregation

### `groupBy(string|\Closure $key): static`

Group rows. Returns `['key' => [rows...]]`. Chain with `aggregate()`.

### `aggregate(array $specs): static`

Compute aggregate functions. Each spec: `'alias' => 'fn(field)'`.

### `tally(string $field): static`

Count distinct values. Returns `['value' => count]` sorted descending.

### `partition(string|\Closure $expression): PartitionResult`

Split into pass/fail in one iteration. **Terminal method — returns PartitionResult, not a collection.**

---

## Window functions

### `window(string $fn, string $field, ?string $partitionBy, string $as, int $offset, int $buckets): static`

Enrich rows with a computed value. Available functions:
`running_sum`, `running_avg`, `running_count`, `running_diff`,
`rank`, `dense_rank`, `row_number`, `lag`, `lead`, `ntile`, `cume_dist`.

### `movingAverage(string $field, int $window, string $as): static`

Sliding window average. Rows with insufficient history receive `null`.

### `normalize(string $field, string $as): static`

Min-max normalization to [0.0, 1.0].

---

## Pivot

### `pivot(string $rows, string $cols, string $value, string $aggregateFn): static`

Cross-tab matrix. Missing cells are `null`.

---

## Sorting & slicing

### `orderBy(string|array $key, string $direction): static`

Sort rows. Pass array of `[['field', 'dir'], ...]` for multi-key.

### `limit(int $limit, int $offset): static`

Return at most `$limit` rows, skipping `$offset`.

### `topN(int $n, string $by): static`

Shorthand: `orderBy($by, 'desc') + limit($n)`.

### `bottomN(int $n, string $by): static`

Shorthand: `orderBy($by, 'asc') + limit($n)`.

### `rankBy(string $field, string $direction, string $as): static`

Sort + annotate each row with a sequential rank number (1-based).

---

## Structural operations

### `distinct(string $key): static`

Remove duplicate rows by key. First occurrence wins.

### `reindex(string $key): static`

Key the output array by a field value for O(1) lookup.

### `pluck(string $field): static`

Extract one column into a flat zero-indexed array.

### `chunk(int $size): static`

Split into fixed-size sub-arrays.

### `fillGaps(string $key, array $series, array $default): static`

Insert default rows for missing series entries.

### `transpose(): static`

Flip rows ↔ columns.

### `sample(int $count, ?int $seed): static`

Random N rows. `$seed` for reproducibility.

---

## Terminal operations

### `toArray(): array`

Execute pipeline and return plain PHP array.

### `materialize(): MaterializedCollection`

Execute pipeline and return `MaterializedCollection` (includes execution log).

### `count(): int`

Execute and return row count.

### `getIterator(): \ArrayIterator`

Iterate with `foreach` (triggers execution).

### `operations(): array`

Return declared (pre-optimization) operation list.

### `source(): array`

Return the raw source array.

---

## MaterializedCollection methods

### `toArray(): array`
### `count(): int`
### `first(): mixed` — first row or null
### `last(): mixed` — last row or null
### `isEmpty(): bool`
### `executionLog(): array` — per-operation metrics
### `totalDurationMs(): float` — total pipeline wall-clock time

---

## PartitionResult methods

### `pass(): array` — matching rows
### `fail(): array` — non-matching rows
### `passCount(): int`
### `failCount(): int`
### `totalCount(): int`
### `passRate(): float` — fraction 0.0–1.0
### `toArray(): array` — `['pass' => [...], 'fail' => [...]]`
