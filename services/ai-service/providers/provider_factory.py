import hashlib
from typing import Dict

from providers.base_provider import BaseAIProvider
from providers.gemini_provider import GeminiProvider
from providers.openai_provider import OpenAIProvider
from providers.tittu_provider import TittuGatewayProvider

_cache: Dict[str, BaseAIProvider] = {}

_PROVIDER_ALIASES = {
    "openai": "openai",
    "open_ai": "openai",
    "gpt": "openai",
    "gemini": "gemini",
    "google": "gemini",
    "google_gemini": "gemini",
    "tittu": "tittu",
    "tittu_gateway": "tittu",
    "tittu-gateway": "tittu",
    "gateway": "tittu",
}


def _normalize_provider(provider: str) -> str:
    key = (provider or "").strip().lower().replace(" ", "_")
    return _PROVIDER_ALIASES.get(key, key)


def get_provider(provider: str, api_key: str) -> BaseAIProvider:
    if not api_key:
        raise ValueError(
            "API key is required for this operation. "
            "Configure a provider key in AI Assistant → API Settings."
        )

    normalized = _normalize_provider(provider)
    cache_key = f"{normalized}:{hashlib.sha256(api_key.encode()).hexdigest()[:16]}"
    if cache_key not in _cache:
        if normalized == "openai":
            _cache[cache_key] = OpenAIProvider(api_key)
        elif normalized == "gemini":
            _cache[cache_key] = GeminiProvider(api_key)
        elif normalized == "tittu":
            _cache[cache_key] = TittuGatewayProvider(api_key)
        else:
            raise ValueError(
                f"Unknown provider: {provider}. Supported: openai, gemini, tittu."
            )
    return _cache[cache_key]


def clear_cache() -> None:
    _cache.clear()
