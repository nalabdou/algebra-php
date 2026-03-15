# Pipeline branching

algebra pipelines are **lazy and immutable**. Every operation method returns
a new collection instance — the original is never modified.

This makes it safe to branch a pipeline and reuse intermediate results.

---

## Immutability in practice

```php
$base = Algebra::from($orders);             // 0 operations — nothing executed

$paid    = $base->where("item['status'] == 'paid'");    // 1 operation on $paid
$pending = $base->where("item['status'] == 'pending'"); // 1 operation on $pending
$all     = $base->orderBy('id', 'asc');                 // 1 operation on $all

// $base is unchanged: count($base->operations()) === 0
// Each branch is an independent collection
```

---

## Laziness

Nothing executes until you call `toArray()`, `materialize()`, or iterate with `foreach`.

```php
$pipeline = Algebra::from($orders)
    ->where("item['status'] == 'paid'")  // queued
    ->orderBy('amount', 'desc')           // queued

// Still nothing executed here

$result = $pipeline->toArray();           // executes NOW
```

---

## Branching from a shared base

```php
$paidOrders = Algebra::from($orders)->where("item['status'] == 'paid'");

// All three branches share the same filtered source without re-filtering
$byRegion  = $paidOrders->groupBy('region')->aggregate(['total' => 'sum(amount)']);
$top10     = $paidOrders->topN(10, by: 'amount');
$withOwner = $paidOrders->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner');

// Execute all three independently
$regionReport = $byRegion->toArray();
$topList      = $top10->toArray();
$detailed     = $withOwner->toArray();
```

> **Performance note:** Each branch re-runs the shared operations (`where` in this
> example). The materialization cache only applies within a single collection instance.
> To avoid re-running expensive shared operations, materialise the base first:

```php
// Materialise once, then branch from the cached result
$paidOrders = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->materialize()
    ->toArray();    // ← now it's a plain array

// Both branches read from the same already-filtered array
$byRegion  = Algebra::from($paidOrders)->groupBy('region')->aggregate([...]);
$top10     = Algebra::from($paidOrders)->topN(10, by: 'amount');
```

---

## Materialization cache

Within a **single** collection instance, `materialize()` caches its result:

```php
$col  = Algebra::from($orders)->where("item['status'] == 'paid'");

$mat1 = $col->materialize(); // executes the where filter
$mat2 = $col->materialize(); // returns the same cached MaterializedCollection
$arr  = $col->toArray();     // also returns the cached result

assert($mat1 === $mat2);     // same instance
```

The cache is **invalidated** when you add an operation via `pipe()`:

```php
$col  = Algebra::from($orders)->where("item['status'] == 'paid'");
$mat1 = $col->materialize(); // cached: 334 rows

$sorted = $col->orderBy('amount', 'desc'); // new instance — cache not shared
$mat2   = $sorted->materialize();           // executes again: 334 rows, sorted
```

---

## Partition as a branch replacement

When you need to split a collection into two groups based on a condition,
`partition()` is more efficient than two `where()` calls because it iterates once:

```php
// ✗ Two where calls = two iterations
$highValue = Algebra::from($orders)->where("item['amount'] > 500")->toArray();
$standard  = Algebra::from($orders)->where("item['amount'] <= 500")->toArray();

// ✓ Partition = one iteration, two results
$result    = Algebra::from($orders)->partition("item['amount'] > 500");
$highValue = $result->pass();
$standard  = $result->fail();
```

---

## Parallel branching

Use `Algebra::parallel()` to run independent branches concurrently via PHP Fibers:

```php
$results = Algebra::parallel([
    'summary'   => Algebra::from($orders)->groupBy('status')->aggregate([...]),
    'top10'     => Algebra::from($orders)->topN(10, by: 'amount'),
    'pivot'     => Algebra::from($orders)->pivot(rows: 'month', cols: 'region', value: 'amount'),
]);

$results['summary'];
$results['top10'];
$results['pivot'];
```

See [Parallel execution](parallel-execution.md) for details.
