# Student Hostel Outpass Management System (AI-Powered)

An enterprise-ready, modern, responsive, and secure web application for managing student daily outings and home passes. The system features a glassmorphic dark user interface, real-time QR-based gate controls, a high-performance **Python + FastAPI** backend, **MySQL Workbench / MySQL 8.0** persistence, and an intelligent **AI Assistant** powered by **PyMuPDF**, **Sentence Transformers**, and **FAISS** vector search.

---

## 🌟 Architecture & Key Features

1. **Python + FastAPI Async Backend (`backend/`):**
   - High-throughput asynchronous REST API built with FastAPI and Uvicorn.
   - Comprehensive endpoints for Authentication, Student Registry, Outing Records, Homepass Workflow, Activity Logs, PDF Ingestion, and AI Chat.
   - Auto-generated interactive Swagger API documentation at `/docs` and ReDoc at `/redoc`.

2. **MySQL Database (`hostel_outpass_ai`):**
   - Native integration with MySQL Server (managed via MySQL Workbench).
   - Relational tables: `admin`, `security`, `students`, `outing`, `homepass`, `activity_logs`, `rag_documents`, `rag_chunks`, and `ai_query_logs`.

3. **AI Assistant & RAG Engine (`backend/services/`):**
   - **PyMuPDF Document Extractor:** Ingests hostel rules, regulatory policies, and student handbooks from PDF files (`uploads/rag_docs/`).
   - **Hugging Face Sentence Transformers:** Embeds document text chunks into high-dimensional vector representations (`all-MiniLM-L6-v2`).
   - **FAISS Vector Similarity Search:** Fast index lookup for relevant policy paragraphs.
   - **Tool-Calling AI Agent:** Autonomous agent with tools to check student in/out status, search home pass requests, inspect occupancy numbers, and answer hostel rule queries.
   - **Floating UI Drawer:** Integrated across all admin, security, and student views (`js/ai_assistant.js`).

4. **Gatekeeper Security Console (`security/`):**
   - Secure login for gatekeepers.
   - Dual camera QR scanners for **Daily Outings** (`outing/scan.html`) and **Home Passes** (`homepass/scan.html`).
   - Real-time instant verification and timestamp logging for exit and entry.

5. **Warden / Admin Portal (`admin/`):**
   - Student management (Register, View, Search, Delete).
   - Automated server-side QR Code generation using Python `qrcode` and `Pillow`.
   - Home pass approval workflow (Approve / Reject with warden remarks).
   - Real-time occupancy analytics, movement chronologies, and printable reports.

6. **100% Local & Offline Design (`css/`, `js/`, `webfonts/`):**
   - Local Bootstrap 5 styles, Font Awesome 6 icons, and HTML5 QR camera decoder.

---

## 📂 Project Structure

```
outing_pass_project2/
├── backend/                         # Python FastAPI Backend
│   ├── config.py                    # Environment settings (.env loader)
│   ├── database.py                  # MySQL connection pool & table initializers
│   ├── auth.py                      # Password hashing (bcrypt) & JWT sessions
│   ├── main.py                      # FastAPI application entrypoint & static mount
│   ├── faiss_data/                  # Serialized FAISS vector index & metadata
│   ├── routers/                     # Modular API Route Controllers
│   │   ├── auth_routes.py           # Admin & Security login/logout
│   │   ├── student_routes.py        # Student CRUD & QR generation
│   │   ├── outing_routes.py         # Daily outing gate scanning & logs
│   │   ├── homepass_routes.py       # Homepass applications & approvals
│   │   ├── report_routes.py         # Dashboard analytics & PDF/print logs
│   │   ├── rag_routes.py            # PDF document upload, chunking & RAG
│   │   └── agent_routes.py          # AI Agent tool-calling execution
│   └── services/                    # Core AI & Utility Services
│       ├── ai_agent_service.py      # Tool-calling agent with MySQL functions
│       ├── rag_service.py           # PyMuPDF parser + SentenceTransformers + FAISS
│       └── qr_service.py            # Dynamic QR code generator
│
├── admin/                           # Admin & Warden Frontend Portal
│   ├── login.html                   # Admin authentication
│   ├── dashboard.html               # Real-time metrics & active outings
│   ├── view_students.html           # Student roster & QR preview modal
│   ├── add_student.html             # Student registration form
│   ├── apply_homepass.html          # New home pass request form
│   ├── approve_homepass.html        # Warden review & approval panel
│   └── reports.html                 # Chronological movement logs
│
├── security/                        # Security Gatekeeper Frontend Portal
│   ├── login.html                   # Security staff authentication
│   ├── dashboard.html               # Gatekeeper quick actions & recent logs
│   └── approve_homepass.html        # Gatekeeper homepass validation list
│
├── outing/                          # Daily Outing Scanning
│   ├── scan.html                    # Camera QR scanner
│   ├── outing.html                  # Verification & exit/entry submission
│   └── success.html                 # Confirmation screen with audio feedback
│
├── homepass/                        # Home Pass Scanning
│   ├── scan.html                    # Camera QR scanner
│   └── check_pass.html              # Pass validity checker & gate clearance
│
├── css/                             # Glassmorphic Dark UI & Bootstrap Styles
├── js/                              # UI handlers, QR camera scripts & AI Assistant
│   └── ai_assistant.js              # Floating AI Assistant widget
├── webfonts/                        # Font Awesome offline fonts
├── assets/qr/                       # Generated student QR codes
├── uploads/                         # Uploaded photos & RAG policy documents
│   ├── student_photos/              # Student profile photos
│   └── rag_docs/                    # Ingested PDF policy manuals
│
├── .env                             # Environment variables & MySQL credentials
├── index.html                       # System landing portal
├── requirements.txt                 # Python dependencies
├── requirements.md                  # System prerequisites & deployment guide
├── run_server.py                    # Uvicorn launcher script
└── README.md                        # Documentation
```

---

## 🚀 Quick Start Guide

### 1. Prerequisites
* Python 3.10+ installed
* MySQL Server 8.0+ running (or MySQL Workbench)

### 2. Environment Setup
Configure your database credentials in the `.env` file:
```ini
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=YourPassword
DB_NAME=hostel_outpass_ai
SECRET_KEY=outpass_secret_key_jwt_2026_super_secure
```

### 3. Install Python Dependencies
```bash
pip install -r requirements.txt
```

### 4. Start the Application
Run the FastAPI development server:
```bash
python run_server.py
```
Or with Uvicorn:
```bash
uvicorn backend.main:app --reload --host 127.0.0.1 --port 8000
```

### 5. Access the Portals
Open your web browser and navigate to:
* **Landing Gateway:** [http://127.0.0.1:8000](http://127.0.0.1:8000)
* **Interactive API Swagger Docs:** [http://127.0.0.1:8000/docs](http://127.0.0.1:8000/docs)
* **Admin Login:** [http://127.0.0.1:8000/admin/login.html](http://127.0.0.1:8000/admin/login.html) (`admin` / `admin123`)
* **Security Gate Login:** [http://127.0.0.1:8000/security/login.html](http://127.0.0.1:8000/security/login.html) (`security` / `security123`)
