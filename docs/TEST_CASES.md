# Test Cases: MKU Complaints and Feedback Management System

**Project:** Mount Kigali University Students' Complaints and Feedback Management System

Each test case includes: ID, description, preconditions, steps, expected result, and a blank column for actual result and pass/fail.

---

## 1. Unit Testing

These tests focus on individual functions in isolation, primarily the validation rules in `includes/validator.php`. A PHPUnit test class is provided in `tests/ValidatorTest.php`.

### TC-U-01: Name validation - valid input
- **Function:** `validate_name()`
- **Input:** "Irakoze Ntwari"
- **Expected:** `['valid' => true, 'error' => '']`
- **Result:** ___________

### TC-U-02: Name validation - too short
- **Input:** "A"
- **Expected:** `valid = false`, error contains "between 2 and 50"
- **Result:** ___________

### TC-U-03: Name validation - contains numbers
- **Input:** "John123 Doe"
- **Expected:** `valid = false`, error contains "letters, spaces, hyphens"
- **Result:** ___________

### TC-U-04: Registration number - valid
- **Input:** "BBICTR/2024/45081"
- **Expected:** `valid = true`
- **Result:** ___________

### TC-U-05: Registration number - too short
- **Input:** "ABC"
- **Expected:** `valid = false`, error contains "between 6 and 20"
- **Result:** ___________

### TC-U-06: Email validation - valid
- **Input:** "irakoze@students.mku.ac.rw"
- **Expected:** `valid = true`
- **Result:** ___________

### TC-U-07: Email validation - invalid format
- **Input:** "not-an-email"
- **Expected:** `valid = false`, error = "Enter a valid email address."
- **Result:** ___________

### TC-U-08: Email validation - disposable domain
- **Input:** "test@mailinator.com"
- **Expected:** `valid = false`, error contains "Disposable email"
- **Result:** ___________

### TC-U-09: Phone validation - valid Rwanda number
- **Input:** "+250788123456"
- **Expected:** `valid = true`
- **Result:** ___________

### TC-U-10: Phone validation - invalid
- **Input:** "abc-xyz"
- **Expected:** `valid = false`, error contains "valid phone number"
- **Result:** ___________

### TC-U-11: Password validation - strong
- **Input:** "Secure@2026"
- **Expected:** `valid = true`
- **Result:** ___________

### TC-U-12: Password validation - no uppercase
- **Input:** "secure@2026"
- **Expected:** `valid = false`, error contains "uppercase"
- **Result:** ___________

### TC-U-13: Password validation - no special character
- **Input:** "Secure2026"
- **Expected:** `valid = false`, error contains "special character"
- **Result:** ___________

### TC-U-14: Password validation - contains name
- **Input:** password = "Irakoze@123", name = "Irakoze Ntwari"
- **Expected:** `valid = false`, error contains "must not contain your name"
- **Result:** ___________

### TC-U-15: Complaint title - too short
- **Input:** "Hi"
- **Expected:** `valid = false`, error contains "between 5 and 100"
- **Result:** ___________

### TC-U-16: Complaint description - too short
- **Input:** "Short"
- **Expected:** `valid = false`, error contains "between 20 and 2000"
- **Result:** ___________

### TC-U-17: Attachment validation - valid PDF
- **Input:** A valid PDF file under 5 MB
- **Expected:** `valid = true`
- **Result:** ___________

### TC-U-18: Attachment validation - PHP file
- **Input:** A .php file disguised with PDF extension
- **Expected:** `valid = false`, error contains "PDF, JPG and PNG"
- **Result:** ___________

### TC-U-19: Attachment validation - oversized file
- **Input:** A PDF file larger than 5 MB
- **Expected:** `valid = false`, error contains "under 5 MB"
- **Result:** ___________

---

## 2. Integration Testing

These tests verify that PHP and MySQL work correctly together.

### TC-I-01: User registration inserts a row
1. Submit the registration form with valid data.
2. Open phpMyAdmin and check the `users` table.
- **Expected:** A new row exists with the correct full_name, email, reg_number, role = 'student', and a bcrypt password_hash.
- **Result:** ___________

### TC-I-02: Complaint submission creates tracking ID and history row
1. Log in as a student.
2. Submit a complaint with all required fields and select a department with an active staff account.
- **Expected:** A row in `complaints` with a unique tracking_id (format MKU-YYYY-#####), the selected `department_id`, and status = 'Submitted'. A corresponding row in `complaint_status_history` with new_status = 'Submitted', plus a notification for the selected department's active staff.
- **Result:** ___________

### TC-I-03: Department staff can see student-routed complaint
1. Log in as a student and submit a complaint to the Academics department.
2. Log in as staff assigned to Academics and open the Complaints page.
3. Open the complaint and change its status or add a response.
4. Log in as admin and locate the same complaint.
- **Expected:** Academics staff can view and update the complaint; staff from other departments cannot access it; admin can view it regardless of department.
- **Result:** ___________

### TC-I-04: Status change updates complaints and inserts history
1. Log in as staff.
2. Open an assigned complaint and change status to "In Progress" with a note.
- **Expected:** `complaints.status` updated. A new row in `complaint_status_history` with old_status and new_status. A row in `notifications` for the student.
- **Result:** ___________

### TC-I-05: Staff reply creates response and notification
1. Log in as staff.
2. Submit a public reply on a complaint.
- **Expected:** A row in `responses` with is_internal = 0. A row in `notifications` for the student.
- **Result:** ___________

### TC-I-06: Password reset token is hashed and expired after use
1. Request a password reset for a student account.
2. Use the link to set a new password.
3. Try to use the same link again.
- **Expected:** First use succeeds and redirects to login. Second use shows "invalid or already used" message. `password_resets.used = 1` in DB.
- **Result:** ___________

### TC-I-07: Feedback inserts correctly
1. Log in as a student.
2. Submit feedback with type = Course, rating = 4, comment = "Good course".
- **Expected:** A row in `feedback` with correct student_id, type, rating and comment.
- **Result:** ___________

---

## 3. System Testing

End-to-end tests of the complete system from submission to resolution.

### TC-S-01: Full complaint lifecycle
1. Register a new student account.
2. Log in as the student.
3. Submit a complaint: title = "Test complaint for system testing", category = Academic, priority = Medium, description of 30+ characters.
4. Note the tracking ID shown after submission.
5. Log out. Log in as admin.
6. Go to All Complaints, find the complaint by tracking ID.
7. Confirm its department is Academics.
8. Log out. Log in as staff (Academics).
9. Go to Complaints, find the complaint.
10. Update status to "In Progress" with note "Investigating".
11. Add a public reply: "We are looking into this."
12. Update status to "Resolved" with note "Issue resolved."
13. Log out. Log in as student.
14. Check the complaint details: status should be Resolved, timeline shows all changes, staff reply is visible.
15. Rate the resolution with 4 stars.
- **Expected:** All steps complete without errors. Status timeline accurate. Rating saved.
- **Result:** ___________

### TC-S-02: Anonymous complaint
1. Log in as student.
2. Submit a complaint with "Submit anonymously" checked.
3. Log in as staff.
4. Find the complaint in the department list.
- **Expected:** Student name shows as "Anonymous Student" in the staff view. Student can still view their own complaint.
- **Result:** ___________

### TC-S-03: Password reset flow (DEV_MODE)
1. Go to forgot-password.php.
2. Enter a registered student's email.
3. Note the reset link shown on screen (DEV_MODE).
4. Open the link.
5. Enter a new strong password.
6. Log in with the new password.
- **Expected:** Password reset succeeds. Login with old password fails. Login with new password succeeds.
- **Result:** ___________

### TC-S-04: File attachment download
1. Submit a complaint with a PDF attachment.
2. Log in as staff.
3. Open the complaint and click "Download" on the attachment.
- **Expected:** File downloads correctly. MIME type matches original. File is not directly accessible via the uploads/ URL.
- **Result:** ___________

### TC-S-05: Auto-escalation
1. Find or create a complaint with a created_at date more than 7 days ago and status = "Submitted".
   (In phpMyAdmin, manually set `created_at = DATE_SUB(NOW(), INTERVAL 8 DAY)` for a test complaint.)
2. Log in as admin and open the dashboard.
- **Expected:** Complaint status changed to "Escalated". A row in `complaint_status_history` with note "Auto-escalated: no action after 7 days." Admin sees the escalation alert on the dashboard.
- **Result:** ___________

### TC-S-06: PDF receipt and report download
1. Log in as student, open a resolved complaint, click "Download receipt".
2. Log in as admin, go to Reports, apply a date filter, click "Download PDF".
3. Click "Download Excel".
- **Expected:** PDF receipt opens with correct complaint details, timeline and responses. Report PDF includes MKU header and summary figures. CSV opens in Excel with header row and data rows.
- **Result:** ___________

---

## 4. User Acceptance Testing

A checklist for three types of users.

### UAT-Student Checklist

| # | Task | Pass / Fail |
|---|---|---|
| 1 | Register a new student account | |
| 2 | Log in with the new account | |
| 3 | Submit a complaint with a file attachment | |
| 4 | View the complaint and confirm the tracking ID | |
| 5 | Add a reply to the complaint | |
| 6 | Submit feedback with a 4-star rating | |
| 7 | Use "Forgot password?" and reset the password | |
| 8 | Download a PDF receipt for a complaint | |
| 9 | Edit profile details (phone, faculty) | |
| 10 | Change password from the profile page | |
| 11 | Toggle dark mode and confirm it persists after page reload | |
| 12 | Access the Help page and read the student guide | |

### UAT-Staff Checklist

| # | Task | Pass / Fail |
|---|---|---|
| 1 | Log in as staff | |
| 2 | View all complaints assigned to the department | |
| 3 | Filter complaints by status and date range | |
| 4 | Open a complaint and read the description | |
| 5 | Add an internal note (confirm it is not visible to student) | |
| 6 | Add a public reply (confirm student is notified) | |
| 7 | Update complaint status to "In Progress" | |
| 8 | Update complaint status to "Resolved" | |
| 9 | Download a department report as PDF | |
| 10 | Download a department report as Excel/CSV | |
| 11 | Log out | |

### UAT-Admin Checklist

| # | Task | Pass / Fail |
|---|---|---|
| 1 | Log in as admin | |
| 2 | View the dashboard with charts and stat cards | |
| 3 | View a newly submitted complaint routed by the student | |
| 4 | View all complaints across all departments | |
| 5 | Add a new staff user with department and role | |
| 6 | Disable and re-enable a user account | |
| 7 | Send a password reset link to a user | |
| 8 | Add a new department | |
| 9 | Edit a department name | |
| 10 | Download a full system report (PDF and Excel) | |
| 11 | Download a database backup from the Backup page | |
| 12 | View the complaint details including student identity on an anonymous complaint | |

---

## 5. Security Testing

### TC-SEC-01: Student cannot access admin pages
1. Log in as a student.
2. Navigate directly to `http://localhost/mku-complaints/admin/dashboard.php`.
- **Expected:** Redirected to 403 error page or login page. Student cannot view admin content.
- **Result:** ___________

### TC-SEC-02: Student cannot view another student's complaint
1. Log in as student A. Note the ID of student A's complaint.
2. Log out. Log in as student B.
3. Navigate to `http://localhost/mku-complaints/student/complaint-details.php?id=<student_A_id>`.
- **Expected:** 404 or redirect with "not found" message. Complaint data is not shown.
- **Result:** ___________

### TC-SEC-03: SQL injection attempt
1. On the login page, enter `' OR '1'='1` as the email and `anything` as the password.
- **Expected:** Generic error "Invalid email or password." No data is returned. Database query is not altered.
- **Result:** ___________

### TC-SEC-04: XSS (cross-site scripting) attempt
1. Submit a complaint with the following as the title: `<script>alert('XSS')</script>`
2. View the complaint in the student and staff views.
- **Expected:** The title is displayed as plain text. No JavaScript alert fires. Output is escaped with htmlspecialchars.
- **Result:** ___________

### TC-SEC-05: CSRF protection
1. Log in as a student.
2. Copy the submit-complaint form and submit it from a different origin without the CSRF token (use Postman or browser dev tools to remove the token field).
- **Expected:** Server returns "Invalid or missing security token." Request is rejected.
- **Result:** ___________

### TC-SEC-06: Account lockout
1. Go to the login page.
2. Enter a valid email and wrong password 5 times in a row.
- **Expected:** After the 5th attempt, the error message says the account is locked for 15 minutes. Correct credentials also fail during the lockout period. `users.locked_until` is set in the DB.
- **Result:** ___________

### TC-SEC-07: Expired password reset token
1. Request a password reset link.
2. Wait 16 minutes (or manually update `password_resets.expires_at` to a past time in phpMyAdmin).
3. Try to use the link.
- **Expected:** Message says "This reset link has expired." New password form is not shown.
- **Result:** ___________

### TC-SEC-08: PHP file upload rejected
1. Log in as a student and go to Submit a Complaint.
2. Rename a PHP file to `malicious.pdf` and try to attach it.
- **Expected:** Server-side MIME check detects it is not a PDF. Error message says "Only PDF, JPG and PNG files are allowed." File is not saved to the server.
- **Result:** ___________

### TC-SEC-09: Direct upload folder access
1. Try to access `http://localhost/mku-complaints/uploads/` in the browser.
- **Expected:** 403 Forbidden. The .htaccess file blocks all direct access.
- **Result:** ___________

### TC-SEC-10: Staff cannot see complaints from other departments
1. Log in as staff from the Finance department.
2. Note the ID of a complaint assigned to the ICT department.
3. Navigate to `http://localhost/mku-complaints/staff/complaint-details.php?id=<ICT_complaint_id>`.
- **Expected:** Redirect with "not assigned to your department" message.
- **Result:** ___________

---

## PHPUnit Tests

A basic PHPUnit test class is provided in `tests/ValidatorTest.php`. To run it:

1. Install PHPUnit via Composer: `composer require --dev phpunit/phpunit`
2. Run from the project folder: `vendor\bin\phpunit tests\ValidatorTest.php`
