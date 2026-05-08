# ticktock — Timesheet Management App (PWA)

A sleek, SaaS-style timesheet management application built as a single-file solution. It has now been upgraded to a full PWA (Progressive Web App) to allow for offline use and installation on both desktop and mobile devices.

## 🚀 Live Demo
**[https://yasinullah.github.io/PHPtestassement/](https://yasinullah.github.io/PHPtestassement/)**

## 🛠️ Technologies & Libraries Used
- **Vanilla HTML5 & JavaScript (ES6+)**: Core application logic.
- **Tailwind CSS**: Modern and responsive UI styling.
- **IndexedDB**: Local browser-based database for data persistence (no backend/server required).
- **PWA (Progressive Web App)**: Provides an app-like experience with installation support via `manifest.json` and `sw.js`.
- **Inter Font**: High-quality typography via Google Fonts.
- **Heroicons**: Beautiful SVG icons for the UI.

## ✨ New Client-Side Features
- **PWA Capabilities**: You can now install this app on your phone or computer like a native application. It works offline thanks to Service Workers.
- **User Registration**: Users can now create their own accounts locally. This data is stored securely in the browser's IndexedDB.
- **Data Backup & Restore**: Since the app is serverless, users can export their entire database (Users, Projects, and Timesheets) as a JSON file. This file can be imported back at any time to restore the data on a different device or after clearing browser storage.
- **Responsive Management**: Surgical entry management and weekly dashboard status tracking tailored for local use.

## 📋 Usage Instructions
This is a self-contained application, so no complex setup is required.

1. Download the project files (`index.html`, `manifest.json`, `sw.js`, `icon.svg`).
2. Run them through a local server (e.g., VS Code Live Server or `php -S localhost:8000`) for Service Workers and PWA features to function correctly.
3. **Login Details**:
   - You can create a new account via the registration page.
   - Or use the default testing credentials:
     - **Email**: `name@example.com`
     - **Password**: `password`

## 💡 Important Notes & Assumptions
- **Local Data Persistence**: All your data is stored in the browser's IndexedDB. Clearing browser cache/site data will delete your timesheets. Use the **Backup Data** button frequently to keep your data safe.
- **Offline-First**: Thanks to PWA integration, the app can load even without an internet connection.
- **Data Structure**: The dashboard is specifically designed to showcase timesheets for January 2024 as per the client's design requirements.

---
**Time Spent:**
- Research & Planning: 1 Hour
- IndexedDB & Data Layer: 1.5 Hours
- UI & Styling: 2.5 Hours
- PWA, Backup, and Registration: 2 Hours
- **Total Time**: ~7 Hours

*© 2026 tentwenty. All rights reserved.*
