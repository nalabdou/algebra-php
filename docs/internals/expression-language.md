# Expression language

algebra-php ships a **built-in, dependency-free expression engine**.
No Symfony, no eval(), no external libraries.

## Architecture

```
Source string → Lexer → Token[] → Parser → AST (Node tree)
                                               ↓
                                          Evaluator  ←  context { item, ...row }
                                               ↓
                                            mixed result
```

The compiled AST is **cached** (APCu when available, in-process array otherwise),
so each unique expression string is only lexed and parsed once.

---

## Two expression styles

### 1. String expressions (compiled to AST)

```php
->where("item['status'] == 'paid'")
->where("status == 'paid'")                     // direct variable also works
->where("amount > 100 and region == 'Nord'")
->where("status in ['paid', 'refunded']")
->where("amount > 500 ? true : false")
->where("contains(lower(name), 'laptop')")
```

### 2. Closure expressions (zero overhead)

```php
->where(fn($r) => $r['status'] === 'paid')
->select(fn($r) => ['id' => $r['id'], 'label' => strtoupper($r['name'])])
->groupBy(fn($r) => substr($r['createdAt'], 0, 7))
```

Closures are always supported and run as native PHP. Use them when the condition
is known at code time or requires logic that the expression language does not cover.

---

## Operators

### Comparison
| Operator | Description |
|---|---|
| `==` | Loose equality |
| `!=` | Not equal |
| `>` | Greater than |
| `>=` | Greater than or equal |
| `<` | Less than |
| `<=` | Less than or equal |

### Logical
| Operator | Aliases | Description |
|---|---|---|
| `and` | `&&` | Logical AND (short-circuits) |
| `or` | `\|\|` | Logical OR (short-circuits) |
| `not` | `!` | Logical NOT |

### Arithmetic
| Operator | Description |
|---|---|
| `+` `-` `*` `/` `%` | Standard arithmetic |
| `**` | Exponentiation |

### String
| Operator | Description |
|---|---|
| `~` | Concatenation: `first ~ ' ' ~ last` |

### Special
| Operator | Example | Description |
|---|---|---|
| `in` | `status in ['paid', 'refunded']` | Membership test |
| `?:` | `amount > 500 ? 'high' : 'low'` | Ternary |

---

## Variable access

The row is exposed as `item`. All top-level array keys are also available as direct variables:

```php
->where("item['status'] == 'paid'")   // always works
->where("status == 'paid'")            // works for top-level keys
->where("item['user']['name'] == 'Alice'")  // nested
->where("user.name == 'Alice'")         // dot-path via resolve()
```

---

## Built-in functions

### String functions
| Function | Description |
|---|---|
| `length(v)` | String length or array count |
| `lower(v)` | `strtolower` |
| `upper(v)` | `strtoupper` |
| `trim(v)` | Trim whitespace |
| `ltrim(v)` | Left trim |
| `rtrim(v)` | Right trim |
| `substr(s, start, len?)` | Substring |
| `replace(s, find, replace)` | `str_replace` |
| `split(s, sep)` | `explode` → array |
| `join(arr, sep)` | `implode` |
| `contains(haystack, needle)` | `str_contains` |
| `starts(s, prefix)` | `str_starts_with` |
| `ends(s, suffix)` | `str_ends_with` |

### Numeric functions
| Function | Description |
|---|---|
| `abs(v)` | Absolute value |
| `round(v, precision=2)` | Round |
| `ceil(v)` | Ceiling |
| `floor(v)` | Floor |
| `min(a, b)` | Minimum of two values |
| `max(a, b)` | Maximum of two values |
| `clamp(v, min, max)` | Clamp to range |

### Type casting
| Function | Description |
|---|---|
| `int(v)` | Cast to int |
| `float(v)` | Cast to float |
| `str(v)` | Cast to string |
| `bool(v)` | Cast to bool |

### Collection / misc
| Function | Description |
|---|---|
| `count(v)` | Count array elements |
| `isset(v)` | True when value is not null |
| `empty(v)` | `empty()` |
| `date(fmt, ts?)` | `date()` |
| `now()` | `time()` |

---

## Examples

```php
// String operations
->where("lower(status) == 'paid'")
->where("starts(email, 'admin@')")
->where("length(name) > 3 and length(name) < 20")

// Numeric
->where("round(amount, 0) > 100")
->where("abs(balance) < 1000")

// Ternary labels
->select(fn($r) => [
    ...$r,
    'tier' => $r['amount'] > 1000 ? 'vip' : ($r['amount'] > 500 ? 'premium' : 'standard'),
])

// Date
->where("date('Y', createdAt) == '2024'")
->select(fn($r) => [...$r, 'year' => date('Y', $r['createdAt'])])

// Concatenation
->select(fn($r) => ['label' => $r['first_name'] ~ ' ' ~ $r['last_name']])
```

---

## Performance

| Scenario | Approx. cost per 5k rows |
|---|---|
| Closure expression | ~0.3 ms |
| Cached string expression (APCu) | ~0.4 ms |
| Cached string expression (memory) | ~0.5 ms |
| First evaluation (parse + cache) | ~1–2 ms (one-time) |

The cache key is an xxh3 hash of the expression string. APCu cache survives request
boundaries; the in-process array cache is per-process.

---

## Strict vs lenient mode

```php
// Default: strict mode — invalid expressions throw \RuntimeException
Algebra::evaluator()->evaluate($row, '@@@invalid@@@'); // throws

// Lenient mode — returns false/null silently
$lenient = new ExpressionEvaluator(
    new PropertyAccessor(),
    new ExpressionCache(),
    strictMode: false
);
$lenient->evaluate($row, '@@@invalid@@@'); // returns false
```
