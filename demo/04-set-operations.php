<?php

declare(strict_types=1);

/**
 * Demo 04 — Set operations.
 *
 * Shows intersect, except, union, symmetricDiff, semiJoin, antiJoin.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;

$all = array_map(static fn ($i) => ['id' => $i, 'name' => "Item {$i}"], range(1, 10));
$featured = [['id' => 2], ['id' => 4], ['id' => 6], ['id' => 8]];
$outOfStock = [['id' => 3], ['id' => 6], ['id' => 9]];
$wishlist = [['id' => 1], ['id' => 4], ['id' => 7]];

echo "=== Featured AND in-stock ===\n";
$featuredInStock = Algebra::from($featured)
    ->intersect($all, by: 'id')
    ->except($outOfStock, by: 'id')
    ->toArray();
echo '  IDs: '.implode(', ', array_column($featuredInStock, 'id'))."\n";

echo "\n=== All items except out-of-stock ===\n";
$available = Algebra::from($all)->except($outOfStock, by: 'id')->toArray();
echo '  IDs: '.implode(', ', array_column($available, 'id'))."\n";

echo "\n=== Featured UNION wishlist (deduplicated) ===\n";
$combined = Algebra::from($featured)->union($wishlist, by: 'id')->toArray();
$ids = array_column($combined, 'id');
sort($ids);
echo '  IDs: '.implode(', ', $ids)."\n";

echo "\n=== Items in featured XOR wishlist (symmetric diff) ===\n";
$exclusive = Algebra::from($featured)->symmetricDiff($wishlist, by: 'id')->toArray();
$ids = array_column($exclusive, 'id');
sort($ids);
echo '  IDs: '.implode(', ', $ids)." (not in both)\n";

echo "\n=== Items with at least one sale (semi join) ===\n";
$sales = [['productId' => 2], ['productId' => 5], ['productId' => 8], ['productId' => 2]];
$soldItems = Algebra::from($all)
    ->semiJoin($sales, leftKey: 'id', rightKey: 'productId')
    ->toArray();
echo '  IDs: '.implode(', ', array_column($soldItems, 'id'))."\n";

echo "\n=== Items with zero sales (anti join) ===\n";
$unsoldItems = Algebra::from($all)
    ->antiJoin($sales, leftKey: 'id', rightKey: 'productId')
    ->toArray();
echo '  IDs: '.implode(', ', array_column($unsoldItems, 'id'))."\n";

echo "\n=== Complex: available + featured + not in wishlist ===\n";
$result = Algebra::from($all)
    ->intersect($featured, by: 'id')       // featured items only
    ->except($outOfStock, by: 'id')        // that are in stock
    ->except($wishlist, by: 'id')          // and not already wishlisted
    ->toArray();
echo '  IDs: '.implode(', ', array_column($result, 'id'))." (recommend these)\n";
