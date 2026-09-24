# AI service (Python + Chroma)

The monolith talks to the Python AI / Knowledge Base service for RAG (`process_query`) and KB indexing.

```
services/
└── ai-service/   # FastAPI + Chroma (see ai-service/README.md)
```

Configure via `PYTHON_AI_URL` / `config/ai.php`. Inbox, campaigns, and templates run in the Laravel monolith only.
