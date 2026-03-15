# Aggregate spec DSL

The aggregate spec DSL is the string-based mini-language used inside `->aggregate([])`.

---

## Syntax

```
alias => 'functionName(fieldPath)'
alias => 'functionName(fieldPath, param)'
alias => 'count(*)'          // special form for row count
```

Each spec maps an **output key alias** to an **aggregate expression**.

---

## Standard form: `fn(field)`

```php
->aggregate([
    'total'   => 'sum(amount)',
    'average' => 'avg(amount)',
    'minimum' => 'min(amount)',
    'maximum' => 'max(amount)',
    'count'   => 'count(*)',        // * means count all rows
    'uniq'    => 'count(userId)',   // count non-null userId values
])
```

The field path is resolved by `PropertyAccessor` — dot-paths work:

```php
->aggregate(['ownerName' => 'first(owner.name)'])
```

---

## Parametric forms

### `percentile(field, quantile)`

```php
->aggregate([
    'p25' => 'percentile(amount, 0.25)',
    'p50' => 'percentile(amount, 0.50)',   // same as median
    'p75' => 'percentile(amount, 0.75)',
    'p90' => 'percentile(amount, 0.90)',
    'p99' => 'percentile(amount, 0.99)',
])
```

Quantile must be between 0.0 and 1.0. Uses nearest-rank method.

### `string_agg(field, "separator")`

```php
->aggregate([
    'tags'    => 'string_agg(tag, ", ")',
    'emails'  => 'string_agg(email, "; ")',
    'ids'     => 'string_agg(id, "|")',
])
```

The separator is specified inside double quotes after a comma. Null and empty strings are excluded.

### `bool_and(field)` and `bool_or(field)`

```php
->aggregate([
    'all_shipped'  => 'bool_and(shipped)',    // true iff every value is truthy
    'any_digital'  => 'bool_or(isDigital)',   // true iff at least one value is truthy
    'every_active' => 'bool_and(active)',
])
```

---

## All 18 built-in functions in spec syntax

| Spec | PHP equivalent | Notes |
|---|---|---|
| `count(*)` | `count($rows)` | All rows |
| `count(field)` | `count(non-null values)` | Non-null field values only |
| `sum(field)` | `array_sum($values)` | 0 on empty |
| `avg(field)` | `array_sum / count` | null on empty |
| `min(field)` | `min($values)` | null on empty |
| `max(field)` | `max($values)` | null on empty |
| `median(field)` | sort + middle value | null on empty |
| `stddev(field)` | sample std dev (n−1) | null if count < 2 |
| `variance(field)` | sample variance (n−1) | null if count < 2 |
| `percentile(field, q)` | nearest-rank | null on empty |
| `mode(field)` | most frequent value | null on empty |
| `count_distinct(field)` | count(array_unique) | 0 on empty |
| `ntile(field)` | bucket boundaries | null on empty |
| `cume_dist(field)` | cumulative fractions | null on empty |
| `first(field)` | first value in group | null on empty |
| `last(field)` | last value in group | null on empty |
| `string_agg(field, "sep")` | implode(sep, values) | null if all empty |
| `bool_and(field)` | all truthy | null on empty |
| `bool_or(field)` | any truthy | null on empty |

---

## Output row structure

After `groupBy` + `aggregate`, each output row has:
- `_group` — the group key value
- one key per alias from your spec map

```php
Algebra::from($orders)
    ->groupBy('status')
    ->aggregate(['count' => 'count(*)', 'total' => 'sum(amount)'])
    ->toArray();

// Output:
// [
//   ['_group' => 'paid',      'count' => 42, 'total' => 18500],
//   ['_group' => 'pending',   'count' => 12, 'total' =>  4200],
//   ['_group' => 'cancelled', 'count' =>  3, 'total' =>   800],
// ]
```

When called without `groupBy`, the entire collection is one group with `_group => '*'`:

```php
Algebra::from($orders)->aggregate(['total' => 'sum(amount)'])->toArray();
// [['_group' => '*', 'total' => 23500]]
```

---

## Error handling

An invalid spec string throws `\InvalidArgumentException` with a helpful message:

```php
->aggregate(['bad' => 'not_a_valid_spec'])
// InvalidArgumentException: Invalid aggregate spec: 'not_a_valid_spec'.
// Expected format: fn(field) — e.g. 'sum(amount)', 'count(*)'
```

An unknown function name throws the same exception:

```php
->aggregate(['x' => 'unknown_fn(amount)'])
// InvalidArgumentException: Unknown aggregate function 'unknown_fn'. Available: ...
```

---

## Custom aggregates in specs

Any function registered in `AggregateRegistry` is available in the DSL:

```php
Algebra::aggregates()->register(new GeomeanAggregate()); // name() = 'geomean'

Algebra::from($data)
    ->groupBy('category')
    ->aggregate(['geo' => 'geomean(price)'])
    ->toArray();
```

See [Custom aggregates](custom.md).
