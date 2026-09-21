from typing import Any, Dict, List, Tuple

from openai import OpenAI
from openai import AuthenticationError

from providers.base_provider import BaseAIProvider


class OpenAIProvider(BaseAIProvider):
    def __init__(self, api_key: str):
        super().__init__(api_key)
        self.client = OpenAI(api_key=self.api_key)

    def validate_api_key(self) -> Tuple[bool, str]:
        try:
            self.client.models.list()
            return (True, "")
        except AuthenticationError as e:
            return (False, str(e))
        except Exception as e:
            return (False, str(e))

    def _chat_model_allowed(self, model_id: str) -> bool:
        mid = model_id.lower()
        if ":" in mid or "realtime" in mid or "audio" in mid:
            return False
        if "instruct" in mid:
            return False
        # exclude standalone vision models (e.g. gpt-4-vision-preview kept if not matching — user asked exclude vision alone)
        if mid.endswith("-vision") or mid.endswith("-vision-preview"):
            return False
        prefixes = ("gpt-4", "gpt-3.5", "o1", "o3", "o4", "chatgpt")
        return any(mid.startswith(p) for p in prefixes)

    def list_chat_models(self) -> List[Dict[str, Any]]:
        page = self.client.models.list()
        if hasattr(page, "data") and page.data is not None:
            models = list(page.data)
        else:
            models = list(page)
        out: List[Dict[str, Any]] = []
        for m in models:
            mid = getattr(m, "id", None) or ""
            if not self._chat_model_allowed(mid):
                continue
            created = getattr(m, "created", None) or 0
            out.append(
                {
                    "id": mid,
                    "name": mid,
                    "description": "",
                    "context_window": 0,
                    "_created": int(created),
                }
            )
        out.sort(key=lambda x: x["_created"], reverse=True)
        for item in out:
            item.pop("_created", None)
        return out

    def list_embedding_models(self) -> List[Dict[str, Any]]:
        dims = {
            "text-embedding-3-small": 1536,
            "text-embedding-3-large": 3072,
            "text-embedding-ada-002": 1536,
        }
        page = self.client.models.list()
        if hasattr(page, "data") and page.data is not None:
            models = list(page.data)
        else:
            models = list(page)
        out: List[Dict[str, Any]] = []
        for m in models:
            mid = getattr(m, "id", None) or ""
            if not mid.startswith("text-embedding-"):
                continue
            out.append(
                {
                    "id": mid,
                    "name": mid,
                    "dimensions": dims.get(mid, 1536),
                }
            )
        out.sort(key=lambda x: x["id"])
        return out

    def get_embedding(self, text: str, model: str) -> Tuple[List[float], int]:
        response = self.client.embeddings.create(input=text, model=model)
        vec = response.data[0].embedding
        total = response.usage.total_tokens if response.usage else 0
        return (vec, total)

    def get_embeddings_batch(self, texts: List[str], model: str) -> Tuple[List[List[float]], int]:
        if not texts:
            return ([], 0)
        response = self.client.embeddings.create(input=texts, model=model)
        embeddings = [d.embedding for d in response.data]
        total = response.usage.total_tokens if response.usage else 0
        return (embeddings, total)

    def chat_completion(
        self,
        messages: List[Dict[str, str]],
        model: str,
        temperature: float = 0.7,
        max_tokens: int = 1000,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        response = self.client.chat.completions.create(
            model=model,
            messages=messages,
            temperature=temperature,
            max_tokens=max_tokens,
            **kwargs,
        )
        content = response.choices[0].message.content or ""
        pt = response.usage.prompt_tokens if response.usage else 0
        ct = response.usage.completion_tokens if response.usage else 0
        return {
            "content": content,
            "prompt_tokens": pt,
            "completion_tokens": ct,
        }

    def get_model_pricing(self, model_id: str) -> Dict[str, float]:
        pricing = {
            "gpt-4o": {"input_per_1m": 2.50, "output_per_1m": 10.00, "embedding_per_1m": 0.0},
            "gpt-4o-mini": {"input_per_1m": 0.15, "output_per_1m": 0.60, "embedding_per_1m": 0.0},
            "gpt-4.1": {"input_per_1m": 2.00, "output_per_1m": 8.00, "embedding_per_1m": 0.0},
            "gpt-4.1-mini": {"input_per_1m": 0.40, "output_per_1m": 1.60, "embedding_per_1m": 0.0},
            "gpt-3.5-turbo": {"input_per_1m": 0.50, "output_per_1m": 1.50, "embedding_per_1m": 0.0},
            "o3-mini": {"input_per_1m": 1.10, "output_per_1m": 4.40, "embedding_per_1m": 0.0},
            "text-embedding-3-small": {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.02},
            "text-embedding-3-large": {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.13},
            "text-embedding-ada-002": {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.10},
        }
        return pricing.get(
            model_id,
            {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.0},
        )
