# OCI Heavy Workers (v1)

Long-lived Horizon on the OCI app VM. Same Redis + MySQL as the web app.
See `docs/OCI-Heavy-Workers-Architecture.md`.

## Quick start (same host / shared disk)

```bash
# On OCI worker host (copy release + .env from app, then):
cp deploy/oci/.env.oci-workers.example .env.oci.local   # merge into real .env
# Set HORIZON_ROLE=oci-heavy and point REDIS_* / DB_* at prod

php artisan config:cache
php artisan horizon
# or: docker compose -f deploy/oci/docker-compose.yml up -d
```

## Rollout order (safe)

1. Deploy this release everywhere with `OCI_WORKERS_ENABLED=false` and `HORIZON_ROLE=all` (no behaviour change).
2. Start Horizon on OCI with `HORIZON_ROLE=oci-heavy` only.
3. Confirm campaign/status/import supervisors are online in Horizon UI.
4. On main web: `HORIZON_ROLE=web` + `OCI_WORKERS_ENABLED=true`.
5. If OCI dies: set main back to `HORIZON_ROLE=all` (and optionally `OCI_WORKERS_ENABLED=false`). Jobs remain in Redis.

## Import files

CSV uploads land on the web host `storage/app/imports/...`.  
v1 assumes workers share that filesystem (same VM or NFS). Separate worker VMs need object storage later.

## Image

`Dockerfile` builds a PHP-CLI worker image suitable for OCIR (`bmue9nxcdpso/wapapp-prod`).
