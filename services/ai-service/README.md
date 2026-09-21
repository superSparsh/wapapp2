# AI Knowledge Base Service (FastAPI + ChromaDB)

Source of truth for Knowledge Base documents and RAG embeddings.

## Role

| Layer | Responsibility |
|-------|----------------|
| **Laravel (WapApp 2.0)** | Auth, tenant/bot identity, proxy HTTP only |
| **This service** | Chunking, embeddings, Chroma persistence, query |

**Never** store KB files/chunks as primary data in MySQL. Short `ai_bots.business_information` text may stay in DB; uploaded docs / URL scrapes / long manual content go here only.

## Collections

- Without bot: `client_{md5(customer_id)[:8]}`
- With bot: `bot_{md5(f"client_{customer_id}_bot_{bot_id}")[:12]}`

`customer_id` = tenant id string. `bot_id` = `AiBot.uuid`.

## Run

```bash
cd services/ai-service
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
export CHROMA_DB_PATH=/var/www/ai_env/chroma_data   # or a local path
uvicorn main:app --host 0.0.0.0 --port 5005
```

## Laravel env

```
PYTHON_AI_URL=http://127.0.0.1:5005
PYTHON_AI_TIMEOUT=90
PYTHON_AI_ENABLED=true
```

## Key endpoints

- `POST /upload_file`
- `POST /scrape_website`
- `POST /extract_text_from_file`
- `POST /structure_text`
- `POST /add_manual_content`
- `GET /knowledge_base/{client_id}`
- `GET /knowledge_base/{client_id}/download`
- `GET /client_storage_info/{client_id}`
- `DELETE /clear_client_data/{client_id}`
- `POST /reindex`
- `POST /process_query`

Dashboard lists KB via Laravel proxies that call these live — no local mirror table.
