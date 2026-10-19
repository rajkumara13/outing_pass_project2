import os
import pymupdf  # PyMuPDF
import numpy as np
import faiss
import json
import requests
from pathlib import Path
from typing import List, Dict, Any, Optional
from sentence_transformers import SentenceTransformer

from ..config import (
    RAG_DOCS_DIR,
    FAISS_INDEX_DIR,
    EMBEDDING_MODEL_NAME,
    HUGGINGFACE_API_TOKEN,
    HF_LLM_MODEL
)
from ..database import execute_query

# Global singleton embedding model
_embedding_model: Optional[SentenceTransformer] = None
_faiss_index: Optional[faiss.Index] = None
_index_metadata: List[Dict[str, Any]] = []

INDEX_FILE = FAISS_INDEX_DIR / "hostel_rag.index"
METADATA_FILE = FAISS_INDEX_DIR / "hostel_rag_meta.json"

def get_embedding_model() -> SentenceTransformer:
    global _embedding_model
    if _embedding_model is None:
        _embedding_model = SentenceTransformer(EMBEDDING_MODEL_NAME)
    return _embedding_model

def get_faiss_index() -> faiss.Index:
    global _faiss_index, _index_metadata
    if _faiss_index is None:
        if INDEX_FILE.exists() and METADATA_FILE.exists():
            try:
                _faiss_index = faiss.read_index(str(INDEX_FILE))
                with open(METADATA_FILE, "r", encoding="utf-8") as f:
                    _index_metadata = json.load(f)
            except Exception as e:
                print(f"Error loading existing FAISS index: {e}")
                _faiss_index = None
                _index_metadata = []

        if _faiss_index is None:
            # Dimension of all-MiniLM-L6-v2 is 384
            dimension = 384
            _faiss_index = faiss.IndexFlatIP(dimension)
            _index_metadata = []

    return _faiss_index

def save_faiss_index():
    global _faiss_index, _index_metadata
    if _faiss_index is not None:
        faiss.write_index(_faiss_index, str(INDEX_FILE))
        with open(METADATA_FILE, "w", encoding="utf-8") as f:
            json.dump(_index_metadata, f, ensure_ascii=False, indent=2)

def chunk_text(text: str, chunk_size: int = 400, overlap: int = 80) -> List[str]:
    """Splits a body of text into overlapping word chunks."""
    words = text.split()
    if not words:
        return []
    chunks = []
    i = 0
    while i < len(words):
        chunk = " ".join(words[i:i + chunk_size])
        if chunk.strip():
            chunks.append(chunk.strip())
        i += (chunk_size - overlap)
        if i >= len(words) - overlap:
            break
    return chunks

def ingest_pdf_document(file_path: Path, filename: str) -> Dict[str, Any]:
    """
    Extracts text from PDF using PyMuPDF, chunks it, generates SentenceTransformer
    embeddings, and indexes it into FAISS and MySQL.
    """
    global _index_metadata
    doc = pymupdf.open(str(file_path))
    total_pages = len(doc)

    # 1. Register in MySQL rag_documents
    doc_id = execute_query(
        "INSERT INTO rag_documents (filename, file_path, title, total_pages) VALUES (%s, %s, %s, %s);",
        (filename, str(file_path), filename.replace(".pdf", "").replace("_", " "), total_pages)
    )

    all_chunks = []
    for page_num in range(total_pages):
        page = doc.load_page(page_num)
        page_text = page.get_text("text").strip()
        if not page_text:
            continue
        page_chunks = chunk_text(page_text)
        for idx, chunk in enumerate(page_chunks):
            all_chunks.append({
                "document_id": doc_id,
                "filename": filename,
                "page_number": page_num + 1,
                "chunk_index": idx,
                "text": chunk
            })

    if not all_chunks:
        return {"document_id": doc_id, "filename": filename, "total_chunks": 0, "message": "No text could be extracted."}

    # 2. Generate embeddings using SentenceTransformers
    model = get_embedding_model()
    texts = [c["text"] for c in all_chunks]
    embeddings = model.encode(texts, normalize_embeddings=True)
    embeddings = np.array(embeddings, dtype=np.float32)

    # 3. Add to FAISS
    index = get_faiss_index()
    start_faiss_id = index.ntotal
    index.add(embeddings)

    # 4. Save metadata and sync with MySQL
    for i, chunk_info in enumerate(all_chunks):
        faiss_id = int(start_faiss_id + i)
        chunk_info["faiss_id"] = faiss_id
        _index_metadata.append(chunk_info)

        execute_query(
            "INSERT INTO rag_chunks (document_id, page_number, chunk_index, chunk_text, faiss_id) VALUES (%s, %s, %s, %s, %s);",
            (chunk_info["document_id"], chunk_info["page_number"], chunk_info["chunk_index"], chunk_info["text"], faiss_id)
        )

    save_faiss_index()

    return {
        "document_id": doc_id,
        "filename": filename,
        "total_pages": total_pages,
        "total_chunks": len(all_chunks),
        "message": f"Successfully ingested {filename} with {len(all_chunks)} chunks."
    }

def search_rag_knowledge(query: str, top_k: int = 4) -> List[Dict[str, Any]]:
    """
    Finds the most relevant document chunks for a query using FAISS vector search.
    """
    global _index_metadata
    index = get_faiss_index()
    if index.ntotal == 0 or not _index_metadata:
        return []

    model = get_embedding_model()
    query_vector = model.encode([query], normalize_embeddings=True)
    query_vector = np.array(query_vector, dtype=np.float32)

    actual_k = min(top_k, index.ntotal)
    scores, indices = index.search(query_vector, actual_k)

    results = []
    for score, idx in zip(scores[0], indices[0]):
        if idx >= 0 and idx < len(_index_metadata):
            meta = _index_metadata[idx].copy()
            meta["score"] = float(score)
            results.append(meta)

    return results

def generate_rag_response(query: str, context_chunks: List[Dict[str, Any]]) -> str:
    """
    Uses the Hugging Face language model (or intelligent context synthesizer) to answer questions
    grounded strictly in the retrieved hostel documents.
    """
    if not context_chunks:
        return "I could not find any specific documents or policies in the hostel repository matching your query."

    context_str = "\n\n".join([
        f"[Document: {c.get('filename')} (Page {c.get('page_number')})]:\n{c.get('text')}"
        for c in context_chunks
    ])

    system_prompt = (
        "You are the official Hostel Outpass AI Assistant. Answer the user's question accurately, "
        "concisely, and professionally using only the provided context below. "
        "Cite the document name and page number when stating hostel rules, timings, or policies."
    )

    user_prompt = f"Context:\n{context_str}\n\nQuestion: {query}\n\nAnswer:"

    # If user provided a Hugging Face API Token, call the Hugging Face Inference API
    token = HUGGINGFACE_API_TOKEN.strip() if HUGGINGFACE_API_TOKEN else os.getenv("HUGGINGFACE_API_TOKEN", "").strip()

    if token:
        try:
            api_url = f"https://api-inference.huggingface.co/models/{HF_LLM_MODEL}"
            headers = {"Authorization": f"Bearer {token}"}
            payload = {
                "inputs": f"{system_prompt}\n\n{user_prompt}",
                "parameters": {"max_new_tokens": 300, "temperature": 0.2, "return_full_text": False}
            }
            resp = requests.post(api_url, headers=headers, json=payload, timeout=20)
            if resp.status_code == 200:
                data = resp.json()
                if isinstance(data, list) and len(data) > 0 and "generated_text" in data[0]:
                    return data[0]["generated_text"].strip()
                elif isinstance(data, dict) and "generated_text" in data:
                    return data["generated_text"].strip()
        except Exception as e:
            print(f"HF Inference API call failed: {e}")

    # High-quality fallback synthesizer when HF API token is pending
    best_chunk = context_chunks[0]
    return (
        f"Based on **{best_chunk.get('filename')}** (Page {best_chunk.get('page_number')}):\n\n"
        f"{best_chunk.get('text')}\n\n"
        f"> *Note: Retrieved via FAISS similarity search (confidence score: {best_chunk.get('score', 0):.2f}). "
        f"Set `HUGGINGFACE_API_TOKEN` in `.env` to enable full Hugging Face generative chat.*"
    )
