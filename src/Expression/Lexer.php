<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

/**
 * Converts a raw expression string into a flat list of {@see Token} objects.
 *
 * Single-pass, hand-written scanner with no external dependencies.
 * Handles the complete grammar required by {@see Parser}:
 *
 * - Integer and float literals, including negative numbers
 * - Single and double-quoted strings with escape sequences
 * - Boolean literals (`true`, `false`) and null
 * - Identifiers and keyword operators (`and`, `or`, `not`, `in`)
 * - All comparison, logical, arithmetic, and concatenation operators
 * - Subscript bracket notation (`item['key']`)
 * - Grouping parentheses and function call syntax
 */
final class Lexer
{
    private string $source;
    private int $pos;
    private int $len;

    /** @var Token[] */
    private array $tokens = [];

    /**
     * Tokenise the expression string into a token list.
     *
     * @param string $source the raw expression string
     *
     * @return Token[] ordered list, always ending with {@see Token::T_EOF}
     *
     * @throws \RuntimeException on unrecognised character or unterminated string
     */
    public function tokenise(string $source): array
    {
        $this->source = $source;
        $this->pos = 0;
        $this->len = \strlen($source);
        $this->tokens = [];

        while ($this->pos < $this->len) {
            $this->skipWhitespace();

            if ($this->pos >= $this->len) {
                break;
            }

            $ch = $this->source[$this->pos];
            $off = $this->pos;

            if (\ctype_digit($ch) || ('-' === $ch && null !== $this->peekChar(1) && \ctype_digit($this->peekChar(1)))) {
                $this->tokens[] = $this->scanNumber($off);
                continue;
            }

            if ('\'' === $ch || '"' === $ch) {
                $this->tokens[] = $this->scanString($off);
                continue;
            }

            if (\ctype_alpha($ch) || '_' === $ch) {
                $this->tokens[] = $this->scanName($off);
                continue;
            }

            if ('[' === $ch) {
                $this->tokens[] = $this->scanSubscript($off);
                continue;
            }

            $two = $this->peek(2) ?? '';
            if (\in_array($two, ['==', '!=', '<=', '>=', '&&', '||', '**'], strict: true)) {
                $this->tokens[] = new Token(Token::T_OP, $two, $off);
                $this->pos += 2;
                continue;
            }

            $token = match ($ch) {
                '(' => new Token(Token::T_LPAREN, '(', $off),
                ')' => new Token(Token::T_RPAREN, ')', $off),
                ',' => new Token(Token::T_COMMA, ',', $off),
                '.' => new Token(Token::T_DOT, '.', $off),
                '<', '>', '+', '-', '*', '/', '%', '~', '!', '?', ':' => new Token(Token::T_OP, $ch, $off),
                default => null,
            };

            if (null !== $token) {
                $this->tokens[] = $token;
                ++$this->pos;
                continue;
            }

            throw new \RuntimeException(\sprintf("Unexpected character '%s' at offset %d in: %s", $ch, $off, $source));
        }

        $this->tokens[] = new Token(Token::T_EOF, '', $this->len);

        return $this->tokens;
    }

    private function skipWhitespace(): void
    {
        while ($this->pos < $this->len && \ctype_space($this->source[$this->pos])) {
            ++$this->pos;
        }
    }

    private function peek(int $length): ?string
    {
        return ($this->pos + $length - 1 < $this->len)
            ? \substr($this->source, $this->pos, $length)
            : null;
    }

    private function peekChar(int $offset): ?string
    {
        $pos = $this->pos + $offset;

        return ($pos < $this->len) ? $this->source[$pos] : null;
    }

    private function scanNumber(int $off): Token
    {
        $start = $this->pos;
        $isFloat = false;

        if ('-' === $this->source[$this->pos]) {
            ++$this->pos;
        }

        while ($this->pos < $this->len && \ctype_digit($this->source[$this->pos])) {
            ++$this->pos;
        }

        if (
            $this->pos < $this->len
            && '.' === $this->source[$this->pos]
            && $this->pos + 1 < $this->len
            && \ctype_digit($this->source[$this->pos + 1])
        ) {
            $isFloat = true;
            ++$this->pos;
            while ($this->pos < $this->len && \ctype_digit($this->source[$this->pos])) {
                ++$this->pos;
            }
        }

        return new Token(
            $isFloat ? Token::T_FLOAT : Token::T_INTEGER,
            \substr($this->source, $start, $this->pos - $start),
            $off
        );
    }

    private function scanString(int $off): Token
    {
        $quote = $this->source[$this->pos];
        ++$this->pos;
        $value = '';

        while ($this->pos < $this->len && $this->source[$this->pos] !== $quote) {
            if ('\\' === $this->source[$this->pos] && $this->pos + 1 < $this->len) {
                ++$this->pos;
                $value .= match ($this->source[$this->pos]) {
                    'n' => "\n",
                    't' => "\t",
                    'r' => "\r",
                    '\\' => '\\',
                    '\'' => '\'',
                    '"' => '"',
                    default => '\\'.$this->source[$this->pos],
                };
                ++$this->pos;
            } else {
                $value .= $this->source[$this->pos++];
            }
        }

        if ($this->pos >= $this->len) {
            throw new \RuntimeException("Unterminated string literal in: {$this->source}");
        }

        ++$this->pos;

        return new Token(Token::T_STRING, $value, $off);
    }

    private function scanName(int $off): Token
    {
        $start = $this->pos;

        while (
            $this->pos < $this->len
            && (\ctype_alnum($this->source[$this->pos]) || '_' === $this->source[$this->pos])
        ) {
            ++$this->pos;
        }

        $value = \substr($this->source, $start, $this->pos - $start);

        return match (\strtolower($value)) {
            'true' => new Token(Token::T_BOOL, 'true', $off),
            'false' => new Token(Token::T_BOOL, 'false', $off),
            'null' => new Token(Token::T_NULL, 'null', $off),
            'and' => new Token(Token::T_OP, 'and', $off),
            'or' => new Token(Token::T_OP, 'or', $off),
            'not' => new Token(Token::T_OP, 'not', $off),
            'in' => new Token(Token::T_OP, 'in', $off),
            default => new Token(Token::T_NAME, $value, $off),
        };
    }

    private function scanSubscript(int $off): Token
    {
        $depth = 0;
        $value = '';

        while ($this->pos < $this->len) {
            $ch = $this->source[$this->pos];

            if ('[' === $ch) {
                ++$depth;
            } elseif (']' === $ch) {
                --$depth;
            }

            $value .= $ch;
            ++$this->pos;

            if (0 === $depth) {
                break;
            }
        }

        return new Token(Token::T_SUBSCRIPT, $value, $off);
    }
}
