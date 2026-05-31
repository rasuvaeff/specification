# Examples

Runnable examples for `rasuvaeff/specification`. They use an in-memory SQLite
database (via `ext-pdo_sqlite`, a dev dependency), so they run offline after:

```bash
composer install
```

| Script | Shows |
|--------|-------|
| `builder.php` | `SpecificationBuilder` (AND, IN, BETWEEN), the generated SQL, and results. |
| `or-not-raw.php` | `OrSpecification`, `notWhere`, `RawSpecification` / `withRaw` (bound params), builder `orderBy`/`limit`/`offset`. |

```bash
php examples/builder.php
php examples/or-not-raw.php
```

`_bootstrap.php` builds and seeds the sample `items` table shared by both.
