from fastapi import APIRouter, Request, Query
from pydantic import BaseModel
from typing import Optional
from ..services.ai_agent_service import (
    process_agent_query,
    tool_get_warden_briefing,
    tool_assess_student_risk,
    tool_get_gate_sentinel_status,
    tool_check_curfew_alerts
)
from ..database import execute_query

router = APIRouter(prefix="/api/agent", tags=["AI Agent"])

class AgentChatRequest(BaseModel):
    message: str
    user_type: Optional[str] = "Admin"

@router.post("/chat")
def chat_with_agent(data: AgentChatRequest):
    result = process_agent_query(data.message, data.user_type or "Admin")
    return result

@router.get("/briefing")
def get_campus_briefing(user_name: str = Query("Warden")):
    """Returns a natural, warm, humanized Warden Campus Pulse Briefing."""
    return tool_get_warden_briefing(user_name=user_name)

@router.get("/assess-pass/{pass_id}")
def assess_homepass_risk(pass_id: str):
    """Returns instant AI risk assessment & approval recommendations for a homepass."""
    return tool_assess_student_risk(pass_id)

@router.get("/gate-sentinel")
def get_gate_sentinel():
    """Returns AI Sentinel guidance specifically for security gatekeepers."""
    return tool_get_gate_sentinel_status()

@router.get("/curfew-alerts")
def get_curfew_alerts():
    """Returns active curfew tracking and ready-to-send reminder notifications."""
    return tool_check_curfew_alerts()

@router.get("/logs")
def get_agent_query_logs(limit: int = 20):
    logs = execute_query(
        "SELECT * FROM ai_query_logs ORDER BY created_at DESC LIMIT %s;",
        (limit,),
        fetch_all=True
    ) or []
    return {"logs": logs}
