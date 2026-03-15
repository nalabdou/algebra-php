<?php

declare(strict_types=1);

/**
 * Demo 03 — Window functions.
 *
 * Shows all 11 window functions: running_sum, running_avg, running_diff,
 * row_number, rank, dense_rank, lag, lead, ntile, cume_dist,
 * plus movingAverage and normalize.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$dailyRevenue = [
    ['day' => 'Mon', 'region' => 'Nord', 'amount' => 1000],
    ['day' => 'Tue', 'region' => 'Nord', 'amount' => 1200],
    ['day' => 'Wed', 'region' => 'Nord', 'amount' => 800],
    ['day' => 'Thu', 'region' => 'Nord', 'amount' => 1500],
    ['day' => 'Fri', 'region' => 'Nord', 'amount' => 2000],
    ['day' => 'Mon', 'region' => 'Sud',  'amount' => 600],
    ['day' => 'Tue', 'region' => 'Sud',  'amount' => 900],
    ['day' => 'Wed', 'region' => 'Sud',  'amount' => 750],
];

echo "=== Running sum and daily change ===\n";

$withRunning = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->window('running_sum', field: 'amount', as: 'cumulative')
    ->window('running_diff', field: 'amount', as: 'delta')
    ->toArray();

printf("  %-6s %8s %10s %8s\n", 'Day', 'Amount', 'Cumulative', 'Delta');
printf("  %s\n", str_repeat('-', 38));
foreach ($withRunning as $row) {
    printf(
        "  %-6s %8d %10.0f %8s\n",
        $row['day'],
        $row['amount'],
        $row['cumulative'],
        null !== $row['delta'] ? sprintf('%+d', $row['delta']) : '—'
    );
}

echo "\n=== Revenue ranking ===\n";

$ranked = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->window('rank', field: 'amount', as: 'rank')
    ->window('dense_rank', field: 'amount', as: 'dense_rank')
    ->window('row_number', field: 'amount', as: 'row_num')
    ->orderBy('rank', 'asc')
    ->toArray();

printf("  %-6s %8s %6s %11s %8s\n", 'Day', 'Amount', 'Rank', 'DenseRank', 'RowNum');
printf("  %s\n", str_repeat('-', 46));
foreach ($ranked as $row) {
    printf(
        "  %-6s %8d %6d %11d %8d\n",
        $row['day'],
        $row['amount'],
        $row['rank'],
        $row['dense_rank'],
        $row['row_num']
    );
}

echo "\n=== Lag and lead (previous / next day) ===\n";

$withLagLead = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->window('lag', field: 'amount', as: 'prev_day', offset: 1)
    ->window('lead', field: 'amount', as: 'next_day', offset: 1)
    ->toArray();

printf("  %-6s %8s %9s %9s\n", 'Day', 'Amount', 'PrevDay', 'NextDay');
printf("  %s\n", str_repeat('-', 38));
foreach ($withLagLead as $row) {
    printf(
        "  %-6s %8d %9s %9s\n",
        $row['day'],
        $row['amount'],
        $row['prev_day'] ?? '—',
        $row['next_day'] ?? '—'
    );
}

echo "\n=== Quartiles and cumulative distribution ===\n";

$withTiles = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->orderBy('amount', 'asc')
    ->window('ntile', field: 'amount', as: 'quartile', buckets: 4)
    ->window('cume_dist', field: 'amount', as: 'pct')
    ->toArray();

printf("  %-6s %8s %9s %8s\n", 'Day', 'Amount', 'Quartile', 'CumePct');
printf("  %s\n", str_repeat('-', 36));
foreach ($withTiles as $row) {
    printf(
        "  %-6s %8d %9d %7.1f%%\n",
        $row['day'],
        $row['amount'],
        $row['quartile'],
        $row['pct'] * 100
    );
}

echo "\n=== 3-day moving average ===\n";

$withMa = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->movingAverage(field: 'amount', window: 3, as: 'ma_3d')
    ->toArray();

printf("  %-6s %8s %10s\n", 'Day', 'Amount', 'MA(3d)');
printf("  %s\n", str_repeat('-', 28));
foreach ($withMa as $row) {
    printf(
        "  %-6s %8d %10s\n",
        $row['day'],
        $row['amount'],
        null !== $row['ma_3d'] ? number_format($row['ma_3d'], 2) : '—'
    );
}

echo "\n=== Running sum per region (partitioned window) ===\n";

$partitioned = Algebra::from($dailyRevenue)
    ->window('running_sum', field: 'amount', as: 'regional_total', partitionBy: 'region')
    ->toArray();

foreach ($partitioned as $row) {
    printf(
        "  %-4s %-6s €%6d → €%6d total\n",
        $row['region'],
        $row['day'],
        $row['amount'],
        $row['regional_total']
    );
}

echo "\n=== Normalized revenue score (0.0 – 1.0) ===\n";

$normalized = Algebra::from($dailyRevenue)
    ->where(static fn ($r) => 'Nord' === $r['region'])
    ->normalize(field: 'amount', as: 'score')
    ->toArray();

printf("  %-6s %8s %8s\n", 'Day', 'Amount', 'Score');
printf("  %s\n", str_repeat('-', 26));
foreach ($normalized as $row) {
    $bar = str_repeat('▓', (int) round($row['score'] * 20));
    printf("  %-6s %8d %8.2f %s\n", $row['day'], $row['amount'], $row['score'], $bar);
}
