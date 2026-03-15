# Example: E-commerce dashboard

A complete example building a multi-widget e-commerce analytics dashboard.

---

## Data setup

```php
use Nalabdou\Algebra\Algebra;

$orders = [/* ... from Doctrine or API ... */];
$users  = [/* ... */];
$products = [/* ... */];
```

---

## Widget 1: Revenue KPIs

```php
$kpis = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->aggregate([
        'total_revenue' => 'sum(amount)',
        'order_count'   => 'count(*)',
        'avg_order'     => 'avg(amount)',
        'median_order'  => 'median(amount)',
        'p90_order'     => 'percentile(amount, 0.9)',
    ])
    ->toArray()[0];

// ['_group'=>'*', 'total_revenue'=>48200, 'order_count'=>312, ...]
```

---

## Widget 2: Revenue by status (donut chart)

```php
$byStatus = Algebra::from($orders)
    ->tally('status')
    ->toArray();

// ['paid'=>312, 'pending'=>48, 'cancelled'=>23, 'refunded'=>11]
```

---

## Widget 3: Monthly revenue trend with moving average

```php
$trend = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->groupBy('month')
    ->aggregate(['revenue' => 'sum(amount)', 'orders' => 'count(*)'])
    ->orderBy('_group', 'asc')       // sort by month name (or use YYYY-MM format)
    ->window('running_sum', field: 'revenue', as: 'cumulative')
    ->movingAverage(field: 'revenue', window: 3, as: 'ma3')
    ->toArray();

// [{_group:'Jan', revenue:4200, cumulative:4200, ma3:null},
//  {_group:'Feb', revenue:5100, cumulative:9300, ma3:null},
//  {_group:'Mar', revenue:3800, cumulative:13100, ma3:4367}, ...]
```

---

## Widget 4: Revenue matrix — month × region (pivot table)

```php
$matrix = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->pivot(rows: 'month', cols: 'region', value: 'amount', aggregateFn: 'sum')
    ->toArray();

// [{_row:'Jan', Nord:4200, Sud:3100, Est:1800, Ouest:2400}, ...]
```

---

## Widget 5: Top 10 customers by revenue

```php
$topCustomers = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->innerJoin($users, leftKey: 'userId', rightKey: 'id', as: 'user')
    ->groupBy('userId')
    ->aggregate([
        'name'    => 'first(user.name)',
        'revenue' => 'sum(amount)',
        'orders'  => 'count(*)',
        'avg'     => 'avg(amount)',
    ])
    ->topN(10, by: 'revenue')
    ->toArray();
```

---

## Widget 6: Order value distribution

```php
$distribution = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->orderBy('amount', 'asc')
    ->window('ntile', field: 'amount', as: 'quartile', buckets: 4)
    ->window('cume_dist', field: 'amount', as: 'pct')
    ->toArray();
```

---

## Widget 7: High-value vs standard orders partition

```php
$partition = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->partition(fn($r) => $r['amount'] > 500);

$highValue = [
    'count'   => $partition->passCount(),
    'revenue' => array_sum(array_column($partition->pass(), 'amount')),
    'rate'    => $partition->passRate(),
];

$standard = [
    'count'   => $partition->failCount(),
    'revenue' => array_sum(array_column($partition->fail(), 'amount')),
];
```

---

## Widget 8: Running totals with lag (day-over-day growth)

```php
$dailyGrowth = Algebra::from($orders)
    ->where("item['status'] == 'paid'")
    ->groupBy('date')
    ->aggregate(['daily_revenue' => 'sum(amount)'])
    ->orderBy('_group', 'asc')
    ->window('lag',          field: 'daily_revenue', as: 'prev_day',   offset: 1)
    ->window('running_sum',  field: 'daily_revenue', as: 'cumulative')
    ->select(fn($r) => [
        'date'        => $r['_group'],
        'revenue'     => $r['daily_revenue'],
        'prev_day'    => $r['prev_day'],
        'growth_pct'  => $r['prev_day']
            ? round(($r['daily_revenue'] - $r['prev_day']) / $r['prev_day'] * 100, 1)
            : null,
        'cumulative'  => $r['cumulative'],
    ])
    ->toArray();
```

---

## All widgets in parallel

```php
$widgets = Algebra::parallel([
    'kpis'        => Algebra::from($orders)->where("item['status'] == 'paid'")->aggregate([...]),
    'by_status'   => Algebra::from($orders)->tally('status'),
    'matrix'      => Algebra::from($orders)->where(...)->pivot(rows: 'month', cols: 'region', value: 'amount'),
    'top_customers' => Algebra::from($orders)->where(...)->innerJoin($users, ...)->groupBy('userId')->aggregate([...])->topN(10, by: 'revenue'),
]);
```
