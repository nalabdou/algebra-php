<?php

declare(strict_types=1);

namespace Nalabdou\Algebra\Tests\Fixtures\Generator;

/**
 * Generates typed synthetic datasets for tests and benchmarks.
 *
 * All methods are static and accept an optional seed for reproducibility.
 * Pass the same seed across a test suite to get deterministic data.
 */
final class DataGenerator
{
    private const STATUSES = ['paid', 'pending', 'cancelled', 'refunded'];
    private const REGIONS = ['Nord', 'Sud', 'Est', 'Ouest'];
    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    private const TIERS = ['standard', 'premium', 'vip'];
    private const CATEGORIES = ['tech', 'furniture', 'clothing', 'food', 'books'];

    /**
     * Generate synthetic orders with referential integrity to N users.
     *
     * @param int      $count     number of orders to generate
     * @param int      $userCount upper bound for userId (1 … $userCount)
     * @param int|null $seed      optional seed for reproducibility
     *
     * @return array<int, array{id:int, userId:int, status:string, amount:float, region:string, month:string}>
     */
    public static function orders(int $count, int $userCount = 50, ?int $seed = null): array
    {
        if (null !== $seed) {
            \srand($seed);
        }

        return \array_map(static fn (int $i): array => [
            'id' => $i,
            'userId' => \random_int(1, $userCount),
            'status' => self::STATUSES[\random_int(0, 3)],
            'amount' => \round(\random_int(500, 500_000) / 100, 2),
            'region' => self::REGIONS[\random_int(0, 3)],
            'month' => self::MONTHS[\random_int(0, 11)],
        ], \range(1, $count));
    }

    /**
     * Generate synthetic users.
     *
     * @return array<int, array{id:int, name:string, email:string, tier:string}>
     */
    public static function users(int $count, ?int $seed = null): array
    {
        if (null !== $seed) {
            \srand($seed);
        }

        return \array_map(static fn (int $i): array => [
            'id' => $i,
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
            'tier' => self::TIERS[\random_int(0, 2)],
        ], \range(1, $count));
    }

    /**
     * Generate monthly sales timeseries (all months × all regions).
     *
     * @return array<int, array{month:string, region:string, revenue:float, orders:int}>
     */
    public static function monthlySales(?int $seed = null): array
    {
        if (null !== $seed) {
            \srand($seed);
        }

        $rows = [];
        foreach (self::MONTHS as $month) {
            foreach (self::REGIONS as $region) {
                $rows[] = [
                    'month' => $month,
                    'region' => $region,
                    'revenue' => (float) \random_int(1_000, 10_000),
                    'orders' => \random_int(5, 100),
                ];
            }
        }

        return $rows;
    }

    /**
     * Generate a sparse monthly revenue series with intentional gaps.
     *
     * @param float $gapProbability Probability (0.0–1.0) that a month is missing.
     *
     * @return array<int, array{month:string, revenue:float}>
     */
    public static function sparseMonthlyRevenue(float $gapProbability = 0.3, ?int $seed = null): array
    {
        if (null !== $seed) {
            \srand($seed);
        }

        return \array_values(\array_filter(
            \array_map(
                static fn (string $month): ?array => \random_int(0, 100) / 100 > $gapProbability
                    ? ['month' => $month, 'revenue' => (float) \random_int(1_000, 10_000)]
                    : null,
                self::MONTHS
            ),
            static fn (?array $row): bool => null !== $row
        ));
    }

    /**
     * Generate two overlapping sets for set-operation tests.
     *
     * @return array{left: array<int, array{id:int}>, right: array<int, array{id:int}>}
     */
    public static function overlappingSets(int $size, float $overlapRatio = 0.5, ?int $seed = null): array
    {
        if (null !== $seed) {
            \srand($seed);
        }

        $overlap = (int) ($size * $overlapRatio);

        return [
            'left' => \array_map(static fn ($i) => ['id' => $i], \range(1, $size)),
            'right' => \array_map(static fn ($i) => ['id' => $i], \range($size - $overlap + 1, $size + $overlap)),
        ];
    }
}
