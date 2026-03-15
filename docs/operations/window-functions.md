# Window functions

Window functions **enrich each row** with a computed value without collapsing the collection. Row count is always preserved.

---

## The `window()` method

```php
->window(
    fn:          'running_sum',   // function name (see table below)
    field:       'amount',        // field to compute over
    partitionBy: null,            // optional: reset window per group
    as:          'cumulative',    // output key added to each row
    offset:      1,               // for lag/lead: how many rows to look back/forward
    buckets:     4,               // for ntile: number of equal-sized groups
)
```

---

## All window functions

### Running functions

| Function | Description | Notes |
|---|---|---|
| `running_sum` | Cumulative sum of `field` | — |
| `running_avg` | Cumulative average of `field` | — |
| `running_count` | Cumulative row count | `field` is ignored |
| `running_diff` | Delta vs previous row | First row receives `null` |

```php
$result = Algebra::from($dailyRevenue)
    ->orderBy('date', 'asc')
    ->window('running_sum',  field: 'amount', as: 'cumulative')
    ->window('running_diff', field: 'amount', as: 'delta')
    ->toArray();

// Row 0: amount=1000, cumulative=1000.0, delta=null
// Row 1: amount=1200, cumulative=2200.0, delta=200.0
// Row 2: amount=800,  cumulative=3000.0, delta=-400.0
```

---

### Ranking functions

| Function | Description | On ties |
|---|---|---|
| `row_number` | Sequential 1-based row number | No ties (always unique) |
| `rank` | Rank descending by `field` | Gaps on ties (1,1,3) |
| `dense_rank` | Rank descending by `field` | No gaps (1,1,2) |

```php
$result = Algebra::from($scores)
    ->window('rank',       field: 'score', as: 'rank')
    ->window('dense_rank', field: 'score', as: 'denseRank')
    ->window('row_number', field: 'id',    as: 'rowNum')
    ->toArray();
```

---

### Offset functions

| Function | Description |
|---|---|
| `lag` | Value of `field` N rows before the current row |
| `lead` | Value of `field` N rows after the current row |

```php
// Compare each day's revenue to the previous day
Algebra::from($dailyRevenue)
    ->window('lag',  field: 'amount', as: 'yesterday', offset: 1)
    ->window('lead', field: 'amount', as: 'tomorrow',  offset: 1)
    ->toArray();

// First row: yesterday=null (no prior row)
// Last row:  tomorrow=null (no next row)
```

Custom offset:
```php
->window('lag', field: 'amount', as: 'lastWeek', offset: 7)
```

---

### Statistical functions

| Function | Description |
|---|---|
| `ntile` | Assigns a bucket number 1–N. Set N with `buckets:`. |
| `cume_dist` | Cumulative distribution: what fraction of rows have a value ≤ this row's value |

```php
// Divide orders into quartiles by amount
Algebra::from($orders)
    ->orderBy('amount', 'asc')
    ->window('ntile', field: 'amount', as: 'quartile', buckets: 4)
    ->toArray();

// Rows 0–24%: quartile=1, 25–49%: quartile=2, 50–74%: quartile=3, 75–100%: quartile=4

// Cumulative distribution
Algebra::from($orders)
    ->window('cume_dist', field: 'amount', as: 'pct')
    ->toArray();

// Each row gets pct = fraction of all rows with amount ≤ this row's amount
```

---

## Partitioned windows

Pass `partitionBy` to **reset the window per distinct group**. The function runs independently within each partition.

```php
// Running sum per user — resets for each userId
Algebra::from($orders)
    ->window('running_sum', field: 'amount', as: 'userTotal', partitionBy: 'userId')
    ->toArray();

// userId=10: 100, 250, 600 (running total for user 10 only)
// userId=20: 200, 350      (running total for user 20, independent)
```

---

## movingAverage

Sliding window average over N consecutive rows. Rows without enough prior context receive `null`.

```php
Algebra::from($dailyRevenue)
    ->orderBy('date', 'asc')
    ->movingAverage(field: 'revenue', window: 7, as: 'avg_7d')
    ->toArray();

// Rows 0–5: avg_7d = null (not enough history)
// Row 6:   avg_7d = average of rows 0–6
// Row 7:   avg_7d = average of rows 1–7
// ...
```

> Throws `\InvalidArgumentException` when `window < 1`.

---

## normalize

Min-max normalization: scales all values of a field to the [0.0, 1.0] range.

Formula: `(value − min) / (max − min)`

When all values are identical (range = 0), every row receives 0.0.

```php
Algebra::from($products)
    ->normalize(field: 'price', as: 'priceScore')
    ->toArray();

// Cheapest product: priceScore = 0.0
// Most expensive:  priceScore = 1.0
// All others:      priceScore between 0.0 and 1.0
```

Useful for scoring, ranking, and ML feature engineering.

---

## Combining multiple window functions

Chain `window()` calls freely — each adds a new key to every row:

```php
Algebra::from($salesData)
    ->orderBy('date', 'asc')
    ->window('running_sum',  field: 'revenue', as: 'cumRevenue')
    ->window('running_diff', field: 'revenue', as: 'dayOverDay')
    ->window('rank',         field: 'revenue', as: 'revenueRank')
    ->window('lag',          field: 'revenue', as: 'prevRevenue', offset: 1)
    ->movingAverage(field: 'revenue', window: 7, as: 'ma7d')
    ->normalize(field: 'revenue', as: 'revenueScore')
    ->toArray();
```

---

## Order matters

Window functions operate on rows in their current order. Always apply `orderBy` **before** window functions when order is meaningful:

```php
// Correct — sort first, then running sum
Algebra::from($orders)
    ->orderBy('createdAt', 'asc')
    ->window('running_sum', field: 'amount', as: 'cumulative')

// Wrong — running sum over unsorted data is meaningless
Algebra::from($orders)
    ->window('running_sum', field: 'amount', as: 'cumulative')
    ->orderBy('createdAt', 'asc')   // too late
```
