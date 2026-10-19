from fastapi import APIRouter, HTTPException, Depends, Request
from pydantic import BaseModel
from typing import Optional
from ..database import execute_query, get_db
from ..auth import require_security

router = APIRouter(prefix="/api/outing", tags=["Outing"])

class OutingExitRequest(BaseModel):
    roll_number: str
    reason: str = "Personal"

class OutingEntryRequest(BaseModel):
    roll_number: str

@router.get("/verify/{roll_number}")
def verify_for_outing(roll_number: str):
    clean_roll = roll_number.strip().upper()
    student = execute_query(
        "SELECT * FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        raise HTTPException(status_code=404, detail="Student not found.")

    active_outing = None
    if student["status"] == "Outside":
        active_outing = execute_query(
            "SELECT * FROM outing WHERE student_id = %s AND status = 'Outside' ORDER BY exit_time DESC LIMIT 1;",
            (student["id"],),
            fetch_one=True
        )

    return {
        "student": student,
        "is_outside": student["status"] == "Outside",
        "active_outing": active_outing
    }

@router.post("/exit")
def register_outing_exit(data: OutingExitRequest, request: Request):
    require_security(request)
    clean_roll = data.roll_number.strip().upper()
    reason = data.reason.strip() or "Personal"

    with get_db() as (cursor, conn):
        cursor.execute("SELECT * FROM students WHERE roll_number = %s LIMIT 1;", (clean_roll,))
        student = cursor.fetchone()
        if not student:
            raise HTTPException(status_code=404, detail="Student not found.")

        # Insert outing
        cursor.execute(
            "INSERT INTO outing (student_id, reason, exit_time, status) VALUES (%s, %s, CURRENT_TIMESTAMP, 'Outside');",
            (student["id"], reason)
        )

        # Update student status
        cursor.execute("UPDATE students SET status = 'Outside' WHERE id = %s;", (student["id"],))

        # Log action
        cursor.execute(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Outing Exit', %s);",
            (f"Student {student['name']} ({clean_roll}) checked out for outing. Reason: {reason}",)
        )

    return {"success": True, "status": "success_exit", "message": "Gate exit successfully registered."}

@router.post("/entry")
def register_outing_entry(data: OutingEntryRequest, request: Request):
    require_security(request)
    clean_roll = data.roll_number.strip().upper()

    with get_db() as (cursor, conn):
        cursor.execute("SELECT * FROM students WHERE roll_number = %s LIMIT 1;", (clean_roll,))
        student = cursor.fetchone()
        if not student:
            raise HTTPException(status_code=404, detail="Student not found.")

        # Check active outing
        cursor.execute(
            "SELECT id FROM outing WHERE student_id = %s AND status = 'Outside' ORDER BY exit_time DESC LIMIT 1;",
            (student["id"],)
        )
        active_outing = cursor.fetchone()

        if active_outing:
            cursor.execute(
                "UPDATE outing SET entry_time = CURRENT_TIMESTAMP, status = 'Returned' WHERE id = %s;",
                (active_outing["id"],)
            )
        else:
            # Fallback if student was outside under home pass or manual mark
            cursor.execute(
                "INSERT INTO outing (student_id, reason, exit_time, entry_time, status) VALUES (%s, 'Returned check-in', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 'Returned');",
                (student["id"],)
            )

        cursor.execute("UPDATE students SET status = 'Inside' WHERE id = %s;", (student["id"],))

        cursor.execute(
            "INSERT INTO activity_logs (user_type, action, details) VALUES ('System', 'Outing Entry', %s);",
            (f"Student {student['name']} ({clean_roll}) returned to hostel and checked in.",)
        )

    return {"success": True, "status": "success_entry", "message": "Hostel entry check-in successfully logged."}

@router.get("/active")
def get_active_outings():
    outings = execute_query("""
        SELECT o.id, s.name, s.roll_number, s.room_number, s.hostel_type, s.phone, o.reason, o.exit_time
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE o.status = 'Outside'
        ORDER BY o.exit_time DESC;
    """, fetch_all=True)
    return {"active_outings": outings or []}
