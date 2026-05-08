# 🕰️ ticktock — Advanced Timesheet Management Suite

> A premium, high-performance timesheet management solution developed for the **TenTwenty Frontend Technical Assessment 2026**. This suite features two distinct architectural implementations: a **Server-Side PHP/MySQL** version and a **Client-Side PWA/IndexedDB** version.

---

## 🔗 Live Deployments & Repository

| Platform | URL |
|---|---|
| **Live PWA Demo** | [https://yasinullah.github.io/PHPtestassement/](https://yasinullah.github.io/PHPtestassement/) |
| **GitHub Repository** | [https://github.com/yasinullah/PHPtestassement](https://github.com/yasinullah/PHPtestassement) |

---

## 🏗️ Architectural Implementations

This project demonstrates versatility by providing two complete solutions for different use cases:

### 1. Client-Side PWA (Single-File / Serverless)
**Target:** Local-first, privacy-focused users and offline field work.
- **Tech Stack:** Vanilla JS (ES6+), Tailwind CSS, IndexedDB.
- **Key Features:**
  - **PWA (Progressive Web App):** Installable on mobile/desktop; works 100% offline via Service Workers.
  - **Local Persistence:** Data is stored directly in the browser's IndexedDB.
  - **Backup & Restore:** Export your entire database as a JSON file or import existing backups.
  - **Local Registration:** Create private user accounts without a server.

### 2. Server-Side Suite (Enterprise / Centralized)
**Target:** Teams requiring centralized data management and administrative oversight.
- **Tech Stack:** Native PHP 8.0+, MySQL, Vanilla CSS.
- **Key Features:**
  - **Admin Control Panel:** Manage Users, Projects, and Work Types from a central UI.
  - **Submission Workflow:** Multi-stage timesheet lifecycle (Draft -> Pending -> Approved/Rejected).
  - **Profile & Account Management:** User profile updates, avatar customization, and secure password changes.
  - **Global Analytics:** Detailed reporting on hours by project and user.
  - **Enhanced Security:** IP-based rate limiting, CSRF protection, math CAPTCHA, and session absolute timeouts.
  - **Password Recovery:** Token-based password reset system with simulated email logging.
  - **Data Export:** Built-in CSV export for payroll and external reporting.
  - **Personalized Setup:** Configurable standard work hours per user.

---

## ✨ Core Feature Set (Both Versions)

- **📈 Timesheet Management:** Weekly dashboard with status tracking (Completed / Incomplete / Missing).
- **📅 Smart Filtering:** Date range filtering with automatic week-span expansion.
- **📝 Daily Logging:** Surgical entry management; group tasks by day with project and work-type tagging.
- **📊 Interactive Progress:** Real-time visual feedback on weekly targets (e.g., 40-hour goal).
- **🔒 Security First:** Password strength enforcement and secure session handling.
- **📱 Ultra-Responsive:** Pixel-perfect implementation of Figma designs across all device sizes.

---

## 🛠️ Why Native Development?

Unlike typical framework-heavy builds, this project utilizes **Native PHP and Vanilla JS** to deliver:
- **Zero Dependency Bloat:** Minimal attack surface and no `node_modules` overhead.
- **Maximum Performance:** Instantaneous load times and near-zero latency.
- **Durability:** Built on core web standards that will remain functional for decades without breaking changes.

---

## 📂 Project Structure & Setup

- **`index.html`**: The Master Client-Side PWA. (Open directly or host on GitHub Pages).
- **`index.php`**: The Server-Side Enterprise App. (Requires PHP & MySQL).
- **`PHPreadme.md`**: Detailed technical setup for the PHP version.
- **`README_UR.md`**: Dedicated documentation for the Client-Side PWA features.
- **`database.sql`**: Complete schema for the MySQL environment.

### Quick Start (Server-Side)
1. Import `ticktock_db-db_2026-05-08.sql` (Recommended) or `database.sql` into your MySQL server.
2. Update DB credentials in `index.php`.
3. Run on any PHP-enabled server.

---

## 👤 Author

**Yasin Ullah**
Bannu Software Solutions
🌐 [www.yasinbss.com](https://www.yasinbss.com)
📱 WhatsApp: [+92 336 1593533](https://wa.me/923361593533)

---

*© 2026 tentwenty. All rights reserved.*

