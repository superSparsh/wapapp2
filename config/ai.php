<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Python AI / RAG service (FastAPI + ChromaDB)
    |--------------------------------------------------------------------------
    |
    | Knowledge Base documents are NEVER stored in MySQL. This service is the
    | source of truth for embeddings and chunk storage (Chroma collections).
    | The Laravel app only authenticates and proxies.
    |
    */
    'python_url' => rtrim((string) env('PYTHON_AI_URL', 'http://127.0.0.1:5005'), '/'),
    'timeout' => (int) env('PYTHON_AI_TIMEOUT', 90),
    'connect_timeout' => (int) env('PYTHON_AI_CONNECT_TIMEOUT', 10),
    'enabled' => (bool) env('PYTHON_AI_ENABLED', true),
    'internal_secret' => (string) env('AI_INTERNAL_API_SECRET', env('INTERNAL_API_SECRET', '')),
];
