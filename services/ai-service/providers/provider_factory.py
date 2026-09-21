import hashlib
from typing import Dict

from providers.base_provider import BaseAIProvider
from providers.gemini_provider import GeminiProvider
from providers.openai_provider import OpenAIProvider

_cache: Dict[str, BaseAIProvider] = {}


def get_provider(provider: str, api_key: str) -> BaseAIProvider:
    if not api_key:
        raise ValueError(
            "API key is required for this operation. "
            "Configure a provider key in AI Assistant → API Settings."
        )
    cache_key = f"{provider}:{hashlib.sha256(api_key.encode()).hexdigest()[:16]}"
    if cache_key not in _cache:
        if provider == "openai":
            _cache[cache_key] = OpenAIProvider(api_key)
        elif provider == "gemini":
            _cache[cache_key] = GeminiProvider(api_key)
        else:
            raise ValueError(f"Unknown provider: {provider}")
    return _cache[cache_key]


def clear_cache() -> None:
    _cache.clear()
