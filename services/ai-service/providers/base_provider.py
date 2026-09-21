from abc import ABC, abstractmethod
from typing import Any, Dict, List, Optional, Tuple


class BaseAIProvider(ABC):
    def __init__(self, api_key: str):
        self.api_key = api_key

    @abstractmethod
    def validate_api_key(self) -> Tuple[bool, str]:
        """Returns (is_valid, error_message)"""
        pass

    @abstractmethod
    def list_chat_models(self) -> List[Dict[str, Any]]:
        """Fetch chat models live from provider API.
        Returns: [{'id': str, 'name': str, 'description': str, 'context_window': int}]"""
        pass

    @abstractmethod
    def list_embedding_models(self) -> List[Dict[str, Any]]:
        """Returns: [{'id': str, 'name': str, 'dimensions': int}]"""
        pass

    @abstractmethod
    def get_embedding(self, text: str, model: str) -> Tuple[List[float], int]:
        """Returns (embedding_vector, token_count)"""
        pass

    @abstractmethod
    def get_embeddings_batch(self, texts: List[str], model: str) -> Tuple[List[List[float]], int]:
        """Returns (list_of_embeddings, total_token_count)"""
        pass

    @abstractmethod
    def chat_completion(
        self,
        messages: List[Dict[str, str]],
        model: str,
        temperature: float = 0.7,
        max_tokens: int = 1000,
        **kwargs: Any,
    ) -> Dict[str, Any]:
        """Returns: {'content': str, 'prompt_tokens': int, 'completion_tokens': int}"""
        pass

    def get_model_pricing(self, model_id: str) -> Dict[str, float]:
        """Returns {'input_per_1m': float, 'output_per_1m': float, 'embedding_per_1m': float}
        Returns zeros if unknown — caller should store NULL cost, not zero."""
        return {"input_per_1m": 0.0, "output_per_1m": 0.0, "embedding_per_1m": 0.0}

    def calculate_cost(
        self,
        model_id: str,
        prompt_tokens: int = 0,
        completion_tokens: int = 0,
        embedding_tokens: int = 0,
    ) -> Optional[float]:
        """Returns cost in USD or None if pricing unknown."""
        pricing = self.get_model_pricing(model_id)
        if not any(pricing.values()):
            return None
        cost = (
            prompt_tokens / 1_000_000 * pricing["input_per_1m"]
            + completion_tokens / 1_000_000 * pricing["output_per_1m"]
            + embedding_tokens / 1_000_000 * pricing["embedding_per_1m"]
        )
        return round(cost, 8)
