# Public API

## Public tenant resolve by domain

Unauthenticated tenant bootstrap uses `GET /api/public/tenant?domain=` on central domains (`PublicTenantController`). It looks up Stancl domains and returns safe branding via `PublicTenantResource` (`id`, `name`, `slug`, `status`, `is_active`, `allows_login`, `domain`, `display_name`, `logo`, `profile`). Do not require profile `is_public` for login branding. Do not expose secrets. Distinct from `GET /api/public/stores/{slug}` which is the public store directory.
