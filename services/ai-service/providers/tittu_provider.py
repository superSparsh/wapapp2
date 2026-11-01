import os
from typing import Any, Dict, List, Optional, Tuple

import requests

from providers.base_provider import BaseAIProvider

_BASE_URL = os.getenv("TITTU_GATEWAY_URL", "https://gateway.tittu.in").rstrip("/")

_FALLBACK_CHAT_MODELS: List[Dict[str, Any]] = [
    {
        "id": "auto",
        "name": "Auto (gateway failover)",
        "description": "gateway.tittu.in picks the upstream provider",
        "context_window": 0,
    },
    {"id": "groq-free", "name": "Groq Free", "description": "", "context_window": 0},
    {"id": "gemini-free", "name": "Gemini Free", "description": "", "context_window": 0},
    {"id": "mistral-free", "name": "Mistral Free", "description": "", "context_window": 0},
]

_FALLBACK_EMBEDDING_MODELS: List[Dict[str, Any]] = [
    {"id": "auto", "name": "Auto (embeddings pool)", "dimensions": 0},
    {"id": "voyage-embeddings", "name": "Voyage AI", "dimensions": 0},
    {"id": "jina-embeddings", "name": "Jina AI", "dimensions": 0},
]

# Meta names / empty values must NOT be sent as gateway targetProvider
# (gateway returns "Unknown provider: tittu" if targetProvider=tittu).
_AUTO_TARGET_ALIASES = {
    "",
    "auto",
    "tittu",
    "tittu_gateway",
    "tittu-gateway",
    "gateway",
    "openai",
    "gemini",
    "azure",
    "google",
}


def _resolve_target_provider(model: str) -> Optional[str]:
    target = (model or "").strip()
    if not target:
        return None
    if target.lower().replace(" ", "_") in _AUTO_TARGET_ALIASES:
        return None
    return target


class TittuGatewayProvider(BaseAIProvider):
    """Internal Tittu AI Gateway. Bearer API key is required. Supports chat + embeddings."""

    def validate_api_key(self) -> Tuple[bool, str]:
        if not (self.api_key or "").strip():
            return (False, "API key is required.")
        try:
            response = self._post_generate({
                "prompt": "Ping",
                "maxTokens": 5,
                "temperature": 0,
            })
        except Exception as exc:
            return (False, str(exc))

        if response.status_code == 401:
            return (False, "Invalid Tittu Gateway API key.")
        if not response.ok:
            return (False, response.text[:300] or "Tittu Gateway rejected the key.")

        data = response.json() if response.content else {}
        if data.get("text") or data.get("providerId"):
            return (True, "")
        return (False, "Tittu Gateway returned an empty response.")

    def list_chat_models(self) -> List[Dict[str, Any]]:
        return list(_FALLBACK_CHAT_MODELS)

    def list_embedding_models(self) -> List[Dict[str, Any]]:
        return list(_FALLBACK_EMBEDDING_MODELS)

    def get_embedding(self, text: str, model: str) -> Tuple[List[float], int]:
        vectors, tokens = self.get_embeddings_batch([text], model)
        if not vectors:
            raise ValueError("Tittu Gateway embedding response did not include a vector.")
        return vectors[0], tokens

    def get_embeddings_batch(self, texts: List[str], model: str) -> Tuple[List[List[float]], int]:
        cleaned = [str(t).strip() for t in texts if str(t).strip()]
        if not cleaned:
            raise ValueError("Embedding text is required.")

        body: Dict[str, Any] = {
            "input": cleaned if len(cleaned) > 1 else cleaned[0],
            "text": cleaned[0] if len(cleaned) == 1 else cleaned,
        }
        target = _resolve_target_provider(model)
        if target:
            body["model"] = target
            body["targetProvider"] = target

        response = self._post_embeddings(body)
        if not response.ok:
            detail = (response.text or "").strip()[:300] or f"HTTP {response.status_code}"
            raise ValueError(f"Tittu Gateway embeddings failed: {detail}")
        data = response.json() if response.content else {}
        if not isinstance(data, dict):
            raise ValueError("Tittu Gateway returned an invalid embedding response.")

        vectors = self._extract_embedding_vectors(data, expected=len(cleaned))
        if not vectors:
            raise ValueError("Tittu Gateway embedding response did not include a vector.")

        return vectors, self._extract_tokens(data)

    def chat_completion(
        self,
        messages: List[Dict[str, str]],
        model: str,
        temperature: float = 0.7,
        max_tokens: int = 1000,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        system_parts: List[str] = []
        turns: List[str] = []
        for message in messages:
            content = (message.get("content") or "").strip()
            if not content:
                continue
            if message.get("role") == "system":
                system_parts.append(content)
                continue
            role = "Assistant" if message.get("role") == "assistant" else "User"
            turns.append(f"{role}: {content}")

        body: Dict[str, Any] = {
            "prompt": "\n\n".join(turns) if turns else "Hello",
            "temperature": temperature,
            "maxTokens": max_tokens,
        }
        if system_parts:
            body["systemPrompt"] = "\n\n".join(system_parts)

        target = _resolve_target_provider(model)
        if target:
            body["targetProvider"] = target

        response = self._post_generate(body)
        if not response.ok:
            detail = (response.text or "").strip()[:300] or f"HTTP {response.status_code}"
            raise ValueError(f"Tittu Gateway chat failed: {detail}")
        data = response.json() if response.content else {}

        return {
            "content": data.get("text") or "",
            "prompt_tokens": 0,
            "completion_tokens": 0,
        }

    def _headers(self) -> Dict[str, str]:
        return {
            "Authorization": f"Bearer {self.api_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        }

    def _post_generate(self, body: Dict[str, Any]) -> requests.Response:
        return requests.post(
            f"{_BASE_URL}/v1/generate",
            json=body,
            headers=self._headers(),
            timeout=60,
        )

    def _post_embeddings(self, body: Dict[str, Any]) -> requests.Response:
        response = requests.post(
            f"{_BASE_URL}/v1/embeddings",
            json=body,
            headers=self._headers(),
            timeout=60,
        )
        if response.status_code != 404:
            return response

        return requests.post(
            f"{_BASE_URL}/v1/embed",
            json=body,
            headers=self._headers(),
            timeout=60,
        )

    def _extract_embedding_vectors(self, data: Dict[str, Any], expected: int) -> List[List[float]]:
        candidates = [
            data.get("data"),
            data.get("embeddings"),
            data.get("embedding"),
            data.get("vector"),
        ]

        for candidate in candidates:
            vectors = self._coerce_vectors(candidate)
            if vectors:
                return vectors[:expected] if expected > 0 else vectors

        nested = data.get("data")
        if isinstance(nested, dict):
            vectors = self._coerce_vectors(nested.get("embedding") or nested.get("embeddings"))
            if vectors:
                return vectors[:expected] if expected > 0 else vectors

        return []

    def _coerce_vectors(self, value: Any) -> List[List[float]]:
        if value is None:
            return []

        # Single vector: [0.1, 0.2]
        if isinstance(value, list) and value and all(
            isinstance(v, (int, float)) or (isinstance(v, str) and str(v).replace(".", "", 1).isdigit())
            for v in value
        ):
            return [[float(v) for v in value]]

        # OpenAI-style: [{"embedding": [...]}, ...]
        if isinstance(value, list):
            out: List[List[float]] = []
            for row in value:
                if isinstance(row, dict):
                    emb = row.get("embedding") or row.get("vector")
                    if isinstance(emb, list) and emb:
                        out.append([float(v) for v in emb])
                elif isinstance(row, list) and row:
                    out.append([float(v) for v in row])
            return out

        return []

    def _extract_tokens(self, data: Dict[str, Any]) -> int:
        usage = data.get("usage") if isinstance(data.get("usage"), dict) else {}
        for key in ("total_tokens", "prompt_tokens", "tokens"):
            candidate = usage.get(key) if key in usage else data.get(key)
            if isinstance(candidate, (int, float)):
                return int(candidate)
        return 0
