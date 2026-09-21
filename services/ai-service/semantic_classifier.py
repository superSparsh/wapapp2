import json
import logging
from config import Config
from typing import List, Dict

from providers.provider_factory import get_provider


class SemanticClassifier:
    def __init__(self, api_key: str = None, provider: str = "openai", model_name: str = None):
        self.api_key = api_key or Config.OPENAI_API_KEY
        self.provider_name = provider or "openai"
        self.model_name = model_name or Config.MODEL_NAME
        self.provider = get_provider(self.provider_name, self.api_key)

    def classify_chunk(self, text: str, domain: str = "general") -> dict:
        """
        Analyzes a text chunk to extract structured entity information and attributes.
        The 'domain' parameter (e.g., 'real estate', 'healthcare') helps tailor the extraction.
        """
        domain_context = f"The domain is {domain}." if domain != "general" else ""
        
        prompt = f"""Analyze the following text chunk and extract structured information. {domain_context}
Identify the primary entity and its attributes. This is industry-agnostic (real estate, healthcare, e-commerce, services, etc.).

Return ONLY a JSON object with this structure:
{{
  "has_entity": true | false,
  "entity_name": "Name of the entity if found",
  "entity_type": "Type of entity (e.g. project, hospital, product, store, branch)",
  "attributes": {{
    "location": {{ "road": "...", "area": "...", "city": "...", "region": "..." }},
    "category": "...",
    "brand": "...",
    "tags": ["tag1", "tag2"],
    "features": ["feature1", "feature2"],
    "service_area": "..."
  }},
  "confidence": 0.0 to 1.0
}}

Note: Extract exact names and specific attributes from the text. Adapt to the domain (real estate, healthcare, e-commerce, etc.).

Text Chunk:
\"\"\"{text}\"\"\"
"""

        try:
            extra = {}
            if self.provider_name == "openai":
                extra["response_format"] = {"type": "json_object"}
            logging.info(
                "SemanticClassifier classify_chunk",
                extra={
                    "provider": self.provider_name,
                    "chat_model": self.model_name,
                    "domain": domain,
                    "text_len": len(text or ""),
                },
            )
            out = self.provider.chat_completion(
                messages=[
                    {"role": "system", "content": "You are a semantic data extractor."},
                    {"role": "user", "content": prompt},
                ],
                model=self.model_name,
                temperature=0,
                max_tokens=2000,
                **extra,
            )
            return json.loads(out["content"])
        except Exception as e:
            logging.error(
                "SemanticClassifier classify_chunk failed",
                exc_info=True,
                extra={
                    "provider": self.provider_name,
                    "chat_model": self.model_name,
                    "domain": domain,
                    "error": str(e),
                },
            )
            return {
                "has_entity": False,
                "entity_name": None,
                "entity_type": None,
                "attributes": {},
                "confidence": 0
            }
