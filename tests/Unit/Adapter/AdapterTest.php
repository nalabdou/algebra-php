<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Adapter;

use Nalabdou\Algebra\Adapter\ArrayAdapter;
use Nalabdou\Algebra\Adapter\GeneratorAdapter;
use Nalabdou\Algebra\Adapter\TraversableAdapter;
use Nalabdou\Algebra\Algebra;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayAdapter::class)]
#[CoversClass(GeneratorAdapter::class)]
#[CoversClass(TraversableAdapter::class)]
final class AdapterTest extends TestCase
{
    protected function setUp(): void
    {
        Algebra::reset();
    }

    public function testArrayAdapterSupportsArrays(): void
    {
        $adapter = new ArrayAdapter();
        self::assertTrue($adapter->supports([]));
        self::assertTrue($adapter->supports([1, 2, 3]));
    }

    public function testArrayAdapterRejectsNonArray(): void
    {
        $adapter = new ArrayAdapter();
        self::assertFalse($adapter->supports('string'));
        self::assertFalse($adapter->supports(new \stdClass()));
    }

    public function testArrayAdapterReindexesToZeroBased(): void
    {
        $result = (new ArrayAdapter())->toArray([5 => ['id' => 5], 10 => ['id' => 10]]);
        self::assertArrayHasKey(0, $result);
        self::assertArrayHasKey(1, $result);
        self::assertArrayNotHasKey(5, $result);
    }

    public function testArrayAdapterPreservesValues(): void
    {
        $result = (new ArrayAdapter())->toArray([['id' => 1], ['id' => 2]]);
        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['id']);
    }

    public function testAlgebraFromPlainArray(): void
    {
        self::assertCount(2, Algebra::from([['id' => 1], ['id' => 2]])->toArray());
    }

    public function testGeneratorAdapterSupportsGenerator(): void
    {
        $gen = (static function () { yield 1; })();
        self::assertTrue((new GeneratorAdapter())->supports($gen));
    }

    public function testGeneratorAdapterRejectsArrayObject(): void
    {
        self::assertFalse((new GeneratorAdapter())->supports(new \ArrayObject()));
    }

    public function testGeneratorAdapterMaterialisesToArray(): void
    {
        $gen = (static function () {
            yield ['id' => 1];
            yield ['id' => 2];
            yield ['id' => 3];
        })();

        $result = (new GeneratorAdapter())->toArray($gen);
        self::assertCount(3, $result);
        self::assertSame(1, $result[0]['id']);
    }

    public function testGeneratorAdapterEmptyGenerator(): void
    {
        $gen = (static function () {
            return;
            yield;
        })();
        self::assertSame([], (new GeneratorAdapter())->toArray($gen));
    }

    public function testAlgebraFromGenerator(): void
    {
        $gen = (static function () {
            yield ['v' => 10];
            yield ['v' => 20];
        })();

        $result = Algebra::from($gen)->where("item['v'] > 15")->toArray();
        self::assertCount(1, $result);
    }

    public function testTraversableAdapterSupportsArrayObject(): void
    {
        self::assertTrue((new TraversableAdapter())->supports(new \ArrayObject()));
    }

    public function testTraversableAdapterSupportsSplFixedArray(): void
    {
        self::assertTrue((new TraversableAdapter())->supports(new \SplFixedArray(3)));
    }

    public function testTraversableAdapterRejectsGenerator(): void
    {
        $gen = (static function () { yield 1; })();
        self::assertFalse((new TraversableAdapter())->supports($gen));
    }

    public function testTraversableAdapterRejectsPlainArray(): void
    {
        self::assertFalse((new TraversableAdapter())->supports([]));
    }

    public function testTraversableAdapterConvertsArrayObject(): void
    {
        $result = (new TraversableAdapter())->toArray(new \ArrayObject([['id' => 1], ['id' => 2]]));
        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['id']);
    }

    public function testTraversableAdapterConvertsSplFixedArray(): void
    {
        $fixed = new \SplFixedArray(2);
        $fixed[0] = ['id' => 1];
        $fixed[1] = ['id' => 2];

        self::assertCount(2, (new TraversableAdapter())->toArray($fixed));
    }

    public function testTraversableAdapterCustomIterator(): void
    {
        $iter = new class implements \Iterator {
            private int $pos = 0;
            private array $data = [['id' => 1], ['id' => 2]];

            public function current(): mixed
            {
                return $this->data[$this->pos];
            }

            public function key(): int
            {
                return $this->pos;
            }

            public function next(): void
            {
                ++$this->pos;
            }

            public function rewind(): void
            {
                $this->pos = 0;
            }

            public function valid(): bool
            {
                return $this->pos < \count($this->data);
            }
        };

        self::assertTrue((new TraversableAdapter())->supports($iter));
        self::assertCount(2, (new TraversableAdapter())->toArray($iter));
    }

    public function testAlgebraFromArrayObject(): void
    {
        self::assertSame(2, Algebra::from(new \ArrayObject([['id' => 1], ['id' => 2]]))->count());
    }
}
