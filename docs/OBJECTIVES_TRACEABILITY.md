# Objectives Traceability Matrix

**Project:** Mount Kigali University Students' Complaints and Feedback Management System

---

## General Objective

Improve the efficiency of complaint handling, feedback management and communication between students and university management at MKU.

| Objective | Pages / Entry Points | PHP Files | Database Tables |
|---|---|---|---|
| Centralised complaint handling with status tracking | `student/submit-complaint.php`, `student/complaint-details.php`, `staff/complaint-details.php`, `admin/complaints.php` | `includes/functions.php` (generate_tracking_id, check_escalations), `includes/mailer.php` | `complaints`, `complaint_status_history`, `responses` |
| Feedback collection and ratings | `student/feedback.php`, `student/complaint-details.php` (rating) | `student/feedback.php` | `feedback`, `complaints.rating` |
| Communication between students and staff | `student/complaint-details.php`, `staff/complaint-details.php`, `admin/complaints.php` | `includes/mailer.php`, `api/notifications.php` | `responses`, `notifications` |
| Management reporting | `admin/reports.php`, `staff/reports.php`, `reports/pdf.php`, `reports/excel.php` | `reports/pdf.php`, `reports/excel.php` | `complaints`, `users`, `departments` |

---

## Specific Objective 1

**A web-based application that records, tracks and manages students' complaints and feedback.**

| Feature | Pages | PHP Files | DB Tables |
|---|---|---|---|
| Complaint submission with tracking ID | `student/submit-complaint.php` | `includes/functions.php`, `includes/validator.php` | `complaints`, `complaint_status_history`, `attachments` |
| Status flow (Submitted, Under Review, In Progress, Resolved, Closed, Rejected, Escalated) | `staff/complaint-details.php`, `admin/complaints.php` | `includes/functions.php` | `complaints`, `complaint_status_history` |
| Auto-escalation after 7 days | `admin/dashboard.php`, `staff/dashboard.php`, `cron/escalate.php` | `includes/functions.php` (check_escalations) | `complaints`, `complaint_status_history`, `notifications` |
| Complaint search, filter and pagination | `student/my-complaints.php`, `staff/complaints.php`, `admin/complaints.php` | All list pages | `complaints` |
| Feedback submission with star rating | `student/feedback.php` | `student/feedback.php` | `feedback` |
| Threaded conversation on each complaint | `student/complaint-details.php`, `staff/complaint-details.php`, `admin/complaints.php` | All detail pages | `responses` |
| File attachments on complaints | `student/submit-complaint.php`, `download.php` | `includes/validator.php` | `attachments` |
| PDF complaint receipt | `student/receipt.php` | `student/receipt.php` | `complaints`, `complaint_status_history`, `responses`, `attachments` |

---

## Specific Objective 2

**A user-friendly interface that lets students, administrators and university staff use the system easily, on desktop browsers and mobile-responsive layouts.**

| Feature | Pages | CSS / JS Files | Notes |
|---|---|---|---|
| Mobile-first responsive layout | All pages | `assets/css/style.css` (responsive section) | Sidebar collapses on mobile, tables become cards below 640px |
| Accessible forms with labels and ARIA | All form pages | `assets/css/style.css`, `assets/js/validation.js` | Labels on all inputs, aria-required, aria-describedby, role attributes |
| Keyboard navigation and visible focus | All pages | `assets/css/style.css` (:focus-visible styles) | Full keyboard nav on modals, dropdowns, sidebar |
| Inline client-side validation | `login.php`, `register.php`, `student/submit-complaint.php` etc. | `assets/js/validation.js` | Mirrors server-side rules, validates on blur |
| Password strength meter | `register.php`, `reset-password.php`, `*/profile.php` | `assets/js/validation.js` | Visual bar with Weak/Fair/Good/Strong |
| Dark mode with localStorage persistence | All authenticated pages | `assets/js/app.js`, `assets/css/style.css` | Toggle in topbar, remembers preference |
| Toasts for success and error feedback | All pages | `assets/js/app.js` | Top-right, auto-dismiss at 4s |
| Stat cards and Chart.js dashboards | `*/dashboard.php` | `assets/js/charts.js`, `api/charts.php` | Status doughnut, category bar, monthly trend line |
| Intuitive dashboards per role | `student/dashboard.php`, `staff/dashboard.php`, `admin/dashboard.php` | All dashboard pages | Tailored nav and content per role |
| Landing page for new users | `index.php` | `assets/css/style.css` | Hero, How it works, Features, FAQ, Footer |

---

## Specific Objective 3

**A secure database that stores and manages student information, complaints, feedback, status and responses from responsible staff.**

| Feature | Files | DB Tables / Config |
|---|---|---|
| Secure password hashing (bcrypt) | `register.php`, `admin/users.php`, `reset-password.php`, `*/profile.php` | `users.password_hash` |
| PDO prepared statements on every query | All PHP files that query the DB | All tables |
| CSRF tokens on all POST forms | All form pages via `includes/auth.php` (csrf_field, csrf_verify) | Session-stored token |
| Role-based access control | All protected pages via `includes/auth.php` (require_role) | `users.role` |
| Session security (httponly, samesite, timeout, regenerate) | `includes/auth.php` | Session storage |
| Input validation (JS + PHP) | All form pages, `includes/validator.php` | N/A |
| MIME-type validated file uploads | `student/submit-complaint.php`, `includes/validator.php` | `attachments` |
| Uploads blocked from direct access | `uploads/.htaccess`, `download.php` | `attachments.stored_name` |
| Output escaped with htmlspecialchars | All PHP view files via `e()` helper in `includes/functions.php` | N/A |
| Error logging (no raw errors shown) | `config/config.php` (set_error_handler), `includes/functions.php` (log_error) | `logs/error.log` |
| Anonymous complaint privacy | `staff/complaint-details.php`, `staff/complaints.php`, `admin/complaints.php` | `complaints.is_anonymous` |
| Password reset with time-limited token hash | `forgot-password.php`, `reset-password.php` | `password_resets` |
| Account lockout after failed logins | `login.php` | `users.failed_attempts`, `users.locked_until` |
| Database schema with foreign keys and indexes | `database/schema.sql` | All tables |
| Sample data for testing | `database/seed.sql` | All tables |
| Automated and manual database backup | `admin/backup.php`, `scripts/backup.php` | Full DB export |
| Notification system (in-app + email) | `api/notifications.php`, `includes/mailer.php` | `notifications` |
