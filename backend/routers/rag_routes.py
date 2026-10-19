from pathlib import Path
from typing import Optional
from fastapi import APIRouter, UploadFile, File, Form, HTTPException, Request
from pydantic import BaseModel
from ..config import RAG_DOCS_DIR, BASE_DIR
from ..services.rag_service import ingest_pdf_document, search_rag_knowledge, generate_rag_response
from ..database import execute_query
from ..auth import require_admin

router = APIRouter(prefix="/api/rag", tags=["RAG Documents"])

class RagQueryRequest(BaseModel):
    query: str
    top_k: int = 4

@router.get("/documents")
def list_documents():
    docs = execute_query("""
        SELECT d.*, COUNT(c.id) as chunk_count
        FROM rag_documents d
        LEFT JOIN rag_chunks c ON d.id = c.document_id
        GROUP BY d.id
        ORDER BY d.ingested_at DESC;
    """, fetch_all=True) or []
    return {"documents": docs}

@router.post("/upload")
async def upload_and_ingest_pdf(request: Request, file: UploadFile = File(...)):
    require_admin(request)
    if not file.filename.lower().endswith(".pdf"):
        raise HTTPException(status_code=400, detail="Only PDF files can be uploaded for RAG indexing.")

    safe_filename = file.filename.replace(" ", "_")
    target_path = RAG_DOCS_DIR / safe_filename

    content = await file.read()
    with open(target_path, "wb") as f:
        f.write(content)

    res = ingest_pdf_document(target_path, safe_filename)
    return res

@router.post("/query")
def query_rag(data: RagQueryRequest):
    chunks = search_rag_knowledge(data.query, top_k=data.top_k)
    answer = generate_rag_response(data.query, chunks)
    return {
        "query": data.query,
        "answer": answer,
        "retrieved_chunks": chunks
    }

@router.post("/auto-index-sample")
def auto_index_sample_pdf():
    """Automatically indexes projectsdata.pdf from the workspace root if not already indexed."""
    sample_pdf = BASE_DIR / "projectsdata.pdf"
    if not sample_pdf.exists():
        return {"status": "skipped", "message": "projectsdata.pdf not found in project root."}

    # Check if already indexed
    existing = execute_query(
        "SELECT id FROM rag_documents WHERE filename = %s LIMIT 1;",
        ("projectsdata.pdf",),
        fetch_one=True
    )
    if existing:
        return {"status": "exists", "message": "projectsdata.pdf is already indexed."}

    res = ingest_pdf_document(sample_pdf, "projectsdata.pdf")
    return res
