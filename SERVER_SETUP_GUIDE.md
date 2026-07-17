# Production Server Setup & Update Guide
## Beydoun Tech EMR/CRM

This guide provides step-by-step instructions for deploying or updating the Beydoun Tech EMR/CRM system on a production server (e.g., cPanel, VPS).

---

### 1. Environment Configuration
Ensure your `.env` file is properly configured on the server. Important fields include:

- **APP_ENV**: Should be set to `production`.
- **APP_DEBUG**: Should be set to `false`.
- **APP_URL**: Your live website URL (e.g., `https://yourdomain.com`).
- **DB_DATABASE/USERNAME/PASSWORD**: Your live database credentials.

> [!IMPORTANT]
> Never share your `.env` file or database credentials with unauthorized personnel.

---

### 2. Database Synchronization (Migration)
Whenever new features are pushed to the server, you must sync the database structure to ensure the application doesn't crash.

**Command:**
```bash
php artisan migrate
```
*This will create any new tables or columns required by the latest update.*

---

### 3. Population of Initial Data (Seeding)
To populate the database with default values (like lookup labels, roles, and admin users) or dummy testing data:

**Command:**
```bash
php artisan db:seed
```
*Note: Our system uses `updateOrCreate` logic, so running this command multiple times is safe and will not create duplicate records.*

---

### 4. File Storage Link
If you are uploading documents or images, you must ensure the public storage link is active.

**Command:**
```bash
php artisan storage:link
```

---

### 5. Production Optimization
To ensure the best performance and avoid configuration errors, run the optimization command after every update.

**Command:**
```bash
php artisan optimize
```
*This command clears and caches your configurations, routes, and views for faster loading.*

---

### 6. Frontend Assets (Vite)
If you notice the design (CSS/Icons) is not loading correctly after an update, you may need to rebuild the frontend assets.

**Commands:**
```bash
npm install
npm run build
```

---

### 7. Troubleshooting & Logs
If you encounter a "500 Internal Server Error," check the Laravel logs to identify the issue.

**Log Location:** `storage/logs/laravel.log`

---

### How to run these commands in cPanel:
1. Log in to your **cPanel**.
2. Search for and open the **Terminal** tool.
3. Navigate to your project directory (e.g., `cd public_html`).
4. Type the commands listed above and press **Enter**.

---

### 8. Production Security — Demo Users & Passwords

`php artisan db:seed` creates **demo accounts** for development and staging QA. These must **not** remain unchanged in production.

| Email | Role | Default password (change immediately) |
|-------|------|-------------------------------------|
| `super@beydountech.com` | Super Administrator | `super123` |
| `admin@beydountech.com` | Administrator | `admin123` |
| `staff@beydountech.com` | Operations Staff | `staff123` |
| `caregiver@beydountech.com` | Employee (caregiver) | `care123` |

**Before go-live you must either:**

1. **Change passwords** for every seeded account (My Staff → profile, or direct DB update with bcrypt hashes), **or**
2. **Deactivate or delete** demo users (`is_active = false` or remove rows) and create real staff via the invite flow.

> [!WARNING]
> Never deploy to production with default seeded passwords. Treat any leaked demo credential as a security incident.

Also set `APP_ENV=production`, `APP_DEBUG=false`, and `DEMO_ROUTES_ENABLED=false`.

---

### 9. Staging QA — Mail-Dependent Flows

Several features send email. Configure `MAIL_*` in `.env` before QA (Mailpit locally; SMTP on staging).

| Flow | Trigger | Route / UI | What to verify |
|------|---------|------------|----------------|
| **2FA OTP** | Login when 2FA required | `/two-factor/*` | OTP email arrives; code verifies session (`2fa_verified`) |
| **Staff invite / setup account** | Add staff in My Staff | `POST /staff` → email with `/setup-account?email=&token=` | Invite link sets password and activates user |
| **Password reset (self-service)** | Forgot password | `/forgot-password` | Reset link email; `/reset-password/{token}` completes reset |
| **Password reset (admin)** | Staff profile → Send reset | `POST /staff/{id}/reset-password` | Same Laravel reset email to staff member |

**Staging tips:**

- With `APP_DEBUG=true`, staff create may flash `debug_setup_url` in session for invite QA without mail.
- Confirm `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` match your domain to reduce spam filtering.
- After mail QA, test with `security.require_2fa` enabled in Global Settings if enforcing org-wide 2FA.

---

### 10. Leads vs Intakes — Intended Workflow

| Path | Status | Purpose |
|------|--------|---------|
| **`/intakes`** | **Primary — live module** | Full client intake pipeline: create, assess, convert to client, documents, call logs |
| **`/leads`** | Legacy / parallel | Simpler lead list CRUD; kept for BRD parity, **not** linked from the main menu |

**Navigation:** Sidebar **Intake** and Dashboard sub-item **Client Intake** both point to `/intakes`. Use Intakes for staging demos and production intake work. Leads consolidation is planned for P7+.

---

### 11. Placeholder (“Coming Soon”) Modules

Routes that render `pages.placeholders.coming-soon` are **not** production-ready. They display a “Coming Soon — Placeholder Module” screen and exist for BRD menu parity only.

Examples: `/work-shifts`, `/marketing`, `/events`, and most `/clients/*` sub-nav paths (`/clients/create`, `/clients/documents`, etc.).

**Implemented dashboard modules** (no longer placeholders): `/visit-reports`, `/tasks`, `/forms`, `/dashboard/forms` (redirects to `/forms`), `/exploration`, `/data-exploration`.

**Completed modules** (use these for QA): Dashboard, Clients (list/show/edit), Intakes, Schedule, Billing, Compliance, Documents, Directory, My Staff, Employees, Caregivers, Messages, Calendar, Audit Trail (`/audit-view`), Global Settings (Super Admin).

---
*Generated for Beydoun Tech — updated for pre-staging cleanup (P7 prep)*
