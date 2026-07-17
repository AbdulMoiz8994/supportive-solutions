# BeydounTech — Engineering Handoff

> Orientation doc for anyone (human or AI) picking up this codebase. It is deliberately
> **short and high-signal** — pointers, not a copy of the code. When a detail here and the
> code disagree, the code wins; fix this doc. Last meaningful update: **2026-07-11**.

## What this is
A Laravel 12 / PHP 8.2 back-office EMR + CRM for **Supportive Solutions Home Care (SSHC)** —
a home-care agency. It handles clients (patients), caregivers (employees), authorizations,
compliance, scheduling/visits, billing/claims, and payroll. A **separate Flutter mobile app**
(different developer) is the caregiver-facing clock-in / chat / pay app; **this platform is the
source of truth** and exposes a Sanctum-token REST + WebSocket API to it (see `docs/MOBILE_API.md`).

## Stack
- **Laravel 12**, PHP 8.2, Blade + Tailwind/Alpine for the web UI.
- **Auth:** session + 2FA for web; **Laravel Sanctum** bearer tokens for the mobile API.
- **DB:** SQLite locally (`database/database.sqlite`), MySQL in staging/prod.
- **Queue/cache:** `sync` / `file` locally.
- **Real-time:** **Laravel Reverb** (Pusher-protocol WebSockets) for live chat.
- **LLM:** Anthropic Claude (`claude-sonnet-4-6`) for ID-OCR / document recognition / assistant.
- **External integrations:** HHAeXchange (EVV), Availity, Google Workspace (Gmail), Google Maps,
  RingCentral (click-to-call). See the `[[integration-stack]]` notes / `docs/` for specifics.

## Branch & deploy workflow (IMPORTANT)
- `master` = **live/production**. Never push to it directly.
- Do all work on **`umair`**. Before starting, fast-forward `umair` from `origin/master`.
- Ship by opening a **PR `umair` → `master`**; merging triggers CI/CD to production.
- Module ownership: **umair** owns Client / Caregiver / Authorization / Compliance /
  Background-checks / the **mobile API**. A co-dev owns Visit Reports / Tasks / Dashboard /
  Billing / Workflow / Staff / Forms / Calendar. Stay in your lane unless coordinated.

## Where things live
- HTTP: `app/Http/Controllers/` (mobile API under `.../Api/`, 17 controllers), routes in
  `routes/web.php`, `routes/api.php`, private-channel auth in `routes/channels.php`.
- Domain: `app/Models/`, business logic in `app/Services/`, events in `app/Events/`.
- Broadcasting/real-time config: `config/reverb.php`, `config/broadcasting.php`,
  `bootstrap/app.php` (`withBroadcasting` under `auth:sanctum`).
- Tests: `tests/Feature/**` (Pest). Mobile API suite: `tests/Feature/Api/`.
- Env templates: `.env.example` (local), `.env.staging.example` (staging/prod).

## Running & testing locally
```bash
# Web app — use php -S with the router AND -t public (artisan serve has quirks here).
# The -t public is REQUIRED or /build/* Vite assets 404 and Alpine never loads:
php -S 127.0.0.1:8000 -t public server.php

# Real-time chat socket (Pusher-protocol / Reverb):
php artisan reverb:start --host=127.0.0.1 --port=8080 --debug

# Seed test data (creates the two chat test caregivers, below):
php artisan db:seed

# Tests:
php artisan test                       # full suite
php artisan test tests/Feature/Api     # mobile API only
```
**Test logins (seeded):** `caregiver@beydountech.com` / `care123` and a 2nd account
`caregiver2@beydountech.com` / `care123` for two-sided chat. Web admin logins are in the seeders.

## Real-time chat — current state (the thing the mobile dev keeps hitting)
- **Backend is stable and verified end-to-end** (connect → authorize private channel →
  send via REST → receive `message.sent` live). It does **not** cause a reconnect loop with the
  correct app key.
- The mobile app's `connecting → disconnected → reconnecting` **loop is a config mismatch**, not
  a server bug: Reverb closes the socket with **`4001` "Application does not exist"** when the
  client's app key (or host) doesn't match the server's `REVERB_APP_KEY`. See the
  **🛟 Debugging** section in `docs/MOBILE_API.md`.
- **Self-check endpoint:** `GET /api/realtime/config` (Sanctum-authed) returns the exact
  key/host/port/scheme the server expects so the client config can be compared field-by-field.
  It also reports `enabled:false` when an environment is still on the `log` broadcaster.
- **Prod turn-on is a ONE-TIME MANUAL SERVER TASK — not done by merging.** The deploy pipeline
  (`.github/workflows/deploy.yml`) explicitly excludes `.env` (`-x ".env"`) and doesn't manage
  daemons, so the production `.env` is a standalone file on the server
  (`/var/www/vhosts/beydountech.com/.env`, Plesk) that merges never touch. To go live, someone with
  SSH/Plesk access must once: (1) add the `REVERB_*` block + `BROADCAST_CONNECTION=reverb` to that
  `.env`, (2) run `php artisan reverb:start` under Supervisor/systemd, (3) add the nginx `/app/`
  websocket proxy to `:8080`. Full step-by-step (with Supervisor + Plesk nginx config) is in
  **`docs/REVERB_DEPLOY.md`** — hand that to the server admin. Give the mobile team only the
  **app key + host + port** — never `REVERB_APP_SECRET`.

## A note on this doc vs. AI memory
For AI sessions, cross-chat continuity is primarily kept in Claude Code's per-project **memory**
(auto-loaded each session), which is more current than any checked-in doc. This `HANDOFF.md`
exists for the **human team** and for fast first-load orientation — keep it to durable facts
(architecture, workflow, how-to-run) and let per-feature detail live in `docs/` + the code.

## Current work state — pick up here (updated per session)
> This section is the "where were we" log. Newest first. Keep entries short; move durable
> facts up into the sections above once they stop being "current".

### 2026-07-13 — Authorization data-entry & save bugs (umair scope) — FIXED locally, shipping
Source: the client's **"Data-Entry & Save Deep-Test (Jul 10)"** report. All four items are in
the Authorization module (umair's lane). Fixed and verified:
- **#1/#3 Authorization Details edit didn't persist.** The panel used `<x-clients.edit-panel>`
  with **no `:action`** and fields with **no `name`** — so "Save" only closed the panel (JS-only,
  never POSTed). Wired it to a new endpoint and gave the editable fields real names.
  - New route `PUT /clients/{id}/care-details/{careDetail}` → `ClientController@updateCareDetail`
    (+ `UpdateClientCareDetailRequest`). It recomputes `hours_per_week = total_units/4`, so
    **"Weekly average" and "Units remaining" recompute on reload** (that was #3).
  - View: [resources/views/pages/clients/tabs/authorization.blade.php](../resources/views/pages/clients/tabs/authorization.blade.php)
    — the "Authorization Details" panel now has `:action` + `name="billing_code|total_units|start_date|end_date"`.
- **#2 Agency "Log authorization" was a dead link** (pointed at `/clients`). Now a **client-picker
  modal** ([pages/authorizations/index.blade.php](../resources/views/pages/authorizations/index.blade.php),
  Alpine `logAuthOpen`/`logAuthClient` on `authRegistry`) → `clients/{id}?tab=authorization&add_auth=1`.
- **#4 "Upload new PA" did nothing.** Relabeled to **"Add authorization"**; opens a modal that
  POSTs to the existing `clients.care-details.store`. Auto-opens when `?add_auth=1` is present
  (that's how the picker lands you there).
- **Tests:** `tests/Feature/Clients/CareDetailHoursTest.php` (+2 update/persist + cross-org tests, 10 pass).
- **Browser-verified** end-to-end via `tests/e2e/authorization-save.cjs` on client #1 (the 112-unit
  record the tester used): edit 112→120 → "Changes saved" → reload shows 120. 5/5 checks pass.
- **Follow-up (NOT mine / separate):** the client *Documents* tab throws console `pageerror`s
  (`Cannot read properties of null (reading 'document_type'|'confidence'|'summary'|'suggested_status')`)
  — the AI document-scan Alpine component initialising with null. Pre-existing, unrelated to these
  fixes; worth a separate look.
- **Still to run the save-persistence checklist on** (report's list, umair scope): Compliance
  document upload, Caregiver profile edit, Caregiver Assignment, each remaining client tab.
- **Next step:** PR `umair` → `master`, then **re-test the same loop on live** (beydountech.com)
  before handing the client the updated build.

> ⚠️ **Local dev gotcha (cost me a while):** start the server as
> `php -S 127.0.0.1:8000 -t public server.php` — **with `-t public`**. Without it, `/build/*`
> Vite assets 404, Alpine never loads, and every `x-show`/modal/inline-edit looks "broken" in a
> headless browser even though the server-side code is fine. Also `php artisan cache:clear` if the
> 2FA "Send code" silently bounces back to the choice screen (resend rate-limit is cache-based and
> the choice view doesn't render the session error).

## Key docs
- `docs/MOBILE_API.md` — full mobile REST + WebSocket contract (the mobile dev's primary reference).
- `docs/REVERB_DEPLOY.md` — one-time production Reverb setup runbook (hand to the server admin).
- `docs/BILLING_CLAIMS_AUDIT_FIELD_MAPPING.md` — billing/claims field mapping.
