---
paths:
  - app/Jobs/SendNotificationJob.php
---

# Jobs

## Queued landlord notifications need null tenantId
Queued SendNotificationJob with null tenantId must still deliver landlord/central notifiables. Only skip when the notifiable is App\\Models\\Tenant\\* (needs tenant context). Tenant create (tenant.created) and other landlord alerts fail silently if this guard rejects all null-tenantId jobs.
