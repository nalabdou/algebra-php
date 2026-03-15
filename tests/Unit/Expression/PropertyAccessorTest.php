<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Expression;

use Nalabdou\Algebra\Expression\PropertyAccessor;
use PHPUnit\Framework\TestCase;

final class PropertyAccessorTest extends TestCase
{
    private PropertyAccessor $accessor;

    protected function setUp(): void
    {
        $this->accessor = new PropertyAccessor();
    }

    public function testGetFlatArray(): void
    {
        self::assertSame('paid', $this->accessor->get(['status' => 'paid'], 'status'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        self::assertNull($this->accessor->get(['id' => 1], 'missing'));
    }

    public function testGetNestedDotPath(): void
    {
        $row = ['user' => ['name' => 'Alice']];
        self::assertSame('Alice', $this->accessor->get($row, 'user.name'));
    }

    public function testGetDeepNestedDotPath(): void
    {
        $row = ['a' => ['b' => ['c' => 42]]];
        self::assertSame(42, $this->accessor->get($row, 'a.b.c'));
    }

    public function testGetReturnsNullForMissingNestedKey(): void
    {
        self::assertNull($this->accessor->get(['user' => ['id' => 1]], 'user.missing'));
    }

    public function testGetReturnsNullForNullIntermediate(): void
    {
        self::assertNull($this->accessor->get(['user' => null], 'user.name'));
    }

    public function testGetFromStdclass(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Alice';
        self::assertSame('Alice', $this->accessor->get($obj, 'name'));
    }

    public function testGetFromObjectWithGetter(): void
    {
        $obj = new class {
            public function getName(): string
            {
                return 'Alice';
            }
        };
        self::assertSame('Alice', $this->accessor->get($obj, 'name'));
    }

    public function testGetFromObjectWithIsGetter(): void
    {
        $obj = new class {
            public function isActive(): bool
            {
                return true;
            }
        };
        self::assertTrue($this->accessor->get($obj, 'active'));
    }

    public function testGetFromObjectWithHasGetter(): void
    {
        $obj = new class {
            public function hasRole(): bool
            {
                return true;
            }
        };
        self::assertTrue($this->accessor->get($obj, 'role'));
    }

    public function testGetMissingObjectPropertyReturnsNull(): void
    {
        $obj = new \stdClass();
        self::assertNull($this->accessor->get($obj, 'nonexistent'));
    }

    public function testGetFromNestedObject(): void
    {
        $inner = new \stdClass();
        $inner->city = 'Paris';
        $outer = new \stdClass();
        $outer->addr = $inner;

        self::assertSame('Paris', $this->accessor->get($outer, 'addr.city'));
    }

    public function testGetFromScalarReturnsNull(): void
    {
        self::assertNull($this->accessor->get('string', 'key'));
        self::assertNull($this->accessor->get(42, 'key'));
        self::assertNull($this->accessor->get(null, 'key'));
    }

    public function testParseSimpleCondition(): void
    {
        [$left, $right] = $this->accessor->parseJoinCondition('userId=id');
        self::assertSame('userId', $left);
        self::assertSame('id', $right);
    }

    public function testParseConditionWithWhitespace(): void
    {
        [$left, $right] = $this->accessor->parseJoinCondition('  userId  =  id  ');
        self::assertSame('userId', $left);
        self::assertSame('id', $right);
    }

    public function testParseDotPathCondition(): void
    {
        [$left, $right] = $this->accessor->parseJoinCondition('order.userId=user.id');
        self::assertSame('order.userId', $left);
        self::assertSame('user.id', $right);
    }

    public function testParseConditionThrowsWithoutEquals(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->accessor->parseJoinCondition('userId:id');
    }
}
