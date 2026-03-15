# Custom aggregates

Implement `AggregateInterface` to add any aggregate function to the DSL.

---

## Implementing AggregateInterface

```php
use Nalabdou\Algebra\Contract\AggregateInterface;

final class GeomeanAggregate implements AggregateInterface
{
    /**
     * The name used in the aggregate spec DSL.
     * Must be unique within the AggregateRegistry.
     */
    public function name(): string
    {
        return 'geomean';
    }

    /**
     * Compute the aggregate over a flat list of non-null scalar values.
     *
     * Values are pre-filtered by AggregateOperation — only non-null
     * values are passed here. Handle empty $values gracefully.
     */
    public function compute(array $values): float|null
    {
        if (empty($values)) {
            return null;
        }

        $product = array_product(array_map('abs', $values));

        return $product ** (1 / count($values));
    }
}
```

---

## Registering a custom aggregate

Register once at application bootstrap (before any pipeline runs):

```php
use Nalabdou\Algebra\Algebra;

// In bootstrap / service container / service provider
Algebra::aggregates()->register(new GeomeanAggregate());
```

Then use anywhere:

```php
Algebra::from($products)
    ->groupBy('category')
    ->aggregate([
        'avg_price'     => 'avg(price)',
        'geomean_price' => 'geomean(price)',  // ← your custom aggregate
    ])
    ->toArray();
```

---

## Overriding built-in aggregates

Registering with an existing name **replaces** the built-in:

```php
// Replace the built-in median with a weighted median implementation
Algebra::aggregates()->register(new WeightedMedianAggregate());
```

---

## Examples

### Harmonic mean

```php
final class HarmonicMeanAggregate implements AggregateInterface
{
    public function name(): string { return 'harmonic_mean'; }

    public function compute(array $values): float|null
    {
        if (empty($values)) { return null; }

        $sumOfReciprocals = array_sum(array_map(fn($v) => 1 / $v, array_filter($values)));

        return $sumOfReciprocals > 0 ? count($values) / $sumOfReciprocals : null;
    }
}
```

### Range (max − min)

```php
final class RangeAggregate implements AggregateInterface
{
    public function name(): string { return 'range'; }

    public function compute(array $values): float|null
    {
        if (empty($values)) { return null; }
        return max($values) - min($values);
    }
}
```

### Interquartile range (IQR)

```php
final class IqrAggregate implements AggregateInterface
{
    public function name(): string { return 'iqr'; }

    public function compute(array $values): float|null
    {
        if (count($values) < 4) { return null; }

        sort($values);
        $n   = count($values);
        $q1  = $values[(int) floor($n * 0.25)];
        $q3  = $values[(int) floor($n * 0.75)];

        return $q3 - $q1;
    }
}
```

### Concatenate JSON arrays

```php
final class JsonArrayAggAggregate implements AggregateInterface
{
    public function name(): string { return 'json_array_agg'; }

    public function compute(array $values): string|null
    {
        if (empty($values)) { return null; }
        return json_encode(array_values($values), JSON_UNESCAPED_UNICODE);
    }
}
```

Usage: `'tags_json' => 'json_array_agg(tag)'` → `'["php","symfony","twig"]'`

---

## Registering in frameworks

### Symfony

```php
// config/services.yaml
services:
    App\Aggregate\GeomeanAggregate:
        tags:
            - { name: algebra.aggregate }
```

The algebra-symfony bundle auto-registers all services tagged `algebra.aggregate`.

### Laravel

```php
// app/Providers/AlgebraServiceProvider.php
public function boot(): void
{
    Algebra::aggregates()->register(new GeomeanAggregate());
}
```
