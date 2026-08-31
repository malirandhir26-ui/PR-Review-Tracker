# Software Requirements Specification (SRS)

## PR Review Tracker — Automated Pull Request Review Monitoring System

| | |
|---|---|
| **Project Name** | PR Review Tracker |
| **Prepared For** | Internship Final Project Submission |
| **Technology** | PHP 8, MySQL/MariaDB, Bootstrap 5, GitHub REST API |
| **Document Standard** | IEEE Std 830-1998 (Software Requirements Specification) |
| **Version** | 1.0 |
| **Status** | Final |

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Overall Description](#2-overall-description)
3. [External Interface Requirements](#3-external-interface-requirements)
4. [System Features (Functional Requirements)](#4-system-features-functional-requirements)
5. [Non-Functional Requirements](#5-non-functional-requirements)
6. [Data Requirements](#6-data-requirements)
7. [Constraints and Assumptions](#7-constraints-and-assumptions)
8. [Future Enhancements](#8-future-enhancements)
9. [Appendices](#9-appendices)

---

## 1. Introduction

### 1.1 Purpose

This document specifies the complete functional and non-functional requirements for the **PR Review Tracker**, a web application that monitors and manages the pull request (PR) review workflow of software teams. The system connects to the GitHub API, fetches open pull requests in real time, tracks their review status, flags stale and blocked PRs, and records reviewer decisions.

### 1.2 Document Conventions

- **MUST / SHALL** — mandatory requirement.
- **SHOULD** — recommended, but not mandatory.
- **MAY** — optional requirement.
- Requirement identifiers follow the format `FR-XX` (Functional Requirement), `NFR-XX` (Non-Functional Requirement), `IR-XX` (Interface Requirement), `DR-XX` (Data Requirement).

### 1.3 Intended Audience

| Audience | Use of Document |
|---|---|
| Project Supervisor / Mentor | Verification of scope and completeness |
| Evaluators / Examination Panel | Assessment against stated requirements |
| Developers | Implementation reference |
| Testers | Test case derivation |

### 1.4 Product Scope

The product is a **monitoring dashboard** (not a code review tool itself). It provides:
- Real-time visibility of open pull requests across multiple GitHub repositories.
- Automated detection of **stale** PRs (no activity for a configurable period) and **blocked** PRs (latest review requested changes).
- A workflow for reviewers to record decisions (approve / request changes / reject) with comments.
- Team analytics: open/stale/blocked/closed counts, average review time, per-reviewer and per-repository statistics.
- Export of reports to CSV.

The product solves the 2026 industry problem where AI-generated code accelerates development but the code review queue becomes the bottleneck: PRs wait unreviewed, releases are delayed, and technical debt accumulates.

### 1.5 References

| Reference | Source |
|---|---|
| GitHub REST API Documentation | https://docs.github.com/en/rest |
| PHP Manual (PDO, Sessions, password_hash) | https://www.php.net/manual |
| MySQL / MariaDB Documentation | https://mariadb.com/docs |
| Bootstrap 5 Documentation | https://getbootstrap.com/docs/5.3 |
| IEEE 830-1998 SRS Standard | IEEE Software Requirements Specifications |

### 1.6 Definitions, Acronyms, and Abbreviations

| Term | Definition |
|---|---|
| PR / Pull Request | A proposal to merge code changes into a repository branch |
| Reviewer | A user who evaluates PRs and records a decision |
| Developer | A user who authors PRs and tracks their status |
| Admin | A user who manages repositories, users, and system configuration |
| Stale PR | An open PR with no activity for more than `STALE_DAYS` |
| Blocked PR | An open PR whose latest review decision is "request changes" |
| PAT | Personal Access Token (GitHub) |
| CSRF | Cross-Site Request Forgery |
| SRS | Software Requirements Specification |
| KPI | Key Performance Indicator |

---

## 2. Overall Description

### 2.1 Product Perspective

PR Review Tracker is a **standalone web application**. It is a new, self-contained product that depends on the following external systems:

- **GitHub API** — source of pull request data (primary integration).
- **MySQL/MariaDB** — persistent data storage.
- **Web browser** — client interface.
- **SMTP/mail service** — for reminder emails (optional).

### 2.2 Product Functions (Summary)

| Module | Major Functions |
|---|---|
| Authentication | Register, login, logout, role-based access control |
| Repository Management | Add/delete repositories, store GitHub tokens, trigger sync |
| PR Syncing | Fetch open PRs from GitHub API, update/close PRs, deduplicate |
| Dashboard | KPI cards, stale PR list, latest PR list, repository status, "My PRs" |
| PR Tracking | PR list with filters/search, PR detail view, status computation |
| Review Workflow | Record decisions (approve/changes/reject) with comments, history |
| Reports | Summary tables, per-reviewer stats, per-repo stats, CSV export |
| Alerts | Stale/blocked PR email reminders, GitHub webhook auto-refresh |

### 2.3 User Classes and Characteristics

| User Class | Privileges | Skill Level |
|---|---|---|
| **Admin** | All functions: manage repos, tokens, users, roles; full visibility | Technical |
| **Reviewer** | View PRs, submit reviews, access reports | Technical |
| **Developer** | View PRs (own + team), cannot submit reviews | Technical |
| **Guest (unauthenticated)** | No access; redirected to login | — |

### 2.4 Operating Environment

| Component | Requirement |
|---|---|
| Server | PHP 8.0 or higher, with `pdo_mysql` and `curl` extensions |
| Database | MySQL 5.7+ / MariaDB 10.4+ |
| Client | Any modern browser (Chrome, Edge, Firefox, Safari) |
| OS | Linux / Windows / macOS (XAMPP, WAMP, LAMP, or PHP built-in server) |
| Network | Internet access required for GitHub API sync |

### 2.5 Design and Implementation Constraints

- **C-1:** Implemented in plain PHP (no framework) using procedural/functional style.
- **C-2:** All database access MUST use PDO prepared statements (SQL injection prevention).
- **C-3:** Passwords MUST be hashed using `password_hash()` (bcrypt).
- **C-4:** All state-changing forms MUST be protected by CSRF tokens.
- **C-5:** GitHub credentials MUST be stored in the database, never hard-coded or logged.
- **C-6:** The application SHALL degrade gracefully when the GitHub API is unreachable.

### 2.6 Assumptions and Dependencies

- The GitHub account/repository is accessible over the internet.
- Public repositories require no token; private repositories require a valid PAT with the `repo` scope.
- Email delivery depends on the host's `mail()`/SMTP configuration.
- The number of open PRs per repository is assumed to be within GitHub's rate limits (5,000 requests/hour with a token).

---

## 3. External Interface Requirements

### 3.1 User Interfaces

- **IR-01:** The application SHALL render a responsive, browser-based interface using HTML5, CSS3, Bootstrap 5, and minimal vanilla JavaScript.
- **IR-02:** A persistent navigation bar SHALL provide links to Dashboard, Pull Requests, Repositories, and Reports.
- **IR-03:** The user's name and role SHALL be displayed in the navigation bar.
- **IR-04:** All pages SHALL be usable on screen widths from 320px (mobile) to 1920px+ (desktop).
- **IR-05:** Action feedback SHALL be shown via dismissible alert messages (success, error, warning, info).

### 3.2 Hardware Interfaces

- **IR-06:** No direct hardware interfaces. The system requires only a server host with storage for the database and PHP files.

### 3.3 Software Interfaces

- **IR-07:** **GitHub REST API** — the sync module SHALL call `GET /repos/{owner}/{repo}/pulls?state=open` with headers:
  - `User-Agent: PR-Review-Tracker`
  - `Accept: application/vnd.github+json`
  - `Authorization: Bearer <token>` (when a token is configured)
- **IR-08:** **MySQL/MariaDB** — the data layer SHALL connect via PDO with `utf8mb4` charset and exception-based error mode.
- **IR-09:** **Mail (optional)** — the reminder script SHALL use PHP's `mail()` function.
- **IR-10:** **GitHub Webhook (optional)** — `api/webhook.php` SHALL accept JSON POST payloads signed with HMAC-SHA256 (`X-Hub-Signature-256`) when a secret is configured.

### 3.4 Communication Interfaces

- **IR-11:** HTTP/HTTPS over standard ports (80/443) for the web application.
- **IR-12:** Outbound HTTPS (port 443) to `api.github.com` for PR syncing.
- **IR-13:** MySQL over TCP (default 3306) or a Unix socket.

---

## 4. System Features (Functional Requirements)

### 4.1 Authentication and Authorization

| ID | Requirement | Priority |
|---|---|---|
| FR-01 | The system SHALL allow a user to register with name, email, GitHub username, and password. | High |
| FR-02 | The system SHALL reject registration with an invalid email, a password shorter than 6 characters, mismatched confirmation, or a duplicate email. | High |
| FR-03 | The system SHALL authenticate a registered user by email + password using `password_verify()`. | High |
| FR-04 | On successful login, the system SHALL create a session, regenerate the session ID, and redirect to the dashboard. | High |
| FR-05 | The system SHALL allow logout, destroying the session and redirecting to the login page. | High |
| FR-06 | Unauthenticated access to any protected page SHALL redirect to the login page with a warning. | High |
| FR-07 | The system SHALL enforce role-based access: only `admin`/`reviewer` may submit reviews; only `admin` may manage repositories and users. | High |
| FR-08 | Newly registered users SHALL default to the `developer` role. | High |
| FR-09 | The admin SHALL be able to change any other user's role (admin/reviewer/developer). | Medium |
| FR-10 | The admin SHALL be able to delete any user except themselves. | Medium |
| FR-11 | The system SHALL protect every POST form with a CSRF token. | High |

### 4.2 Repository Management (Admin)

| ID | Requirement | Priority |
|---|---|---|
| FR-20 | The admin SHALL add a repository with a display name, GitHub `owner/repo`, and an optional sync token. | High |
| FR-21 | The system SHALL validate that `owner/repo` matches the pattern `[\w.-]+/[\w.-]+`. | High |
| FR-22 | The admin SHALL be able to delete a repository; deleting a repository SHALL cascade-delete its PRs. | High |
| FR-23 | The repository list SHALL show provider, open-PR count, last-sync time, and who added it. | Medium |
| FR-24 | Any logged-in user MAY view the repository list; only admin MAY add/delete. | Medium |
| FR-25 | The system SHALL store the GitHub token in the database and SHALL NOT display it in the UI. | High |

### 4.3 Pull Request Synchronization

| ID | Requirement | Priority |
|---|---|---|
| FR-30 | The system SHALL fetch open PRs for a repository from the GitHub API when sync is triggered. | High |
| FR-31 | A "Sync all" action SHALL sync every tracked repository sequentially and report each result. | High |
| FR-32 | Sync SHALL upsert PRs using the composite key (repo_id, github_pr_number) so no duplicates occur. | High |
| FR-33 | Sync SHALL mark PRs as `closed` when they no longer appear in the open-PR response. | High |
| FR-34 | The system SHALL record `synced_at` on each repository after a successful sync. | Medium |
| FR-35 | On a GitHub API error (HTTP 404/401/403/timeout), the system SHALL show a descriptive message and continue without data loss. | High |
| FR-36 | A GitHub webhook endpoint SHALL re-sync the affected repository automatically on `pull_request` / `pull_request_review` events. | Medium |

### 4.4 Dashboard

| ID | Requirement | Priority |
|---|---|---|
| FR-40 | The dashboard SHALL display KPI cards: Open PRs, Stale, Blocked, Closed, Reviews Given, Avg Review Time. | High |
| FR-41 | The dashboard SHALL list up to 10 stale PRs with PR number, title, repo, author, and last-activity time. | High |
| FR-42 | The dashboard SHALL list the 10 latest PRs with status badge and age. | Medium |
| FR-43 | The dashboard SHALL list all tracked repositories with their last-sync time. | Medium |
| FR-44 | For non-admin users, the dashboard SHALL show "My Pull Requests" filtered by the user's GitHub username. | Medium |

### 4.5 PR Tracking and Status Logic

| ID | Requirement | Priority |
|---|---|---|
| FR-50 | A PR's status SHALL be computed as: **Open** (open, recently active), **Stale** (open, last activity older than `STALE_DAYS`), **Blocked** (open, latest review = "request changes"), or **Closed**. | High |
| FR-51 | `STALE_DAYS` SHALL be a configurable constant (default 2 days). | Medium |
| FR-52 | The PR list SHALL support filtering by repository, by status (all/open/stale/blocked/closed), and keyword search across title, author, and PR number. | High |
| FR-53 | The PR list SHALL show PR number, title, repository, author, status badge, latest review decision, and created time. | High |
| FR-54 | The PR detail page SHALL show full metadata, the current status, a link to GitHub, and complete review history. | High |
| FR-55 | The "blocked" determination SHALL use the most recent review decision. | High |

### 4.6 Review Workflow

| ID | Requirement | Priority |
|---|---|---|
| FR-60 | A reviewer/admin SHALL submit a decision: **Approved**, **Request changes**, or **Rejected**, with an optional comment. | High |
| FR-61 | Only reviewers and admins SHALL be able to submit reviews; developers SHALL see an informational notice instead. | High |
| FR-62 | Reviews SHALL NOT be accepted for closed PRs. | High |
| FR-63 | Every review SHALL record the reviewer, decision, comment, and timestamp. | High |
| FR-64 | The PR detail page SHALL display the full review history chronologically. | High |

### 4.7 Reports

| ID | Requirement | Priority |
|---|---|---|
| FR-70 | The reports page SHALL list all PRs (optionally filtered by status). | Medium |
| FR-71 | The reports page SHALL summarize reviews per reviewer (approved/changes/rejected/total). | Medium |
| FR-72 | The reports page SHALL summarize open/closed PR counts per repository. | Medium |
| FR-73 | The system SHALL export the PR summary as a CSV file downloadable with a timestamped filename. | High |

### 4.8 Alerts and Reminders

| ID | Requirement | Priority |
|---|---|---|
| FR-80 | A CLI script (`api/reminders.php`) SHALL collect stale and blocked PRs and email reviewers/admins a summary. | Low |
| FR-81 | The reminder script SHALL terminate gracefully (with a log line) when there is nothing to report. | Low |

---

## 5. Non-Functional Requirements

### 5.1 Performance

| ID | Requirement |
|---|---|
| NFR-01 | All page loads (except GitHub sync) SHALL respond in under 2 seconds on the reference environment. |
| NFR-02 | The system SHALL display a list of at least 500 PRs without noticeable degradation. |
| NFR-03 | GitHub sync SHALL complete within 30 seconds per repository (or report a timeout). |
| NFR-04 | The application SHALL reuse a single database connection per request (static PDO). |

### 5.2 Security

| ID | Requirement |
|---|---|
| NFR-10 | Passwords SHALL be stored as bcrypt hashes (`password_hash`). |
| NFR-11 | All SQL SHALL use PDO prepared statements; user input SHALL never be concatenated into SQL. |
| NFR-12 | All output SHALL be HTML-escaped (`htmlspecialchars`) to prevent XSS. |
| NFR-13 | Every state-changing request SHALL verify a CSRF token. |
| NFR-14 | GitHub tokens SHALL be stored in the database and never rendered, logged, or sent to the client. |
| NFR-15 | Session IDs SHALL be regenerated on login to prevent session fixation. |
| NFR-16 | Role checks SHALL be enforced server-side on every protected action (not only hidden in the UI). |

### 5.3 Reliability and Availability

| ID | Requirement |
|---|---|
| NFR-20 | The system SHALL continue to function (viewing, filtering, reviewing) even when the GitHub API is unreachable; only sync fails with an error message. |
| NFR-21 | Database schema imports SHALL be idempotent-safe (`IF NOT EXISTS`, `ON DUPLICATE KEY`). |

### 5.4 Usability

| ID | Requirement |
|---|---|
| NFR-30 | The interface SHALL be self-explanatory to a technical user without training. |
| NFR-31 | Statuses SHALL be visually distinguishable by colour-coded badges (green=open, yellow=stale, red=blocked, grey=closed). |
| NFR-32 | All user-facing messages SHALL be clear and actionable (e.g. GitHub error reasons). |

### 5.5 Maintainability

| ID | Requirement |
|---|---|
| NFR-40 | Code SHALL be organised into a clear folder structure (`includes/`, `api/`, `assets/`). |
| NFR-41 | Shared logic SHALL live in `includes/functions.php`, `includes/auth.php`, `includes/github.php`. |
| NFR-42 | Configuration SHALL be centralised in `config.php`. |

### 5.6 Portability

| ID | Requirement |
|---|---|
| NFR-50 | The application SHALL run unchanged on XAMPP/WAMP/LAMP and the PHP built-in server. |
| NFR-51 | Switching environments SHALL require editing only `config.php` (DB host/port/credentials). |
| NFR-52 | A `db.sql` script SHALL create the schema and default admin on any MySQL/MariaDB instance. |

---

## 6. Data Requirements

### 6.1 Data Entities (Logical Model)

| Entity | Purpose | Key Fields |
|---|---|---|
| `users` | System users and roles | id, name, email (unique), role, github_username, password_hash, created_at |
| `repositories` | Tracked GitHub repos | id, name, provider, repo_full_name (unique), owner_id, sync_token, synced_at |
| `pull_requests` | PRs fetched from GitHub | id, repo_id, github_pr_number, title, author, url, state, last_activity_at, created_at |
| `reviews` | Review decisions | id, pr_id, reviewer_id, decision, comment, reviewed_at |

### 6.2 Relationship Rules

- **DR-01:** A repository belongs to exactly one owner (admin); deleting the user deletes their repos.
- **DR-02:** A pull request belongs to exactly one repository; deleting the repo deletes its PRs.
- **DR-03:** A PR is uniquely identified per repository by its GitHub PR number.
- **DR-04:** A review belongs to one PR and one reviewer; deleting a PR or user deletes associated reviews.

### 6.3 Data Integrity

- **DR-10:** `email` SHALL be unique. `repo_full_name` SHALL be unique.
- **DR-11:** The composite key `(repo_id, github_pr_number)` SHALL be unique.
- **DR-12:** `decision` SHALL be restricted to `approved`, `changes`, `rejected`.
- **DR-13:** `role` SHALL be restricted to `admin`, `reviewer`, `developer`.
- **DR-14:** Foreign keys SHALL enforce referential integrity (ON DELETE CASCADE where appropriate).

### 6.4 Retention

- **DR-20:** Closed PRs SHALL remain in the database for reporting and historical reference.
- **DR-21:** Only repositories explicitly deleted by an admin are permanently removed.

---

## 7. Constraints and Assumptions

- **Assumption:** GitHub API availability and rate limits are external and uncontrolled.
- **Assumption:** The demo environment has internet access.
- **Constraint:** No framework may be introduced (plain PHP), per academic scope.
- **Constraint:** The system is a monitoring tool; it does NOT create/merge PRs or push review decisions back to GitHub automatically (reserved for future work).
- **Assumption:** For a given repository, the number of open PRs is within a single API page (≤ 100) or the pagination extension is treated as future work.

---

## 8. Future Enhancements

| ID | Enhancement |
|---|---|
| FE-01 | GitLab API integration (second provider adapter). |
| FE-02 | Write review decisions back to GitHub via the API (auto-approve/reject). |
| FE-03 | Slack/Teams/Telegram notifications for stale PRs. |
| FE-04 | AI-generated PR summaries and review suggestions. |
| FE-05 | Pagination handling for repositories with >100 open PRs. |
| FE-06 | Charts (Chart.js) for trend analysis on the dashboard. |
| FE-07 | Mobile native app (Flutter) wrapping the same backend. |
| FE-08 | OAuth login via GitHub instead of manual credentials. |

---

## 9. Appendices

### 9.1 Acceptance Test Scenarios (Summary)

| Scenario | Steps | Expected Result |
|---|---|---|
| Admin login | Enter admin credentials | Redirect to dashboard, KPIs visible |
| Role guard | Login as developer, open Users page | Redirected to dashboard with permission warning |
| Sync public repo | Admin adds `octocat/Hello-World`, clicks Sync | Open PRs appear in list |
| Duplicate sync | Click Sync twice | No duplicate PR rows |
| Stale detection | Seed PR with old `last_activity_at` | PR shows "Stale" badge |
| Blocked detection | Reviewer submits "request changes" | PR shows "Blocked" badge |
| Review recording | Reviewer adds comment | Comment visible in review history |
| CSV export | Open Reports → Export CSV | File downloads with PR rows |
| SQL injection | Submit `' OR 1=1 --` in search | Treated as literal text, no data leak |
| CSRF | POST without token | Request rejected with 400 |

### 9.2 Requirement Traceability Summary

| Module | Functional IDs |
|---|---|
| Authentication | FR-01 … FR-11 |
| Repositories | FR-20 … FR-25 |
| Sync | FR-30 … FR-36 |
| Dashboard | FR-40 … FR-44 |
| PR Tracking | FR-50 … FR-55 |
| Reviews | FR-60 … FR-64 |
| Reports | FR-70 … FR-73 |
| Reminders | FR-80 … FR-81 |

---

*End of SRS Document*
