---
paths:
  - 'app/Services/Landlord/Tenant/**'
---

# Landlord Tenant

## Queued Stancl create pipeline
TenantCreated uses Stancl JobPipeline with `shouldBeQueued(true)`: CreateDatabase → MigrateDatabase → SeedDatabase → FinalizeTenantProvision. HTTP create returns 202 with `status=pending`; admin is created in FinalizeTenantProvision. Requires `php artisan queue:work` (phpunit uses QUEUE_CONNECTION=sync so jobs run inline).

## Extend PHP time limit on delete / finalize workers
Delete still runs sync Stancl delete pipelines. FinalizeTenantProvision and TenantService::destroy call set_time_limit via tenancy.provisioning.max_execution_time (TENANT_PROVISIONING_MAX_EXECUTION_TIME, default 300).

## Domain or header identification
Tenant routes use InitializeTenancyByDomainOrHeader + PreventAccessFromUnwantedDomainsUnlessTenantHeader so the Next BFF can hit the central host with X-Tenant-Domain.
SkipCentralDomainWhenTenantHeaderValidator skips landlord `Route::domain(central)` routes when that header is present; otherwise overlapping URIs like `/api/auth/login` hit landlord auth and return "credentials do not match".
