<?php

declare(strict_types=1);

/**
 * Demo 01 — Basic filters and joins.
 *
 * Demonstrates the most common operations: where, innerJoin, leftJoin,
 * orderBy, limit, and the execution log.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$orders = [
    ['id' => 1,  'userId' => 10, 'status' => 'paid',      'amount' => 250.00, 'region' => 'Nord'],
    ['id' => 2,  'userId' => 20, 'status' => 'pending',   'amount' => 150.00, 'region' => 'Sud'],
    ['id' => 3,  'userId' => 10, 'status' => 'paid',      'amount' => 400.00, 'region' => 'Est'],
    ['id' => 4,  'userId' => 30, 'status' => 'cancelled', 'amount' => 80.00, 'region' => 'Nord'],
    ['id' => 5,  'userId' => 20, 'status' => 'paid',      'amount' => 500.00, 'region' => 'Sud'],
    ['id' => 6,  'userId' => 10, 'status' => 'pending',   'amount' => 120.00, 'region' => 'Ouest'],
    ['id' => 7,  'userId' => 30, 'status' => 'paid',      'amount' => 330.00, 'region' => 'Nord'],
    ['id' => 8,  'userId' => 40, 'status' => 'refunded',  'amount' => 90.00, 'region' => 'Est'],
    ['id' => 9,  'userId' => 20, 'status' => 'paid',      'amount' => 760.00, 'region' => 'Sud'],
    ['id' => 10, 'userId' => 40, 'status' => 'paid',      'amount' => 210.00, 'region' => 'Ouest'],
];

$users = [
    ['id' => 10, 'name' => 'Alice', 'email' => 'alice@example.com', 'tier' => 'vip'],
    ['id' => 20, 'name' => 'Bob',   'email' => 'bob@example.com',   'tier' => 'standard'],
    ['id' => 30, 'name' => 'Carol', 'email' => 'carol@example.com', 'tier' => 'premium'],
    ['id' => 40, 'name' => 'David', 'email' => 'david@example.com', 'tier' => 'standard'],
];

echo "=== Paid orders over €200 ===\n";

$paidHighValue = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->where(static fn ($r) => $r['amount'] > 200)   // closures also work
    ->orderBy('amount', 'desc')
    ->toArray();

foreach ($paidHighValue as $order) {
    printf("  Order #%d — €%.2f (%s)\n", $order['id'], $order['amount'], $order['region']);
}

echo "\n=== Paid orders with owner (INNER JOIN) ===\n";

$withOwner = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->orderBy('amount', 'desc')
    ->limit(5)
    ->toArray();

foreach ($withOwner as $row) {
    printf(
        "  %-8s (%s) — #%d €%.2f\n",
        $row['owner']['name'],
        $row['owner']['tier'],
        $row['id'],
        $row['amount']
    );
}

echo "\n=== All orders, owner or Guest (LEFT JOIN) ===\n";

$allWithOwner = Algebra::from($orders)
    ->leftJoin($users, on: 'userId=id', as: 'owner')
    ->limit(4)
    ->toArray();

foreach ($allWithOwner as $row) {
    $ownerName = $row['owner']['name'] ?? 'Guest';
    printf("  Order #%d — %s — €%.2f\n", $row['id'], $ownerName, $row['amount']);
}

echo "\n=== Pipeline branching (base reused) ===\n";

$base = Algebra::from($orders)->where("item['status'] == 'paid'");
$nordOrders = $base->where(static fn ($r) => 'Nord' === $r['region'])->toArray();
$top3 = $base->topN(3, by: 'amount')->toArray();

echo '  Paid orders in Nord: '.count($nordOrders)."\n";
echo "  Top 3 paid by amount:\n";
foreach ($top3 as $row) {
    printf("    #%d €%.2f\n", $row['id'], $row['amount']);
}

echo "\n=== Execution log ===\n";

$materialized = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'owner')
    ->orderBy('amount', 'desc')
    ->materialize();

foreach ($materialized->executionLog() as $step) {
    printf(
        "  %-50s %5.3fms  %d→%d rows\n",
        $step['signature'],
        $step['duration_ms'],
        $step['input_rows'],
        $step['output_rows']
    );
}
printf("  Total: %.3fms\n", $materialized->totalDurationMs());
