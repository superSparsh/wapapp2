import os
from typing import Any, Dict, List, Tuple

import requests

from providers.base_provider import BaseAIProvider

_BASE_URL = os.getenv("TITTU_GATEWAY_URL", "https://gateway.tittu.in").rstrip("/")


class TittuGatewayProvider(BaseAIProvider):
    """Internal Tittu AI Gateway. Bearer API key is required."""

    def validate_api_key(self) -> Tuple[bool, str]:
        if not (self.api_key or "").strip():
            return (False, "API key is required.")
        try:
            response = self._post({
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
        ok, error = self.validate_api_key()
        if not ok:
            raise ValueError(error or "Invalid Tittu Gateway API key.")
        return [
            {
                "id": "auto",
                "name": "Auto (gateway failover)",
                "description": "gateway.tittu.in picks the upstream provider",
                "context_window": 0,
            }
        ]

    def list_embedding_models(self) -> List[Dict[str, Any]]:
        return []

    def get_embedding(self, text: str, model: str) -> Tuple[List[float], int]:
        raise ValueError("Tittu Gateway does not provide embeddings. Use it for chat replies.")

    def get_embeddings_batch(self, texts: List[str], model: str) -> Tuple[List[List[float]], int]:
        raise ValueError("Tittu Gateway does not provide embeddings. Use it for chat replies.")

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

        target = (model or "").strip()
        if target and target.lower() != "auto":
            body["targetProvider"] = target

        response = self._post(body)
        response.raise_for_status()
        data = response.json() if response.content else {}

        return {
            "content": data.get("text") or "",
            "prompt_tokens": 0,
            "completion_tokens": 0,
        }

    def _post(self, body: Dict[str, Any]) -> requests.Response:
        return requests.post(
            f"{_BASE_URL}/v1/generate",
            json=body,
            headers={
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            timeout=60,
        )
