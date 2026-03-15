# Filter & projection

---

## where — filter rows

Keep only rows matching an expression or closure.

```php
// String expression — compiled to AST, cached
->where("item['status'] == 'paid'")
->where("status == 'paid'")                    // direct variable access also works
->where("amount > 100 and region == 'Nord'")
->where("status in ['paid', 'refunded']")
->where("amount > 500 ? true : false")
->where("contains(lower(email), '@company.com')")
->where("length(name) > 3")

// Closure — native PHP, zero overhead
->where(fn($r) => $r['status'] === 'paid')
->where(fn($r) => $r['amount'] > 100 && in_array($r['status'], ['paid', 'refunded']))
```

### Row exposure in string expressions

The row is available as `item`. All top-level array keys are also bound directly:

```php
$row = ['status' => 'paid', 'amount' => 250];

// Both of these work identically:
->where("item['status'] == 'paid'")
->where("status == 'paid'")
```

### Boolean expression operators

```php
->where("a == 1 and b == 2")          // logical AND
->where("a == 1 or b == 2")           // logical OR
->where("not active")                  // negation
->where("!active")                     // shorthand negation
->where("a == 1 && b == 2")           // symbolic AND
->where("a == 1 || b == 2")           // symbolic OR
```

### The `in` operator

```php
->where("status in ['paid', 'refunded', 'completed']")
->where("tier in ['vip', 'premium']")
```

### Chaining multiple where calls

Each `where` is an independent filter. Chaining is equivalent to `AND`:

```php
// Equivalent to: status == 'paid' AND amount > 100 AND region == 'Nord'
Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->where("item['amount'] > 100")
    ->where("item['region'] == 'Nord'")
```

### Performance

The query planner pushes `where` before `innerJoin` / `leftJoin` automatically.
Filter-first is always faster — use closures when the condition is known at compile time.

See [Expression language](../internals/expression-language.md) for full syntax reference.

---

## select — transform rows

Project each row through an expression or closure.

```php
// Pluck a single field (returns flat values, not rows)
->select('id')
->select('user.name')        // dot-path

// Closure transformation (most common and most flexible)
->select(fn($r) => [
    'id'    => $r['id'],
    'name'  => strtoupper($r['name']),
    'label' => $r['amount'] > 500 ? 'high' : 'low',
])

// Add computed fields while keeping existing ones
->select(fn($r) => [
    ...$r,
    'discounted' => round($r['price'] * 0.8, 2),
    'inStock'    => $r['stock'] > 0,
])

// String expression
->select("item['id'] ~ '-' ~ item['name']")
```

### Common patterns

```php
// Rename fields
->select(fn($r) => ['orderId' => $r['id'], 'customerName' => $r['name']])

// Flatten nested structure
->select(fn($r) => [
    'orderId'     => $r['id'],
    'userName'    => $r['owner']['name'],     // from a previous innerJoin
    'userEmail'   => $r['owner']['email'],
    'amount'      => $r['amount'],
])

// Add scoring
->select(fn($r) => [...$r, 'score' => $r['views'] * 0.3 + $r['sales'] * 0.7])

// Format for output
->select(fn($r) => [
    'id'     => $r['id'],
    'amount' => number_format($r['amount'], 2),
    'date'   => date('d/m/Y', $r['createdAt']),
])
```

### Difference between select and pluck

```php
// pluck → flat array of scalar values
Algebra::from($users)->pluck('id')->toArray();
// → [1, 2, 3, 4, 5]

// select with a field name → same as pluck
Algebra::from($users)->select('id')->toArray();
// → [1, 2, 3, 4, 5]

// select with closure → array of transformed rows
Algebra::from($users)->select(fn($r) => ['id' => $r['id']])->toArray();
// → [['id'=>1], ['id'=>2], ['id'=>3], ...]
```

---

## Combining where and select

The most common pipeline pattern:

```php
$result = Algebra::from($orders)
    ->where("item['status'] == 'paid'")       // filter first (planner enforces this)
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->where(fn($r) => $r['owner']['tier'] === 'vip')  // filter again after join
    ->select(fn($r) => [
        'orderId'    => $r['id'],
        'amount'     => $r['amount'],
        'ownerName'  => $r['owner']['name'],
        'ownerTier'  => $r['owner']['tier'],
    ])
    ->orderBy('amount', 'desc')
    ->toArray();
```
