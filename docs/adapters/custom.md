# Custom adapters

Implement `AdapterInterface` to teach algebra-php how to read from any data source.

---

## Implementing AdapterInterface

```php
use Nalabdou\Algebra\Contract\AdapterInterface;

final class DoctrineCollectionAdapter implements AdapterInterface
{
    /**
     * Return true when this adapter can handle the given input.
     * Called for each registered adapter in priority order.
     */
    public function supports(mixed $input): bool
    {
        return $input instanceof \Doctrine\Common\Collections\Collection;
    }

    /**
     * Convert the input to a zero-indexed array of rows.
     * Each row should be an associative array or an accessible object.
     */
    public function toArray(mixed $input): array
    {
        // toArray() on PersistentCollection triggers one SQL load if not initialized
        return array_values($input->toArray());
    }
}
```

---

## Registering adapters

Adapters are registered on the `CollectionFactory`. Create a custom factory and pass it to your container:

```php
use Nalabdou\Algebra\Aggregate\AggregateRegistry;
use Nalabdou\Algebra\Collection\CollectionFactory;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;
use Nalabdou\Algebra\Planner\QueryPlanner;

$accessor  = new PropertyAccessor();
$evaluator = new ExpressionEvaluator($accessor);
$planner   = new QueryPlanner();
$registry  = new AggregateRegistry();

$factory = new CollectionFactory(
    planner:    $planner,
    evaluator:  $evaluator,
    accessor:   $accessor,
    aggregates: $registry,
    adapters:   [
        new DoctrineCollectionAdapter(),   // checked first
        new EloquentCollectionAdapter(),
        new GeneratorAdapter(),
        new TraversableAdapter(),
        new ArrayAdapter(),                // checked last
    ],
);

// Then use the factory directly, or replace Algebra's singleton:
// (Use framework bundles to do this automatically)
```

---

## Adapter priority

Adapters are checked in **registration order**. Place more specific adapters first:

```
DoctrineCollectionAdapter  ← checks instanceof Doctrine\Collection first
EloquentCollectionAdapter  ← then instanceof Illuminate\Support\Collection
GeneratorAdapter           ← then instanceof Generator
TraversableAdapter         ← then instanceof Traversable (catches most iterables)
ArrayAdapter               ← last resort for plain arrays
```

Plain PHP arrays are handled **inline** in `CollectionFactory::resolve()` as a fast path before the adapter loop — they never reach `ArrayAdapter`.

---

## Examples

### CSV file adapter

```php
final class CsvFileAdapter implements AdapterInterface
{
    public function __construct(private readonly bool $hasHeader = true) {}

    public function supports(mixed $input): bool
    {
        return \is_string($input) && \str_ends_with($input, '.csv') && \file_exists($input);
    }

    public function toArray(mixed $input): array
    {
        $rows   = [];
        $handle = \fopen($input, 'rb');
        $header = null;

        while (($line = \fgetcsv($handle)) !== false) {
            if ($this->hasHeader && $header === null) {
                $header = $line;
                continue;
            }
            $rows[] = $header !== null ? \array_combine($header, $line) : $line;
        }

        \fclose($handle);
        return $rows;
    }
}

// Usage:
Algebra::from('/path/to/orders.csv')
    ->where("item['status'] == 'paid'")
    ->toArray();
```

### PDO query adapter

```php
final class PdoStatementAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof \PDOStatement;
    }

    public function toArray(mixed $input): array
    {
        return $input->fetchAll(\PDO::FETCH_ASSOC);
    }
}

// Usage:
$stmt = $pdo->query('SELECT id, amount, status FROM orders WHERE created_at > ?');
$stmt->execute([$cutoffDate]);

Algebra::from($stmt)
    ->groupBy('status')
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray();
```

### Redis sorted set adapter

```php
final class RedisSortedSetAdapter implements AdapterInterface
{
    public function __construct(private readonly \Redis $redis) {}

    public function supports(mixed $input): bool
    {
        return \is_array($input) && isset($input['__redis_zset']);
    }

    public function toArray(mixed $input): array
    {
        $key     = $input['__redis_zset'];
        $members = $this->redis->zRangeWithScores($key, 0, -1);

        return \array_map(fn ($member, $score) => [
            'member' => $member,
            'score'  => $score,
        ], \array_keys($members), $members);
    }
}

// Usage:
Algebra::from(['__redis_zset' => 'leaderboard'])
    ->topN(10, by: 'score')
    ->toArray();
```
