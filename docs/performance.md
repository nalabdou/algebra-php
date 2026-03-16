# Performance guide

---

## Benchmark numbers

Measured on PHP > 8.2, 5 000 rows, 200 right-side users, Apple M-series chip:

| Operation | Avg (5 iterations) |
|---|---|
| `where` (closure) | ~0.5ms |
| `where` (string expr) | ~3ms |
| `orderBy` | ~2ms |
| `innerJoin` (hash-indexed) | ~1.5ms |
| `groupBy + aggregate` | ~2ms |
| `window(running_sum)` | ~1ms |
| `movingAverage(window=7)` | ~2ms |
| `normalize` | ~1ms |
| `pivot` | ~1.5ms |
| Full pipeline (7 ops) | ~6ms |

Run your own benchmark:

```bash
php demo/benchmark.php 5000 5
# or with custom row count and iterations:
php demo/benchmark.php 10000 10
```

---

## Use closures over string expressions

String expressions go through ExpressionLanguage — more flexible, but slower.
Closures run as native PHP.

```php
// Slower (~3ms per 5000 rows)
->where("item['status'] == 'paid'")

// Faster (~0.5ms per 5000 rows)
->where(fn($r) => $r['status'] === 'paid')
```

**Exception:** the `ExpressionCompiler` automatically compiles common string patterns to closures:
- `item['field'] == 'value'`
- `item['field'] > 100`

These run at closure speed after the first compilation.

---

## Enable APCu for expression caching

```bash
# php.ini or docker
extension=apcu
apc.enabled=1
apc.enable_cli=1   # for CLI scripts and tests
```

With APCu: compiled expressions survive between requests — even the first compilation is amortized.

Without APCu: in-process array cache works within a single request but recompiles every time.

---

## Use the query planner

Declare pipelines naturally — the planner handles optimization. But understanding what it does helps you write even faster pipelines.

**Declare filters before joins when possible** (the planner does this automatically, but it's good practice):

```php
// Planner will reorder this, but declaring it optimally is clearest
Algebra::from($orders)
    ->where("item['status'] == 'paid'")    // filter first
    ->innerJoin($users, ...)               // then join smaller set
```

---

## Reuse base pipelines via branching

```php
// BAD: reads and filters $orders twice
$paid    = Algebra::from($orders)->where("item['status'] == 'paid'")->toArray();
$pending = Algebra::from($orders)->where("item['status'] == 'pending'")->toArray();

// GOOD: uses partition — one iteration, both results
$result  = Algebra::from($orders)->partition(fn($r) => $r['status'] === 'paid');
$paid    = $result->pass();
$pending = $result->fail();

// ALSO GOOD for different operations: branch from a shared base
$base    = Algebra::from($orders)->where("item['status'] == 'paid'");
$byRegion = $base->groupBy('region')->aggregate([...]);  // uses cached source
$top10    = $base->topN(10, by: 'amount');               // uses cached source
```

---

## Right-side joins should be small

The hash index is built on the **right** side. If your join is one-to-many, put the many side on the left:

```php
// GOOD: 1000 orders joined against 50 users
Algebra::from($orders)->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')

// BAD if orders is larger: building a 1000-entry index
Algebra::from($users)->innerJoin($orders, leftKey: 'id', rightKey: 'userId', as: 'orders')
```

---

## Memory considerations

All operations materialize rows into PHP arrays in memory. For very large datasets:

1. **Filter early** — reduce row count before expensive operations
2. **Pluck before joining** — if you only need a few fields from the right side, `pluck` them first
3. **Use generators** — wrap file reads or DB cursors in a generator; the adapter materializes only once

```php
// Generator for large files
function readLargeFile(string $path): \Generator
{
    $handle = fopen($path, 'rb');
    $header = fgetcsv($handle);
    while (($row = fgetcsv($handle)) !== false) {
        yield array_combine($header, $row);
    }
    fclose($handle);
}

Algebra::from(readLargeFile('/data/large.csv'))
    ->where(fn($r) => (float)$r['amount'] > 100)  // filter early
    ->groupBy('status')
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray();
```

---

## Parallel execution

Run independent pipelines concurrently via PHP 8.1 Fibers:

```php
$results = Algebra::parallel([
    'paid'   => Algebra::from($orders)->where("item['status'] == 'paid'"),
    'report' => Algebra::from($sales)->pivot(rows: 'month', cols: 'region', value: 'amount'),
    'top10'  => Algebra::from($orders)->topN(10, by: 'amount'),
]);
```

Fibers use cooperative multitasking within one process — no true parallelism, but they amortize computation-heavy pipelines by interleaving their work.

---
