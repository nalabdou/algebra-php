# Core concepts

## RelationalCollection — the pipeline

A `RelationalCollection` is a **lazy, immutable pipeline**. It holds:
- A source array of rows
- A list of pending operations
- References to infrastructure (evaluator, planner, aggregates)

Nothing executes until the collection is materialized.

```
Algebra::from($orders)        ← source array
    ->where(...)              ← FilterOperation appended
    ->innerJoin($users, ...)  ← JoinOperation appended
    ->orderBy('amount', 'desc') ← SortOperation appended
    ->toArray()               ← QueryPlanner runs, then operations execute in order
```

---

## Immutability

Every operation method returns a **clone** with the new operation appended. The original is never mutated.

```php
$a = Algebra::from($orders);
$b = $a->where("item['status'] == 'paid'");
$c = $a->where("item['status'] == 'pending'");

// $a has 0 operations
// $b has 1 operation (filter paid)
// $c has 1 operation (filter pending)
// All three share the same source array — no duplication
```

This makes **branching** safe and memory-efficient:

```php
$paid = Algebra::from($orders)->where("item['status'] == 'paid'");

$report   = $paid->groupBy('region')->aggregate([...]);
$top10    = $paid->topN(10, by: 'amount');
$withUser = $paid->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner');
// $paid is unchanged
```

---

## Lazy evaluation

Operations are queued, not executed. Execution happens at the last possible moment — when you actually need the data.

```php
$pipeline = Algebra::from($orders)
    ->where("item['status'] == 'paid'")  // queued
    ->orderBy('amount', 'desc');          // queued

// Nothing has executed yet

foreach ($pipeline as $row) {
    // First iteration triggers execution
}
```

Materialization caches the result. Calling `toArray()` twice on the same instance executes only once:

```php
$col   = Algebra::from($orders)->where("item['amount'] > 100");
$arr1  = $col->toArray(); // executes
$arr2  = $col->toArray(); // returns cached result
```

---

## The query planner

Before executing, the `QueryPlanner` rewrites the operation chain for efficiency. This is transparent — the result is always identical.

**Example: filter pushdown before join**

```
Declared:  inner_join(userId=id) → where(status=='paid')
Optimized: where(status=='paid') → inner_join(userId=id)
```

Why it matters: filtering first reduces the number of rows that participate in the join.

- Before: O(1000 × 200) join → O(200000 × 0.5) filter = 200000 operations
- After: O(1000 × 0.5) filter → O(500 × 200) join = 100500 operations

**Built-in optimization passes:**
1. `PushFilterBeforeJoin` — moves `where` before `innerJoin`/`leftJoin`
2. `PushFilterBeforeAntiJoin` — moves `where` before `semiJoin`/`antiJoin`
3. `EliminateRedundantSort` — drops consecutive `orderBy` on the same key
4. `CollapseConsecutiveMaps` — merges adjacent closure `select` calls

See [Query planner](internals/query-planner.md) for how to inspect the plan.

---

## Operations

Every operation implements `OperationInterface`:

```php
interface OperationInterface
{
    public function execute(array $rows): array;
    public function signature(): string;   // human-readable description
    public function selectivity(): float;  // 0.0–∞ output/input ratio hint
}
```

Operations live under five namespaces:

| Namespace | Purpose |
|---|---|
| `Operation\Join\` | Merge two collections by key or position |
| `Operation\Set\` | Set algebra (intersect, except, union, diff) |
| `Operation\Aggregate\` | Collapse rows (groupBy, aggregate, tally, partition) |
| `Operation\Window\` | Enrich rows without collapsing (running totals, rank, lag) |
| `Operation\Utility\` | Reshape (filter, sort, slice, pivot, chunk, transpose…) |

---

## Aggregates

Aggregates implement `AggregateInterface` and are invoked through the spec DSL:

```php
->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)'])
```

The spec string `'sum(amount)'` is parsed by `AggregateOperation` which looks up `'sum'` in the `AggregateRegistry` and resolves `'amount'` against each row. Special patterns like `string_agg(field, "sep")`, `percentile(field, 0.9)`, `bool_and(field)` are handled as DSL extensions before the registry lookup.

---

## Expressions

Two styles:

**String expressions** — evaluated by ExpressionLanguage:
```php
->where("item['status'] == 'paid' and item['amount'] > 100")
```
The row is available as `item`. All top-level keys are also available directly. Built-in functions: `length`, `lower`, `upper`, `trim`, `abs`, `round`, `contains`.

**Closure expressions** — pure PHP, zero overhead:
```php
->where(fn($r) => $r['status'] === 'paid' && $r['amount'] > 100)
```

For the most common patterns (`item['field'] == 'value'`, `item['field'] > 100`), the `ExpressionCompiler` compiles string expressions to native PHP closures and caches them in APCu — achieving closure-level performance on repeated executions.

---

## Adapters

Any input type can be wrapped in a `RelationalCollection` via adapters:

```
Algebra::from($input)
    → CollectionFactory
        → checks adapters in order
            GeneratorAdapter  ← \Generator
            TraversableAdapter ← any \Traversable (not generator)
            ArrayAdapter       ← plain array (fast path, checked first inline)
        → returns RelationalCollection with source as plain array
```

Register custom adapters for Doctrine collections, Eloquent, CSV files, etc. See [Custom adapters](adapters/custom.md).

---

## Infrastructure singletons

`Algebra` maintains process-scoped singletons for all shared infrastructure:

```php
Algebra::factory()    // CollectionFactory
Algebra::evaluator()  // ExpressionEvaluator
Algebra::accessor()   // PropertyAccessor
Algebra::aggregates() // AggregateRegistry
Algebra::planner()    // QueryPlanner
```

These are created lazily and reused. Call `Algebra::reset()` in tests to get a fresh state between runs.
