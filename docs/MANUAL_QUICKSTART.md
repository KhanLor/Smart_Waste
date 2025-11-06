## Smart Waste — Quick Manual

This short manual describes the basic manual processes for the three main roles: Authority, Resident, and Collector. Keep it concise and actionable.

### Authority (Admin)
- Purpose: manage users, schedules, reports, and system settings.
- Access: log in to the Admin/Authority dashboard (`dashboard/admin/` or `dashboard/authority/`).
- Common tasks:
  1. Review and approve new user registrations and collector accounts.
  2. Create or update collection schedules (use `create_schedule` / `update_schedule` pages in the dashboard).
  3. Monitor reports and mark them resolved or assign follow-up tasks.
  4. View collector statistics and history to evaluate performance.
  5. Manage push notification templates and VAPID/Pusher settings when needed.
- Notifications: check the notifications area regularly for unread report alerts and schedule issues.

### Resident
- Purpose: report problems, view collection schedules, and receive notifications.
- Access: register or log in at the public site (`register.php` / `login.php`). Use the resident dashboard (`dashboard/resident/`).
- Common tasks:
  1. Report an issue: submit a new report with description and photos (use the report form or `send_message.php` / `upload_evidence` endpoints).
  2. View upcoming schedules: check the schedules page to confirm pickup dates and times.
  3. Manage profile: update contact details and profile image in the profile section.
  4. Reset password or verify email using `forgot_password.php` / `verify_email.php` when needed.
- Notifications: enable push notifications in the browser (site prompts) to receive schedule and report updates.

### Collector
- Purpose: perform pickups, update task status, and upload evidence (photo proof).
- Access: log in to the Collector dashboard (`dashboard/collector/` or collector mobile view).
- Common tasks:
  1. View assigned tasks for the day (open `get_collector_tasks.php` or collector dashboard tasks list).
  2. Update task status: mark tasks as completed, in-progress, or failed via `update_task_status.php`.
  3. Upload evidence: attach photos or files when completing a task using `upload_evidence.php`.
  4. Update location: send live or periodic location updates with `update_collector_location.php` to help routing.
- Notifications: collectors receive schedule notifications and task assignments—monitor the notifications panel.

### Quick troubleshooting & tips
- If emails fail, check SMTP credentials in `config/config.php` and the configured app password.
- If web push fails, verify VAPID keys and the service worker (`sw.js`) registration in the browser.
- Database issues: use `check_db.php` and `fix_database.php` to diagnose and repair common problems.

### Contact / Escalation
- For system-level problems, Authority users should contact the system administrator and provide error logs or screenshots.
- For unclear reports, collectors should mark them for follow-up and notify the Authority.

---
This document is intentionally brief. If you want a longer step-by-step guide (with screenshots, exact dashboard paths, or API mappings), tell me which role to expand first.
