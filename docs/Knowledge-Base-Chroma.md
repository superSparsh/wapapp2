# Knowledge Base architecture (WapApp 2.0)

## Rule

**ChromaDB (via Python FastAPI) is the source of truth for Knowledge Base documents.**

The Laravel app must **not** store uploaded KB files / scraped pages / long manual content as primary KB data in MySQL.

| Allowed in MySQL | Not allowed as KB SoT |
|------------------|------------------------|
| API provider keys | PDF/DOC chunks |
| Bot config + short `business_information` | URL scrape corpus |
| Token usage logs | Manual “Add Sources” long text |

## Dual layer

1. **Application (Laravel)** — auth, tenant id, bot uuid, resolve API keys, **proxy only**
2. **Intelligence (`services/ai-service`)** — embed, index, list, download, clear, reindex, `process_query`

## Collections

- No bot: `client_{md5(client_id)[:8]}`
- With bot: `bot_{md5("client_{client_id}_bot_{bot_id}")[:12]}`

**Migrated tenants (legacy Continuity):** Laravel sends Chroma keys as:

- `client_id` = `tenants.settings.legacy_customer_id` (numeric customer id from old app)
- `bot_id` = `ai_bots.legacy_bot_id` (numeric legacy `ai_bots.id`)

New (non-migrated) tenants use tenant slug + `AiBot.uuid`.

`CHROMA_DB_PATH` on the AI service **must** point at the same Chroma directory the legacy stack used, or chunk counts stay 0 even with correct IDs.

## Dashboard

AI Assistant → Knowledge Base tab:

- Lists chunks via `GET /openai-key/knowledge_base` → AI `GET /knowledge_base/{tenant}`
- Stats via `client_storage_info`
- Add Sources: scrape / extract → `add_manual_content` (indexes to Chroma)
- Download / Clear / Refresh hit the AI service live
- Empty/error if AI/Chroma is down — **do not invent rows from MySQL**

## Env

```
PYTHON_AI_URL=http://127.0.0.1:5005
PYTHON_AI_TIMEOUT=90
PYTHON_AI_ENABLED=true
```

See `services/ai-service/README.md` for running FastAPI + Chroma.

**Ubuntu tip:** use `python3 -m venv .venv` and install **inside** the venv (`python -m pip install -r requirements.txt`). Never use system/`~/.local` uvicorn.
