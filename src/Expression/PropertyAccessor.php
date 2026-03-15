<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

/**
 * Deep property accessor for arrays and objects.
 *
 * Resolves dot-path expressions like `"user.address.city"` against either
 * an associative array or an object with public properties or getters.
 *
 * Zero external dependencies — no Symfony, no reflection overhead for
 * the common single-level case.
 *
 * Single-level access uses a fast path that avoids all string splitting:
 * ```php
 * $accessor->get(['status' => 'paid'], 'status');  // O(1)
 * ```
 *
 * Dot-path access walks each segment:
 * ```php
 * $accessor->get(['user' => ['name' => 'Alice']], 'user.name'); // 'Alice'
 * $accessor->get($order, 'user.email');                         // via getter
 * ```
 *
 * Returns `null` when any segment is missing rather than throwing.
 */
final class PropertyAccessor
{
    /**
     * Resolve a dot-path against a row.
     *
     * @param mixed  $row  associative array or object
     * @param string $path bare key (`"status"`) or dot-path (`"user.address.city"`)
     *
     * @return mixed the resolved value, or `null` when not found
     */
    public function get(mixed $row, string $path): mixed
    {
        if (!\str_contains($path, '.')) {
            return match (true) {
                \is_array($row) => $row[$path] ?? null,
                \is_object($row) => $this->objectGet($row, $path),
                default => null,
            };
        }

        return $this->dotGet($row, $path);
    }

    /**
     * Parse a join condition string into left and right key paths.
     *
     * Supported formats:
     * - `"userId=id"` → `['userId', 'id']`
     * - `" userId = id "` → `['userId', 'id']` (whitespace trimmed)
     * - `"order.userId=user.id"` → `['order.userId', 'user.id']`
     *
     * @param string $condition condition string with `=` separator
     *
     * @return array{string, string} `[leftKeyPath, rightKeyPath]`
     *
     * @throws \InvalidArgumentException when no `=` separator is present
     */
    public function parseJoinCondition(string $condition): array
    {
        if (!\str_contains($condition, '=')) {
            throw new \InvalidArgumentException("Join condition must contain '='. Examples: 'userId=id', 'order.userId=user.id'. Got: '{$condition}'");
        }

        [$left, $right] = \explode('=', $condition, 2);

        return [\trim($left), \trim($right)];
    }

    private function dotGet(mixed $row, string $path): mixed
    {
        $current = $row;

        foreach (\explode('.', $path) as $segment) {
            $current = match (true) {
                null === $current => null,
                \is_array($current) => $current[$segment] ?? null,
                \is_object($current) => $this->objectGet($current, $segment),
                default => null,
            };

            if (null === $current) {
                return null;
            }
        }

        return $current;
    }

    private function objectGet(object $obj, string $key): mixed
    {
        foreach (['get', 'is', 'has'] as $prefix) {
            $method = $prefix.\ucfirst($key);
            if (\method_exists($obj, $method)) {
                return $obj->{$method}();
            }
        }

        if (\property_exists($obj, $key)) {
            return $obj->{$key};
        }

        if (\method_exists($obj, '__get')) {
            try {
                return $obj->{$key};
            }
            // @phpstan-ignore catch.neverThrown
            catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
