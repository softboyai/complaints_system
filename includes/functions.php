<?php
// General helper functions used across the application.

require_once dirname(__DIR__) . '/config/config.php';

// ------------------------------------------------------------
// Tracking ID Generation
// ------------------------------------------------------------

// Generates a unique complaint tracking ID inside the caller's transaction.
function generate_tracking_id(PDO $pdo): string {
    $year = date('Y');
    $prefix = "MKU-{$year}-";

    $stmt = $pdo->prepare(
        "SELECT tracking_id FROM complaints
         WHERE tracking_id LIKE ?
         ORDER BY tracking_id DESC LIMIT 1
         FOR UPDATE"
    );
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $seq = (int) substr($last, strlen($prefix)) + 1;
    } else {
        $seq = 1;
    }

    return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------
// Notifications
// ------------------------------------------------------------

function create_notification(PDO $pdo, int $user_id, string $message, string $link = ''): void {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, message, link) VALUES (?, ?, ?)'
        );
        $stmt->execute([$user_id, $message, $link ?: null]);
    } catch (Throwable $e) {
        log_error('create_notification failed: ' . $e->getMessage());
    }
}

// ------------------------------------------------------------
// Escalation Check
// ------------------------------------------------------------

// Flags complaints unresolved for more than 7 days as Escalated.
// Returns the number of complaints escalated.
function check_escalations(PDO $pdo): int {
    try {
        // Find complaints that should be escalated.
        $find = $pdo->prepare(
            "SELECT id, student_id, tracking_id
             FROM complaints
             WHERE status NOT IN ('Resolved','Closed','Rejected','Escalated')
             AND DATEDIFF(NOW(), created_at) > 7"
        );
        $find->execute();
        $targets = $find->fetchAll();

        if (empty($targets)) {
            return 0;
        }

        // Get or find the admin user for history records.
        $admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
        $admin_id = $admin ? (int) $admin['id'] : 1;

        $update = $pdo->prepare(
            "UPDATE complaints SET status = 'Escalated', updated_at = NOW() WHERE id = ?"
        );
        $history = $pdo->prepare(
            "INSERT INTO complaint_status_history
             (complaint_id, old_status, new_status, changed_by, note)
             VALUES (?, ?, 'Escalated', ?, 'Auto-escalated: no action after 7 days.')"
        );

        foreach ($targets as $c) {
            $update->execute([$c['id']]);
            $history->execute([$c['id'], 'Under Review', $admin_id]);

            // Notify the admin.
            create_notification(
                $pdo,
                $admin_id,
                "Complaint {$c['tracking_id']} was auto-escalated after 7 days.",
                '/admin/complaints.php?id=' . $c['id']
            );
        }

        return count($targets);
    } catch (Throwable $e) {
        log_error('check_escalations failed: ' . $e->getMessage());
        return 0;
    }
}

// ------------------------------------------------------------
// Output and Sanitisation
// ------------------------------------------------------------

// Escape output for safe HTML rendering.
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Trim and escape a value.
function sanitize(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ------------------------------------------------------------
// Error Logging
// ------------------------------------------------------------

function log_error(string $message): void {
    $entry = sprintf('[%s] %s' . PHP_EOL, date('Y-m-d H:i:s'), $message);
    file_put_contents(LOG_PATH . '/error.log', $entry, FILE_APPEND | LOCK_EX);
}

// ------------------------------------------------------------
// Date / Time Helpers
// ------------------------------------------------------------

// Returns a human-friendly relative time string.
function time_ago(string $datetime): string {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute'. ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Format bytes to a readable string.
function format_bytes(int $bytes): string {
    if ($bytes >= 1_048_576) return round($bytes / 1_048_576, 1) . ' MB';
    if ($bytes >= 1_024)     return round($bytes / 1_024, 1) . ' KB';
    return $bytes . ' B';
}

// ------------------------------------------------------------
// Status Helpers
// ------------------------------------------------------------

// Returns the CSS class suffix for a complaint status badge.
function status_badge_class(string $status): string {
    return match ($status) {
        'Submitted'    => 'blue',
        'Under Review' => 'yellow',
        'In Progress'  => 'orange',
        'Resolved'     => 'green',
        'Closed'       => 'grey',
        'Rejected'     => 'red',
        'Escalated'    => 'purple',
        default        => 'grey',
    };
}

// Returns the CSS class for a priority badge.
function priority_badge_class(string $priority): string {
    return match ($priority) {
        'High'   => 'red',
        'Medium' => 'orange',
        'Low'    => 'green',
        default  => 'grey',
    };
}

// List of valid complaint categories.
function complaint_categories(): array {
    return [
        'Academic',
        'Fees and Finance',
        'Exams and Results',
        'Accommodation',
        'ICT and E-learning',
        'Library',
        'Staff Conduct',
        'Other',
    ];
}

// List of valid statuses for staff to transition to.
function allowed_status_transitions(string $current_status, string $role): array {
    $all = ['Submitted', 'Under Review', 'In Progress', 'Resolved', 'Closed', 'Rejected', 'Escalated'];
    if ($role === 'admin') {
        return $all;
    }
    // Staff cannot set Closed directly.
    return array_diff($all, ['Closed']);
}

// ------------------------------------------------------------
// Pagination
// ------------------------------------------------------------

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = (int) ceil($total / $per_page);
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;

    return [
        'total'        => $total,
        'per_page'     => $per_page,
        'current_page' => $current_page,
        'total_pages'  => $total_pages,
        'offset'       => $offset,
    ];
}

// Build a query string preserving existing params except 'page'.
function pagination_url(int $page, array $existing_params = []): string {
    $params = array_merge($existing_params, ['page' => $page]);
    return '?' . http_build_query($params);
}

// ------------------------------------------------------------
// MIME type to file icon name
// ------------------------------------------------------------
function file_icon(string $mime): string {
    return match (true) {
        str_contains($mime, 'pdf')  => 'pdf',
        str_contains($mime, 'jpeg'),
        str_contains($mime, 'png')  => 'image',
        default                     => 'file',
    };
}
