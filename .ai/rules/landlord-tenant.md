---
paths:
  - 'app/Services/Landlord/Tenant/**'
---

# Landlord Tenant

## Extend PHP time limit during tenant provisioning
Tenant create/delete runs Stancl pipeline synchronously (~185 tenant migrations + seed). Herd default max_execution_time is 30s. TenantService::extendProvisioningTimeLimit() must run before store/destroy; configured via tenancy.provisioning.max_execution_time (env TENANT_PROVISIONING_MAX_EXECUTION_TIME, default 300). For faster provisioning, generate database/schema/tenant-schema.dump after migrating tenant path.
