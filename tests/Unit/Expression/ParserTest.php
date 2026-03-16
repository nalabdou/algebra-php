<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Expression;

use Nalabdou\Algebra\Expression\Lexer;
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
use Nalabdou\Algebra\Expression\Parser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[CoversClass(LiteralNode::class)]
#[CoversClass(NameNode::class)]
#[CoversClass(BinaryNode::class)]
#[CoversClass(UnaryNode::class)]
#[CoversClass(CallNode::class)]
#[CoversClass(TernaryNode::class)]
#[CoversClass(SubscriptNode::class)]
#[CoversClass(PropertyNode::class)]
#[CoversClass(ArrayNode::class)]
final class ParserTest extends TestCase
{
    private function parse(string $expr): Node
    {
        return (new Parser())->parse((new Lexer())->tokenise($expr));
    }

    public function testParsesIntegerLiteral(): void
    {
        $node = $this->parse('42');
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertSame(42, $node->value);
    }

    public function testParsesFloatLiteral(): void
    {
        $node = $this->parse('3.14');
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertSame(3.14, $node->value);
    }

    public function testParsesStringLiteral(): void
    {
        $node = $this->parse("'paid'");
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertSame('paid', $node->value);
    }

    public function testParsesTrueLiteral(): void
    {
        $node = $this->parse('true');
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertTrue($node->value);
    }

    public function testParsesFalseLiteral(): void
    {
        $node = $this->parse('false');
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertFalse($node->value);
    }

    public function testParsesNullLiteral(): void
    {
        $node = $this->parse('null');
        self::assertInstanceOf(LiteralNode::class, $node);
        self::assertNull($node->value);
    }

    public function testParsesName(): void
    {
        $node = $this->parse('status');
        self::assertInstanceOf(NameNode::class, $node);
        self::assertSame('status', $node->name);
    }

    public function testParsesDotPath(): void
    {
        $node = $this->parse('user.name');
        self::assertInstanceOf(PropertyNode::class, $node);
        self::assertSame('name', $node->property);
        self::assertInstanceOf(NameNode::class, $node->object);
    }

    public function testParsesSubscriptAccess(): void
    {
        $node = $this->parse("item['status']");
        self::assertInstanceOf(SubscriptNode::class, $node);
        self::assertInstanceOf(NameNode::class, $node->object);
        self::assertInstanceOf(LiteralNode::class, $node->key);
        self::assertSame('status', $node->key->value);
    }

    public function testParsesEquality(): void
    {
        $node = $this->parse("status == 'paid'");
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('==', $node->operator);
    }

    public function testParsesNotEqual(): void
    {
        $node = $this->parse("status != 'cancelled'");
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('!=', $node->operator);
    }

    public function testParsesComparisonOperators(): void
    {
        foreach (['<', '<=', '>', '>='] as $op) {
            $node = $this->parse("amount {$op} 100");
            self::assertInstanceOf(BinaryNode::class, $node);
            self::assertSame($op, $node->operator);
        }
    }

    public function testParsesLogicalAnd(): void
    {
        $node = $this->parse('a == 1 and b == 2');
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('and', $node->operator);
    }

    public function testParsesLogicalOr(): void
    {
        $node = $this->parse('a == 1 or b == 2');
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('or', $node->operator);
    }

    public function testParsesSymbolicAnd(): void
    {
        $node = $this->parse('a == 1 && b == 2');
        self::assertSame('&&', $node->operator);
    }

    public function testParsesSymbolicOr(): void
    {
        $node = $this->parse('a == 1 || b == 2');
        self::assertSame('||', $node->operator);
    }

    public function testParsesArithmeticOperators(): void
    {
        foreach (['+', '-', '*', '/', '%'] as $op) {
            $node = $this->parse("a {$op} b");
            self::assertInstanceOf(BinaryNode::class, $node);
            self::assertSame($op, $node->operator);
        }
    }

    public function testParsesConcatenation(): void
    {
        $node = $this->parse('a ~ b');
        self::assertSame('~', $node->operator);
    }

    public function testParsesInOperator(): void
    {
        $node = $this->parse("status in ['paid', 'refunded']");
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('in', $node->operator);
        self::assertInstanceOf(ArrayNode::class, $node->right);
        self::assertCount(2, $node->right->elements);
    }

    public function testParsesNot(): void
    {
        $node = $this->parse('not flag');
        self::assertInstanceOf(UnaryNode::class, $node);
        self::assertSame('not', $node->operator);
    }

    public function testParsesBang(): void
    {
        $node = $this->parse('!flag');
        self::assertInstanceOf(UnaryNode::class, $node);
        self::assertSame('!', $node->operator);
    }

    public function testParsesUnaryMinus(): void
    {
        $node = $this->parse('-x');
        self::assertInstanceOf(UnaryNode::class, $node);
        self::assertSame('-', $node->operator);
    }

    public function testParsesFunctionCallNoArgs(): void
    {
        $node = $this->parse('now()');
        self::assertInstanceOf(CallNode::class, $node);
        self::assertSame('now', $node->name);
        self::assertEmpty($node->arguments);
    }

    public function testParsesFunctionCallOneArg(): void
    {
        $node = $this->parse('length(name)');
        self::assertInstanceOf(CallNode::class, $node);
        self::assertSame('length', $node->name);
        self::assertCount(1, $node->arguments);
    }

    public function testParsesFunctionCallTwoArgs(): void
    {
        $node = $this->parse("contains(email, '@example.com')");
        self::assertInstanceOf(CallNode::class, $node);
        self::assertCount(2, $node->arguments);
    }

    public function testParsesTernary(): void
    {
        $node = $this->parse("amount > 500 ? 'high' : 'low'");
        self::assertInstanceOf(TernaryNode::class, $node);
        self::assertInstanceOf(BinaryNode::class, $node->condition);
        self::assertInstanceOf(LiteralNode::class, $node->then);
        self::assertSame('high', $node->then->value);
    }

    public function testParsesGroupedExpression(): void
    {
        $node = $this->parse('(1 + 2)');
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('+', $node->operator);
    }

    public function testMultiplicationBeforeAddition(): void
    {
        $node = $this->parse('1 + 2 * 3');
        self::assertInstanceOf(BinaryNode::class, $node);
        self::assertSame('+', $node->operator);
        self::assertInstanceOf(BinaryNode::class, $node->right);
        self::assertSame('*', $node->right->operator);
    }

    public function testParensOverridePrecedence(): void
    {
        $node = $this->parse('(1 + 2) * 3');
        self::assertSame('*', $node->operator);
        self::assertSame('+', $node->left->operator);
    }

    public function testUnexpectedTokenThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->parse(') invalid (');
    }
}
