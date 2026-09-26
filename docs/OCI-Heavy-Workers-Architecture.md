# OCI Heavy Workers — High-Level Architecture

**Goal:** Campaign sending, delivery-status webhooks, and large contact imports never block the web app — and never “break” if OCI is slow/down (safe fallback).

---

## 1. Split of responsibility

```
┌─────────────────────────────┐         Redis queues          ┌──────────────────────────────┐
│  Main WapApp (web / API)    │  campaign / status / import   │  OCI Heavy Worker VM(s)      │
│  - UI, auth, admin          │ ─────────────────────────────▶│  HORIZON_ROLE=oci-heavy      │
│  - Orchestration            │                               │  - campaign workers          │
│  - DB writes (source of     │◀──── results via same DB ─────│  - status workers            │
│    truth)                   │                               │  - import workers (≥30k)     │
│  HORIZON_ROLE=web           │                               │                              │
│  (light queues only)        │                               │  Same Redis + MySQL as app   │
└─────────────────────────────┘                               └──────────────────────────────┘
```

| Workload | Queue | Where |
|----------|-------|--------|
| Campaign recipient send | `campaign` | **OCI** |
| Alibaba delivery / read status | `status` | **OCI** |
| Contact CSV import | `import` if **≥ 30,000** rows, else `default` | OCI / web |
| Inbound chat messages / chatbot | sync + `messages` / `chatbot` | **Main app** (latency) |
| UI, billing, admin | — | **Main app** |

---

## 2. Design rules (smooth / no surprises)

1. **One Redis, one MySQL** — workers are consumers, not a second app with its own DB.
2. **Feature flag** — `OCI_WORKERS_ENABLED=true` only after workers are healthy.
3. **Horizon roles** — web never steals heavy queues when OCI is on; OCI never runs web-critical chat path.
4. **Fallback** — set `HORIZON_ROLE=all` on main (or disable OCI flag) if OCI VM is down; jobs stay in Redis, nothing is lost.
5. **Idempotent jobs** — duplicate status / re-queued sends must not corrupt recipient state (existing handlers + unique keys).
6. **Ephemeral Container Instances (optional)** — set `OCI_EPHEMERAL_CONTAINERS=true` so a shared campaign CI is **created** when the first campaign starts sending and **destroyed** after the last campaign completes/cancels (grace + queue-drain check). Pause does not destroy. Concurrent campaigns share one instance (refcount).
7. **Import threshold** — small CSVs stay local; ≥30k go to `import` queue (long timeout).

---

## 3. Server mapping (what you received)

| Role | Host |
|------|------|
| App / worker candidate | `instance-voiceai-prod-app-001` — `140.238.241.148` / `10.0.0.203` |
| DB | `instance-voiceai-prod-db-001` — `80.225.245.133` / `10.0.0.253` |
| OCIR | `bmue9nxcdpso/wapapp-prod` (mumbai) |

**Recommended baseline:** long-lived Horizon on OCI VM (`HORIZON_ROLE=oci-heavy`).  
**Optional cost control:** ephemeral CI mode for the **campaign** worker only (status/import can stay on the VM or a separate always-on process).

---

## 4. Rollout (zero-drama)

1. Deploy code + config to **main** and **OCI** (same release).
2. Start OCI workers with `HORIZON_ROLE=oci-heavy` (dry: watch queues fill).
3. On main set `HORIZON_ROLE=web` + `OCI_WORKERS_ENABLED=true`.
4. Smoke: 1 small campaign, 1 status webhook, 1 small import, 1 ≥30k import (staging).
5. Only then raise campaign concurrency on OCI.
6. (Optional) Enable ephemeral: `OCI_EPHEMERAL_CONTAINERS=true` + `OCI_EPHEMERAL_DRIVER=http` with OCI API credentials / image / subnet. Start with `OCI_EPHEMERAL_DRIVER=log` to verify hooks without creating CIs.

---

## 5. Env cheat-sheet

See `deploy/oci/.env.oci-workers.example`, `deploy/oci/README.md`, and `config/oci-workers.php`.

| Host | Key env |
|------|---------|
| Main web (after cutover) | `HORIZON_ROLE=web` `OCI_WORKERS_ENABLED=true` |
| OCI workers | `HORIZON_ROLE=oci-heavy` (flag stays false on worker) |
| Fallback / single host | `HORIZON_ROLE=all` `OCI_WORKERS_ENABLED=false` |
| Ephemeral CI (optional) | `OCI_EPHEMERAL_CONTAINERS=true` `OCI_EPHEMERAL_DRIVER=http` + `OCI_*` credentials |

---

## 6. Ephemeral campaign Container Instance lifecycle

```
Campaign → Sending ──► onCampaignStarted (refcount++)
                         └─► EnsureOciCampaignWorkerJob (provisioning queue)
                               └─► create CI if OCID missing

Campaign → Completed/Cancelled ──► onCampaignFinished (refcount--)
                         └─► if refcount==0 → TeardownOciCampaignWorkerJob (+grace)
                               └─► delete CI if queue empty
```

Hooks: `CampaignSendService::queueCampaign`, `refreshCampaignCompletion`, `CampaignService::cancel` / resume `toggle`, `CampaignResendService::resendFailed`.

---

## 7. What we intentionally do NOT do

- Spawn **one CI per campaign** (concurrent campaigns share one refcounted instance).
- Give workers a separate database.
- Move inbound chat messages to OCI (hurts chatbot reply latency).
- Destroy CI on **pause** (resume must stay warm).