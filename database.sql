CREATE DATABASE IF NOT EXISTS ticktock_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ticktock_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS timesheet_entries;
DROP TABLE IF EXISTS timesheet_submissions;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS work_types;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS captcha_store;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    avatar_url VARCHAR(255) DEFAULT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    standard_hours DECIMAL(4,2) DEFAULT 40.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE work_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE timesheet_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    year INT NOT NULL,
    week INT NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    rejection_reason TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    reviewed_by INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_user_year_week (user_id, year, week)
) ENGINE=InnoDB;

CREATE TABLE timesheet_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    work_type_id INT UNSIGNED NOT NULL,
    submission_id INT UNSIGNED DEFAULT NULL,
    description TEXT NOT NULL,
    hours DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (submission_id) REFERENCES timesheet_submissions(id) ON DELETE SET NULL,
    INDEX idx_user_date (user_id, date)
) ENGINE=InnoDB;

CREATE TABLE captcha_store (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    answer VARCHAR(20) NOT NULL,
    expires_at DATETIME NOT NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempt_time)
) ENGINE=InnoDB;

-- Default Data
INSERT INTO users (email, password_hash, name, role, is_approved) VALUES
('yasin@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Yasin Ullah', 'admin', 1),
('sarah.connor@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Sarah Connor', 'user', 1),
('michael.chen@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Michael Chen', 'user', 1),
('priya.sharma@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Priya Sharma', 'user', 1);

INSERT INTO projects (name) VALUES
('Homepage Development'),
('Mobile App Redesign'),
('API Integration Layer'),
('Client Portal v2');

INSERT INTO work_types (name) VALUES
('Feature Development'),
('Bug Fix'),
('Code Review'),
('UI/UX Implementation'),
('Testing & QA'),
('Documentation'),
('Deployment & DevOps'),
('Client Meeting');

-- Sample Entries (Optional)
INSERT INTO timesheet_entries (user_id, date, project_id, work_type_id, description, hours) VALUES
(1, '2024-01-01', 1, 1, 'Implemented hero section with responsive animations and CTA buttons.', 8.00),
(1, '2024-01-02', 1, 4, 'Designed and coded the navigation bar with mobile hamburger menu.', 8.00),
(2, '2024-01-01', 2, 1, 'Set up the React Native project scaffolding with navigation library.', 8.00);
