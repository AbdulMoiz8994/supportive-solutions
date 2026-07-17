# Caregiver Mobile API

Base URL: `https://beydountech.com/api` · Format: JSON · Header on every request: `Accept: application/json`

**Auth:** login once → get a `token` → send `Authorization: Bearer <token>` on all other calls.
Call `POST /refresh` before the token expires to rotate it without re-entering credentials.
Every endpoint returns the **logged-in caregiver's own data only**.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/login` | get token |
| POST | `/refresh` | rotate token (stay signed in) |
| POST | `/logout` | revoke token |
| GET | `/me` | caregiver profile (Profile tab + home header) |
| GET | `/dashboard` | home screen aggregate ("Welcome Back") |
| GET | `/assignments` | clients assigned to caregiver (My Clients list) |
| POST | `/calls` | place a call to a client ("Call Now") |
| GET | `/calls` | call history |
| GET | `/visits/active` | current open visit (or null) |
| POST | `/visits/clock-in` | start a visit |
| POST | `/visits/clock-out` | end a visit (hours auto-calculated) |
| GET | `/visits` | visit history |
| GET | `/visits/{schedule}/tasks` | care-task checklist for a visit |
| POST | `/visits/{schedule}/tasks` | add task(s) to a visit |
| POST | `/visits/{schedule}/tasks/{task}/toggle` | check/uncheck a task |
| GET | `/schedule` | shifts (Day list) |
| GET | `/schedule/week` | shifts grouped by day (Week view) |
| GET | `/pay` | pay history |
| GET | `/pay/{id}` | paystub detail (breakdown + visit summary) |
| GET | `/pay/{id}/stub` | download pay stub PDF |
| GET | `/earnings/summary` | YTD + earnings/hours graph data |
| GET | `/compliance-forms` | monthly compliance forms |
| GET | `/compliance-forms/history` | 12-month compliance history |
| GET | `/compliance-forms/{id}` | one form + certification questions |
| POST | `/compliance-forms/{id}/submit` | submit the certification |
| GET | `/documents` | documents the caregiver uploaded |
| POST | `/documents` | upload a captured document to the EMR |
| GET | `/notifications` | notification feed |
| GET | `/notifications/unread-count` | unread badge count |
| POST | `/notifications/{id}/read` | mark one read |
| POST | `/notifications/read-all` | mark all read |
| DELETE | `/notifications/{id}` | delete one (swipe) |
| GET | `/conversations` | inbox (chat threads) |
| GET | `/conversations/unread-count` | unread thread badge |
| POST | `/conversations` | start a new chat |
| GET | `/conversations/{id}` | open a chat (messages) |
| POST | `/conversations/{id}/messages` | send a message |
| GET | `/realtime/config` | live socket connection params for this env (debug the connect/disconnect loop) |
| POST | ⚠️ `/broadcasting/auth` (no `/api` prefix — see note below) | authorize a private socket channel (real-time chat) |

⚠️ **`/broadcasting/auth` is the only endpoint in this doc that is NOT under `/api`.** Call it as `https://beydountech.com/broadcasting/auth`, not `https://beydountech.com/api/broadcasting/auth` — the latter 404s. Every other row in this table is under the `/api` base URL as normal.

Errors are always `{ "message": "..." }`. Codes: `401` no/invalid token · `403` not allowed · `404` not found · `409` already clocked in · `422` validation/rule failure.

---

## POST `/login`
**Payload**
```json
{ "email": "robert@example.com", "password": "secret" }
```
**Response 200**
```json
{ "message": "Login successful", "token": "12|aBc...", "user": { "id": 5, "name": "Robert Nguyen", "email": "robert@example.com" } }
```

## POST `/refresh`
Rotates the token: issues a fresh one and revokes the one used for this call. Send the **current** bearer token; you get back a new token to store. No credentials needed. Call it on app resume / before expiry so caregivers are never bounced to login mid-shift.
No payload. **200**
```json
{ "message": "Token refreshed", "token": "34|XyZ...", "user": { "id": 5, "name": "Robert Nguyen", "email": "robert@example.com" } }
```
After a successful call the old token is dead — replace it everywhere.

## POST `/logout`
No payload. **200** `{ "message": "Logged out successfully" }`

## GET `/me`
No payload. **200**
```json
{ "data": {
  "id": 9, "first_name": "Robert", "last_name": "Nguyen", "name": "Robert Nguyen",
  "initials": "RN", "email": "robert@example.com", "phone": "(313) 555-0277",
  "address": "742 Evergreen, Dearborn, MI", "avatar_url": null,
  "caregiver_type": "agency", "live_in": false, "hourly_wage": 15.0,
  "status": "Active", "pay_eligibility_start": "2026-03-18"
} }
```
`avatar_url` may be `null` — fall back to `initials`.

## GET `/dashboard`
Everything the home screen needs in one call. **200**
```json
{ "data": {
  "caregiver": { "id": 9, "name": "Robert Nguyen", "first_name": "Robert", "avatar_url": null },
  "today": { "date": "2026-04-22", "weekday": "Tuesday", "label": "Tuesday, Apr 22" },
  "active_visit": null,
  "next_shift": {
    "visit": { "id": 90, "client_name": "John Doe", "scheduled_start": "2026-04-22T09:00:00+00:00", "...": "…full visit shape…" },
    "starts_in_minutes": 35
  },
  "today_schedule": [ { "id": 90, "client_name": "John Doe", "status": "Scheduled", "...": "…full visit shape…" } ],
  "tasks": { "done": 12, "total": 15, "remaining_hours": 4.5 },
  "hours_this_week": 22.5,
  "pay": { "ytd_gross": 7112.5, "ytd_hours": 284.5, "paystub_count": 4 },
  "badges": { "unread_notifications": 2, "unread_conversations": 1 }
} }
```
- `active_visit` = an open (clocked-in) visit, else `null`.
- `next_shift.starts_in_minutes` drives the "35 In Min" countdown ring (0 if the start is in the past).
- `tasks` are aggregated across **today's** visits (see care tasks); `remaining_hours` = scheduled hours of today's not-yet-completed visits.

## GET `/assignments`
No payload. **200**
```json
{ "data": [
  { "id": 12, "name": "Maria Hassan", "phone": "(313) 555-0101",
    "address": "742 Evergreen, Dearborn, MI", "county": "Wayne", "program": "MICH",
    "authorization": { "label": "Active", "tone": "green", "days": 64 } }
] }
```

---

# Calls (click-to-call)

**This is a real phone call, not an in-app/VoIP call.** Your app never streams audio — it just triggers the call and (optionally) opens the native dialer. The actual voices travel over the regular phone network, bridged by RingCentral.

Backs the **"Call Now"** button on the My Clients screen. A caregiver may only call a client they are **assigned** to (`403` otherwise).

Two ways a call is placed, decided **server-side** and reported back in `mode`:

- **`ringout`** — RingCentral bridges the call: it rings the **caregiver's phone first**, then dials the client and connects them. The client sees the **agency caller ID**, never the caregiver's personal number. Used when RingCentral is configured and both phone numbers are on file.
- **`manual`** — no bridge was placed (RingCentral not configured, or a phone number is missing). The response carries a **`tel:` link** — open it with the device dialer so the call still goes through.

**The app should always honour `mode`:** on `ringout`, show "Connecting… your phone will ring". On `manual`, open the `tel` link. That way the button works whether or not RingCentral voice is live yet.

**What actually happens step by step (`ringout` mode):**
1. App calls `POST /calls { client_id }`.
2. Our server tells RingCentral to dial the **caregiver's own phone number** (the one on file).
3. Caregiver's phone rings and they answer it like a normal call.
4. RingCentral then dials the **client's phone number** and bridges the two legs together.
5. The client's caller ID shows the **agency's number**, never the caregiver's personal cell.

There is no audio/SDK/WebRTC work for the app to do — `POST /calls` just fires the bridge server-side. The only thing the app does with the *response* is decide what UI to show (`ringout` = "connecting" toast; `manual` = launch `tel:` in the OS dialer).

`GET /calls` is just a **log of past attempts** (who/when/mode/status) for the "Recent Calls" list — it is not a recording and has no audio to play back.

## POST `/calls`
**Payload** `{ "client_id": 12 }`
**Response 201**
```json
{
  "message": "Call initiated — your phone will ring first, then connect to the client.",
  "data": {
    "id": 51, "client_id": 12, "client_name": "Maria Hassan",
    "direction": "outbound", "mode": "ringout", "status": "initiated",
    "to": "(313) 555-0101", "from": "(313) 555-2001",
    "provider": "ringcentral", "provider_call_id": "rc-call-123", "provider_error": null,
    "tel": "tel:3135550101",
    "created_at": "2026-07-06T15:12:00+00:00", "time": "3:12 PM", "time_ago": "1 second ago"
  }
}
```
When RingCentral isn't available the same call returns the manual fallback:
```json
{ "message": "Ready to dial. Open the client number on your device.",
  "data": { "mode": "manual", "status": "manual", "provider": null,
            "provider_call_id": null, "tel": "tel:3135550101", "...": "…" } }
```
- `mode`: `ringout` (bridged) · `manual` (dial `tel` on the device).
- `status`: `initiated` (RingOut accepted — phones will ring) · `manual`.
- `tel`: always present when the client has a phone — the native-dialer fallback.
- `provider_error`: set only when a RingOut attempt failed and we fell back to `manual` (surface for debugging; the call still degrades gracefully).
- Errors: `403` client not assigned to you · `422` client has no phone on file.

## GET `/calls`
Query: `?per_page=25`. **200** — the caregiver's own calls, newest first (same object shape as above), plus `links`, `meta`.

**Server note (backend/deploy):** RingOut needs the RingCentral app to have the **RingOut** permission enabled (developers.ringcentral.com → your app → Permissions), plus the usual `RINGCENTRAL_*` env keys and an SMS/voice-capable `RINGCENTRAL_FROM_NUMBER` for the agency caller ID. Until then every call returns `mode: manual` and the app dials natively — no mobile change needed when voice goes live.

---

# Visits (clock in / out)

## GET `/visits/active`
No payload. **200** (open visit, or `null`)
```json
{ "data": { "id": 88, "client_id": 12, "client_name": "Maria Hassan",
  "status": "Clocked In", "clock_in_at": "2026-06-20T14:02:11+00:00",
  "clock_out_at": null, "total_hours": null } }
```

## POST `/visits/clock-in`
Pass **`client_id`** (ad-hoc) OR **`schedule_id`** (existing shift). GPS optional.
**Payload**
```json
{ "client_id": 12, "latitude": 42.31, "longitude": -83.17 }
```
**Response 201**
```json
{ "message": "Clocked in.", "data": {
  "id": 88, "client_id": 12, "client_name": "Maria Hassan", "status": "Clocked In",
  "clock_in_at": "2026-06-20T14:02:11+00:00", "clock_out_at": null, "total_hours": null,
  "clock_in_location": { "latitude": 42.31, "longitude": -83.17 } } }
```
Errors: `409` already clocked in · `422` client not assigned · `404` not found.

## POST `/visits/clock-out`
Ends the open visit (or pass `schedule_id`). All fields optional.
**Payload**
```json
{ "latitude": 42.31, "longitude": -83.17, "notes": "Full visit completed." }
```
**Response 200**
```json
{ "message": "Clocked out.", "data": {
  "id": 88, "status": "Completed", "total_hours": 4.0, "evv_verified": true,
  "clock_in_at": "2026-06-20T14:02:11+00:00", "clock_out_at": "2026-06-20T18:02:11+00:00" } }
```
Error: `404` no open visit.

## GET `/visits`
Query: `?per_page=50`. **200** — paginated list of visits (same shape as above) + `links`, `meta`.

---

# Care tasks (visit checklist)

The "Care Tasks" list on the active shift, the "Confirm Completed Tasks" step at clock-out, and the home "Task Done" ring. Tasks belong to a **visit** (`schedule`); the caregiver can only touch their own visits (`403` otherwise).

## GET `/visits/{schedule}/tasks`
No payload. **200** — checklist order:
```json
{ "data": [
  { "id": 5, "schedule_id": 90, "label": "Bathing Assistance", "category": "Personal Care",
    "sort_order": 1, "is_completed": true, "completed_at": "2026-04-22T09:35:00+00:00" },
  { "id": 6, "schedule_id": 90, "label": "Medication Reminder", "category": "Personal Care",
    "sort_order": 2, "is_completed": false, "completed_at": null }
] }
```

## POST `/visits/{schedule}/tasks`
Add one task, or a batch.
**Payload** (single) `{ "label": "Light Housekeeping", "category": "Homemaking" }`
**Payload** (batch) `{ "tasks": [ { "label": "Bathing Assistance", "category": "Personal Care" }, { "label": "Meal Preparation" } ] }`
**Response 201** `{ "message": "Tasks added.", "data": [ { ...task... } ] }`

## POST `/visits/{schedule}/tasks/{task}/toggle`
Tap a checklist row. Omit the body to flip; or force a state with `{ "is_completed": true }`.
**200** `{ "message": "Task completed.", "data": { "id": 5, "is_completed": true, "completed_at": "..." } }`

---

# Schedule

## GET `/schedule`  (Day list)
Query (all optional): `from=2026-06-01` · `to=2026-06-30` · `status=Scheduled` · `upcoming=1` · `per_page=50`.
Defaults to upcoming. **200**
```json
{ "data": [
  { "id": 90, "client_id": 12, "client_name": "Maria Hassan", "title": "Care visit",
    "status": "Scheduled", "date": "2026-06-21",
    "scheduled_start": "2026-06-21T08:00:00+00:00", "scheduled_end": "2026-06-21T12:00:00+00:00",
    "address": "742 Evergreen, Dearborn, MI" }
], "links": {}, "meta": {} }
```

## GET `/schedule/week`  (Week view)
Query: `date=2026-04-21` (any day in the week; defaults to today). Weeks start **Monday**. **200**
```json
{ "data": {
  "week_start": "2026-04-20", "week_end": "2026-04-26", "month": "April 2026",
  "days": [
    { "date": "2026-04-20", "weekday": "Monday", "weekday_short": "MON", "day_number": 20,
      "is_today": false, "count": 2, "visits": [ { "id": 90, "client_name": "Steven Mark", "...": "…full visit shape…" } ] },
    { "date": "2026-04-21", "weekday": "Tuesday", "weekday_short": "TUE", "day_number": 21, "is_today": true, "count": 0, "visits": [] }
  ]
} }
```
`days` is always 7 entries (the day-chip strip); each carries its own visit list.

---

# Pay & earnings

## GET `/pay`
Query (optional): `period_key=2026-05` · `per_page=50`. **200**
```json
{ "data": [
  { "id": 31, "period": "May 2026", "period_key": "2026-05",
    "hours": 108.0, "rate": 15.0, "gross": 1620.0, "status": "Paid",
    "program": "MICH", "paid_date": "2026-06-06", "client_name": "Maria Hassan",
    "stub_available": true, "stub_url": "https://beydountech.com/api/pay/31/stub" }
], "links": {}, "meta": {} }
```

## GET `/pay/{id}`
One paystub with an **estimated** gross → net breakdown and a per-client visit summary. **200**
```json
{ "data": {
  "id": 31, "period": "Apr 14 – Apr 27, 2026", "period_key": "2026-04",
  "hours": 72.5, "rate": 25.0, "gross": 1812.5, "status": "Paid", "pay_date": "2026-04-30",
  "breakdown": { "gross": 1812.5, "federal_tax": 0.0, "state_tax": 0.0, "fica": 138.66, "net": 1673.84, "estimated": true },
  "visit_summary": [ { "client_id": 12, "client_name": "John Doe", "hours": 40.5 }, { "client_id": 15, "client_name": "Evelyn Carter", "hours": 32.0 } ]
} }
```
`breakdown` is an **estimate** (`estimated: true`): FICA is statutory (7.65%); federal/state default to 0 until the org configures rates. The authoritative net pay comes from the payroll provider (Gusto/QuickBooks) — label it "estimated" in the UI. `403` if not your record.

## GET `/pay/{id}/stub`
No payload. **200** = PDF file download. `404` no stub · `403` not yours.

## GET `/earnings/summary`
Drives the Payroll screen: YTD header, integration chips, and the two graphs. Query (optional): `year=2026` · `periods=6` (earnings bars) · `weeks=8` (hours line). **200**
```json
{ "data": {
  "year": 2026,
  "year_to_date": { "gross": 7112.5, "hours": 284.5, "paystub_count": 4 },
  "integrations": { "quickbooks": { "label": "QuickBooks", "connected": true }, "gusto": { "label": "Gusto", "ready": true } },
  "earnings_series": [
    { "period": "Mar 03 – Mar 16, 2026", "period_key": "2026-03", "gross": 1750.0, "hours": 70.0, "status": "Paid", "paid_date": "2026-03-20" }
  ],
  "hours_series": [
    { "week_start": "2026-03-02", "week_end": "2026-03-08", "label": "Mar 2", "hours": 34.5 }
  ]
} }
```
Both series are **oldest-first** for left-to-right charting. `integrations` flags reflect whether the org has QuickBooks/Gusto payroll URLs configured.

---

# Monthly compliance certification

The "Certify Last Month's Services" flow + Compliance History. Forms belong to the caregiver (`403` otherwise).

## GET `/compliance-forms`
Query (optional): `status=Due` · `per_page=25`. **200** — paginated:
```json
{ "data": [
  { "id": 7, "period": "2026-05", "period_label": "May 2026", "status": "Pending",
    "submitted": false, "is_overdue": false, "service_start": null, "service_end": null,
    "required_days_per_week": 5, "authorized_hours": null, "delivered_hours": null,
    "additional_notes": null, "certification": null, "signature_url": null,
    "submitted_at": null, "time_ago": null }
], "links": {}, "meta": {} }
```
`status` is derived: `Submitted` · `Overdue` (certification window closed, not submitted) · `Pending`.

## GET `/compliance-forms/history`
The "Last 12 Months" header + record list. **200**
```json
{ "data": {
  "summary": { "submitted": 11, "overdue": 1, "on_time_pct": 92 },
  "records": [ { "id": 7, "period_label": "April 2026", "status": "Submitted", "submitted_at": "2026-05-02T14:10:00+00:00", "...": "…form shape…" } ]
} }
```

## GET `/compliance-forms/{id}`
One form plus the certification questions (the `{month}` placeholder is filled in). **200**
```json
{ "data": {
  "id": 7, "period_label": "May 2026", "status": "Pending", "submitted": false,
  "questions": [
    { "key": "provided_services",   "text": "Did you provide services during May 2026?" },
    { "key": "client_hospitalized", "text": "Was the client hospitalized during this month?" },
    { "key": "missed_visits",       "text": "Were there any missed or skipped visits?" },
    { "key": "condition_changed",   "text": "Did the client's condition change significantly?" },
    { "key": "services_as_planned", "text": "Were all scheduled services provided as planned?" },
    { "key": "certify_accurate",    "text": "Do you certify the information above is accurate?" }
  ]
} }
```

## POST `/compliance-forms/{id}/submit`
Submit the yes/no answers, an optional note, and the captured signature.
**Payload**
```json
{
  "answers": { "provided_services": true, "client_hospitalized": false, "missed_visits": false,
               "condition_changed": true, "services_as_planned": true, "certify_accurate": true },
  "additional_notes": "Client in good spirits all month.",
  "signature": "data:image/png;base64,iVBORw0KGgoAAAANS..."
}
```
`answers` values are booleans keyed by the question `key`s (unknown keys are ignored). `signature` is a base64 PNG (a `data:` URI prefix is accepted). **200**
```json
{ "message": "Compliance form submitted.", "data": { "id": 7, "status": "Submitted", "submitted_at": "2026-06-01T09:42:00+00:00", "signature_url": "https://beydountech.com/storage/compliance/signatures/7-1780000000.png", "certification": { "provided_services": true, "...": "…" } } }
```
Errors: `422` missing answers/signature · `403` not your form.

---

# Documents (capture → EMR)

The "Submit To EMR" screen: snap a document and file it against a client's record.

## GET `/documents`
Query: `per_page=25`. **200** — the caregiver's own uploads, newest first:
```json
{ "data": [
  { "id": 40, "name": "Drivers-License.jpg", "type": "ID", "category": "Field Upload",
    "is_signed": false, "mime_type": "image/jpeg", "file_size": 1258291,
    "verification_status": "Pending", "attached_to": "John Doe", "client_id": 12,
    "url": "https://beydountech.com/storage/documents/abc.jpg", "uploaded_at": "2026-04-22T10:05:00+00:00" }
], "links": {}, "meta": {} }
```

## POST `/documents`
**`multipart/form-data`** (not JSON — it carries a file):

| Field | Required | Notes |
|---|---|---|
| `file` | yes | jpg/jpeg/png/pdf/heic/webp, ≤ 10 MB |
| `type` | yes | one of `ID` · `Mail/Letter` · `Signed Form` · `Other` |
| `client_id` | no | file against this client (must be assigned); omit to file against yourself |
| `notes` | no | note for the office |

**Response 201** `{ "message": "Document uploaded.", "data": { ...document... } }`.
Errors: `422` bad file / unassigned client.

---

# Notifications API

The in-app alert feed. Same store the web app writes to, so schedule changes, compliance-due reminders and new-message pings all land here. Per-user only.

## GET `/notifications`
Query (all optional): `unread=1` · `per_page=25` (max 100). **200** — paginated, newest first:
```json
{ "data": [
  { "id": 42, "type": "secure_message", "title": "Schedule Updated",
    "body": "New Visit Added: Robert Lee — Thursday 6:00 PM.",
    "read": false, "created_at": "2026-06-27T18:00:00+00:00", "time_ago": "4 days ago" }
], "links": {}, "meta": {} }
```
`type` is one of: `secure_message` · `communication_sent` · `communication_failed` · `communication_received`.

## GET `/notifications/unread-count`
**200** `{ "count": 2 }`.

## POST `/notifications/{id}/read`
**200** `{ "message": "Notification marked as read." }`. `403` if not yours.

## POST `/notifications/read-all`
**200** `{ "message": "All notifications marked as read.", "updated": 2 }`.

## DELETE `/notifications/{id}`
**200** `{ "message": "Notification deleted." }`. `403` if not yours. Backs swipe-to-delete.

---

# Chat / Messaging API

Threaded messaging (the **Inbox** + **Chat** screens), backed by secure message threads. A user only ever sees threads they are a **participant** in. For live updates see **Real-time** below (REST here is the source of truth + offline fallback).

> **Test accounts (chat + calls).** The demo seed ships **two** caregiver logins so you can test two-sided chat from two devices/sessions and see both sides update live:
> | Email | Password | Seeded as |
> |---|---|---|
> | `caregiver@beydountech.com` | `care123` | Sarah Connor (2 assigned clients) |
> | `caregiver2@beydountech.com` | `care123` | Michael Rodriguez (1 assigned client) |
>
> After seeding, both accounts already share a conversation (**"Covering Thursday?"**), and `caregiver@` also has a **"Welcome to the team!"** thread from the office. Log in as each, open `/conversations`, and send messages back and forth. Both accounts have assigned clients, so `/calls` works for them too. (Run `php artisan db:seed` to create them.)

## GET `/conversations`
Query (optional): `unread=1` · `search=text` · `per_page=20` (max 100). Newest activity first. **200**:
```json
{ "data": [
  { "id": 7, "subject": "Article for you",
    "counterpart": { "id": 3, "name": "Salena James", "avatar_url": null },
    "last_message": "Hi Designers, Checkout This Article; Learn More About The Laws Of U.I Design.",
    "last_sender": "Salena James", "unread": true,
    "last_message_at": "2026-06-27T18:00:00+00:00", "time_ago": "4 days ago" }
], "links": {}, "meta": {} }
```

## GET `/conversations/unread-count`
**200** `{ "count": 2 }`.

## GET `/conversations/{id}`
Opens a thread. **Side effect: marks the thread read.** **200** — messages oldest-first:
```json
{ "data": {
  "id": 7, "subject": "Setup help",
  "participants": [ { "id": 3, "name": "Salena James" }, { "id": 9, "name": "Robert Nguyen" } ],
  "messages": [
    { "id": 51, "body": "How can we help you?", "sender_id": 3, "sender_name": "Salena James",
      "avatar_url": null, "is_mine": false, "created_at": "2026-06-27T16:35:00+00:00",
      "time": "11:35 AM", "time_ago": "4 days ago" }
  ] } }
```
`is_mine` tells the app which side to render. `403` if not a participant.

## POST `/conversations/{id}/messages`
**Payload** `{ "body": "Account setup help." }` (required, max 5000). **Response 201** = the message (same shape). Also fires the real-time `message.sent` event to the other participants. `403` not a participant · `422` empty/oversized body.

## POST `/conversations`
Start a new thread.
**Payload**
```json
{ "subject": "Question about my shift", "body": "Can you confirm Thursday 6:00 PM?", "participant_ids": [3] }
```
**Response 201** `{ "message": "Conversation started.", "data": { ...conversation summary... } }`. `participant_ids` must be users in your organization (`403` otherwise).

---

# Real-time (WebSockets)

> ⚠️ **Not Socket.IO.** Live chat is delivered over **Laravel Reverb**, which speaks the **Pusher Channels protocol**. This is a different wire protocol than Socket.IO — a generic `socket_io_client` package **will not connect to this backend**, no matter how it's configured. On Flutter, use a Pusher-Channels-compatible client instead, e.g. the **`pusher_channels_flutter`** package (or `pusher_client_socket`/`pusher_client_flutter`) pointed at the Reverb host/port below. Do not implement Socket.IO.
>
> ✅ **Confirmed working (re-verified 2026-07-11).** Reverb is installed and `BROADCAST_CONNECTION=reverb` locally; the full flow was tested end-to-end against a live Reverb server (connect → `POST /broadcasting/auth` → subscribe to `private-conversation.{id}` → send a message via REST → received `message.sent` live on the socket). The server side is stable and does **not** produce a reconnect loop with the correct app key. If you are seeing a `connecting → disconnected → reconnecting` loop, it is a client/env **config mismatch** (Reverb error 4001) — see **🛟 Debugging a `connecting → disconnected → reconnecting` loop** below and hit `GET /api/realtime/config` to compare. It is **not yet turned on in staging/production** — see the deploy note at the bottom for the exact `.env` keys to set there. Build against REST first if the socket isn't reachable yet in your environment; nothing about the REST flow changes once the socket is added, it's purely an extra push layer.

REST endpoints above remain the source of truth; the socket is a live push layer on top. If the socket is not connected for any reason (no network, server down), keep polling the two `unread-count` endpoints as a fallback — that's exactly what they're there for.

### Quick reference — connection settings
| Setting | Value |
|---|---|
| Host | `beydountech.com` |
| Port | `443` |
| Scheme | `https` / `wss` (TLS on) |
| Auth endpoint | `https://beydountech.com/broadcasting/auth` |
| App key | ask the backend team for the current `REVERB_APP_KEY` once it's live in production |
| Package (Flutter) | `pusher_channels_flutter` (**not** `socket_io_client`) |

Only the **app key** rotates if the backend ever regenerates credentials — host, port, and the auth endpoint above are fixed. Never ask for or hardcode `REVERB_APP_SECRET`; that key never leaves the server.

### 🛟 Debugging a `connecting → disconnected → reconnecting` loop

If the socket connects and immediately drops in a tight loop (the debug console spams `connection connecting → disconnected → reconnecting → connecting …`), the socket is being **rejected the instant it opens**. This is almost never a network problem — it is a **config mismatch**, and Reverb tells you exactly which one in the close/error frame. Turn on the client's error logging to see it:

```dart
await pusher.init(
  apiKey: REVERB_APP_KEY,
  // ... other options ...
  onError: (message, code, error) => print('Pusher error $code: $message'),
  onConnectionStateChange: (prev, curr) => print('Pusher state: $prev -> $curr'),
);
```

The `code` in the error frame is the whole diagnosis:

| Code | Meaning | Fix |
|---|---|---|
| **4001** `Application does not exist` | The **app key** your client connects with is not the key the server runs with — **this is the #1 cause of the reconnect loop.** | Make your `apiKey` exactly equal the server's `REVERB_APP_KEY`. Verify against the live server with the endpoint below. |
| **4009** `Connection is unauthorized` | The socket is fine; a **private channel** subscription failed auth (bad/expired bearer token, or not a participant). The connection stays up — only that channel is rejected, so this alone does **not** cause the loop. | Send a valid `Authorization: Bearer <token>` from `onAuthorizer`; re-login if the token expired. |
| **1006 / no error frame** | Can't reach Reverb at all (wrong host/port, TLS mismatch, or the server/reverse-proxy isn't forwarding `/app/*`). | Check host/port/scheme; confirm Reverb is running and proxied on the environment you point at. |

**Self-check endpoint — `GET /api/realtime/config`** (Sanctum-authed). Returns the *exact* connection parameters the server you're pointing at expects, so you can compare them to your client init field-by-field:

```json
{
  "enabled": true, "driver": "reverb",
  "key": "tu1vewiajsfgqdlaaysy",
  "host": "beydountech.com", "port": 443, "scheme": "https", "use_tls": true,
  "auth_endpoint": "https://beydountech.com/broadcasting/auth",
  "channels": { "conversation": "private-conversation.{threadId}", "user": "private-user.42" },
  "event": "message.sent",
  "hint": "…"
}
```

- If `key`/`host`/`port` here don't match your app's init → that's your **4001** loop. Use these values.
- If `enabled` is `false`, this environment is still on the `log` broadcaster (sockets are OFF) — no live pushes will ever arrive; build against REST polling until the backend sets `BROADCAST_CONNECTION=reverb` there. It is **not** something you can fix client-side.

### Connecting (once Reverb is live)
Point the client at the Reverb server with the app key from the table above.

**Flutter** (`pusher_channels_flutter` package):
```dart
final pusher = PusherChannelsFlutter.getInstance();

await pusher.init(
  apiKey: REVERB_APP_KEY,                 // from the backend team
  cluster: 'mt1',                         // ignored by Reverb, package requires a value
  useTLS: true,                           // false only for local http testing
  host: REVERB_HOST,                      // = 'beydountech.com'
  port: REVERB_PORT,                      // e.g. 443
  authEndpoint: 'https://beydountech.com/broadcasting/auth',
  onAuthorizer: (channelName, socketId, options) async {
    // attach the bearer token so the server can authorize this private channel
    final res = await http.post(Uri.parse('https://beydountech.com/broadcasting/auth'),
      headers: {'Authorization': 'Bearer $token', 'Accept': 'application/json'},
      body: {'channel_name': channelName, 'socket_id': socketId});
    return jsonDecode(res.body);
  },
);
await pusher.connect();
```

**Web / reference** (`laravel-echo` + `pusher-js`):
```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: REVERB_APP_KEY,            // from the backend team
  wsHost: REVERB_HOST,           // = 'beydountech.com'
  wsPort: REVERB_PORT,           // e.g. 443
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: 'https://beydountech.com/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
});
```

### Authorizing private channels — `POST /broadcasting/auth`
Private channels are authorized with the **Sanctum bearer token**. The client library calls `POST /broadcasting/auth` automatically on subscribe; just make sure the `Authorization: Bearer <token>` header is attached (as above). A non-participant is rejected `403`.

### Channels
| Channel | Who may subscribe | Use |
|---|---|---|
| `private-conversation.{threadId}` | participants of that thread | live messages inside an open chat |
| `private-user.{userId}` | that user only | inbox badge / new-thread pings anywhere in the app |

(The `private-` prefix is added by the client library; the server channels are `conversation.{threadId}` and `user.{userId}`.)

### Event — `message.sent`
Fires on every new message (new thread **or** reply), on the thread channel and on each other participant's user channel.
```json
{
  "id": 52, "thread_id": 7, "body": "Account setup help.",
  "sender_id": 9, "sender_name": "Robert Nguyen", "avatar_url": null,
  "created_at": "2026-06-27T16:40:00+00:00", "time": "11:40 AM", "time_ago": "1 second ago"
}
```
Note: the socket payload has **no `is_mine`** (broadcasting is one-to-many). Decide the bubble side on the device by comparing `sender_id` to the logged-in user's id.

**Flutter:**
```dart
await pusher.subscribe(
  channelName: 'private-conversation.$threadId',
  onEvent: (event) {
    if (event.eventName == 'message.sent') appendBubble(jsonDecode(event.data));
  },
);
await pusher.subscribe(
  channelName: 'private-user.$myUserId',
  onEvent: (event) {
    if (event.eventName == 'message.sent') bumpInboxBadge(jsonDecode(event.data));
  },
);
```

**Web / reference:**
```js
echo.private(`conversation.${threadId}`)
    .listen('.message.sent', (e) => appendBubble(e));   // note the leading dot

echo.private(`user.${myUserId}`)
    .listen('.message.sent', (e) => bumpInboxBadge(e));
```

### Server / deploy note (backend)
Real-time ships **disabled by default** so nothing breaks before the socket server exists. Already done locally; to turn it on in staging/production:
```bash
composer require laravel/reverb
php artisan reverb:install
# run the server continuously under a process supervisor (systemd/Supervisor), not a one-off command:
php artisan reverb:start
```
⚠️ **Known gotcha (hit during local setup):** `php artisan reverb:install` writes `BROADCAST_DRIVER=reverb` to `.env`, but this app's `config/broadcasting.php` reads `BROADCAST_CONNECTION` (the current Laravel key name) — `BROADCAST_DRIVER` is ignored. If you only run the installer, broadcasting **silently stays on the `log` driver** (no errors, sockets just never fire). After running the installer, open `.env` and make sure it says `BROADCAST_CONNECTION=reverb`, not `BROADCAST_DRIVER=reverb`.

The exact keys needed are templated in `.env.staging.example` (`BROADCAST_CONNECTION` + `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` / `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` / `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT`). Give the mobile team only `REVERB_APP_KEY`, host, and port — never the `REVERB_APP_SECRET` (server-side only).

---

# Screen integration cheat-sheet

How the shared mockups map to the endpoints above.

### My Clients (client list + "Call Now")
- List → **`GET /assignments`** (name, address, program, authorization badge).
- Search → filter client-side, or the app can search the returned list.
- **Call Now** → **`POST /calls`** `{ client_id }`. Honour `mode`: `ringout` → "your phone will ring"; `manual` → open `data.tel` in the device dialer.

### Upload Document ("Submit To EMR")
- Whole screen → **`POST /documents`** (`multipart/form-data`): `file`, `type` (`ID`/`Mail/Letter`/`Signed Form`/`Other`), optional `client_id`, optional `notes`.
- "Document Uploaded → Synced To Your Agency EMR" = the `201` response; the office is notified automatically.
- History → **`GET /documents`**.

### Tasks ("All Tasks", filter chips All / Compliance / Documents / Visits)
There is **no single `/tasks` feed endpoint** — compose the list on the device from the three sources, and let the filter chips switch source:
- **Compliance** ("Complete Form") → **`GET /compliance-forms?status=Due`** → the "Complete Form" CTA opens `GET /compliance-forms/{id}` then `POST /compliance-forms/{id}/submit`.
- **Documents** ("Upload …") → items the office is waiting on; today these are surfaced via **`GET /notifications`** (type `secure_message`) and fulfilled with **`POST /documents`**.
- **Visits** ("Sign Visit Note", "Start Shift") → **`GET /schedule?upcoming=1`** / **`GET /visits`**; act with the clock-in/out + care-task endpoints.
- The header count ("3 Pending") = the total across whichever sources are shown.

> If you'd prefer one unified `GET /tasks` aggregate (server merges compliance + document-requests + visit actions into a single ranked feed), that's a small backend add — ask the backend team. It isn't built yet.

---

### Status values
**Visit:** `Scheduled` · `Clocked In` · `Completed` · `Missed` · `Cancelled` · `No Show`
**Pay:** `Awaiting form` · `Pending` · `Ready` · `In grace` · `Late - rolled` · `Held - review` · `Paid`
**Compliance (derived):** `Submitted` · `Overdue` · `Pending`
**Document type:** `ID` · `Mail/Letter` · `Signed Form` · `Other`

### Notes
- Hours (`total_hours`) are calculated **server-side** at clock-out — don't compute pay on the device.
- Only **one open visit at a time**; caregiver can only clock into an **assigned** client.
- **Everything is per-user**: a token only ever exposes that caregiver's own data and the threads they participate in.
- Tax breakdowns on `/pay/{id}` are **estimates** (`estimated: true`) — surface the authoritative net from the payroll provider where available.
- Timestamps are ISO-8601; `time`, `time_ago`, `label` are pre-formatted convenience strings for display.
