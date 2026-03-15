<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

use Nalabdou\Algebra\Expression\Node\Node;

/**
 * AST cache for compiled expression strings.
 *
 * Stores parsed {@see Node} ASTs so each unique expression string is only
 * lexed and parsed once per process. Dramatically reduces overhead for
 * repeated pipeline executions using the same expressions.
 *
 * Backend selection is automatic:
 * - **APCu** — when `ext-apcu` is loaded and `apc.enabled=1`. Shared across
 *   requests within the same worker process, survives request boundaries.
 * - **In-process array** — fallback when APCu is unavailable. Fast within a
 *   single request, cleared on process exit.
 *
 * Cache keys use an xxh3 hash of the expression string — fast and collision-resistant.
 */
final class ExpressionCache
{
    private const PREFIX = 'alg_ast_';
    private const TTL = 3_600;

    /** @var array<string, Node> In-process fallback store */
    private array $memory = [];

    private readonly bool $apcuAvailable;

    public function __construct()
    {
        $this->apcuAvailable = \function_exists('apcu_fetch') && (bool) \ini_get('apc.enabled');
    }

    /**
     * Retrieve a previously cached AST node.
     *
     * @param string $expression the raw expression string
     *
     * @return Node|null the cached node, or null on miss
     */
    public function get(string $expression): ?Node
    {
        $key = $this->buildKey($expression);

        if ($this->apcuAvailable) {
            $result = \apcu_fetch($key, $success);

            return $success ? $result : null;
        }

        return $this->memory[$key] ?? null;
    }

    /**
     * Store a compiled AST node.
     *
     * @param string $expression the raw expression string
     * @param Node   $node       the compiled root AST node
     */
    public function set(string $expression, Node $node): void
    {
        $key = $this->buildKey($expression);

        if ($this->apcuAvailable) {
            \apcu_store($key, $node, self::TTL);

            return;
        }

        $this->memory[$key] = $node;
    }

    /**
     * Clear all cached ASTs from the in-process store.
     *
     * Also clears APCu entries for this prefix when APCu is available.
     * Useful in tests and when resetting the expression engine.
     */
    public function flush(): void
    {
        $this->memory = [];

        if ($this->apcuAvailable) {
            \apcu_delete(new \APCUIterator('/^'.\preg_quote(self::PREFIX, '/').'/'));
        }
    }

    /**
     * Number of ASTs held in the in-process store.
     *
     * Returns 0 for APCu-backed caches (iteration is expensive and not needed).
     */
    public function size(): int
    {
        return \count($this->memory);
    }

    private function buildKey(string $expression): string
    {
        return self::PREFIX.\hash('xxh3', $expression);
    }
}
