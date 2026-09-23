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
6. **No direct Container Instance spawn in v1** — you were given **VMs**. Long-lived Horizon on OCI VM is simpler and more reliable than create/destroy CI.
7. **Import threshold** — small CSVs stay local; ≥30k go to `import` queue (long timeout).

---

## 3. Server mapping (what you received)

| Role | Host |
|------|------|
| App / worker candidate | `instance-voiceai-prod-app-001` — `140.238.241.148` / `10.0.0.203` |
| DB | `instance-voiceai-prod-db-001` — `80.225.245.133` / `10.0.0.253` |
| OCIR | `bmue9nxcdpso/wapapp-prod` (mumbai) |

**Recommended v1:** run **Horizon oci-heavy** on the app OCI VM (or a dedicated worker process set), sharing Redis with current production app.  
**Later:** push the same image to OCIR and move to Container Instances if you need burst billing.

---

## 4. Rollout (zero-drama)

1. Deploy code + config to **main** and **OCI** (same release).
2. Start OCI workers with `HORIZON_ROLE=oci-heavy` (dry: watch queues fill).
3. On main set `HORIZON_ROLE=web` + `OCI_WORKERS_ENABLED=true`.
4. Smoke: 1 small campaign, 1 status webhook, 1 small import, 1 ≥30k import (staging).
5. Only then raise campaign concurrency on OCI.

---

## 5. Env cheat-sheet

See `deploy/oci/.env.oci-workers.example`, `deploy/oci/README.md`, and `config/oci-workers.php`.

| Host | Key env |
|------|---------|
| Main web (after cutover) | `HORIZON_ROLE=web` `OCI_WORKERS_ENABLED=true` |
| OCI workers | `HORIZON_ROLE=oci-heavy` (flag stays false on worker) |
| Fallback / single host | `HORIZON_ROLE=all` `OCI_WORKERS_ENABLED=false` |

---

## 6. What we intentionally do NOT do in v1

- Spawn/destroy OCI Container Instances per campaign (fragile until IAM + networking proven).
- Give workers a separate database.
- Move inbound chat messages to OCI (hurts chatbot reply latency).
