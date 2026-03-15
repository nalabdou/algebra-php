# algebra-php Documentation

Pure PHP relational algebra engine — JOIN · PIVOT · WINDOW · GROUP BY · 60+ operations.

---

## Table of contents

### Getting started
- [Installation & quick start](getting-started.md)
- [Core concepts](core-concepts.md)
- [Configuration](configuration.md)

### Operations
- [Joins](operations/joins.md) — `innerJoin`, `leftJoin`, `semiJoin`, `antiJoin`, `crossJoin`, `zip`
- [Set operations](operations/set-operations.md) — `intersect`, `except`, `union`, `symmetricDiff`
- [Filter & projection](operations/filter-projection.md) — `where`, `select`
- [Grouping & aggregation](operations/grouping-aggregation.md) — `groupBy`, `aggregate`, `tally`, `partition`
- [Window functions](operations/window-functions.md) — `window`, `movingAverage`, `normalize`
- [Pivot](operations/pivot.md) — cross-tab matrix
- [Sorting & slicing](operations/sorting-slicing.md) — `orderBy`, `limit`, `topN`, `bottomN`
- [Structural utilities](operations/structural.md) — `distinct`, `reindex`, `pluck`, `chunk`, `fillGaps`, `transpose`, `sample`, `rankBy`

### Aggregates
- [Built-in aggregates](aggregates/builtin.md) — all 18 built-in functions
- [Custom aggregates](aggregates/custom.md) — implementing `AggregateInterface`
- [Aggregate spec DSL](aggregates/spec-dsl.md) — string spec syntax reference

### Adapters
- [Built-in adapters](adapters/builtin.md) — array, generator, traversable
- [Custom adapters](adapters/custom.md) — implementing `AdapterInterface`
- [Framework adapters](adapters/frameworks.md) — Doctrine, Eloquent, CSV, Excel

### Advanced
- [Expression language](internals/expression-language.md) — string expressions & closures
- [Query planner](internals/query-planner.md) — optimization passes & plan inspection
- [Execution log](internals/execution-log.md) — per-operation timing & row counts
- [Pipeline branching](internals/pipeline-branching.md) — immutability & reuse
- [Parallel execution](internals/parallel-execution.md) — PHP Fibers

### Examples
- [E-commerce dashboard](examples/ecommerce-dashboard.md)
- [Financial reporting](examples/financial-reporting.md)
- [Timeseries analysis](examples/timeseries-analysis.md)
- [Data migration pipeline](examples/data-migration.md)

### Reference
- [API reference](api-reference.md) — every public method
- [Performance guide](performance.md)
- [Upgrading](upgrading.md)
- [Contributing](contributing.md)
