-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: May 08, 2026 at 08:28 AM
-- Server version: 8.2.0
-- PHP Version: 8.3.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ticktock_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `captcha_store`
--

CREATE TABLE `captcha_store` (
  `id` int UNSIGNED NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `captcha_store`
--

INSERT INTO `captcha_store` (`id`, `token`, `answer`, `expires_at`) VALUES
(28, '48f03a77a160be82501d92305c476c46', '6', '2026-05-08 12:24:31'),
(31, '2f2c43ce04359863a076c5956ecfb3ff', '13', '2026-05-08 12:28:05');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Homepage Development', 1, '2026-05-08 06:37:32'),
(2, 'Mobile App Redesign', 1, '2026-05-08 06:37:32'),
(3, 'API Integration Layer', 1, '2026-05-08 06:37:32'),
(4, 'Client Portal v2', 1, '2026-05-08 06:37:32');

-- --------------------------------------------------------

--
-- Table structure for table `timesheet_entries`
--

CREATE TABLE `timesheet_entries` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `project_id` int UNSIGNED NOT NULL,
  `work_type_id` int UNSIGNED NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `hours` decimal(4,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timesheet_entries`
--

INSERT INTO `timesheet_entries` (`id`, `user_id`, `date`, `project_id`, `work_type_id`, `description`, `hours`, `created_at`, `updated_at`) VALUES
(1, 1, '2024-01-01', 1, 1, 'Implemented hero section with responsive animations and CTA buttons for the homepage redesign project.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(2, 1, '2024-01-02', 1, 4, 'Designed and coded the navigation bar with mobile hamburger menu, dropdown submenus, and sticky scroll behavior.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(3, 1, '2024-01-03', 2, 2, 'Fixed critical layout bug on iOS Safari where flex containers were overflowing on product listing pages.', 6.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(4, 1, '2024-01-03', 2, 5, 'Ran regression tests on the mobile app checkout flow and documented 3 reproducible edge-case bugs.', 2.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(5, 1, '2024-01-04', 3, 1, 'Built the OAuth2 token refresh middleware and integrated it with the existing Axios interceptor setup.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(6, 1, '2024-01-05', 1, 3, 'Conducted peer code reviews for the homepage feature branch, left inline comments and approved 4 PRs.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(7, 1, '2024-01-08', 4, 1, 'Developed the client dashboard overview page with dynamic KPI widgets pulling from the reporting API.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(8, 1, '2024-01-09', 4, 4, 'Implemented responsive table component with sorting, filtering, and column visibility toggle for client reports.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(9, 1, '2024-01-10', 2, 1, 'Added push notification support to the mobile app using Firebase Cloud Messaging with background handlers.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(10, 1, '2024-01-11', 3, 2, 'Debugged and resolved a race condition in the WebSocket connection manager causing intermittent disconnects.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(11, 1, '2024-01-12', 1, 7, 'Deployed homepage v2.3 to staging, configured Nginx rewrites, and ran Lighthouse audits targeting 90+ scores.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(12, 1, '2024-01-15', 4, 4, 'Redesigned the client onboarding flow with multi-step wizard, progress indicators, and form validation.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(13, 1, '2024-01-16', 3, 1, 'Built GraphQL resolvers for the user profile endpoints and integrated with the PostgreSQL data layer.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(14, 1, '2024-01-17', 2, 5, 'Executed full regression testing suite on mobile app v3.1 build across 12 device/OS combinations.', 4.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(15, 1, '2024-01-17', 1, 8, 'Attended weekly client sync to review sprint progress, discuss upcoming features, and clarify requirements.', 4.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(16, 1, '2024-01-18', 4, 2, 'Fixed permission bug allowing standard users to access admin analytics routes in the client portal.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(17, 1, '2024-01-19', 3, 6, 'Wrote comprehensive API documentation for all v2 endpoints including request/response schemas and examples.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(18, 1, '2024-01-22', 1, 1, 'Built the blog listing and detail pages with dynamic content loading, pagination, and SEO meta tags.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(19, 1, '2024-01-23', 2, 4, 'Redesigned the onboarding screens for the mobile app following new brand guidelines and accessibility standards.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(20, 1, '2024-01-24', 4, 1, 'Developed the invoice generation module with PDF export capability and email delivery integration.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(21, 1, '2024-01-25', 3, 3, 'Reviewed the new microservice architecture PRs and provided feedback on service boundaries and API contracts.', 4.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(22, 1, '2024-01-26', 1, 7, 'Configured CI/CD pipeline with GitHub Actions for automated testing, building, and deployment to AWS S3.', 4.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(23, 1, '2024-01-29', 2, 2, 'Investigated and fixed memory leak in the React Native FlatList component causing app crashes on Android.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(24, 1, '2024-01-30', 4, 1, 'Implemented real-time notification system for client portal using Server-Sent Events and Redis pub/sub.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(25, 1, '2024-01-31', 1, 5, 'Performed cross-browser compatibility testing on homepage across Chrome, Firefox, Safari, and Edge browsers.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(26, 1, '2024-02-01', 3, 1, 'Built the file upload service with S3 integration, virus scanning, and progress tracking via WebSockets.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(27, 1, '2024-02-01', 2, 8, 'Joined the mobile app pre-launch client meeting to walk through UAT feedback and finalize release checklist.', 2.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(28, 2, '2024-01-01', 2, 1, 'Set up the React Native project scaffolding with navigation library, state management, and folder structure.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(29, 2, '2024-01-02', 2, 4, 'Designed custom UI components: buttons, cards, modals, and input fields matching the new design system.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(30, 2, '2024-01-03', 1, 2, 'Patched broken image lazy loading on the homepage that was causing CLS score degradation in Lighthouse.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(31, 2, '2024-01-04', 2, 1, 'Implemented user authentication flow in mobile app with biometric login and secure token storage.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(32, 2, '2024-01-05', 3, 5, 'Tested all API v2 integration points against staging environment and filed bug reports for 5 endpoints.', 8.00, '2026-05-08 06:37:32', '2026-05-08 06:37:32'),
(33, 1, '2025-12-29', 3, 2, 'this is new', 1.00, '2026-05-08 07:01:02', '2026-05-08 07:01:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','user') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_approved` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `name`, `role`, `is_active`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 'yasin@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'yasin ullah', 'admin', 1, 1, '2026-05-08 06:37:32', '2026-05-08 06:50:47'),
(2, 'sarah.connor@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Sarah Connor', 'user', 1, 1, '2026-05-08 06:37:32', '2026-05-08 06:50:47'),
(3, 'michael.chen@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Michael Chen', 'user', 1, 1, '2026-05-08 06:37:32', '2026-05-08 06:50:47'),
(4, 'priya.sharma@tentwenty.me', '$2y$10$mcyBs0epqdvSv8a4IBpnWOyUZvAZ9daWC9fd.WaC0K76h1.JBZTq.', 'Priya Sharma', 'user', 1, 1, '2026-05-08 06:37:32', '2026-05-08 06:50:47'),
(5, 'khan@khan.com', '$2y$12$qe10faFNr3y.ZcaG4olR/.ezpb3GSB4s1yL61rgXtAdKmT9FQAgA.', 'Khan', 'user', 1, 1, '2026-05-08 07:05:19', '2026-05-08 07:14:49');

-- --------------------------------------------------------

--
-- Table structure for table `work_types`
--

CREATE TABLE `work_types` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `work_types`
--

INSERT INTO `work_types` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Feature Development', 1, '2026-05-08 06:37:32'),
(2, 'Bug Fix', 1, '2026-05-08 06:37:32'),
(3, 'Code Review', 1, '2026-05-08 06:37:32'),
(4, 'UI/UX Implementation', 1, '2026-05-08 06:37:32'),
(5, 'Testing & QA', 1, '2026-05-08 06:37:32'),
(6, 'Documentation', 1, '2026-05-08 06:37:32'),
(7, 'Deployment & DevOps', 1, '2026-05-08 06:37:32'),
(8, 'Client Meeting', 1, '2026-05-08 06:37:32');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `captcha_store`
--
ALTER TABLE `captcha_store`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `timesheet_entries`
--
ALTER TABLE `timesheet_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `work_type_id` (`work_type_id`),
  ADD KEY `idx_user_date` (`user_id`,`date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `work_types`
--
ALTER TABLE `work_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `captcha_store`
--
ALTER TABLE `captcha_store`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `timesheet_entries`
--
ALTER TABLE `timesheet_entries`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `work_types`
--
ALTER TABLE `work_types`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `timesheet_entries`
--
ALTER TABLE `timesheet_entries`
  ADD CONSTRAINT `timesheet_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `timesheet_entries_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `timesheet_entries_ibfk_3` FOREIGN KEY (`work_type_id`) REFERENCES `work_types` (`id`) ON DELETE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
