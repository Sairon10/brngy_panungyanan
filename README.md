## Information System for Barangay Panungyanan (General Trias, Cavite)

Tech: PHP 8+, MySQL (XAMPP), Bootstrap 5, Leaflet

### Setup (XAMPP)
- Start Apache and MySQL in XAMPP.
- Clone/copy this folder to `C:\xampp\htdocs\barangay_system`.
- Create database and tables:
  - Open `phpMyAdmin` → Import → select `schema.sql` from this folder.
- Configure DB (if needed) in `config.php`.

### Accounts
- Admin: email `admin@panungyanan.local`
- Password: `Admin@1234`

### Features
- Register (strong password), Login, Logout, Forgot/Reset Password
- Document Requests (resident + admin management)
- Report Incident (with map pin)
- Resident Profile
- Resident ID printable card
- Admin dashboard for requests, incidents, residents
- Simple rule-based Chatbot

### URLs
- `http://localhost/barangay_system/`
- Admin: `http://localhost/barangay_system/admin/`


