# Pivot

The `pivot` operation reshapes a flat collection into a **cross-tab matrix** — rows become row labels, columns become column headers, and each cell contains an aggregated value.

---

## Basic usage

```php
$sales = [
    ['month' => 'Jan', 'region' => 'Nord', 'revenue' => 4200],
    ['month' => 'Jan', 'region' => 'Sud',  'revenue' => 3100],
    ['month' => 'Jan', 'region' => 'Est',  'revenue' => 1800],
    ['month' => 'Feb', 'region' => 'Nord', 'revenue' => 5100],
    ['month' => 'Feb', 'region' => 'Sud',  'revenue' => 2900],
    ['month' => 'Feb', 'region' => 'Est',  'revenue' => 2200],
];

$matrix = Algebra::from($sales)
    ->pivot(rows: 'month', cols: 'region', value: 'revenue')
    ->toArray();

// Result:
// [
//   ['_row' => 'Jan', 'Nord' => 4200, 'Sud' => 3100, 'Est' => 1800],
//   ['_row' => 'Feb', 'Nord' => 5100, 'Sud' => 2900, 'Est' => 2200],
// ]
```

---

## Parameters

```php
->pivot(
    rows:        'month',   // field whose distinct values become row labels
    cols:        'region',  // field whose distinct values become column headers
    value:       'revenue', // field to aggregate within each cell
    aggregateFn: 'sum',     // aggregate function name (default: 'sum')
)
```

### Available aggregate functions for cells

Any function registered in the `AggregateRegistry`:
- `sum` (default) — sum all values in the cell
- `avg` — average of all values
- `count` — count of values
- `min` / `max` — minimum / maximum
- `median` — median value
- Or any [custom aggregate](../aggregates/custom.md)

```php
// Average revenue per cell instead of sum
->pivot(rows: 'month', cols: 'region', value: 'revenue', aggregateFn: 'avg')
```

---

## Missing cells

When a row/column combination has no data, the cell value is `null`:

```php
$sparse = [
    ['month' => 'Jan', 'region' => 'Nord', 'revenue' => 4200],
    // Jan/Sud is missing
    ['month' => 'Feb', 'region' => 'Nord', 'revenue' => 5100],
    ['month' => 'Feb', 'region' => 'Sud',  'revenue' => 2900],
];

$matrix = Algebra::from($sparse)->pivot(rows: 'month', cols: 'region', value: 'revenue')->toArray();
// [
//   ['_row' => 'Jan', 'Nord' => 4200, 'Sud' => null],   ← Jan/Sud = null
//   ['_row' => 'Feb', 'Nord' => 5100, 'Sud' => 2900],
// ]
```

Use PHP's null coalescing when rendering:
```php
foreach ($matrix as $row) {
    echo $row['Sud'] ?? 0; // treat null as 0
}
```

---

## Column order

Columns appear in the order their values are **first encountered** in the input. To control column order, sort before pivoting:

```php
Algebra::from($sales)
    ->orderBy('region', 'asc')   // sort by region first to get alphabetical columns
    ->pivot(rows: 'month', cols: 'region', value: 'revenue')
```

---

## Rendering a pivot table

```php
$matrix = Algebra::from($sales)->pivot(rows: 'month', cols: 'region', value: 'revenue')->toArray();

$regions = ['Nord', 'Sud', 'Est', 'Ouest'];

// Header
echo sprintf("%-6s", 'Month');
foreach ($regions as $r) { echo sprintf(" %8s", $r); }
echo "\n";

// Data rows
foreach ($matrix as $row) {
    echo sprintf("%-6s", $row['_row']);
    foreach ($regions as $r) {
        echo sprintf(" %8s", isset($row[$r]) ? number_format($row[$r]) : '—');
    }
    echo "\n";
}
```

---

## Pivot + filter

Filter before pivoting to restrict which rows and cells appear:

```php
Algebra::from($orders)
    ->where("item['status'] == 'paid'")   // only paid orders
    ->where(fn($r) => $r['amount'] > 50)  // skip small amounts
    ->pivot(rows: 'month', cols: 'region', value: 'amount', aggregateFn: 'sum')
    ->orderBy('_row', 'asc')              // sort output rows by month
    ->toArray();
```

---

## Pivot vs transpose

| Operation | Use case |
|---|---|
| `pivot` | Turn a flat fact table into a matrix — cross-tab reporting |
| `transpose` | Flip an already-shaped 2D array — rows become columns and vice versa |
