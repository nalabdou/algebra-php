# Built-in adapters

algebra ships three built-in adapters that cover the most common PHP input types.
All are registered automatically in `Algebra::factory()`.

---

## How adapters work

When you call `Algebra::from($input)`, the `CollectionFactory` checks adapters in
registration order. The first adapter whose `supports()` returns `true` is used to
convert the input to a plain PHP array.

**Plain arrays** are handled as a fast path *before* the adapter loop — they never
reach any adapter class.

---

## ArrayAdapter

Handles plain PHP arrays. Reindexes to zero-based keys.

```php
Algebra::from([['id' => 1], ['id' => 2]]);

// Sparse arrays are reindexed:
Algebra::from([5 => ['id' => 5], 10 => ['id' => 10]]);
// → result keys are [0, 1]

// Associative arrays (used by reindex):
Algebra::from(['alice' => ['id' => 1, 'name' => 'Alice']]);
```

---

## GeneratorAdapter

Handles PHP generators. The generator is consumed once and materialised into an array
so the pipeline can be replayed (e.g. for `materialize()` caching).

```php
// Generator function
function streamOrders(): \Generator {
    $stmt = $pdo->query('SELECT * FROM orders');
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        yield $row;
    }
}

Algebra::from(streamOrders())
    ->where("item['status'] == 'paid'")
    ->groupBy('region')
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray();
```

```php
// Generator factory closure
Algebra::from(static function () {
    for ($i = 1; $i <= 1000; $i++) {
        yield ['id' => $i, 'amount' => $i * 10];
    }
});
```

> **Note:** Once consumed, a generator cannot be rewound. `CollectionFactory`
> calls `iterator_to_array()` immediately, so all data is in memory before the
> pipeline begins. For true streaming (no full materialisation), implement a
> custom adapter.

---

## TraversableAdapter

Handles any `\Traversable` that is **not** a `\Generator`
(which is handled by `GeneratorAdapter` first).

Covered types include:
- `\ArrayObject`
- `\SplFixedArray`
- `\SplDoublyLinkedList` / `\SplStack` / `\SplQueue`
- Custom classes implementing `\Iterator` or `\IteratorAggregate`
- Doctrine `ArrayCollection`
- Any library collection class implementing `\Traversable`

```php
// ArrayObject
Algebra::from(new \ArrayObject([['id' => 1], ['id' => 2]]));

// SplFixedArray
$fixed    = new \SplFixedArray(3);
$fixed[0] = ['id' => 1];
$fixed[1] = ['id' => 2];
$fixed[2] = ['id' => 3];
Algebra::from($fixed);

// Custom iterator
class OrderIterator implements \Iterator { /* ... */ }
Algebra::from(new OrderIterator($connection));
```

---

## Registration order

Adapters are checked in this order by default:

```
1. Plain array   — inline fast path (no adapter class)
2. GeneratorAdapter     — \Generator
3. TraversableAdapter   — \Traversable (not Generator)
4. ArrayAdapter         — plain array (fallback, rarely reached)
```

When you register custom adapters, they are checked **before** the built-ins.
See [Custom adapters](custom.md) for how to do this.

---

## Behaviour on unsupported input

When no adapter matches and the input is not `\Traversable`, `CollectionFactory`
throws an `\InvalidArgumentException`:

```php
Algebra::from(new \stdClass());
// InvalidArgumentException: algebra-php cannot convert stdClass into a
// RelationalCollection. Register a custom AdapterInterface implementation.
```
