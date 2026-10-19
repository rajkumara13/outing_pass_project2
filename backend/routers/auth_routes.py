from fastapi import APIRouter, HTTPException, status, Response, Request
from pydantic import BaseModel
from typing import Optional
from ..database import execute_query
from ..auth import verify_password, create_access_token, get_current_user_from_request

router = APIRouter(prefix="/api/auth", tags=["Authentication"])

class LoginRequest(BaseModel):
    username: str
    password: str
    role: str = "admin"  # "admin" or "security"

class LoginResponse(BaseModel):
    success: bool
    message: str
    access_token: str
    token_type: str = "bearer"
    role: str
    user: dict

@router.post("/login", response_model=LoginResponse)
def login(creds: LoginRequest, response: Response):
    username = creds.username.strip()
    password = creds.password.strip()
    role = creds.role.strip().lower()

    if role == "admin":
        user = execute_query(
            "SELECT id, username, password FROM admin WHERE username = %s LIMIT 1;",
            (username,),
            fetch_one=True
        )
    elif role == "security":
        user = execute_query(
            "SELECT id, username, password, name, phone FROM security WHERE username = %s LIMIT 1;",
            (username,),
            fetch_one=True
        )
    else:
        raise HTTPException(status_code=400, detail="Invalid role specified.")

    if not user or not verify_password(password, user["password"]):
        # Log failed attempt
        try:
            execute_query(
                "INSERT INTO activity_logs (user_type, action, details) VALUES (%s, %s, %s);",
                (role.capitalize(), "Failed Login", f"Failed login attempt for username: {username}")
            )
        except Exception:
            pass
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Invalid username or password."
        )

    # Generate token payload
    token_payload = {
        "sub": str(user["id"]),
        "username": user["username"],
        "role": role,
        "name": user.get("name", "Administrator")
    }
    token = create_access_token(token_payload)

    # Set HTTP-only cookie for browser navigation compatibility
    response.set_cookie(
        key="access_token",
        value=f"Bearer {token}",
        httponly=True,
        max_age=60 * 60 * 24,
        samesite="lax",
        secure=False
    )

    # Log successful login
    try:
        execute_query(
            "INSERT INTO activity_logs (user_type, user_id, action, details) VALUES (%s, %s, %s, %s);",
            (role.capitalize(), user["id"], "Login", f"{role.capitalize()} logged in successfully.")
        )
    except Exception:
        pass

    clean_user = {
        "id": user["id"],
        "username": user["username"],
        "name": user.get("name", "Administrator"),
        "role": role
    }

    return LoginResponse(
        success=True,
        message="Login successful",
        access_token=token,
        role=role,
        user=clean_user
    )

@router.get("/me")
def get_current_user_profile(request: Request):
    user = get_current_user_from_request(request)
    return {"authenticated": True, "user": user}

@router.post("/logout")
def logout(response: Response):
    response.delete_cookie(key="access_token")
    return {"success": True, "message": "Successfully logged out"}
