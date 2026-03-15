# Sorting & slicing

---

## orderBy — sort rows

Sort by one or multiple keys. Stable sort (preserves relative order of equal elements).

```php
// Single key ascending (default)
->orderBy('amount', 'asc')

// Single key descending
->orderBy('amount', 'desc')

// Multi-key: primary by status asc, secondary by amount desc
->orderBy([['status', 'asc'], ['amount', 'desc']])

// Case-insensitive sort via select
->select(fn($r) => [...$r, '_sortKey' => strtolower($r['name'])])
->orderBy('_sortKey', 'asc')
```

### Dot-path sorting

```php
// Sort by nested field (from a previous join)
->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
->orderBy('owner.name', 'asc')
```

### Direction values

`'asc'` (ascending, default) or `'desc'` (descending). Case-insensitive.

---

## limit — slice rows

Return at most N rows, optionally skipping an offset.

```php
->limit(10)              // first 10 rows
->limit(10, offset: 0)   // same as above
->limit(10, offset: 20)  // rows 21–30 (page 3 of 10-per-page)
->limit(10, offset: 30)  // rows 31–40 (page 4)
```

### Pagination pattern

```php
function getPage(array $data, int $page, int $perPage = 20): array
{
    return Algebra::from($data)
        ->orderBy('createdAt', 'desc')
        ->limit($perPage, offset: ($page - 1) * $perPage)
        ->toArray();
}

$page1 = getPage($orders, 1); // rows 1–20
$page2 = getPage($orders, 2); // rows 21–40
```

---

## topN — highest N rows

Shorthand for `orderBy($by, 'desc') + limit($n)`.

```php
->topN(5, by: 'amount')     // 5 highest-amount rows
->topN(10, by: 'views')     // top 10 most viewed
->topN(3, by: 'createdAt')  // 3 most recent
```

---

## bottomN — lowest N rows

Shorthand for `orderBy($by, 'asc') + limit($n)`.

```php
->bottomN(3, by: 'amount')  // 3 cheapest products
->bottomN(5, by: 'stock')   // 5 lowest-stock items
```

---

## rankBy — sort + annotate with rank number

Sort descending (default) and add a sequential rank number to each row.

```php
$result = Algebra::from($products)
    ->rankBy('price', direction: 'desc', as: 'priceRank')
    ->toArray();

// Most expensive product: priceRank = 1
// Second most expensive: priceRank = 2
```

```php
// Custom field name
->rankBy('revenue', direction: 'desc', as: 'revenuePosition')

// Ascending rank (lowest first = rank 1)
->rankBy('price', direction: 'asc', as: 'cheapestFirst')
```

### rankBy vs window('rank')

| Method | Use case |
|---|---|
| `rankBy(field)` | You want to sort AND get rank in one call |
| `->window('rank', field:, as:)` | Collection already sorted; you just want rank annotation |
| `->window('row_number', ...)` | Sequential row number regardless of value ties |

---

## Combining sort and slice

```php
// Top 10 paid orders by amount, newest first on ties
$result = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->orderBy([['amount', 'desc'], ['createdAt', 'desc']])
    ->limit(10)
    ->toArray();

// Page 2 of results sorted by name
$page2 = Algebra::from($customers)
    ->orderBy('name', 'asc')
    ->limit(20, offset: 20)
    ->toArray();
```

---

## Query planner note

The planner eliminates redundant back-to-back sorts:

```
Declared:  orderBy(region:asc) → orderBy(amount:desc)
Optimized: orderBy(amount:desc)   ← first sort dropped
```

The second `orderBy` fully overwrites the first. Declare only the final sort you need.
