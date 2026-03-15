# Joins

Joins merge rows from two collections by matching a key value.
All joins use a **hash index** on the right-side collection — O(n+m) complexity, not O(n×m).

---

## innerJoin

Merge rows where a key matches. Unmatched left rows are **dropped**.

```php
Algebra::from($orders)
    ->innerJoin(
        right:    $users,
        leftKey:  'userId',   // dot-path on left row
        rightKey: 'id',       // dot-path on right row
        as:       'owner',    // key under which right row is attached
    )
    ->toArray();

// Each result row:
// ['id'=>1, 'userId'=>10, 'amount'=>250, ..., 'owner'=>['id'=>10, 'name'=>'Alice']]
```

**One-to-many:** when multiple right rows share the same key, the left row is duplicated once per match — standard SQL INNER JOIN behaviour.

```php
// Orders can have multiple tags
$ordersWithTags = Algebra::from($orders)
    ->innerJoin($orderTags, leftKey: 'id', rightKey: 'orderId', as: 'tag')
    ->toArray();
// If order #1 has 3 tags → 3 output rows for order #1
```

**Complexity:** O(m) to build the index + O(n) to probe = **O(n+m)** total.

---

## leftJoin

Keep all left rows. Attach matched right row, or `null` when no match.

```php
Algebra::from($orders)
    ->leftJoin(
        right: $users,
        on:    'userId=id',   // shorthand: "leftKey=rightKey"
        as:    'owner',
    )
    ->toArray();

// Unmatched order: ['id'=>4, 'userId'=>99, ..., 'owner'=>null]
```

The `on` parameter accepts `"leftKey=rightKey"` syntax. Whitespace is trimmed: `" userId = id "` is valid.

---

## semiJoin

Keep left rows that have **at least one match** on the right. **No right data is attached.**

```php
// Orders that have at least one payment
Algebra::from($orders)
    ->semiJoin($payments, leftKey: 'id', rightKey: 'orderId')
    ->toArray();

// Result rows are identical to left rows — no extra keys
```

Faster than `innerJoin` when you only need existence — no merging, no output duplication for one-to-many matches.

---

## antiJoin

Keep left rows that have **no match** on the right. The inverse of `semiJoin`.

```php
// Orders with zero payments recorded
Algebra::from($orders)
    ->antiJoin($payments, leftKey: 'id', rightKey: 'orderId')
    ->toArray();
```

---

## crossJoin

Cartesian product — every left row combined with every right row.
Output size = `count(left) × count(right)`.

```php
$sizes   = [['size' => 'S'], ['size' => 'M'], ['size' => 'L']];
$colours = [['colour' => 'Red'], ['colour' => 'Blue']];

Algebra::from($sizes)->crossJoin($colours)->toArray();
// → [{size:'S',colour:'Red'}, {size:'S',colour:'Blue'},
//    {size:'M',colour:'Red'}, {size:'M',colour:'Blue'}, ...]
```

Use `leftPrefix` and `rightPrefix` to prevent key collisions when both sides share field names:

```php
Algebra::from($a)->crossJoin($b, leftPrefix: 'a_', rightPrefix: 'b_');
// → [{a_id:1, b_id:2}, ...]
```

> ⚠️ Use only on small collections. 1 000 × 1 000 = 1 000 000 output rows.

---

## zip

Merge two collections **by position** (index 0 with 0, 1 with 1, …).
Output length = `min(count(left), count(right))`. No key matching.

```php
$labels = [['label' => 'Revenue'], ['label' => 'Orders'], ['label' => 'Customers']];
$values = [['value' => 48_200],    ['value' => 312],      ['value' => 89]];

Algebra::from($labels)->zip($values)->toArray();
// → [{label:'Revenue',value:48200}, {label:'Orders',value:312}, {label:'Customers',value:89}]
```

Use `leftAs`/`rightAs` to namespace keys from scalar values:

```php
Algebra::from(['a', 'b', 'c'])->zip([1, 2, 3], leftAs: 'letter', rightAs: 'number');
// → [{letter:'a',number:1}, {letter:'b',number:2}, {letter:'c',number:3}]
```

---

## Combining joins

Chain joins and filters freely. The query planner pushes filters before joins automatically:

```php
Algebra::from($orders)
    ->where("item['status'] == 'paid'")        // pushed BEFORE joins by planner
    ->innerJoin($users,    leftKey: 'userId',  rightKey: 'id',      as: 'owner')
    ->leftJoin($addresses, on: 'owner.id=addressUserId', as: 'address')
    ->toArray();
```

---

## Complexity comparison

| Operation | Time complexity | Output size |
|---|---|---|
| `innerJoin` | O(n+m) | ≤ n×m |
| `leftJoin` | O(n+m) | = n (×m for one-to-many) |
| `semiJoin` | O(n+m) | ≤ n |
| `antiJoin` | O(n+m) | ≤ n |
| `crossJoin` | O(n×m) | = n×m |
| `zip` | O(min(n,m)) | = min(n,m) |
