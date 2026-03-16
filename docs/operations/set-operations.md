# Set operations

Set operations combine two collections based on key membership, following
standard mathematical set algebra.

---

## intersect — A ∩ B

Keep only rows whose key exists in **both** collections.

```php
$wishlist       = [['id' => 1], ['id' => 2], ['id' => 3]];
$inStock        = [['id' => 2], ['id' => 3], ['id' => 4]];

Algebra::from($wishlist)->intersect($inStock, by: 'id')->toArray();
// → [['id' => 2], ['id' => 3]]
```

**Practical use cases:**
- Products on a wishlist that are also in stock
- Users in group A that are also in group B
- Articles bookmarked AND featured
- Notifications unseen AND unarchived

```php
// Items in wishlist that are featured AND in stock
$result = Algebra::from($wishlist)
    ->intersect($featured, by: 'productId')
    ->intersect($inStock, by: 'productId')
    ->toArray();
```

---

## except — A − B

Keep left rows whose key is **absent** from the right collection.

```php
$all       = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]];
$dismissed = [['id' => 2], ['id' => 4]];

Algebra::from($all)->except($dismissed, by: 'id')->toArray();
// → [['id' => 1], ['id' => 3]]
```

**Practical use cases:**
- Notifications not yet dismissed
- Products not already in the cart
- Users not in a ban list
- Orders with no matching payment record

```php
// Unread, non-archived notifications
$result = Algebra::from($notifications)
    ->except($read,     by: 'id')
    ->except($archived, by: 'id')
    ->toArray();
```

---

## union — A ∪ B

Merge two collections and deduplicate by key. **First occurrence wins** on duplicate keys.

```php
$staff       = [['id' => 1, 'src' => 'staff'],       ['id' => 2, 'src' => 'staff']];
$contractors = [['id' => 2, 'src' => 'contractors'], ['id' => 3, 'src' => 'contractors']];

Algebra::from($staff)->union($contractors, by: 'id')->toArray();
// → [['id'=>1,'src'=>'staff'], ['id'=>2,'src'=>'staff'], ['id'=>3,'src'=>'contractors']]
// Note: id=2 from contractors is dropped — staff version wins
```

Pass `by: null` to use PHP's native `SORT_REGULAR` uniqueness (no key-based deduplication):

```php
->union($other, by: null)  // deduplicates by full row value
```

**Practical use cases:**
- All employees (staff + contractors) without duplicates
- Combined product catalog from two sources
- Merge two tag lists

---

## symmetricDiff — A △ B

Keep rows in A **or** B but **not both** — exclusive rows from each side.

```php
$a = [['id' => 1], ['id' => 2], ['id' => 3]];
$b = [['id' => 2], ['id' => 3], ['id' => 4]];

Algebra::from($a)->symmetricDiff($b, by: 'id')->toArray();
// → [['id' => 1], ['id' => 4]]
// 1 is only in A, 4 is only in B
```

**Practical use cases:**
- Changed items between two data snapshots
- Products in one catalog but not the other
- User permission differences between two roles

---

## Comparison with joins

| Operation | What it keeps | Right data attached |
|---|---|---|
| `intersect` | Left rows that exist in right | No |
| `except` | Left rows that don't exist in right | No |
| `semiJoin` | Left rows that have a match | No |
| `antiJoin` | Left rows that have no match | No |
| `innerJoin` | Matched rows | Yes (full right row) |

`intersect` / `except` and `semiJoin` / `antiJoin` are functionally equivalent
for arrays of flat objects. Use `intersect` / `except` for set algebra semantics
and `semiJoin` / `antiJoin` when matching on different key names.

---

## Chaining set operations

```php
// Available products: in catalog, in stock, not discontinued, not already in cart
$available = Algebra::from($catalog)
    ->intersect($inStock,        by: 'productId')
    ->except($discontinued,      by: 'productId')
    ->except($cartItems,         by: 'productId')
    ->toArray();
```

---

## Performance

All four operations build a hash index on the right collection — O(n+m) total.

| Operation | Time | Output size |
|---|---|---|
| `intersect(right, by)` | O(n+m) | ≤ min(n, m) |
| `except(right, by)` | O(n+m) | ≤ n |
| `union(right, by)` | O(n+m) | ≤ n+m |
| `symmetricDiff(right, by)` | O(n+m) | ≤ n+m |
