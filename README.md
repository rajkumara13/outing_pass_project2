# Student Hostel Outpass Management System

A modern, responsive, and secure web application for managing student daily outings and home passes. The system features a glassmorphic dark user interface and is designed to operate 100% offline at the gate by downloading all necessary UI styles and webcam libraries locally during installation.

---

## Key Features

1. **Gatekeeper Security Console:**
   - Restricted to logged-in security personnel only.
   - Dual secure scanner pages for **Daily Outing** and **Home Pass** verification.
   - Fallback manual entry forms in case of webcam or code reading issues.
   - Success/failure screens with audio beep feedback using the Web Audio API.

2. **Warden/Admin Panel:**
   - Manage student profiles (Register, View, Edit, Delete).
   - Dynamic local QR Code generation (automatically falls back to crisp SVG images if the PHP GD extension is disabled on the server).
   - Register and review student Home Pass applications (Approve/Reject with remarks).
   - Occupancy rate tracking and printable activity/chronology logs.

3. **100% Local & Offline Capability:**
   - All styling (Bootstrap 5) and icons (Font Awesome 6) are stored locally.
   - QR scanner decoding uses a local build of `html5-qrcode`.
   - Setup installer fetches all libraries automatically to allow offline gate operation.

---

## Directory Layout

```
student_outpass/
├── index.html                 # Balanced gateway landing page
├── admin/                     # Admin & Warden portals (reports, approvals, QR generator)
├── security/                  # Gatekeeper portals (logs, status grids)
├── outing/                    # Outing exit/entry scanners and registers
├── homepass/                  # Home pass exit/entry scanners and check-ins
├── config/                    # Connection setup and installation script
├── css/                       # Local stylesheets (Bootstrap 5, Font Awesome, theme files)
├── js/                        # Local scripts (webcam decoder, UI logic)
├── webfonts/                  # Font Awesome offline font files
├── uploads/                   # Uploaded student profile photos
└── assets/                    # Generated student QR codes
```

---

## Setup Instructions

To run this project on any system:

### 1. Requirements
Ensure the host system has:
* An Apache Web Server (e.g., XAMPP, WAMP, or Laragon)
* PHP 7.4 or higher
* MySQL database server
* PHP PDO Extension enabled (default in XAMPP)

### 2. File Placement
Copy the entire `student_outpass` folder into your server's web root directory (e.g., `C:\xampp\htdocs\student_outpass\`).

### 3. Database & Assets Initialization
1. Start your Apache and MySQL servers.
2. Open your web browser and go to:
   `http://localhost/student_outpass/config/setup.php`
3. The installer will automatically:
   - Create the `student_outpass` database and tables.
   - Fetch and download all offline CSS, JS, and Font Awesome font files to your server.
   - Seed the default credentials:
     - **Admin Control:** Username: `admin` | Password: `admin123`
     - **Security Gate:** Username: `security` | Password: `security123`

4. Once the setup completes successfully, click **Go to Home Page** or navigate to `http://localhost/student_outpass/index.html` to start using the system.
