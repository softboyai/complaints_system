<?php
// Notification helpers.
// Email sending is disabled. All calls log to logs/mail.log for reference.
// Password reset does NOT use email - it is handled in forgot-password.php.

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// Logs a notification entry to mail.log.
// No emails are actually sent.
function send_mail(string $to, string $subject, string $body): bool {
    $entry = sprintf(
        "============================================================\n[%s] TO: %s\nSUBJECT: %s\n\n%s\n\n",
        date('Y-m-d H:i:s'), $to, $subject, strip_tags($body)
    );
    file_put_contents(LOG_PATH . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
    return true;
}

// Called when a student submits a complaint.
function mail_complaint_received(string $to, string $name, string $tracking_id, string $title): bool {
    return send_mail($to, "Complaint {$tracking_id} received",
        "Dear {$name}, your complaint '{$title}' has been received. Tracking ID: {$tracking_id}.");
}

// Called when admin receives a new complaint notification.
function mail_admin_new_complaint(string $admin_email, string $tracking_id, string $title, string $student_name): bool {
    return send_mail($admin_email, "New complaint: {$tracking_id}",
        "New complaint submitted by {$student_name}: '{$title}' ({$tracking_id}).");
}

// Called when a complaint status changes.
function mail_status_changed(string $to, string $name, string $tracking_id, string $new_status): bool {
    return send_mail($to, "Update on complaint {$tracking_id}",
        "Dear {$name}, your complaint {$tracking_id} is now: {$new_status}.");
}

// Called when staff replies to a student.
function mail_staff_replied(string $to, string $name, string $tracking_id): bool {
    return send_mail($to, "New reply on complaint {$tracking_id}",
        "Dear {$name}, a staff member replied to your complaint {$tracking_id}.");
}

// Called when a student replies to assigned staff.
function mail_student_replied(string $to, string $staff_name, string $tracking_id): bool {
    return send_mail($to, "Student replied to complaint {$tracking_id}",
        "Dear {$staff_name}, a student replied to complaint {$tracking_id}.");
}
