-- ============================================================
-- Mount Kigali University - Complaints & Feedback System
-- Database Schema
-- Charset: utf8mb4_unicode_ci | Engine: InnoDB
-- Import this file first, then seed.sql
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `responses`;
DROP TABLE IF EXISTS `attachments`;
DROP TABLE IF EXISTS `complaint_status_history`;
DROP TABLE IF EXISTS `complaints`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `departments`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- departments
-- ------------------------------------------------------------
CREATE TABLE `departments` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(150) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- users
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reg_number`      VARCHAR(20)  DEFAULT NULL,
    `full_name`       VARCHAR(100) NOT NULL,
    `email`           VARCHAR(150) NOT NULL,
    `phone`           VARCHAR(20)  DEFAULT NULL,
    `faculty`         VARCHAR(100) DEFAULT NULL,
    `role`            ENUM('student','staff','admin') NOT NULL DEFAULT 'student',
    `department_id`   INT UNSIGNED DEFAULT NULL,
    `password_hash`   VARCHAR(255) NOT NULL,
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until`    DATETIME DEFAULT NULL,
    `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email`      (`email`),
    UNIQUE KEY `uq_reg_number` (`reg_number`),
    KEY `idx_role`             (`role`),
    KEY `idx_department_id`    (`department_id`),
    CONSTRAINT `fk_users_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- complaints
-- ------------------------------------------------------------
CREATE TABLE `complaints` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tracking_id`   VARCHAR(20)  NOT NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `is_anonymous`  TINYINT(1)   NOT NULL DEFAULT 0,
    `category`      ENUM(
                        'Academic',
                        'Fees and Finance',
                        'Exams and Results',
                        'Accommodation',
                        'ICT and E-learning',
                        'Library',
                        'Staff Conduct',
                        'Other'
                    ) NOT NULL,
    `title`         VARCHAR(200) NOT NULL,
    `description`   TEXT NOT NULL,
    `priority`      ENUM('Low','Medium','High') NOT NULL DEFAULT 'Low',
    `department_id` INT UNSIGNED DEFAULT NULL,
    `assigned_to`   INT UNSIGNED DEFAULT NULL,
    `status`        ENUM(
                        'Submitted',
                        'Under Review',
                        'In Progress',
                        'Resolved',
                        'Closed',
                        'Rejected',
                        'Escalated'
                    ) NOT NULL DEFAULT 'Submitted',
    `rating`        TINYINT UNSIGNED DEFAULT NULL COMMENT '1-5 student satisfaction after resolution',
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tracking_id` (`tracking_id`),
    KEY `idx_student_id`        (`student_id`),
    KEY `idx_status`            (`status`),
    KEY `idx_department_id`     (`department_id`),
    KEY `idx_assigned_to`       (`assigned_to`),
    KEY `idx_created_at`        (`created_at`),
    CONSTRAINT `fk_complaints_student`
        FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_department`
        FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_complaints_assigned`
        FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- complaint_status_history
-- ------------------------------------------------------------
CREATE TABLE `complaint_status_history` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED NOT NULL,
    `old_status`   VARCHAR(20)  DEFAULT NULL,
    `new_status`   VARCHAR(20)  NOT NULL,
    `changed_by`   INT UNSIGNED NOT NULL,
    `note`         TEXT DEFAULT NULL,
    `changed_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_complaint_id` (`complaint_id`),
    KEY `idx_changed_at`   (`changed_at`),
    CONSTRAINT `fk_history_complaint`
        FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_history_user`
        FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- attachments
-- ------------------------------------------------------------
CREATE TABLE `attachments` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `complaint_id`  INT UNSIGNED NOT NULL,
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name`   VARCHAR(255) NOT NULL COMMENT 'Random filename on disk',
    `file_type`     VARCHAR(50)  NOT NULL COMMENT 'Verified MIME type',
    `file_size`     INT UNSIGNED NOT NULL COMMENT 'Size in bytes',
    PRIMARY KEY (`id`),
    KEY `idx_complaint_id` (`complaint_id`),
    CONSTRAINT `fk_attachments_complaint`
        FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- responses (conversation thread on a complaint)
-- ------------------------------------------------------------
CREATE TABLE `responses` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `complaint_id` INT UNSIGNED NOT NULL,
    `author_id`    INT UNSIGNED NOT NULL,
    `message`      TEXT NOT NULL,
    `is_internal`  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Internal notes hidden from student',
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_complaint_id` (`complaint_id`),
    KEY `idx_author_id`    (`author_id`),
    CONSTRAINT `fk_responses_complaint`
        FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_responses_author`
        FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- feedback
-- ------------------------------------------------------------
CREATE TABLE `feedback` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `student_id` INT UNSIGNED NOT NULL,
    `type`       ENUM('Course','Lecturer','Service','Facility') NOT NULL,
    `target`     VARCHAR(200) NOT NULL COMMENT 'Course name, lecturer name, service, etc.',
    `rating`     TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    `comment`    TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_student_id` (`student_id`),
    KEY `idx_type`        (`type`),
    KEY `idx_created_at`  (`created_at`),
    CONSTRAINT `fk_feedback_student`
        FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- notifications
-- ------------------------------------------------------------
CREATE TABLE `notifications` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `message`    VARCHAR(500) NOT NULL,
    `link`       VARCHAR(500) DEFAULT NULL,
    `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_is_read`    (`is_read`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_notifications_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- password_resets
-- ------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of the raw token',
    `expires_at` DATETIME NOT NULL,
    `used`       TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_token_hash` (`token_hash`),
    CONSTRAINT `fk_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
