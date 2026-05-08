CREATE DATABASE IF NOT EXISTS ticktock_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ticktock_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS timesheet_entries;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS work_types;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS captcha_store;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_approved TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE timesheet_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    work_type_id INT UNSIGNED NOT NULL,
    description TEXT NOT NULL,
    hours DECIMAL(4,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE RESTRICT,
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

INSERT INTO users (email, password_hash, name, role, is_approved) VALUES
('john.doe@tentwenty.me', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', 'admin', 1),
('sarah.connor@tentwenty.me', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Connor', 'user', 1),
('michael.chen@tentwenty.me', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Michael Chen', 'user', 1),
('priya.sharma@tentwenty.me', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Priya Sharma', 'user', 1);

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

INSERT INTO timesheet_entries (user_id, date, project_id, work_type_id, description, hours) VALUES
(1, '2024-01-01', 1, 1, 'Implemented hero section with responsive animations and CTA buttons for the homepage redesign project.', 8.00),
(1, '2024-01-02', 1, 4, 'Designed and coded the navigation bar with mobile hamburger menu, dropdown submenus, and sticky scroll behavior.', 8.00),
(1, '2024-01-03', 2, 2, 'Fixed critical layout bug on iOS Safari where flex containers were overflowing on product listing pages.', 6.00),
(1, '2024-01-03', 2, 5, 'Ran regression tests on the mobile app checkout flow and documented 3 reproducible edge-case bugs.', 2.00),
(1, '2024-01-04', 3, 1, 'Built the OAuth2 token refresh middleware and integrated it with the existing Axios interceptor setup.', 8.00),
(1, '2024-01-05', 1, 3, 'Conducted peer code reviews for the homepage feature branch, left inline comments and approved 4 PRs.', 8.00),
(1, '2024-01-08', 4, 1, 'Developed the client dashboard overview page with dynamic KPI widgets pulling from the reporting API.', 8.00),
(1, '2024-01-09', 4, 4, 'Implemented responsive table component with sorting, filtering, and column visibility toggle for client reports.', 8.00),
(1, '2024-01-10', 2, 1, 'Added push notification support to the mobile app using Firebase Cloud Messaging with background handlers.', 8.00),
(1, '2024-01-11', 3, 2, 'Debugged and resolved a race condition in the WebSocket connection manager causing intermittent disconnects.', 8.00),
(1, '2024-01-12', 1, 7, 'Deployed homepage v2.3 to staging, configured Nginx rewrites, and ran Lighthouse audits targeting 90+ scores.', 8.00),
(1, '2024-01-15', 4, 4, 'Redesigned the client onboarding flow with multi-step wizard, progress indicators, and form validation.', 8.00),
(1, '2024-01-16', 3, 1, 'Built GraphQL resolvers for the user profile endpoints and integrated with the PostgreSQL data layer.', 8.00),
(1, '2024-01-17', 2, 5, 'Executed full regression testing suite on mobile app v3.1 build across 12 device/OS combinations.', 4.00),
(1, '2024-01-17', 1, 8, 'Attended weekly client sync to review sprint progress, discuss upcoming features, and clarify requirements.', 4.00),
(1, '2024-01-18', 4, 2, 'Fixed permission bug allowing standard users to access admin analytics routes in the client portal.', 8.00),
(1, '2024-01-19', 3, 6, 'Wrote comprehensive API documentation for all v2 endpoints including request/response schemas and examples.', 8.00),
(1, '2024-01-22', 1, 1, 'Built the blog listing and detail pages with dynamic content loading, pagination, and SEO meta tags.', 8.00),
(1, '2024-01-23', 2, 4, 'Redesigned the onboarding screens for the mobile app following new brand guidelines and accessibility standards.', 8.00),
(1, '2024-01-24', 4, 1, 'Developed the invoice generation module with PDF export capability and email delivery integration.', 8.00),
(1, '2024-01-25', 3, 3, 'Reviewed the new microservice architecture PRs and provided feedback on service boundaries and API contracts.', 4.00),
(1, '2024-01-26', 1, 7, 'Configured CI/CD pipeline with GitHub Actions for automated testing, building, and deployment to AWS S3.', 4.00),
(1, '2024-01-29', 2, 2, 'Investigated and fixed memory leak in the React Native FlatList component causing app crashes on Android.', 8.00),
(1, '2024-01-30', 4, 1, 'Implemented real-time notification system for client portal using Server-Sent Events and Redis pub/sub.', 8.00),
(1, '2024-01-31', 1, 5, 'Performed cross-browser compatibility testing on homepage across Chrome, Firefox, Safari, and Edge browsers.', 8.00),
(1, '2024-02-01', 3, 1, 'Built the file upload service with S3 integration, virus scanning, and progress tracking via WebSockets.', 8.00),
(1, '2024-02-01', 2, 8, 'Joined the mobile app pre-launch client meeting to walk through UAT feedback and finalize release checklist.', 2.00),
(2, '2024-01-01', 2, 1, 'Set up the React Native project scaffolding with navigation library, state management, and folder structure.', 8.00),
(2, '2024-01-02', 2, 4, 'Designed custom UI components: buttons, cards, modals, and input fields matching the new design system.', 8.00),
(2, '2024-01-03', 1, 2, 'Patched broken image lazy loading on the homepage that was causing CLS score degradation in Lighthouse.', 8.00),
(2, '2024-01-04', 2, 1, 'Implemented user authentication flow in mobile app with biometric login and secure token storage.', 8.00),
(2, '2024-01-05', 3, 5, 'Tested all API v2 integration points against staging environment and filed bug reports for 5 endpoints.', 8.00);
