<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Operation\Aggregate;

use Nalabdou\Algebra\Contract\OperationInterface;
use Nalabdou\Algebra\Expression\PropertyAccessor;

/**
 * TALLY — count occurrences of each distinct value of a field.
 *
 * Returns an associative array sorted by count **descending**:
 * `['paid' => 42, 'pending' => 12, 'cancelled' => 3]`
 *
 * ```php
 * Algebra::from($orders)->tally('status')->toArray();
 * ```
 */
final class TallyOperation implements OperationInterface
{
    public function __construct(
        private readonly string $field,
        private readonly PropertyAccessor $accessor,
    ) {
    }

    public function execute(array $rows): array
    {
        $tally = [];

        foreach ($rows as $row) {
            $value = (string) $this->accessor->get($row, $this->field);
            isset($tally[$value]) ? $tally[$value]++ : $tally[$value] = 1;
        }

        \arsort($tally);

        return $tally;
    }

    public function signature(): string
    {
        return "tally(field={$this->field})";
    }

    public function selectivity(): float
    {
        return 0.1;
    }
}
