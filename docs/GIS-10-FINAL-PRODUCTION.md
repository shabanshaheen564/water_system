# GIS-10 — Final Security / Audit / Production

## Scope
Final hardening for official use: RBAC, authentication/authorization, API throttling, CSRF/session security, audit trail, GIS/database indexes, tests, production configuration, backups and deployment.

## Audit trail
Core Eloquent create/update/delete events are recorded with user, action, timestamp, affected dataset/layer, model identifier, old values, new values, request metadata and spatial geometry where available. Audit logs are exposed through the audit endpoint and require `audit_logs.view`.

## Security
- Web uses Laravel session authentication and framework CSRF protection.
- API uses Sanctum and Spatie permissions.
- Inactive users are blocked.
- API requests are rate limited.
- Security response headers are applied.
- Passwords and remember tokens are excluded from audit snapshots.
- System Owner protections remain enforced.

## GIS / performance
- GiST spatial index on `gis_features.geometry`.
- GIN index on `dataset_records.values`.
- Dataset/time and identifier indexes support common list/filter queries.
- GIS remains bbox/radius filtered and paginated; clients should request the visible map extent only.

## Production checklist
1. `APP_ENV=production` and `APP_DEBUG=false`.
2. Use a strong `APP_KEY` and HTTPS.
3. Use PostgreSQL/PostGIS with SSL.
4. Prefer Redis for cache and queue in production.
5. `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`.
6. Run `config:cache`, `route:cache` and `view:cache`.
7. Run `migrate --force`.
8. Run queue workers under a supervisor.
9. Schedule PostgreSQL backups and test restoration.
10. Keep `.env` outside Git.
11. Restrict database network access.
12. Monitor logs, failed jobs, disk, DB growth and API latency.

## Backup
Use `pg_dump` for logical backups and verify restore on a separate database before relying on it operationally.
