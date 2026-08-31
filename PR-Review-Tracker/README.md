# PR Review Tracker

Automated Pull Request Review Monitoring System.

A web dashboard that helps software teams track pull request (PR) review status across
GitHub repositories. It fetches open PRs live from the GitHub API, highlights **stale**
and **blocked** PRs, and records reviewer decisions (approve / request changes / reject)
with comments. Built with PHP + MySQL and multi-user roles.

> In 2026 AI makes code generation fast, but code review queues have become the #1
> bottleneck in software delivery. This project gives teams visibility into where
> reviews are stuck, who is blocking them, and which PRs have been open too long.

## Features

- Role-based login: **Admin**, **Reviewer**, **Developer**
- Live GitHub API sync of open pull requests (per-repo token or public repos)
- Dashboard KPIs: open / stale / blocked / closed PRs, reviews given, avg review time
- Stale detection (no activity for > `STALE_DAYS`) and blocked detection (last review = "request changes")
- Review flow: approve / request changes / reject + comment, with full history
- Filters & search: by repository, status, author, keyword
- Admin: manage repositories, tokens, users and roles
- Reports: summary tables + one-click CSV export
- Bonus: GitHub webhook receiver, stale-PR reminder email script

## v2 Features (analytics, automation & polish)

- **Dashboard charts** — live status doughnut + 14-day activity line chart (PRs created vs reviews) via Chart.js
- **"Needs Attention" panel** — blocked + stale PRs surfaced together, with auto-refresh countdown
- **Automatic sync** — dashboard auto-syncs with GitHub when data is stale (toggle via `AUTO_SYNC` in `config.php`)
- **In-app notifications** — bell icon with unread badge + full center; alerts on review activity
- **Dark mode toggle** — persisted per-user, styled for both themes
- **Sortable + paginated PR list** — click headers to sort, page through large datasets
- **PDF export** — print-friendly report in addition to CSV
- **Reports upgrades** — decision breakdown chart + recent review activity feed
- **GitHub review write-back** — when a repo has a write-scope token, review decisions are also pushed to GitHub
- **Hardening** — login brute-force throttling, charset/fetch pagination, CRSF on all new forms
- `php migrate.php` adds the new `notifications` table + `users.theme` column (idempotent, safe to re-run)

## Tech Stack

| Layer      | Technology                                    |
|------------|-----------------------------------------------|
| Frontend   | HTML5, CSS3, Bootstrap 5 (CDN), vanilla JS    |
| Backend    | PHP 8+ (plain PHP, no framework)              |
| Database   | MySQL / MariaDB                               |
| API        | GitHub REST API via cURL                      |
| Auth       | PHP sessions, `password_hash()`/`password_verify()`, CSRF tokens |

## Project Structure

```
PR-Review-Tracker/
├── config.php            # DB credentials + app settings
├── db.sql                # Database schema + default admin user
├── seed_demo.php         # Optional demo data (sample PRs + reviewer)
├── run.sh                # One-command launcher (DB + PHP server)
├── Dockerfile            # Container build (Render / Railway)
├── docker-entrypoint.sh  # Container entrypoint: MariaDB + Apache in one
├── render.yaml           # Render Blueprint config
├── db_start.sh / db_stop.sh   # Start/stop the project-local MariaDB
├── login.php register.php logout.php
├── index.php             # Dashboard
├── prs.php               # PR list + filters + search
├── pr_view.php           # PR detail + review form + history
├── review.php            # Review submission handler
├── repos.php             # Admin: manage repositories
├── sync.php              # Trigger GitHub sync
├── reports.php           # Reports + CSV export
├── users.php             # Admin: manage users & roles
├── includes/             # db, auth, functions, github, header/footer
├── api/webhook.php       # GitHub webhook receiver
├── api/reminders.php     # Stale/blocked PR email reminder (CLI)
└── assets/               # style.css, app.js
```

## Quick Start (local)

### Option A — one command (this project ships a self-contained DB)

```bash
cd PR-Review-Tracker
./run.sh
# open http://localhost:8000
```

`run.sh` starts a project-local MariaDB (port 3307) and the PHP dev server.
The first time only, import the schema and demo data:

```bash
./db_start.sh
mariadb --socket=.db/run/mysql.sock -u root < db.sql
PHP_INI_SCAN_DIR="$PWD/.php/conf.d" php seed_demo.php
```

### Option B — XAMPP / WAMP / existing MySQL

1. Import `db.sql` in phpMyAdmin.
2. Edit `config.php`: `DB_HOST='localhost'`, `DB_PORT=3306`, and your DB credentials.
3. Place the folder in `htdocs` and open `http://localhost/PR-Review-Tracker`.
4. Run `php seed_demo.php` (optional demo data).

### Demo logins (after seeding)

| Role     | Email                     | Password   |
|----------|---------------------------|------------|
| Admin    | admin@example.com         | admin123   |
| Reviewer | vikram.reviews@example.com| review123  |

## Adding a real GitHub repository

1. Login as admin → **Repositories** → **Add repository**.
2. Enter a display name and the repo as `owner/repo` (e.g. `octocat/Hello-World`).
3. (Optional) Paste a GitHub **Personal Access Token** with the `repo` scope
   (Settings → Developer settings → Personal access tokens). Public repos work without one.
4. Click **Sync all PRs now** (or the per-repo Sync button).

## GitHub webhook (optional, auto-refresh)

1. GitHub repo → Settings → Webhooks → Add webhook.
2. URL: `https://your-site.com/api/webhook.php`, content type `application/json`,
   events: **Pull requests**.
3. To protect it, add `define('WEBHOOK_SECRET', 'your-secret');` to `config.php`.

## Reminder emails (optional, cron)

```bash
php api/reminders.php
```

Emails reviewers/admins about stale and blocked PRs (uses PHP `mail()`).

## Deployment (free)

### Option A — Render (one-container Docker, live URL in minutes)

The repo ships with a `Dockerfile`, `docker-entrypoint.sh`, and `render.yaml` that run
MariaDB + Apache together in a single free instance. No external DB needed.

1. Push this folder to a GitHub repo (a `.gitignore` keeps `.db/`, `.php/`, `.vscode/` out).
2. [render.com](https://render.com) → **New** → **Blueprint** → connect the repo (or
   **New + Web Service**, pick *Docker* runtime, plan *Free*).
3. With the Blueprint, `render.yaml` is read automatically — just confirm and deploy.
4. When the service is `Live`, open `https://<name>.onrender.com` and log in with the
   seeded demo credentials below.

`config.php` reads `DB_HOST` / `DB_PORT` / `DB_NAME` / `DB_USER` / `DB_PASS` from
environment variables when present, so the same code runs locally (port 3307) and in
the container (port 3306) unchanged.

> Note: Render free instances sleep after ~15 min of inactivity and their disk is
> ephemeral — the DB is rebuilt (schema + demo seed) on each cold start, and GitHub
> sync repopulates real PRs. Good for demos; use a managed DB for production.

### Option B — shared PHP host (InfinityFree / 000webhost)

1. Upload all files (except `.db/`, `.php/`, `.vscode/`).
2. Create a MySQL DB in the panel, import `db.sql` via phpMyAdmin.
3. Edit `config.php`: `DB_HOST`, `DB_PORT` (3306), `DB_USER`, `DB_PASS`.
4. Set `BASE_URL` if the app lives in a subfolder (e.g. `define('BASE_URL', '/pr-review-tracker');`).

### Option C — local / LAN demo

- `./run.sh` then open `http://localhost:8000`.

## Demo script (5 minutes)

1. Login as **admin** → Repositories → **Sync all PRs now** → PRs appear.
2. Dashboard → point at **Stale** / **Blocked** badges.
3. Open a stale PR → as **reviewer**, click **Request changes** + comment.
4. Go back to dashboard → PR now shows as **Blocked**.
5. Reports → **Export CSV** → open the file.

## Security notes

- Passwords hashed with bcrypt; sessions with role checks; PDO prepared statements.
- GitHub tokens are stored in the DB and never printed in the UI.
- All forms are CSRF-protected.
