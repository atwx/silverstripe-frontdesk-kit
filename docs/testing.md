# Testing

## Running the tests

The module ships unit tests for all pure-logic classes. Tests are executed from the **consuming project root** using the project's PHPUnit installation:

```bash
# From the project root
vendor/bin/phpunit --configuration vendor/atwx/silverstripe-frontdesk-kit/phpunit.xml.dist
```

### Why from the project root?

All module classes use the Silverstripe framework (ArrayList, FormField, i18n, etc.), which requires an initialised Silverstripe Kernel and Injector. The project's test bootstrap (`vendor/silverstripe/framework/tests/bootstrap.php`) provides that initialisation, along with the correct `.env`/DB configuration.

The module's `tests/bootstrap.php` automatically detects whether it is embedded inside a project (standard path `vendor/atwx/silverstripe-frontdesk-kit/`) and delegates to the project bootstrap. When running standalone with the module's own `composer install`, it falls back to the module-local bootstrap instead.

### DB user requirement

Silverstripe's `SapphireTest` needs permission to create temporary databases even for `$usesDatabase = false` tests. Make sure the configured DB user has `CREATE DATABASE` privileges (e.g. `root` in a local ddev environment).

---

## Test structure

```
tests/
├── bootstrap.php          # Smart bootstrap (project or standalone)
└── Unit/
    ├── ColumnTest.php          # Column fluent API, renderValue, renderLink
    ├── ColumnCollectionTest.php # ColumnCollection, make(), renderFor(), forExport()
    ├── FilterTest.php          # TextFilter, SelectFilter, DateRangeFilter
    ├── FilterCollectionTest.php # applyAll(), isActive(), toFieldList()
    └── RowActionTest.php       # RowAction factories, fluent modifiers, isEnabled()
```

All tests extend `SapphireTest` with `$usesDatabase = false` — no DB writes occur.

---

## What is tested

| Class | Coverage |
|---|---|
| `Column` | Constructor, all fluent setters, `renderValue()` (method + formatter), `renderLink()` (single + multiple placeholders) |
| `ColumnCollection` | `make()` / `addColumn()`, `forExport()`, `renderFor()` (count, link, base-URL prepend, absolute URLs, IsFirst, Type) |
| `TextFilter` | `renderField()`, `applyToList()` (skips empty, passes "0", calls applyFn) |
| `SelectFilter` | `options()` with array and callable, `renderField()` |
| `DateRangeFilter` | `renderField()` field names/types, `applyRange()` |
| `FilterCollection` | `add()`, `toFieldList()`, `isActive()` (text + date range), `applyAll()` (chaining, skip empty, DateRange routing) |
| `RowAction` | `link()`, `delete()`, `htmx()` factories, `withIcon()`, `withConfirm()`, `enabled()` (bool + callable), template accessors |

---

## Integration tests

Controller-level tests (HTTP requests, full CRUD, template rendering) belong in the **consuming project**, not in this module. Add them alongside your application tests and use `SapphireTest` with `$usesDatabase = true` and `$extra_dataobjects` as needed.
