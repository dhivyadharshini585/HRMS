# Modern HR Management System (HRMS)

This is a comprehensive, full-stack Human Resource Management System built to handle everything from employee onboarding and payroll to AI-driven candidate screening. 

## Tech Stack
- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: React (Vite)
- **Database**: MySQL / MariaDB
- **AI Integration**: Google Gemini 1.5 Pro API

## Features (Phases 1-7)
1. **Core Architecture & Dashboard**: Custom auth with Spatie Permissions, robust role-based access control (Super Admin, HR Admin, HR Executive, Manager, Employee, Finance/Payroll Admin), and a central dashboard.
2. **Employee Lifecycle**: End-to-end management from adding employees to tracking their shifts, holidays, leaves, and attendance logs.
3. **Recruitment & Applicant Tracking**: Manage job openings, candidate pipeline, offer letters, and onboarding checklists.
4. **Payroll & Compensation**: Statutory rules enforcement, salary structure generation, payslip PDF rendering, and bulk payroll approval processing.
5. **Performance & Training**: Goal setting, KPI tracking, performance reviews, and training schedules.
6. **Assets & IT Helpdesk**: Device assignments and IT support ticketing.
7. **AI & Automation**:
   - **AI Resume Screening**: Upload a candidate's resume (PDF/DOCX). The backend extracts the text and uses Gemini AI to analyze the match score against the job description and extract key skills.
   - **HR Assistant Chat**: A global floating chat bubble where admins can ask natural-language questions (e.g., "What is the total headcount in Engineering?"). The AI translates this to a safe `SELECT` query, executes it against a read-only database, and formulates a human-readable response.

## Setup Instructions

### 1. Backend Setup (Laravel)
Navigate to the `hrm-backend` directory:
```bash
cd hrm-backend
composer install
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file:
```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms
DB_USERNAME=root
DB_PASSWORD=your_password

# For security, create a secondary MySQL user with SELECT-only grants
DB_READONLY_USERNAME=readonly_user
DB_READONLY_PASSWORD=readonly_password

# Required for AI Features
GEMINI_API_KEY=your_gemini_api_key
```

Run database migrations and seeders:
```bash
php artisan migrate --seed
```

Start the backend server:
```bash
php artisan serve
```

### 2. Frontend Setup (React)
Navigate to the `hrm-frontend` directory:
```bash
cd ../hrm-frontend
npm install
```

Start the Vite development server:
```bash
npm run dev
```

## Security Architecture & Best Practices
- **Strict RBAC**: Every API route and React View is protected by specific capabilities/roles defined via Spatie permissions.
- **AI Query Isolation**: The HR AI Assistant automatically limits queries to `100` rows and executes via the `ai-readonly` database connection to prevent catastrophic LLM hallucinations (e.g., `DROP TABLE`, `DELETE`). It also enforces an explicit PDO timeout.
- **File Upload Security**: Uploaded resumes are strictly validated for MIME types (`pdf, doc, docx`) and capped at `10MB`.

## Roles & Permissions
- **Super Admin**: Full system access.
- **HR Admin**: Manages employees, recruitment, payroll configurations.
- **HR Executive**: Handles day-to-day candidate pipelines and attendance.
- **Finance / Payroll Admin**: Manages salary processing and statutory reporting.
- **Manager**: Team-level views for timesheets, leave approvals, and performance tracking.
- **Employee**: Self-service portal for payslips, leaves, goals, and helpdesk.
