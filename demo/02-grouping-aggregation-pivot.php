<?php

declare(strict_types=1);

/**
 * Demo 02 — Grouping, aggregation, and pivot.
 *
 * Shows groupBy, all aggregate functions, tally, and pivot.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$orders = [
    ['id' => 1,  'status' => 'paid',    'amount' => 250, 'region' => 'Nord', 'month' => 'Jan'],
    ['id' => 2,  'status' => 'pending', 'amount' => 150, 'region' => 'Sud',  'month' => 'Jan'],
    ['id' => 3,  'status' => 'paid',    'amount' => 400, 'region' => 'Est',  'month' => 'Feb'],
    ['id' => 4,  'status' => 'cancelled', 'amount' => 80, 'region' => 'Nord', 'month' => 'Feb'],
    ['id' => 5,  'status' => 'paid',    'amount' => 500, 'region' => 'Sud',  'month' => 'Mar'],
    ['id' => 6,  'status' => 'pending', 'amount' => 120, 'region' => 'Ouest', 'month' => 'Mar'],
    ['id' => 7,  'status' => 'paid',    'amount' => 330, 'region' => 'Nord', 'month' => 'Jan'],
    ['id' => 8,  'status' => 'refunded', 'amount' => 90, 'region' => 'Est',  'month' => 'Feb'],
    ['id' => 9,  'status' => 'paid',    'amount' => 760, 'region' => 'Sud',  'month' => 'Mar'],
    ['id' => 10, 'status' => 'paid',    'amount' => 210, 'region' => 'Ouest', 'month' => 'Jan'],
];

echo "=== Revenue by status (group_by + aggregate) ===\n";

$byStatus = Algebra::from($orders)
    ->groupBy('status')
    ->aggregate([
        'orders' => 'count(*)',
        'revenue' => 'sum(amount)',
        'avg' => 'avg(amount)',
        'min' => 'min(amount)',
        'max' => 'max(amount)',
        'median' => 'median(amount)',
    ])
    ->orderBy('revenue', 'desc')
    ->toArray();

printf("  %-12s %6s %8s %8s %8s %8s %8s\n", 'Status', 'Orders', 'Revenue', 'Avg', 'Min', 'Max', 'Median');
printf("  %s\n", str_repeat('-', 64));
foreach ($byStatus as $row) {
    printf(
        "  %-12s %6d %8.2f %8.2f %8.2f %8.2f %8.2f\n",
        $row['_group'],
        $row['orders'],
        $row['revenue'],
        $row['avg'],
        $row['min'],
        $row['max'],
        $row['median']
    );
}

echo "\n=== Status distribution (tally) ===\n";
$tally = Algebra::from($orders)->tally('status')->toArray();
foreach ($tally as $status => $count) {
    $bar = str_repeat('█', $count);
    printf("  %-12s %2d %s\n", $status, $count, $bar);
}

echo "\n=== Revenue matrix: month × region (pivot) ===\n";

$matrix = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->pivot(rows: 'month', cols: 'region', value: 'amount')
    ->toArray();

$cols = ['Nord', 'Sud', 'Est', 'Ouest'];
printf('  %-6s', 'Month');
foreach ($cols as $col) {
    printf(' %8s', $col);
}
echo "\n  ".str_repeat('-', 38)."\n";

foreach ($matrix as $row) {
    printf('  %-6s', $row['_row']);
    foreach ($cols as $col) {
        printf(' %8s', isset($row[$col]) ? number_format($row[$col], 0) : '—');
    }
    echo "\n";
}

echo "\n=== High-value vs standard orders (partition) ===\n";

$partition = Algebra::from($orders)->partition("item['amount'] > 300");
printf("  High-value (>€300): %d orders\n", $partition->passCount());
printf("  Standard (≤€300):   %d orders\n", $partition->failCount());
printf("  Pass rate:          %.0f%%\n", $partition->passRate() * 100);

echo "\n=== Statistical analysis of paid order amounts ===\n";

$stats = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->aggregate([
        'count' => 'count(*)',
        'sum' => 'sum(amount)',
        'avg' => 'avg(amount)',
        'median' => 'median(amount)',
        'stddev' => 'stddev(amount)',
        'p25' => 'percentile(amount, 0.25)',
        'p75' => 'percentile(amount, 0.75)',
        'p90' => 'percentile(amount, 0.90)',
    ])
    ->toArray()[0];

printf("  Count:   %d orders\n", $stats['count']);
printf("  Sum:     €%.2f\n", $stats['sum']);
printf("  Avg:     €%.2f\n", $stats['avg']);
printf("  Median:  €%.2f\n", $stats['median']);
printf("  Std dev: €%.2f\n", $stats['stddev']);
printf("  P25/P75: €%.2f / €%.2f\n", $stats['p25'], $stats['p75']);
printf("  P90:     €%.2f\n", $stats['p90']);
