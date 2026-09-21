from fastapi import FastAPI, HTTPException, UploadFile, File, Query
from pydantic import BaseModel
from rag_engine import RAGEngine
import uvicorn
import os
import logging
import traceback
from typing import Optional
import io
import json
import time
import hashlib

from config import Config
from providers.provider_factory import get_provider

app = FastAPI()
TRANSFER_MESSAGE = "I'm transferring your chat to our available executive."

_model_cache = {}


class ValidateKeyRequest(BaseModel):
    provider: str
    api_key: str


class ListModelsRequest(BaseModel):
    provider: str
    api_key: str


class InternalUsageLogRequest(BaseModel):
    customer_id: Optional[str] = None
    bot_id: Optional[str] = None
    provider: str
    model: str
    request_type: str
    prompt_tokens: int = 0
    completion_tokens: int = 0
    embedding_tokens: int = 0
    internal_secret: str
    session_id: Optional[str] = None


class QueryRequest(BaseModel):
    client_id: str
    query_text: str
    conversation_id: str = None  # Optional for conversation history
    chat_history: list[dict] = [] # New: Full chat history from client
    api_key: str  # Client's OpenAI API key
    chat_model: Optional[str] = None  # Preferred field (multi-provider)
    model_name: str = "gpt-3.5-turbo"  # Default model
    temperature: float = 0.3  # Default temperature
    system_prompt: str = None  # Optional custom system prompt
    bot_id: str = None  # Optional bot ID for collection isolation
    provider: str = "openai"
    embedding_model: Optional[str] = None
    customer_id: Optional[str] = None  # For usage logging (defaults to client_id if omitted)

class DocumentUpload(BaseModel):
    client_id: str
    documents: list[str]
    metadata: list[dict] = None
    api_key: str  # Client's OpenAI API key
    chat_model: Optional[str] = None  # Preferred field (multi-provider)
    model_name: str = "gpt-3.5-turbo"  # Default model
    temperature: float = 0.3  # Default temperature
    bot_id: str = None  # Optional bot ID for collection isolation
    domain: str = "general" # New: industry/domain (real estate, healthcare, etc.)
    provider: str = "openai"
    embedding_model: Optional[str] = None
    customer_id: Optional[str] = None

class ReadinessRequest(BaseModel):
    client_id: str
    name: str
    purpose: str
    prompt: str
    api_key: str
    bot_id: str = None
    domain: str = "general"

class StructureRequest(BaseModel):
    text: str
    api_key: str
    model_name: str = "gpt-3.5-turbo"
    provider: str = "openai"
    embedding_model: Optional[str] = None
    customer_id: Optional[str] = None


class ReindexRequest(BaseModel):
    client_id: str
    api_key: str
    provider: str = "openai"
    embedding_model: Optional[str] = None
    bot_id: Optional[str] = None
    customer_id: Optional[str] = None


@app.post("/validate_key")
async def validate_key(request: ValidateKeyRequest):
    try:
        provider = get_provider(request.provider, request.api_key)
        is_valid, error = provider.validate_api_key()
        return {"valid": is_valid, "error": error or ""}
    except ValueError as e:
        return {"valid": False, "error": str(e)}


@app.post("/list_models")
async def list_models(request: ListModelsRequest):
    cache_key = f"{request.provider}:{hashlib.sha256(request.api_key.encode()).hexdigest()[:16]}"
    now = time.time()
    if cache_key in _model_cache and now - _model_cache[cache_key]["ts"] < 300:
        return _model_cache[cache_key]["data"]

    provider = get_provider(request.provider, request.api_key)
    chat_models = provider.list_chat_models()
    embedding_models = provider.list_embedding_models()
    result = {"chat_models": chat_models, "embedding_models": embedding_models}
    _model_cache[cache_key] = {"data": result, "ts": now}
    return result


@app.post("/internal/log_usage")
async def log_usage_internal(request: InternalUsageLogRequest):
    if request.internal_secret != Config.INTERNAL_API_SECRET:
        return {"error": "unauthorized", "logged": False}
    # DB persistence is handled by Laravel when the worker POSTs there; this route acknowledges only.
    return {"logged": True}


@app.post("/reindex")
async def reindex(request: ReindexRequest):
    """Re-embed all Chroma chunks for a client/bot using the current embedding model."""
    try:
        logging.info(
            "FastAPI reindex start",
            extra={
                "provider": request.provider,
                "embedding_model": request.embedding_model,
                "bot_id": request.bot_id,
                "client_id": request.client_id,
                "customer_id": request.customer_id,
            },
        )
        cid = request.customer_id or request.client_id
        client_engine = RAGEngine(
            api_key=request.api_key,
            provider=request.provider,
            embedding_model=request.embedding_model,
            bot_id_str=str(request.bot_id) if request.bot_id else None,
            customer_id=str(cid) if cid is not None else None,
        )
        result = client_engine.reindex_collection(request.client_id, bot_id=request.bot_id)
        return result
    except Exception as e:
        logging.error("reindex failed: %s", str(e))
        logging.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/process_query")
async def process_query(request: QueryRequest):
    try:
        model_name = request.chat_model or request.model_name
        logging.info(
            "FastAPI process_query start",
            extra={
                "provider": request.provider,
                "chat_model": model_name,
                "embedding_model": request.embedding_model,
                "bot_id": request.bot_id,
                "client_id": request.client_id,
                "customer_id": request.customer_id,
                "temperature": request.temperature,
            },
        )
        cid = request.customer_id or request.client_id
        # Create a new RAGEngine instance with the client's API key and model settings
        client_engine = RAGEngine(
            api_key=request.api_key,
            model_name=model_name,
            temperature=request.temperature,
            provider=request.provider,
            embedding_model=request.embedding_model,
            bot_id_str=str(request.bot_id) if request.bot_id else None,
            customer_id=str(cid) if cid is not None else None,
        )
        response = client_engine.query(
            request.client_id, 
            request.query_text,
            request.conversation_id,
            system_prompt=request.system_prompt,
            bot_id=request.bot_id,
            chat_history=request.chat_history
        )
        return {
            "success": True,
            "response": response,
            "client_id": request.client_id
        }
    except Exception as e:
        logging.error(
            "process_query failed: %s | client_id=%s bot_id=%s query=%s",
            str(e), getattr(request, 'client_id', '?'), getattr(request, 'bot_id', '?'),
            getattr(request, 'query_text', '?')[:80]
        )
        logging.error(traceback.format_exc())
        # Return 200 with transfer message so WhatsApp/Laravel get valid response instead of 500
        return {
            "success": True,
            "response": TRANSFER_MESSAGE,
            "client_id": getattr(request, 'client_id', ''),
            "_error": str(e),
        }

@app.post("/add_documents")
async def add_documents(data: DocumentUpload):
    try:
        cid = data.customer_id or data.client_id
        model_name = data.chat_model or data.model_name
        client_engine = RAGEngine(
            api_key=data.api_key,
            model_name=model_name,
            temperature=data.temperature,
            provider=data.provider,
            embedding_model=data.embedding_model,
            bot_id_str=str(data.bot_id) if data.bot_id else None,
            customer_id=str(cid) if cid is not None else None,
        )
        success = client_engine.add_documents(
            data.client_id,
            data.documents,
            data.metadata,
            bot_id=data.bot_id,
            domain=data.domain
        )
        if not success:
            raise HTTPException(status_code=500, detail="Failed to add documents")
        return {"success": True, "message": "Documents added successfully"}
    except Exception as e:
        print(f"Error in add_documents endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/upload_file")
async def upload_file(
    client_id: str = Query(..., description="Client ID for the document"),
    api_key: str = Query(..., description="Client's OpenAI API key"),
    file: UploadFile = File(...),
    metadata: Optional[str] = Query(None, description="Metadata as JSON string"),
    bot_id: Optional[str] = Query(None, description="Bot ID for collection isolation"),
    domain: str = Query("general", description="Industry domain"),
    provider: str = Query("openai", description="AI provider"),
    embedding_model: Optional[str] = Query(None, description="Embedding model id"),
    customer_id: Optional[str] = Query(None, description="Customer id for usage logging"),
):
    try:
        # Read file content
        file_content = await file.read()
        
        # Get file extension
        file_type = file.filename.split('.')[-1].lower()
        
        # Create a file-like object from the content
        file_obj = io.BytesIO(file_content)
        
        # Parse metadata if provided
        metadata_dict = None
        if metadata:
            try:
                metadata_dict = json.loads(metadata)
            except json.JSONDecodeError:
                raise HTTPException(status_code=400, detail="Invalid metadata JSON format")
        
        # Create client-specific engine
        cid = customer_id or client_id
        client_engine = RAGEngine(
            api_key=api_key,
            provider=provider,
            embedding_model=embedding_model,
            bot_id_str=str(bot_id) if bot_id else None,
            customer_id=str(cid) if cid is not None else None,
        )
        
        # Process and add the file
        success = client_engine.add_file(
            client_id=client_id,
            file_content=file_obj,
            file_type=file_type,
            metadata=metadata_dict,
            bot_id=bot_id,
            domain=domain
        )
        
        if not success:
            raise HTTPException(status_code=500, detail="Failed to process file")
            
        return {
            "success": True,
            "message": f"File {file.filename} processed and added successfully",
            "client_id": client_id
        }
        
    except Exception as e:
        print(f"Error in upload_file endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/extract_text_from_file")
async def extract_text_from_file(
    file: UploadFile = File(...)
):
    try:
        file_content = await file.read()
        file_type = file.filename.split('.')[-1].lower()
        file_obj = io.BytesIO(file_content)
        
        # We just need a generic engine for processing
        engine = RAGEngine()
        texts = engine.process_file(file_obj, file_type)
        
        return {
            "success": True,
            "filename": file.filename,
            "text_chunks": texts,
            "full_text": "\n".join(texts)
        }
    except Exception as e:
        print(f"Error in extract_text endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/scrape_website")
async def scrape_website(
    url: str = Query(..., description="Website URL to scrape"),
    max_pages: int = Query(15, description="Maximum number of pages to scrape")
):
    try:
        engine = RAGEngine()
        results = engine.crawl_website(url, max_pages=max_pages)
        
        return {
            "success": True,
            "url": url,
            "pages_scraped": len(results),
            "data": results
        }
    except Exception as e:
        print(f"Error in scrape_website endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/add_manual_content")
async def add_manual_content(data: DocumentUpload):
    # This uses the DocumentUpload model which already has:
    # client_id, documents (list of strings), metadata, api_key, etc.
    try:
        cid = data.customer_id or data.client_id
        client_engine = RAGEngine(
            api_key=data.api_key,
            model_name=data.model_name,
            temperature=data.temperature,
            provider=data.provider,
            embedding_model=data.embedding_model,
            bot_id_str=str(data.bot_id) if data.bot_id else None,
            customer_id=str(cid) if cid is not None else None,
        )
        success = client_engine.add_documents(
            data.client_id,
            data.documents,
            data.metadata,
            bot_id=data.bot_id,
            domain=data.domain
        )
        if not success:
            raise HTTPException(status_code=500, detail="Failed to add manual content")
        return {"success": True, "message": "Manual content added and indexed successfully"}
    except Exception as e:
        print(f"Error in add_manual_content endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/analyze_readiness")
async def analyze_readiness(request: ReadinessRequest):
    try:
        client_engine = RAGEngine(api_key=request.api_key)
        analysis = client_engine.analyze_bot_readiness(
            client_id=request.client_id,
            name=request.name,
            purpose=request.purpose,
            prompt=request.prompt,
            bot_id=request.bot_id
        )
        return {
            "success": True,
            "analysis": analysis
        }
    except Exception as e:
        print(f"Error in analyze_readiness endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.delete("/clear_client_data/{client_id}")
async def clear_client_data(client_id: str, bot_id: Optional[str] = Query(None)):
    """Delete all documents for a specific client/bot."""
    try:
        client_engine = RAGEngine()
        success = client_engine.delete_client_collection(client_id, bot_id=bot_id)
        if not success:
            raise HTTPException(status_code=500, detail="Failed to delete client data")
        return {
            "success": True,
            "message": f"All data for client {client_id} has been deleted",
            "client_id": client_id
        }
    except Exception as e:
        print(f"Error in clear_client_data endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/client_storage_info/{client_id}")
async def get_client_storage_info(
    client_id: str,
    bot_id: Optional[str] = Query(None)
):
    """Get storage information for a specific client/bot."""
    try:
        client_engine = RAGEngine()
        info = client_engine.get_client_storage_info(client_id, bot_id=bot_id)
        return {
            "success": True,
            "data": info
        }
    except Exception as e:
        logging.error("get_client_storage_info failed: %s | client_id=%s bot_id=%s", str(e), client_id, bot_id)
        # Empty storage is fine for UI; do not 500 when Chroma has no collection yet
        return {
            "success": True,
            "data": {
                "client_id": client_id,
                "document_count": 0,
                "total_size_bytes": 0,
                "total_size_mb": 0,
                "metadata_fields": [],
                "file_types": ["text"],
            },
        }

@app.get("/knowledge_base/{client_id}/download")
async def download_knowledge_base(
    client_id: str,
    bot_id: Optional[str] = Query(None, description="Bot ID for collection isolation")
):
    """Download all documents in the knowledge base for a specific client/bot."""
    try:
        client_engine = RAGEngine()
        # Retrieve all documents without pagination
        result = client_engine.get_knowledge_base(
            client_id=client_id,
            bot_id=bot_id,
            limit=10000, # Large limit to get all
            offset=0
        )
        return {
            "success": True,
            "data": result["documents"],
            "total_documents": result["total_documents"]
        }
    except Exception as e:
        print(f"Error in download_knowledge_base endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/knowledge_base/{client_id}")
async def get_knowledge_base(
    client_id: str,
    bot_id: Optional[str] = Query(None, description="Bot ID for collection isolation"),
    limit: int = Query(100, ge=1, le=500, description="Maximum documents to return"),
    offset: int = Query(0, ge=0, description="Number of documents to skip")
):
    """Retrieve the uploaded knowledge base documents for a specific client/bot.
    
    Returns all documents with their content, metadata, and a preview.
    Supports pagination with limit and offset parameters.
    """
    try:
        client_engine = RAGEngine()
        result = client_engine.get_knowledge_base(
            client_id=client_id,
            bot_id=bot_id,
            limit=limit,
            offset=offset
        )
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        logging.error("get_knowledge_base failed: %s | client_id=%s bot_id=%s", str(e), client_id, bot_id)
        logging.error(traceback.format_exc())
        # Return empty to avoid 500 and allow WhatsApp/UI to load
        return {
            "success": True,
            "data": {
                "documents": [],
                "total_documents": 0,
                "client_id": client_id,
                "bot_id": bot_id,
                "message": "No knowledge base found for this client/bot"
            }
        }

@app.get("/api_usage")
async def get_api_usage(
    api_key: str = Query(..., description="Client's OpenAI API key"),
    provider: str = Query("openai", description="AI provider"),
):
    """Check if the API key is valid and get basic information."""
    try:
        client_engine = RAGEngine(api_key=api_key, provider=provider)
        key_info = client_engine.check_api_key()
        return {
            "success": True,
            "data": key_info
        }
    except Exception as e:
        print(f"Error in get_api_usage endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/structure_text")
async def structure_text(request: StructureRequest):
    try:
        cid = request.customer_id
        engine = RAGEngine(
            api_key=request.api_key,
            model_name=request.model_name,
            provider=request.provider,
            embedding_model=request.embedding_model,
            customer_id=str(cid) if cid is not None else None,
        )
        result = engine.structure_text(request.text)
        return {
            "success": True,
            "data": result
        }
    except Exception as e:
        print(f"Error in structure_text endpoint: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    uvicorn.run(
        app, 
        host="0.0.0.0", 
        port=5000,
        workers=2  # Adjust based on server capacity
    )