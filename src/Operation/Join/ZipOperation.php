<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Join;

use Nalabdou\Algebra\Contract\OperationInterface;

/**
 * ZIP — merge two collections by position (index 0 with 0, 1 with 1, …).
 *
 * Output length = `min(count(left), count(right))`.
 * No key matching is involved — pure positional pairing.
 *
 * ### Usage
 * ```php
 * Algebra::from($labels)->zip($values);
 * // → [{label:'Revenue', value:5400}, {label:'Orders', value:120}]
 * ```
 */
final class ZipOperation implements OperationInterface
{
    public function __construct(
        private readonly array $right,
        private readonly string $leftAs = '',
        private readonly string $rightAs = '',
    ) {
    }

    public function execute(array $rows): array
    {
        $left = \array_values($rows);
        $right = \array_values($this->right);
        $count = \min(\count($left), \count($right));
        $result = [];

        for ($i = 0; $i < $count; ++$i) {
            $l = $left[$i];
            $r = $right[$i];

            $result[] = match (true) {
                \is_array($l) && \is_array($r) => $this->mergeArrays($l, $r),
                !\is_array($l) && \is_array($r) => \array_merge([$this->leftAs ?: 'left' => $l], $r),
                \is_array($l) && !\is_array($r) => \array_merge($l, [$this->rightAs ?: 'right' => $r]),
                default => [
                    $this->leftAs ?: 'left' => $l,
                    $this->rightAs ?: 'right' => $r,
                ],
            };
        }

        return $result;
    }

    private function mergeArrays(array $left, array $right): array
    {
        if ('' === $this->leftAs && '' === $this->rightAs) {
            return \array_merge($left, $right);
        }

        $out = [];
        foreach ($left as $k => $v) {
            $out[$this->leftAs.$k] = $v;
        }
        foreach ($right as $k => $v) {
            $out[$this->rightAs.$k] = $v;
        }

        return $out;
    }

    public function signature(): string
    {
        return 'zip()';
    }

    public function selectivity(): float
    {
        return 1.0;
    }
}
