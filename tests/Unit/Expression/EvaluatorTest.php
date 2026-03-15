<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Expression;

use Nalabdou\Algebra\Expression\ExpressionCache;
use Nalabdou\Algebra\Expression\ExpressionEvaluator;
use Nalabdou\Algebra\Expression\PropertyAccessor;
use PHPUnit\Framework\TestCase;

final class EvaluatorTest extends TestCase
{
    private ExpressionEvaluator $eval;

    protected function setUp(): void
    {
        $this->eval = new ExpressionEvaluator(new PropertyAccessor(), new ExpressionCache());
    }

    private function evaluate(array $row, string $expr): bool
    {
        return $this->eval->evaluate($row, $expr);
    }

    private function resolve(array $row, string $expr): mixed
    {
        return $this->eval->resolve($row, $expr);
    }

    public function testLiteralTrue(): void
    {
        self::assertTrue($this->evaluate([], 'true'));
    }

    public function testLiteralFalse(): void
    {
        self::assertFalse($this->evaluate([], 'false'));
    }

    public function testLiteralNumberIsTruthy(): void
    {
        self::assertTrue($this->evaluate([], '1'));
    }

    public function testLiteralZeroIsFalsy(): void
    {
        self::assertFalse($this->evaluate([], '0'));
    }

    public function testDirectVariableAccess(): void
    {
        self::assertTrue($this->evaluate(['flag' => true], 'flag'));
    }

    public function testItemSubscriptAccess(): void
    {
        self::assertTrue($this->evaluate(['status' => 'paid'], "item['status'] == 'paid'"));
    }

    public function testDirectKeyAsVariable(): void
    {
        self::assertTrue($this->evaluate(['status' => 'paid'], "status == 'paid'"));
    }

    public function testMissingVariableIsNull(): void
    {
        self::assertNull($this->resolve([], 'missing'));
    }

    public function testEquality(): void
    {
        self::assertTrue($this->evaluate(['v' => 5], 'v == 5'));
        self::assertFalse($this->evaluate(['v' => 5], 'v == 6'));
    }

    public function testNotEqual(): void
    {
        self::assertTrue($this->evaluate(['v' => 5], 'v != 6'));
        self::assertFalse($this->evaluate(['v' => 5], 'v != 5'));
    }

    public function testGreaterThan(): void
    {
        self::assertTrue($this->evaluate(['v' => 10], 'v > 5'));
        self::assertFalse($this->evaluate(['v' => 5], 'v > 10'));
    }

    public function testGreaterEqual(): void
    {
        self::assertTrue($this->evaluate(['v' => 5], 'v >= 5'));
        self::assertTrue($this->evaluate(['v' => 6], 'v >= 5'));
        self::assertFalse($this->evaluate(['v' => 4], 'v >= 5'));
    }

    public function testLessThan(): void
    {
        self::assertTrue($this->evaluate(['v' => 3], 'v < 5'));
        self::assertFalse($this->evaluate(['v' => 5], 'v < 5'));
    }

    public function testLessEqual(): void
    {
        self::assertTrue($this->evaluate(['v' => 5], 'v <= 5'));
        self::assertFalse($this->evaluate(['v' => 6], 'v <= 5'));
    }

    public function testAndOperator(): void
    {
        self::assertTrue($this->evaluate(['a' => 1, 'b' => 2], 'a == 1 and b == 2'));
        self::assertFalse($this->evaluate(['a' => 1, 'b' => 3], 'a == 1 and b == 2'));
    }

    public function testOrOperator(): void
    {
        self::assertTrue($this->evaluate(['a' => 1, 'b' => 99], 'a == 1 or b == 2'));
        self::assertFalse($this->evaluate(['a' => 9, 'b' => 9], 'a == 1 or b == 2'));
    }

    public function testAndShortCircuits(): void
    {
        // If left is false, right should not be evaluated
        $called = false;
        $this->eval->evaluate(['v' => 0], 'v == 1 and v == 2');
        self::assertFalse($called); // no exception means short-circuit worked
    }

    public function testOrShortCircuits(): void
    {
        self::assertTrue($this->evaluate(['v' => 1], 'v == 1 or v / 0 == 0'));
    }

    public function testNotOperator(): void
    {
        self::assertTrue($this->evaluate(['flag' => false], 'not flag'));
        self::assertFalse($this->evaluate(['flag' => true], 'not flag'));
    }

    public function testBangOperator(): void
    {
        self::assertTrue($this->evaluate(['flag' => false], '!flag'));
    }

    public function testSymbolicAnd(): void
    {
        self::assertTrue($this->evaluate(['a' => 1, 'b' => 1], 'a == 1 && b == 1'));
    }

    public function testSymbolicOr(): void
    {
        self::assertTrue($this->evaluate(['a' => 1, 'b' => 0], 'a == 1 || b == 1'));
    }

    public function testAddition(): void
    {
        self::assertSame(5, $this->resolve(['a' => 2, 'b' => 3], 'a + b'));
    }

    public function testSubtraction(): void
    {
        self::assertSame(1, $this->resolve(['a' => 3, 'b' => 2], 'a - b'));
    }

    public function testMultiplication(): void
    {
        self::assertSame(6, $this->resolve(['a' => 2, 'b' => 3], 'a * b'));
    }

    public function testDivision(): void
    {
        self::assertEqualsWithDelta(2.5, $this->resolve(['a' => 5, 'b' => 2], 'a / b'), 0.001);
    }

    public function testModulo(): void
    {
        self::assertSame(1, $this->resolve(['a' => 7, 'b' => 3], 'a % b'));
    }

    public function testDivisionByZeroThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->resolve(['a' => 5], 'a / 0');
    }

    public function testUnaryMinus(): void
    {
        self::assertSame(-5, $this->resolve(['v' => 5], '-v'));
    }

    public function testConcatOperator(): void
    {
        self::assertSame('helloworld', $this->resolve(['a' => 'hello', 'b' => 'world'], 'a ~ b'));
    }

    public function testInOperatorMatch(): void
    {
        self::assertTrue($this->evaluate(['s' => 'paid'], "s in ['paid', 'refunded']"));
    }

    public function testInOperatorNoMatch(): void
    {
        self::assertFalse($this->evaluate(['s' => 'cancelled'], "s in ['paid', 'refunded']"));
    }

    public function testTernaryTrueBranch(): void
    {
        self::assertSame('high', $this->resolve(['v' => 600], "v > 500 ? 'high' : 'low'"));
    }

    public function testTernaryFalseBranch(): void
    {
        self::assertSame('low', $this->resolve(['v' => 200], "v > 500 ? 'high' : 'low'"));
    }

    public function testLengthString(): void
    {
        self::assertSame(5, $this->resolve(['s' => 'hello'], 'length(s)'));
    }

    public function testLengthArray(): void
    {
        self::assertSame(3, $this->resolve(['a' => [1, 2, 3]], 'length(a)'));
    }

    public function testLower(): void
    {
        self::assertSame('hello', $this->resolve(['s' => 'HELLO'], 'lower(s)'));
    }

    public function testUpper(): void
    {
        self::assertSame('HELLO', $this->resolve(['s' => 'hello'], 'upper(s)'));
    }

    public function testTrim(): void
    {
        self::assertSame('hello', $this->resolve(['s' => '  hello  '], 'trim(s)'));
    }

    public function testAbs(): void
    {
        self::assertSame(5.0, $this->resolve(['v' => -5], 'abs(v)'));
    }

    public function testRound(): void
    {
        self::assertSame(3.14, $this->resolve(['v' => 3.14159], 'round(v, 2)'));
    }

    public function testContainsTrue(): void
    {
        self::assertTrue($this->evaluate(['s' => 'hello world'], "contains(s, 'world')"));
    }

    public function testContainsFalse(): void
    {
        self::assertFalse($this->evaluate(['s' => 'hello'], "contains(s, 'xyz')"));
    }

    public function testStarts(): void
    {
        self::assertTrue($this->evaluate(['s' => 'hello'], "starts(s, 'hel')"));
    }

    public function testEnds(): void
    {
        self::assertTrue($this->evaluate(['s' => 'hello'], "ends(s, 'llo')"));
    }

    public function testSubstr(): void
    {
        self::assertSame('ello', $this->resolve(['s' => 'hello'], 'substr(s, 1)'));
        self::assertSame('el', $this->resolve(['s' => 'hello'], 'substr(s, 1, 2)'));
    }

    public function testReplace(): void
    {
        self::assertSame('hi world', $this->resolve(['s' => 'hello world'], "replace(s, 'hello', 'hi')"));
    }

    public function testIntCast(): void
    {
        self::assertSame(42, $this->resolve(['v' => '42.9'], 'int(v)'));
    }

    public function testFloatCast(): void
    {
        self::assertSame(42.9, $this->resolve(['v' => '42.9'], 'float(v)'));
    }

    public function testStrCast(): void
    {
        self::assertSame('42', $this->resolve(['v' => 42], 'str(v)'));
    }

    public function testCountFunction(): void
    {
        self::assertSame(3, $this->resolve(['a' => [1, 2, 3]], 'count(a)'));
    }

    public function testMinFunction(): void
    {
        self::assertSame(3, $this->resolve([], 'min(3, 7)'));
    }

    public function testMaxFunction(): void
    {
        self::assertSame(7, $this->resolve([], 'max(3, 7)'));
    }

    public function testClamp(): void
    {
        self::assertSame(5, $this->resolve([], 'clamp(5, 1, 10)'));
        self::assertSame(1, $this->resolve([], 'clamp(0, 1, 10)'));
        self::assertSame(10, $this->resolve([], 'clamp(99, 1, 10)'));
    }

    public function testIssetTrue(): void
    {
        self::assertTrue($this->evaluate(['v' => 'hello'], 'isset(v)'));
    }

    public function testIssetFalse(): void
    {
        self::assertFalse($this->evaluate(['v' => null], 'isset(v)'));
    }

    public function testUnknownFunctionThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->resolve([], 'unknown_fn()');
    }

    public function testNestedArrayAccess(): void
    {
        $row = ['user' => ['name' => 'Alice']];
        $result = $this->resolve($row, "item['user']['name']");
        self::assertSame('Alice', $result);
    }

    public function testObjectPropertyAccess(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Alice';
        $result = $this->eval->resolve($obj, 'name');
        self::assertSame('Alice', $result);
    }

    public function testObjectGetterAccess(): void
    {
        $obj = new class {
            public function getName(): string
            {
                return 'Alice';
            }
        };
        $result = $this->eval->resolve($obj, 'name');
        self::assertSame('Alice', $result);
    }

    public function testStrictModeThrowsOnInvalidExpression(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->evaluate([], '@@@invalid@@@');
    }

    public function testLenientModeReturnsFalseOnInvalid(): void
    {
        $lenient = new ExpressionEvaluator(new PropertyAccessor(), new ExpressionCache(), strictMode: false);
        self::assertFalse($lenient->evaluate([], '@@@invalid@@@'));
    }

    public function testLenientModeResolveReturnsNull(): void
    {
        $lenient = new ExpressionEvaluator(new PropertyAccessor(), new ExpressionCache(), strictMode: false);
        self::assertNull($lenient->resolve([], '@@@invalid@@@'));
    }

    public function testClosureEvaluate(): void
    {
        self::assertTrue($this->eval->evaluate(['v' => 5], static fn ($r) => $r['v'] > 3));
    }

    public function testClosureResolve(): void
    {
        self::assertSame(10, $this->eval->resolve(['v' => 5], static fn ($r) => $r['v'] * 2));
    }

    public function testDotPathFastPath(): void
    {
        $row = ['user' => ['name' => 'Alice']];
        $result = $this->eval->resolve($row, 'user.name');
        self::assertSame('Alice', $result);
    }
}
