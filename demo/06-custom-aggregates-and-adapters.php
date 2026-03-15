<?php

declare(strict_types=1);

/**
 * Demo 06 — Custom aggregates and adapters.
 *
 * Shows how to register a custom aggregate function,
 * a custom adapter (SplFixedArray, ArrayObject, generator),
 * and the Algebra::pipe() convenience method.
 */

require __DIR__.'/../vendor/autoload.php';

use Nalabdou\Algebra\Algebra;
use Nalabdou\Algebra\Contract\AggregateInterface;

final class GeomeanAggregate implements AggregateInterface
{
    public function name(): string
    {
        return 'geomean';
    }

    public function compute(array $values): ?float
    {
        if (empty($values)) {
            return null;
        }
        $product = array_product(array_map('abs', $values));

        return $product ** (1 / count($values));
    }
}

Algebra::aggregates()->register(new GeomeanAggregate());

echo "=== Custom aggregate: geometric mean ===\n";

$prices = [
    ['category' => 'tech',      'price' => 1200],
    ['category' => 'tech',      'price' => 25],
    ['category' => 'tech',      'price' => 600],
    ['category' => 'furniture', 'price' => 450],
    ['category' => 'furniture', 'price' => 300],
];

$result = Algebra::from($prices)
    ->groupBy('category')
    ->aggregate([
        'count' => 'count(*)',
        'avg' => 'avg(price)',
        'geomean' => 'geomean(price)',
    ])
    ->toArray();

foreach ($result as $row) {
    printf(
        "  %-12s count=%d  avg=€%.2f  geomean=€%.2f\n",
        $row['_group'],
        $row['count'],
        $row['avg'],
        $row['geomean']
    );
}

echo "\n=== Generator adapter ===\n";

function generateOrders(): Generator
{
    $statuses = ['paid', 'pending', 'cancelled'];
    for ($i = 1; $i <= 20; ++$i) {
        yield [
            'id' => $i,
            'status' => $statuses[$i % 3],
            'amount' => $i * 50,
        ];
    }
}

$fromGenerator = Algebra::from(generateOrders())
    ->where("item['status'] == 'paid'")
    ->aggregate(['count' => 'count(*)', 'total' => 'sum(amount)'])
    ->toArray();

printf(
    "  From generator — paid: %d orders, total: €%d\n",
    $fromGenerator[0]['count'],
    $fromGenerator[0]['total']
);

echo "\n=== ArrayObject adapter ===\n";

$arrayObject = new ArrayObject([
    ['id' => 1, 'value' => 100],
    ['id' => 2, 'value' => 200],
    ['id' => 3, 'value' => 300],
]);

$fromAO = Algebra::from($arrayObject)->orderBy('value', 'desc')->toArray();
echo '  Values: '.implode(', ', array_column($fromAO, 'value'))."\n";

echo "\n=== Algebra::pipe() convenience method ===\n";

$data = array_map(static fn ($i) => ['id' => $i, 'v' => $i * 10], range(1, 10));
$result = Algebra::pipe(
    $data,
    static fn ($c) => $c->where(static fn ($r) => $r['v'] > 30)
      ->orderBy('v', 'desc')
      ->limit(3)
);

echo '  Top 3 values over 30: '.implode(', ', array_column($result, 'v'))."\n";

echo "\n=== Algebra::parallel() — concurrent pipelines ===\n";

$orders = array_map(static fn ($i) => ['id' => $i, 'status' => ['paid', 'pending'][$i % 2], 'amount' => $i * 100], range(1, 10));

$results = Algebra::parallel([
    'paid' => Algebra::from($orders)->where("item['status'] == 'paid'"),
    'pending' => Algebra::from($orders)->where("item['status'] == 'pending'"),
    'top3' => Algebra::from($orders)->topN(3, by: 'amount'),
]);

printf(
    "  Paid: %d | Pending: %d | Top3 amounts: %s\n",
    count($results['paid']),
    count($results['pending']),
    implode(', ', array_column($results['top3'], 'amount'))
);
