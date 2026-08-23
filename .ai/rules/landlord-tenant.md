---
paths:
  - 'app/Services/Landlord/Tenant/**'
---

# Landlord Tenant

## Extend PHP time limit during tenant provisioning
Tenant create/delete runs the Stancl JobPipeline synchronously (`CreateDatabase` → `MigrateDatabase` → `SeedDatabase` via normal `tenants:migrate`). Herd default max_execution_time is 30s. TenantService::extendProvisioningTimeLimit() must run before store/destroy; configured via tenancy.provisioning.max_execution_time (env TENANT_PROVISIONING_MAX_EXECUTION_TIME, default 300).

## Tenant create 504 is a gateway timeout, not a migration bug
Sync provision of many tenant migrations can exceed ~60s BFF/proxy idle limits. Keep Stancl's stock MigrateDatabase (no custom schema dumps). Raise the Next.js Laravel proxy timeout (`maxDuration` + `LARAVEL_PROXY_TIMEOUT_MS`, default 600000) so the HTTP request can wait for normal migrations.
