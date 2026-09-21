import os
from dotenv import load_dotenv
from pathlib import Path

# Load from ../.env if exists, otherwise from .env
env_path = Path(__file__).parent.parent / ".env"
load_dotenv(env_path if env_path.exists() else None)

# Create absolute path for ChromaDB (override with CHROMA_DB_PATH)
CHROMA_DB_PATH = os.getenv(
    "CHROMA_DB_PATH",
    os.path.join("/var/www/ai_env", "chroma_data"),
)
os.makedirs(CHROMA_DB_PATH, exist_ok=True)

class Config:
    # API Configuration
    OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")

    # Laravel internal usage logging (Python → Laravel)
    LARAVEL_BASE_URL = os.getenv("LARAVEL_BASE_URL", "http://localhost:8000")
    INTERNAL_API_SECRET = os.getenv("INTERNAL_API_SECRET", "change-me-secret-123")
    
    # Model Configuration
    MODEL_NAME = "gpt-3.5-turbo"  # Switch to "gpt-4" for better results
    EMBEDDING_MODEL = "text-embedding-3-small"
    TEMPERATURE = 0.3
    
    # Storage Configuration
    CHROMA_DB_PATH = CHROMA_DB_PATH  # Absolute path for persistent storage
    MAX_DOCUMENT_LENGTH = 10000  # Characters
    
    # Performance Configuration
    MAX_CONCURRENT_QUERIES = 5
    TIMEOUT_SECONDS = 30