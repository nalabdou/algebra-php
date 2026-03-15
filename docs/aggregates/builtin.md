# Built-in aggregates

All 18 built-in aggregate functions, organized by category.

---

## Math aggregates

### count

Count non-null values. Use `count(*)` to count all rows regardless of field.

```php
->aggregate(['total_rows' => 'count(*)', 'non_null_amounts' => 'count(amount)'])
```

### sum

Arithmetic sum.

```php
->aggregate(['revenue' => 'sum(amount)'])
```

### avg

Arithmetic mean. Returns `null` on empty input.

```php
->aggregate(['average' => 'avg(amount)'])
```

### min / max

Minimum and maximum value. Returns `null` on empty input.

```php
->aggregate(['low' => 'min(amount)', 'high' => 'max(amount)'])
```

### median

Middle value after sorting. Averages the two middle values for even-count groups. Returns `null` on empty input.

```php
->aggregate(['middle' => 'median(amount)'])
```

More robust than `avg` for skewed distributions.

### stddev

Sample standard deviation (Bessel's correction, divides by n−1). Returns `null` when fewer than 2 values are present.

```php
->aggregate(['spread' => 'stddev(amount)'])
```

### variance

Sample variance (Bessel's correction, divides by n−1). Returns `null` when fewer than 2 values are present.

```php
->aggregate(['var' => 'variance(amount)'])
```

### percentile

Value at the Nth percentile (nearest-rank method). Requires quantile in spec string.

```php
->aggregate([
    'p25' => 'percentile(amount, 0.25)',   // 25th percentile (Q1)
    'p50' => 'percentile(amount, 0.50)',   // 50th percentile (median)
    'p75' => 'percentile(amount, 0.75)',   // 75th percentile (Q3)
    'p90' => 'percentile(amount, 0.90)',   // 90th percentile
    'p99' => 'percentile(amount, 0.99)',   // 99th percentile
])
```

---

## Statistical aggregates

### mode

Most frequently occurring value. Preserves the original PHP type of the winning value. On ties, returns the first value encountered in the descending sort.

```php
->aggregate(['top_status' => 'mode(status)'])
```

### count_distinct

Number of unique non-null values.

```php
->aggregate(['unique_customers' => 'count_distinct(userId)'])
```

### ntile

Divides values into N equal-sized buckets and returns the boundary thresholds. For per-row bucket assignment use `window('ntile', ...)` instead.

```php
->aggregate(['quartiles' => 'ntile(amount)'])
// → [boundary1, boundary2, boundary3] (N-1 boundaries for N buckets)
```

### cume_dist

Cumulative distribution fractions. Returns an array of `value => fraction` pairs. For per-row fractions use `window('cume_dist', ...)` instead.

```php
->aggregate(['distribution' => 'cume_dist(amount)'])
```

---

## Positional aggregates

### first

First value in the group (preserves input order). Chain after `orderBy` to get the first by a specific ordering.

```php
->aggregate(['earliest' => 'first(createdAt)'])

// Most common pattern: first after sorting
Algebra::from($events)
    ->orderBy('createdAt', 'asc')
    ->groupBy('userId')
    ->aggregate(['firstEvent' => 'first(eventType)'])
```

### last

Last value in the group. Chain after `orderBy` to get the last by a specific ordering.

```php
->aggregate(['latest' => 'last(updatedAt)'])
```

---

## String / Bool aggregates

### string_agg

Concatenate string values using a separator. Null and empty-string values are excluded. Returns `null` when all values are empty.

```php
->aggregate(['products' => 'string_agg(name, ", ")'])
// → 'Laptop, Mouse, Keyboard'
```

The separator is specified in the spec string after a comma, inside quotes: `string_agg(field, "separator")`.

### bool_and

Returns `true` only when ALL values in the group are truthy. Equivalent to SQL's `BOOL_AND` / `EVERY`.

```php
->aggregate(['all_shipped' => 'bool_and(shipped)'])
// true if every item in the group has shipped=true
```

### bool_or

Returns `true` when AT LEAST ONE value in the group is truthy. Equivalent to SQL's `BOOL_OR` / `ANY`.

```php
->aggregate(['any_digital' => 'bool_or(isDigital)'])
// true if at least one item in the group has isDigital=true
```

---

## Empty group handling

All aggregates handle empty input gracefully:

| Aggregate | Empty input returns |
|---|---|
| `count` | `0` |
| `sum` | `0` |
| `avg` | `null` |
| `min` / `max` | `null` |
| `median` | `null` |
| `stddev` / `variance` | `null` (also `null` for single value) |
| `percentile` | `null` |
| `mode` | `null` |
| `count_distinct` | `0` |
| `first` / `last` | `null` |
| `string_agg` | `null` |
| `bool_and` / `bool_or` | `null` |
