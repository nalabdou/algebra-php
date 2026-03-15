# Grouping & aggregation

---

## groupBy

Group rows by the resolved value of a field, expression, or closure.

Returns an associative array: `['group_key' => [rows...]]`.

```php
// Simple field
$grouped = Algebra::from($orders)->groupBy('status')->toArray();
// ['paid'=>[...rows...], 'pending'=>[...rows...], 'cancelled'=>[...rows...]]

// String expression
Algebra::from($orders)->groupBy("item['region'] ~ '-' ~ item['year']")

// Closure
Algebra::from($orders)->groupBy(fn($r) => substr($r['createdAt'], 0, 7)) // YYYY-MM
```

Chain with `aggregate()` to collapse groups into summary rows.

---

## aggregate

Compute aggregate functions over groups or a flat collection.

```php
Algebra::from($orders)
    ->groupBy('status')
    ->aggregate([
        'count'          => 'count(*)',
        'total'          => 'sum(amount)',
        'average'        => 'avg(amount)',
        'minimum'        => 'min(amount)',
        'maximum'        => 'max(amount)',
        'median_value'   => 'median(amount)',
        'std_deviation'  => 'stddev(amount)',
        'variance_value' => 'variance(amount)',
        'p90'            => 'percentile(amount, 0.9)',
        'unique_users'   => 'count_distinct(userId)',
        'product_list'   => 'string_agg(name, ", ")',
        'all_shipped'    => 'bool_and(shipped)',
        'any_digital'    => 'bool_or(isDigital)',
        'first_order'    => 'first(createdAt)',
        'last_order'     => 'last(createdAt)',
    ])
    ->toArray();

// Each output row:
// ['_group' => 'paid', 'count' => 42, 'total' => 18500.00, ...]
```

### Aggregating a flat collection

When called without `groupBy`, the entire collection is treated as one group with `_group => '*'`:

```php
$stats = Algebra::from($orders)
    ->aggregate(['count' => 'count(*)', 'total' => 'sum(amount)'])
    ->toArray();
// [['_group' => '*', 'count' => 10, 'total' => 2350.00]]
```

### Spec DSL reference

| Spec | Description |
|---|---|
| `count(*)` | Row count (ignores field, counts all) |
| `sum(field)` | Arithmetic sum |
| `avg(field)` | Arithmetic mean |
| `min(field)` | Minimum value |
| `max(field)` | Maximum value |
| `median(field)` | Middle value (sorted) |
| `stddev(field)` | Sample standard deviation (n−1) |
| `variance(field)` | Sample variance (n−1) |
| `percentile(field, 0.9)` | Nth percentile (nearest-rank) |
| `count_distinct(field)` | Count of unique values |
| `mode(field)` | Most frequent value |
| `string_agg(field, ", ")` | Concatenate with separator |
| `bool_and(field)` | True when all values are truthy |
| `bool_or(field)` | True when any value is truthy |
| `first(field)` | First value in group |
| `last(field)` | Last value in group |

See [Aggregate spec DSL](../aggregates/spec-dsl.md) for full syntax reference.

---

## tally

Count occurrences of each distinct value, sorted by count descending.

```php
Algebra::from($orders)->tally('status')->toArray();
// ['paid' => 42, 'pending' => 12, 'cancelled' => 3, 'refunded' => 1]
```

Unlike `groupBy + aggregate(['count' => 'count(*)'])`, `tally` returns a flat associative array directly — no `_group` key, no extra nesting.

---

## partition

Split rows into **pass** and **fail** groups in a **single iteration**.
Returns a `PartitionResult`, not a `RelationalCollection`.

```php
$result = Algebra::from($orders)->partition("item['amount'] > 500");

$highValue = $result->pass();        // orders > €500
$standard  = $result->fail();        // orders ≤ €500
$count     = $result->passCount();   // e.g. 18
$rate      = $result->passRate();    // e.g. 0.27 (27%)
$total     = $result->totalCount();  // passCount + failCount
$both      = $result->toArray();     // ['pass' => [...], 'fail' => [...]]
```

**Why `partition` instead of two `where` calls:**
- `partition` iterates the collection **once** and splits in one pass — O(n)
- Two `where` calls iterate **twice** — O(2n)
- Partition also guarantees every row ends up in exactly one group

---

## Chaining aggregate steps

You can chain multiple transformations after aggregation:

```php
Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->groupBy('region')
    ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
    ->orderBy('revenue', 'desc')           // sort the summary rows
    ->limit(5)                             // top 5 regions
    ->toArray();
```

---

## Multi-level grouping

Simulate multi-level grouping by chaining group operations:

```php
// First group by region, then aggregate by month within each region
$byRegion = Algebra::from($orders)->groupBy('region')->toArray();

$result = [];
foreach ($byRegion as $region => $regionRows) {
    $result[$region] = Algebra::from($regionRows)
        ->groupBy('month')
        ->aggregate(['revenue' => 'sum(amount)'])
        ->orderBy('_group', 'asc')
        ->toArray();
}
```
