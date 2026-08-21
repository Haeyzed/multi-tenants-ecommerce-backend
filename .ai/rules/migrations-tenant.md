---
paths:
  - 'database/migrations/tenant/**/*.php'
---

# Migrations Tenant

## Short MySQL unique index names
MySQL identifier names are limited to 64 characters. Laravel auto-names unique indexes as {table}_{columns}_unique. On long pivot tables pass an explicit second argument, e.g. unique([...], 'variant_option_value_unique'). SQLite tests will not catch this; tenant provisioning on MySQL will.

## Drop composite unique on FK columns
MySQL binds foreign keys to supporting indexes. Before dropUnique() on columns that also have foreign keys, drop those foreign keys first via ForeignKeyIndexHelper::dropForeignKeys(), then re-add them after the new unique/index exists. SQLite tests will not catch error 1553; tenant provisioning on MySQL will.

## Drop FKs before dropping composite unique indexes
Before dropUnique() on columns that also have foreign keys, call ForeignKeyIndexHelper::dropForeignKeys() first, then re-add the foreign keys after the new unique exists. MySQL error 1553 on tenant provisioning; SQLite tests will not catch it.
