import hashlib
import re
from typing import Dict, Type

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
    "tittugateway": "tittu",
    "gateway": "tittu",
}

_PROVIDER_CLASSES: Dict[str, Type[BaseAIProvider]] = {
    "openai": OpenAIProvider,
    "gemini": GeminiProvider,
    "tittu": TittuGatewayProvider,
}


def _normalize_provider(provider: str) -> str:
    raw = (provider or "").strip().lower()
    # Drop invisible / punctuation noise from forms or DB values.
    key = re.sub(r"[^a-z0-9]+", "_", raw).strip("_")
    return _PROVIDER_ALIASES.get(key, key)


def supported_providers() -> list[str]:
    return sorted(_PROVIDER_CLASSES.keys())


def get_provider(provider: str, api_key: str) -> BaseAIProvider:
    if not api_key:
        raise ValueError(
            "API key is required for this operation. "
            "Configure a provider key in AI Assistant → API Settings."
        )

    normalized = _normalize_provider(provider)
    cache_key = f"{normalized}:{hashlib.sha256(api_key.encode()).hexdigest()[:16]}"
    if cache_key not in _cache:
        cls = _PROVIDER_CLASSES.get(normalized)
        if cls is None:
            raise ValueError(
                f"Unknown provider: {provider!r} (normalized={normalized!r}). "
                f"Supported: {', '.join(supported_providers())}."
            )
        _cache[cache_key] = cls(api_key)
    return _cache[cache_key]


def clear_cache() -> None:
    _cache.clear()
