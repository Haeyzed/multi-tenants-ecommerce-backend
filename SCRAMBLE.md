# Scramble API documentation

Setup follows:

- https://scramble.dedoc.co/usage/multiple-docs
- https://scramble.dedoc.co/blog/multitenant-apis

## URLs

- Landlord UI: `/docs/landlord`
- Landlord OpenAPI: `/docs/landlord.json`
- Tenant UI: `/docs/tenant`
- Tenant OpenAPI: `/docs/tenant.json`

Tenant docs use an OpenAPI server variable:

`https://{tenant}.your-app-host/api`

The UI lets you set `tenant` (default: `demo`) when trying requests.

## Commands

```bash
php artisan scramble:cache
php artisan scramble:cache --api=landlord
php artisan scramble:cache --api=tenant

php artisan scramble:clear
php artisan scramble:clear --api=landlord
php artisan scramble:clear --api=tenant

php artisan scramble:export --api=landlord --path=landlord-openapi.json
php artisan scramble:export --api=tenant --path=tenant-openapi.json
```
