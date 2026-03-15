<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Unit\Operation\Set;

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Operation\Set\DiffByOperation;
use Nalabdou\Algebra\Operation\Set\ExceptOperation;
use Nalabdou\Algebra\Operation\Set\IntersectOperation;
use Nalabdou\Algebra\Operation\Set\UnionOperation;
use PHPUnit\Framework\TestCase;

final class SetOperationTest extends TestCase
{
    private \Nalabdou\Algebra\Expression\PropertyAccessor $accessor;

    protected function setUp(): void
    {
        Algebra::reset();
        $this->accessor = Algebra::accessor();
    }

    public function testIntersectKeepsOnlySharedRows(): void
    {
        $a = [['id' => 1], ['id' => 2], ['id' => 3]];
        $b = [['id' => 2], ['id' => 3], ['id' => 4]];

        $result = (new IntersectOperation($b, 'id', $this->accessor))->execute($a);

        self::assertSame([2, 3], \array_column($result, 'id'));
    }

    public function testIntersectEmptyRightReturnsEmpty(): void
    {
        $a = [['id' => 1], ['id' => 2]];

        $result = (new IntersectOperation([], 'id', $this->accessor))->execute($a);

        self::assertEmpty($result);
    }

    public function testIntersectNoOverlapReturnsEmpty(): void
    {
        $a = [['id' => 1], ['id' => 2]];
        $b = [['id' => 3], ['id' => 4]];

        $result = (new IntersectOperation($b, 'id', $this->accessor))->execute($a);

        self::assertEmpty($result);
    }

    public function testIntersectSignature(): void
    {
        $op = new IntersectOperation([], 'id', $this->accessor);
        self::assertStringContainsString('intersect', $op->signature());
    }

    public function testIntersectSelectivity(): void
    {
        $op = new IntersectOperation([], 'id', $this->accessor);
        self::assertIsFloat($op->selectivity());
    }

    public function testExceptRemovesRowsPresentInRight(): void
    {
        $all = [['id' => 1], ['id' => 2], ['id' => 3]];
        $dismissed = [['id' => 2]];

        $result = (new ExceptOperation($dismissed, 'id', $this->accessor))->execute($all);

        self::assertSame([1, 3], \array_column($result, 'id'));
    }

    public function testExceptEmptyRightReturnsAll(): void
    {
        $all = [['id' => 1], ['id' => 2]];
        $result = (new ExceptOperation([], 'id', $this->accessor))->execute($all);

        self::assertCount(2, $result);
    }

    public function testExceptAllExcludedReturnsEmpty(): void
    {
        $rows = [['id' => 1], ['id' => 2]];
        $excl = [['id' => 1], ['id' => 2]];

        $result = (new ExceptOperation($excl, 'id', $this->accessor))->execute($rows);

        self::assertEmpty($result);
    }

    public function testUnionMergesAndDeduplicates(): void
    {
        $a = [['id' => 1, 'src' => 'a'], ['id' => 2, 'src' => 'a']];
        $b = [['id' => 2, 'src' => 'b'], ['id' => 3, 'src' => 'b']];

        $result = (new UnionOperation($b, 'id', $this->accessor))->execute($a);

        self::assertCount(3, $result);
        self::assertSame([1, 2, 3], \array_column($result, 'id'));
    }

    public function testUnionFirstOccurrenceWins(): void
    {
        $a = [['id' => 1, 'source' => 'a']];
        $b = [['id' => 1, 'source' => 'b']];

        $result = (new UnionOperation($b, 'id', $this->accessor))->execute($a);

        self::assertSame('a', $result[0]['source']);
    }

    public function testUnionNullByUsesSortRegular(): void
    {
        $a = [['id' => 1]];
        $b = [['id' => 1], ['id' => 2]];

        $result = (new UnionOperation($b, null, $this->accessor))->execute($a);

        self::assertCount(2, $result); // deduped by value
    }

    public function testUnionSelectivityGreaterThanOne(): void
    {
        $op = new UnionOperation([], null, $this->accessor);
        self::assertGreaterThan(1.0, $op->selectivity());
    }

    public function testSymmetricDiffReturnsExclusiveRows(): void
    {
        $a = [['id' => 1], ['id' => 2], ['id' => 3]];
        $b = [['id' => 2], ['id' => 3], ['id' => 4]];

        $result = (new DiffByOperation($b, 'id', $this->accessor))->execute($a);
        $ids = \array_column($result, 'id');
        \sort($ids);

        self::assertSame([1, 4], $ids);
    }

    public function testSymmetricDiffEmptyWhenSetsEqual(): void
    {
        $a = [['id' => 1], ['id' => 2]];
        $b = [['id' => 1], ['id' => 2]];

        $result = (new DiffByOperation($b, 'id', $this->accessor))->execute($a);

        self::assertEmpty($result);
    }

    public function testSymmetricDiffNoOverlapReturnsAll(): void
    {
        $a = [['id' => 1], ['id' => 2]];
        $b = [['id' => 3], ['id' => 4]];

        $result = (new DiffByOperation($b, 'id', $this->accessor))->execute($a);

        self::assertCount(4, $result);
    }

    public function testSymmetricDiffSignature(): void
    {
        $op = new DiffByOperation([], 'id', $this->accessor);
        self::assertStringContainsString('symmetric_diff', $op->signature());
    }
}
