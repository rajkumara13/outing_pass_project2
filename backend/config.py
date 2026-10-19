import os
from pathlib import Path
from dotenv import load_dotenv

# Base project directory
BASE_DIR = Path(__file__).resolve().parent.parent

# Load .env file if present
load_dotenv(BASE_DIR / ".env")

# Database configurations
DB_HOST = os.getenv("DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("DB_PORT", "3306"))
DB_USER = os.getenv("DB_USER", "root")
DB_PASS = os.getenv("DB_PASS", "Rajkumar0958@")
DB_NAME = os.getenv("DB_NAME", "hostel_outpass_ai")

# Authentication Secret
SECRET_KEY = os.getenv("SECRET_KEY", "hostel-outpass-ai-secure-jwt-secret-key-2026")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 60 * 24  # 24 hours

# Directories
UPLOADS_DIR = BASE_DIR / "uploads" / "student_photos"
ASSETS_QR_DIR = BASE_DIR / "assets" / "qr"
RAG_DOCS_DIR = BASE_DIR / "uploads" / "rag_docs"
FAISS_INDEX_DIR = BASE_DIR / "backend" / "faiss_data"

UPLOADS_DIR.mkdir(parents=True, exist_ok=True)
ASSETS_QR_DIR.mkdir(parents=True, exist_ok=True)
RAG_DOCS_DIR.mkdir(parents=True, exist_ok=True)
FAISS_INDEX_DIR.mkdir(parents=True, exist_ok=True)

# Hugging Face Settings
HUGGINGFACE_API_TOKEN = os.getenv("HUGGINGFACE_API_TOKEN", "")
EMBEDDING_MODEL_NAME = os.getenv("EMBEDDING_MODEL_NAME", "all-MiniLM-L6-v2")
HF_LLM_MODEL = os.getenv("HF_LLM_MODEL", "meta-llama/Llama-3.2-3B-Instruct")
