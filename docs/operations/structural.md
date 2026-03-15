# Structural utilities

Operations that reshape, slice, sample, and restructure collections without aggregating.

---

## orderBy

Sort rows by one or multiple keys.

```php
// Single key ascending (default)
->orderBy('amount', 'asc')

// Single key descending
->orderBy('amount', 'desc')

// Multi-key: primary by status asc, secondary by amount desc
->orderBy([['status', 'asc'], ['amount', 'desc']])
```

---

## limit

Return at most N rows, optionally skipping an offset.

```php
->limit(10)              // first 10 rows
->limit(10, offset: 20)  // rows 21–30 (page 3 of 10-per-page)
```

---

## topN / bottomN

Shorthand for sort + limit. Equivalent to `orderBy + limit`.

```php
->topN(5, by: 'amount')     // 5 highest-amount rows
->bottomN(3, by: 'amount')  // 3 lowest-amount rows
```

---

## rankBy

Sort + annotate each row with a sequential rank number (1-based).

```php
Algebra::from($products)->rankBy('price', direction: 'desc', as: 'priceRank')->toArray();

// Most expensive product: priceRank = 1
// Second most expensive: priceRank = 2
// ...
```

---

## distinct

Deduplicate rows by a key. **First occurrence wins.**

```php
Algebra::from($rows)->distinct('productId')->toArray();

// If productId appears 3 times, only the first row is kept
```

---

## reindex

Key the output array by a field value for O(1) lookup.

```php
$map = Algebra::from($users)->reindex('id')->toArray();

// Now: $map[42] → the user with id=42
// Instead of: foreach ($users as $u) { if ($u['id'] === 42) ... }

echo $map[42]['name']; // O(1) lookup, no loop
```

On duplicate keys, the **last** occurrence wins.

---

## pluck

Extract a single column into a flat, zero-indexed array.

```php
Algebra::from($orders)->pluck('id')->toArray();
// → [1, 2, 3, 4, 5]

Algebra::from($orders)->pluck('status')->toArray();
// → ['paid', 'pending', 'paid', 'cancelled', 'paid']
```

Equivalent to PHP's `array_column($rows, 'id')` but works on objects and nested dot-paths.

---

## chunk

Split the collection into fixed-size sub-arrays. The last chunk may be smaller.

```php
Algebra::from($products)->chunk(3)->toArray();
// → [
//     [row0, row1, row2],
//     [row3, row4, row5],
//     [row6]              ← smaller last chunk
//   ]
```

Useful for:
- Rendering product grids (3 columns)
- Batch processing (process 100 rows at a time)
- Pagination display

```php
// 3-column product grid in Twig (via algebra-twig)
{% for row in products|chunk(3) %}
  <div class="row">
    {% for product in row %}
      <div class="col">{{ product.name }}</div>
    {% endfor %}
  </div>
{% endfor %}
```

---

## fillGaps

Insert default rows for missing entries in a sparse series. Preserves the order defined by `$series`.

```php
$monthlySales = [
    ['month' => 'Jan', 'revenue' => 4200],
    // Feb is missing
    ['month' => 'Mar', 'revenue' => 5100],
    // Apr is missing
    ['month' => 'May', 'revenue' => 3800],
];

Algebra::from($monthlySales)
    ->fillGaps(
        key:     'month',
        series:  ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        default: ['revenue' => 0],
    )
    ->toArray();

// → [
//   ['month' => 'Jan', 'revenue' => 4200],
//   ['month' => 'Feb', 'revenue' => 0],     ← inserted
//   ['month' => 'Mar', 'revenue' => 5100],
//   ['month' => 'Apr', 'revenue' => 0],     ← inserted
//   ['month' => 'May', 'revenue' => 3800],
//   ['month' => 'Jun', 'revenue' => 0],     ← inserted
// ]
```

Essential for charting — most chart libraries expect a complete series with no gaps.

---

## transpose

Flip rows ↔ columns of a 2-D array.

```php
$matrix = [
    ['month' => 'Jan', 'nord' => 1000, 'sud' => 800],
    ['month' => 'Feb', 'nord' => 1200, 'sud' => 900],
];

Algebra::from($matrix)->transpose()->toArray();
// → [
//   'month' => ['Jan', 'Feb'],
//   'nord'  => [1000, 1200],
//   'sud'   => [800,  900],
// ]
```

Handles sparse rows — keys present in any row appear in the output.

---

## sample

Return N random rows, preserving original relative order.

```php
->sample(10)              // random 10 rows
->sample(10, seed: 42)    // reproducible — same seed = same selection
```

When `count ≥ source size`, all rows are returned unchanged.

> Throws `\InvalidArgumentException` when `count < 0`.

---

## select (map)

Project / transform each row through an expression or closure.

```php
// Pluck single field
->select('id')

// Closure transformation
->select(fn($r) => [
    'id'   => $r['id'],
    'name' => strtoupper($r['name']),
    'vip'  => $r['tier'] === 'vip',
])

// ExpressionLanguage string
->select("item['id'] ~ '-' ~ item['name']")
```

---

## where (filter)

Keep only rows matching an expression or closure.

```php
// String expression
->where("item['status'] == 'paid' and item['amount'] > 100")
->where("contains(item['email'], '@company.com')")
->where("length(item['name']) > 3")

// Closure
->where(fn($r) => $r['status'] === 'paid' && $r['amount'] > 100)
```

See [Filter & projection](filter-projection.md) for full expression reference.
