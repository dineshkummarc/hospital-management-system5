# Hospital Management System (HMS)

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

A complete, production-ready Hospital Management System built with PHP, MySQL, Bootstrap 5, and vanilla JavaScript. Designed for local deployment with XAMPP/Apache. Features three distinct roles (**Patient**, **Doctor**, and **Administrator**), each with a dedicated dashboard, responsive UI, real-time appointment booking, medical history tracking, billing & payment simulation, and administrative controls.

---

## 1. Project Overview

HMS replaces manual, paper-based hospital processes with a centralized,
browser-based client-server application. Patients can register, search doctors,
book/reschedule/cancel appointments, view their medical history, and pay bills.
Doctors manage their appointments, view patient history, and record diagnoses
and prescriptions. Administrators manage doctors, staff, wards/beds, billing,
reports, and audit logs.

The system was built incrementally, in this order:

1. User Registration & Login
2. Appointment Management
3. Medical Records & Prescription Management
4. Billing & Online Payment (test-payment architecture)
5. Administration — Staff, Ward/Bed Management & Reports

---

## 2. Features

**Patient**
- Registration with validation (duplicate email check, auto-generated Patient ID)
- Login/logout with hashed passwords and sessions
- Dashboard with real stats (appointments, outstanding bills, recent records)
- Search/filter doctors by name and specialization
- Book appointments with a real-time, AJAX-driven available-slots picker
- Reschedule / cancel appointments
- View chronological medical history (diagnosis + prescriptions), read-only
- View and pay bills via a clearly-labelled Test Payment flow, with receipts

**Doctor**
- Dashboard with real stats (today's/upcoming appointments, patient count)
- View and manage appointments (confirm / complete / cancel)
- Completing an appointment auto-generates a bill
- View assigned patients and their full medical history
- Add diagnosis, clinical notes, and multi-line prescriptions (never overwrites
  past records — every entry is a new, timestamped row)

**Administrator**
- Dashboard with real, live stats (patients, doctors, revenue, bed occupancy, etc.)
- Manage doctors: add / edit / activate / deactivate, set specialization & fee
- Manage staff: add / edit / activate / deactivate, search & filter
- Manage patients: view details, activate/deactivate accounts
- Manage wards: create, edit, activate/deactivate, capacity tracking
- Manage beds: add, assign (blocked if already occupied), release
- Billing overview across all patients, with filters and totals
- Reports: appointment, revenue, and bed-occupancy reports with date-range
  filters and Chart.js visualizations
- Audit log viewer (every sensitive action is logged with actor, action, IP)

**Cross-cutting**
- Role-based access control: direct URL access to another role's pages is
  blocked and redirected
- Passwords hashed with `password_hash()` / verified with `password_verify()`
- All queries use PDO prepared statements (SQL-injection safe)
- CSRF tokens on every state-changing form
- Output escaped everywhere (`esc()` helper) to prevent XSS
- Friendly error messages; raw PHP/MySQL errors are never shown to users
  (they're sent to the PHP error log instead)
- Real-time double-booking prevention: enforced in the browser (AJAX
  availability check), in the PHP booking handler, and at the database level
  with a `UNIQUE(doctor_id, appointment_date, appointment_time)` constraint
- In-app notifications for booking, rescheduling, cancellation, bill
  generation, and payment
- Status-based deactivation everywhere (doctors, staff, patients, wards) —
  historical medical/billing records are never destroyed

---

## 3. Technology Stack

- **Frontend:** HTML5, CSS3, vanilla JavaScript, Bootstrap 5, Chart.js
- **Backend:** PHP 8+ (PDO, procedural style with small helper functions)
- **Database:** MySQL 8 (InnoDB, full referential integrity)
- **Server:** Apache via XAMPP

---

## 4. Folder Structure

```
hms/
├── index.php               Redirects to the right dashboard or login
├── login.php
├── register.php             Patient self-registration
├── logout.php
├── config/database.php      PDO connection (edit DB credentials here)
├── includes/                auth.php, functions.php, header/navbar/footer.php
├── assets/css/style.css     Custom hospital-admin styling on top of Bootstrap
├── assets/js/main.js        Confirm dialogs, loading states, slot picker
├── patient/                 dashboard, appointments, book_appointment,
│                             medical_records, bills, profile
├── doctor/                  dashboard, appointments, patients,
│                             medical_records, profile
├── admin/                   dashboard, doctors, staff, patients, wards,
│                             beds, billing, reports, audit_logs
├── api/appointments/available_slots.php   real-time slot availability (AJAX)
├── api/notifications/mark_read.php        marks notifications as read
└── database/
    ├── schema.sql            Table definitions only
    ├── seed_data.sql          Demo data only
    └── hms.sql                schema.sql + seed_data.sql combined (import this one)
```

---

## 5. Database Setup

The database `hms_db` and all 13 tables (users, doctors, patients,
appointments, medical_records, prescriptions, bills, payments, staff, wards,
beds, notifications, audit_logs) are created by `database/hms.sql`, which
also seeds demo data (1 admin, 5 doctors, 5 patients, sample appointments,
one medical record + prescription, one bill, 4 staff, 3 wards, 7 beds).

All primary/foreign keys, `UNIQUE` constraints (including the doctor/date/time
constraint that prevents double-booking at the DB level), and indexes used by
the app are defined in `schema.sql`.

---

## 6. XAMPP Setup / How to Run

### Quick Start (Windows)
If XAMPP is installed at `C:\xampp`:
1. Double-click **`START_HMS.bat`** in the project folder.
2. It automatically starts MySQL and Apache (if not already running) and opens **`http://localhost/hms/`** in your browser.

### Manual Setup
1. Install [XAMPP](https://www.apachefriends.org/) (PHP 8+, MySQL/MariaDB, Apache).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Copy or clone the `hms/` folder into your XAMPP `htdocs` directory:
   - Windows: `C:\xampp\htdocs\hms\`
   - Linux/macOS: `/opt/lampp/htdocs/hms/`
4. Import the database:
   - Via phpMyAdmin (`http://localhost/phpmyadmin`): Click **Import**, select `database/hms.sql`, and run it.
   - Or via terminal: `mysql -u root < database/hms.sql`
5. Verify `config/database.php` matches your MySQL credentials (defaults to host `127.0.0.1`, port `3306`, user `root`, no password).
6. Navigate to **`http://localhost/hms/`** in your browser.

No build step, no Node.js dependencies, no Composer required — runs as native PHP under Apache.

---

## 7. Demo Credentials

| Role    | Email                        | Password     |
|---------|-------------------------------|---------------|
| Admin   | admin@hms.com                 | Admin@123     |
| Doctor  | ananya.sharma@hms.com         | Doctor@123    |
| Doctor  | rohan.verma@hms.com           | Doctor@123    |
| Doctor  | kavya.iyer@hms.com            | Doctor@123    |
| Doctor  | sameer.khan@hms.com           | Doctor@123    |
| Doctor  | priya.nair@hms.com            | Doctor@123    |
| Patient | aarav.mehta@example.com       | Patient@123   |
| Patient | diya.kapoor@example.com       | Patient@123   |
| Patient | vivaan.joshi@example.com      | Patient@123   |
| Patient | ishita.rao@example.com        | Patient@123   |
| Patient | kabir.malhotra@example.com    | Patient@123   |

You can also register a brand-new patient account from the login page.

---

## 8. Payment Test Instructions

This is a **development/demo** system — no real payment gateway is wired up.
Payments are handled by a clearly-labelled **Test Payment** flow:

1. Log in as a patient with an unpaid bill (e.g. `ishita.rao@example.com`,
   who has a seeded Rs. 950 bill), go to **Bills**, and click **Pay Now**.
2. The payment modal explicitly states this is a demo/test mechanism — no
   card details are validated or transmitted anywhere.
3. Click **Pay Now (Test)**. This generates a unique transaction ID, records
   a `payments` row, and flips the bill's status to `Paid`.
4. A receipt (transaction ID, amount, date/time) is then viewable from the
   bill list.
5. To see the failure path, check "Simulate a failed payment" before
   submitting — the bill is marked `Failed` and no success notification is sent.

The `payments` table and the way `bills.status` is updated on success are
designed so a real gateway (Razorpay, Stripe, etc.) could later be dropped in
by replacing only the contents of the "process payment" block in
`patient/bills.php` — no schema changes required.

---

## 9. Known Limitations

- No AI diagnosis, insurance claims, or external health-registry integration
  (explicitly out of scope per the SRS).
- The Test Payment flow is a simulation; no real payment gateway is integrated.
- Email/SMS delivery is not implemented — notifications are in-app only
  (a `notifications` table + navbar bell), not sent externally.
- The standard appointment slots are fixed 30-minute increments (09:00–17:00);
  per-doctor custom schedules are stored as free text (`doctors.availability`)
  for display only and don't yet drive the slot generator.
- No password-reset-by-email flow; an admin can deactivate/reactivate accounts,
  but there's no self-service "forgot password" yet (a notification type for
  "password reset requested" exists in the schema for future use).
- File uploads (e.g. lab reports, scanned documents) are not implemented.
- Pagination is not implemented on very large tables (report/audit views are
  capped with `LIMIT` instead); fine for demo data volumes, worth adding
  before production use with thousands of rows.

## 10. Future Enhancements

- Real payment gateway integration (Razorpay/Stripe) behind the existing
  `payments` table.
- Email/SMS notifications in addition to in-app ones.
- Doctor-configurable schedules driving the slot generator (rather than a
  fixed 9–5 grid).
- Self-service password reset via emailed token.
- File/document attachments on medical records.
- REST API layer under `api/` for a future mobile app (the `api/` folder is
  already scaffolded for this).

---

## 11. Implementation Notes

Where the brief left an implementation detail unspecified, the simplest
practical choice was made and is documented here:

- **Time slots** are a fixed grid (09:00–17:00, 30-minute increments) checked
  against existing appointments in real time, rather than per-doctor custom
  calendars.
- **Bill generation** happens automatically the moment a doctor marks an
  appointment "Completed" (consultation fee + Rs. 0 treatment charge by
  default; treatment charges can be extended later without schema changes).
- **Deactivation** is status-based everywhere (`active`/`inactive`,
  `Available`/`Occupied`) — nothing is ever hard-deleted, so historical
  medical and billing records are always preserved.
- **Access control for medical records** is enforced by only ever querying
  by the logged-in user's own `patient_id`/`doctor_id` server-side — URL
  parameters for patient/appointment IDs are always re-validated against
  the current user's actual relationships before any data is returned.
