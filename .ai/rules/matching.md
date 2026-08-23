---
paths:
  - 'app/Routing/Matching/**'
---

# Matching

## Skip central routes when X-Tenant-Domain present
Landlord API is Route::domain(central). When the Next BFF sends X-Tenant-Domain on the central Host, SkipCentralDomainWhenTenantHeaderValidator must skip those routes or overlapping URIs like /api/auth/login hit landlord AuthController and return credentials do not match.
