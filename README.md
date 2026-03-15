# algebra-php

**Pure PHP relational algebra engine.**

JOIN · PIVOT · WINDOW · GROUP BY · 60+ operations · Zero framework dependency.

[![PHP](https://img.shields.io/badge/PHP-8.2+-blue?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen)](phpstan.neon)

---

## Why algebra-php?

You have two arrays from Doctrine, an API response, or a CSV file. You need to join them, group them, compute running totals, and build a pivot table — all in PHP, all in one readable pipeline.

Without algebra you write nested loops, multiple `array_filter` calls, manual aggregation, and bespoke pivot code spread across multiple methods. With algebra-php you write this:

```php
$result = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->groupBy('region')
    ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
    ->orderBy('revenue', 'desc')
    ->toArray();
```

---

## Installation

```bash
composer require nalabdou/algebra-php
```

Requires PHP 8.2+.

---

## Quick start

```php
use Nalabdou\Algebra\Algebra;

// From any input — array, generator, Traversable
$result = Algebra::from($orders)
    ->where("item['status'] == 'paid' and item['amount'] > 100")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->groupBy('region')
    ->aggregate([
        'revenue' => 'sum(amount)',
        'orders'  => 'count(*)',
        'avg'     => 'avg(amount)',
    ])
    ->orderBy('revenue', 'desc')
    ->toArray();
```

---

## All operations

### Entry point

```php
Algebra::from($input)           // array, generator, Traversable → RelationalCollection
Algebra::pipe($input, $fn)      // build + execute in one expression
Algebra::parallel(['a' => $c1, 'b' => $c2])  // concurrent via PHP Fibers
```

---

### Joins

```php
// INNER JOIN — unmatched left rows dropped — O(n+m) hash index
->innerJoin($right, leftKey: 'userId', rightKey: 'id', as: 'owner')

// LEFT JOIN — unmatched left rows kept with null
->leftJoin($right, on: 'userId=id', as: 'owner')

// SEMI JOIN — existence check, no right data attached
->semiJoin($right, leftKey: 'id', rightKey: 'orderId')

// ANTI JOIN — rows with no match on right
->antiJoin($right, leftKey: 'id', rightKey: 'orderId')

// CROSS JOIN — cartesian product (all left × all right)
->crossJoin($right, leftPrefix: 'size_', rightPrefix: 'colour_')

// ZIP — positional merge (index 0 with 0, 1 with 1, …)
->zip($right, leftAs: 'label', rightAs: 'value')
```

---

### Set operations

```php
->intersect($right, by: 'productId')   // A ∩ B — rows in both
->except($right, by: 'id')             // A − B — rows only in left
->union($right, by: 'email')           // A ∪ B — merged, deduplicated
->symmetricDiff($right, by: 'id')      // A △ B — rows in one but not both
```

---

### Filter & projection

```php
// WHERE — string expression (Symfony ExpressionLanguage)
->where("item['status'] == 'paid' and item['amount'] > 100")
->where("contains(item['email'], '@company.com')")
->where("length(item['name']) > 3")

// WHERE — closure (zero overhead, full PHP)
->where(fn($r) => $r['status'] === 'paid' && $r['amount'] > 100)

// SELECT — project each row
->select('id')                               // pluck single field
->select(fn($r) => ['id' => $r['id'],
                     'name' => strtoupper($r['name'])])
```

---

### Grouping & aggregation

```php
->groupBy('status')
->groupBy("item['region'] ~ '-' ~ item['year']")
->groupBy(fn($r) => substr($r['createdAt'], 0, 7))   // YYYY-MM

->aggregate([
    'count'         => 'count(*)',
    'total'         => 'sum(amount)',
    'average'       => 'avg(amount)',
    'minimum'       => 'min(amount)',
    'maximum'       => 'max(amount)',
    'median_val'    => 'median(amount)',
    'std_dev'       => 'stddev(amount)',
    'variance_val'  => 'variance(amount)',
    'p90'           => 'percentile(amount, 0.9)',
    'distinct_users'=> 'count_distinct(userId)',
    'product_list'  => 'string_agg(name, ", ")',
    'all_shipped'   => 'bool_and(shipped)',
    'any_digital'   => 'bool_or(isDigital)',
    'first_date'    => 'first(createdAt)',
    'last_date'     => 'last(createdAt)',
])

->tally('status')     // → ['paid'=>42, 'pending'=>12, 'cancelled'=>3]
```

---

### Window functions

```php
// Running aggregates
->window('running_sum',   field: 'amount', as: 'cumulative')
->window('running_avg',   field: 'amount', as: 'moving')
->window('running_count', field: 'id',     as: 'rowCount')
->window('running_diff',  field: 'amount', as: 'delta')

// Ranking
->window('row_number',  field: 'id',     as: 'rowNum')
->window('rank',        field: 'amount', as: 'rank')       // gaps on ties
->window('dense_rank',  field: 'amount', as: 'denseRank')  // no gaps

// Offset
->window('lag',  field: 'amount', as: 'prevAmount', offset: 1)
->window('lead', field: 'amount', as: 'nextAmount', offset: 1)

// Statistical
->window('ntile',     field: 'amount', as: 'quartile', buckets: 4)
->window('cume_dist', field: 'amount', as: 'pct')

// Partition — resets window per group
->window('running_sum', field: 'amount', as: 'userTotal', partitionBy: 'userId')

// Shorthand window operations
->movingAverage(field: 'revenue', window: 7, as: 'avg_7d')
->normalize(field: 'price', as: 'priceScore')           // min-max 0.0–1.0
```

---

### Pivot

```php
->pivot(rows: 'month', cols: 'region', value: 'revenue')
->pivot(rows: 'month', cols: 'region', value: 'revenue', aggregateFn: 'avg')

// Output:
// [
//   ['_row' => 'Jan', 'Nord' => 4200, 'Sud' => 3100, 'Est' => 1800],
//   ['_row' => 'Feb', 'Nord' => 5100, 'Sud' => 2900, 'Est' => 2200],
// ]
```

---

### Sorting & slicing

```php
->orderBy('amount', 'desc')
->orderBy([['status', 'asc'], ['amount', 'desc']])  // multi-key
->limit(10)
->limit(10, offset: 20)       // page 3 of 10-per-page
->topN(5, by: 'amount')       // shorthand for orderBy+limit
->bottomN(3, by: 'amount')
->rankBy('sales', direction: 'desc', as: 'rank')
```

---

### Structural operations

```php
->distinct('productId')                // DISTINCT ON key
->reindex('id')                        // key by field → O(1) lookup
->pluck('id')                          // → [1, 2, 3, 4, 5]
->chunk(3)                             // → [[r0,r1,r2],[r3,r4,r5],[r6]]
->transpose()                          // flip rows ↔ columns
->sample(10)                           // random 10 rows
->sample(10, seed: 42)                 // reproducible
->fillGaps(
    key:     'month',
    series:  ['Jan','Feb','Mar','Apr'],
    default: ['revenue' => 0],
)
```

---

### Terminal operations

```php
->toArray()                            // execute + plain PHP array
->materialize()                        // execute + MaterializedCollection
->count()                              // row count
->partition("item['amount'] > 500")    // → PartitionResult
    ->pass()     // matching rows
    ->fail()     // non-matching rows
    ->passRate() // 0.0–1.0
```

---

## Expression language

String expressions use Symfony ExpressionLanguage. The row is exposed as `item`:

```php
->where("item['status'] == 'paid'")
->where("item['amount'] > 100 and item['region'] == 'Nord'")
->where("contains(item['email'], '@example.com')")
->where("length(item['name']) > 3")
->where("upper(item['tier']) == 'VIP'")
```

Built-in functions: `length`, `lower`, `upper`, `trim`, `abs`, `round`, `contains`.

**Closures are always supported** and run with zero ExpressionLanguage overhead:

```php
->where(fn($r) => $r['amount'] > 100 && in_array($r['status'], ['paid', 'refunded']))
->select(fn($r) => [...$r, 'label' => strtoupper($r['name'])])
```

---

## Pipeline branching

Pipelines are immutable — `pipe()` always returns a new instance. Branch freely:

```php
$base = Algebra::from($orders)->where("item['status'] == 'paid'");

$byRegion  = $base->groupBy('region')->aggregate(['total' => 'sum(amount)']);
$top10     = $base->topN(10, by: 'amount');
$withOwner = $base->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner');

// $base is unchanged — all three share the same filter step
```

---

## Query planner

The `QueryPlanner` automatically rewrites the declared operation order before execution. Filters are pushed before joins, redundant sorts eliminated, consecutive maps collapsed — without changing the result.

```php
// Declared (suboptimal):
Algebra::from($orders)
    ->innerJoin($users, ...)    // join 1000 rows × 200 users
    ->where("item['status'] == 'paid'")  // then filter

// Optimized execution (planner reorders):
// 1. where   — reduce to ~333 rows  O(1000)
// 2. innerJoin — now O(333×200) instead of O(1000×200)
```

Inspect the plan:

```php
$plan = Algebra::planner()->explain($collection->operations());
// [
//   'original'  => ['inner_join(...)', 'where(...)'],
//   'optimized' => ['where(...)', 'inner_join(...)'],
//   'changed'   => true,
//   'passes'    => ['PushFilterBeforeJoin', ...],
// ]
```

---

## Execution log

Every `MaterializedCollection` carries a per-operation execution log:

```php
$result = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->pivot(rows: 'month', cols: 'region', value: 'amount')
    ->materialize();

foreach ($result->executionLog() as $step) {
    printf("%-50s %6.3fms  %d→%d rows\n",
        $step['signature'],
        $step['duration_ms'],
        $step['input_rows'],
        $step['output_rows'],
    );
}
printf("Total: %.3fms\n", $result->totalDurationMs());
```

---

## Custom aggregates

```php
use Nalabdou\Algebra\Contract\AggregateInterface;

final class GeomeanAggregate implements AggregateInterface
{
    public function name(): string { return 'geomean'; }

    public function compute(array $values): float|null
    {
        if (empty($values)) { return null; }
        $product = array_product(array_map('abs', $values));
        return $product ** (1 / count($values));
    }
}

// Register once at bootstrap
Algebra::aggregates()->register(new GeomeanAggregate());

// Use anywhere
Algebra::from($data)
    ->groupBy('category')
    ->aggregate(['geo' => 'geomean(price)'])
    ->toArray();
```

---

## Custom adapters

```php
use Nalabdou\Algebra\Contract\AdapterInterface;

final class DoctrineCollectionAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof \Doctrine\Common\Collections\Collection;
    }

    public function toArray(mixed $input): array
    {
        return array_values($input->toArray());
    }
}
```

Register in a custom `CollectionFactory` or use the framework bundles:
- `nalabdou/algebra-symfony` — Symfony bundle with Doctrine, Profiler, Commands
- `nalabdou/algebra-laravel` — [*Comming soon*] Laravel Service Provider, Eloquent macros, Artisan
- `nalabdou/algebra-twig` — [*Comming soon*] All operations as Twig filters

---

## Parallel execution

```php
$results = Algebra::parallel([
    'paid'    => Algebra::from($orders)->where("item['status'] == 'paid'"),
    'report'  => Algebra::from($sales)->groupBy('region')->aggregate([...]),
    'top10'   => Algebra::from($orders)->topN(10, by: 'amount'),
]);

$results['paid'];   // executed concurrently via PHP 8.1 Fibers
$results['report'];
$results['top10'];
```

---

## Running the demos

```bash
composer install
php demo/01-basic-filters-and-joins.php
php demo/02-grouping-aggregation-pivot.php
php demo/03-window-functions.php
php demo/04-set-operations.php
php demo/05-structural-utilities.php
php demo/06-custom-aggregates-and-adapters.php
php demo/benchmark.php          # or: make benchmark
```

---

## Running tests

```bash
make install
make test          # all suites
make unit          # unit only
make integration   # integration only
make coverage      # HTML coverage report
make stan          # PHPStan level 5
make cs            # code style check
make ci            # cs + stan + test
```

---

## Architecture

```
src/
├── Algebra.php                        ← static entry point + singleton infrastructure
├── Contract/                          ← 7 interfaces — the public API surface
├── Collection/
│   ├── RelationalCollection.php       ← lazy, immutable, full fluent API
│   ├── MaterializedCollection.php     ← evaluated result + execution log
│   └── CollectionFactory.php          ← converts any input via adapters
├── Operation/
│   ├── Join/                          ← 6 operations (inner, left, semi, anti, cross, zip)
│   ├── Set/                           ← 4 operations (intersect, except, union, diffBy)
│   ├── Aggregate/                      ← 4 operations (aggregate, groupBy, tally, partition)
│   ├── Window/                         ← 3 operations (window dispatcher, movingAvg, normalize)
│   └── Utility/                        ← 13 operations (where/filter, select/map, orderBy/sort, limit/slice, pivot, sample, reindex, fillGaps, uniqueBy, chunk, extract, transpose)
├── Aggregate/
│   ├── AggregateRegistry.php          ← register + retrieve by name
│   ├── Math/                          ← count, sum, avg, min, max, median, stddev, variance, percentile
│   ├── Statistical/                    ← mode, count_distinct, ntile, cume_dist
│   ├── Positional/                     ← first, last
│   └── String/                         ← string_agg, bool_and, bool_or
├── Planner/
│   ├── QueryPlanner.php               ← runs optimization passes
│   └── Pass/                           ← 4 passes (filter pushdown, sort dedup, map collapse, pushFilterBeforeAntiJoin)
├── Expression/
│   ├── ExpressionEvaluator.php        ← Symfony ExpressionLanguage + fast-path
│   ├── Parser.php                      ← expression parser
│   ├── Lexer.php                       ← tokenizes expressions
│   ├── ExpressionCache.php            ← APCu-backed cache
│   ├── PropertyAccessor.php           ← dot-path resolver
│   └── Node/                           ← AST nodes for expressions
│       ├── Node.php
│       ├── ArrayNode.php
│       ├── BinaryNode.php
│       ├── CallNode.php
│       ├── LiteralNode.php
│       ├── NameNode.php
│       ├── PropertyNode.php
│       ├── SubscriptNode.php
│       ├── TernaryNode.php
│       └── UnaryNode.php
├── Adapter/                            ← array, generator, traversable
└── Result/
    └── PartitionResult.php            ← stores partitioned results + execution metadata
```

---

## Versioning

**MAJOR.MINOR.FIX** — Versioning follows this scheme:

- **MAJOR** – Incremented for breaking changes.  
- **MINOR** – Incremented on a regular monthly release. Adds new features in a backward-compatible way.  
- **FIX** – Incremented on demand for bug fixes, documentation updates, or minor improvements.

---

## License

MIT — [Nadim Al Abdou](https://github.com/nalabdou)
