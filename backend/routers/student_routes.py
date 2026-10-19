import time
from pathlib import Path
from typing import Optional
from fastapi import APIRouter, Form, UploadFile, File, HTTPException, Depends, Query, Request
from ..database import execute_query
from ..config import UPLOADS_DIR, ASSETS_QR_DIR
from ..services.qr_service import generate_student_qr
from ..auth import require_admin

router = APIRouter(prefix="/api/students", tags=["Students"])

@router.get("")
def list_students(
    search: Optional[str] = Query(None),
    hostel_type: Optional[str] = Query(None),
    status: Optional[str] = Query(None)
):
    sql = "SELECT * FROM students WHERE 1=1"
    params = []

    if search and search.strip():
        sql += " AND (roll_number LIKE %s OR name LIKE %s OR department LIKE %s)"
        s = f"%{search.strip()}%"
        params.extend([s, s, s])

    if hostel_type and hostel_type.strip():
        sql += " AND hostel_type = %s"
        params.append(hostel_type.strip())

    if status and status.strip():
        sql += " AND status = %s"
        params.append(status.strip())

    sql += " ORDER BY roll_number ASC;"
    students = execute_query(sql, tuple(params), fetch_all=True) or []
    return {"students": students, "total": len(students)}

@router.get("/{roll_number}")
def get_student(roll_number: str):
    clean_roll = roll_number.strip().upper()
    student = execute_query(
        "SELECT * FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")
    return student

@router.post("")
async def create_student(
    request: Request,
    roll_number: str = Form(...),
    name: str = Form(...),
    department: str = Form(...),
    year: int = Form(...),
    room_number: str = Form(...),
    hostel_type: str = Form(...),
    phone: str = Form(...),
    email: str = Form(...),
    photo: UploadFile = File(...)
):
    require_admin(request)
    clean_roll = roll_number.strip().upper()
    
    # Check if student exists
    existing = execute_query(
        "SELECT id FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if existing:
        raise HTTPException(status_code=400, detail=f"Student with Roll Number {clean_roll} is already registered.")

    # Save photo
    photo_ext = photo.filename.split(".")[-1].lower() if "." in photo.filename else "jpg"
    if photo_ext not in ["jpg", "jpeg", "png"]:
        raise HTTPException(status_code=400, detail="Photo must be a JPG or PNG image.")

    timestamp = int(time.time())
    photo_filename = f"{clean_roll}_{timestamp}.{photo_ext}"
    photo_path = UPLOADS_DIR / photo_filename
    
    content = await photo.read()
    with open(photo_path, "wb") as f:
        f.write(content)

    # Generate QR Code locally
    qr_path = generate_student_qr(clean_roll)

    # Insert student into MySQL
    insert_sql = """
        INSERT INTO students (roll_number, name, department, year, room_number, hostel_type, phone, email, photo, qr_code, status)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 'Inside');
    """
    student_id = execute_query(
        insert_sql,
        (clean_roll, name.strip(), department.strip(), year, room_number.strip(), hostel_type.strip(), phone.strip(), email.strip(), photo_filename, qr_path)
    )

    # Activity log
    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('Admin', 'Add Student', %s);",
            (f"Registered student {name} ({clean_roll}) in {hostel_type} Room {room_number}",)
        )
    except Exception:
        pass

    return {
        "success": True,
        "message": f"Student {name} ({clean_roll}) registered successfully.",
        "student_id": student_id,
        "qr_code": qr_path
    }

@router.put("/{roll_number}")
async def update_student(
    request: Request,
    roll_number: str,
    name: str = Form(...),
    department: str = Form(...),
    year: int = Form(...),
    room_number: str = Form(...),
    hostel_type: str = Form(...),
    phone: str = Form(...),
    email: str = Form(...),
    photo: Optional[UploadFile] = File(None)
):
    require_admin(request)
    clean_roll = roll_number.strip().upper()

    student = execute_query(
        "SELECT * FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")

    photo_filename = student["photo"]

    if photo and photo.filename:
        photo_ext = photo.filename.split(".")[-1].lower() if "." in photo.filename else "jpg"
        if photo_ext in ["jpg", "jpeg", "png"]:
            # Delete old photo if exists
            if photo_filename:
                old_path = UPLOADS_DIR / photo_filename
                if old_path.exists():
                    old_path.unlink()

            timestamp = int(time.time())
            photo_filename = f"{clean_roll}_{timestamp}.{photo_ext}"
            new_path = UPLOADS_DIR / photo_filename
            content = await photo.read()
            with open(new_path, "wb") as f:
                f.write(content)

    update_sql = """
        UPDATE students
        SET name = %s, department = %s, year = %s, room_number = %s, hostel_type = %s, phone = %s, email = %s, photo = %s
        WHERE roll_number = %s;
    """
    execute_query(
        update_sql,
        (name.strip(), department.strip(), year, room_number.strip(), hostel_type.strip(), phone.strip(), email.strip(), photo_filename, clean_roll)
    )

    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('Admin', 'Edit Student', %s);",
            (f"Updated details of student {name} ({clean_roll})",)
        )
    except Exception:
        pass

    return {"success": True, "message": "Student details updated successfully."}

@router.delete("/{roll_number}")
def delete_student(request: Request, roll_number: str):
    require_admin(request)
    clean_roll = roll_number.strip().upper()

    student = execute_query(
        "SELECT id, name, photo, qr_code FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")

    # Remove photo and QR
    if student["photo"]:
        p = UPLOADS_DIR / student["photo"]
        if p.exists():
            p.unlink()

    for ext in [".png", ".svg"]:
        q = ASSETS_QR_DIR / f"{clean_roll}{ext}"
        if q.exists():
            q.unlink()

    execute_query("DELETE FROM students WHERE roll_number = %s;", (clean_roll,))

    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('Admin', 'Delete Student', %s);",
            (f"Deleted student {student['name']} ({clean_roll})",)
        )
    except Exception:
        pass

    return {"success": True, "message": f"Student {student['name']} deleted successfully."}

@router.post("/{roll_number}/regenerate-qr")
def regenerate_qr(request: Request, roll_number: str):
    require_admin(request)
    clean_roll = roll_number.strip().upper()
    student = execute_query("SELECT id FROM students WHERE roll_number = %s;", (clean_roll,), fetch_one=True)
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")

    qr_path = generate_student_qr(clean_roll)
    execute_query("UPDATE students SET qr_code = %s WHERE roll_number = %s;", (qr_path, clean_roll))
    return {"success": True, "qr_code": qr_path}
