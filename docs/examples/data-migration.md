# Example: Data migration pipeline

Using algebra-php as a lightweight ETL (Extract, Transform, Load) tool —
reading from one source, transforming, validating, and writing to a target.

---

## Classic ETL pipeline

```php
use Nalabdou\Algebra\Algebra;

// ── 1. EXTRACT ────────────────────────────────────────────────────────────────

// From a legacy CSV export
$legacyRows = array_map(
    fn($line) => str_getcsv($line),
    file('/data/legacy_customers.csv')
);

$headers = array_shift($legacyRows); // remove header row
$rows    = array_map(fn($r) => array_combine($headers, $r), $legacyRows);

// ── 2. TRANSFORM ──────────────────────────────────────────────────────────────

$transformed = Algebra::from($rows)
    // Normalise field names
    ->select(fn($r) => [
        'id'         => (int) $r['CUST_ID'],
        'name'       => trim($r['FULL_NAME']),
        'email'      => strtolower(trim($r['EMAIL_ADDRESS'])),
        'phone'      => preg_replace('/[^0-9+]/', '', $r['PHONE']),
        'country'    => strtoupper(trim($r['COUNTRY_CODE'])),
        'created_at' => strtotime($r['REGISTRATION_DATE']),
        'tier'       => match(strtoupper($r['CUSTOMER_TIER'])) {
            'GOLD', 'PLATINUM' => 'premium',
            'SILVER'           => 'standard',
            default            => 'basic',
        },
    ])
    // Deduplicate by email (keep first occurrence)
    ->distinct('email')
    // Remove obviously invalid records
    ->where(fn($r) => strlen($r['email']) > 3 && str_contains($r['email'], '@'))
    ->where(fn($r) => strlen($r['name']) > 1)
    ->where(fn($r) => $r['id'] > 0);
```

---

## Validation and error splitting

```php
// Split valid vs invalid records in one pass
$partition = Algebra::from($rows)
    ->select(fn($r) => [
        'id'          => (int) ($r['id'] ?? 0),
        'email'       => trim($r['email'] ?? ''),
        'name'        => trim($r['name'] ?? ''),
        '_valid'      => !empty($r['email'])
                      && str_contains($r['email'], '@')
                      && strlen($r['name']) > 1
                      && ((int)$r['id']) > 0,
        '_errorReason' => match(true) {
            empty($r['email'])                      => 'missing_email',
            !str_contains($r['email'] ?? '', '@')   => 'invalid_email',
            strlen($r['name'] ?? '') < 2            => 'invalid_name',
            ((int)($r['id'] ?? 0)) <= 0             => 'invalid_id',
            default                                 => null,
        },
    ])
    ->partition("item['_valid'] == true");

$valid   = $partition->pass();
$invalid = $partition->fail();

printf("Valid: %d | Invalid: %d\n", count($valid), count($invalid));

// Log error reasons
$errorSummary = Algebra::from($invalid)->tally('_errorReason')->toArray();
foreach ($errorSummary as $reason => $count) {
    printf("  %s: %d records\n", $reason, $count);
}
```

---

## Cross-reference and enrichment

```php
// Lookup table from another source
$countryMap = [
    ['code' => 'FR', 'name' => 'France',  'region' => 'EU'],
    ['code' => 'DE', 'name' => 'Germany', 'region' => 'EU'],
    ['code' => 'US', 'name' => 'USA',     'region' => 'NA'],
    ['code' => 'GB', 'name' => 'UK',      'region' => 'EU'],
];

$enriched = Algebra::from($valid)
    // Attach country details
    ->leftJoin($countryMap, on: 'country=code', as: 'countryInfo')
    // Add computed fields
    ->select(fn($r) => [
        'id'         => $r['id'],
        'name'       => $r['name'],
        'email'      => $r['email'],
        'tier'       => $r['tier'],
        'country'    => $r['country'],
        'countryName'=> $r['countryInfo']['name'] ?? 'Unknown',
        'region'     => $r['countryInfo']['region'] ?? 'OTHER',
        'created_at' => $r['created_at'],
    ]);
```

---

## Migration statistics

```php
$stats = Algebra::parallel([
    'total'       => Algebra::from($rows)->aggregate(['count' => 'count(*)']),
    'valid'       => Algebra::from($valid)->aggregate(['count' => 'count(*)']),
    'by_tier'     => Algebra::from($valid)->tally('tier'),
    'by_region'   => Algebra::from($enriched->toArray())->tally('region'),
    'by_country'  => Algebra::from($enriched->toArray())->tally('country'),
]);

printf("Source rows    : %d\n", $stats['total'][0]['count']);
printf("Valid rows     : %d\n", $stats['valid'][0]['count']);
printf("Error rate     : %.1f%%\n",
    ($stats['total'][0]['count'] - $stats['valid'][0]['count'])
    / $stats['total'][0]['count'] * 100
);
printf("\nTier breakdown:\n");
foreach ($stats['by_tier'] as $tier => $count) {
    printf("  %-10s %d\n", $tier, $count);
}
```

---

## Chunked batch insert

```php
// Insert in batches of 500 to avoid memory pressure
$batches = Algebra::from($enriched->toArray())
    ->orderBy('id', 'asc')
    ->chunk(500)
    ->toArray();

foreach ($batches as $batchIndex => $batch) {
    printf("Inserting batch %d (%d rows)...\n", $batchIndex + 1, count($batch));

    // $pdo->beginTransaction();
    foreach ($batch as $row) {
        // $stmt->execute($row);
    }
    // $pdo->commit();
}
```

---

## Incremental migration (find new/changed records)

```php
// Existing records in target (e.g. from DB)
$existing = Algebra::from($targetDb->all())
    ->reindex('email')
    ->toArray();

// New records: in source but not in target
$newRecords = Algebra::from($valid)
    ->antiJoin(array_values($existing), leftKey: 'email', rightKey: 'email')
    ->toArray();

// Updated records: email exists but data differs
$updatedRecords = Algebra::from($valid)
    ->semiJoin(array_values($existing), leftKey: 'email', rightKey: 'email')
    ->where(fn($r) => $r['name'] !== ($existing[$r['email']]['name'] ?? null))
    ->toArray();

printf("New: %d | Updated: %d\n", count($newRecords), count($updatedRecords));
```
