import chromadb
from chromadb.config import Settings
from config import Config
import hashlib
import re
import PyPDF2
import docx
import pandas as pd
import io
import os
import logging
import requests
import threading
import uuid
from datetime import datetime, date
from typing import List, Dict, Union, BinaryIO, Tuple, Optional
from urllib.parse import urljoin, urlparse
from bs4 import BeautifulSoup
import time
import json
from intent_detector import IntentDetector
from semantic_classifier import SemanticClassifier
from providers.provider_factory import get_provider


class ProviderEmbeddingFunction:
    """Chroma-compatible embedding callable; delegates to provider batch embeddings."""

    def __init__(self, provider_instance, model: str):
        self._provider = provider_instance
        self._model = model

    def name(self) -> str:
        """
        Chroma calls embedding_function.name() when validating embedding function conflicts.
        Returning "default" avoids hard failures when older collections were created without
        a custom embedding_function name.
        """
        return "default"

    def __call__(self, input: List[str]) -> List[List[float]]:
        if not input:
            return []
        embeddings, _ = self._provider.get_embeddings_batch(input, self._model)
        return embeddings


class RAGEngine:
    def __init__(
        self,
        api_key: str = None,
        model_name: str = None,
        temperature: float = None,
        provider: str = "openai",
        embedding_model: str = None,
        bot_id_str: str = None,
        customer_id: str = None,
    ):
        # Use provided API key or fall back to default from config.
        # Read-only Chroma ops (list / storage / delete) may run without a key.
        self.api_key = api_key or Config.OPENAI_API_KEY
        self.provider_name = provider or "openai"
        self.embedding_model_name = embedding_model or Config.EMBEDDING_MODEL
        self.bot_id_str = bot_id_str
        self.customer_id = customer_id

        # COST OPTIMIZATION: Response cache to avoid duplicate API calls
        self._response_cache = {}

        # Use provided model settings or fall back to defaults from config
        self.model_name = model_name or Config.MODEL_NAME
        self.temperature = temperature if temperature is not None else Config.TEMPERATURE

        self._provider = None
        self._embedding_func = None
        self._intent_detector = None
        self._semantic_classifier = None

        logging.info(
            "RAGEngine init",
            extra={
                "provider": self.provider_name,
                "chat_model": self.model_name,
                "embedding_model": self.embedding_model_name,
                "customer_id": self.customer_id,
                "bot_id": self.bot_id_str,
                "has_api_key": bool(self.api_key),
            },
        )

        # Initialize Chroma client with persistence
        self.chroma_client = chromadb.PersistentClient(
            path=Config.CHROMA_DB_PATH,
            settings=Settings(
                anonymized_telemetry=False
            )
        )

    @property
    def provider(self):
        if self._provider is None:
            self._provider = get_provider(self.provider_name, self.api_key)
        return self._provider

    @property
    def embedding_func(self):
        if self._embedding_func is None:
            self._embedding_func = ProviderEmbeddingFunction(self.provider, self.embedding_model_name)
        return self._embedding_func

    @property
    def intent_detector(self):
        if self._intent_detector is None:
            self._intent_detector = IntentDetector(
                api_key=self.api_key,
                provider=self.provider_name,
                model_name=self.model_name,
            )
        return self._intent_detector

    @property
    def semantic_classifier(self):
        if self._semantic_classifier is None:
            self._semantic_classifier = SemanticClassifier(
                api_key=self.api_key,
                provider=self.provider_name,
                model_name=self.model_name,
            )
        return self._semantic_classifier

    def _log_usage_background(
        self,
        request_type: str,
        model: str,
        prompt_tokens: int = 0,
        completion_tokens: int = 0,
        embedding_tokens: int = 0,
        session_id: Optional[str] = None,
    ) -> None:
        def _run() -> None:
            try:
                import requests as req

                base = (Config.LARAVEL_BASE_URL or "").rstrip("/")
                url = f"{base}/api/ai/internal/log-usage"
                payload = {
                    "customer_id": self.customer_id,
                    "bot_id": self.bot_id_str,
                    "provider": self.provider_name,
                    "model": model,
                    "request_type": request_type,
                    "prompt_tokens": prompt_tokens,
                    "completion_tokens": completion_tokens,
                    "embedding_tokens": embedding_tokens,
                    "internal_secret": Config.INTERNAL_API_SECRET,
                }
                if session_id:
                    payload["session_id"] = session_id
                req.post(url, json=payload, timeout=5)
            except Exception:
                pass

        threading.Thread(target=_run, daemon=True).start()

    def _generate_entity_root_id(self, client_id: str, entity_type: str, entity_name: str) -> str:
        """Generates a deterministic unique ID for an entity."""
        if not entity_name:
            return ""
        base = f"{client_id}_{entity_type or 'generic'}_{entity_name}"
        # Lowercase, replace non-alphanumeric with underscores
        clean_id = re.sub(r'[^a-z0-9_]', '_', base.lower())
        # Collapse multiple underscores
        return re.sub(r'_+', '_', clean_id).strip('_')

    def _normalize_attributes(self, attributes: dict) -> dict:
        """Normalizes attributes for any industry. Extracts aliases from nested/compound values."""
        normalized = {}
        for attr_name, attr_val in attributes.items():
            if not attr_val:
                continue
            if isinstance(attr_val, dict):
                # Nested (e.g. location: {road, area, city}) - flatten and build aliases
                aliases = []
                for sub_key, sub_val in attr_val.items():
                    if sub_val and len(str(sub_val).strip()) > 2:
                        v = str(sub_val).lower().strip()
                        normalized[f"attr_{attr_name}_{sub_key}"] = v
                        aliases.append(v)
                        if sub_key in ("road", "area") and "-" in str(sub_val):
                            aliases.extend(re.split(r'[-/]', v))
                if aliases:
                    normalized[f"attr_{attr_name}_aliases"] = ", ".join(list(set(a for a in aliases if len(a) > 2)))
            elif isinstance(attr_val, list):
                normalized[f"attr_{attr_name}"] = ", ".join(str(v).lower() for v in attr_val if v)
            else:
                normalized[f"attr_{attr_name}"] = str(attr_val).lower()
        return normalized

    def _calculate_entity_score(self, metadata: dict, intent_info: dict) -> float:
        """Calculates confidence score (0.0–1.0) for any entity. Industry-agnostic."""
        score = 0.0
        query_text = intent_info.get("query", "").lower()
        entities = [str(e).lower().strip() for e in intent_info.get("entities", [])]
        attrs = intent_info.get("attributes", {})
        
        # 1. Name Match (Weight: 0.6)
        entity_name = str(metadata.get("entity_name_normalized", "")).lower()
        if entities:
            name_score = 0.0
            for name in entities:
                if name == entity_name:
                    name_score = max(name_score, 1.0)
                elif name in entity_name:
                    name_score = max(name_score, 0.7)
            score += name_score * 0.6
            
        # 2. Attribute Match (Weight: 0.3) - dynamic: check any attr_* that matches extracted attributes
        attr_hits = 0
        attr_total = 0
        for attr_type, attr_val in attrs.items():
            if not attr_val:
                continue
            attr_total += 1
            val = str(attr_val).lower().strip()
            hit = False
            for meta_key, meta_val in metadata.items():
                if not meta_key.startswith(f"attr_{attr_type}") and meta_key != f"attr_{attr_type}":
                    continue
                mv = str(meta_val).lower()
                if val in mv or mv in val:
                    hit = True
                    break
            if hit:
                attr_hits += 1
            elif val in str(metadata.get(f"attr_{attr_type}", "")).lower():
                attr_hits += 1
        if attr_total > 0:
            score += (attr_hits / attr_total) * 0.3
            
        # 3. Proximity/Alias Match (Weight: 0.15) - any attr with aliases/area/city
        query_words = list(set(w for w in query_text.split() if len(w) > 2))
        for attr_val in attrs.values():
            if attr_val:
                query_words.extend(w for w in str(attr_val).lower().split() if len(w) > 2)
        query_words = list(set(query_words))
        proximity_hit = False
        for meta_key, meta_val in metadata.items():
            if "_aliases" in meta_key or "_area" in meta_key or "_city" in meta_key:
                mv = str(meta_val).lower()
                for word in query_words:
                    if word in mv:
                        proximity_hit = True
                        break
                if proximity_hit:
                    break
        if proximity_hit:
            score += 0.15
        return min(score, 1.0)

    def _process_pdf(self, file_content: BinaryIO) -> List[str]:
        """Process PDF file and return list of text chunks."""
        pdf_reader = PyPDF2.PdfReader(file_content)
        texts = []
        for page in pdf_reader.pages:
            text = page.extract_text()
            if text.strip():
                texts.append(text)
        return texts

    def _process_docx(self, file_content: BinaryIO) -> List[str]:
        """Process DOCX file and return list of text chunks."""
        doc = docx.Document(file_content)
        texts = []
        for para in doc.paragraphs:
            if para.text.strip():
                texts.append(para.text)
        return texts

    def _process_txt(self, file_content: BinaryIO) -> List[str]:
        """Process TXT file and return list of text chunks."""
        text = file_content.read().decode('utf-8')
        # Split by paragraphs
        paragraphs = [p.strip() for p in text.split('\n\n') if p.strip()]
        return paragraphs

    def _process_excel(self, file_content: BinaryIO) -> List[str]:
        """Process Excel file and return list of text chunks."""
        df = pd.read_excel(file_content)
        texts = []
        # Convert each row to a string representation
        for _, row in df.iterrows():
            text = " | ".join(str(val) for val in row if pd.notna(val))
            if text.strip():
                texts.append(text)
        return texts

    def process_file(self, file_content: BinaryIO, file_type: str) -> List[str]:
        """Process different file types and return list of text chunks."""
        processors = {
            'pdf': self._process_pdf,
            'docx': self._process_docx,
            'txt': self._process_txt,
            'xlsx': self._process_excel,
            'xls': self._process_excel
        }
        
        if file_type.lower() not in processors:
            raise ValueError(f"Unsupported file type: {file_type}")
        
        return processors[file_type.lower()](file_content)

    def crawl_website(self, start_url: str, max_pages: int = 15) -> List[Dict[str, str]]:
        """Scrape websites starting from start_url up to max_pages."""
        domain = urlparse(start_url).netloc
        visited = set()
        queue = [start_url]
        results = []

        while queue and len(visited) < max_pages:
            url = queue.pop(0)
            if url in visited:
                continue
            
            try:
                visited.add(url)
                response = requests.get(url, timeout=10, headers={'User-Agent': 'Mozilla/5.0'})
                if response.status_code != 200:
                    continue
                
                soup = BeautifulSoup(response.text, 'lxml')
                
                # Remove script and style elements
                for script in soup(["script", "style"]):
                    script.extract()

                # Get text
                text = soup.get_text(separator='\n')
                
                # Break into lines and remove leading and trailing whitespace
                lines = (line.strip() for line in text.splitlines())
                # Break multi-headlines into a line each
                chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
                # Drop blank lines
                clean_text = "\n".join(chunk for chunk in chunks if chunk)

                if clean_text:
                    results.append({
                        "url": url,
                        "text": clean_text
                    })

                # Find internal links
                for a in soup.find_all('a', href=True):
                    next_url = urljoin(url, a['href'])
                    # Clean the URL (remove fragment)
                    next_url = next_url.split('#')[0]
                    parsed_next = urlparse(next_url)
                    
                    if parsed_next.netloc == domain and next_url not in visited:
                        if next_url.endswith(('.pdf', '.jpg', '.png', '.jpeg', '.zip')):
                            continue
                        queue.append(next_url)
                
                # Small delay to be polite
                time.sleep(0.5)

            except Exception as e:
                print(f"Error scraping {url}: {str(e)}")
                continue


        return results

    def _split_text(self, text: str, chunk_size: int = 4000, overlap: int = 200) -> List[str]:
        """Split text into smaller chunks to avoid token limits."""
        if not text or len(text) <= chunk_size:
            return [text] if text else []
        
        chunks = []
        # Try to split by newlines first to keep logical separation
        lines = text.split('\n')
        current_chunk = ""
        
        for line in lines:
            # If current line fits in current chunk
            if len(current_chunk) + len(line) + 1 <= chunk_size:
                current_chunk += (line + '\n')
            else:
                # Flush current chunk if not empty
                if current_chunk:
                    chunks.append(current_chunk.strip())
                
                # If the line itself is larger than chunk_size, hard split it
                if len(line) > chunk_size:
                    # Recursive split for the giant line
                    start = 0
                    while start < len(line):
                        end = min(start + chunk_size, len(line))
                        chunks.append(line[start:end])
                        start += chunk_size - overlap
                        if start >= len(line):
                            break
                    current_chunk = ""
                else:
                    current_chunk = line + '\n'
        
        if current_chunk:
            chunks.append(current_chunk.strip())
            
        return chunks

    def add_file(self, client_id: str, file_content: BinaryIO, file_type: str, metadata: Dict = None, bot_id: str = None, domain: str = "general") -> bool:
        """Add a file to the collection."""
        try:
            # Process the file
            texts = self.process_file(file_content, file_type)
            
            if not texts:
                raise ValueError("No text content extracted from file")

            # Prepare metadata
            if not metadata:
                metadata = {"source": "default"}
            
            # Add source file information to metadata
            metadata["file_type"] = file_type
            metadata["chunk_count"] = len(texts)
            
            # Add documents with metadata
            return self.add_documents(client_id, texts, [metadata] * len(texts), bot_id=bot_id, domain=domain)
            
        except Exception as e:
            print(f"Error processing file: {str(e)}")
            raise

    def _get_collection_name(self, client_id: str, bot_id: str = None) -> str:
        if not bot_id:
            return f"client_{hashlib.md5(client_id.encode()).hexdigest()[:8]}"
        base = f"client_{client_id}_bot_{bot_id}"
        return f"bot_{hashlib.md5(base.encode()).hexdigest()[:12]}"

    def create_collection(self, client_id: str, bot_id: str = None):
        collection_name = self._get_collection_name(client_id, bot_id)
        return self.chroma_client.get_or_create_collection(
            name=collection_name,
            embedding_function=self.embedding_func
        )

    def _split_scraped_by_page(self, doc: str, base_meta: dict) -> Tuple[List[str], List[dict]]:
        """Split combined scraped content (URL: ... newline --- newline) into per-page documents with URL metadata."""
        if "---" not in doc or "URL:" not in doc:
            return [doc], [base_meta]
        # Support both "\n\n---\n\n" and "\n---\n" as separators
        parts = re.split(r'\n\s*---\s*\n', doc)
        pages_docs = []
        pages_meta = []
        for part in parts:
            part = part.strip()
            if not part:
                continue
            lines = part.split("\n", 2)
            page_url = ""
            if len(lines) >= 1 and lines[0].strip().upper().startswith("URL:"):
                page_url = lines[0].replace("URL:", "").strip()
                part = "\n".join(lines[1:]).strip() if len(lines) > 1 else ""
            if not part:
                continue
            meta = base_meta.copy()
            meta["source"] = f"Scraped: {page_url}" if page_url else base_meta.get("source", "default")
            meta["page_url"] = page_url
            pages_docs.append(part)
            pages_meta.append(meta)
        return (pages_docs, pages_meta) if pages_docs else ([doc], [base_meta])

    def add_documents(self, client_id: str, documents: list[str], metadata: list[dict] = None, bot_id: str = None, domain: str = "general") -> bool:
        if not metadata:
            metadata = [{"source": "default"}] * len(documents)
        elif len(metadata) == 1:
            if not metadata[0]:
                metadata[0] = {"source": "default"}
            metadata = [metadata[0]] * len(documents)
        elif len(metadata) != len(documents):
            raise ValueError(f"Number of metadata entries ({len(metadata)}) must match number of documents ({len(documents)})")
        else:
            for meta in metadata:
                if meta is not None and "source" not in meta:
                    meta["source"] = "default"

        # Split combined scraped content by page so each page is chunked separately (avoids mixed-entity chunks)
        expanded_docs = []
        expanded_meta = []
        for doc, meta in zip(documents, metadata):
            if meta.get("source", "").startswith("Scraped:") or ("\n\n---\n\n" in doc and "URL:" in doc):
                page_docs, page_meta = self._split_scraped_by_page(doc, meta)
                expanded_docs.extend(page_docs)
                expanded_meta.extend(page_meta)
            else:
                expanded_docs.append(doc)
                expanded_meta.append(meta)
        documents = expanded_docs
        metadata = expanded_meta

        # Split documents into chunks if they exceed size limits
        final_docs = []
        final_meta = []
        
        for doc, meta in zip(documents, metadata):
            chunks = self._split_text(doc)
            for i, chunk in enumerate(chunks):
                if not chunk.strip():
                    continue
                
                chunk_meta = meta.copy()
                
                # Skip semantic classification when upload already has complete metadata (e.g. from knowledge base JSON re-ingest)
                has_complete_metadata = (
                    meta.get("entity_root_id") and
                    meta.get("entity_name") and
                    any(k.startswith("attr_") for k in meta.keys())
                )
                if has_complete_metadata:
                    chunk_meta["entity_name_normalized"] = str(meta.get("entity_name", "")).lower().strip()
                    if len(chunks) > 1:
                        chunk_meta["chunk_index"] = i
                        chunk_meta["total_chunks"] = len(chunks)
                    final_docs.append(chunk)
                    final_meta.append(chunk_meta)
                    continue
                
                # ENHANCEMENT: Semantic classification for structured metadata
                semantic_data = self.semantic_classifier.classify_chunk(chunk, domain=domain)
                confidence = semantic_data.get("confidence", 0)
                
                if semantic_data.get("has_entity") and confidence >= 0.6:
                    e_name = semantic_data.get("entity_name") or ""
                    e_type = semantic_data.get("entity_type") or ""
                    
                    chunk_meta["entity_name"] = str(e_name)
                    chunk_meta["entity_type"] = str(e_type)
                    
                    # NEW: Normalized entity name for better searching
                    chunk_meta["entity_name_normalized"] = str(e_name).lower().strip()
                    
                    # FIX 1: Deterministic Entity Root ID
                    chunk_meta["entity_root_id"] = self._generate_entity_root_id(
                        client_id, e_type, e_name
                    )
                    
                    # Attribute normalization: works for any industry
                    attrs = semantic_data.get("attributes", {})
                    normalized_attrs = self._normalize_attributes(attrs)
                    # Preserve and merge existing metadata from upload (knowledge base JSON, etc.)
                    for key, val in meta.items():
                        if key.startswith("attr_") and val and key not in normalized_attrs:
                            normalized_attrs[key] = str(val).lower() if not isinstance(val, (list, dict)) else val
                        elif key.startswith("attr_") and "_aliases" in key and val:
                            existing = set(a.strip().lower() for a in str(normalized_attrs.get(key, "")).split(",") if a.strip())
                            existing.update(a.strip().lower() for a in str(val).split(",") if a.strip() and len(a.strip()) > 2)
                            if existing:
                                normalized_attrs[key] = ", ".join(sorted(existing))
                    chunk_meta.update(normalized_attrs)
                    
                    # Flatten remaining attributes
                    for key, value in attrs.items():
                        if isinstance(value, dict):
                            for sub_key, sub_value in value.items():
                                if sub_value:
                                    # Ensure "attr_" prefix for low-level keys
                                    chunk_meta[f"attr_{key}_{sub_key}"] = str(sub_value)
                        elif isinstance(value, list):
                            chunk_meta[f"attr_{key}"] = ", ".join(map(str, value))
                        elif value:
                            chunk_meta[f"attr_{key}"] = str(value)
                else:
                    if semantic_data.get("has_entity"):
                        print(f"Skipping entity metadata due to low confidence: {confidence}")
                
                # Add chunking info to metadata
                if len(chunks) > 1:
                    chunk_meta["chunk_index"] = i
                    chunk_meta["total_chunks"] = len(chunks)
                
                final_docs.append(chunk)
                final_meta.append(chunk_meta)

        if not final_docs:
            print(f"No valid text chunks to add for client {client_id}")
            return True

        collection = self.create_collection(client_id, bot_id=bot_id)
        
        # Generate unique IDs for vector store objects
        ids = [
            f"doc_{hashlib.md5(doc.encode()).hexdigest()[:12]}_{i}"
            for i, doc in enumerate(final_docs)
        ]

        total_embed_tokens = 0
        batch_size = 64
        all_embeddings: List[List[float]] = []
        for i in range(0, len(final_docs), batch_size):
            batch_docs = final_docs[i : i + batch_size]
            vecs, t = self.provider.get_embeddings_batch(batch_docs, self.embedding_model_name)
            total_embed_tokens += t
            all_embeddings.extend(vecs)

        self._log_usage_background(
            request_type="embedding",
            model=self.embedding_model_name,
            embedding_tokens=total_embed_tokens,
        )

        collection.add(
            embeddings=all_embeddings,
            documents=final_docs,
            metadatas=final_meta,
            ids=ids
        )
        print(f"Added {len(final_docs)} documents with deterministic entity_root_ids for client {client_id}")
        return True

    def query(self, client_id: str, query_text: str, conversation_id: str = None, k: int = 2, system_prompt: str = None, bot_id: str = None, chat_history: list = None) -> str:
        # FIX 5: Enhanced Response Cache Key
        # Includes bot_id and system_prompt to ensure isolation
        cache_str = f"{client_id}_{bot_id or 'none'}_{query_text.lower().strip()}_{system_prompt or 'default'}"
        cache_key = hashlib.md5(cache_str.encode()).hexdigest()
        
        if cache_key in self._response_cache:
            return self._response_cache[cache_key]

        rag_session = str(uuid.uuid4())

        logging.info(
            "RAGEngine query start",
            extra={
                "provider": self.provider_name,
                "chat_model": self.model_name,
                "embedding_model": self.embedding_model_name,
                "customer_id": self.customer_id,
                "bot_id": bot_id,
                "client_id": client_id,
                "query_len": len(query_text or ""),
            },
        )

        # Fast-path: Greetings only (industry-agnostic). All other intents use LLM for universal compatibility.
        intent_info = None
        query_lower = query_text.lower()
        if any(greet in query_lower for greet in ["hello", "hi", "good morning", "good evening"]):
            intent_info = {"intent": "general_query", "entities": [], "attributes": {}, "reasoning": "Fast-path: Greeting detected."}

        # LLM intent detection for all queries (works for any industry: real estate, healthcare, e-commerce, etc.)
        if not intent_info:
            intent_info = self.intent_detector.detect_intent(query_text)
        
        intent_info["query"] = query_text
        intent = intent_info.get("intent", "general_query")
        extracted_attrs = intent_info.get("attributes", {})
        
        # Fallback: extract location from query when intent is entity_name_query but query has location-like terms
        LOCATION_INDICATORS = {"road", "area", "street", "location", "near", "in", "at"}
        if not extracted_attrs.get("location") and any(ind in query_lower for ind in LOCATION_INDICATORS):
            # Use significant words from query for location filtering (e.g. "gokul road project" -> ["gokul"])
            ADDRESS_STOP = {"road", "street", "main", "area", "near", "on", "at", "lane", "ave", "project", "the"}
            fallback_words = [w for w in re.split(r'\s+', query_lower) if len(w) > 2 and w not in ADDRESS_STOP]
            if fallback_words:
                extracted_attrs = dict(extracted_attrs)
                extracted_attrs["location"] = " ".join(fallback_words)
                intent_info["attributes"] = extracted_attrs
        collection = self.create_collection(client_id, bot_id=bot_id)

        # 2. Two-Stage Retrieval Strategy
        context_chunks = []
        matched_entity_root_ids = set()
        
        # Universal attribute mapping: works for any industry (real estate, healthcare, e-commerce, etc.)
        # LLM intent detector returns attributes; we map to metadata keys. Unknown types use fallback.
        ATTRIBUTE_MAP = {
            "location": ["location_road", "location_city", "location_area", "location_aliases"],
            "category": ["category"],
            "feature": ["features", "tags"],
            "brand": ["brand"],
            "service_area": ["service_area"],
        }
        
        # Step 1: Attribute/Metadata Filter (ChromaDB doesn't support $contains; we filter in Python)
        if (intent in ["attribute_query", "mixed_query"] and extracted_attrs) or extracted_attrs.get("location"):
            ADDRESS_STOPWORDS = {"road", "street", "main", "area", "near", "on", "at", "lane", "ave"}
            search_words = []
            for attr_type, attr_val in extracted_attrs.items():
                if attr_val:
                    val_lower = str(attr_val).lower().strip()
                    meta_keys = ATTRIBUTE_MAP.get(attr_type, [attr_type, f"{attr_type}_aliases"])
                    if isinstance(meta_keys, str):
                        meta_keys = [meta_keys]
                    if " " in val_lower:
                        words = [w for w in re.split(r'\s+', val_lower) if len(w) > 2]
                        if attr_type == "location":
                            words = [w for w in words if w not in ADDRESS_STOPWORDS]
                        if not words:
                            words = [val_lower]
                        search_words.extend((w, meta_keys) for w in words)
                    else:
                        search_words.append((val_lower, meta_keys))
            entities_to_search = intent_info.get("entities", [])

            if search_words or entities_to_search:
                try:
                    # ChromaDB doesn't support $contains for metadata; fetch candidates then filter in Python
                    all_data = collection.get(limit=500, include=["metadatas"])
                    if all_data and all_data.get("metadatas"):
                        for meta in all_data["metadatas"]:
                            match = False
                            for word, meta_keys in search_words:
                                for key in meta_keys:
                                    attr_key = f"attr_{key}"
                                    mv = str(meta.get(attr_key, "")).lower()
                                    if word in mv:
                                        match = True
                                        break
                                if match:
                                    break
                            if not match and entities_to_search:
                                for name in entities_to_search:
                                    if str(name).lower() in str(meta.get("entity_name_normalized", "")).lower():
                                        match = True
                                        break
                            if match:
                                eid = meta.get("entity_root_id")
                                if eid:
                                    matched_entity_root_ids.add(eid)
                except Exception:
                    pass

        # Step 2: Vector Search Fallback (Only if metadata results are EMPTY)
        # FIX 3: Prevent unnecessary vector search
        if not matched_entity_root_ids:
            # If entity name is known, filter in Python (ChromaDB doesn't support $contains)
            query_entities = intent_info.get("entities", [])
            if query_entities:
                try:
                    all_data = collection.get(limit=500, include=["metadatas"])
                    if all_data and all_data.get("metadatas"):
                        for meta in all_data["metadatas"]:
                            en = str(meta.get("entity_name_normalized", "")).lower()
                            for n in query_entities:
                                if str(n).lower().strip() in en:
                                    eid = meta.get("entity_root_id")
                                    if eid:
                                        matched_entity_root_ids.add(eid)
                                    break
                except Exception:
                    pass

            # Standard Vector Search (if names didn't find anything)
            if not matched_entity_root_ids:
                # For location/area queries, retrieve more so we get ALL matching entities (industry-agnostic)
                is_location_query = intent in ["attribute_query", "mixed_query"] and bool(extracted_attrs.get("location"))
                retrieval_k = max(k, 200) if is_location_query else (max(k, 10) if intent != "general_query" else k)
                query_emb, embed_tok = self.provider.get_embedding(
                    query_text, self.embedding_model_name
                )
                self._log_usage_background(
                    request_type="embedding",
                    model=self.embedding_model_name,
                    embedding_tokens=embed_tok,
                    session_id=rag_session,
                )
                vector_results = collection.query(
                    query_embeddings=[query_emb], n_results=retrieval_k
                )
                for meta in vector_results['metadatas'][0]:
                    eid = meta.get("entity_root_id")
                    if eid: matched_entity_root_ids.add(eid)
                
                # Fallback to individual vector-hit chunks only if no entities ever found
                if not matched_entity_root_ids and vector_results['documents'][0]:
                    for doc, meta in zip(vector_results['documents'][0], vector_results['metadatas'][0]):
                        context_chunks.append({
                            "content": doc,
                            "entity_name": meta.get("entity_name", "Unknown"),
                            "label": "[RELATED_CONTEXT]",
                            "score": 0.5
                        })
        # Step 3: Entity-Centric Expansion, Merging & Scoring
        if matched_entity_root_ids:
            try:
                entity_query = {"entity_root_id": {"$in": list(matched_entity_root_ids)}} if len(matched_entity_root_ids) > 1 \
                               else {"entity_root_id": list(matched_entity_root_ids)[0]}
                all_entity_data = collection.get(where=entity_query)
                
                # Group chunks by entity
                entity_groups = {}
                for doc, meta in zip(all_entity_data['documents'], all_entity_data['metadatas']):
                    eid = meta.get("entity_root_id")
                    if eid not in entity_groups:
                        # Calculate score for this entity once
                        score = self._calculate_entity_score(meta, intent_info)
                        entity_groups[eid] = {
                            "name": meta.get("entity_name", "Unknown"), 
                            "chunks": [],
                            "score": score,
                            "metadata": meta
                        }
                    entity_groups[eid]["chunks"].append(doc)
                
                # Merge chunks and partition by confidence
                location_query = str(extracted_attrs.get("location", "")).lower()
                location_words = [w for w in re.split(r'\s+', location_query) if len(w) > 2] if location_query else []
                ADDRESS_STOP = {"road", "street", "main", "area", "near", "on", "at", "lane", "ave"}
                location_words = [w for w in location_words if w not in ADDRESS_STOP] if location_words else []
                
                for eid, data in entity_groups.items():
                    meta = data.get("metadata", {})
                    if location_words:
                        loc_match = any(
                            word in str(meta.get("attr_location_area", "")).lower() or
                            word in str(meta.get("attr_location_road", "")).lower() or
                            word in str(meta.get("attr_location_city", "")).lower() or
                            word in str(meta.get("attr_location_aliases", "")).lower()
                            for word in location_words
                        )
                        if not loc_match:
                            continue
                    merged_text = "\n---\n".join(data["chunks"])
                    
                    if len(merged_text) > 3000:
                        merged_text = merged_text[:3000] + "... [Context Truncated]"
                    
                    label = "[PRIMARY_MATCH]" if data["score"] >= 0.3 else "[RELATED_CONTEXT]"
                    
                    context_chunks.append({
                        "content": merged_text,
                        "entity_name": data["name"],
                        "label": label,
                        "score": data["score"]
                    })
            except Exception as e:
                print(f"Expansion error: {e}")

        # 4. Process Context & Guardrail Instructions
        # Sort context so PRIMARY matches appear first
        context_chunks.sort(key=lambda x: x.get('score', 0.0), reverse=True)
        
        context = "Relevant information found in our database:\n"
        if not context_chunks:
            context = "No specific information found."
        else:
            for i, chunk in enumerate(context_chunks, 1):
                label = chunk.get('label', '[RELATED_CONTEXT]')
                score = chunk.get('score', 0.0)
                context += f"\n{label} ENTITY: {chunk.get('entity_name', 'Unknown')} (Confidence: {score:.2f})\n"
                context += f"START_ENTITY_DATA\n{chunk['content']}\nEND_ENTITY_DATA\n"

        location_in_query = extracted_attrs.get("location", "")
        guardrail_instructions = """
CORE RULES (industry-agnostic):
1. If any entity is labeled [PRIMARY_MATCH], you MUST prioritize its information for the main answer.
2. NEVER mix attributes from a [RELATED_CONTEXT] entity into the description of a [PRIMARY_MATCH] entity.
3. If the user asks by a specific attribute (location, category, brand, etc.) and you find a [PRIMARY_MATCH], provide its details.
4. Use ONLY the entity names, addresses, contacts, and details from the context. Do NOT invent, fabricate, or make up any entity, address, phone number, or information.
5. Base your entire answer on the provided context. Do not hallucinate.
"""
        if location_in_query and context_chunks:
            entity_names = [c.get("entity_name", "") for c in context_chunks if c.get("entity_name")]
            guardrail_instructions += f"""
CRITICAL: The user asked about "{location_in_query}". You MUST list and describe EVERY entity from the context: {', '.join(entity_names)}. Do NOT mention only one—describe each of these that match. Do not add entities not in this list.
"""

        # ALWAYS inject RAG context - otherwise LLM may hallucinate (e.g. wrong project for location query)
        if system_prompt and "{context}" in system_prompt:
            system_prompt = system_prompt.replace("{context}", context)
        elif system_prompt and context_chunks:
            # Custom prompt without {context} placeholder - MUST append context so LLM sees it
            system_prompt = f"{system_prompt}\n\n[RAG CONTEXT - USE THIS TO ANSWER]\n{context}"
        elif not system_prompt:
            system_prompt = f"You are a helpful assistant. Base your answers on the context provided.\n\nContext:\n{context}"

        if context_chunks:
            system_prompt = f"{system_prompt}\n\n[CRITICAL INSTRUCTION]\n{guardrail_instructions}"
        elif "GUARDRAIL" not in system_prompt and "CRITICAL INSTRUCTION" not in system_prompt:
            system_prompt = f"{system_prompt}\n\n{guardrail_instructions}"

        # When custom prompt has required opening (e.g. *Sales Expert*), enforce it
        is_greeting = any(g in query_lower for g in ["hey", "hi", "hello", "good morning", "good evening"])
        has_persona_prompt = system_prompt and ("MUST start with" in system_prompt or "must start with" in system_prompt.lower())

        if has_persona_prompt:
            persona_override = "\n\nIMPORTANT: Ignore any previous assistant messages that don't match your persona. Always respond using YOUR designated opening above — never repeat generic greetings from earlier in the conversation."
            if persona_override not in system_prompt:
                system_prompt = system_prompt + persona_override

            # For greetings: extract required opening and add explicit final instruction so model follows it
            if is_greeting:
                match = re.search(r'[Mm]UST start with[:\s]*\n([\s\S]*?)(?=\n\s*---|\n\nYou are|\n\nGOALS:|\n\nRULES:|$)', system_prompt)
                if match:
                    required_opening = match.group(1).strip()
                    system_prompt = system_prompt + f"\n\n[FOR THIS GREETING] The user said \"{query_text}\". Your response MUST begin with these EXACT words:\n{required_opening}\n\nDo not paraphrase. Output the above first, then you may add a brief follow-up."

        # Prepare message history
        messages = [{"role": "system", "content": system_prompt}]
        
        # For greeting queries with persona prompt, skip assistant messages from history
        if chat_history:
            for msg in chat_history:
                if isinstance(msg, dict) and "role" in msg and "content" in msg:
                    if is_greeting and has_persona_prompt and msg.get("role") == "assistant":
                        continue  # Skip prior assistant replies to avoid repeating generic greetings
                    messages.append(msg)
        
        messages.append({"role": "user", "content": query_text})

        # Final generation
        result = self.provider.chat_completion(
            messages=messages,
            model=self.model_name,
            temperature=self.temperature,
            max_tokens=500,
        )
        ai_response = result["content"] or ""

        self._log_usage_background(
            request_type="rag_query",
            model=self.model_name,
            prompt_tokens=result.get("prompt_tokens", 0),
            completion_tokens=result.get("completion_tokens", 0),
            embedding_tokens=0,
            session_id=rag_session,
        )

        self.last_usage = {
            "prompt_tokens": int(result.get("prompt_tokens") or 0),
            "completion_tokens": int(result.get("completion_tokens") or 0),
            "total_tokens": int(result.get("prompt_tokens") or 0) + int(result.get("completion_tokens") or 0),
            "model": self.model_name,
            "provider": self.provider_name,
        }

        # COST OPTIMIZATION: Cache the response
        self._response_cache[cache_key] = ai_response
        return ai_response

    def analyze_bot_readiness(self, client_id: str, name: str, purpose: str, prompt: str, bot_id: str = None) -> dict:
        """Analyze if the bot is ready based on its configuration and knowledge base."""
        try:
            # 1. Get storage info
            info = self.get_client_storage_info(client_id, bot_id=bot_id)
            doc_count = info.get("document_count", 0)
            
            # 2. Sample some content if documents exist
            samples = ""
            if doc_count > 0:
                collection = self.create_collection(client_id, bot_id=bot_id)
                results = collection.get(limit=5)
                docs = results.get('documents', [])
                samples = "\n".join([f"Sample {i+1}: {doc[:500]}..." for i, doc in enumerate(docs)])

            # 3. Construct Analysis Prompt (using a slightly simpler prompt for 3.5)
            analysis_prompt = f"""Analyze the readiness of this AI bot:
Name: {name}
Purpose: {purpose}
System Prompt: {prompt}

Knowledge Base Info:
- Document Count: {doc_count}
- Content Samples:
{samples if samples else "No documents uploaded yet."}

Provide a JSON response with:
- "score": (0-100)
- "summary": (brief evaluation)
- "suggestions": (list of 3 specific tips)
- "is_ready": (boolean)
"""

            # 4. Call LLM for analysis with JSON mode
            extra = {}
            if self.provider_name == "openai":
                extra["response_format"] = {"type": "json_object"}
            out = self.provider.chat_completion(
                messages=[
                    {"role": "system", "content": "You are an AI Analyst. Output MUST be valid JSON."},
                    {"role": "user", "content": analysis_prompt},
                ],
                model=self.model_name,
                temperature=0.3,
                max_tokens=1500,
                **extra,
            )
            content = (out["content"] or "").strip()
            
            # Robust JSON cleaning
            if "```" in content:
                # Handle markdown code blocks
                import re
                json_match = re.search(r'```(?:json)?\s*([\s\S]*?)\s*```', content)
                if json_match:
                    content = json_match.group(1).strip()
                else:
                    # Strip any leading/trailing backticks or markdown markers if regex failed
                    content = re.sub(r'^```[a-z]*\n?', '', content)
                    content = re.sub(r'\n?```$', '', content)
                
            return json.loads(content)
            
        except Exception as e:
            print(f"Error in analyze_bot_readiness: {str(e)}")
            return {
                "score": 0,
                "summary": f"Analysis failed: {str(e)}",
                "suggestions": ["Check your OpenAI API key", "Upload more documents", "Define a clearer system prompt"],
                "is_ready": False
            }

    def structure_text(self, text: str) -> dict:
        """Structure raw extracted text into a summary and highlights."""
        try:
            # Cap the input text to save tokens and stay within limits
            truncated_text = text[:10000] if len(text) > 10000 else text
            
            prompt = f"""Analyze the following text and provide a structured summary.
Output MUST be a JSON object with:
"summary": A concise 2-3 sentence overview.
"highlights": A list of 4-6 key technical or business highlights/features.
"topic": A one or two word topic for this content.

Text:
{truncated_text}
"""
            extra = {}
            if self.provider_name == "openai":
                extra["response_format"] = {"type": "json_object"}
            out = self.provider.chat_completion(
                messages=[
                    {"role": "system", "content": "You are a content analyzer. Output MUST be valid JSON."},
                    {"role": "user", "content": prompt},
                ],
                model=self.model_name,
                temperature=0.3,
                max_tokens=2000,
                **extra,
            )
            content = (out["content"] or "").strip()
            
            # Robust JSON cleaning
            if "```" in content:
                import re
                json_match = re.search(r'```(?:json)?\s*([\s\S]*?)\s*```', content)
                if json_match:
                    content = json_match.group(1).strip()
                else:
                    content = re.sub(r'^```[a-z]*\n?', '', content)
                    content = re.sub(r'\n?```$', '', content)
                
            return json.loads(content)
        except Exception as e:
            print(f"Error in structure_text: {str(e)}")
            return {
                "summary": "Full extracted text below. AI summarization failed.",
                "highlights": ["Check the raw content", "Manual verification recommended"],
                "topic": "General"
            }

    def _sanitize_chroma_metadata(self, meta: Optional[dict]) -> dict:
        """Chroma expects plain metadata; drop None/unsupported values."""
        if not meta:
            return {"source": "reindex"}
        out: Dict[str, Union[str, int, float, bool]] = {}
        for k, v in meta.items():
            if v is None:
                continue
            if isinstance(v, (str, int, float, bool)):
                out[k] = v
            else:
                out[k] = str(v)
        return out or {"source": "reindex"}

    def reindex_collection(self, client_id: str, bot_id: str = None) -> dict:
        """Re-embed all stored chunks with the current embedding model (delete + recreate collection)."""
        collection_name = self._get_collection_name(client_id, bot_id)
        try:
            old_col = self.chroma_client.get_collection(collection_name)
        except Exception:
            return {"success": True, "message": "No collection found; nothing to reindex", "chunks": 0}

        try:
            data = old_col.get()
        except Exception as e:
            return {"success": False, "message": f"Failed to read collection: {e}", "chunks": 0}

        ids = data.get("ids") or []
        documents = data.get("documents") or []
        metadatas = data.get("metadatas") or []

        if not ids or not documents:
            try:
                self.chroma_client.delete_collection(collection_name)
            except Exception:
                pass
            return {"success": True, "message": "Collection was empty; removed", "chunks": 0}

        n = min(len(ids), len(documents))
        ids = ids[:n]
        documents = documents[:n]
        if len(metadatas) < n:
            metadatas = list(metadatas) + [None] * (n - len(metadatas))
        else:
            metadatas = metadatas[:n]

        try:
            self.chroma_client.delete_collection(collection_name)
        except Exception as e:
            return {"success": False, "message": f"Failed to reset collection: {e}", "chunks": 0}

        new_col = self.create_collection(client_id, bot_id=bot_id)

        total_embed_tokens = 0
        batch_size = 64
        for i in range(0, n, batch_size):
            batch_docs = documents[i : i + batch_size]
            batch_ids = ids[i : i + batch_size]
            batch_meta_raw = metadatas[i : i + batch_size]
            batch_meta = [self._sanitize_chroma_metadata(m) for m in batch_meta_raw]

            vecs, t = self.provider.get_embeddings_batch(batch_docs, self.embedding_model_name)
            total_embed_tokens += t

            new_col.add(
                embeddings=vecs,
                documents=batch_docs,
                metadatas=batch_meta,
                ids=batch_ids,
            )

        self._log_usage_background(
            request_type="embedding",
            model=self.embedding_model_name,
            embedding_tokens=total_embed_tokens,
        )

        print(f"Reindexed {n} chunks for client {client_id} bot={bot_id}")
        return {"success": True, "message": f"Reindexed {n} chunks", "chunks": n}

    def delete_client_collection(self, client_id: str, bot_id: str = None) -> bool:
        """Delete all documents for a specific client."""
        try:
            collection_name = self._get_collection_name(client_id, bot_id)
            self.chroma_client.delete_collection(collection_name)
            print(f"Deleted collection for client {client_id}")
            return True
        except Exception as e:
            print(f"Error deleting collection: {str(e)}")
            raise

    def get_client_storage_info(self, client_id: str, bot_id: str = None) -> dict:
        """Get storage information for a specific client."""
        try:
            collection_name = self._get_collection_name(client_id, bot_id)
            try:
                collection = self.chroma_client.get_collection(collection_name)
            except Exception:
                return {"client_id": client_id, "collection_name": collection_name, "document_count": 0, "total_size_bytes": 0, "total_size_mb": 0, "metadata_fields": [], "file_types": ["text"]}
            
            # Get all documents and their metadata
            results = collection.get()
            documents = [d for d in (results.get('documents') or []) if isinstance(d, str)]
            metadatas = [m if isinstance(m, dict) else {} for m in (results.get('metadatas') or [])]

            # Calculate total size of documents
            total_size = sum(len(doc.encode('utf-8')) for doc in documents)

            # Get unique metadata keys
            metadata_keys = set()
            for meta in metadatas:
                metadata_keys.update(meta.keys())

            return {
                "client_id": client_id,
                "collection_name": collection_name,
                "document_count": len(documents),
                "total_size_bytes": total_size,
                "total_size_mb": round(total_size / (1024 * 1024), 2),
                "metadata_fields": list(metadata_keys),
                "file_types": list(set(meta.get('file_type', 'text') for meta in metadatas)) or ["text"],
            }
        except Exception as e:
            print(f"Error getting storage info: {str(e)}")
            raise

    def get_knowledge_base(self, client_id: str, bot_id: str = None, limit: int = 100, offset: int = 0) -> dict:
        """Retrieve the uploaded knowledge base documents for a specific client/bot.
        
        Args:
            client_id: The client identifier
            bot_id: Optional bot identifier for collection isolation
            limit: Maximum number of documents to return (default 100)
            offset: Number of documents to skip (for pagination)
        
        Returns:
            Dictionary containing documents, metadata, and pagination info
        """
        try:
            collection_name = self._get_collection_name(client_id, bot_id)
            try:
                collection = self.chroma_client.get_collection(collection_name)
            except Exception:
                return {"documents": [], "total_documents": 0}
            
            # Get all documents
            all_results = collection.get()
            
            total_documents = len(all_results['documents'])
            
            # Apply pagination
            start_idx = min(offset, total_documents)
            end_idx = min(offset + limit, total_documents)
            
            paginated_docs = []
            for i in range(start_idx, end_idx):
                paginated_docs.append({
                    "id": all_results['ids'][i],
                    "content": all_results['documents'][i],
                    "metadata": all_results['metadatas'][i],
                    "content_preview": all_results['documents'][i][:200] + "..." if len(all_results['documents'][i]) > 200 else all_results['documents'][i]
                })
            
            return {
                "client_id": client_id,
                "bot_id": bot_id,
                "collection_name": collection_name,
                "total_documents": total_documents,
                "returned_count": len(paginated_docs),
                "offset": offset,
                "limit": limit,
                "has_more": end_idx < total_documents,
                "documents": paginated_docs
            }
        except ValueError:
            # Collection doesn't exist
            return {
                "client_id": client_id,
                "bot_id": bot_id,
                "collection_name": self._get_collection_name(client_id, bot_id),
                "total_documents": 0,
                "returned_count": 0,
                "offset": offset,
                "limit": limit,
                "has_more": False,
                "documents": [],
                "message": "No knowledge base found for this client/bot"
            }
        except Exception as e:
            print(f"Error getting knowledge base: {str(e)}")
            # Return empty instead of re-raising to avoid 500 (e.g. when collection doesn't exist)
            return {
                "client_id": client_id,
                "bot_id": bot_id,
                "collection_name": self._get_collection_name(client_id, bot_id),
                "total_documents": 0,
                "returned_count": 0,
                "offset": offset,
                "limit": limit,
                "has_more": False,
                "documents": [],
                "message": "No knowledge base found for this client/bot"
            }

    def check_api_key(self) -> dict:
        """Check if the API key is valid and get basic information."""
        today = date.today()
        try:
            is_valid, err = self.provider.validate_api_key()
            if not is_valid:
                return {
                    "status": "invalid",
                    "valid": False,
                    "checked_at": today.isoformat(),
                    "error": err,
                    "message": "API key is invalid or has insufficient permissions",
                }
            chat_models = self.provider.list_chat_models()
            return {
                "status": "valid",
                "valid": True,
                "checked_at": today.isoformat(),
                "available_models": [m.get("id", "") for m in chat_models],
                "message": "API key is valid.",
            }
        except Exception as e:
            return {
                "status": "invalid",
                "valid": False,
                "checked_at": today.isoformat(),
                "error": str(e),
                "message": "API key is invalid or has insufficient permissions",
            }