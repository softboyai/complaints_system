-- ============================================================
-- Mount Kigali University - Complaints & Feedback System
-- Seed Data
-- Import schema.sql first, then this file.
-- All passwords are: Password@123
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Repair demo accounts when this seed file is imported again.
UPDATE `users`
SET `password_hash` = '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe',
    `failed_attempts` = 0,
    `locked_until` = NULL
WHERE `email` IN (
    'admin@mku.ac.rw',
    'academics@mku.ac.rw',
    'finance@mku.ac.rw',
    'ict@mku.ac.rw',
    'irakoze.ntwari@students.mku.ac.rw',
    'amina.mukagatare@students.mku.ac.rw',
    'eric.hakizimana@students.mku.ac.rw',
    'clarisse.ingabire@students.mku.ac.rw',
    'patrick.uwihanganye@students.mku.ac.rw'
);

-- ------------------------------------------------------------
-- Departments
-- ------------------------------------------------------------
INSERT INTO `departments` (`id`, `name`, `email`) VALUES
(1, 'Academics',          'academics@mku.ac.rw'),
(2, 'Finance',            'finance@mku.ac.rw'),
(3, 'Accommodation',      'accommodation@mku.ac.rw'),
(4, 'ICT and E-learning', 'ict@mku.ac.rw'),
(5, 'Library',            'library@mku.ac.rw'),
(6, 'Examinations',       'examinations@mku.ac.rw');

-- ------------------------------------------------------------
-- Users
-- Password hash for "Password@123" using bcrypt (cost 12)
-- ------------------------------------------------------------
INSERT INTO `users`
    (`id`, `reg_number`, `full_name`, `email`, `phone`, `faculty`, `role`, `department_id`, `password_hash`, `failed_attempts`, `locked_until`, `is_active`, `created_at`)
VALUES
-- Admin
(1, NULL, 'Jean-Paul Habimana', 'admin@mku.ac.rw', '+250788100001', NULL, 'admin', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-01 08:00:00'),

-- Staff
(2, NULL, 'Esperance Mukamana', 'academics@mku.ac.rw', '+250788100002', NULL, 'staff', 1,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-01 08:05:00'),

(3, NULL, 'Aimable Nshimiyimana', 'finance@mku.ac.rw', '+250788100003', NULL, 'staff', 2,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-01 08:10:00'),

(4, NULL, 'Claudine Uwimana', 'ict@mku.ac.rw', '+250788100004', NULL, 'staff', 4,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-01 08:15:00'),

-- Students
(5, 'BBICTR/2024/45081', 'Irakoze Ntwari', 'irakoze.ntwari@students.mku.ac.rw', '+250788200001', 'Business and ICT', 'student', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-15 09:00:00'),

(6, 'BALAW/2023/32014', 'Amina Mukagatare', 'amina.mukagatare@students.mku.ac.rw', '+250788200002', 'Law', 'student', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-15 09:10:00'),

(7, 'BENG/2024/51203', 'Eric Hakizimana', 'eric.hakizimana@students.mku.ac.rw', '+250788200003', 'Engineering', 'student', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-16 10:00:00'),

(8, 'BMAN/2023/29876', 'Clarisse Ingabire', 'clarisse.ingabire@students.mku.ac.rw', '+250788200004', 'Management', 'student', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-16 10:30:00'),

(9, 'BCOMP/2024/47532', 'Patrick Uwihanganye', 'patrick.uwihanganye@students.mku.ac.rw', '+250788200005', 'Computer Science', 'student', NULL,
 '$2y$12$gX3baOEftTQ.GjhQQRtDAetMJXFLphHUJwimHV0nQ4Dcpxlgl7dXe', 0, NULL, 1, '2025-09-17 08:45:00');

-- ------------------------------------------------------------
-- Complaints
-- ------------------------------------------------------------
INSERT INTO `complaints`
    (`id`, `tracking_id`, `student_id`, `is_anonymous`, `category`, `title`, `description`, `priority`, `department_id`, `assigned_to`, `status`, `rating`, `created_at`, `updated_at`)
VALUES
(1, 'MKU-2026-00001', 5, 0, 'Exams and Results', 'Missing marks in Database Systems exam',
 'I sat the Database Systems exam on 15 October 2026 (student ID BBICTR/2024/45081). When the results were published on 28 October, my name did not appear on the results sheet. I have my exam card and attendance record as proof.',
 'High', 6, NULL, 'Under Review', NULL, '2026-10-30 09:15:00', '2026-10-31 10:00:00'),

(2, 'MKU-2026-00002', 6, 0, 'Fees and Finance', 'Charged twice for semester two tuition',
 'My bank statement shows two deductions of RWF 450,000 on 5 October and 7 October 2026. I paid once and have the original receipt (No. FIN-2026-8821). Please refund the duplicate payment or confirm which payment is valid.',
 'High', 2, 3, 'In Progress', NULL, '2026-10-31 11:00:00', '2026-11-02 14:30:00'),

(3, 'MKU-2026-00003', 7, 0, 'ICT and E-learning', 'Cannot access the student portal for two weeks',
 'Since 20 October 2026 I have been unable to log in to the student portal. The error message says "Account suspended", but I have no outstanding dues and my registration is current. I need access to download my timetable and submit assignments.',
 'High', 4, 4, 'Resolved', 5, '2026-10-20 08:30:00', '2026-11-03 16:00:00'),

(4, 'MKU-2026-00004', 8, 0, 'Accommodation', 'Water supply cut in Block C for three days',
 'There has been no running water in Block C (rooms 301-320) since the morning of 3 November 2026. We were not informed in advance. Students are unable to shower or use the toilets properly. This is a health and safety concern.',
 'Medium', 3, NULL, 'Submitted', NULL, '2026-11-03 07:45:00', '2026-11-03 07:45:00'),

(5, 'MKU-2026-00005', 9, 0, 'Academic', 'Lecturer consistently starts class 30 minutes late',
 'The lecturer for Algorithm Design (COMP 312) has started every class at least 30 minutes after the scheduled time for the past five weeks. The course is running behind schedule and we are concerned about covering all the content before the final exam.',
 'Medium', 1, 2, 'In Progress', NULL, '2026-10-25 14:00:00', '2026-10-27 09:00:00'),

(6, 'MKU-2026-00006', 5, 1, 'Staff Conduct', 'Inappropriate behaviour by an invigilator during exam',
 'During the Business Statistics exam on 22 October 2026, the invigilator made several personal comments about students that were embarrassing and unprofessional. I am submitting this anonymously because I fear retaliation.',
 'High', 1, 2, 'Under Review', NULL, '2026-10-23 18:00:00', '2026-10-24 10:00:00'),

(7, 'MKU-2026-00007', 6, 0, 'Library', 'Reference books for Public Law not available',
 'The library does not have any copies of "Constitutional Law of Rwanda" by Prof. Habimana, which is the core textbook for Public Law III. Five students are competing for one copy and it is affecting our preparation for the end-of-semester paper.',
 'Low', 5, NULL, 'Submitted', NULL, '2026-11-01 13:30:00', '2026-11-01 13:30:00'),

(8, 'MKU-2026-00008', 7, 0, 'Exams and Results', 'Wrong grade recorded for Engineering Mathematics II',
 'The grade sheet shows a D for Engineering Mathematics II. I scored 74% in the final exam and my continuous assessment was 18/20. A D does not match these scores. I would like a formal remark.',
 'High', 6, NULL, 'Escalated', NULL, '2026-10-18 10:00:00', '2026-10-25 08:00:00'),

(9, 'MKU-2026-00009', 8, 0, 'Fees and Finance', 'Scholarship deduction not applied to my balance',
 'I was awarded the University Merit Scholarship for 2026/2027 (reference SCH-MKU-2026-0142). My fee balance still shows the full amount without any deduction. The scholarship office confirmed it was approved but the finance office has not applied it.',
 'Medium', 2, 3, 'Resolved', 4, '2026-10-10 09:00:00', '2026-10-28 11:00:00'),

(10, 'MKU-2026-00010', 9, 0, 'ICT and E-learning', 'Wi-Fi connection is very slow in the main lecture block',
 'The Wi-Fi in the main lecture block (MB-01 to MB-08) has been extremely slow since the start of the semester. Upload speeds average 0.3 Mbps. This makes it difficult to attend online sessions or submit assignments on time.',
 'Low', 4, 4, 'Closed', NULL, '2026-09-25 16:00:00', '2026-10-15 12:00:00');

-- ------------------------------------------------------------
-- Complaint Status History
-- ------------------------------------------------------------
INSERT INTO `complaint_status_history`
    (`complaint_id`, `old_status`, `new_status`, `changed_by`, `note`, `changed_at`)
VALUES
-- Complaint 1: Submitted then Under Review
(1, NULL,           'Submitted',    5, 'Complaint received.',             '2026-10-30 09:15:00'),
(1, 'Submitted',    'Under Review', 1, 'Forwarded to Examinations.',      '2026-10-31 10:00:00'),

-- Complaint 2: Full path
(2, NULL,           'Submitted',    6, 'Complaint received.',             '2026-10-31 11:00:00'),
(2, 'Submitted',    'Under Review', 1, 'Assigned to Finance.',            '2026-11-01 09:00:00'),
(2, 'Under Review', 'In Progress',  3, 'Contacted accounts team.',        '2026-11-02 14:30:00'),

-- Complaint 3: Resolved
(3, NULL,           'Submitted',    7, 'Complaint received.',             '2026-10-20 08:30:00'),
(3, 'Submitted',    'Under Review', 1, 'Assigned to ICT.',                '2026-10-20 11:00:00'),
(3, 'Under Review', 'In Progress',  4, 'Identified portal account issue.','2026-10-21 09:00:00'),
(3, 'In Progress',  'Resolved',     4, 'Account restored successfully.',  '2026-11-03 16:00:00'),

-- Complaint 4: Submitted only
(4, NULL,           'Submitted',    8, 'Complaint received.',             '2026-11-03 07:45:00'),

-- Complaint 5: In Progress
(5, NULL,           'Submitted',    9, 'Complaint received.',             '2026-10-25 14:00:00'),
(5, 'Submitted',    'Under Review', 1, 'Forwarded to Academics.',         '2026-10-26 09:00:00'),
(5, 'Under Review', 'In Progress',  2, 'Meeting scheduled with lecturer.','2026-10-27 09:00:00'),

-- Complaint 6: Anonymous, Under Review
(6, NULL,           'Submitted',    5, 'Complaint received.',             '2026-10-23 18:00:00'),
(6, 'Submitted',    'Under Review', 1, 'Under internal review.',          '2026-10-24 10:00:00'),

-- Complaint 7: Submitted only
(7, NULL,           'Submitted',    6, 'Complaint received.',             '2026-11-01 13:30:00'),

-- Complaint 8: Escalated automatically after 7 days
(8, NULL,           'Submitted',    7, 'Complaint received.',             '2026-10-18 10:00:00'),
(8, 'Submitted',    'Escalated',    1, 'Auto-escalated: no action after 7 days.', '2026-10-25 08:00:00'),

-- Complaint 9: Resolved
(9, NULL,           'Submitted',    8, 'Complaint received.',             '2026-10-10 09:00:00'),
(9, 'Submitted',    'Under Review', 1, 'Assigned to Finance.',            '2026-10-11 10:00:00'),
(9, 'Under Review', 'In Progress',  3, 'Checking with scholarships office.','2026-10-15 11:00:00'),
(9, 'In Progress',  'Resolved',     3, 'Scholarship deduction applied.',  '2026-10-28 11:00:00'),

-- Complaint 10: Closed
(10, NULL,           'Submitted',    9, 'Complaint received.',            '2026-09-25 16:00:00'),
(10, 'Submitted',    'Under Review', 1, 'Assigned to ICT.',               '2026-09-26 09:00:00'),
(10, 'Under Review', 'In Progress',  4, 'Investigating network equipment.','2026-09-28 10:00:00'),
(10, 'In Progress',  'Resolved',     4, 'Router firmware updated.',       '2026-10-10 14:00:00'),
(10, 'Resolved',     'Closed',       1, 'Student confirmed, closing.',    '2026-10-15 12:00:00');

-- ------------------------------------------------------------
-- Responses (conversation threads)
-- ------------------------------------------------------------
INSERT INTO `responses`
    (`complaint_id`, `author_id`, `message`, `is_internal`, `created_at`)
VALUES
-- Complaint 2 (fees double charge)
(2, 3, 'Thank you for reporting this. I have reviewed your account and can confirm there was a duplicate transaction. I am processing a refund now. Please allow 3 to 5 working days for the funds to reflect.', 0, '2026-11-02 14:30:00'),
(2, 6, 'Thank you. I will wait and check again on Monday.', 0, '2026-11-02 16:00:00'),
(2, 3, 'Internal note: Finance reconciliation reference REF-2026-9930. Refund approved by CFO.', 1, '2026-11-03 09:00:00'),

-- Complaint 3 (portal access, resolved)
(3, 4, 'I have identified the issue. Your account was incorrectly flagged by an automated system. I have restored your access. Please try logging in again.', 0, '2026-10-21 09:00:00'),
(3, 7, 'I can log in now. Thank you for fixing it quickly.', 0, '2026-10-21 11:30:00'),
(3, 4, 'Great, glad it is working. Let us know if you experience any further issues.', 0, '2026-11-03 16:00:00'),

-- Complaint 5 (late lecturer)
(5, 2, 'Thank you for raising this. I have spoken to the course coordinator and a meeting with the lecturer has been scheduled for next week. We will update you on the outcome.', 0, '2026-10-27 09:00:00'),

-- Complaint 9 (scholarship not applied, resolved)
(9, 3, 'I have confirmed your scholarship approval with the scholarships office. The deduction of RWF 400,000 has been applied to your account. Your current balance is RWF 50,000.', 0, '2026-10-28 11:00:00'),
(9, 8, 'I can see the deduction now. Thank you very much.', 0, '2026-10-28 14:00:00'),

-- Complaint 10 (Wi-Fi, closed)
(10, 4, 'Thank you for reporting this. We have updated the firmware on the main lecture block routers. Please let us know if the speed has improved.', 0, '2026-10-10 14:00:00'),
(10, 9, 'The connection is much better now. Thank you.', 0, '2026-10-11 09:00:00');

-- ------------------------------------------------------------
-- Feedback
-- ------------------------------------------------------------
INSERT INTO `feedback`
    (`student_id`, `type`, `target`, `rating`, `comment`, `created_at`)
VALUES
(5, 'Course',    'Database Systems (BICT 301)',          5, 'Very well structured course. The practical sessions were the most helpful part.', '2026-10-15 10:00:00'),
(5, 'Lecturer',  'Dr. Nzeyimana Jean-Claude',            4, 'Explains concepts clearly. More practice examples would help.', '2026-10-15 10:05:00'),
(6, 'Service',   'Student Registration Office',          3, 'The process is long and requires too many queues. An online option would help.', '2026-10-20 11:00:00'),
(7, 'Facility',  'Engineering Lab (EB-03)',               4, 'Equipment is modern but some machines are out of order for weeks at a time.', '2026-10-22 14:00:00'),
(8, 'Course',    'Business Communication (BMAN 201)',    5, 'Excellent course. The assignments were practical and relevant.', '2026-10-18 09:00:00'),
(9, 'Lecturer',  'Mr. Nkurunziza Alexis',                2, 'Lectures are hard to follow and slides are not shared after class.', '2026-10-30 16:00:00'),
(6, 'Facility',  'University Library',                   3, 'Good collection but not enough seating during exam period.', '2026-11-01 15:00:00');

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
INSERT INTO `notifications`
    (`user_id`, `message`, `link`, `is_read`, `created_at`)
VALUES
-- Admin notifications
(1, 'New complaint submitted: Missing marks in Database Systems exam', '/admin/complaints.php?id=1', 1, '2026-10-30 09:15:00'),
(1, 'New complaint submitted: Charged twice for semester two tuition', '/admin/complaints.php?id=2', 1, '2026-10-31 11:00:00'),
(1, 'New complaint submitted: Water supply cut in Block C', '/admin/complaints.php?id=4', 0, '2026-11-03 07:45:00'),
(1, 'Complaint MKU-2026-00008 was auto-escalated after 7 days.', '/admin/complaints.php?id=8', 0, '2026-10-25 08:00:00'),

-- Student notifications
(5, 'Your complaint MKU-2026-00001 is now Under Review.', '/student/complaint-details.php?id=1', 1, '2026-10-31 10:00:00'),
(6, 'Your complaint MKU-2026-00002 is now In Progress.', '/student/complaint-details.php?id=2', 1, '2026-11-02 14:30:00'),
(6, 'A staff member replied to your complaint MKU-2026-00002.', '/student/complaint-details.php?id=2', 1, '2026-11-02 14:31:00'),
(7, 'Your complaint MKU-2026-00003 has been Resolved.', '/student/complaint-details.php?id=3', 1, '2026-11-03 16:00:00'),
(7, 'A staff member replied to your complaint MKU-2026-00003.', '/student/complaint-details.php?id=3', 1, '2026-10-21 09:01:00'),
(8, 'Your complaint MKU-2026-00009 has been Resolved.', '/student/complaint-details.php?id=9', 1, '2026-10-28 11:01:00'),
(9, 'Your complaint MKU-2026-00010 has been Closed.', '/student/complaint-details.php?id=10', 0, '2026-10-15 12:01:00'),

-- Staff notifications
(3, 'A complaint has been assigned to your department: MKU-2026-00002.', '/staff/complaint-details.php?id=2', 1, '2026-11-01 09:00:00'),
(4, 'A complaint has been assigned to your department: MKU-2026-00003.', '/staff/complaint-details.php?id=3', 1, '2026-10-20 11:00:00'),
(4, 'A student replied to complaint MKU-2026-00003.', '/staff/complaint-details.php?id=3', 1, '2026-10-21 11:31:00');

SET FOREIGN_KEY_CHECKS = 1;
