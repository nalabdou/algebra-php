# Contributing

Contributions are welcome! This document covers how to get started, the
development workflow, and contribution guidelines.

---

## Getting started

```bash
git clone https://github.com/nalabdou/algebra-php
cd algebra-php
composer install
```

**Requirements:** PHP 8.2+, Composer.

---

## Running tests

```bash
make test             # all suites
make unit             # unit tests only
make integration      # integration tests only
make coverage         # HTML coverage report (requires Xdebug)
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --filter LexerTest
```

---

## Code quality

```bash
make stan    # PHPStan level 9
make cs      # PHP-CS-Fixer dry run
make cs-fix  # auto-fix code style
make ci      # cs + stan + test (full CI)
```

---

## Project structure

```
src/
├── Algebra.php                 ← public entry point + singletons
├── Contract/                   ← 7 interfaces — the stable API surface
├── Collection/                 ← RelationalCollection, MaterializedCollection
├── Expression/                 ← native Lexer/Parser/Evaluator (zero deps)
├── Operation/                  ← 29 operation classes
│   ├── Join/                   ← 6 join types
│   ├── Set/                    ← 4 set operations
│   ├── Aggregate/              ← 4 (groupBy, aggregate, tally, partition)
│   ├── Window/                 ← 3 (window dispatcher, movingAvg, normalize)
│   └── Utility/                ← 12 structural operations
├── Aggregate/                  ← 18 aggregate functions in 4 namespaces
├── Planner/                    ← QueryPlanner + 4 optimization passes
├── Adapter/                    ← 3 adapters (array, generator, traversable)
└── Result/                     ← PartitionResult
```

---

## Adding a new operation

1. Create the class in the appropriate `src/Operation/X/` directory
2. Implement `OperationInterface` (`execute`, `signature`, `selectivity`)
3. Add a fluent method to `RelationalCollection`
4. Update `Algebra.php` docblock
5. Write tests in `tests/Unit/Operation/X/`
6. Add to `docs/operations/` and `docs/api-reference.md`

Example minimal operation:

```php
<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Utility;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * SHUFFLE — randomise the order of rows.
 */
final class ShuffleOperation implements OperationInterface
{
    public function __construct(private readonly ?int $seed = null) {}

    public function execute(array $rows): array
    {
        if ($this->seed !== null) {
            mt_srand($this->seed);
        }

        shuffle($rows);

        return $rows;
    }

    public function signature(): string
    {
        return sprintf('shuffle(seed=%s)', $this->seed ?? 'random');
    }

    public function selectivity(): float { return 1.0; }
}
```

---

## Adding a new aggregate function

1. Create the class in the appropriate `src/Aggregate/X/` directory
2. Implement `AggregateInterface` (`name`, `compute`)
3. Register in `AggregateRegistry::registerBuiltins()`
4. Add to `docs/aggregates/builtin.md` and `docs/aggregates/spec-dsl.md`
5. Write tests in `tests/Unit/Aggregate/`

---

## Coding standards

- PHP 8.2+ features encouraged: `readonly` properties, named arguments, first-class callables
- `declare(strict_types=1)` in every file
- `final` classes everywhere (no accidental inheritance)
- Full PHPDoc on every public method and class
- PHPStan level 5 — no suppression comments without justification

---

## Commit message format

```
type(scope): short description

Longer description if needed.
```

Types: `feat`, `fix`, `docs`, `test`, `refactor`, `perf`, `chore`

Examples:
```
feat(operation): add ShuffleOperation
fix(lexer): handle escaped quotes in double-quoted strings
docs(examples): add financial reporting guide
test(expression): add ParserTest for ternary precedence
perf(planner): improve CollapseConsecutiveMaps for long chains
```

---

## Submitting a pull request

1. Fork the repository
2. Create a feature branch: `git checkout -b feat/shuffle-operation`
3. Make your changes with tests
4. Run `make ci` to verify everything passes
5. Open a PR with a clear description of what and why

---

## Issues

Report bugs and request features at:
https://github.com/nalabdou/algebra-php/issues

Please include:
- PHP version and `composer show | grep algebra-php`
- Minimal reproducible example
- Expected vs actual output
