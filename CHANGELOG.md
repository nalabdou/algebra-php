# Changelog

All notable changes to `nalabdou/algebra` are documented here.

---

## [1.0.0] — Initial release

### Core engine
- `Algebra::from()` — fluent static entry point
- `Algebra::pipe()` — build + execute in one expression
- `Algebra::parallel()` — concurrent pipelines via PHP 8.1 Fibers
- `RelationalCollection` — lazy, immutable, clone-on-pipe, full fluent API
- `MaterializedCollection` — evaluated result with per-operation execution log
- `CollectionFactory` — converts any input via pluggable adapters
- `QueryPlanner` — rewrites operation chains before execution

### Operations — Join family
- `innerJoin` — INNER JOIN, O(n+m) hash index
- `leftJoin` — LEFT JOIN, preserves unmatched rows
- `semiJoin` — EXISTS check, no data merged
- `antiJoin` — NOT EXISTS
- `crossJoin` — cartesian product with optional key prefixes
- `zip` — positional merge by index

### Operations — Set algebra
- `intersect` — A ∩ B by key
- `except` — A − B by key
- `union` — A ∪ B, deduplicated, first occurrence wins
- `symmetricDiff` — A △ B

### Operations — Grouping & aggregation
- `groupBy` — GROUP BY field or expression or closure
- `aggregate` — full spec DSL with 18 built-in functions
- `tally` — count occurrences sorted descending
- `partition` — split pass/fail in one iteration → `PartitionResult`

### Operations — Window functions
- `window` dispatcher with 11 functions:
  `running_sum`, `running_avg`, `running_count`, `running_diff`,
  `rank`, `dense_rank`, `row_number`, `lag`, `lead`, `ntile`, `cume_dist`
- `movingAverage` — sliding window average
- `normalize` — min-max normalization [0.0, 1.0]

### Operations — Utility
- `where` — filter by expression or closure
- `select` — project / transform each row
- `orderBy` — multi-key sort
- `limit` — LIMIT / OFFSET
- `topN` / `bottomN` — sort + limit shorthand
- `rankBy` — sort + annotate with rank number
- `distinct` — DISTINCT ON key
- `reindex` — key by field value for O(1) lookup
- `pluck` — extract single column to flat array
- `chunk` — split into fixed-size sub-arrays
- `fillGaps` — insert default rows for missing series entries
- `transpose` — flip rows ↔ columns
- `sample` — random N rows with optional seed
- `pivot` — cross-tab matrix (rows × cols → aggregated values)

### Aggregate functions
- **Math**: `count`, `sum`, `avg`, `min`, `max`, `median`, `stddev`, `variance`, `percentile`
- **Statistical**: `mode`, `count_distinct`, `ntile`, `cume_dist`
- **Positional**: `first`, `last`
- **String/Bool**: `string_agg`, `bool_and`, `bool_or`

### Spec DSL extensions
- `string_agg(field, "separator")`
- `bool_and(field)` / `bool_or(field)`
- `percentile(field, 0.9)`

### Planner optimization passes
- `PushFilterBeforeJoin` — filter before inner/left join
- `PushFilterBeforeAntiJoin` — filter before semi/anti join
- `EliminateRedundantSort` — drops back-to-back sorts
- `CollapseConsecutiveMaps` — merges adjacent closure maps

### Adapters
- `ArrayAdapter` — plain PHP arrays
- `GeneratorAdapter` — PHP generators
- `TraversableAdapter` — any `\Traversable`

### Result objects
- `PartitionResult` — `pass()`, `fail()`, `passCount()`, `failCount()`, `passRate()`, `totalCount()`

### Expression engine
- `ExpressionEvaluator` — Pure ExpressionLanguage with fast dot-path shortcut
- `ExpressionCompiler` — compiles hot expressions to native PHP closures
- `ExpressionCache` — APCu-backed with in-process array fallback
- `PropertyAccessor` — deep dot-path resolver on arrays and objects

### Developer experience
- Full PHPDoc on every public class and method
- Complete Contract interfaces with full documentation
- Execution log on every `MaterializedCollection` (signature, duration, input/output rows)
- `Algebra::planner()->explain()` — human-readable plan diff
- `Algebra::reset()` — clean singleton state for tests
- Runnable demo scripts in `demo/`
- Benchmark script with configurable row count and iterations
