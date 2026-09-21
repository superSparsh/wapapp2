import json
import logging
from config import Config
from providers.provider_factory import get_provider


class IntentDetector:
    def __init__(self, api_key: str = None, provider: str = "openai", model_name: str = None):
        self.api_key = api_key or Config.OPENAI_API_KEY
        self.provider_name = provider or "openai"
        self.model_name = model_name or Config.MODEL_NAME
        self.provider = get_provider(self.provider_name, self.api_key)

    def detect_intent(self, query: str) -> dict:
        """
        Classifies the user query into entity_name_query, attribute_query, mixed_query, or general_query.
        Extracts key entities and attributes.
        """
        prompt = f"""Analyze the following user query for a RAG system. This is industry-agnostic (real estate, healthcare, e-commerce, retail, etc.).

Identify if the user is asking for:
1. 'entity_name_query': Searching for a specific entity by name (e.g., "Tell me about X", "Info on Product Y").
2. 'attribute_query': Searching by attributes (location, category, brand, feature, price range, etc.). IMPORTANT: Queries like "gokul road project", "clinic in Mumbai", "lab in Delhi", "stores near X" are attribute_query with location - the user is filtering by location.
3. 'mixed_query': Both entity name and attributes (e.g., "Store X in Mumbai", "Product Y under 500").
4. 'general_query': Greetings or generic questions.

Extract the following if present:
- 'entities': List of specific names mentioned (products, projects, stores, branches, etc.).
- 'attributes': Dictionary of any attribute type: 'location', 'category', 'brand', 'feature', 'service_area', etc. ALWAYS extract 'location' when the query mentions a place, road, area, or city (e.g. "gokul road" -> {{"location": "gokul road"}}, "clinic in Bangalore" -> {{"location": "bangalore"}}, "lab in Delhi" -> {{"location": "delhi"}}).

Return ONLY a JSON object with this structure:
{{
  "intent": "entity_name_query" | "attribute_query" | "mixed_query" | "general_query",
  "entities": ["name1", "name2"],
  "attributes": {{ "attribute_type": "value" }},
  "reasoning": "brief explanation"
}}

Query: "{query}"
"""

        try:
            extra = {}
            if self.provider_name == "openai":
                extra["response_format"] = {"type": "json_object"}
            logging.info(
                "IntentDetector detect_intent",
                extra={
                    "provider": self.provider_name,
                    "chat_model": self.model_name,
                    "query_len": len(query or ""),
                },
            )
            completion = self.provider.chat_completion(
                messages=[
                    {"role": "system", "content": "You are a query intent analyzer."},
                    {"role": "user", "content": prompt},
                ],
                model=self.model_name,
                temperature=0,
                max_tokens=1500,
                **extra,
            )
            return json.loads(completion["content"])
        except Exception as e:
            logging.error(
                "IntentDetector detect_intent failed",
                exc_info=True,
                extra={
                    "provider": self.provider_name,
                    "chat_model": self.model_name,
                    "error": str(e),
                },
            )
            return {
                "intent": "general_query",
                "entities": [],
                "attributes": {},
                "reasoning": f"Fallback due to error: {str(e)}"
            }
