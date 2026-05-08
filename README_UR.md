# ticktock - Timesheet Management App

A sleek, SaaS-style timesheet management application built as a single-file solution. This project was developed as part of a technical assessment to demonstrate UI/UX accuracy, API integration simulation, and state management using native web technologies.

## 🚀 Live Demo
**[Link to your hosted demo, e.g., GitHub Pages]**
*(Note to User: See instructions below on how to set this up)*

## 🛠️ Frameworks & Libraries Used
- **Vanilla HTML5 & JavaScript (ES6+)**: Core application logic.
- **Tailwind CSS (via CDN)**: For modern, responsive, and pixel-perfect styling.
- **IndexedDB**: For persistent, client-side data storage without a backend.
- **Inter Font**: Sourced via Google Fonts for typography.
- **Heroicons**: SVG icons for the UI.

## 📋 Setup Instructions
Since this is a self-contained application, no installation or build process is required.

1.  Clone this repository or download the `index.html` file.
2.  Open `index.html` directly in any modern web browser (Chrome, Firefox, Edge, Safari).
3.  **Login Credentials**:
    - **Email**: `name@example.com`
    - **Password**: `password` (or any text, as it uses dummy authentication).

## 💡 Assumptions & Notes
- **Dummy Authentication**: As requested, the login system is a simulation. It stores a session token in `sessionStorage` upon a successful "login".
- **API Simulation**: The application uses an asynchronous API layer that interacts with IndexedDB. This layer simulates network latency using Promises and `setTimeout` to demonstrate real-world integration patterns.
- **Data Persistence**: All data (projects, tasks, hours) is saved locally in your browser's IndexedDB. Clearing browser data will reset the app state.
- **Date Scope**: The dashboard is specifically optimized for January 2024 to match the primary focus of the Figma designs provided.
- **Responsive Design**: The UI is fully responsive, adapting from a split-screen desktop layout to a stacked mobile-friendly view.

## ⏳ Time Spent
- **Research & Design Analysis**: 1 hour
- **Data Layer & IndexedDB Implementation**: 1.5 hours
- **UI Development & Styling**: 2.5 hours
- **Logic & Integration**: 1 hour
- **Total**: ~6 hours

---

### How to complete your submission:

1.  **Create GitHub Repo**: 
    - Create a new public repository on GitHub.
    - Upload `index.html` and this `README.md`.
2.  **Working Online Demo**:
    - Go to your Repo **Settings** > **Pages**.
    - Set the Branch to `main` and the folder to `/ (root)`.
    - GitHub will give you a URL (e.g., `https://yourusername.github.io/reponame/`). 
    - Update the **Live Demo** link at the top of this README with that URL.
