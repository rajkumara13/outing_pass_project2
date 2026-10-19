from fastapi import APIRouter, HTTPException, Depends, Request
from pydantic import BaseModel
from typing import Optional
from ..database import execute_query, get_db
from ..auth import require_admin, require_security

router = APIRouter(prefix="/api/homepass", tags=["Home Pass"])

class HomepassApplyRequest(BaseModel):
    roll_number: str
    leave_date: str
    return_date: str
    reason: str
    status: str = "Approved"
    remarks: Optional[str] = ""

class HomepassActionRequest(BaseModel):
    action: str  # "approve" or "reject"
    remarks: Optional[str] = ""

class HomepassGateRequest(BaseModel):
    roll_number: str
    homepass_id: int
    action: str  # "exit" or "entry"

@router.post("/apply")
def apply_homepass(data: HomepassApplyRequest, request: Request):
    require_admin(request)
    clean_roll = data.roll_number.strip().upper()

    student = execute_query(
        "SELECT id, name FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail=f"Student with Roll Number {clean_roll} not found.")

    if data.leave_date > data.return_date:
        raise HTTPException(status_code=400, detail="Return date must be on or after leave date.")

    # Insert home pass
    insert_sql = """
        INSERT INTO homepass (student_id, leave_date, return_date, reason, status, admin_remarks)
        VALUES (%s, %s, %s, %s, %s, %s);
    """
    hp_id = execute_query(
        insert_sql,
        (student["id"], data.leave_date, data.return_date, data.reason.strip(), data.status, (data.remarks or "").strip())
    )

    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('Admin', 'Admin Apply Home Pass', %s);",
            (f"Applied Home Pass for {student['name']} ({clean_roll}). Status: {data.status}",)
        )
    except Exception:
        pass

    return {
        "success": True,
        "message": f"Home pass created with status: {data.status}",
        "homepass_id": hp_id
    }

@router.get("/pending")
def list_pending():
    rows = execute_query("""
        SELECT h.*, s.name, s.roll_number, s.room_number, s.hostel_type
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE h.status = 'Pending'
        ORDER BY h.created_at ASC;
    """, fetch_all=True)
    return {"pending_requests": rows or []}

@router.get("/history")
def list_history(limit: int = 15):
    rows = execute_query("""
        SELECT h.*, s.name, s.roll_number, s.room_number, s.hostel_type
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE h.status != 'Pending'
        ORDER BY h.created_at DESC
        LIMIT %s;
    """, (limit,), fetch_all=True)
    return {"history": rows or []}

@router.post("/{hp_id}/action")
def update_homepass_status(hp_id: int, data: HomepassActionRequest, request: Request):
    require_security(request)  # Either admin or security
    action = data.action.strip().lower()
    if action not in ["approve", "reject"]:
        raise HTTPException(status_code=400, detail="Action must be 'approve' or 'reject'.")

    status_str = "Approved" if action == "approve" else "Rejected"
    hp = execute_query("""
        SELECT h.*, s.name, s.roll_number
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE h.id = %s;
    """, (hp_id,), fetch_one=True)

    if not hp:
        raise HTTPException(status_code=404, detail="Home pass request not found.")

    execute_query("""
        UPDATE homepass
        SET status = %s, admin_remarks = %s
        WHERE id = %s;
    """, (status_str, data.remarks or "", hp_id))

    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('Security', %s, %s);",
            (f"{status_str} Home Pass", f"Home Pass for {hp['name']} ({hp['roll_number']}) was {status_str}. Remarks: {data.remarks}")
        )
    except Exception:
        pass

    return {"success": True, "status": status_str, "message": f"Home pass successfully {status_str.lower()}."}

@router.get("/verify/{roll_number}")
def verify_homepass_at_gate(roll_number: str):
    clean_roll = roll_number.strip().upper()
    student = execute_query(
        "SELECT * FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")

    status = student["status"]
    if status == "Inside":
        hp = execute_query("""
            SELECT * FROM homepass
            WHERE student_id = %s AND status = 'Approved' AND exit_time IS NULL AND entry_time IS NULL
            ORDER BY created_at DESC LIMIT 1;
        """, (student["id"],), fetch_one=True)
    else:
        hp = execute_query("""
            SELECT * FROM homepass
            WHERE student_id = %s AND status = 'Approved' AND exit_time IS NOT NULL AND entry_time IS NULL
            ORDER BY created_at DESC LIMIT 1;
        """, (student["id"],), fetch_one=True)

    return {
        "student": student,
        "is_approved": hp is not None,
        "homepass": hp
    }

@router.post("/confirm-gate")
def confirm_homepass_gate(data: HomepassGateRequest, request: Request):
    require_security(request)
    clean_roll = data.roll_number.strip().upper()

    with get_db() as (cursor, conn):
        cursor.execute("SELECT * FROM students WHERE roll_number = %s LIMIT 1;", (clean_roll,))
        student = cursor.fetchone()
        if not student:
            raise HTTPException(status_code=404, detail="Student not found.")

        if data.action == "exit":
            cursor.execute("UPDATE homepass SET exit_time = CURRENT_TIMESTAMP WHERE id = %s;", (data.homepass_id,))
            cursor.execute("UPDATE students SET status = 'Outside' WHERE id = %s;", (student["id"],))
            cursor.execute(
                "INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Home Pass Exit', %s);",
                (f"Student {student['name']} ({clean_roll}) exited gate on approved Home Pass (ID: {data.homepass_id})",)
            )
            return {"success": True, "status": "success_exit", "message": "Home pass exit approved."}
        elif data.action == "entry":
            cursor.execute("UPDATE homepass SET entry_time = CURRENT_TIMESTAMP WHERE id = %s;", (data.homepass_id,))
            cursor.execute("UPDATE students SET status = 'Inside' WHERE id = %s;", (student["id"],))
            cursor.execute(
                "INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Home Pass Entry', %s);",
                (f"Student {student['name']} ({clean_roll}) returned to hostel on Home Pass (ID: {data.homepass_id})",)
            )
            return {"success": True, "status": "success_entry", "message": "Home pass entry check-in registered."}
        else:
            raise HTTPException(status_code=400, detail="Invalid action.")
