<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Adapter;

use Nalabdou\Algebra\Contract\AdapterInterface;

/**
 * PHP generator adapter.
 *
 * Generators are consumed once — the result is materialized into an array
 * so the pipeline can be replayed or re-materialized.
 *
 * ```php
 * function streamOrders(): \Generator {
 *     yield ['id' => 1, 'amount' => 250];
 *     yield ['id' => 2, 'amount' => 150];
 * }
 *
 * Algebra::from(streamOrders())->where("item['amount'] > 100")->toArray();
 * ```
 */
final class GeneratorAdapter implements AdapterInterface
{
    public function supports(mixed $input): bool
    {
        return $input instanceof \Generator;
    }

    /**@param \Generator $input */
    public function toArray(mixed $input): array
    {
        return \iterator_to_array($input, preserve_keys: false);
    }
}
