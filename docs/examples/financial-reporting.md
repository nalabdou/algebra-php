# Example: Financial reporting

A complete example building standard financial reports: P&L summary, monthly trends,
cohort revenue, and anomaly detection.

---

## Data setup

```php
use Nalabdou\Algebra\Algebra;

$transactions = [
    ['id' => 1,  'date' => '2024-01-05', 'category' => 'revenue',  'amount' =>  4200.00, 'region' => 'Nord', 'account' => 'sales'],
    ['id' => 2,  'date' => '2024-01-12', 'category' => 'expense',  'amount' =>   800.00, 'region' => 'Nord', 'account' => 'rent'],
    ['id' => 3,  'date' => '2024-01-18', 'category' => 'revenue',  'amount' =>  3100.00, 'region' => 'Sud',  'account' => 'sales'],
    ['id' => 4,  'date' => '2024-02-08', 'category' => 'expense',  'amount' =>   420.00, 'region' => 'Nord', 'account' => 'utilities'],
    ['id' => 5,  'date' => '2024-02-14', 'category' => 'revenue',  'amount' =>  5100.00, 'region' => 'Nord', 'account' => 'sales'],
    ['id' => 6,  'date' => '2024-02-20', 'category' => 'revenue',  'amount' =>  2900.00, 'region' => 'Sud',  'account' => 'services'],
    ['id' => 7,  'date' => '2024-03-02', 'category' => 'expense',  'amount' =>   950.00, 'region' => 'Sud',  'account' => 'salaries'],
    ['id' => 8,  'date' => '2024-03-15', 'category' => 'revenue',  'amount' =>  6000.00, 'region' => 'Nord', 'account' => 'sales'],
    ['id' => 9,  'date' => '2024-03-28', 'category' => 'revenue',  'amount' =>  3800.00, 'region' => 'Sud',  'account' => 'sales'],
    ['id' => 10, 'date' => '2024-03-30', 'category' => 'expense',  'amount' =>  1200.00, 'region' => 'Nord', 'account' => 'marketing'],
];
```

---

## Report 1: P&L summary

```php
$pnl = Algebra::parallel([
    'revenue' => Algebra::from($transactions)
        ->where("item['category'] == 'revenue'")
        ->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)', 'avg' => 'avg(amount)']),

    'expenses' => Algebra::from($transactions)
        ->where("item['category'] == 'expense'")
        ->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)']),
]);

$revenue  = $pnl['revenue'][0]['total'];
$expenses = $pnl['expenses'][0]['total'];
$profit   = $revenue - $expenses;
$margin   = round($profit / $revenue * 100, 1);

printf("Revenue : €%.2f\n", $revenue);
printf("Expenses: €%.2f\n", $expenses);
printf("Profit  : €%.2f (%.1f%% margin)\n", $profit, $margin);
```

---

## Report 2: Monthly revenue trend with MoM growth

```php
$monthlyRevenue = Algebra::from($transactions)
    ->where("item['category'] == 'revenue'")
    ->groupBy(fn($r) => substr($r['date'], 0, 7))   // YYYY-MM
    ->aggregate(['revenue' => 'sum(amount)', 'transactions' => 'count(*)'])
    ->orderBy('_group', 'asc')
    ->window('lag',          field: 'revenue', as: 'prevMonth',    offset: 1)
    ->window('running_sum',  field: 'revenue', as: 'ytdRevenue')
    ->select(fn($r) => [
        'month'        => $r['_group'],
        'revenue'      => $r['revenue'],
        'transactions' => $r['transactions'],
        'ytd'          => $r['ytdRevenue'],
        'mom_pct'      => $r['prevMonth']
            ? round(($r['revenue'] - $r['prevMonth']) / $r['prevMonth'] * 100, 1)
            : null,
    ])
    ->toArray();
```

---

## Report 3: Revenue by region with share

```php
$totalRevenue = Algebra::from($transactions)
    ->where("item['category'] == 'revenue'")
    ->aggregate(['total' => 'sum(amount)'])
    ->toArray()[0]['total'];

$byRegion = Algebra::from($transactions)
    ->where("item['category'] == 'revenue'")
    ->groupBy('region')
    ->aggregate(['revenue' => 'sum(amount)', 'transactions' => 'count(*)'])
    ->orderBy('revenue', 'desc')
    ->select(fn($r) => [
        'region'       => $r['_group'],
        'revenue'      => $r['revenue'],
        'transactions' => $r['transactions'],
        'share_pct'    => round($r['revenue'] / $totalRevenue * 100, 1),
    ])
    ->toArray();
```

---

## Report 4: Expense breakdown with partition

```php
$expenses = Algebra::from($transactions)
    ->where("item['category'] == 'expense'")
    ->groupBy('account')
    ->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)'])
    ->orderBy('total', 'desc')
    ->toArray();

// Split into major (>€500) vs minor expenses
$partition = Algebra::from($expenses)->partition("item['total'] > 500");
printf("Major expenses: %d categories\n", $partition->passCount());
printf("Minor expenses: %d categories\n", $partition->failCount());
```

---

## Report 5: Revenue anomaly detection

```php
$withStats = Algebra::from($transactions)
    ->where("item['category'] == 'revenue'")
    ->orderBy('date', 'asc')
    ->window('running_avg', field: 'amount', as: 'runningAvg')
    ->window('running_diff', field: 'amount', as: 'delta')
    ->movingAverage(field: 'amount', window: 3, as: 'ma3')
    ->normalize(field: 'amount', as: 'amountScore')
    ->toArray();

// Flag transactions significantly above the moving average
$anomalies = Algebra::from($withStats)
    ->where(fn($r) => $r['ma3'] !== null && $r['amount'] > $r['ma3'] * 1.5)
    ->pluck('id')
    ->toArray();

printf("Anomalous transaction IDs: %s\n", implode(', ', $anomalies));
```

---

## Report 6: Revenue matrix (pivot)

```php
$matrix = Algebra::from($transactions)
    ->where("item['category'] == 'revenue'")
    ->select(fn($r) => [...$r, 'month' => substr($r['date'], 0, 7)])
    ->pivot(rows: 'month', cols: 'region', value: 'amount', aggregateFn: 'sum')
    ->toArray();
```

---

## Complete financial dashboard

```php
$dashboard = Algebra::parallel([
    'pnl_summary'  => Algebra::from($transactions)->groupBy('category')->aggregate(['total' => 'sum(amount)']),
    'monthly_rev'  => Algebra::from($transactions)->where("item['category'] == 'revenue'")->groupBy(fn($r) => substr($r['date'], 0, 7))->aggregate(['revenue' => 'sum(amount)'])->orderBy('_group', 'asc'),
    'by_region'    => Algebra::from($transactions)->where("item['category'] == 'revenue'")->groupBy('region')->aggregate(['revenue' => 'sum(amount)'])->orderBy('revenue', 'desc'),
    'by_account'   => Algebra::from($transactions)->groupBy('account')->aggregate(['total' => 'sum(amount)', 'count' => 'count(*)'])->orderBy('total', 'desc'),
    'pivot_matrix' => Algebra::from($transactions)->where("item['category'] == 'revenue'")->select(fn($r) => [...$r, 'month' => substr($r['date'], 0, 7)])->pivot(rows: 'month', cols: 'region', value: 'amount'),
]);
```
