<?php

declare(strict_types=1);

/**
 * Benchmark — algebra-php performance numbers.
 *
 * Usage:
 *   php demo/benchmark.php [rows] [iterations]
 *   php demo/benchmark.php 5000 5
 *   make benchmark
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Tests\Fixtures\Generator\DataGenerator;

$rowCount = (int) ($argv[1] ?? 5_000);
$iterations = (int) ($argv[2] ?? 5);

echo "\nalgebra-php benchmark (zero dependencies)\n";
echo str_repeat('─', 64)."\n";
printf("  Rows per run : %s\n", number_format($rowCount));
printf("  Iterations   : %d\n\n", $iterations);

$orders = DataGenerator::orders($rowCount, userCount: 200, seed: 42);
$users = DataGenerator::users(200, seed: 42);

function bench(string $name, callable $fn, int $iterations): void
{
    // Warmup
    Algebra::reset();
    $fn();

    $times = [];
    $lastCount = 0;

    for ($i = 0; $i < $iterations; ++$i) {
        Algebra::reset();
        $start = hrtime(true);
        $result = $fn();
        $elapsed = (hrtime(true) - $start) / 1_000_000;
        $times[] = $elapsed;
        $lastCount = is_array($result) ? count($result) : 0;
    }

    $avg = array_sum($times) / count($times);
    $min = min($times);
    $max = max($times);
    $p95 = (static function () use ($times): float {
        $sorted = $times;
        sort($sorted);

        return $sorted[(int) ceil(count($sorted) * 0.95) - 1];
    })();

    printf(
        "  %-40s  avg:%7.2fms  p95:%7.2fms  rows:%s\n",
        $name,
        $avg,
        $p95,
        number_format($lastCount)
    );
}

echo "Individual operations:\n";

bench(
    'where — string expr (cached after warmup)',
    static fn () => Algebra::from($orders)->where("item['status'] == 'paid'")->toArray(),
    $iterations
);

bench(
    'where — closure',
    static fn () => Algebra::from($orders)->where(static fn ($r) => 'paid' === $r['status'])->toArray(),
    $iterations
);

bench(
    'where — in operator',
    static fn () => Algebra::from($orders)->where("status in ['paid', 'pending']")->toArray(),
    $iterations
);

bench(
    'where — complex and/or',
    static fn () => Algebra::from($orders)->where("status == 'paid' and amount > 100")->toArray(),
    $iterations
);

bench(
    'orderBy — single key',
    static fn () => Algebra::from($orders)->orderBy('amount', 'desc')->toArray(),
    $iterations
);

bench(
    'orderBy — multi key',
    static fn () => Algebra::from($orders)->orderBy([['status', 'asc'], ['amount', 'desc']])->toArray(),
    $iterations
);

bench(
    'innerJoin — hash-indexed',
    static fn () => Algebra::from($orders)->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'u')->toArray(),
    $iterations
);

bench(
    'groupBy + aggregate (sum/count)',
    static fn () => Algebra::from($orders)->groupBy('status')->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)'])->toArray(),
    $iterations
);

bench(
    'groupBy + aggregate (all 6)',
    static fn () => Algebra::from($orders)->groupBy('status')->aggregate([
        'count' => 'count(*)',
        'total' => 'sum(amount)',
        'avg' => 'avg(amount)',
        'min' => 'min(amount)',
        'max' => 'max(amount)',
        'median' => 'median(amount)',
    ])->toArray(),
    $iterations
);

bench(
    'window — running_sum',
    static fn () => Algebra::from($orders)->window('running_sum', field: 'amount', as: 'cum')->toArray(),
    $iterations
);

bench(
    'window — rank',
    static fn () => Algebra::from($orders)->window('rank', field: 'amount', as: 'rank')->toArray(),
    $iterations
);

bench(
    'movingAverage(window=7)',
    static fn () => Algebra::from($orders)->movingAverage(field: 'amount', window: 7, as: 'ma')->toArray(),
    $iterations
);

bench(
    'normalize',
    static fn () => Algebra::from($orders)->normalize(field: 'amount', as: 'score')->toArray(),
    $iterations
);

bench(
    'pivot',
    static fn () => Algebra::from($orders)->pivot(rows: 'month', cols: 'region', value: 'amount')->toArray(),
    $iterations
);

bench(
    'tally',
    static fn () => Algebra::from($orders)->tally('status')->toArray(),
    $iterations
);

bench(
    'topN(10)',
    static fn () => Algebra::from($orders)->topN(10, by: 'amount')->toArray(),
    $iterations
);

bench(
    'distinct',
    static fn () => Algebra::from($orders)->distinct('status')->toArray(),
    $iterations
);

bench(
    'reindex',
    static fn () => Algebra::from($users)->reindex('id')->toArray(),
    $iterations
);

echo "\nChained pipelines:\n";

bench(
    'where → innerJoin → orderBy',
    static fn () => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
        ->orderBy('amount', 'desc')
        ->toArray(),
    $iterations
);

bench(
    'where → groupBy → aggregate → sort',
    static fn () => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->groupBy('region')
        ->aggregate(['revenue' => 'sum(amount)', 'count' => 'count(*)'])
        ->orderBy('revenue', 'desc')
        ->toArray(),
    $iterations
);

bench(
    'full 7-op pipeline',
    static fn () => Algebra::from($orders)
        ->where("item['status'] == 'paid'")
        ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
        ->window('running_sum', field: 'amount', as: 'cumulative')
        ->groupBy('region')
        ->aggregate(['revenue' => 'sum(amount)', 'count' => 'count(*)'])
        ->orderBy('revenue', 'desc')
        ->limit(10)
        ->toArray(),
    $iterations
);

bench(
    'parallel (3 independent pipelines)',
    static fn () => Algebra::parallel([
        'paid' => Algebra::from($orders)->where("item['status'] == 'paid'"),
        'grouped' => Algebra::from($orders)->groupBy('status')->aggregate(['total' => 'sum(amount)']),
        'top10' => Algebra::from($orders)->topN(10, by: 'amount'),
    ]),
    $iterations
);

echo "\nExpression engine — string vs closure:\n";

bench('100 expr evaluations (string, cached)', static function () use ($orders): array {
    $results = [];
    for ($i = 0; $i < 100; ++$i) {
        $results[] = Algebra::from($orders)->where("item['status'] == 'paid'")->toArray();
    }

    return $results[0];
}, 3);

bench('100 expr evaluations (closure)', static function () use ($orders): array {
    $results = [];
    for ($i = 0; $i < 100; ++$i) {
        $results[] = Algebra::from($orders)->where(static fn ($r) => 'paid' === $r['status'])->toArray();
    }

    return $results[0];
}, 3);

echo "\n".str_repeat('─', 64)."\n";
printf("  Peak memory : %.1f MB\n", memory_get_peak_usage(true) / 1_024 / 1_024);
echo "\n";
