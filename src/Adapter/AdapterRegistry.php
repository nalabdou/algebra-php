<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Adapter;

use Nalabdou\Algebra\Contract\AdapterInterface;

/**
 * Registry of all input adapters used by {@see \Nalabdou\Algebra\Collection\CollectionFactory}.
 *
 * Adapters are tried in priority order (highest first). The first adapter whose
 * {@see AdapterInterface::supports()} returns `true` is used to convert the input.
 *
 * ### Built-in adapters (registered automatically)
 *
 * | Priority | Adapter | Handles |
 * |---|---|---|
 * | 20 | `GeneratorAdapter` | PHP `\Generator` |
 * | 10 | `TraversableAdapter` | Any `\Traversable` (except generators) |
 * | 0 | `ArrayAdapter` | Plain PHP arrays |
 *
 * ### Registering custom adapters
 * ```php
 * // Register at default priority (0)
 * Algebra::adapters()->register(new CsvFileAdapter());
 *
 * // Register at higher priority than built-ins
 * Algebra::adapters()->register(new DoctrineQueryBuilderAdapter(), priority: 100);
 *
 * // Then use normally — no factory configuration needed
 * Algebra::from($queryBuilder)->where(...)->toArray();
 * Algebra::from('/path/to/file.csv')->groupBy('region')->toArray();
 * ```
 *
 * ### Replacing a built-in
 * Register a new adapter at the same or higher priority — it will be tried first.
 * The built-in remains registered but will never be reached for inputs the new
 * adapter claims to support.
 *
 * ### Priority guidelines
 * - **100+** — framework-specific (Doctrine, Eloquent, QueryBuilder)
 * - **50–99** — third-party input types (CSV, Redis, REST)
 * - **1–49** — application-specific adapters
 * - **20** — built-in `GeneratorAdapter`
 * - **10** — built-in `TraversableAdapter`
 * - **0** — built-in `ArrayAdapter` (last resort)
 */
final class AdapterRegistry
{
    /**
     * @var array<int, array{adapter: AdapterInterface, priority: int}>
     */
    private array $entries = [];

    /** Whether the sorted list needs rebuilding after a new registration. */
    private bool $dirty = false;

    /** @var AdapterInterface[] sorted by priority descending, rebuilt on demand */
    private array $sorted = [];

    public function __construct()
    {
        $this->registerBuiltins();
    }

    /**
     * Register a custom adapter.
     *
     * Higher priority = tried first. The same adapter class may be registered
     * multiple times at different priorities — the highest-priority instance wins.
     *
     * @param AdapterInterface $adapter  the adapter to register
     * @param int              $priority higher = checked first (default: 0)
     */
    public function register(AdapterInterface $adapter, int $priority = 0): void
    {
        $this->entries[] = ['adapter' => $adapter, 'priority' => $priority];
        $this->dirty = true;
    }

    /**
     * Return all adapters in priority order (highest first).
     *
     * @return AdapterInterface[]
     */
    public function all(): array
    {
        if ($this->dirty) {
            $entries = $this->entries;
            \usort(
                $entries,
                static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']
            );
            $this->sorted = \array_column($entries, 'adapter');
            $this->dirty = false;
        }

        return $this->sorted;
    }

    /**
     * Find the first adapter that supports the given input.
     *
     * @return AdapterInterface|null null when no adapter supports the input
     */
    public function find(mixed $input): ?AdapterInterface
    {
        foreach ($this->all() as $adapter) {
            if ($adapter->supports($input)) {
                return $adapter;
            }
        }

        return null;
    }

    /**
     * Total number of registered adapters (including built-ins).
     */
    public function count(): int
    {
        return \count($this->entries);
    }

    private function registerBuiltins(): void
    {
        $this->register(new GeneratorAdapter(), priority: 20);
        $this->register(new TraversableAdapter(), priority: 10);
        $this->register(new ArrayAdapter(), priority: 0);
    }
}
