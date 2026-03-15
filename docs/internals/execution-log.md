# Execution log

Every `MaterializedCollection` carries a per-operation execution log produced
during the pipeline run. Use it for debugging, profiling, and performance tuning.

---

## Accessing the log

```php
$materialized = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->groupBy('region')
    ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
    ->orderBy('revenue', 'desc')
    ->materialize();

foreach ($materialized->executionLog() as $step) {
    printf(
        "%-50s  %6.3fms  %4d → %4d rows\n",
        $step['signature'],
        $step['duration_ms'],
        $step['input_rows'],
        $step['output_rows'],
    );
}

printf("Total: %.3fms\n", $materialized->totalDurationMs());
```

**Example output:**
```
where(item['status'] == 'paid')                     0.412ms  1000 →  334 rows
inner_join(userId=id, as=owner)                     0.823ms   334 →  334 rows
group_by(region)                                    0.105ms   334 →  334 rows
aggregate(revenue, orders)                          0.218ms   334 →    4 rows
order_by(revenue:desc)                              0.034ms     4 →    4 rows
Total: 1.592ms
```

---

## Log entry fields

Each entry is an associative array with these keys:

| Key | Type | Description |
|---|---|---|
| `operation` | `string` | Fully-qualified class name of the operation |
| `signature` | `string` | Compact human-readable description |
| `input_rows` | `int` | Row count before this operation ran |
| `output_rows` | `int` | Row count after this operation ran |
| `duration_ms` | `float` | Wall-clock time in milliseconds (4 decimal places) |

---

## Total duration

```php
$materialized->totalDurationMs(); // sum of all step durations
```

---

## Reading signatures

Each operation produces a compact signature string for the log:

| Operation | Example signature |
|---|---|
| `where` | `where(item['status'] == 'paid')` |
| `innerJoin` | `inner_join(userId=id, as=owner)` |
| `leftJoin` | `left_join(userId=id, as=owner)` |
| `groupBy` | `group_by(status)` |
| `aggregate` | `aggregate(revenue, count)` |
| `orderBy` | `order_by(amount:desc)` |
| `limit` | `limit(10, offset=0)` |
| `window` | `window(fn=running_sum, field=amount, partition=none, as=cumulative)` |
| `pivot` | `pivot(rows=month, cols=region, value=amount, fn=sum)` |
| `tally` | `tally(field=status)` |
| `distinct` | `distinct(key=productId)` |
| `reindex` | `reindex(key=id)` |
| `pluck` | `pluck(field=id)` |
| `chunk` | `chunk(size=3)` |

---

## Identifying bottlenecks

```php
$log = $materialized->executionLog();

// Find the slowest operation
usort($log, fn($a, $b) => $b['duration_ms'] <=> $a['duration_ms']);
$slowest = $log[0];
printf("Slowest: %s (%.3fms)\n", $slowest['signature'], $slowest['duration_ms']);

// Find the most selective operation
usort($log, fn($a, $b) => ($a['output_rows'] / max(1, $a['input_rows'])) <=> ($b['output_rows'] / max(1, $b['input_rows'])));
$mostSelective = $log[0];
printf("Most selective: %s (%d → %d rows)\n",
    $mostSelective['signature'],
    $mostSelective['input_rows'],
    $mostSelective['output_rows']
);
```

---

## Query planner interaction

The execution log reflects the **optimised** execution order, not the declared order.
Compare with the plan to see what the planner reordered:

```php
$collection = Algebra::from($orders)
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')  // declared first
    ->where("item['status'] == 'paid'");                                   // declared second

$plan = Algebra::planner()->explain($collection->operations());
// ['original'  => ['inner_join(...)', "where(...)"],
//  'optimized' => ["where(...)", 'inner_join(...)'],   ← planner reordered
//  'changed'   => true]

$log = $collection->materialize()->executionLog();
// Log shows where() BEFORE inner_join() — matching the optimised order
```

---

## Using the log in a profiler panel

For Symfony applications, `nalabdou/algebra-symfony` provides a Web Profiler
data collector that renders the execution log as a table in the Profiler.

For other frameworks, you can integrate the log manually:

```php
// Middleware or event listener
$materialized = $pipeline->materialize();

$telemetry->record([
    'pipeline_duration_ms' => $materialized->totalDurationMs(),
    'operations'           => array_map(fn($s) => $s['signature'], $materialized->executionLog()),
    'row_counts'           => array_column($materialized->executionLog(), 'output_rows'),
]);
```
