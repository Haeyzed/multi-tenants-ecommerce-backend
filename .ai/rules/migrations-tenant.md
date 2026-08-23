---
paths:
  - 'database/migrations/tenant/**/*.php'
---

# Migrations Tenant

## Short MySQL unique index names
MySQL identifier names are limited to 64 characters. Laravel auto-names unique indexes as {table}_{columns}_unique. On long pivot tables pass an explicit second argument, e.g. unique([...], 'variant_option_value_unique'). SQLite tests will not catch this; tenant provisioning on MySQL will.

## Nullable columns in unique constraints (with FKs)
MySQL rejects a foreign key on a column that is also referenced by a STORED/VIRTUAL generated column (error 1215, either order). Do not use `storedAs('COALESCE(...)')` for uniqueness when that column has a FK. Use a functional unique index instead: SQLite `COALESCE(col, 0)`, MySQL `(COALESCE(col, 0))` with parentheses.

## Drop composite unique on FK columns
MySQL binds foreign keys to supporting indexes. Before dropUnique() on columns that also have foreign keys, call ForeignKeyIndexHelper::dropForeignKeys() first, then re-add the foreign keys after the new unique exists. MySQL error 1553 on tenant provisioning; SQLite tests will not catch it.
