# WapApp 2.0 — Webhook Architecture (Legacy Analysis + New Design)

**Source:** `tittuprod4.sql` + legacy code audit  
**Date:** 27 July 2026

---

## CRITICAL: 3 Completely Different Webhook Systems

Legacy mein "webhook" naam se 3 alag cheezein hain. Inhe kabhi merge mat karo.

```
┌─────────────────────────────────────────────────────────────────────────┐
│  SYSTEM 1: `webhooks` table                                             │
│  Direction: INBOUND (Meta/Shopify → App)                                │
│  Purpose:   Staging buffer — raw payloads queue hone se pehle store     │
│  Scope:     Platform level (NO customer_id)                             │
│  Lifecycle: INSERT → process → DELETE (data loss risk!)                 │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  SYSTEM 2: `webhook_settings` + `webhook_logs`                        │
│  Direction: OUTBOUND (App → Customer's URL)                           │
│  Purpose:   Customer apna webhook URL configure karta hai               │
│  Scope:     Tenant level (customer_id + new_contact_id)                 │
│  Lifecycle: Config persists, logs are audit trail                     │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│  SYSTEM 3: `processed_webhooks`                                       │
│  Direction: DEDUP guard                                                 │
│  Purpose:   Same message_id dobara process na ho                        │
│  Scope:     Platform level                                            │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## SYSTEM 1: `webhooks` Table (Inbound Staging) — SQL Verified

### Production Schema (from tittuprod4.sql)

```sql
CREATE TABLE `webhooks` (
  `id`           bigint NOT NULL,
  `response`     text,                          -- Raw JSON payload
  `type`         varchar(191) DEFAULT NULL,     -- Event category (see below)
  `client_id`    varchar(191) DEFAULT NULL,     -- Added later for prioritization
  `webhook_status` varchar(191) DEFAULT NULL,   -- Processing state
  `message_id`   varchar(255) DEFAULT NULL,     -- Sometimes reused for other IDs
  `date`         timestamp NULL DEFAULT NULL
);

-- Index:
ADD KEY `idx_webhooks_prioritization` (`type`, `webhook_status`, `client_id`, `id`);
```

**Note:** No CREATE migration in legacy repo — table manually created in production.

### What Goes Into This Table (type values)

| type | Source | How it arrives | How it's processed |
|------|--------|----------------|-------------------|
| `Message` | Meta WhatsApp | `POST /message-uplink` | INSERT → `ProcessWebhookJob` (high queue) → **DELETE before process** → inbox/chatbot |
| `Status` | Meta WhatsApp | `POST /status-uplink` | `PersistStatusWebhookJob` INSERT → cron every minute → batch DELETE → `ProcessSingleWebhookJob` |
| `shopify_webhook` | Shopify | Shopify routes in WebhookController | INSERT → cron `update:shopifywebhook` drains |
| `shopify_extension` | Shopify extension | WalletController API | INSERT → cron drains |
| `Status Report` | Legacy/debug | Old callback endpoint | Mostly unused |
| `Message Uplink` | Legacy/debug | Old callback endpoint | Mostly unused |

### Legacy Flow Diagram

```
INBOUND MESSAGE:
  Meta POST /message-uplink
    → INSERT webhooks (type=Message, response=raw JSON)
    → dispatch ProcessWebhookJob(webhookId) on "high" queue
    → return 200 to Meta

  ProcessWebhookJob:
    → SELECT webhooks WHERE id = webhookId
    → WebhookMessageService.processMessage()
        → DELETE webhooks row FIRST  ← DATA LOSS RISK
        → parse JSON → find tenant via msg_to → new_contacts.phone
        → route to inbox / chatbot / FCM / new lead webhook

INBOUND STATUS:
  Meta POST /status-uplink
    → PersistStatusWebhookJob → INSERT webhooks (type=Status)
    → return 200

  Cron (every minute): webhook:process-statuses
    → SELECT webhooks WHERE type=Status LIMIT 100
    → DELETE batch
    → dispatch ProcessSingleWebhookJob chunks
    → update conversations.delivered_at / read_at / failed_at

SHOPIFY:
  Shopify POST → INSERT webhooks (type=shopify_webhook)
  Cron: update:shopifywebhook → read + delete + process orders
```

### Problems With Legacy `webhooks` Table

1. **Delete before process** — `WebhookMessageService.php:105` deletes row before processing. Fail = data gone forever.
2. **Mixed event sources** — Meta messages, Meta status, Shopify all in one table with different processing paths.
3. **No tenant_id** — Must parse JSON and lookup `new_contacts` by phone to find customer.
4. **Cron dependency for Status** — Messages use queue, Status uses cron polling. Inconsistent.
5. **No proper status tracking** — `webhook_status` column exists but underused.
6. **message_id column misused** — Shopify stores event type name in message_id field.

---

## SYSTEM 2: Customer Outbound Webhooks — SQL Verified

### `webhook_settings` (Customer Config)

```sql
CREATE TABLE `webhook_settings` (
  `id`               bigint UNSIGNED NOT NULL,
  `customer_id`      bigint UNSIGNED NOT NULL,
  `new_contact_id`   bigint UNSIGNED DEFAULT NULL,  -- Per WhatsApp line
  `url`              varchar(191) NOT NULL,           -- Customer's endpoint
  `description`      varchar(191) DEFAULT NULL,
  `secret_key`       varchar(191) NOT NULL,           -- HMAC signing
  `events`           json NOT NULL,                   -- Which events to send
  `status`           enum('active','inactive') DEFAULT 'active',
  `last_triggered_at` timestamp NULL,
  `audience_list_id` int UNSIGNED DEFAULT NULL,       -- Filter by list
  `created_at`       timestamp NULL,
  `updated_at`       timestamp NULL
);
```

**Purpose:** Customer configures "jab naya lead aaye, is URL pe POST karo"

**Used by:** `WebhookService::notifyNewLead()` — reads active webhooks for customer+line, sends HMAC-signed POST.

### `webhook_logs` (Delivery Audit)

```sql
CREATE TABLE `webhook_logs` (
  `id`                   bigint UNSIGNED NOT NULL,
  `customer_id`          int UNSIGNED NOT NULL,
  `new_contact_id`       bigint UNSIGNED DEFAULT NULL,
  `webhook_id`           bigint UNSIGNED DEFAULT NULL,  -- FK → webhook_settings.id
  `webhook_url`          varchar(191) DEFAULT NULL,
  `event_type`           varchar(191) NOT NULL,
  `payload`              text NOT NULL,
  `response_body`        text,
  `response_status`      int DEFAULT NULL,
  `error_message`        text,
  `status`               varchar(191) NOT NULL,
  `sent_at`              timestamp NOT NULL,
  `response_received_at` timestamp NULL,
  `created_at`           timestamp NULL,
  `updated_at`           timestamp NULL
);
```

**Purpose:** Every outbound delivery attempt logged — success, failure, response.

**These are NOT related to the `webhooks` staging table.** Completely separate concern.

---

## SYSTEM 3: `processed_webhooks` (Dedup)

```sql
CREATE TABLE `processed_webhooks` (
  `id`         bigint UNSIGNED NOT NULL,
  `message_id` varchar(191) NOT NULL,    -- UNIQUE
  `status`     varchar(191) NOT NULL,
  `created_at` timestamp NULL,
  `updated_at` timestamp NULL
);
ADD UNIQUE KEY `processed_webhooks_message_id_unique` (`message_id`);
```

**Purpose:** Prevent duplicate processing of same Meta message_id.

---

## MISSING TABLES — SQL Verified (No CREATE Migration in Legacy)

### `sub_replies` (Inbox Conversation Threads)

```sql
CREATE TABLE `sub_replies` (
  `id`                     bigint NOT NULL,
  `subscriber_id`          bigint DEFAULT NULL,
  `user_id`                bigint DEFAULT NULL,
  `sender_name`            text,
  `msg_from`               varchar(200) NOT NULL,    -- Customer phone
  `msg_to`                 varchar(200) NOT NULL,    -- Business WhatsApp number
  `status`                 tinyint(1) DEFAULT 0,
  `created_at`             timestamp NOT NULL,
  `updated_at`             timestamp NOT NULL,
  `replied_at`             timestamp NULL,
  `messaged_at`            datetime DEFAULT '1970-01-01',
  `response_type`          varchar(191) DEFAULT 'human_response',
  `active_ai_bot_id`       bigint UNSIGNED DEFAULT NULL,
  `lead_score`             int DEFAULT 0,
  `bant_data`              text,
  `qualification_status`   varchar(191) DEFAULT 'pending',
  `last_qualification_at`  timestamp NULL
);

-- Indexes:
PRIMARY KEY (`id`)
KEY `sub_replies_status_index` (`status`)
KEY `sub_replies_replied_at_index` (`replied_at`)
KEY `messaged_at` (`messaged_at`)
KEY `idx_sub_replies_msg_to_id` (`msg_to`, `id`)
```

**Tenant resolution:** NO customer_id in production dump. Tenant found via `msg_to` → `new_contacts.phone` → `customer_id`.

**New name:** `conversations` (thread level)

### `conversations` (Individual Messages in Thread)

```sql
CREATE TABLE `conversations` (
  `id`              bigint NOT NULL,
  `sub_reply_id`    bigint NOT NULL,
  `msg`             text,
  `status`          varchar(191) DEFAULT NULL,       -- unread, read, etc.
  `msg_id`          varchar(200) DEFAULT NULL,       -- Meta message ID
  `type`            varchar(10) DEFAULT NULL,         -- frnd, my, etc.
  `created_at`      timestamp NOT NULL,
  `updated_at`      timestamp NOT NULL,
  `new_status`      varchar(191) DEFAULT NULL,
  `sent_at`         timestamp NULL,
  `delivered_at`    timestamp NULL,
  `read_at`         timestamp NULL,
  `failed_at`       timestamp NULL,
  `failed_reason`   text,
  `subscriber_id`   bigint UNSIGNED DEFAULT NULL,
  `form_id`         bigint UNSIGNED DEFAULT NULL,
  `template_id`     bigint UNSIGNED DEFAULT NULL,
  `chatbot_id`      bigint UNSIGNED DEFAULT NULL,
  `flow_id`         bigint UNSIGNED DEFAULT NULL,
  `reply_msg`       varchar(191) DEFAULT NULL,
  `new_type`        varchar(191) DEFAULT NULL
);

-- Indexes:
PRIMARY KEY (`id`)
KEY `idx_conversations_subscriber_date` (`subscriber_id`, `created_at`)
KEY `idx_conversations_sub_reply_id_id` (`sub_reply_id`, `id`)
KEY `idx_conversations_sub_reply_id_status_type` (`sub_reply_id`, `status`, `type`)
```

**New name:** `messages` (individual message level)

---

## NEW PROJECT: How To Treat Each System

### Replace `webhooks` staging → `inbound_webhook_events` (Master DB)

**NOT a staging table. An immutable event log.**

```sql
CREATE TABLE inbound_webhook_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source          VARCHAR(50) NOT NULL,     -- meta_message, meta_status, shopify, razorpay
  event_type      VARCHAR(100) NULL,        -- Message, Status, orders/create, etc.
  idempotency_key VARCHAR(255) NULL,        -- message_id or shopify event id
  payload         JSON NOT NULL,            -- Raw payload (never delete)
  headers         JSON NULL,                -- Request headers for audit
  tenant_id       BIGINT UNSIGNED NULL,     -- Resolved after processing (nullable initially)
  whatsapp_line_id BIGINT UNSIGNED NULL,    -- Resolved from payload
  status          ENUM('received','processing','processed','failed','duplicate') DEFAULT 'received',
  processed_at    TIMESTAMP NULL,
  error_message   TEXT NULL,
  retry_count     TINYINT UNSIGNED DEFAULT 0,
  created_at      TIMESTAMP NOT NULL,

  UNIQUE KEY uq_idempotency (source, idempotency_key),
  KEY idx_status_created (status, created_at),
  KEY idx_tenant (tenant_id, created_at),
  KEY idx_source_type (source, event_type)
);
```

**New flow (no delete, no cron polling):**

```
Meta POST /webhooks/meta/messages
  → Verify HMAC signature
  → Check idempotency (source + message_id exists? → return 200, mark duplicate)
  → INSERT inbound_webhook_events (status=received)
  → Dispatch ProcessInboundWebhookJob on "critical" queue
  → Return 200 (< 100ms)

ProcessInboundWebhookJob:
  → UPDATE status = processing
  → Parse payload → resolve tenant_id + whatsapp_line_id
  → Route by source:
      meta_message  → HandleInboundMessage (inbox, chatbot, new lead)
      meta_status   → HandleDeliveryStatus (update message timestamps)
      shopify       → HandleShopifyEvent
  → UPDATE status = processed (or failed + error_message)
  → NEVER DELETE the row
```

### Keep Customer Outbound Separate → Tenant DB

```sql
-- Renamed from webhook_settings
CREATE TABLE webhook_subscriptions (
  id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  whatsapp_line_id  BIGINT UNSIGNED NULL,
  url               VARCHAR(500) NOT NULL,
  description       VARCHAR(255) NULL,
  secret_key        VARCHAR(255) NOT NULL,
  events            JSON NOT NULL,              -- ["new_lead", "message_received"]
  status            ENUM('active','inactive') DEFAULT 'active',
  audience_list_id  BIGINT UNSIGNED NULL,
  last_triggered_at TIMESTAMP NULL,
  created_at        TIMESTAMP NULL,
  updated_at        TIMESTAMP NULL
);

-- Renamed from webhook_logs
CREATE TABLE webhook_deliveries (
  id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subscription_id      BIGINT UNSIGNED NOT NULL,  -- FK → webhook_subscriptions
  whatsapp_line_id     BIGINT UNSIGNED NULL,
  event_type           VARCHAR(100) NOT NULL,
  payload              JSON NOT NULL,
  response_status      SMALLINT NULL,
  response_body        TEXT NULL,
  error_message        TEXT NULL,
  status               ENUM('pending','sent','failed','retrying') NOT NULL,
  attempt_count        TINYINT UNSIGNED DEFAULT 1,
  sent_at              TIMESTAMP NULL,
  response_received_at TIMESTAMP NULL,
  next_retry_at        TIMESTAMP NULL,
  created_at           TIMESTAMP NULL,
  updated_at           TIMESTAMP NULL,

  KEY idx_subscription (subscription_id, created_at),
  KEY idx_status_retry (status, next_retry_at)
);
```

### Keep Dedup on Master DB → `processed_events`

```sql
CREATE TABLE processed_events (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source          VARCHAR(50) NOT NULL,
  idempotency_key VARCHAR(255) NOT NULL,
  status          VARCHAR(50) NOT NULL,
  created_at      TIMESTAMP NOT NULL,

  UNIQUE KEY uq_source_key (source, idempotency_key)
);
```

---

## Side-by-Side Comparison

| Aspect | Legacy `webhooks` | New `inbound_webhook_events` |
|--------|-------------------|------------------------------|
| Purpose | Staging buffer | Immutable event log |
| Delete after process? | YES (data loss risk) | NEVER |
| Tenant ID | No — parse JSON | Resolved + stored |
| Processing | Queue + Cron mix | Queue only (event-driven) |
| Shopify + Meta mixed? | YES (same table) | NO — `source` column separates |
| Status tracking | `webhook_status` underused | Proper enum lifecycle |
| Retry | None | retry_count + failed status |
| Dedup | Separate `processed_webhooks` | Unique on source + idempotency_key |

| Aspect | Legacy `webhook_settings` + `webhook_logs` | New (same concept, cleaner) |
|--------|---------------------------------------------|------------------------------|
| Direction | OUTBOUND (App → Customer URL) | Same |
| DB | Tenant DB | Tenant DB |
| Rename | webhook_settings → webhook_subscriptions | |
| Rename | webhook_logs → webhook_deliveries | |
| Retry | Basic | Exponential backoff with next_retry_at |

---

## Inbox Tables: Legacy → New Mapping

| Legacy | New | Notes |
|--------|-----|-------|
| `sub_replies` | `conversations` | Thread per contact+line pair |
| `conversations` | `messages` | Individual messages |
| — | Add `tenant_id` | Legacy resolves via msg_to lookup |
| — | Add `whatsapp_line_id` | Direct FK instead of phone lookup |
| `msg_from` / `msg_to` | `contact_phone` / `line_phone` | Clearer naming |

---

## What NOT To Do

1. Do NOT put inbound Meta webhooks and customer outbound webhooks in same table.
2. Do NOT delete inbound events after processing — append-only log.
3. Do NOT use cron to poll inbound events — queue dispatch only.
4. Do NOT mix Shopify/Razorpay/Calendly with Meta in one table without `source` column.
5. Do NOT rename `webhook_settings`/`webhook_logs` to anything that sounds like inbound.

---

— End of Document —
