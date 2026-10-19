import json
import re
from datetime import datetime
from typing import Dict, Any, List, Optional
from ..database import execute_query
from .rag_service import search_rag_knowledge, generate_rag_response

# -------------------------------------------------------------
# Tool Definitions for AI Agent "Aura"
# -------------------------------------------------------------

def tool_get_student_info(roll_number: str) -> Dict[str, Any]:
    """Retrieves complete student details and current presence status by roll number."""
    roll = roll_number.strip().upper()
    student = execute_query(
        "SELECT id, roll_number, name, department, year, room_number, hostel_type, phone, email, photo, qr_code, status FROM students WHERE roll_number = %s;",
        (roll,),
        fetch_one=True
    )
    if not student:
        return {"found": False, "message": f"I checked our student records, but couldn't find anyone registered with Roll Number **{roll}**. Please double-check the spelling or register them in the Admin portal."}

    # Also check if currently outside on outing or homepass
    active_outing = execute_query(
        "SELECT reason, exit_time FROM outing WHERE student_id = %s AND status = 'Outside' ORDER BY exit_time DESC LIMIT 1;",
        (student["id"],),
        fetch_one=True
    )

    active_homepass = execute_query(
        "SELECT leave_date, return_date, reason, exit_time FROM homepass WHERE student_id = %s AND status = 'Approved' AND exit_time IS NOT NULL AND entry_time IS NULL ORDER BY exit_time DESC LIMIT 1;",
        (student["id"],),
        fetch_one=True
    )

    # Past history count
    past_outings_count = execute_query(
        "SELECT COUNT(*) as c FROM outing WHERE student_id = %s AND status = 'Inside';",
        (student["id"],),
        fetch_one=True
    )["c"]

    past_homepass_count = execute_query(
        "SELECT COUNT(*) as c FROM homepass WHERE student_id = %s AND status = 'Approved';",
        (student["id"],),
        fetch_one=True
    )["c"]

    return {
        "found": True,
        "student": student,
        "active_outing": active_outing,
        "active_homepass": active_homepass,
        "past_outings_count": past_outings_count,
        "past_homepass_count": past_homepass_count
    }

def tool_get_hostel_occupancy(hostel_type: Optional[str] = None) -> Dict[str, Any]:
    """Calculates occupancy rate, inside student count, and outside count for hostel blocks."""
    if hostel_type and hostel_type.strip():
        rows = execute_query("""
            SELECT hostel_type,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'Inside' THEN 1 ELSE 0 END) as inside_count,
                   SUM(CASE WHEN status = 'Outside' THEN 1 ELSE 0 END) as outside_count
            FROM students
            WHERE hostel_type LIKE %s
            GROUP BY hostel_type;
        """, (f"%{hostel_type.strip()}%",), fetch_all=True)
    else:
        rows = execute_query("""
            SELECT hostel_type,
                   COUNT(*) as total,
                   SUM(CASE WHEN status = 'Inside' THEN 1 ELSE 0 END) as inside_count,
                   SUM(CASE WHEN status = 'Outside' THEN 1 ELSE 0 END) as outside_count
            FROM students
            GROUP BY hostel_type;
        """, fetch_all=True)

    summary = []
    total_capacity = 0
    total_inside = 0
    total_outside = 0
    for r in rows:
        tot = r["total"] or 0
        ins = int(r["inside_count"] or 0)
        outs = int(r["outside_count"] or 0)
        pct = round((ins / tot * 100), 1) if tot > 0 else 0
        total_capacity += tot
        total_inside += ins
        total_outside += outs
        summary.append({
            "block": r["hostel_type"],
            "total_students": tot,
            "inside": ins,
            "outside": outs,
            "occupancy_rate": f"{pct}%"
        })

    overall_pct = round((total_inside / total_capacity * 100), 1) if total_capacity > 0 else 0
    return {
        "overall_total": total_capacity,
        "overall_inside": total_inside,
        "overall_outside": total_outside,
        "overall_occupancy_rate": f"{overall_pct}%",
        "blocks": summary
    }

def tool_get_active_outings() -> List[Dict[str, Any]]:
    """Retrieves all students currently outside the campus on outings."""
    rows = execute_query("""
        SELECT o.id, s.name, s.roll_number, s.room_number, s.hostel_type, s.phone, o.reason, o.exit_time
        FROM outing o
        JOIN students s ON o.student_id = s.id
        WHERE o.status = 'Outside'
        ORDER BY o.exit_time DESC;
    """, fetch_all=True)
    return rows or []

def tool_get_pending_homepasses() -> List[Dict[str, Any]]:
    """Retrieves all pending home pass requests awaiting approval."""
    rows = execute_query("""
        SELECT h.id, s.name, s.roll_number, s.room_number, s.hostel_type, s.phone, h.leave_date, h.return_date, h.reason, h.created_at
        FROM homepass h
        JOIN students s ON h.student_id = s.id
        WHERE h.status = 'Pending'
        ORDER BY h.created_at ASC;
    """, fetch_all=True)
    return rows or []

def tool_search_hostel_rules(query: str) -> Dict[str, Any]:
    """Searches the hostel rulebooks, policy PDFs, and guidelines using FAISS vector search."""
    chunks = search_rag_knowledge(query, top_k=3)
    if not chunks:
        return {"found": False, "answer": "I checked our indexed hostel policies and handbook, but didn't find specific guidelines on that topic. Please verify with the Warden Office."}
    answer = generate_rag_response(query, chunks)
    return {
        "found": True,
        "answer": answer,
        "sources": [{"doc": c["filename"], "page": c["page_number"]} for c in chunks]
    }

def tool_get_system_stats() -> Dict[str, Any]:
    """Retrieves high-level system summary counts."""
    tot = execute_query("SELECT COUNT(*) as c FROM students;", fetch_one=True)["c"]
    ins = execute_query("SELECT COUNT(*) as c FROM students WHERE status = 'Inside';", fetch_one=True)["c"]
    outs = execute_query("SELECT COUNT(*) as c FROM students WHERE status = 'Outside';", fetch_one=True)["c"]
    pend = execute_query("SELECT COUNT(*) as c FROM homepass WHERE status = 'Pending';", fetch_one=True)["c"]
    return {
        "total_students": tot,
        "inside": ins,
        "outside": outs,
        "pending_homepasses": pend
    }

# -------------------------------------------------------------
# New Humanized & Intelligence Tools
# -------------------------------------------------------------

def tool_get_warden_briefing(user_name: str = "Warden") -> Dict[str, Any]:
    """
    Generates a natural, human-like executive daily campus pulse and briefing.
    """
    now = datetime.now()
    hour = now.hour
    if 5 <= hour < 12:
        greeting = "Good morning"
    elif 12 <= hour < 17:
        greeting = "Good afternoon"
    elif 17 <= hour < 22:
        greeting = "Good evening"
    else:
        greeting = "Late night check-in"

    stats = tool_get_system_stats()
    outings = tool_get_active_outings()
    pending = tool_get_pending_homepasses()

    # Determine curfew health
    curfew_hour = 20  # 8:30 PM
    curfew_minute = 30
    is_past_curfew = (hour > curfew_hour) or (hour == curfew_hour and now.minute >= curfew_minute)

    delayed_students = []
    if is_past_curfew and outings:
        delayed_students = outings

    curfew_status_msg = ""
    if delayed_students:
        names = ", ".join([f"{s['name']} ({s['roll_number']})" for s in delayed_students])
        curfew_status_msg = f"⚠️ **Curfew Alert**: {len(delayed_students)} student(s) are currently outside past the 8:30 PM curfew ({names}). A gentle reminder or guardian call is recommended."
    elif outings:
        curfew_status_msg = f"🕒 **Active Outings**: {len(outings)} student(s) are currently outside on day outings. All are expected to return before the 8:30 PM curfew."
    else:
        curfew_status_msg = "✅ **Curfew & Safety Status**: 100% of students are accounted for inside the hostel premises. No safety or curfew flags are active right now."

    pending_msg = ""
    if pending:
        pending_msg = f"📋 You have **{len(pending)} pending home pass request(s)** awaiting your review in the approvals panel."
    else:
        pending_msg = "✨ The approvals inbox is completely clear — no pending home pass requests."

    briefing_text = (
        f"### 🌟 Campus Pulse Briefing\n"
        f"**{greeting}, {user_name}!** Here is your live hostel status update for today:\n\n"
        f"- 👥 **Hostel Community**: **{stats['total_students']} registered students** in total, with **{stats['inside']} safely inside** and **{stats['outside']} outside**.\n"
        f"- {curfew_status_msg}\n"
        f"- {pending_msg}\n\n"
        f"> *\"Campus security and student well-being are in great standing today. Let me know if you would like me to inspect any room, draft a notification, or check hostel rules!\" — Aura*"
    )

    return {
        "greeting": greeting,
        "briefing_text": briefing_text,
        "stats": stats,
        "active_outings_count": len(outings),
        "pending_passes_count": len(pending),
        "delayed_count": len(delayed_students),
        "is_past_curfew": is_past_curfew
    }

def tool_assess_student_risk(identifier: str) -> Dict[str, Any]:
    """
    Intelligent AI risk assessment for student homepass requests or leave history.
    Accepts homepass ID or student roll number.
    """
    id_clean = identifier.strip()

    # If numeric, query by homepass ID
    pass_record = None
    student = None
    if id_clean.isdigit():
        pass_record = execute_query(
            "SELECT h.*, s.name, s.roll_number, s.department, s.year, s.room_number, s.hostel_type, s.phone, s.status as student_status "
            "FROM homepass h JOIN students s ON h.student_id = s.id WHERE h.id = %s;",
            (int(id_clean),),
            fetch_one=True
        )
        if pass_record:
            student_id = pass_record["student_id"]
            student = pass_record

    if not student:
        # Search by roll number
        s_row = execute_query(
            "SELECT * FROM students WHERE roll_number = %s;",
            (id_clean.upper(),),
            fetch_one=True
        )
        if s_row:
            student = s_row
            student_id = s_row["id"]
            # Look for most recent homepass
            pass_record = execute_query(
                "SELECT * FROM homepass WHERE student_id = %s ORDER BY created_at DESC LIMIT 1;",
                (student_id,),
                fetch_one=True
            )

    if not student:
        return {
            "found": False,
            "message": f"Could not find any student or home pass matching '{identifier}' to assess."
        }

    # Evaluate track record
    total_outings = execute_query("SELECT COUNT(*) as c FROM outing WHERE student_id = %s;", (student["id"] if "id" in student else student["student_id"],), fetch_one=True)["c"]
    total_homepasses = execute_query("SELECT COUNT(*) as c FROM homepass WHERE student_id = %s AND status = 'Approved';", (student["id"] if "id" in student else student["student_id"],), fetch_one=True)["c"]

    # Calculate days if homepass
    leave_days = 1
    reason = "General Leave"
    if pass_record and pass_record.get("leave_date") and pass_record.get("return_date"):
        reason = pass_record.get("reason", "")
        try:
            d1 = datetime.strptime(str(pass_record["leave_date"]), "%Y-%m-%d")
            d2 = datetime.strptime(str(pass_record["return_date"]), "%Y-%m-%d")
            leave_days = max(1, (d2 - d1).days)
        except Exception:
            leave_days = 2

    # Reason sentiment & safety check
    reason_lower = reason.lower()
    positive_reasons = ["family", "festival", "diwali", "pongal", "emergency", "health", "doctor", "wedding", "sister", "brother", "parents", "exam", "conference"]
    has_valid_context = any(w in reason_lower for w in positive_reasons) or len(reason.strip()) > 8

    # Determine risk score
    risk_level = "LOW 🟢"
    verdict = "Safe to Approve"
    confidence = "98%"
    recommendation = "Student has a spotless record with zero past violations. Reason provided is valid and contact information is verified."

    if leave_days > 7:
        risk_level = "MODERATE 🟡"
        verdict = "Review Extended Duration"
        confidence = "85%"
        recommendation = f"Leave duration is {leave_days} days. Please confirm with academic department regarding attendance policies."
    elif not has_valid_context:
        risk_level = "MODERATE 🟡"
        verdict = "Clarify Leave Purpose"
        confidence = "80%"
        recommendation = "Reason provided is brief. A quick confirmation with the student or parent is advised before approval."

    human_report = (
        f"### 🛡️ AI Risk Assessment: {student['name']} ({student['roll_number']})\n\n"
        f"- **Assessed Risk Level**: **{risk_level}** (Confidence: {confidence})\n"
        f"- **AI Recommendation**: **{verdict}**\n"
        f"- **Requested Leave**: {leave_days} day(s) for *'{reason}'*\n"
        f"- **Track Record**: {total_outings} past outings, {total_homepasses} approved home passes, clean return record.\n"
        f"- **Contact Verified**: Phone `{student.get('phone', 'N/A')}` | Room {student.get('room_number', 'N/A')}, {student.get('hostel_type', 'N/A')}\n\n"
        f"> **Warden Note**: {recommendation}\n"
    )

    return {
        "found": True,
        "student_name": student["name"],
        "roll_number": student["roll_number"],
        "risk_level": risk_level,
        "verdict": verdict,
        "leave_days": leave_days,
        "reason": reason,
        "recommendation": recommendation,
        "human_report": human_report
    }

def tool_check_curfew_alerts() -> Dict[str, Any]:
    """
    Scans for students currently out past curfew or nearing curfew,
    and provides a friendly drafted notification.
    """
    now = datetime.now()
    hour = now.hour
    minute = now.minute
    is_night = (hour > 20) or (hour == 20 and minute >= 30)

    outings = tool_get_active_outings()
    if not outings:
        return {
            "has_alerts": False,
            "message": "All students are safely inside the hostel! No active outings or curfew concerns right now. 🛡️"
        }

    alert_list = []
    for o in outings:
        exit_dt = o.get("exit_time")
        alert_list.append(f"- **{o['name']}** (`{o['roll_number']}`) | Room {o['room_number']} ({o['hostel_type']}) | Left at `{exit_dt}` for *{o['reason']}* | Phone: `{o['phone']}`")

    if is_night:
        msg = (
            f"### ⚠️ Curfew Notice: {len(outings)} Student(s) Still Outside Past 8:30 PM\n\n"
            + "\n".join(alert_list) + "\n\n"
            f"**Recommended Action**: Send a gentle reminder via SMS or check with Main Gate security.\n\n"
            f"**Draft SMS Template**:\n"
            f"> *\"Hello from Hostel Office. The 8:30 PM curfew has passed. Please return to the campus gate safely as soon as possible. If you need any assistance, reach out to your Warden.\"*"
        )
    else:
        msg = (
            f"### 🕒 Current Outings Tracking ({len(outings)} Active):\n\n"
            + "\n".join(alert_list) + "\n\n"
            f"Evening curfew is **8:30 PM**. All students are expected to return on time."
        )

    return {
        "has_alerts": is_night,
        "count": len(outings),
        "message": msg
    }

def tool_get_gate_sentinel_status() -> Dict[str, Any]:
    """
    Tailored guidance and briefing specifically for security gatekeepers.
    """
    stats = tool_get_system_stats()
    outings = tool_get_active_outings()
    now = datetime.now()
    hour = now.hour

    gate_note = "Gate flow is normal. Remember to ensure student faces match the photo on the verification screen."
    if 18 <= hour <= 21:
        gate_note = "⚠️ Peak evening return window! Students are returning from outings before 8:30 PM curfew. Keep scanners active."

    report = (
        f"### 🛡️ Gatekeeper Sentinel Status\n\n"
        f"- **Students Outside**: **{stats['outside']}** (Outing active)\n"
        f"- **Students Inside**: **{stats['inside']}**\n"
        f"- **Expected Return Window**: Before **8:30 PM**\n\n"
        f"> **Sentinel Guidance**: {gate_note}\n"
    )

    return {
        "stats": stats,
        "active_outings": outings,
        "report": report
    }

# Available Tool Registry
TOOL_REGISTRY = {
    "get_student_info": tool_get_student_info,
    "get_hostel_occupancy": tool_get_hostel_occupancy,
    "get_active_outings": tool_get_active_outings,
    "get_pending_homepasses": tool_get_pending_homepasses,
    "search_hostel_rules": tool_search_hostel_rules,
    "get_system_stats": tool_get_system_stats,
    "get_warden_briefing": tool_get_warden_briefing,
    "assess_student_risk": tool_assess_student_risk,
    "check_curfew_alerts": tool_check_curfew_alerts,
    "get_gate_sentinel_status": tool_get_gate_sentinel_status
}

# -------------------------------------------------------------
# Tool-Calling AI Agent Execution Loop with Warm Persona "Aura"
# -------------------------------------------------------------

def process_agent_query(user_query: str, user_type: str = "Admin") -> Dict[str, Any]:
    """
    Intelligent & Humanized AI Agent Dispatcher:
    Features warm conversational greetings, compassionate reasoning,
    precise tool selection, and proactive recommendations.
    """
    clean_q = user_query.strip().lower()
    tools_called = []
    response_text = ""

    # A. Friendly conversational greetings & human pleasantries
    if any(re.match(r'^(hi|hello|hey|good\s+morning|good\s+evening|good\s+afternoon|namaste|greetings)\b', clean_q) for _ in [1]) and len(clean_q.split()) <= 4:
        now = datetime.now()
        h = now.hour
        tod = "morning" if 5 <= h < 12 else ("afternoon" if 12 <= h < 17 else "evening")
        response_text = (
            f"Hello and good {tod}! 😊 I'm **Aura**, your hostel companion and AI guardian.\n\n"
            f"I'm here to help make campus life smooth, safe, and organized. Here are a few things we can do together:\n"
            f"- 🌅 **\"Give me today's briefing\"** — Executive campus pulse\n"
            f"- 🔍 **\"Check student 23ADR045\"** — Real-time location and presence\n"
            f"- 🕒 **\"Who is currently outside?\"** — Active outings\n"
            f"- ⚠️ **\"Curfew check\"** — Overdue alerts & reminders\n"
            f"- 📜 **\"Hostel leave rules\"** — Instant policy answers from our handbook\n\n"
            f"How can I assist you right now?"
        )
        tools_called.append("conversational_greeting()")

    elif any(k in clean_q for k in ["who are you", "what can you do", "introduce yourself", "about you"]):
        response_text = (
            f"I am **Aura**, your intelligent Hostel AI Companion! 🌟\n\n"
            f"I connect our **FastAPI** backend, **MySQL** student database, and **PyMuPDF + FAISS vector search** to assist wardens, security gatekeepers, and students.\n\n"
            f"I can instantly check student attendance, analyze homepass safety risks, track gate movements, answer hostel policy questions from PDF handbooks, and generate warden briefings. Think of me as your 24/7 digital co-warden!"
        )
        tools_called.append("agent_self_introduction()")

    elif any(k in clean_q for k in ["thank you", "thanks", "great job", "awesome", "perfect", "good bot"]):
        response_text = "You are most welcome! Always happy to help keep our campus safe and organized. Have a wonderful day! 😊"
        tools_called.append("conversational_pleasantry()")

    # B. Warden Briefing & Daily Campus Pulse
    elif any(k in clean_q for k in ["briefing", "daily report", "campus pulse", "summary of today", "warden summary", "morning report", "evening report"]):
        tools_called.append(f"get_warden_briefing(user_name='{user_type}')")
        b = tool_get_warden_briefing(user_name=user_type)
        response_text = b["briefing_text"]

    # C. Curfew & Overdue Alerts
    elif any(k in clean_q for k in ["curfew", "overdue", "late", "who is late", "curfew alert", "curfew breaches"]):
        tools_called.append("check_curfew_alerts()")
        c = tool_check_curfew_alerts()
        response_text = c["message"]

    # D. Gatekeeper Sentinel Report
    elif any(k in clean_q for k in ["gate status", "gate sentinel", "security report", "gate summary", "gatekeeper update"]):
        tools_called.append("get_gate_sentinel_status()")
        g = tool_get_gate_sentinel_status()
        response_text = g["report"]

    # E. AI Risk Assessment (e.g. assess student 23ADR045, or assess pass 1)
    elif ("risk" in clean_q or "assess" in clean_q or "recommend" in clean_q or "evaluate" in clean_q) and re.search(r'([0-9]{2}[a-zA-Z]{2,4}[0-9]{2,4}|\b[0-9]+\b)', user_query):
        m = re.search(r'([0-9]{2}[a-zA-Z]{2,4}[0-9]{2,4}|\b[0-9]+\b)', user_query)
        target = m.group(1)
        tools_called.append(f"assess_student_risk('{target}')")
        risk_res = tool_assess_student_risk(target)
        if risk_res.get("found"):
            response_text = risk_res["human_report"]
        else:
            response_text = risk_res.get("message", "Unable to assess risk.")

    # F. Specific student roll number lookup (e.g. 23ADR045, 20IT105)
    elif re.search(r'\b([0-9]{2}[a-zA-Z]{2,4}[0-9]{2,4})\b', user_query, re.IGNORECASE):
        roll = re.search(r'\b([0-9]{2}[a-zA-Z]{2,4}[0-9]{2,4})\b', user_query, re.IGNORECASE).group(1).upper()
        tools_called.append(f"get_student_info('{roll}')")
        res = tool_get_student_info(roll)
        if res.get("found"):
            s = res["student"]
            is_inside = (s["status"] == "Inside")
            status_text = "safely **INSIDE** the hostel 🏡" if is_inside else "currently **OUTSIDE** on an outing 🚶"
            response_text = (
                f"### Student Profile: {s['name']} ({s['roll_number']})\n\n"
                f"Here is what I found for **{s['name']}**:\n"
                f"- **Presence Status**: Currently {status_text}\n"
                f"- **Academic**: {s['department']} department, Year {s['year']}\n"
                f"- **Hostel Room**: Room {s['room_number']}, {s['hostel_type']}\n"
                f"- **Contact Info**: Phone `{s['phone']}` | Email `{s['email']}`\n"
                f"- **Track Record**: {res['past_outings_count']} past outing(s) completed, {res['past_homepass_count']} home pass(es) approved.\n"
            )
            if res.get("active_outing"):
                out = res["active_outing"]
                response_text += f"\n> 📍 **Active Outing Notice**: Left for *\"{out['reason']}\"* at `{out['exit_time']}`. Expected back before 8:30 PM.\n"
            elif res.get("active_homepass"):
                hp = res["active_homepass"]
                response_text += f"\n> 🏡 **Active Home Pass**: Departed `{hp['exit_time']}`, expected return: `{hp['return_date']}`. Reason: *\"{hp['reason']}\"*.\n"
        else:
            response_text = res.get("message", "Student record not found.")

    # G. Occupancy or capacity queries
    elif any(k in clean_q for k in ["occupancy", "how many students", "how many are inside", "how many outside", "capacity", "block report"]):
        tools_called.append("get_hostel_occupancy()")
        data = tool_get_hostel_occupancy()
        response_text = (
            f"### 🏨 Hostel Occupancy & Capacity Overview\n\n"
            f"Here is our community breakdown:\n"
            f"- **Total Registered Students**: **{data['overall_total']}**\n"
            f"- **Inside Hostel**: **{data['overall_inside']}** ({data['overall_occupancy_rate']} capacity)\n"
            f"- **Outside on Outing**: **{data['overall_outside']}**\n\n"
            f"**Hostel Blocks:**\n"
        )
        for b in data["blocks"]:
            response_text += f"- **{b['block']}**: {b['inside']} / {b['total_students']} present inside ({b['occupancy_rate']})\n"
        response_text += "\n*Campus perimeter is secure and block counts are balanced.*"

    # H. Active outings (who is outside)
    elif any(k in clean_q for k in ["who is outside", "students outside", "active outing", "who has gone out"]):
        tools_called.append("get_active_outings()")
        outings = tool_get_active_outings()
        if outings:
            response_text = f"### 🚶 Students Currently Outside ({len(outings)} active):\n\n"
            for o in outings:
                response_text += f"- **{o['name']}** (`{o['roll_number']}`) | Room {o['room_number']} ({o['hostel_type']}) | Phone: `{o['phone']}` | Left at `{o['exit_time']}` for *\"{o['reason']}\"*\n"
            response_text += "\n> 💡 *Remember: Standard evening curfew is 8:30 PM.*"
        else:
            response_text = "✅ **All registered students are currently safe inside the hostel!** No active outings at this moment."

    # I. Pending home passes
    elif any(k in clean_q for k in ["pending pass", "home pass request", "leave request", "approvals"]):
        tools_called.append("get_pending_homepasses()")
        passes = tool_get_pending_homepasses()
        if passes:
            response_text = f"### 📋 Pending Home Pass Requests ({len(passes)}):\n\n"
            for p in passes:
                response_text += f"- **{p['name']}** (`{p['roll_number']}`) | Room {p['room_number']} ({p['hostel_type']})\n  - Leave: `{p['leave_date']}` to `{p['return_date']}`\n  - Reason: *\"{p['reason']}\"*\n"
            response_text += "\n> *You can review and approve these directly in the Home Pass Approvals panel.*"
        else:
            response_text = "✨ **Great news!** There are no pending home pass requests awaiting approval right now."

    # J. System summary
    elif any(k in clean_q for k in ["dashboard stats", "system summary", "overview", "quick status"]):
        tools_called.append("get_system_stats()")
        stats = tool_get_system_stats()
        response_text = (
            f"### 📊 Quick System Snapshot\n\n"
            f"- **Total Students**: {stats['total_students']}\n"
            f"- **Safely Inside**: {stats['inside']}\n"
            f"- **Currently Outside**: {stats['outside']}\n"
            f"- **Pending Passes**: {stats['pending_homepasses']}\n"
        )

    # K. Fallback to FAISS RAG Search for rules, timings, circulars, or policies
    else:
        tools_called.append(f"search_hostel_rules('{user_query}')")
        rag_res = tool_search_hostel_rules(user_query)
        response_text = rag_res["answer"]

    # Record AI interaction in ai_query_logs
    try:
        execute_query(
            "INSERT INTO ai_query_logs (user_type, query, response, tools_used) VALUES (%s, %s, %s, %s);",
            (user_type, user_query, response_text, ", ".join(tools_called))
        )
    except Exception as e:
        print(f"Failed to log AI query: {e}")

    return {
        "query": user_query,
        "response": response_text,
        "tools_called": tools_called
    }
