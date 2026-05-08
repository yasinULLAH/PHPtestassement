# 🕰️ ticktock — Timesheet Management Application
**Built with Native PHP & Vanilla JavaScript | Zero Dependencies | Enterprise-Ready Security**

---

## 🌐 English Documentation

### 📖 Overview
`ticktock` is a complete, single-file Timesheet Management Web Application designed for modern workforce tracking. Built entirely with **Native PHP 8+** and **Vanilla JavaScript**, it eliminates framework bloat while delivering unmatched performance, future-proof standards, and a clean, responsive UI. It features role-based access, automated week tracking, approval workflows, admin oversight, and enterprise-grade security.

### 🛠️ Architecture & Tech Stack
| Component | Technology |
|-----------|------------|
| **Backend** | Native PHP 8+ (Single-file routing, PDO, JSON API) |
| **Frontend** | HTML5, CSS3 (Custom + Animate.css), Vanilla ES6 JavaScript |
| **Database** | MySQL / MariaDB (PDO with prepared statements) |
| **UI Libraries** | SweetAlert2 (Notifications), Flatpickr (Date Picker), Inter Font |
| **Architecture** | Monolithic API Router (`?api=...`), Session-based Auth, Stateful UI |
| **Dependencies** | **Zero**. No npm, no composer, no React/Next.js/Vue. Pure standard web stack. |

### 👥 User Roles & Permissions
| Role | Capabilities & Restrictions |
|------|-----------------------------|
| **Employee (User)** | Create/edit/delete timesheet entries, submit weekly timesheets for approval, view personal progress, update profile/avatar, change password, export personal CSV reports. |
| **Administrator** | Full access to all employee features + Manage users (approve, assign roles, adjust standard hours, soft-delete), manage projects & work types (activate/deactivate), review & approve/reject submissions with comments, view global reports, export any user's timesheet. |

---

### 📦 Core Modules & Workflows

#### 🔐 1. Authentication & Account Management
- **Login:** Email/password + math CAPTCHA + IP rate-limiting (10 attempts/15 min). Sessions regenerate on login to prevent fixation.
- **Registration:** Users register with name, email, password (min 8 chars). Accounts are created with `is_approved = 0` and remain locked until an admin approves them.
- **Session Security:** Idle timeout (40 mins), absolute timeout (8 hours). Auto-logout with warning. HTTP-only, strict-mode cookies.
- **Password Reset:** Generates a 1-hour token. Logs simulated email to `email.txt`. Resets password with bcrypt hashing.
- **Profile Management:** Update name/email (checks for duplicates), upload avatar (JPG/PNG/WebP, max 2MB), change password (requires current password + special character).

#### 📝 2. Timesheet Tracking (Employee View)
- **Weekly Overview:** Displays all weeks in a selected date range. Shows status badges: `COMPLETED`, `INCOMPLETE`, `MISSING`, `PENDING`, `APPROVED`, `REJECTED`.
- **Smart Filtering & State:** Filter by status, sort by week/date, paginate (5/10/25/50/All). Table state auto-saves to `localStorage`.
- **Detailed Week View:** Groups entries by day. Shows progress bar vs user's `standard_hours` (default 40). Locked weeks (Pending/Approved) disable editing.
- **Entry Management:** Add/edit/delete daily tasks. Fields: Project, Work Type, Description, Hours (0.5–24, stepper UI). Real-time validation.
- **Submission Flow:** Click "Submit for Approval" → locks the entire week → moves to admin queue → prevents further edits until reviewed.
- **Export:** Download weekly timesheet as CSV with date, project, type, hours, description.

#### 🛡️ 3. Admin Dashboard & Oversight
- **User Management Table:** View all users. Inline-edit roles, standard hours, approval status, or soft-delete (sets `is_active=0`, `is_deleted=1`). 
- **Account Recovery:** Restore soft-deleted users or permanently **Purge** accounts (removing all associated timesheet data).
- **Project & Work Type Management:** Add/edit reference data. Toggle `is_active` to hide from employee dropdowns without breaking historical data.
- **Submission Review Queue:** View pending timesheets. Admins can **edit entries** during review to fix minor errors. Approve instantly or Reject with a mandatory reason.
- **Global Reports:** Filter by date range. Aggregates total hours by Project and by User.
- **System-Wide Settings:** Dedicated panel to manage global defaults such as the Company Name and Default Standard Hours for new registrations.

#### 🎨 4. UI/UX & Client Experience
- **Responsive Design:** Fully adaptive for desktop, tablet, and mobile.
- **Performance:** Skeleton loaders for async data, lazy-loading modals, debounced interactions, zero framework overhead.
- **Interactive Feedback:** Smooth animations with Animate.css and non-intrusive success/error toasts.
- **Print-Ready:** Dedicated `@media print` styles hide navigation, filters, and buttons. Clean, professional paper output.

---

### 🔒 Security & Performance Highlights
| Feature | Implementation | Impact |
|---------|----------------|--------|
| **CSRF Protection** | Token generated per session, verified on all `POST`/`DELETE` requests | Prevents cross-site request forgery |
| **SQL Injection Prevention** | `PDO::ATTR_EMULATE_PREPARES => false`, strict prepared statements | Blocks all injection vectors |
| **XSS Mitigation** | `htmlspecialchars()` with `ENT_QUOTES`, `sanitizeStr()` on all inputs | Safe HTML rendering |
| **Rate Limiting** | Tracks IPs in `login_attempts`, blocks after 10 tries/15 mins | Stops brute-force attacks |
| **Password Storage** | `password_hash()` with `bcrypt`, cost `12` | Industry-standard encryption |
| **Session Hardening** | `session.cookie_httponly=1`, `use_strict_mode=1`, `session_regenerate_id()` | Prevents session hijacking |
| **Soft Deletes** | Sets `is_deleted=1` instead of `DELETE` | Preserves audit trails & historical data |

---

### 🛠️ Installation & Configuration
1. **Requirements:** PHP 8.0+, MySQL 5.7+, Apache/Nginx, GD Library (for CAPTCHA).
2. **Database Setup:** Create DB `ticktock_db`. Run inferred schema (see below).
3. **Config:** Edit top of `index.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'ticktock_db');
   define('DB_USER', 'root');
   define('DB_PASS', 'root');
   ```
4. **Folders:** Ensure `uploads/avatars/` is writable (`chmod 755`).
5. **Deploy:** Upload `index.php` to web root. Access via browser. First user registers → Admin approves → System ready.

#### 📊 Inferred Database Schema
```sql
users (id, name, email, password_hash, role, is_approved, is_active, is_deleted, standard_hours, avatar_url, reset_token, reset_expires, created_at)
projects (id, name, is_active)
work_types (id, name, is_active)
timesheet_entries (id, user_id, date, project_id, work_type_id, description, hours, submission_id)
timesheet_submissions (id, user_id, year, week, status, rejection_reason, submitted_at, reviewed_at, reviewed_by)
captcha_store (token, answer, expires_at)
login_attempts (id, ip_address, attempt_time)
```

---

## 📖 اردو دستاویزات (Urdu Documentation)

### 📖 تعارف
`ticktock` ایک مکمل، جدید ٹائم شیٹ مینجمنٹ ویب ایپلیکیشن ہے جو جدید ورک فورس ٹریکنگ کے لیے ڈیزائن کی گئی ہے۔ یہ **نیٹیو PHP 8+** اور **ویلینا جاوا اسکرپٹ** پر بنائی گئی ہے، جس سے فریم ورک کا بوجھ ختم ہو جاتا ہے اور بہترین کارکردگی، مستقبل کے معیارات، اور ریسپانسو UI ملتا ہے۔ اس میں رول بیسڈ ایکسس، خودکار ویک ٹریکنگ، اپروول ورک فلو، ایڈمن نگرانی، اور اینٹرپرائز گریڈ سیکیورٹی شامل ہے۔

### 👥 صارفین کے کردار اور اجازتیں
| کردار | صلاحیتیں اور پابندیاں |
|------|-----------------------------|
| **ملازم (User)** | ٹائم شیٹ انٹریز بنانا/ترمیم/حذف، ہفتہ وار ٹائم شیٹ اپروول کے لیے جمع کرانا، ذاتی پروگریس دیکھنا، پروفائل/اوتار اپ ڈیٹ کرنا، پاس ورڈ تبدیل کرنا، ذاتی CSV رپورٹ ایکسپورٹ کرنا۔ |
| **ایڈمنسٹریٹر** | تمام ملازمین کی خصوصیات + صارفین کا انتظام (اپروو، رول اسائن، معیاری گھنٹے ایڈجسٹ، سافٹ ڈیلیٹ)، پروجیکٹس اور ورک ٹائپس کا انتظام، جمع شدہ ٹائم شیٹس کا جائزہ/اپروو/ریجیکٹ، گلوبل رپورٹس، کسی بھی صارف کی ٹائم شیٹ ایکسپورٹ۔ |

### 📦 ماڈیولز اور ورک فلو کی تفصیل

#### 🔐 1. لاگ ان اور اکاؤنٹ مینجمنٹ
- **لاگ ان:** ای میل/پاس ورڈ + میتھ کیپچا + آئی پی ریٹ لمٹنگ (15 منٹ میں 10 کوششیں)۔ سیکیورٹی کے لیے سیشن ری جنریٹ ہوتا ہے۔
- **رجسٹریشن:** نام، ای میل، پاس ورڈ (کم از کم 8 حروف)۔ اکاؤنٹس `is_approved = 0` کے ساتھ بنتے ہیں اور ایڈمن کی منظوری تک لاگ ان نہیں ہو سکتے۔
- **سیشن سیکیورٹی:** 40 منٹ کی غیر فعالی یا 8 گھنٹے کی مطلق مدت کے بعد خودکار لاگ آؤٹ۔ انتباہ کے ساتھ سیشن ختم ہوتا ہے۔
- **پاس ورڈ ری سیٹ:** 1 گھنٹے کی ٹوکن جنریٹ ہوتی ہے۔ ای میل `email.txt` فائل میں لاگ ہوتی ہے۔ پاس ورڈ bcrypt ہیشنگ کے ساتھ اپ ڈیٹ ہوتا ہے۔
- **پروفائل:** نام/ای میل اپ ڈیٹ، اوتار اپ لوڈ (JPG/PNG/WebP، زیادہ سے زیادہ 2MB)، پاس ورڈ تبدیل (موجودہ پاس ورڈ + سپیشل کریکٹر لازمی)۔

#### 📝 2. ٹائم شیٹ ٹریکنگ (ملازم ویو)
- **ہفتہ وار اوور ویو:** منتخب ڈیٹ رینج کے تمام ہفتے دکھاتا ہے۔ اسٹیٹس بیجز: `COMPLETED`، `INCOMPLETE`، `MISSING`، `PENDING`، `APPROVED`، `REJECTED`۔
- **فلٹرنگ اور اسٹیٹ:** اسٹیٹس فلٹر، ترتیب (Sort)، پیجینیشن۔ ٹیبل اسٹیٹ خودکار طور پر `localStorage` میں محفوظ ہوتی ہے۔
- **ہفتہ کی تفصیل:** انٹریز کو دن کے حساب سے گروپ کرتا ہے۔ صارف کے `standard_hours` (ڈیفالٹ 40) کے مقابلے میں پروگریس بار۔ اپرووڈ/پینڈنگ ہفتے ایڈیٹ کے لیے لاک ہو جاتے ہیں۔
- **انٹری مینجمنٹ:** روزانہ ٹاسکس شامل/ترمیم/حذف۔ فیلڈز: پروجیکٹ، ورک ٹائپ، تفصیل، گھنٹے (0.5–24)۔ ریئل ٹائم ویلیڈیشن۔
- **جمع کروانے کا عمل:** "Submit for Approval" پر کلک → ہفتہ لاک ہو جاتا ہے → ایڈمن کیو میں چلا جاتا ہے → جائزے تک ایڈٹنگ بند رہتی ہے۔
- **ایکسپورٹ:** ہفتہ وار ٹائم شیٹ CSV فارمیٹ میں ڈاؤن لوڈ کریں۔

#### 🛡️ 3. ایڈمن ڈیش بورڈ اور نگرانی
- **صارف مینجمنٹ:** تمام صارفین دیکھیں۔ رولز، معیاری گھنٹے، منظوری کی حالت آن لائن تبدیل کریں، یا سافٹ ڈیلیٹ کریں۔
- **اکاؤنٹ ریکوری:** ڈیلیٹ شدہ صارفین کو **Restore** کریں یا ہمیشہ کے لیے **Purge** (مکمل خاتمہ) کریں۔
- **پروجیکٹ اور ورک ٹائپس:** ریفرنس ڈیٹا شامل/ترمیم کریں۔ `is_active` ٹوگل کر کے پرانے ڈیٹا کو خراب کیے بغیر نئے ڈراپ ڈاؤن سے چھپائیں۔
- **سبمیشن ریویو:** پینڈنگ ٹائم شیٹس دیکھیں۔ ایڈمن جائزے کے دوران **انٹریز کو ایڈیٹ** کر سکتے ہیں۔ فوری اپروو یا وجہ کے ساتھ ریجیکٹ کریں۔
- **گلوبل رپورٹس:** ڈیٹ رینج فلٹر۔ پروجیکٹ اور صارف کے لحاظ سے کل گھنٹوں کا خلاصہ۔
- **سسٹم سیٹنگز:** کمپنی کا نام اور ڈیفالٹ معیاری گھنٹے سیٹ کرنے کے لیے خصوصی پینل۔

#### 🎨 4. UI/UX اور کلینٹ کا تجربہ
- **ریسپانسو ڈیزائن:** ڈیسک ٹاپ، ٹیبلیٹ، موبائل کے لیے مکمل موافق۔
- **کارکردگی:** اسکیلٹن لوڈرز، لیژی لوڈنگ ماڈلز، زیرو فریم ورک اوور ہیڈ۔
- **انٹرایکٹو تجربہ:** Animate.css کے ذریعے بہترین اینیمیشنز اور ٹوسٹ نوٹیفکیشنز۔
- **پرنٹ ریڈی:** `@media print` اسٹائلز نیویگیشن اور بٹنز چھپاتی ہیں۔ صاف، پروفیشنل کاغذی آؤٹ پٹ۔

### 🔒 سیکیورٹی اور کارکردگی
| فیچر | نفاذ | اثر |
|---------|----------------|--------|
| **CSRF پروٹیکشن** | فی سیشن ٹوکن، تمام `POST`/`DELETE` پر ویریفائی | کراس سائٹ اٹیگز سے بچاؤ |
| **SQL انجیکشن روک تھام** | `PDO` پریپیئرڈ اسٹیٹمنٹس، ایمولیٹ آف | تمام ڈیٹا بیس اٹیگز بلاک |
| **XSS مٹِگیشن** | `htmlspecialchars()` اور ان پٹ سینٹائزیشن | محفوظ HTML رینڈرنگ |
| **ریٹ لمٹنگ** | آئی پی ٹریکنگ، 10 کوششوں کے بعد بلاک | بریٹ فورس روک تھام |
| **پاس ورڈ اسٹوریج** | `bcrypt` کاسٹ 12 | انڈسٹری سٹینڈرڈ انکرپشن |
| **سیشن ہارڈننگ** | `httponly`، `strict_mode`، آئی ڈی ری جنریشن | سیشن ہائی جیکنگ روک تھام |
| **سافٹ ڈیلیٹس** | `is_deleted=1` سیٹ کرتا ہے | آڈٹ ٹریل اور تاریخی ڈیٹا محفوظ |

### 🛠️ انسٹالیشن اور ترتیب
1. **ضروریات:** PHP 8.0+, MySQL 5.7+, Apache/Nginx, GD Library۔
2. **ڈیٹا بیس:** `ticktock_db` بنائیں۔ اسکیما (اوپر دی گئی) امپورٹ کریں۔
3. **کنفیگ:** `index.php` کے اوپر `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` اپ ڈیٹ کریں۔
4. **فولڈرز:** `uploads/avatars/` بنائیں اور `755` پرمیشن دیں۔
5. **ڈپلائے:** `index.php` ویب روٹ میں اپ لوڈ کریں۔ پہلا صارف رجسٹر ہو → ایڈمن اپروو → سسٹم تیار۔

---

## 📞 سپورٹ اور کریڈٹس
- **ڈویلپر:** Yasin Ullah – Bannu Software Solutions
- **ویب سائٹ:** [www.yasinbss.com](https://www.yasinbss.com)
- **واٹس ایپ:** +92 336 1593533
- **لائسنس:** proprietary / custom client delivery
- **نوٹ:** یہ ایپلیکیشن مکمل طور پر کلائنٹ کی ضروریات کے مطابق ڈیزائن کی گئی ہے۔ کسی بھی اپ ڈیٹ، فیچر ری کوئسٹ، یا ڈیپلائمنٹ سپورٹ کے لیے براہ کرم مذکورہ ذرائع سے رابطہ کریں۔

---
✅ **یہ دستاویز ایپلیکیشن کے ہر فیچر، سیکیورٹی میکانزم، ڈیٹا فلو، اور کلینٹ ورک فلو کا مکمل احاطہ کرتی ہے۔**  
🔒 **تیار شدہ برائے پروفیشنل ڈپلائمنٹ اور کلینٹ ہینڈ آف۔**