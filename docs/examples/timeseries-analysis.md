# Example: Timeseries analysis

Working with time-ordered data — running totals, moving averages, gap filling,
and period-over-period comparisons.

---

## Data setup

```php
use Nalabdou\Algebra\Algebra;

$dailyMetrics = [
    ['date' => '2024-01-01', 'revenue' => 1200, 'orders' => 12, 'visitors' => 340],
    ['date' => '2024-01-02', 'revenue' => 1500, 'orders' => 15, 'visitors' => 410],
    ['date' => '2024-01-03', 'revenue' =>  900, 'orders' =>  9, 'visitors' => 280],
    ['date' => '2024-01-04', 'revenue' => 1800, 'orders' => 18, 'visitors' => 520],
    ['date' => '2024-01-05', 'revenue' => 2100, 'orders' => 21, 'visitors' => 610],
    ['date' => '2024-01-06', 'revenue' =>  600, 'orders' =>  6, 'visitors' => 190], // weekend dip
    ['date' => '2024-01-07', 'revenue' =>  750, 'orders' =>  7, 'visitors' => 220], // weekend dip
    ['date' => '2024-01-08', 'revenue' => 1650, 'orders' => 16, 'visitors' => 470],
    ['date' => '2024-01-09', 'revenue' => 1900, 'orders' => 19, 'visitors' => 540],
    ['date' => '2024-01-10', 'revenue' => 2200, 'orders' => 22, 'visitors' => 630],
];
```

---

## Pattern 1: Running totals (YTD, MTD)

```php
$withRunning = Algebra::from($dailyMetrics)
    ->orderBy('date', 'asc')
    ->window('running_sum',   field: 'revenue', as: 'revenueYTD')
    ->window('running_sum',   field: 'orders',  as: 'ordersYTD')
    ->window('running_avg',   field: 'revenue', as: 'avgRevenue')
    ->window('running_count', field: 'date',    as: 'dayNumber')
    ->toArray();
```

---

## Pattern 2: Moving average (smooth out noise)

```php
$smoothed = Algebra::from($dailyMetrics)
    ->orderBy('date', 'asc')
    ->movingAverage(field: 'revenue', window: 7, as: 'ma7d')  // 7-day moving average
    ->movingAverage(field: 'orders',  window: 3, as: 'ma3d')  // 3-day moving average
    ->toArray();

// Rows 0–5: ma7d = null (insufficient history)
// Row 6+:   ma7d = average of current + 6 prior days
```

---

## Pattern 3: Day-over-day and week-over-week comparisons

```php
$withComparisons = Algebra::from($dailyMetrics)
    ->orderBy('date', 'asc')
    ->window('lag',  field: 'revenue', as: 'yesterday',    offset: 1)  // D-1
    ->window('lag',  field: 'revenue', as: 'lastWeek',     offset: 7)  // D-7
    ->window('lead', field: 'revenue', as: 'tomorrow',     offset: 1)  // D+1
    ->select(fn($r) => [
        'date'      => $r['date'],
        'revenue'   => $r['revenue'],
        'dod_pct'   => $r['yesterday']
            ? round(($r['revenue'] - $r['yesterday']) / $r['yesterday'] * 100, 1)
            : null,
        'wow_pct'   => $r['lastWeek']
            ? round(($r['revenue'] - $r['lastWeek']) / $r['lastWeek'] * 100, 1)
            : null,
    ])
    ->toArray();
```

---

## Pattern 4: Fill missing dates

```php
// Generate all dates in a range
$allDates = [];
$start    = new \DateTimeImmutable('2024-01-01');
$end      = new \DateTimeImmutable('2024-01-10');
$current  = $start;

while ($current <= $end) {
    $allDates[] = $current->format('Y-m-d');
    $current    = $current->modify('+1 day');
}

// Sparse data (some dates missing)
$sparseData = [
    ['date' => '2024-01-02', 'revenue' => 1500],
    ['date' => '2024-01-05', 'revenue' => 2100],
    ['date' => '2024-01-08', 'revenue' => 1650],
];

// Fill gaps with 0 revenue
$complete = Algebra::from($sparseData)
    ->fillGaps(
        key:     'date',
        series:  $allDates,
        default: ['revenue' => 0, 'orders' => 0],
    )
    ->toArray();
```

---

## Pattern 5: Quantile distribution and scoring

```php
$withScores = Algebra::from($dailyMetrics)
    ->orderBy('revenue', 'asc')
    ->window('ntile',     field: 'revenue', as: 'quartile', buckets: 4)
    ->window('cume_dist', field: 'revenue', as: 'percentile')
    ->normalize(field: 'revenue', as: 'revenueScore')
    ->normalize(field: 'visitors', as: 'visitorScore')
    ->select(fn($r) => [
        ...$r,
        'compositeScore' => round($r['revenueScore'] * 0.6 + $r['visitorScore'] * 0.4, 4),
    ])
    ->orderBy('date', 'asc')
    ->toArray();
```

---

## Pattern 6: Anomaly detection

Flag days where revenue is more than 1.5 standard deviations below the 7-day moving average:

```php
$anomalies = Algebra::from($dailyMetrics)
    ->orderBy('date', 'asc')
    ->movingAverage(field: 'revenue', window: 7, as: 'ma7')
    ->window('running_diff', field: 'revenue', as: 'delta')
    ->where(fn($r) => $r['ma7'] !== null && $r['revenue'] < $r['ma7'] * 0.6)
    ->select(fn($r) => [
        'date'          => $r['date'],
        'revenue'       => $r['revenue'],
        'ma7'           => round($r['ma7'], 2),
        'deviation_pct' => round(($r['revenue'] - $r['ma7']) / $r['ma7'] * 100, 1),
    ])
    ->toArray();
```

---

## Pattern 7: Partitioned window (per-channel running total)

```php
$multiChannel = [
    ['date' => '2024-01-01', 'channel' => 'web',    'revenue' => 800],
    ['date' => '2024-01-01', 'channel' => 'mobile',  'revenue' => 400],
    ['date' => '2024-01-02', 'channel' => 'web',    'revenue' => 950],
    ['date' => '2024-01-02', 'channel' => 'mobile',  'revenue' => 550],
];

$withChannelRunning = Algebra::from($multiChannel)
    ->orderBy('date', 'asc')
    ->window(
        fn:          'running_sum',
        field:       'revenue',
        partitionBy: 'channel',   // reset per channel
        as:          'channelYTD',
    )
    ->toArray();
```

---

## Full timeseries dashboard

```php
$tsDashboard = Algebra::parallel([
    'running' => Algebra::from($dailyMetrics)
        ->orderBy('date', 'asc')
        ->window('running_sum', field: 'revenue', as: 'ytd')
        ->window('running_avg', field: 'revenue', as: 'avgToDate'),

    'smoothed' => Algebra::from($dailyMetrics)
        ->orderBy('date', 'asc')
        ->movingAverage(field: 'revenue', window: 7, as: 'ma7'),

    'dod' => Algebra::from($dailyMetrics)
        ->orderBy('date', 'asc')
        ->window('lag', field: 'revenue', as: 'prev', offset: 1)
        ->window('running_diff', field: 'revenue', as: 'delta'),

    'normalized' => Algebra::from($dailyMetrics)
        ->normalize(field: 'revenue', as: 'score'),
]);
```
