# Framework adapters

algebra has zero runtime dependencies by design. Framework-specific adapters
live in separate packages so you only install what you need.

---

## Available packages

| Package | Adapts | Status |
|---|---|---|
| `nalabdou/algebra-symfony` | [*Comming soon*] Doctrine Collections, QueryBuilder | Planned |
| `nalabdou/algebra-laravel` | [*Comming soon*] Eloquent Collections, Builder | Planned |
| `nalabdou/algebra-twig` | [*Comming soon*] All ops as Twig filters | Planned |
| `nalabdou/algebra-csv` | [*Comming soon*] CSV file streaming | Planned |
| `nalabdou/algebra-doctrine` | [*Comming soon*] Doctrine DBAL ResultSet | Planned |

In the meantime, you can write your own adapter in under 10 lines.
See [Custom adapters](custom.md).

---

## Doctrine Collection (manual)

```php
use Nalabdou\Algebra\Contract\AdapterInterface;
use Doctrine\Common\Collections\Collection;

final class DoctrineCollectionAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof Collection;
    }

    public function toArray(mixed $input): array
    {
        return array_values($input->toArray());
    }
}
```

Register in Symfony:

```php
// src/AlgebraServiceProvider.php
use Nalabdou\Algebra\Algebra;

Algebra::factory(); // initialise before registering adapter
// Better: inject a custom CollectionFactory via DI
```

---

## Eloquent Collection (manual)

```php
use Nalabdou\Algebra\Contract\AdapterInterface;
use Illuminate\Support\Collection;

final class EloquentCollectionAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof Collection;
    }

    public function toArray(mixed $input): array
    {
        return $input->toArray();
    }
}
```

Register in a Service Provider:

```php
// app/Providers/AlgebraServiceProvider.php
public function boot(): void
{
    // Replace Algebra's factory singleton with a custom one
    // that includes the Eloquent adapter
}
```

---

## CSV file (manual)

```php
final class CsvFileAdapter implements AdapterInterface
{
    public function __construct(private readonly bool $hasHeader = true) {}

    public function supports(mixed $input): bool
    {
        return is_string($input)
            && str_ends_with($input, '.csv')
            && file_exists($input);
    }

    public function toArray(mixed $input): array
    {
        $rows   = [];
        $handle = fopen($input, 'rb');
        $header = null;

        while (($line = fgetcsv($handle)) !== false) {
            if ($this->hasHeader && $header === null) {
                $header = $line;
                continue;
            }
            $rows[] = $header ? array_combine($header, $line) : $line;
        }

        fclose($handle);
        return $rows;
    }
}

// Usage
Algebra::from('/data/orders.csv')
    ->where("item['status'] == 'paid'")
    ->groupBy('region')
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray();
```

---

## PDO Statement (manual)

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

// Usage
$stmt = $pdo->prepare('SELECT * FROM orders WHERE created_at > ?');
$stmt->execute([$cutoffDate]);

Algebra::from($stmt)
    ->where("item['amount'] > 100")
    ->groupBy('status')
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray();
```

---

## Writing and registering your own

See [Custom adapters](custom.md) for the full guide including registration patterns,
priority ordering, and error handling.
