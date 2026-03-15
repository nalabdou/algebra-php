# Configuration

algebra-php requires zero configuration for basic use. `Algebra::from()` works
out of the box with sensible defaults.

This page covers optional tuning and advanced bootstrap patterns.

---

## Default setup

```php
use Nalabdou\Algebra\Algebra;

// Nothing to configure — just use it
$result = Algebra::from($orders)->where("item['status'] == 'paid'")->toArray();
```

All infrastructure is created lazily on first use and cached as process-level singletons.

---

## Singletons

| Singleton | Access | Purpose |
|---|---|---|
| `CollectionFactory` | `Algebra::factory()` | Converts inputs, creates collections |
| `ExpressionEvaluator` | `Algebra::evaluator()` | Evaluates string + closure expressions |
| `ExpressionCache` | `Algebra::cache()` | APCu/array AST cache |
| `PropertyAccessor` | `Algebra::accessor()` | Dot-path resolver |
| `AggregateRegistry` | `Algebra::aggregates()` | All 18 built-in + custom aggregates |
| `QueryPlanner` | `Algebra::planner()` | Runs 4 optimization passes |

---

## APCu expression cache

Install `ext-apcu` to enable cross-request AST caching:

```ini
; php.ini
extension=apcu
apc.enabled=1
apc.shm_size=32M

; For CLI scripts and tests
apc.enable_cli=1
```

Without APCu, the in-process array cache still works — each expression is only
parsed once per request.

**Cache TTL**: 3 600 seconds (1 hour). Adjust by modifying `ExpressionCache::TTL`.

**Manual flush:**
```php
Algebra::cache()->flush(); // clear all cached ASTs
```

---

## Strict vs lenient expression mode

**Default: strict** — invalid expressions throw `\RuntimeException`.

**Lenient mode** — useful when expressions come from untrusted user input:

```php
use Nalabdou\Algebra\Expression\ExpressionCache;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;

$evaluator = new ExpressionEvaluator(
    propertyAccessor: new PropertyAccessor(),
    cache:            new ExpressionCache(),
    strictMode:       false,   // ← returns false/null instead of throwing
);

// Inject into the factory
$factory = new \Nalabdou\Algebra\Collection\CollectionFactory(
    planner:    new \Nalabdou\Algebra\Planner\QueryPlanner($evaluator),
    evaluator:  $evaluator,
    accessor:   new PropertyAccessor(),
    aggregates: new \Nalabdou\Algebra\Aggregate\AggregateRegistry(),
    adapters:   [
        new \Nalabdou\Algebra\Adapter\GeneratorAdapter(),
        new \Nalabdou\Algebra\Adapter\TraversableAdapter(),
        new \Nalabdou\Algebra\Adapter\ArrayAdapter(),
    ],
);
```

---

## Registering custom aggregates

```php
// Register once at application bootstrap
Algebra::aggregates()->register(new GeomeanAggregate());
Algebra::aggregates()->register(new HarmonicMeanAggregate());

// Now available everywhere
Algebra::from($data)->groupBy('category')->aggregate(['geo' => 'geomean(price)'])->toArray();
```

See [Custom aggregates](aggregates/custom.md).

---

## Registering custom adapters

Custom adapters teach `CollectionFactory` to accept new input types
(Doctrine collections, Eloquent, CSV files, Redis sorted sets, etc.):

```php
use Nalabdou\Algebra\Collection\CollectionFactory;

// Replace the factory singleton with one that includes your adapter
$factory = new CollectionFactory(
    planner:    Algebra::planner(),
    evaluator:  Algebra::evaluator(),
    accessor:   Algebra::accessor(),
    aggregates: Algebra::aggregates(),
    adapters:   [
        new MyDoctrineAdapter(),       // checked first
        new GeneratorAdapter(),
        new TraversableAdapter(),
        new ArrayAdapter(),
    ],
);

// Replace Algebra's singleton (call before any Algebra::from() usage)
// Use a framework bundle to do this automatically:
// - nalabdou/algebra-symfony
// - nalabdou/algebra-laravel
```

See [Custom adapters](adapters/custom.md).

---

## Test setup

Reset all singletons between test runs to avoid state leakage:

```php
// tests/TestCase.php
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        \Nalabdou\Algebra\Algebra::reset();
    }
}
```

`Algebra::reset()` clears: factory, evaluator, cache, accessor, aggregates, planner.

---

## Framework integration

| Framework | Package | Auto-configures |
|---|---|---|
| Symfony | `nalabdou/algebra-symfony` | DI container, Profiler panel, Twig filters |
| Laravel | `nalabdou/algebra-laravel` | Service Provider, Eloquent macros, Artisan |
| Twig (standalone) | `nalabdou/algebra-twig` | All operations as Twig filters |
