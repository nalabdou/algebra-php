<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Expression;

use Nalabdou\Algebra\Expression\Lexer;
use Nalabdou\Algebra\Expression\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lexer::class)]
#[CoversClass(Token::class)]
final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testTokenisesInteger(): void
    {
        $tokens = $this->lexer->tokenise('42');

        self::assertSame(Token::T_INTEGER, $tokens[0]->type);
        self::assertSame('42', $tokens[0]->value);
        self::assertSame(Token::T_EOF, $tokens[1]->type);
    }

    public function testTokenisesNegativeInteger(): void
    {
        $tokens = $this->lexer->tokenise('-10');

        self::assertSame(Token::T_INTEGER, $tokens[0]->type);
        self::assertSame('-10', $tokens[0]->value);
    }

    public function testTokenisesFloat(): void
    {
        $tokens = $this->lexer->tokenise('3.14');

        self::assertSame(Token::T_FLOAT, $tokens[0]->type);
        self::assertSame('3.14', $tokens[0]->value);
    }

    public function testTokenisesNegativeFloat(): void
    {
        $tokens = $this->lexer->tokenise('-0.5');

        self::assertSame(Token::T_FLOAT, $tokens[0]->type);
        self::assertSame('-0.5', $tokens[0]->value);
    }

    public function testTokenisesSingleQuotedString(): void
    {
        $tokens = $this->lexer->tokenise("'hello'");

        self::assertSame(Token::T_STRING, $tokens[0]->type);
        self::assertSame('hello', $tokens[0]->value);
    }

    public function testTokenisesDoubleQuotedString(): void
    {
        $tokens = $this->lexer->tokenise('"world"');

        self::assertSame(Token::T_STRING, $tokens[0]->type);
        self::assertSame('world', $tokens[0]->value);
    }

    public function testStringEscapeSequences(): void
    {
        $tokens = $this->lexer->tokenise("'line\\nnext'");

        self::assertSame("line\nnext", $tokens[0]->value);
    }

    public function testUnterminatedStringThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->lexer->tokenise("'unclosed");
    }

    public function testTokenisesTrue(): void
    {
        $tokens = $this->lexer->tokenise('true');
        self::assertSame(Token::T_BOOL, $tokens[0]->type);
        self::assertSame('true', $tokens[0]->value);
    }

    public function testTokenisesFalse(): void
    {
        $tokens = $this->lexer->tokenise('false');
        self::assertSame(Token::T_BOOL, $tokens[0]->type);
        self::assertSame('false', $tokens[0]->value);
    }

    public function testTokenisesNull(): void
    {
        $tokens = $this->lexer->tokenise('null');
        self::assertSame(Token::T_NULL, $tokens[0]->type);
    }

    public function testKeywordsCaseInsensitive(): void
    {
        $tokens = $this->lexer->tokenise('TRUE');
        self::assertSame(Token::T_BOOL, $tokens[0]->type);
    }

    public function testTokenisesIdentifier(): void
    {
        $tokens = $this->lexer->tokenise('status');
        self::assertSame(Token::T_NAME, $tokens[0]->type);
        self::assertSame('status', $tokens[0]->value);
    }

    public function testTokenisesUnderscoreIdentifier(): void
    {
        $tokens = $this->lexer->tokenise('_my_var');
        self::assertSame(Token::T_NAME, $tokens[0]->type);
    }

    public function testTokenisesTwoCharOperators(): void
    {
        foreach (['==', '!=', '<=', '>=', '&&', '||'] as $op) {
            $tokens = $this->lexer->tokenise($op);
            self::assertSame(Token::T_OP, $tokens[0]->type, "Failed for: {$op}");
            self::assertSame($op, $tokens[0]->value);
        }
    }

    public function testTokenisesSingleCharOperators(): void
    {
        foreach (['<', '>', '+', '-', '*', '/', '%', '~', '!', '?', ':'] as $op) {
            $tokens = $this->lexer->tokenise($op);
            self::assertSame(Token::T_OP, $tokens[0]->type, "Failed for: {$op}");
        }
    }

    public function testTokenisesLogicalKeywords(): void
    {
        foreach (['and', 'or', 'not', 'in'] as $kw) {
            $tokens = $this->lexer->tokenise($kw);
            self::assertSame(Token::T_OP, $tokens[0]->type);
            self::assertSame($kw, $tokens[0]->value);
        }
    }

    public function testTokenisesParens(): void
    {
        $tokens = $this->lexer->tokenise('()');
        self::assertSame(Token::T_LPAREN, $tokens[0]->type);
        self::assertSame(Token::T_RPAREN, $tokens[1]->type);
    }

    public function testTokenisesComma(): void
    {
        $tokens = $this->lexer->tokenise(',');
        self::assertSame(Token::T_COMMA, $tokens[0]->type);
    }

    public function testTokenisesDot(): void
    {
        $tokens = $this->lexer->tokenise('.');
        self::assertSame(Token::T_DOT, $tokens[0]->type);
    }

    public function testTokenisesSubscriptBracket(): void
    {
        $tokens = $this->lexer->tokenise("['key']");
        self::assertSame(Token::T_SUBSCRIPT, $tokens[0]->type);
        self::assertSame("['key']", $tokens[0]->value);
    }

    public function testTokenisesComparisonExpression(): void
    {
        $tokens = $this->lexer->tokenise("status == 'paid'");
        self::assertSame(Token::T_NAME, $tokens[0]->type);
        self::assertSame(Token::T_OP, $tokens[1]->type);
        self::assertSame(Token::T_STRING, $tokens[2]->type);
        self::assertSame(Token::T_EOF, $tokens[3]->type);
    }

    public function testAlwaysEndsWithEof(): void
    {
        $tokens = $this->lexer->tokenise('1 + 2');
        self::assertSame(Token::T_EOF, \end($tokens)->type);
    }

    public function testSkipsWhitespace(): void
    {
        $tokens = $this->lexer->tokenise('  42  ');
        self::assertSame(Token::T_INTEGER, $tokens[0]->type);
        self::assertCount(2, $tokens); // integer + EOF
    }

    public function testUnknownCharacterThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->lexer->tokenise('@invalid');
    }

    public function testTokenOffsetIsCorrect(): void
    {
        $tokens = $this->lexer->tokenise('  hello');
        self::assertSame(2, $tokens[0]->offset);
    }

    public function testTokenIsHelper(): void
    {
        $tok = new Token(Token::T_NAME, 'status', 0);
        self::assertTrue($tok->is(Token::T_NAME));
        self::assertTrue($tok->is(Token::T_NAME, 'status'));
        self::assertFalse($tok->is(Token::T_NAME, 'other'));
        self::assertFalse($tok->is(Token::T_OP));
    }

    public function testTokenToString(): void
    {
        $tok = new Token(Token::T_NAME, 'foo', 5);
        self::assertStringContainsString('T_NAME', (string) $tok);
        self::assertStringContainsString('foo', (string) $tok);
        self::assertStringContainsString('5', (string) $tok);
    }
}
