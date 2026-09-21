from typing import Any, Dict, List, Tuple

import requests

from providers.base_provider import BaseAIProvider

try:
    from google import genai
except ImportError:  # pragma: no cover
    genai = None  # type: ignore


_DISPLAY_NAMES: Dict[str, str] = {
    "gemini-2.5-pro": "Gemini 2.5 Pro",
    "gemini-2.5-flash": "Gemini 2.5 Flash",
    "gemini-2.0-flash": "Gemini 2.0 Flash",
    "gemini-2.0-flash-lite": "Gemini 2.0 Flash Lite",
    "gemini-1.5-pro": "Gemini 1.5 Pro",
    "gemini-1.5-flash": "Gemini 1.5 Flash",
}

_EMBEDDING_DIMS: Dict[str, int] = {
    "gemini-embedding-2": 3072,
    "gemini-embedding-001": 3072,
    "text-embedding-004": 768,
}


class GeminiProvider(BaseAIProvider):
    def __init__(self, api_key: str):
        super().__init__(api_key)
        if genai is None:
            raise ImportError("google-genai is not installed. pip install google-genai>=1.0.0")
        self._client = genai.Client(api_key=self.api_key)

    def _fetch_models_rest(self) -> List[Dict[str, Any]]:
        url = "https://generativelanguage.googleapis.com/v1beta/models"
        r = requests.get(url, params={"key": self.api_key}, timeout=60)
        r.raise_for_status()
        data = r.json()
        return data.get("models") or []

    def validate_api_key(self) -> Tuple[bool, str]:
        try:
            # Prefer SDK list; fall back to REST
            try:
                it = self._client.models.list()
                for _ in it:
                    break
                return (True, "")
            except Exception:
                models = self._fetch_models_rest()
                if models:
                    return (True, "")
                return (False, "No models returned for this API key")
        except Exception as e:
            return (False, str(e))

    def list_chat_models(self) -> List[Dict[str, Any]]:
        raw = self._fetch_models_rest()
        out: List[Dict[str, Any]] = []
        for m in raw:
            name = m.get("name") or ""
            if "embedding" in name.lower():
                continue
            if "gemini" not in name.lower():
                continue
            methods = m.get("supportedGenerationMethods") or []
            if "generateContent" not in methods:
                continue
            clean_id = name.split("/", 1)[-1] if "/" in name else name
            display = _DISPLAY_NAMES.get(clean_id, clean_id.replace("-", " ").title())
            out.append(
                {
                    "id": clean_id,
                    "name": display,
                    "description": m.get("description") or "",
                    "context_window": int(m.get("inputTokenLimit") or 0),
                }
            )
        out.sort(key=lambda x: x["id"], reverse=True)
        return out

    def list_embedding_models(self) -> List[Dict[str, Any]]:
        raw = self._fetch_models_rest()
        out: List[Dict[str, Any]] = []
        for m in raw:
            name = m.get("name") or ""
            if "embedding" not in name.lower():
                continue
            clean_id = name.split("/", 1)[-1] if "/" in name else name
            out.append(
                {
                    "id": clean_id,
                    "name": clean_id,
                    "dimensions": _EMBEDDING_DIMS.get(clean_id, 3072),
                }
            )
        out.sort(key=lambda x: x["id"])
        return out

    def get_embedding(self, text: str, model: str) -> Tuple[List[float], int]:
        response = self._client.models.embed_content(model=model, contents=text)
        emb = self._embedding_values(response, index=0)
        token_estimate = max(1, int(len(text.split()) * 1.3))
        return (emb, token_estimate)

    def get_embeddings_batch(self, texts: List[str], model: str) -> Tuple[List[List[float]], int]:
        if not texts:
            return ([], 0)
        response = self._client.models.embed_content(model=model, contents=texts)
        embeddings: List[List[float]] = []
        n_emb = len(getattr(response, "embeddings", None) or [])
        if n_emb >= len(texts):
            for i in range(len(texts)):
                embeddings.append(self._embedding_values(response, index=i))
        else:
            # Fallback: one embedding returned or API shape differs — embed per chunk
            for t in texts:
                ev, _ = self.get_embedding(t, model)
                embeddings.append(ev)
        token_estimate = max(1, int(sum(len(t.split()) for t in texts) * 1.3))
        return (embeddings, token_estimate)

    def _embedding_values(self, response: Any, index: int = 0) -> List[float]:
        if hasattr(response, "embeddings") and response.embeddings:
            e = response.embeddings[index]
            if hasattr(e, "values"):
                return list(e.values)
            if isinstance(e, dict) and "values" in e:
                return list(e["values"])
        if hasattr(response, "embedding") and response.embedding is not None:
            e = response.embedding
            if hasattr(e, "values"):
                return list(e.values)
        raise ValueError("Unexpected embed_content response shape")

    def chat_completion(
        self,
        messages: List[Dict[str, str]],
        model: str,
        temperature: float = 0.7,
        max_tokens: int = 1000,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        gemini_contents: List[Dict[str, Any]] = []
        system_text = ""
        consumed_system = False
        for msg in messages:
            role = msg.get("role", "")
            content = msg.get("content", "") or ""
            if role == "system":
                system_text = content
            elif role == "user":
                if system_text and not consumed_system:
                    content = f"{system_text}\n\n{content}"
                    consumed_system = True
                    system_text = ""
                gemini_contents.append({"role": "user", "parts": [{"text": content}]})
            elif role == "assistant":
                gemini_contents.append({"role": "model", "parts": [{"text": content}]})

        config: Dict[str, Any] = {"temperature": temperature, "max_output_tokens": max_tokens}
        # Ignore OpenAI-only kwargs (e.g. response_format)
        kwargs.pop("response_format", None)
        response = self._client.models.generate_content(
            model=model,
            contents=gemini_contents,
            config=config,
        )

        text_out = ""
        if hasattr(response, "text") and response.text:
            text_out = response.text
        elif hasattr(response, "candidates") and response.candidates:
            c0 = response.candidates[0]
            if hasattr(c0, "content") and c0.content:
                parts = getattr(c0.content, "parts", None) or []
                text_out = "".join(getattr(p, "text", "") or "" for p in parts)

        um = getattr(response, "usage_metadata", None)
        prompt_tokens = 0
        completion_tokens = 0
        if um is not None:
            prompt_tokens = getattr(um, "prompt_token_count", None) or 0
            completion_tokens = (
                getattr(um, "candidates_token_count", None)
                or getattr(um, "candidates_tokens", None)
                or 0
            )

        return {
            "content": text_out,
            "prompt_tokens": int(prompt_tokens),
            "completion_tokens": int(completion_tokens),
        }

    def get_model_pricing(self, model_id: str) -> Dict[str, float]:
        pricing = {
            "gemini-2.5-pro": {"input_per_1m": 1.25, "output_per_1m": 10.00, "embedding_per_1m": 0.0},
            "gemini-2.5-flash": {"input_per_1m": 0.30, "output_per_1m": 2.50, "embedding_per_1m": 0.0},
            "gemini-2.0-flash": {"input_per_1m": 0.10, "output_per_1m": 0.40, "embedding_per_1m": 0.0},
            "gemini-2.0-flash-lite": {"input_per_1m": 0.075, "output_per_1m": 0.30, "embedding_per_1m": 0.0},
            "gemini-1.5-pro": {"input_per_1m": 1.25, "output_per_1m": 5.00, "embedding_per_1m": 0.0},
            "gemini-1.5-flash": {"input_per_1m": 0.075, "output_per_1m": 0.30, "embedding_per_1m": 0.0},
            "gemini-embedding-001": {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.0},
            "gemini-embedding-2": {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.0},
        }
        return pricing.get(
            model_id,
            {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.0},
        )
