# System Requirements & Dependencies

This document details the configuration requirements and system specifications to run the **Student Hostel Outpass Management System** on another machine.

---

## 1. Core Server Environment
* **Web Server:** Apache 2.4+ (Standard in XAMPP / WAMP / Laragon)
* **PHP Engine:** PHP 7.4 to 8.2+
* **Database System:** MySQL 5.7+ / MariaDB 10.4+

---

## 2. PHP Extension Settings
Ensure the following extensions are enabled in your PHP configuration (`php.ini`):

1. **`pdo_mysql`** (Required for database interactions):
   - Make sure `extension=pdo_mysql` is uncommented in your `php.ini`.
2. **`fileinfo`** (Required for secure student photo mime-type checks):
   - Make sure `extension=fileinfo` is uncommented.
3. **`gd`** (Optional, for PNG QR generation):
   - If `extension=gd` is enabled, the system will output standard `.png` QR codes.
   - If disabled, the system will dynamically fall back to vector-based `.svg` QR codes (requires no server-side extension).

---

## 3. Web Directory Permissions
If deploying on Linux/macOS systems, ensure the server has write permissions to create folders and save files in:
* `uploads/student_photos/` (for student photos)
* `assets/qr/` (for generated QR codes)
* `css/`, `js/`, `webfonts/`, `admin/` (so that `config/setup.php` can download and write offline libraries)

---

## 4. Browser Security & Webcam Scanner Rules
* **Localhost Testing:** You can run the webcam scanner on `http://localhost/` without any security warnings.
* **Network Testing (Staging/Production):** Modern web browsers restrict access to camera hardware (Webcam API) to secure origins. When hosting the project on another IP address (e.g., `http://192.168.1.50/` or a custom domain), **you must access it over HTTPS** (`https://...`), otherwise the browser will block the scanner.
