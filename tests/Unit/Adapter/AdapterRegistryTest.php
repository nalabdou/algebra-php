<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Adapter;

use Nalabdou\Algebra\Adapter\AdapterRegistry;
use Nalabdou\Algebra\Adapter\ArrayAdapter;
use Nalabdou\Algebra\Adapter\GeneratorAdapter;
use Nalabdou\Algebra\Adapter\TraversableAdapter;
use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Contract\AdapterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdapterRegistry::class)]
final class AdapterRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        Algebra::reset();
    }

    public function testRegistersThreeBuiltinsOnConstruction(): void
    {
        $registry = new AdapterRegistry();
        self::assertSame(3, $registry->count());
    }

    public function testBuiltinGeneratorAdapterPresent(): void
    {
        $gen = (static function () { yield 1; })();
        $found = (new AdapterRegistry())->find($gen);
        self::assertInstanceOf(GeneratorAdapter::class, $found);
    }

    public function testBuiltinTraversableAdapterPresent(): void
    {
        $found = (new AdapterRegistry())->find(new \ArrayObject([1, 2]));
        self::assertInstanceOf(TraversableAdapter::class, $found);
    }

    public function testBuiltinArrayAdapterPresent(): void
    {
        $found = (new AdapterRegistry())->find([1, 2, 3]);
        self::assertInstanceOf(ArrayAdapter::class, $found);
    }

    public function testRegisterCustomAdapterIncreasesCount(): void
    {
        $registry = new AdapterRegistry();
        $registry->register($this->makeAdapter(false));
        self::assertSame(4, $registry->count());
    }

    public function testCustomAdapterFoundByFind(): void
    {
        $registry = new AdapterRegistry();
        $adapter = $this->makeAdapter(true);
        $registry->register($adapter, priority: 50);

        $found = $registry->find('anything');
        self::assertSame($adapter, $found);
    }

    public function testHigherPriorityWinsOverLower(): void
    {
        $registry = new AdapterRegistry();
        $low = $this->makeAdapter(true, 'low');
        $high = $this->makeAdapter(true, 'high');

        $registry->register($low, priority: 10);
        $registry->register($high, priority: 99);

        $found = $registry->find('anything');
        self::assertSame($high, $found);
    }

    public function testCustomPriorityAboveBuiltinsWins(): void
    {
        $registry = new AdapterRegistry();
        $custom = $this->makeAdapter(true);
        $registry->register($custom, priority: 100);

        // Generator has priority 20 — custom at 100 wins
        $gen = (static function () { yield 1; })();
        $found = $registry->find($gen);
        self::assertSame($custom, $found);
    }

    public function testFindReturnsNullWhenNothingSupportsInput(): void
    {
        $registry = new AdapterRegistry();
        $result = $registry->find(new \stdClass());
        self::assertNull($result);
    }

    public function testAllReturnsAdaptersSortedByPriorityDescending(): void
    {
        $registry = new AdapterRegistry();
        $a = $this->makeAdapter(false, 'a');
        $b = $this->makeAdapter(false, 'b');
        $registry->register($a, priority: 5);
        $registry->register($b, priority: 50);

        $all = $registry->all();
        $bIndex = \array_search($b, $all, true);
        $aIndex = \array_search($a, $all, true);

        self::assertLessThan($aIndex, $bIndex, 'Higher priority should appear first');
    }

    public function testAllRebuildsAfterNewRegistration(): void
    {
        $registry = new AdapterRegistry();
        $first = $registry->all();

        $registry->register($this->makeAdapter(false), priority: 999);
        $second = $registry->all();

        self::assertCount(\count($first) + 1, $second);
    }

    public function testAlgebraAdaptersSingletonHasBuiltins(): void
    {
        $registry = Algebra::adapters();
        self::assertSame(3, $registry->count());
    }

    public function testAlgebraAdaptersIsSingleton(): void
    {
        self::assertSame(Algebra::adapters(), Algebra::adapters());
    }

    public function testAlgebraResetClearsAdapterRegistry(): void
    {
        $before = Algebra::adapters();
        Algebra::reset();
        $after = Algebra::adapters();
        self::assertNotSame($before, $after);
    }

    public function testAlgebraFromUsesRegisteredCustomAdapter(): void
    {
        $rows = [['id' => 1], ['id' => 2]];

        $adapter = new class($rows) implements AdapterInterface {
            public function __construct(private readonly array $rows)
            {
            }

            public function supports(mixed $input): bool
            {
                return 'my_source' === $input;
            }

            public function toArray(mixed $input): array
            {
                return $this->rows;
            }
        };

        Algebra::adapters()->register($adapter, priority: 50);

        $result = Algebra::from('my_source')->toArray();
        self::assertSame($rows, $result);
    }

    public function testAlgebraFromCustomAdapterBeforeFactoryIsCalled(): void
    {
        // Register BEFORE factory() is ever called — should still work
        Algebra::adapters()->register(
            new class implements AdapterInterface {
                public function supports(mixed $input): bool
                {
                    return 'test_key' === $input;
                }

                public function toArray(mixed $input): array
                {
                    return [['v' => 42]];
                }
            },
            priority: 50
        );

        $result = Algebra::from('test_key')->toArray();
        self::assertSame(42, $result[0]['v']);
    }

    private function makeAdapter(bool $supports, string $tag = 'anon'): AdapterInterface
    {
        return new class($supports, $tag) implements AdapterInterface {
            public function __construct(
                private readonly bool $s,
                private readonly string $tag,
            ) {
            }

            public function supports(mixed $input): bool
            {
                return $this->s;
            }

            public function toArray(mixed $input): array
            {
                return [];
            }
        };
    }
}
