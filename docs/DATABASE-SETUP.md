# Database Setup — WapApp 2.0 (Premium Schema)

## Architecture

| Database | Purpose |
|----------|---------|
| `wapapp_master` | Tenants, plans, admins, inbound webhooks, queue |
| `tenant{id}` | Users, lines, contacts, inbox, outbound webhooks |

## Design principles

- **DRY migrations** via Blueprint macros (`auditable`, `phoneNumber`, `publicUuid`)
- **Soft deletes** on all business entities
- **Public UUIDs** for external/API references (no internal ID exposure)
- **Composite indexes** for inbox, webhooks, and queue queries
- **Strict foreign keys** with cascade/null rules
- **PHP Enums** mapped to all status/type columns

## Setup

```bash
# 1. Create database
mysql -u root -p -e "CREATE DATABASE wapapp_master CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Configure .env (MAMP port may be 8889)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=wapapp_master
DB_USERNAME=root
DB_PASSWORD=

# 3. Migrate + seed
php artisan migrate:fresh --seed
```

## Master tables

| Table | Key features |
|-------|-------------|
| `plans` | Limits, currency, features JSON, soft deletes |
| `tenants` | Company profile, plan FK, settings JSON, soft deletes |
| `domains` | Subdomain mapping, primary flag |
| `admins` | Platform admin auth, UUID, soft deletes |
| `inbound_webhook_events` | Alibaba CAMS ingest, append-only, dedup index |
| `processed_events` | Idempotency guard |
| `tenant_provisioning_logs` | Tenant create/migrate audit |

## Tenant tables (per customer)

| Table | Key features |
|-------|-------------|
| `users` | Owner/admin/agent roles, UUID, soft deletes |
| `team_members` | Agent accounts, line assignments |
| `whatsapp_lines` | Alibaba CAMS IDs, quality rating, default line |
| `contacts` | Audience contacts, opt-in status |
| `conversations` | Inbox threads, unread_count, unique per line+phone |
| `messages` | Delivery timestamps, external_message_id dedup |
| `webhook_subscriptions` | Customer outbound webhook config |
| `webhook_deliveries` | Delivery audit with retry scheduling |

## Blueprint macros

Registered in `MacroServiceProvider`:

```php
$table->auditable();              // timestamps + softDeletes
$table->phoneNumber('phone');     // string(20) + index
$table->phoneNumber('phone', unique: true, nullable: true);
$table->publicUuid();             // uuid column + unique
$table->statusColumn();           // string(32) + index + default
```

## Default credentials

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@wapapp.test | password |
| Tenant Owner | owner@demo.wapapp.test | password |
