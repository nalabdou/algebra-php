<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

/**
 * A single lexical token produced by {@see Lexer}.
 *
 * Tokens are immutable value objects. Each carries its type constant,
 * raw string value, and the byte offset where it begins in the source string.
 *
 * @immutable
 */
final class Token
{
    public const T_INTEGER = 'T_INTEGER';
    public const T_FLOAT = 'T_FLOAT';
    public const T_STRING = 'T_STRING';
    public const T_BOOL = 'T_BOOL';
    public const T_NULL = 'T_NULL';
    public const T_NAME = 'T_NAME';
    public const T_SUBSCRIPT = 'T_SUBSCRIPT';
    public const T_OP = 'T_OP';
    public const T_LPAREN = 'T_LPAREN';
    public const T_RPAREN = 'T_RPAREN';
    public const T_COMMA = 'T_COMMA';
    public const T_DOT = 'T_DOT';
    public const T_EOF = 'T_EOF';

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly int $offset,
    ) {
    }

    /**
     * Whether this token matches the given type and optionally the given value.
     */
    public function is(string $type, ?string $value = null): bool
    {
        return $this->type === $type && (null === $value || $this->value === $value);
    }

    public function __toString(): string
    {
        return "{$this->type}({$this->value})@{$this->offset}";
    }
}
