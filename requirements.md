# System Requirements & Specifications

This document details the configuration requirements and system specifications to run the upgraded **Student Hostel Outpass Management System (AI-Powered)** on any machine.

---

## 1. Core Server & Runtime Environment
* **Python Runtime:** Python 3.10 to 3.12+ (64-bit recommended)
* **API Framework:** FastAPI 0.110+ & Uvicorn 0.28+
* **Database Server:** MySQL Server 8.0+ / MariaDB 10.6+ (Managed via MySQL Workbench or command line)
* **Default Database Name:** `hostel_outpass_ai`
* **Default Port:** 8000 (FastAPI), 3306 (MySQL)

---

## 2. Python Packages & AI Dependencies

All dependencies are defined in `requirements.txt`:
* **Web & API:** `fastapi`, `uvicorn[standard]`, `pydantic`, `python-multipart`
* **Database Driver:** `mysql-connector-python`
* **Security & Auth:** `bcrypt`, `pyjwt`, `cryptography`, `python-dotenv`
* **QR Codes:** `qrcode[pil]`, `pillow`
* **Document Extraction:** `pymupdf` (PyMuPDF for PDF ingestion)
* **Vector Search & AI:** `sentence-transformers` (`all-MiniLM-L6-v2`), `faiss-cpu`, `huggingface_hub`, `requests`

---

## 3. Database Schema Overview
The database tables are automatically verified and initialized upon server startup:
1. **`admin`**: Warden/administrator accounts with bcrypt password hashes.
2. **`security`**: Gatekeeper personnel accounts.
3. **`students`**: Registered student records, room numbers, parent contact info, photos, and QR code paths.
4. **`outing`**: Daily student outing checkout and checkin movements.
5. **`homepass`**: Multi-day leave requests with approval workflow (Pending, Approved, Rejected).
6. **`activity_logs`**: System audit trail of all security and administrative operations.
7. **`rag_documents`**: Uploaded hostel regulation PDFs and handbooks.
8. **`rag_chunks`**: Segmented text chunks extracted using PyMuPDF for vector indexing.
9. **`ai_query_logs`**: AI Assistant and tool-calling query audit history.

---

## 4. Hardware & Webcam Scanner Rules
* **Local Testing (`http://127.0.0.1:8000` or `http://localhost:8000`):** Modern web browsers grant camera access to localhost without SSL.
* **Network / Multi-device Testing (e.g. tablet at gate on LAN IP):** Modern browsers restrict camera hardware access (`navigator.mediaDevices.getUserMedia`) to secure contexts. When testing across devices on a local area network (LAN), access the gate console via HTTPS or configure Chrome/Edge flags (`chrome://flags/#unsafely-treat-insecure-origin-as-secure`) for the gate machine's IP.
