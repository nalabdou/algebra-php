# Query planner

The `QueryPlanner` rewrites the declared operation chain into a more efficient execution order before any row is processed. The result is always semantically identical — only efficiency changes.

---

## How it works

```
User declares pipeline:
  innerJoin(users) → where(status=='paid') → orderBy(amount)

Planner runs optimization passes:
  Pass 1 (PushFilterBeforeJoin): where → innerJoin → orderBy
  Pass 2 (EliminateRedundantSort): no change
  Pass 3 (CollapseConsecutiveMaps): no change

Optimized execution order:
  where(status=='paid') → innerJoin(users) → orderBy(amount)
```

The planner is transparent — you declare pipelines in whatever order reads naturally, and the planner makes it efficient.

---

## Optimization passes

### PushFilterBeforeJoin

Moves `where` operations before `innerJoin` and `leftJoin`.

**Why:** Filtering reduces the number of rows that participate in the join, cutting the join's work dramatically.

```
Before: innerJoin(1000×200) → where(0.5 selectivity) = 200000 + 100000 ops
After:  where(1000×0.5) → innerJoin(500×200)         = 500 + 100000 ops
```

### PushFilterBeforeAntiJoin

Same benefit as above, applied to `semiJoin` and `antiJoin`.

### EliminateRedundantSort

Removes a `orderBy` that is immediately followed by another `orderBy`. The first sort is fully overwritten by the second.

```
Before: orderBy(region:asc) → orderBy(amount:desc)
After:  orderBy(amount:desc)
```

### CollapseConsecutiveMaps

Merges two adjacent closure-based `select` operations into one composed closure. Reduces from two iterations to one.

```
Before: select(fn1) → select(fn2)   ← two passes over the data
After:  select(fn2∘fn1)             ← one pass
```

Only applies to closure-based maps. String expression maps are left untouched.

---

## Inspecting the plan

```php
use Nalabdou\Algebra\Algebra;

$collection = Algebra::from($orders)
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->where("item['status'] == 'paid'")
    ->orderBy('amount', 'desc');

$plan = Algebra::planner()->explain($collection->operations());

var_dump($plan);
// [
//   'original'  => [
//     'inner_join(userId=id, as=owner)',
//     "where(item['status'] == 'paid')",
//     'order_by(amount:desc)',
//   ],
//   'optimized' => [
//     "where(item['status'] == 'paid')",
//     'inner_join(userId=id, as=owner)',
//     'order_by(amount:desc)',
//   ],
//   'changed'   => true,
//   'passes'    => [
//     'Nalabdou\Algebra\Planner\Pass\PushFilterBeforeJoin',
//     'Nalabdou\Algebra\Planner\Pass\PushFilterBeforeAntiJoin',
//     'Nalabdou\Algebra\Planner\Pass\EliminateRedundantSort',
//     'Nalabdou\Algebra\Planner\Pass\CollapseConsecutiveMaps',
//   ],
// ]
```

---

## Writing custom passes

Implement `PassInterface` and inject into a custom `QueryPlanner`:

```php
use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Contract\PassInterface;
use Nalabdou\Algebra\Operation\Utility\SliceOperation;
use Nalabdou\Algebra\Operation\Utility\SortOperation;

/**
 * Push SliceOperation before SortOperation when possible.
 * (Only safe when slice is on an already-sorted source.)
 */
final class PushSliceBeforeSort implements PassInterface
{
    public function apply(array $operations): array
    {
        // Your reordering logic here
        return $operations;
    }
}
```

```php
// Build a custom planner with your pass added
$planner = new class extends QueryPlanner {
    public function optimize(array $operations): array
    {
        $operations = (new PushSliceBeforeSort())->apply($operations);
        return parent::optimize($operations);
    }
};
```

---

## Disabling the planner

The planner runs transparently and cannot be disabled directly. If you suspect it is changing semantics incorrectly, compare `$collection->operations()` with `Algebra::planner()->optimize($collection->operations())` and file a bug report.
