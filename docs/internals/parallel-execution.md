# Parallel execution

`Algebra::parallel()` runs multiple independent pipelines concurrently using
**PHP 8.1 Fibers** — all within a single process.

---

## Basic usage

```php
$results = Algebra::parallel([
    'paid'    => Algebra::from($orders)->where("item['status'] == 'paid'"),
    'report'  => Algebra::from($sales)->groupBy('region')->aggregate(['revenue' => 'sum(amount)']),
    'top10'   => Algebra::from($orders)->topN(10, by: 'amount'),
    'pivot'   => Algebra::from($orders)->pivot(rows: 'month', cols: 'region', value: 'amount'),
]);

// Results indexed by the same keys
$paidOrders = $results['paid'];
$regionMap  = $results['report'];
$topOrders  = $results['top10'];
$matrix     = $results['pivot'];
```

---

## How it works

Each pipeline is wrapped in a `\Fiber` and started. PHP Fibers use cooperative
multitasking — they run in the same thread and OS process.

```php
// Simplified internals of Algebra::parallel()
$fibers = [];
foreach ($pipelines as $key => $collection) {
    $fiber        = new \Fiber(static fn() => $collection->toArray());
    $fibers[$key] = $fiber;
    $fiber->start();  // each fiber runs until it suspends or finishes
}

$results = [];
foreach ($fibers as $key => $fiber) {
    $results[$key] = $fiber->getReturn();
}
```

---

## When to use parallel()

Fibers provide cooperative (not preemptive) multitasking. They do **not** provide
true parallelism — the CPU still runs one fiber at a time.

**Good use cases:**
- Pipelines that do different aggregations over the same source data
- Building a dashboard with multiple independent widgets
- Amortising computation across several medium-complexity pipelines

**Not helpful for:**
- Pipelines that share computation (materialise the base first instead)
- I/O-bound work (use async PHP frameworks for that)
- Single large pipelines (no benefit from wrapping in a fiber)

---

## Dashboard example

```php
// Compute all dashboard widgets in one call
$dashboard = Algebra::parallel([
    'kpis' => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)', 'avg' => 'avg(amount)']),

    'by_status' => Algebra::from($orders)->tally('status'),

    'trend' => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->groupBy('month')
        ->aggregate(['revenue' => 'sum(amount)'])
        ->orderBy('_group', 'asc'),

    'matrix' => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->pivot(rows: 'month', cols: 'region', value: 'amount'),

    'top_customers' => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'user')
        ->groupBy('userId')
        ->aggregate(['revenue' => 'sum(amount)', 'count' => 'count(*)'])
        ->topN(10, by: 'revenue'),
]);
```

---

## Requirements

PHP 8.1+ for `\Fiber` support. If you're on PHP 8.2, use sequential execution:

```php
// PHP 8.2 fallback
$results = [
    'paid'   => Algebra::from($orders)->where(...)->toArray(),
    'report' => Algebra::from($orders)->groupBy(...)->toArray(),
];
```

---

## Error handling

If any pipeline throws an exception, it propagates from `Algebra::parallel()`:

```php
try {
    $results = Algebra::parallel([
        'a' => Algebra::from($data)->where("item['v'] > 0"),
        'b' => Algebra::from($data)->where('@@@invalid@@@'),  // will throw
    ]);
} catch (\RuntimeException $e) {
    // Handle expression error
}
```

Fibers do not isolate exceptions — the first exception from any fiber terminates
the parallel execution.
