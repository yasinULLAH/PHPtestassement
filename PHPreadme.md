# ticktock — Enterprise Timesheet Suite (PHP)

> A centralized, SaaS-style timesheet management dashboard built for the **TenTwenty Frontend Technical Assessment 2026**.

---

## 🔗 Links

| | |
|---|---|
| **GitHub Repository** | [https://github.com/yasinullah/PHPtestassement](https://github.com/yasinullah/PHPtestassement) |
| **Live PWA Demo** | [https://yasinullah.github.io/PHPtestassement/](https://yasinullah.github.io/PHPtestassement/) |

---

## 🔑 Test Credentials

For your convenience, I have provided pre-seeded accounts in the database. Use the credentials below to test the different user roles:

### 🛡️ Admin Account
- **Email:** `yasin@tentwenty.me`
- **Password:** `admin@123`
*(Allows full access to User Management, Approvals, Project CRUD, and Reporting)*

### 👤 Standard User Account
- **Email:** `sarah.connor@tentwenty.me`
- **Password:** `admin@123`

---

## ✨ Key Enterprise Features

- 🛡️ **Centralized Admin Dashboard** — Manage users, projects, and work types from one place with full CRUD capabilities.
- ⚙️ **Global System Settings** — Administrators can configure system-wide defaults including company name and standard work hours.
- ✅ **Approval Workflow** — Submissions and registrations require admin approval to maintain data quality.
- 📝 **Advanced Submission Review** — Admins can edit individual timesheet entries during the review process to ensure accuracy before final approval.
- 🔐 **Enhanced Security** — Math CAPTCHA, CSRF, IP-based Rate Limiting, password complexity enforcement, and secure session management with idle and absolute timeouts.
- 📋 **Submission Lifecycle** — Users submit weekly timesheets which lock them for review, preventing post-submission modifications.
- 📊 **Global Reports** — Admins can view aggregate hours by project and user across configurable date ranges with interactive tables.
- 👤 **Profile Management** — Users can update their personal information, manage avatars (stored securely), and securely change passwords.
- 📧 **Password Recovery** — Token-based reset system with simulated email delivery (logged to `email.txt`).
- 🔄 **Data Integrity & Recovery** — Support for soft-deleting users, restoring deactivated accounts, or permanently purging historical data.
- 📥 **Data Export** — Export timesheets to CSV for external reporting or payroll processing.
- 📱 **Native Performance** — Zero framework overhead; built with raw PHP and CSS for maximum durability and speed.

---

## 🛠️ Setup Instructions

### Prerequisites
- PHP **8.0+** with GD extension enabled
- MySQL / MariaDB

### Database Setup
1. Create a new database named `ticktock_db`.
2. Import the provided SQL file. You have two options:
   - **`ticktock_db-db_2026-05-08.sql`**: (**Recommended**) A full export from phpMyAdmin containing the latest schema and all pre-seeded test data.
   - **`database.sql`**: The clean architectural schema.
3. Open `index.php` and update the database configuration constants at the top:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ticktock_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'your_password');
   ```

---

## 💡 Technical Notes

1. **Native Architecture** — This application avoids `node_modules` entirely. It relies on core W3C standards (HTML5/CSS3/ES6+) and server-side PHP to ensure maximum performance and security.
2. **Single-File API** — To demonstrate API integration while maintaining a portable structure, `index.php` functions as both a template renderer and a JSON REST API provider.

---

## 👤 Author

**Yasin Ullah**
Bannu Software Solutions
🌐 [www.yasinbss.com](https://www.yasinbss.com)
📱 WhatsApp: [+92 336 1593533](https://wa.me/923361593533)

---

*© 2026 tentwenty. All rights reserved.*

