<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Aggregate\Math;

use Nalabdou\Algebra\Contract\AggregateInterface;

/**
 * PERCENTILE — value at the Nth percentile (nearest-rank method).
 *
 * Used via spec DSL: `'percentile(amount, 0.9)'` → 90th percentile.
 * As a standalone registered aggregate, defaults to p50 (median).
 *
 * @throws \InvalidArgumentException When quantile is outside [0.0, 1.0].
 */
final class PercentileAggregate implements AggregateInterface
{
    public function __construct(private readonly float $quantile = 0.5)
    {
        if ($this->quantile < 0.0 || $this->quantile > 1.0) {
            throw new \InvalidArgumentException("Percentile quantile must be between 0.0 and 1.0, got {$this->quantile}.");
        }
    }

    public function name(): string
    {
        return 'percentile';
    }

    public function compute(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }

        \sort($values);
        $index = (int) \ceil($this->quantile * \count($values)) - 1;

        return (float) $values[\max(0, $index)];
    }
}
