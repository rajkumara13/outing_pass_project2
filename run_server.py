import uvicorn

if __name__ == "__main__":
    print("=================================================================")
    print("   Student Hostel Outpass Management System & AI Agent (v2.0)   ")
    print("=================================================================")
    print("Backend: Python + FastAPI + MySQL ('hostel_outpass_ai')")
    print("AI Stack: PyMuPDF + SentenceTransformers + FAISS + Tool Agent")
    print("Server running at: http://127.0.0.1:8000")
    print("Open http://127.0.0.1:8000 in your web browser.")
    print("=================================================================")
    uvicorn.run("backend.main:app", host="127.0.0.1", port=8000, reload=True)
