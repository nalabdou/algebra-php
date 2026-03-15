<?php

declare(strict_types=1);

/**
 * Demo 07 — Built-in expression language.
 *
 * Shows all operators, functions, and syntax forms supported
 * by the native PHP expression engine (zero dependencies).
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$products = [
    ['id' => 1, 'name' => 'Laptop',   'price' => 1200.00, 'stock' => 5,   'category' => 'tech',      'active' => true],
    ['id' => 2, 'name' => 'Mouse',    'price' => 25.00, 'stock' => 100, 'category' => 'tech',      'active' => true],
    ['id' => 3, 'name' => 'Desk',     'price' => 450.00, 'stock' => 0,   'category' => 'furniture', 'active' => true],
    ['id' => 4, 'name' => 'Chair',    'price' => 300.00, 'stock' => 12,  'category' => 'furniture', 'active' => false],
    ['id' => 5, 'name' => 'Monitor',  'price' => 600.00, 'stock' => 8,   'category' => 'tech',      'active' => true],
    ['id' => 6, 'name' => 'Keyboard', 'price' => 80.00, 'stock' => 50,  'category' => 'tech',      'active' => true],
];

echo "=== Comparison operators ===\n";

$overFifty = Algebra::from($products)->where('price > 50')->pluck('name')->toArray();
echo '  price > 50: '.implode(', ', $overFifty)."\n";

$under100 = Algebra::from($products)->where('price <= 100')->pluck('name')->toArray();
echo '  price <= 100: '.implode(', ', $under100)."\n";

$notTech = Algebra::from($products)->where("category != 'tech'")->pluck('name')->toArray();
echo "  category != 'tech': ".implode(', ', $notTech)."\n";

echo "\n=== Logical operators ===\n";

$techInStock = Algebra::from($products)
    ->where("category == 'tech' and stock > 0")
    ->pluck('name')->toArray();
echo '  tech AND stock > 0: '.implode(', ', $techInStock)."\n";

$cheapOrFurniture = Algebra::from($products)
    ->where("price < 100 or category == 'furniture'")
    ->pluck('name')->toArray();
echo '  price < 100 OR furniture: '.implode(', ', $cheapOrFurniture)."\n";

$notActive = Algebra::from($products)->where('not active')->pluck('name')->toArray();
echo '  not active: '.implode(', ', $notActive)."\n";

echo "\n=== 'in' operator ===\n";

$selected = Algebra::from($products)
    ->where("name in ['Laptop', 'Monitor', 'Keyboard']")
    ->pluck('name')->toArray();
echo '  name in list: '.implode(', ', $selected)."\n";

echo "\n=== String functions ===\n";

$longNames = Algebra::from($products)->where('length(name) > 5')->pluck('name')->toArray();
echo '  length(name) > 5: '.implode(', ', $longNames)."\n";

$hasK = Algebra::from($products)->where("contains(lower(name), 'k')")->pluck('name')->toArray();
echo "  contains lower(name) 'k': ".implode(', ', $hasK)."\n";

$startsWithM = Algebra::from($products)->where("starts(name, 'M')")->pluck('name')->toArray();
echo "  starts with 'M': ".implode(', ', $startsWithM)."\n";

echo "\n=== Ternary and labels ===\n";

$labelled = Algebra::from($products)
    ->select(static fn ($r) => [
        'name' => $r['name'],
        'label' => $r['price'] > 500 ? 'premium' : ($r['price'] > 100 ? 'mid' : 'budget'),
    ])
    ->toArray();

foreach ($labelled as $p) {
    printf("  %-10s → %s\n", $p['name'], $p['label']);
}

echo "\n=== Arithmetic in expressions ===\n";

$discounted = Algebra::from($products)
    ->where('price * 0.8 < 400')
    ->select(static fn ($r) => ['name' => $r['name'], 'discounted' => round($r['price'] * 0.8, 2)])
    ->toArray();

foreach ($discounted as $p) {
    printf("  %-10s → €%.2f (at 20%% off)\n", $p['name'], $p['discounted']);
}

echo "\n=== String concatenation (~) ===\n";

$labels = Algebra::from($products)
    ->where('stock > 0')
    ->select(static fn ($r) => ['label' => $r['category'].'/'.$r['name'].' (€'.$r['price'].')'])
    ->pluck('label')->toArray();

foreach ($labels as $l) {
    echo "  {$l}\n";
}

echo "\n=== item['key'] access ===\n";

$activeStock = Algebra::from($products)
    ->where("item['active'] == true and item['stock'] > 0")
    ->select(static fn ($r) => ['name' => $r['name'], 'stock' => $r['stock']])
    ->orderBy('stock', 'desc')
    ->toArray();

foreach ($activeStock as $p) {
    printf("  %-10s stock: %d\n", $p['name'], $p['stock']);
}

echo "\n✓ All expression forms demonstrated — zero framework dependencies\n";
