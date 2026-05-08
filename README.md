# ticktock — Timesheet Management Application

> A SaaS-style timesheet management dashboard built for the **TenTwenty Frontend Technical Assessment 2025**.

---

## 🔗 Links

| | |
|---|---|
| **GitHub Repository** | [github.com/your-username/ticktock](https://github.com/your-username/ticktock) |
| **Live Demo** | [ticktock.your-domain.com](https://ticktock.your-domain.com) |

---

## ✨ Features

- 🔐 Secure login with math CAPTCHA, session management (40-min idle / 8-hr absolute timeout)
- 📝 **User Registration** — New users can sign up and wait for admin approval
- 🛡️ **Admin Dashboard** — Admins can approve/disapprove users, change roles, and delete accounts
- 📋 Weekly timesheet dashboard with status tracking (Completed / Incomplete / Missing)
- 📅 Date range filtering — multi-week range automatically expands to show all covered weeks
- 📝 Add, edit, and delete timesheet entries grouped by day
- 📊 Real-time progress bar (hours logged vs. 40-hour target)
- 🔒 Change password with strength enforcement
- 📱 Fully responsive — mobile, tablet, desktop
- 🎨 Pixel-perfect implementation of the provided Figma design

---

## 🚀 Why Native PHP & Vanilla JavaScript?

While frameworks like React, Next.js, and Tailwind CSS are popular, this project intentionally uses **Native PHP and Vanilla JS** for the following reasons:

1. **Zero Dependency Bloat:** Avoids the overhead of large frameworks and thousands of sub-dependencies, ensuring a lightweight, secure, and easily auditable application.
2. **Unmatched Performance:** Native execution provides the fastest possible load times and interactions with zero abstraction layers between the code and the environment.
3. **Future-Proof Standards:** Built on core web standards (HTML5, CSS3, ES6+) that remain compatible across decades, unlike frameworks that may become obsolete or require frequent breaking-change migrations.
4. **Complete Architectural Control:** Allows for surgical optimization and custom implementations that aren't constrained by framework-specific paradigms or "opinions."
5. **Simplified Build Pipeline:** No complex transpilation, bundling, or build steps required. The source code is the production code, making deployment and debugging straightforward.

---

## 🛠️ Setup Instructions

### Prerequisites

- PHP **8.0+** with GD extension enabled
- MySQL **5.7+** or MariaDB **10.3+**
- A web server: Apache / Nginx / `php -S` (local dev)

---

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/ticktock.git
cd ticktock
```

---

### 2. Create the Database

Log into MySQL and run the provided SQL file:

```bash
mysql -u root -p < database.sql
```

This will:
- Create the `ticktock_db` database
- Create all required tables (`users`, `projects`, `work_types`, `timesheet_entries`, `captcha_store`)
- Seed 4 demo users and realistic timesheet data

---

### 3. Configure Database Credentials

Open `index.php` and update the constants at the very top of the file:

```php
define('DB_HOST', 'localhost');      // Your MySQL host
define('DB_NAME', 'ticktock_db');    // Database name
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password
```

---

### 4. Run the Application

**Option A — PHP built-in server (local dev):**
```bash
php -S localhost:8000
```
Then open [http://localhost:8000](http://localhost:8000)

**Option B — Apache:**
Place `index.php` and `database.sql` in your `htdocs` or `www` directory and access via your configured virtual host.

**Option C — Nginx:**
Point your Nginx root to the project directory. Ensure PHP-FPM is configured and `.php` files are processed correctly.

---

### 5. Demo Login Credentials

| Name | Email | Password | Role |
|---|---|---|---|
| John Doe | john.doe@tentwenty.me | `admin@123` | Admin |
| Sarah Connor | sarah.connor@tentwenty.me | `admin@123` | User |
| Michael Chen | michael.chen@tentwenty.me | `admin@123` | User |
| Priya Sharma | priya.sharma@tentwenty.me | `admin@123` | User |

> **Note:** The CAPTCHA is a simple math question (e.g. `4 + 7 = ?`). Solve it to log in. Click the CAPTCHA image to refresh it.

---

## 📦 Frameworks & Libraries Used

| Library | Version | Purpose |
|---|---|---|
| **Inter** (Google Fonts) | — | Primary UI typeface (per Figma spec) |
| **Flatpickr** | latest | Date range picker for timesheet filters |
| **SweetAlert2** | v11 | All modal dialogs, confirmations, and toast notifications |
| **Animate.css** | v4.1.1 | Entrance animations on page load, modals, and table rows |
| **PHP GD** | built-in | Server-side CAPTCHA image generation with noise and stroke lines |
| **PDO (PHP)** | built-in | Database access with prepared statements |

> **No React, Next.js, Vue, or any JS framework was used.** The entire frontend is written in **Vanilla HTML, CSS, and JavaScript** as required. The backend is **pure PHP** with no Composer dependencies.

---

## 🏗️ Architecture

The application is intentionally delivered as **two files only**:

```
ticktock/
├── index.php       # All PHP backend logic + HTML + CSS + JS (single file)
├── database.sql    # MySQL schema + seed data
└── README.md       # This file
```

### How the API Works

`index.php` doubles as both the **view renderer** and a **JSON API**. Any request with a `?api=` query parameter returns JSON instead of HTML:

| Endpoint | Method | Description |
|---|---|---|
| `?api=captcha` | GET | Generate a new math CAPTCHA image |
| `?api=login` | POST | Authenticate user + validate CAPTCHA |
| `?api=logout` | POST | Destroy session |
| `?api=me` | GET | Return current session user |
| `?api=timesheets` | GET | List all weekly timesheets with status |
| `?api=week_entries` | GET | List all entries for a specific week |
| `?api=entry` | POST | Create or update a timesheet entry |
| `?api=entry` | DELETE | Delete a timesheet entry |
| `?api=projects` | GET | List all active projects |
| `?api=work_types` | GET | List all active work types |
| `?api=change_password` | POST | Update authenticated user's password |

All client-side data fetching is done via `fetch()` calls to these endpoints — **no data is hardcoded in the frontend JS**.

---

## 💡 Assumptions & Notes

1. **Single-file constraint** — The assessment asked to demonstrate API integration skill. Rather than calling a separate service, `index.php` handles both rendering and API responses depending on the presence of the `?api=` query parameter. This cleanly separates concerns while staying in one file.

2. **Authentication** — A simple PHP session-based authentication system is used. The "Remember me" checkbox is wired up but full persistent cookie-based remember-me would require a database token table; for this scope, it is noted as a future enhancement.

3. **Status logic** — Implemented exactly per the Figma annotations:
   - **Completed** = exactly 40 hours or more logged for the week
   - **Incomplete** = between 0 (exclusive) and 40 hours logged
   - **Missing** = 0 hours logged for the week

4. **Date range filter** — When a selected range spans multiple weeks, all those weeks are shown in the table, as specified in the Figma notes.

5. **CAPTCHA** — A server-generated GD image math CAPTCHA is used. This avoids any third-party CAPTCHA service dependency and demonstrates server-side image generation. Tokens expire in 10 minutes.

6. **Password hashing** — All passwords use `bcrypt` with cost factor 12 via PHP's `password_hash()`.

7. **CSRF protection** — All state-changing API requests (POST, DELETE) require a CSRF token sent via the `X-CSRF-TOKEN` header, validated server-side with `hash_equals()`.

8. **Week calculation** — Weeks follow ISO 8601 Monday–Sunday boundaries. The year used for seeded data is 2024 to match the Figma design samples.

9. **GD extension** — The CAPTCHA requires PHP's GD image library. This is enabled by default in most PHP installations. If not available, run: `sudo apt install php-gd` and restart your server.

---

## ⏱️ Time Spent

| Task | Time |
|---|---|
| Figma design analysis & planning | ~30 min |
| Database schema design | ~20 min |
| PHP backend (API endpoints, auth, CAPTCHA) | ~2 hrs |
| Login screen (HTML/CSS, JS validation, CAPTCHA flow) | ~45 min |
| Dashboard table view (fetch, render, filter, sort, paginate) | ~1.5 hrs |
| Week detail / list view (grouped entries, progress bar) | ~1 hr |
| Add/Edit modal + form validation | ~45 min |
| Change password modal | ~20 min |
| Responsive CSS + animations + polish | ~1 hr |
| Testing, debugging, seed data | ~30 min |
| **Total** | **~8.5 hours** |

---

## 👤 Author

**Yasin Ullah**
Bannu Software Solutions
🌐 [www.yasinbss.com](https://www.yasinbss.com)
📱 WhatsApp: [03361593533](https://wa.me/923361593533)

---

*© 2024 tentwenty. All rights reserved.*
