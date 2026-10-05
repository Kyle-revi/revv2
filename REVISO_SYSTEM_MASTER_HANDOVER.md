# 📘 REVISO: Complete System Architecture, Mechanics & Deployment Handover Guide

> **📌 Document Purpose:**  
> This master document serves as the complete technical, architectural, and operational handover for **Reviso**. It is designed to be fed into a new AI chat or given to developers setting up a duplicate repository, a new GitHub repository, and a new **Railway** deployment while keeping the original project frozen for panelist presentation.

---

## 📑 Table of Contents
1. [Executive Overview & Project Identity](#1-executive-overview--project-identity)
2. [Tech Stack & System Environment](#2-tech-stack--system-environment)
3. [User Roles, Access Matrix & Program Routing](#3-user-roles-access-matrix--program-routing)
4. [Core Modules & Detailed System Mechanics](#4-core-modules--detailed-system-mechanics)
   - [A. Pre-Test / Post-Test & Formal Assessment Lockout Engine](#a-pre-test--post-test--formal-assessment-lockout-engine)
   - [B. Multi-Post Test Phases & Best Score Engine](#b-multi-post-test-phases--best-score-engine)
   - [C. Mock Board Exam & Passing Likelihood Prediction](#c-mock-board-exam--passing-likelihood-prediction)
   - [D. Psychometric Item Analysis & Distractor Efficiency](#d-psychometric-item-analysis--distractor-efficiency)
   - [E. Historical Board Exam Comparison Engine](#e-historical-board-exam-comparison-engine)
   - [F. AI Engine & Grounding Validation Pipeline (Cloudflare Workers AI)](#f-ai-engine--grounding-validation-pipeline-cloudflare-workers-ai)
   - [G. Test Bank Management System](#g-test-bank-management-system)
   - [H. Granular Module Visibility Controls (`All`, `Selected`, `Except`)](#h-granular-module-visibility-controls-all-selected-except)
   - [I. Admin Approvals & User Role Lifecycle](#i-admin-approvals--user-role-lifecycle)
5. [Database Architecture & Key Models](#5-database-architecture--key-models)
6. [Complete Step-by-Step Guide: New GitHub Repo to New Railway Deployment](#6-complete-step-by-step-guide-new-github-repo-to-new-railway-deployment)
7. [Environment Variables Reference (`.env`)](#7-environment-variables-reference-env)
8. [Testing & Quality Assurance Suite](#8-testing--quality-assurance-suite)
9. [Important Rules & Guardrails for the New Chat/Project](#9-important-rules--guardrails-for-the-new-chatproject)

---

## 1. Executive Overview & Project Identity

**Reviso** is an AI-powered Board Examination Review and Assessment Management System specifically tailored for Philippine Higher Education programs (Psychology, Education, and Accountancy). 

The platform bridges classroom learning and board exam readiness through:
- **Diagnostic Pre-Tests and Evaluative Multi-Post Tests**
- **Simulated Mock Board Examinations** with passing likelihood algorithms
- **Psychometric Item Analysis** (Difficulty Index & Distractor Efficiency)
- **Grounded AI Assessment Generation** (strictly anchored to teacher lectures without hallucinated options)
- **Individualized AI Insights & Class Performance Analytics**
- **Comprehensive Lecture & Test Bank Management**

---

## 2. Tech Stack & System Environment

- **Backend Framework:** Laravel 12 (PHP 8.4 / 8.5)
- **Frontend Architecture:** Blade Templates + Livewire 4 + Alpine.js + Tailwind CSS + Argon Dashboard UI
- **Database:** MySQL (Production on Railway) / SQLite (Automated PHPUnit & Playwright Testing)
- **AI Inference Engine:** Cloudflare Workers AI (`App\Services\CloudflareAI`) running Meta Llama models (`@cf/meta/llama-3.2-3b-instruct`, `@cf/meta/llama-3.1-8b-instruct`, `@cf/meta/llama-3.3-70b-instruct-fp8-fast`) with rule-based post-validation heuristics
- **Containerization & Web Server:** Docker (Alpine Linux) + Nginx + PHP-FPM + Supervisord
- **Deployment Platform:** Railway (Automated Dockerfile build, DB migrations & seeders via entrypoint script)
- **Automated Testing Suite:** 175 PHPUnit Unit/Feature Tests + Playwright Browser E2E Runner

---

## 3. User Roles, Access Matrix & Program Routing

Reviso enforces strict Role-Based Access Control (RBAC) and program-specific routing:

| Role | Programs Supported | Default Dashboard Route | Key Capabilities |
| :--- | :--- | :--- | :--- |
| **Student** | `Psychology` (`psych`), `Education` (`educ`), `Accountancy` (`accountancy`) | `/psych-dashboard`<br>`/educ-dashboard`<br>`/accountancy-dashboard` | Take pre-tests, post-tests, quizzes, mock boards; view AI insights, item analysis, lecture files, announcements, and program-scoped chats. |
| **Teacher** | `Psychology`, `Education`, `Accountancy` | `/teacher-dashboard` | Manage owned classes; upload lectures; trigger AI quiz generation; manage test bank; create mock boards; view student item analysis and benchmark comparisons. |
| **Admin** | Institutional Admin | `/admin-dashboard` | Approve/reject user registrations; deactivate teachers with reason logging; preview and approve mock boards; view program-wide board passing rates. |
| **Superadmin** | Master Admin | `/admin-dashboard` | Manage Admin accounts; reset admin passwords; configure Global AI API settings; override role program locks. |

---

## 4. Core Modules & Detailed System Mechanics

### A. Pre-Test / Post-Test & Formal Assessment Lockout Engine
- **Purpose:** Prevents academic dishonesty during formal assessments.
- **Lockout Mechanism (`FormalAssessmentLectureLockTest`):**
  - When a student begins a **Formal Assessment** (Pre-Test, Post-Test, or Midterm/Final), all lecture materials, module files, subpart readings, and download links in that class are **instantly locked** for that student.
  - A real-time **Dynamic Banner** ("Pre-Test In Progress" / "Post-Test In Progress") displays at the top of the student view.
  - **Auto-Unlock:** The moment the student submits the assessment or runs out of time, all lectures and study materials automatically unlock.
  - **Practice Exemption:** Formative practice quizzes do *not* trigger lecture lockouts.

### B. Multi-Post Test Phases & Best Score Engine
- **Purpose:** Supports iterative remediation (e.g., Post-Test Phase 1 after review, Post-Test Phase 2 after booster session).
- **Mechanics (`MockBoardMultiPostTestTest`):**
  - Teachers can attach **multiple post-test phases** to the same Mock Board / Class Module.
  - Each phase maintains its own isolated questions, timer, and student attempt snapshots.
  - **Best Score Persistence:** The system computes the student's mastery using their **highest achieved score** across all post-test phases, avoiding unfair score drops upon retakes.

### C. Mock Board Exam & Passing Likelihood Prediction
- **Likelihood Algorithm:**
  - Evaluates student performance across both Pre-Test and Post-Test phases:
    - **High Likelihood ($\ge 75\%$):** Consistent passing performance with positive learning delta.
    - **Moderate Likelihood ($60\% - 74\%$):** Borderline readiness; flagged for targeted subtopic review.
    - **Low Likelihood ($< 60\%$):** At-risk candidate requiring intensive intervention.
  - Predictions are only unlocked once both Pre-Test and at least one Post-Test phase are completed.

### D. Psychometric Item Analysis & Distractor Efficiency
- **Calculated Metrics (`MockBoardItemAnalysisTest`):**
  - **Difficulty Index ($p$-value):** Percentage of students who answered the item correctly ($\frac{R}{N}$).
    - High Difficulty: $p < 0.30$
    - Moderate Difficulty: $0.30 \le p \le 0.80$
    - Easy: $p > 0.80$
  - **Distractor Efficiency:** Tracks the percentage of students choosing incorrect options ($A, B, C, D$). Unselected distractors are flagged as non-functional distractors that need revision.

### E. Historical Board Exam Comparison Engine
- **Mechanics (`HistoricalBoardExamComparisonTest`):**
  - Admins encode official Philippine PRC Board Exam historical passing rates per year/batch.
  - Teachers link their class Mock Board to the corresponding PRC exam year.
  - The system automatically generates a **Comparison Delta** (e.g., Class Mock Passing Rate $82\%$ vs National PRC Average $74.5\% \rightarrow +7.5\%$ Delta).

### F. AI Engine & Grounding Validation Pipeline (Cloudflare Workers AI)
The system uses **Cloudflare Workers AI** (`App\Services\CloudflareAI`) configured via `config/services.php`:

1. **AI Quiz Generation (`prompt.quiz_generation`):**
   - Automatically generates multiple-choice questions directly from uploaded lecture PDFs or DOCX files.
   - Strips PDF noise, watermark text, and header artifacts prior to LLM processing.
2. **AI Student Insights (`prompt.quiz_insights`):**
   - Analyzes student answers after an attempt and produces:
     - **Strong Areas:** Topics/questions mastered.
     - **Weak Areas:** Concepts where the student missed key questions.
     - **Recommendation:** Targeted study advice.
3. **Class Performance Summary (`prompt.class_summary`):**
   - Synthesizes class averages, passing distributions, and weak competencies for the instructor.
4. **Rule-Based Post-Validation Heuristics (`AiQuizContentValidationTest`):**
   - *Stem Echo Rejection:* Discards options that repeat question stems.
   - *Deduplication:* Semantic distance checks prevent duplicate questions within and across batches.
   - *Grounding Evidence Check:* Every question must cite verifiable evidence from the source lecture text.
   - *Threshold Guard:* If generated question quality falls below acceptance threshold, existing questions are preserved.

### G. Test Bank Management System
- Teachers can store, tag, and categorize high-performing questions.
- Supports **Import from Existing Quizzes** and **Snapshot Copying** into formal exam modules.

### H. Granular Module Visibility Controls (`All`, `Selected`, `Except`)
- **`All`:** Visible to all enrolled students.
- **`Selected`:** Visible only to specific assigned student IDs (ideal for remediation modules).
- **`Except`:** Visible to all students except excluded IDs (ideal for exemption modules).

### I. Admin Approvals & User Role Lifecycle
- **Registration Flow:** New signups land in `status = 'pending'`.
- **Approval Queue:** Admins approve/reject users with assigned programs (`psych`, `educ`, `accountancy`).
- **Deactivation with Reason:** Admins can deactivate accounts with mandatory reason logs for auditability.
- **AI Key Delegation:** Superadmin manages global Cloudflare AI keys; Admins can configure class-level overrides.

---

## 5. Database Architecture & Key Models

| Model | Table | Purpose |
| :--- | :--- | :--- |
| `User` | `users` | Accounts, roles (`student`, `teacher`, `admin`, `superadmin`), programs, status (`active`, `pending`, `rejected`, `deactivated`). |
| `ClassModel` | `classes` | Review classes created by teachers, tagged by program, year level, and school year. |
| `Module` | `modules` | Lectures, pre-tests, post-tests, quizzes, passing grades, attempt limits, and visibility. |
| `QuizQuestion` | `quiz_questions` | Question text, JSON options, correct option, points, domain, explanation, and test bank linkage. |
| `QuizAttempt` | `quiz_attempts` | Student quiz/assessment attempts, scores, time spent, status, and AI insights. |
| `QuizAnswer` | `quiz_answers` | Individual question responses per attempt with correctness and normalized option. |
| `MockBoard` | `mock_boards` | Mock board exam containers linked to programs and historical PRC exam results. |
| `MockBoardPhase` | `mock_board_phases` | Sequential phases of mock board (Pre-Test, Post-Test 1, Post-Test 2). |
| `MockBoardAttempt` | `mock_board_attempts` | Multi-phase student attempts, best score tracking, and passing likelihood ratings. |
| `TestBankQuestion` | `test_bank_questions` | Reusable master question repository with topic and difficulty tags. |
| `HistoricalBoardExamResult` | `historical_board_exam_results` | Official PRC board exam benchmarks by program and year. |
| `Announcement` | `announcements` | Class announcements with auto-unpinning of previous posts. |
| `AiSetting` | `ai_settings` | Global and class-level Cloudflare Workers AI configurations and prompts. |

---

## 6. Complete Step-by-Step Guide: New GitHub Repo to New Railway Deployment

### Step 1: Duplicate Folder & Link to New GitHub Repository
1. Copy your entire project folder to a new location (e.g. `C:\Users\...\revisonewtwo\myproject-duplicate`).
2. Create a new repository on GitHub (e.g., `https://github.com/YOUR_USERNAME/reviso-v2.git`). Do NOT check "Initialize with README".
3. Open a terminal inside the **new duplicate folder** and run:
   ```bash
   # 1. Remove link to old repository to protect original frozen project
   git remote remove origin

   # 2. Link to your brand new GitHub repo
   git remote add origin https://github.com/YOUR_USERNAME/reviso-v2.git

   # 3. Commit and push all files to new GitHub repo
   git add .
   git commit -m "Initial commit: Reviso V2 duplicate repository"
   git branch -M main
   git push -u origin main
   ```

---

### Step 2: Create a New Project on Railway
1. Log in to [Railway.app](https://railway.app).
2. Click **+ New Project**.
3. Select **Provision MySQL** *(this creates an isolated, dedicated database for the duplicate)*.

---

### Step 3: Connect the New GitHub Repo to Railway
1. Inside the same Railway project dashboard, click **+ Create** or **+ New** (top right).
2. Select **GitHub Repo**.
3. Choose your new repository (`reviso-v2`).
   *(Note: If the repo doesn't appear in the list, click "Configure GitHub App" to grant Railway access to the new repo).*

---

### Step 4: Configure Railway Environment Variables
1. Click the **Web Service box** for your GitHub repo in Railway.
2. Navigate to the **Variables** tab $\rightarrow$ click **Raw Editor**.
3. Paste the following configuration:

```env
APP_NAME=Reviso
APP_ENV=production
APP_KEY=base64:UprY6s7ooVG+jT5QR9D60tSUGhqcwXkZXxdOCUjaYQM=
APP_DEBUG=false
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}
APP_TIMEZONE=Asia/Manila
APP_LOCALE=en

# Database (Automatically pulls from the provisioned Railway MySQL service)
DB_CONNECTION=mysql
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}

SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=sync

# Cloudflare Workers AI Credentials (Required for AI Quiz & Insights Generation)
CLOUDFLARE_ACCOUNT_ID=84753a3f8d0b1a36c7331cd95b48fc7c
CLOUDFLARE_API_TOKEN=KV1CZKUaPZ-wLbPldwJzr-ar20yElWTJTR6OzpxL
CLOUDFLARE_AI_GATEWAY=
```

---

### Step 5: Generate Public Production URL (Domain)
1. In the Web Service settings, go to the **Settings** tab.
2. Scroll down to the **Networking** section and click **Generate Domain**.
3. Railway will generate a live HTTPS URL (e.g., `https://reviso-v2-production.up.railway.app`).

---

### Step 6: Automatic Build, Migration & Seeding Execution
Railway automatically detects `railway.json` and builds via `Dockerfile`. 
The `docker/entrypoint.sh` startup script will automatically execute:
1. Dynamic Nginx port binding to Railway's assigned `$PORT`.
2. Environment file generation (`docker/generate-env.php`).
3. Storage symlinking (`php artisan storage:link`).
4. Automated database migrations (`php artisan migrate --force`).
5. Seed predetermined default accounts (`AdminSeeder`, `TeacherDemoSeeder`).
6. Production optimization caching (`config:cache`, `route:cache`, `view:cache`).
7. Supervisord daemon initialization (running PHP-FPM and Nginx simultaneously).

Once the deployment shows a green checkmark, open the generated domain to start using the duplicate instance!

---

## 7. Environment Variables Reference (`.env`)

| Variable | Description | Recommended Production Value |
| :--- | :--- | :--- |
| `APP_NAME` | Name of application | `Reviso` |
| `APP_ENV` | Environment mode | `production` |
| `APP_KEY` | Laravel 32-character encryption key | Base64 key or `php artisan key:generate` |
| `APP_DEBUG` | Detailed debug error pages | `false` |
| `APP_URL` | Public production URL | `https://${{RAILWAY_PUBLIC_DOMAIN}}` |
| `DB_CONNECTION` | Database driver | `mysql` |
| `DB_HOST` | Database host | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | Database port | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | Database name | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | Database username | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | Database password | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` | Session storage driver | `database` |
| `CLOUDFLARE_ACCOUNT_ID` | Cloudflare Account ID | Your Cloudflare ID |
| `CLOUDFLARE_API_TOKEN` | Cloudflare Workers AI Token | Your API Bearer Token |
| `CLOUDFLARE_AI_GATEWAY` | Cloudflare AI Gateway Name | Optional gateway slug |

---

## 8. Testing & Quality Assurance Suite

Whenever new features or updates are added in the duplicated codebase, run the test suites to guarantee zero regression:

### 1. Backend PHPUnit Suite (175 Tests / 625 Assertions)
```powershell
# Run entire test suite in clean compact mode
php artisan test --compact

# Run Unit tests (AI content validation, document slicing)
php artisan test --testsuite=Unit

# Run Feature tests (Role flows, assessment locks, mock boards)
php artisan test --testsuite=Feature

# Run a specific test filter
php artisan test --filter=FormalAssessmentLectureLockTest
```

### 2. Playwright Browser E2E Runner (14 Real Browser Scenarios)
```powershell
# Run the automated Playwright E2E interactive browser runner
node scratch/e2e_runner.mjs
```
*(Executes real Chromium browser navigation across Student, Teacher, and Admin portals; automatically cleans up with 0 leftover test data).*

---

## 9. Important Rules & Guardrails for the New Chat/Project

When working in the new chat session with the duplicate repository:

1. **Preserve Tested Business Logic:** Do not delete or dismantle the Pre-Test/Post-Test lecture lockout, multi-post test best score calculations, or AI grounding filters unless explicitly requested.
2. **Database Migrations:** Never modify past migration files directly if the production database is already migrated; create new incremental migrations (`php artisan make:migration ...`).
3. **Pint Code Formatter:** Run `vendor/bin/pint --dirty --format agent` after editing PHP files to maintain clean PSR-12 code styling.
4. **Form Requests & Validation:** Always use dedicated Form Request classes for validation rather than inline controller validation.
5. **Freeze Verification on Original Repo:** Keep the current repo folder (`revisonewtwo/myproject`) completely frozen as-is for the panelist demo. All new experiments and feature changes should take place strictly in the new duplicated folder.

---
*End of Master Handover Document.*
