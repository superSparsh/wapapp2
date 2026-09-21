# AI Knowledge Base Service (FastAPI + ChromaDB)

Source of truth for Knowledge Base documents and RAG embeddings.

## Role

| Layer | Responsibility |
|-------|----------------|
| **Laravel (WapApp 2.0)** | Auth, tenant/bot identity, proxy HTTP only |
| **This service** | Chunking, embeddings, Chroma persistence, query |

**Never** store KB files/chunks as primary data in MySQL.

## Collections

- Without bot: `client_{md5(customer_id)[:8]}`
- With bot: `bot_{md5(f"client_{customer_id}_bot_{bot_id}")[:12]}`

Legacy keys: `customer_id` = numeric `customers.id`, `bot_id` = `ai_bots.uid` (`uniqid()`).
Migrated WapApp 2.0 sends the same via `legacy_customer_id` + `legacy_bot_uid`.
New tenants: tenant slug + `AiBot.uuid`.

## Ubuntu / production install (IMPORTANT)

Use **python3** + a **project venv**. Do not `pip install` into the user site-packages.

```bash
cd /var/www/wapapp_v2.0/services/ai-service

# 1) Create & activate venv
python3 -m venv .venv
source .venv/bin/activate

# 2) Confirm you are inside the venv (should show .../ai-service/.venv/bin/pip)
which pip
which python

# 3) Upgrade pip inside venv, then install
python -m pip install --upgrade pip
python -m pip install -r requirements.txt

# 4) Chroma data directory (persistent)
sudo mkdir -p /var/www/ai_env/chroma_data
sudo chown -R "$USER":"$USER" /var/www/ai_env/chroma_data
export CHROMA_DB_PATH=/var/www/ai_env/chroma_data

# 5) Run (always with venv active, or use full path)
python -m uvicorn main:app --host 0.0.0.0 --port 5005
# or:
# .venv/bin/uvicorn main:app --host 0.0.0.0 --port 5005
```

Health check:
```bash
curl -s http://127.0.0.1:5005/docs | head
```

## Auto-start on server (systemd) — required for production

Laravel does **not** start this process. It only HTTP-calls `PYTHON_AI_URL`.  
For reboot / crash recovery, install the unit once:

```bash
# after venv + pip install succeed
sudo mkdir -p /var/www/ai_env/chroma_data
sudo chown -R ubuntu:www-data /var/www/ai_env/chroma_data

sudo cp /var/www/wapapp_v2.0/services/ai-service/deploy/wapapp-ai.service \
  /etc/systemd/system/wapapp-ai.service

# edit User/paths if your deploy user or app path differs
sudo nano /etc/systemd/system/wapapp-ai.service

sudo systemctl daemon-reload
sudo systemctl enable --now wapapp-ai
sudo systemctl status wapapp-ai
```

Useful commands:
```bash
sudo systemctl restart wapapp-ai
sudo journalctl -u wapapp-ai -f
```

Laravel `.env` (same machine):
```
PYTHON_AI_URL=http://127.0.0.1:5005
PYTHON_AI_ENABLED=true
```

Flow: browser → Laravel → `http://127.0.0.1:5005` → Chroma on disk (`CHROMA_DB_PATH`).

### If you already polluted user packages

```bash
deactivate 2>/dev/null
rm -rf .venv
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

Do **not** run `/home/ubuntu/.local/bin/uvicorn` — that skips the venv.

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
