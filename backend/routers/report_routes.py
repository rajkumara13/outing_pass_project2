from datetime import date
from typing import Optional
from fastapi import APIRouter, Query
from ..database import execute_query

router = APIRouter(prefix="/api/reports", tags=["Reports & Analytics"])

@router.get("/dashboard-stats")
def get_dashboard_stats():
    tot = execute_query("SELECT COUNT(*) as c FROM students;", fetch_one=True)["c"]
    ins = execute_query("SELECT COUNT(*) as c FROM students WHERE status = 'Inside';", fetch_one=True)["c"]
    outs = execute_query("SELECT COUNT(*) as c FROM students WHERE status = 'Outside';", fetch_one=True)["c"]
    pend = execute_query("SELECT COUNT(*) as c FROM homepass WHERE status = 'Pending';", fetch_one=True)["c"]

    recent_logs = execute_query(
        "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 6;",
        fetch_all=True
    ) or []

    active_outings = execute_query("""
        SELECT o.*, s.name, s.roll_number, s.room_number, s.hostel_type, s.phone
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE o.status = 'Outside'
        ORDER BY o.exit_time DESC LIMIT 10;
    """, fetch_all=True) or []

    return {
        "total_students": tot,
        "inside_students": ins,
        "outside_students": outs,
        "pending_home_passes": pend,
        "recent_logs": recent_logs,
        "active_outings": active_outings
    }

@router.get("/daily")
def get_daily_report(date_str: Optional[str] = Query(None)):
    target_date = date_str if date_str else date.today().isoformat()

    outings = execute_query("""
        SELECT o.id, s.name, s.roll_number, s.room_number, s.hostel_type, o.reason, o.exit_time, o.entry_time, 'Outing' as pass_type
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE DATE(o.exit_time) = %s OR DATE(o.entry_time) = %s
        ORDER BY o.exit_time DESC;
    """, (target_date, target_date), fetch_all=True) or []

    homepasses = execute_query("""
        SELECT h.id, s.name, s.roll_number, s.room_number, s.hostel_type, h.reason, h.exit_time, h.entry_time, 'Home Pass' as pass_type
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE (DATE(h.exit_time) = %s OR DATE(h.entry_time) = %s) AND h.status = 'Approved'
        ORDER BY h.exit_time DESC;
    """, (target_date, target_date), fetch_all=True) or []

    combined = outings + homepasses
    combined.sort(key=lambda x: str(x.get("exit_time") or ""), reverse=True)

    return {"date": target_date, "records": combined, "total": len(combined)}

@router.get("/monthly")
def get_monthly_report(year_month: Optional[str] = Query(None)):
    # Format: YYYY-MM
    ym = year_month if year_month else date.today().strftime("%Y-%m")
    parts = ym.split("-")
    year = int(parts[0])
    month = int(parts[1])

    outings = execute_query("""
        SELECT o.id, s.name, s.roll_number, s.hostel_type, o.reason, o.exit_time, o.entry_time, 'Outing' as pass_type
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE YEAR(o.exit_time) = %s AND MONTH(o.exit_time) = %s
        ORDER BY o.exit_time DESC;
    """, (year, month), fetch_all=True) or []

    homepasses = execute_query("""
        SELECT h.id, s.name, s.roll_number, s.hostel_type, h.reason, h.exit_time, h.entry_time, 'Home Pass' as pass_type
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE YEAR(h.exit_time) = %s AND MONTH(h.exit_time) = %s AND h.status = 'Approved'
        ORDER BY h.exit_time DESC;
    """, (year, month), fetch_all=True) or []

    combined = outings + homepasses
    combined.sort(key=lambda x: str(x.get("exit_time") or ""), reverse=True)

    return {"year_month": ym, "records": combined, "total": len(combined)}

@router.get("/student-history/{roll_number}")
def get_student_history(roll_number: str):
    clean_roll = roll_number.strip().upper()
    student = execute_query(
        "SELECT * FROM students WHERE roll_number = %s LIMIT 1;",
        (clean_roll,),
        fetch_one=True
    )
    if not student:
        return {"found": False, "student": None, "history": []}

    outings = execute_query("""
        SELECT id, reason, exit_time, entry_time, 'Outing' as pass_type
        FROM outing
        WHERE student_id = %s;
    """, (student["id"],), fetch_all=True) or []

    homepasses = execute_query("""
        SELECT id, reason, exit_time, entry_time, 'Home Pass' as pass_type
        FROM homepass
        WHERE student_id = %s AND status = 'Approved';
    """, (student["id"],), fetch_all=True) or []

    combined = outings + homepasses
    combined.sort(key=lambda x: str(x.get("exit_time") or ""), reverse=True)

    return {"found": True, "student": student, "history": combined}

@router.get("/occupancy")
def get_occupancy_report():
    rows = execute_query("""
        SELECT hostel_type,
               COUNT(*) as total,
               SUM(CASE WHEN status = 'Inside' THEN 1 ELSE 0 END) as inside_count,
               SUM(CASE WHEN status = 'Outside' THEN 1 ELSE 0 END) as outside_count
        FROM students
        GROUP BY hostel_type;
    """, fetch_all=True) or []

    blocks = []
    tot_sum = 0
    in_sum = 0
    out_sum = 0
    for r in rows:
        t = r["total"] or 0
        i = int(r["inside_count"] or 0)
        o = int(r["outside_count"] or 0)
        pct = round((i / t * 100), 1) if t > 0 else 0
        tot_sum += t
        in_sum += i
        out_sum += o
        blocks.append({
            "hostel_type": r["hostel_type"],
            "total": t,
            "inside": i,
            "outside": o,
            "occupancy_rate": pct
        })

    overall_pct = round((in_sum / tot_sum * 100), 1) if tot_sum > 0 else 0

    return {
        "blocks": blocks,
        "overall_total": tot_sum,
        "overall_inside": in_sum,
        "overall_outside": out_sum,
        "overall_rate": overall_pct
    }
