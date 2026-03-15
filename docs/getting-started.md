# Getting started

## Installation

```bash
composer require nalabdou/algebra-php
```

**Requirements:** PHP 8.2+.

**Optional:** `ext-apcu` for expression caching (significant performance improvement on repeated pipelines).

---

## Your first pipeline

```php
use Nalabdou\Algebra\Algebra;

$orders = [
    ['id' => 1, 'userId' => 10, 'status' => 'paid',    'amount' => 250, 'region' => 'Nord'],
    ['id' => 2, 'userId' => 20, 'status' => 'pending', 'amount' => 150, 'region' => 'Sud'],
    ['id' => 3, 'userId' => 10, 'status' => 'paid',    'amount' => 400, 'region' => 'Est'],
];

$users = [
    ['id' => 10, 'name' => 'Alice', 'tier' => 'vip'],
    ['id' => 20, 'name' => 'Bob',   'tier' => 'standard'],
];

$result = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->orderBy('amount', 'desc')
    ->toArray();

// Result:
// [
//   ['id'=>3, 'amount'=>400, ..., 'owner'=>['id'=>10, 'name'=>'Alice', 'tier'=>'vip']],
//   ['id'=>1, 'amount'=>250, ..., 'owner'=>['id'=>10, 'name'=>'Alice', 'tier'=>'vip']],
// ]
```

---

## The pipeline model

Every method call **returns a new immutable collection**. Nothing executes until you call `toArray()`, `materialize()`, or iterate with `foreach`.

```php
$base = Algebra::from($orders);                       // nothing executed yet

$filtered  = $base->where("item['status'] == 'paid'"); // still nothing executed
$sorted    = $filtered->orderBy('amount', 'desc');     // still nothing executed

$result    = $sorted->toArray();                       // executes NOW
```

This laziness has two benefits:
1. **Branching** — reuse `$base` or `$filtered` in multiple chains without re-reading the source
2. **Optimization** — the query planner sees the full chain before executing and reorders it

---

## Accepted inputs

```php
// Plain PHP array (most common)
Algebra::from($orders)

// PHP generator (streaming)
Algebra::from(function() {
    yield ['id' => 1, 'amount' => 100];
    yield ['id' => 2, 'amount' => 200];
})

// Any \Traversable (ArrayObject, SplFixedArray, custom iterators)
Algebra::from(new ArrayObject($orders))

// Another RelationalCollection (branch and merge)
Algebra::from($otherCollection)
```

---

## Executing a pipeline

```php
// Execute and return a plain PHP array
$array = $pipeline->toArray();

// Execute and return a MaterializedCollection (includes execution log)
$materialized = $pipeline->materialize();
$array        = $materialized->toArray();
$log          = $materialized->executionLog();

// Iterate directly with foreach
foreach ($pipeline as $row) {
    echo $row['name'];
}

// Count rows (executes)
$count = $pipeline->count();

// Partition into pass/fail in one pass (terminal method)
$result = $pipeline->partition("item['amount'] > 500");
$result->pass();      // matching rows
$result->fail();      // non-matching rows
$result->passRate();  // 0.0 – 1.0
```

---

## Next steps

- [Core concepts](core-concepts.md) — understand how the engine works
- [Joins](operations/joins.md) — the most commonly needed operations
- [Window functions](operations/window-functions.md) — running totals, ranking, lag/lead
- [Custom aggregates](aggregates/custom.md) — extend the aggregate DSL
