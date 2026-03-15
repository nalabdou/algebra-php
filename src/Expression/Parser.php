<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Expression;

use Nalabdou\Algebra\Expression\Node\ArrayNode;
use Nalabdou\Algebra\Expression\Node\BinaryNode;
use Nalabdou\Algebra\Expression\Node\CallNode;
use Nalabdou\Algebra\Expression\Node\LiteralNode;
use Nalabdou\Algebra\Expression\Node\NameNode;
use Nalabdou\Algebra\Expression\Node\Node;
use Nalabdou\Algebra\Expression\Node\PropertyNode;
use Nalabdou\Algebra\Expression\Node\SubscriptNode;
use Nalabdou\Algebra\Expression\Node\TernaryNode;
use Nalabdou\Algebra\Expression\Node\UnaryNode;

/**
 * Recursive-descent parser for the algebra-php expression grammar.
 *
 * Converts a flat {@see Token} list from {@see Lexer} into an AST of
 * {@see Node} objects consumed by {@see Evaluator}.
 *
 * Grammar (simplified EBNF):
 * ```
 * expr       = ternary
 * ternary    = logical ('?' logical ':' logical)?
 * logical    = comparison (('and'|'or'|'&&'|'||') comparison)*
 * comparison = inExpr (('=='|'!='|'<'|'<='|'>'|'>=') inExpr)?
 * inExpr     = additive ('in' array)?
 * additive   = multiplicative (('+' | '-' | '~') multiplicative)*
 * multi      = unary (('*' | '/' | '%' | '**') unary)*
 * unary      = ('not' | '!' | '-') unary | postfix
 * postfix    = primary ('[' expr ']' | '.' NAME | '(' args ')' )*
 * primary    = literal | NAME | '(' expr ')'
 * literal    = INTEGER | FLOAT | STRING | BOOL | NULL
 * array      = '[' (expr (',' expr)*)? ']'
 * ```
 */
final class Parser
{
    /** @var Token[] */
    private array $tokens;
    private int $pos;

    /**
     * Parse a token list into a root AST node.
     *
     * @param Token[] $tokens token list from {@see Lexer::tokenise()}
     *
     * @return Node root node of the parsed expression
     *
     * @throws \RuntimeException on unexpected token or malformed expression
     */
    public function parse(array $tokens): Node
    {
        $this->tokens = $tokens;
        $this->pos = 0;

        $node = $this->parseTernary();
        $this->expect(Token::T_EOF);

        return $node;
    }

    private function parseTernary(): Node
    {
        $condition = $this->parseLogical();

        if ($this->current()->is(Token::T_OP, '?')) {
            $this->consume();
            $then = $this->parseLogical();
            $this->expectOp(':');
            $else = $this->parseLogical();

            return new TernaryNode($condition, $then, $else);
        }

        return $condition;
    }

    private function parseLogical(): Node
    {
        $left = $this->parseComparison();

        while (
            $this->current()->is(Token::T_OP, 'and')
            || $this->current()->is(Token::T_OP, 'or')
            || $this->current()->is(Token::T_OP, '&&')
            || $this->current()->is(Token::T_OP, '||')
        ) {
            $op = $this->consume()->value;
            $right = $this->parseComparison();
            $left = new BinaryNode($op, $left, $right);
        }

        return $left;
    }

    private function parseComparison(): Node
    {
        $left = $this->parseInExpr();

        if (
            $this->current()->is(Token::T_OP)
            && \in_array($this->current()->value, ['==', '!=', '<', '<=', '>', '>='], strict: true)
        ) {
            $op = $this->consume()->value;
            $right = $this->parseInExpr();

            return new BinaryNode($op, $left, $right);
        }

        return $left;
    }

    private function parseInExpr(): Node
    {
        $left = $this->parseAdditive();

        if ($this->current()->is(Token::T_OP, 'in')) {
            $this->consume();

            return new BinaryNode('in', $left, $this->parseArrayLiteral());
        }

        return $left;
    }

    private function parseAdditive(): Node
    {
        $left = $this->parseMultiplicative();

        while (
            $this->current()->is(Token::T_OP)
            && \in_array($this->current()->value, ['+', '-', '~'], strict: true)
        ) {
            $op = $this->consume()->value;
            $right = $this->parseMultiplicative();
            $left = new BinaryNode($op, $left, $right);
        }

        return $left;
    }

    private function parseMultiplicative(): Node
    {
        $left = $this->parseUnary();

        while (
            $this->current()->is(Token::T_OP)
            && \in_array($this->current()->value, ['*', '/', '%', '**'], strict: true)
        ) {
            $op = $this->consume()->value;
            $right = $this->parseUnary();
            $left = new BinaryNode($op, $left, $right);
        }

        return $left;
    }

    private function parseUnary(): Node
    {
        if ($this->current()->is(Token::T_OP, 'not') || $this->current()->is(Token::T_OP, '!')) {
            return new UnaryNode($this->consume()->value, $this->parseUnary());
        }

        if ($this->current()->is(Token::T_OP, '-')) {
            $this->consume();

            return new UnaryNode('-', $this->parseUnary());
        }

        return $this->parsePostfix();
    }

    private function parsePostfix(): Node
    {
        $node = $this->parsePrimary();

        while (true) {
            if ($this->current()->is(Token::T_SUBSCRIPT)) {
                $raw = $this->consume()->value;
                $inner = \trim(\substr($raw, 1, -1));
                $keyNode = $this->parseSnippet($inner);
                $node = new SubscriptNode($node, $keyNode);
                continue;
            }

            if ($this->current()->is(Token::T_DOT)) {
                $this->consume();
                $prop = $this->expect(Token::T_NAME)->value;
                $node = new PropertyNode($node, $prop);
                continue;
            }

            break;
        }

        return $node;
    }

    private function parsePrimary(): Node
    {
        $tok = $this->current();

        if ($tok->is(Token::T_INTEGER)) {
            $this->consume();

            return new LiteralNode((int) $tok->value);
        }

        if ($tok->is(Token::T_FLOAT)) {
            $this->consume();

            return new LiteralNode((float) $tok->value);
        }

        if ($tok->is(Token::T_STRING)) {
            $this->consume();

            return new LiteralNode($tok->value);
        }

        if ($tok->is(Token::T_BOOL)) {
            $this->consume();

            return new LiteralNode('true' === $tok->value);
        }

        if ($tok->is(Token::T_NULL)) {
            $this->consume();

            return new LiteralNode(null);
        }

        if ($tok->is(Token::T_NAME)) {
            $this->consume();
            $name = $tok->value;

            if ($this->current()->is(Token::T_LPAREN)) {
                $this->consume();
                $args = [];

                if (!$this->current()->is(Token::T_RPAREN)) {
                    $args[] = $this->parseTernary();
                    while ($this->current()->is(Token::T_COMMA)) {
                        $this->consume();
                        $args[] = $this->parseTernary();
                    }
                }

                $this->expect(Token::T_RPAREN);

                return new CallNode($name, $args);
            }

            $node = new NameNode($name);

            while ($this->current()->is(Token::T_DOT)) {
                $this->consume();
                $prop = $this->expect(Token::T_NAME)->value;
                $node = new PropertyNode($node, $prop);
            }

            return $node;
        }

        if ($tok->is(Token::T_LPAREN)) {
            $this->consume();
            $node = $this->parseTernary();
            $this->expect(Token::T_RPAREN);

            return $node;
        }

        if ($tok->is(Token::T_SUBSCRIPT)) {
            return $this->parseArrayLiteral();
        }

        throw new \RuntimeException(\sprintf('Unexpected token %s at offset %d', $tok, $tok->offset));
    }

    private function parseArrayLiteral(): Node
    {
        $tok = $this->current();

        if ($tok->is(Token::T_SUBSCRIPT)) {
            $this->consume();
            $raw = \trim(\substr($tok->value, 1, -1));

            return new ArrayNode('' !== $raw ? $this->parseCommaSeparated($raw) : []);
        }

        throw new \RuntimeException('Expected array literal starting with [');
    }

    /** @return Node[] */
    private function parseCommaSeparated(string $raw): array
    {
        $items = [];
        $depth = 0;
        $start = 0;
        $len = \strlen($raw);

        for ($i = 0; $i < $len; ++$i) {
            if ('[' === $raw[$i]) {
                ++$depth;
            } elseif (']' === $raw[$i]) {
                --$depth;
            } elseif (',' === $raw[$i] && 0 === $depth) {
                $item = \trim(\substr($raw, $start, $i - $start));
                if ('' !== $item) {
                    $items[] = $this->parseSnippet($item);
                }
                $start = $i + 1;
            }
        }

        $last = \trim(\substr($raw, $start));
        if ('' !== $last) {
            $items[] = $this->parseSnippet($last);
        }

        return $items;
    }

    private function parseSnippet(string $expr): Node
    {
        return (new self())->parse((new Lexer())->tokenise(\trim($expr)));
    }

    private function current(): Token
    {
        return $this->tokens[$this->pos];
    }

    private function consume(): Token
    {
        return $this->tokens[$this->pos++];
    }

    private function expect(string $type): Token
    {
        $tok = $this->current();

        if ($tok->type !== $type) {
            throw new \RuntimeException(\sprintf('Expected %s but got %s at offset %d', $type, $tok, $tok->offset));
        }

        return $this->consume();
    }

    private function expectOp(string $value): Token
    {
        $tok = $this->current();

        if (!$tok->is(Token::T_OP, $value)) {
            throw new \RuntimeException(\sprintf("Expected operator '%s' but got %s at offset %d", $value, $tok, $tok->offset));
        }

        return $this->consume();
    }
}
