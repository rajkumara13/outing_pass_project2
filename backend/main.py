from fastapi import FastAPI, Request
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse, RedirectResponse
from fastapi.middleware.cors import CORSMiddleware
from pathlib import Path

from .config import BASE_DIR
from .routers import (
    auth_routes,
    student_routes,
    outing_routes,
    homepass_routes,
    report_routes,
    rag_routes,
    agent_routes
)

app = FastAPI(
    title="Student Hostel Outpass Management System & AI Agent",
    version="2.0",
    description="FastAPI Backend with PyMuPDF RAG, FAISS Vector Search, and Tool-Calling AI Agent"
)

# Enable CORS for flexible multi-device gate testing
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Mount all static asset directories
app.mount("/css", StaticFiles(directory=str(BASE_DIR / "css")), name="css")
app.mount("/js", StaticFiles(directory=str(BASE_DIR / "js")), name="js")
app.mount("/webfonts", StaticFiles(directory=str(BASE_DIR / "webfonts")), name="webfonts")
app.mount("/assets", StaticFiles(directory=str(BASE_DIR / "assets")), name="assets")
app.mount("/uploads", StaticFiles(directory=str(BASE_DIR / "uploads")), name="uploads")

# Include API Routers
app.include_router(auth_routes.router)
app.include_router(student_routes.router)
app.include_router(outing_routes.router)
app.include_router(homepass_routes.router)
app.include_router(report_routes.router)
app.include_router(rag_routes.router)
app.include_router(agent_routes.router)

# -------------------------------------------------------------
# Frontend HTML Page Routes
# -------------------------------------------------------------

@app.get("/")
def serve_index():
    return FileResponse(BASE_DIR / "index.html")

@app.get("/index.html")
def serve_index_html():
    return FileResponse(BASE_DIR / "index.html")

@app.get("/projectsdata.pdf")
def serve_projectsdata_pdf():
    p = BASE_DIR / "projectsdata.pdf"
    if p.exists():
        return FileResponse(p, media_type="application/pdf", filename="projectsdata.pdf")
    raise HTTPException(status_code=404, detail="projectsdata.pdf not found.")

@app.get("/hostel_rules_2026.pdf")
def serve_hostel_rules_pdf():
    p = BASE_DIR / "uploads" / "rag_docs" / "hostel_rules_2026.pdf"
    if p.exists():
        return FileResponse(p, media_type="application/pdf", filename="hostel_rules_2026.pdf")
    raise HTTPException(status_code=404, detail="hostel_rules_2026.pdf not found.")

# Admin Pages
@app.get("/admin/login.html")
@app.get("/admin/login")
def serve_admin_login():
    return FileResponse(BASE_DIR / "admin" / "login.html")

@app.get("/admin/dashboard.html")
@app.get("/admin/dashboard")
def serve_admin_dashboard():
    p = BASE_DIR / "admin" / "dashboard.html"
    return FileResponse(p if p.exists() else BASE_DIR / "admin" / "login.html")

@app.get("/admin/view_students.html")
@app.get("/admin/view_students")
def serve_admin_view_students():
    p = BASE_DIR / "admin" / "view_students.html"
    return FileResponse(p if p.exists() else BASE_DIR / "admin" / "login.html")

@app.get("/admin/add_student.html")
@app.get("/admin/add_student")
def serve_admin_add_student():
    return FileResponse(BASE_DIR / "admin" / "add_student.html")

@app.get("/admin/apply_homepass.html")
@app.get("/admin/apply_homepass")
def serve_admin_apply_homepass():
    p = BASE_DIR / "admin" / "apply_homepass.html"
    return FileResponse(p if p.exists() else BASE_DIR / "admin" / "login.html")

@app.get("/admin/approve_homepass.html")
@app.get("/admin/approve_homepass")
def serve_admin_approve_homepass():
    p = BASE_DIR / "admin" / "approve_homepass.html"
    return FileResponse(p if p.exists() else BASE_DIR / "admin" / "login.html")

@app.get("/admin/reports.html")
@app.get("/admin/reports")
def serve_admin_reports():
    p = BASE_DIR / "admin" / "reports.html"
    return FileResponse(p if p.exists() else BASE_DIR / "admin" / "login.html")

# Security Gate Pages
@app.get("/security/login.html")
@app.get("/security/login")
def serve_security_login():
    return FileResponse(BASE_DIR / "security" / "login.html")

@app.get("/security/dashboard.html")
@app.get("/security/dashboard")
def serve_security_dashboard():
    p = BASE_DIR / "security" / "dashboard.html"
    return FileResponse(p if p.exists() else BASE_DIR / "security" / "login.html")

@app.get("/security/approve_homepass.html")
@app.get("/security/approve_homepass")
def serve_security_approve_homepass():
    p = BASE_DIR / "security" / "approve_homepass.html"
    return FileResponse(p if p.exists() else BASE_DIR / "security" / "login.html")

# Outing Scanners and Pages
@app.get("/outing/scan.html")
@app.get("/outing/scan")
def serve_outing_scan():
    p = BASE_DIR / "outing" / "scan.html"
    return FileResponse(p if p.exists() else BASE_DIR / "outing" / "scan.html")

@app.get("/outing/outing.html")
@app.get("/outing/outing")
def serve_outing_verify():
    p = BASE_DIR / "outing" / "outing.html"
    return FileResponse(p if p.exists() else BASE_DIR / "outing" / "scan.html")

@app.get("/outing/success.html")
@app.get("/outing/success")
def serve_outing_success():
    p = BASE_DIR / "outing" / "success.html"
    return FileResponse(p if p.exists() else BASE_DIR / "outing" / "scan.html")

# Homepass Scanners and Pages
@app.get("/homepass/scan.html")
@app.get("/homepass/scan")
def serve_homepass_scan():
    p = BASE_DIR / "homepass" / "scan.html"
    return FileResponse(p if p.exists() else BASE_DIR / "homepass" / "scan.html")

@app.get("/homepass/check_pass.html")
@app.get("/homepass/check_pass")
def serve_homepass_check_pass():
    p = BASE_DIR / "homepass" / "check_pass.html"
    return FileResponse(p if p.exists() else BASE_DIR / "homepass" / "scan.html")

# Startup event: Ingest sample projectsdata.pdf if present
@app.on_event("startup")
def on_startup():
    try:
        from .routers.rag_routes import auto_index_sample_pdf
        res = auto_index_sample_pdf()
        print(f"Startup RAG Indexing: {res}")
    except Exception as e:
        print(f"Startup indexing notice: {e}")
