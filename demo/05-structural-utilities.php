<?php

declare(strict_types=1);

/**
 * Demo 05 — Structural utilities.
 *
 * Shows reindex, pluck, distinct, chunk, fillGaps, transpose,
 * sample, rankBy, topN, bottomN, zip, crossJoin.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$products = [
    ['id' => 1, 'name' => 'Laptop',   'category' => 'tech',      'price' => 1200, 'stock' => 15],
    ['id' => 2, 'name' => 'Mouse',    'category' => 'tech',      'price' => 25, 'stock' => 200],
    ['id' => 3, 'name' => 'Desk',     'category' => 'furniture', 'price' => 450, 'stock' => 8],
    ['id' => 4, 'name' => 'Chair',    'category' => 'furniture', 'price' => 300, 'stock' => 12],
    ['id' => 5, 'name' => 'Monitor',  'category' => 'tech',      'price' => 600, 'stock' => 20],
    ['id' => 6, 'name' => 'Keyboard', 'category' => 'tech',      'price' => 80, 'stock' => 50],
    ['id' => 7, 'name' => 'Lamp',     'category' => 'furniture', 'price' => 60, 'stock' => 35],
];

echo "=== reindex — O(1) product lookup ===\n";
$productMap = Algebra::from($products)->reindex('id')->toArray();
echo "  Product #5: {$productMap[5]['name']} (€{$productMap[5]['price']})\n";
echo "  Product #3: {$productMap[3]['name']} (€{$productMap[3]['price']})\n";

echo "\n=== pluck — extract all product names ===\n";
$names = Algebra::from($products)->pluck('name')->toArray();
echo '  '.implode(', ', $names)."\n";

echo "\n=== distinct — unique categories ===\n";
$categories = Algebra::from($products)->pluck('category')->distinct('0')->toArray();
// Note: pluck returns scalars so we just use PHP array_unique
echo '  '.implode(', ', array_unique(Algebra::from($products)->pluck('category')->toArray()))."\n";

echo "\n=== topN / bottomN ===\n";
$top3 = Algebra::from($products)->topN(3, by: 'price')->toArray();
$bottom2 = Algebra::from($products)->bottomN(2, by: 'price')->toArray();

echo "  Top 3 by price:\n";
foreach ($top3 as $p) {
    printf("    %-10s €%d\n", $p['name'], $p['price']);
}

echo "  Bottom 2 by price:\n";
foreach ($bottom2 as $p) {
    printf("    %-10s €%d\n", $p['name'], $p['price']);
}

echo "\n=== rankBy — rank products by price ===\n";
$ranked = Algebra::from($products)->rankBy('price', direction: 'desc', as: 'priceRank')->toArray();
foreach ($ranked as $p) {
    printf("  #%d %-10s €%d\n", $p['priceRank'], $p['name'], $p['price']);
}

echo "\n=== chunk — product grid (3 per row) ===\n";
$grid = Algebra::from($products)->chunk(3)->toArray();
foreach ($grid as $i => $row) {
    $names = implode(' | ', array_map(static fn ($p) => $p['name'], $row));
    printf("  Row %d: %s\n", $i + 1, $names);
}

echo "\n=== fillGaps — sparse monthly sales ===\n";
$monthlySales = [
    ['month' => 'Jan', 'revenue' => 4200],
    ['month' => 'Mar', 'revenue' => 5100],
    // Feb missing
    ['month' => 'May', 'revenue' => 3800],
    // Apr and Jun missing
];

$complete = Algebra::from($monthlySales)
    ->fillGaps('month', ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], ['revenue' => 0])
    ->toArray();

foreach ($complete as $row) {
    $bar = $row['revenue'] > 0
        ? str_repeat('█', (int) round($row['revenue'] / 300))
        : '(gap filled)';
    printf("  %-4s €%5d %s\n", $row['month'], $row['revenue'], $bar);
}

echo "\n=== sample — random 3 products (reproducible) ===\n";
$sample = Algebra::from($products)->sample(3, seed: 99)->toArray();
foreach ($sample as $p) {
    printf("  %s\n", $p['name']);
}

echo "\n=== zip — pair labels with values ===\n";
$labels = [['label' => 'Revenue'], ['label' => 'Orders'], ['label' => 'Customers']];
$values = [['value' => 48_200], ['value' => 312], ['value' => 89]];

$paired = Algebra::from($labels)->zip($values)->toArray();
foreach ($paired as $row) {
    printf("  %-12s: %s\n", $row['label'], number_format($row['value']));
}

echo "\n=== crossJoin — all size × colour combinations ===\n";
$sizes = [['size' => 'S'], ['size' => 'M'], ['size' => 'L']];
$colours = [['colour' => 'Red'], ['colour' => 'Blue']];

$variants = Algebra::from($sizes)->crossJoin($colours)->toArray();
foreach ($variants as $v) {
    printf("  %s-%s\n", $v['size'], $v['colour']);
}
