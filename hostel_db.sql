-- ============================================================
--  UniNest Hostel Management System — Database Schema
--  Compatible with: MySQL 5.7+ / MariaDB 10.x (XAMPP)
--  Import via: phpMyAdmin > Import  OR  mysql -u root < hostel_db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `hostel_db`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `hostel_db`;

-- ============================================================
-- 1. ADMINS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(60)  NOT NULL UNIQUE,
    `email`         VARCHAR(120) NOT NULL UNIQUE,
    `password`      VARCHAR(255) NOT NULL,       -- bcrypt hash
    `full_name`     VARCHAR(120) NOT NULL,
    `role`          ENUM('super_admin','admin') DEFAULT 'admin',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 2. STUDENTS (users)
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`        VARCHAR(30)  NOT NULL UNIQUE,   -- e.g. 2024-CS-045
    `full_name`         VARCHAR(120) NOT NULL,
    `email`             VARCHAR(120) NOT NULL UNIQUE,
    `password`          VARCHAR(255) NOT NULL,
    `phone`             VARCHAR(20)  DEFAULT NULL,
    `department`        VARCHAR(100) NOT NULL,
    `year_of_study`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `gender`            ENUM('male','female','other') NOT NULL,
    `date_of_birth`     DATE         DEFAULT NULL,
    `home_address`      TEXT         DEFAULT NULL,
    `emergency_contact` VARCHAR(120) DEFAULT NULL,
    `emergency_phone`   VARCHAR(20)  DEFAULT NULL,
    `profile_photo`     VARCHAR(255) DEFAULT NULL,
    `status`            ENUM('active','inactive','suspended') DEFAULT 'active',
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 3. ROOMS
-- ============================================================
CREATE TABLE IF NOT EXISTS `rooms` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_number`   VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. A-204
    `block`         ENUM('A','B','C') NOT NULL,
    `block_gender`  ENUM('male','female','mixed') NOT NULL DEFAULT 'male',
    `floor`         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `room_type`     ENUM('single','double','triple','quad') NOT NULL,
    `capacity`      TINYINT UNSIGNED NOT NULL,
    `occupied`      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `fee_per_sem`   DECIMAL(10,2) NOT NULL,
    `has_ac`        TINYINT(1) NOT NULL DEFAULT 0,
    `has_attached_bath` TINYINT(1) NOT NULL DEFAULT 0,
    `has_wifi`      TINYINT(1) NOT NULL DEFAULT 1,
    `has_study_desk` TINYINT(1) NOT NULL DEFAULT 1,
    `description`   TEXT DEFAULT NULL,
    `status`        ENUM('available','maintenance','closed') DEFAULT 'available',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_block`       (`block`),
    INDEX `idx_room_type`   (`room_type`),
    INDEX `idx_status`      (`status`)
) ENGINE=InnoDB;

-- ============================================================
-- 4. APPLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `applications` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `app_code`          VARCHAR(20)  NOT NULL UNIQUE,   -- HMS-2025-0045
    `student_id`        INT UNSIGNED NOT NULL,
    `preferred_block`   ENUM('A','B','C') NOT NULL,
    `preferred_type`    ENUM('single','double','triple','quad') NOT NULL,
    `preferred_floor`   TINYINT UNSIGNED DEFAULT NULL,
    `special_req`       VARCHAR(255) DEFAULT NULL,
    `reason`            TEXT DEFAULT NULL,
    `status`            ENUM('pending','approved','rejected','allocated','withdrawn') DEFAULT 'pending',
    `admin_note`        TEXT DEFAULT NULL,
    `reviewed_by`       INT UNSIGNED DEFAULT NULL,
    `reviewed_at`       TIMESTAMP NULL DEFAULT NULL,
    `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`)  REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`reviewed_by`) REFERENCES `admins`(`id`)   ON DELETE SET NULL,
    INDEX `idx_status`   (`status`),
    INDEX `idx_student`  (`student_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 5. ALLOCATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `allocations` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT UNSIGNED NOT NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `room_id`       INT UNSIGNED NOT NULL,
    `semester`      VARCHAR(20)  NOT NULL,       -- e.g. Spring-2025
    `start_date`    DATE         NOT NULL,
    `end_date`      DATE         DEFAULT NULL,
    `allocated_by`  INT UNSIGNED NOT NULL,
    `status`        ENUM('active','vacated','transferred') DEFAULT 'active',
    `notes`         TEXT DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`)      ON DELETE CASCADE,
    FOREIGN KEY (`room_id`)        REFERENCES `rooms`(`id`)         ON DELETE CASCADE,
    FOREIGN KEY (`allocated_by`)   REFERENCES `admins`(`id`),
    INDEX `idx_student`  (`student_id`),
    INDEX `idx_room`     (`room_id`),
    INDEX `idx_semester` (`semester`)
) ENGINE=InnoDB;

-- ============================================================
-- 6. PAYMENTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `payments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `allocation_id` INT UNSIGNED NOT NULL,
    `student_id`    INT UNSIGNED NOT NULL,
    `semester`      VARCHAR(20)  NOT NULL,
    `amount`        DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cash','bank_transfer','mobile_banking','card') DEFAULT 'cash',
    `transaction_ref` VARCHAR(80) DEFAULT NULL,
    `status`        ENUM('pending','paid','overdue','waived') DEFAULT 'pending',
    `due_date`      DATE NOT NULL,
    `paid_at`       TIMESTAMP NULL DEFAULT NULL,
    `notes`         TEXT DEFAULT NULL,
    `created_by`    INT UNSIGNED DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`allocation_id`) REFERENCES `allocations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`)    REFERENCES `students`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`created_by`)    REFERENCES `admins`(`id`)      ON DELETE SET NULL,
    INDEX `idx_status`   (`status`),
    INDEX `idx_student`  (`student_id`),
    INDEX `idx_due_date` (`due_date`)
) ENGINE=InnoDB;

-- ============================================================
-- 7. COMPLAINTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `complaints` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id`    INT UNSIGNED NOT NULL,
    `room_id`       INT UNSIGNED DEFAULT NULL,
    `category`      ENUM('maintenance','plumbing','electricity','internet','cleanliness','security','other') NOT NULL,
    `subject`       VARCHAR(200) NOT NULL,
    `description`   TEXT NOT NULL,
    `priority`      ENUM('low','medium','high','urgent') DEFAULT 'medium',
    `status`        ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    `admin_response` TEXT DEFAULT NULL,
    `handled_by`    INT UNSIGNED DEFAULT NULL,
    `resolved_at`   TIMESTAMP NULL DEFAULT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`)    REFERENCES `rooms`(`id`)    ON DELETE SET NULL,
    FOREIGN KEY (`handled_by`) REFERENCES `admins`(`id`)   ON DELETE SET NULL,
    INDEX `idx_status`   (`status`),
    INDEX `idx_student`  (`student_id`)
) ENGINE=InnoDB;

-- ============================================================
-- 8. NOTICES
-- ============================================================
CREATE TABLE IF NOT EXISTS `notices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`         VARCHAR(200) NOT NULL,
    `body`          TEXT NOT NULL,
    `type`          ENUM('info','warning','danger','success') DEFAULT 'info',
    `target`        ENUM('all','students','block_a','block_b','block_c') DEFAULT 'all',
    `published_by`  INT UNSIGNED NOT NULL,
    `is_active`     TINYINT(1) DEFAULT 1,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`published_by`) REFERENCES `admins`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin accounts  (passwords are bcrypt of "Admin@123")
INSERT INTO `admins` (`username`,`email`,`password`,`full_name`,`role`) VALUES
('admin',   'admin@university.edu',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin'),
('warden',  'warden@university.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Hostel Warden',        'admin');

-- Students  (passwords are bcrypt of "Student@123")
INSERT INTO `students`
  (`student_id`,`full_name`,`email`,`password`,`phone`,`department`,`year_of_study`,`gender`,`status`)
VALUES
('2024-CS-045','Aryan Hossain',  'aryan@uni.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000001','Computer Science',1,'male',  'active'),
('2024-EE-012','Nadia Sultana',  'nadia@uni.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000002','Electrical Eng.',  1,'female','active'),
('2023-ME-034','Rahim Khan',     'rahim@uni.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000003','Mechanical Eng.',  2,'male',  'active'),
('2024-CS-071','Sara Khan',      'sara@uni.edu',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000004','Computer Science',1,'female','active'),
('2022-BA-009','Mihad Ahmed',    'mihad@uni.edu',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000005','Business Admin.',  3,'male',  'active'),
('2023-PH-018','Fatima Akter',   'fatima@uni.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','01711000006','Physics',          2,'female','active');

-- Rooms (Block A=Male, B=Female, C=Mixed)
INSERT INTO `rooms`
  (`room_number`,`block`,`block_gender`,`floor`,`room_type`,`capacity`,`occupied`,`fee_per_sem`,`has_ac`,`has_attached_bath`,`has_wifi`,`has_study_desk`,`status`)
VALUES
-- Block A (Male)
('A-101','A','male',1,'single',1,1,8000.00,0,0,1,1,'available'),
('A-102','A','male',1,'double',2,2,6000.00,0,0,1,1,'available'),
('A-103','A','male',1,'triple',3,1,5000.00,0,0,1,1,'available'),
('A-104','A','male',1,'quad',  4,0,4000.00,0,0,1,1,'available'),
('A-204','A','male',2,'double',2,2,6000.00,1,0,1,1,'available'),
('A-205','A','male',2,'triple',3,3,5000.00,0,0,1,1,'available'),
('A-312','A','male',3,'triple',3,1,5000.00,0,0,1,1,'available'),
('A-314','A','male',3,'single',1,0,8000.00,1,1,1,1,'available'),
-- Block B (Female)
('B-101','B','female',1,'single',1,1,8000.00,0,1,1,1,'available'),
('B-110','B','female',1,'double',2,1,6000.00,0,0,1,1,'available'),
('B-205','B','female',2,'double',2,1,6000.00,1,0,1,1,'available'),
('B-210','B','female',2,'triple',3,0,5000.00,0,0,1,1,'available'),
('B-318','B','female',3,'triple',3,0,5000.00,0,0,1,1,'available'),
-- Block C (Mixed)
('C-102','C','mixed', 1,'double',2,0,6000.00,1,0,1,1,'available'),
('C-315','C','mixed', 3,'triple',3,2,5000.00,0,0,1,1,'available'),
('C-401','C','mixed', 4,'quad',  4,4,4000.00,0,0,1,1,'available'),
('C-402','C','mixed', 4,'quad',  4,2,4000.00,0,0,1,1,'available');

-- Applications
INSERT INTO `applications`
  (`app_code`,`student_id`,`preferred_block`,`preferred_type`,`status`,`reviewed_by`,`reviewed_at`)
VALUES
('HMS-2025-0044', 2,'B','single',  'approved', 1, NOW()),
('HMS-2025-0045', 1,'A','double',  'pending',  NULL, NULL),
('HMS-2025-0043', 3,'A','triple',  'pending',  NULL, NULL),
('HMS-2025-0042', 4,'B','double',  'rejected', 1, NOW()),
('HMS-2025-0041', 5,'C','triple',  'allocated',1, NOW()),
('HMS-2025-0040', 6,'B','double',  'allocated',1, NOW());

-- Allocations (for allocated applications)
INSERT INTO `allocations`
  (`application_id`,`student_id`,`room_id`,`semester`,`start_date`,`allocated_by`,`status`)
VALUES
(5, 5, 15, 'Spring-2025','2025-01-10', 1,'active'),  -- Mihad -> C-315
(6, 6, 11, 'Spring-2025','2025-01-10', 1,'active');  -- Fatima -> B-205

-- Payments
INSERT INTO `payments`
  (`allocation_id`,`student_id`,`semester`,`amount`,`payment_method`,`transaction_ref`,`status`,`due_date`,`paid_at`,`created_by`)
VALUES
(1, 5,'Spring-2025',5000.00,'bank_transfer','TXN-20250112-001','paid',  '2025-02-01','2025-01-12 10:00:00',1),
(2, 6,'Spring-2025',6000.00,'mobile_banking','TXN-20250113-002','paid',  '2025-02-01','2025-01-13 11:30:00',1),
(1, 5,'Fall-2025',  5000.00,'cash',NULL,                        'pending','2025-08-01',NULL,1),
(2, 6,'Fall-2025',  6000.00,'cash',NULL,                        'overdue','2025-02-01',NULL,1);

-- Complaints
INSERT INTO `complaints`
  (`student_id`,`room_id`,`category`,`subject`,`description`,`priority`,`status`,`admin_response`,`handled_by`,`resolved_at`)
VALUES
(6,11,'maintenance',  'Broken ceiling fan','The ceiling fan in room B-205 stopped working.','medium','open',     NULL,NULL,NULL),
(3,NULL,'plumbing',   'Leaking water tap', 'Tap in bathroom dripping constantly.',           'high',  'open',     NULL,NULL,NULL),
(5,15,'internet',     'WiFi not working',  'WiFi is completely down on 3rd floor Block C.',  'urgent','in_progress','Technician dispatched.',1,NULL),
(6,11,'electricity',  'Light bulb out',    'Corridor light bulb needs replacing.',            'low',   'resolved', 'Replaced on Jan 10.',1,'2025-01-10 14:00:00'),
(5,15,'security',     'Door lock broken',  'Room door lock is jammed.',                      'high',  'resolved', 'Lock replaced.',1,'2025-01-08 09:00:00');

-- Notices
INSERT INTO `notices` (`title`,`body`,`type`,`target`,`published_by`) VALUES
('Semester 2 Room Allocations Open','Applications for Spring 2025 semester are now open. Apply before January 20.','info','all',1),
('Fee Payment Deadline','All students must pay semester fees before February 1, 2025 to avoid penalties.','warning','all',1),
('Water Outage — Block A','Scheduled maintenance on Jan 18, 2025 from 6:00 AM to 8:00 AM. No water supply.','danger','block_a',1),
('New WiFi Upgrade','Block C WiFi upgrade completed. Faster speeds now available.','success','block_c',1);

-- ============================================================
-- VIEWS (helpful for reporting)
-- ============================================================

CREATE OR REPLACE VIEW `v_room_summary` AS
SELECT
    r.id, r.room_number, r.block, r.block_gender, r.floor,
    r.room_type, r.capacity, r.occupied,
    (r.capacity - r.occupied) AS available_seats,
    r.fee_per_sem, r.has_ac, r.has_attached_bath, r.has_wifi, r.status,
    ROUND((r.occupied / r.capacity) * 100, 1) AS occupancy_pct
FROM rooms r;

CREATE OR REPLACE VIEW `v_application_detail` AS
SELECT
    a.id, a.app_code, a.status AS app_status, a.created_at,
    s.student_id, s.full_name, s.email, s.department, s.year_of_study, s.gender,
    a.preferred_block, a.preferred_type, a.reason, a.admin_note,
    adm.full_name AS reviewed_by_name, a.reviewed_at
FROM applications a
JOIN students s ON s.id = a.student_id
LEFT JOIN admins adm ON adm.id = a.reviewed_by;

CREATE OR REPLACE VIEW `v_allocation_detail` AS
SELECT
    al.id, al.semester, al.start_date, al.status AS alloc_status,
    s.student_id, s.full_name, s.email, s.department,
    r.room_number, r.block, r.floor, r.room_type, r.fee_per_sem,
    p.status AS payment_status, p.due_date, p.paid_at
FROM allocations al
JOIN students s  ON s.id  = al.student_id
JOIN rooms r     ON r.id  = al.room_id
LEFT JOIN payments p ON p.allocation_id = al.id AND p.semester = al.semester;

CREATE OR REPLACE VIEW `v_dashboard_stats` AS
SELECT
    (SELECT COUNT(*) FROM rooms WHERE status='available')      AS total_rooms,
    (SELECT SUM(capacity) FROM rooms)                          AS total_seats,
    (SELECT SUM(capacity - occupied) FROM rooms)               AS available_seats,
    (SELECT COUNT(*) FROM students WHERE status='active')      AS total_students,
    (SELECT COUNT(*) FROM allocations WHERE status='active')   AS housed_students,
    (SELECT COUNT(*) FROM applications WHERE status='pending') AS pending_apps,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='paid') AS fees_collected,
    (SELECT COUNT(*) FROM complaints WHERE status IN ('open','in_progress')) AS open_complaints;
