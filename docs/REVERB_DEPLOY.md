# Production Reverb Setup (one-time server task)

Turning on real-time chat in production is a **one-time manual server task**. It is **NOT
done by merging a PR** — the deploy pipeline (`.github/workflows/deploy.yml`) explicitly
excludes `.env` (`-x ".env"`) and does not manage long-running daemons. So the three things
below must be done once by whoever has SSH / Plesk access to the server, then it just keeps
running.

Server: Plesk, app root `/var/www/vhosts/beydountech.com`.

> 🔑 The actual `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` values are secrets and
> are **not** stored in this repo. Get them from the team lead (shared privately), or generate
> fresh ones on the server with `php artisan reverb:install` (step 1). The mobile team only ever
> receives `REVERB_APP_KEY` — never the secret.

---

## 1. Add the Reverb block to the server `.env`

SSH in (or use Plesk File Manager) and edit `/var/www/vhosts/beydountech.com/.env`:

```dotenv
BROADCAST_CONNECTION=reverb          # MUST be this exact key. NOT "BROADCAST_DRIVER".

# Credentials (paste the ones provided by the team, OR run `php artisan reverb:install`)
REVERB_APP_ID=<provided-separately>
REVERB_APP_KEY=<provided-separately>       # the only value the mobile app needs
REVERB_APP_SECRET=<provided-separately>    # server-only, never share

# PUBLIC address the mobile app connects to (through nginx/TLS)
REVERB_HOST=beydountech.com
REVERB_PORT=443
REVERB_SCHEME=https

# INTERNAL address the Reverb daemon binds to on the box (behind nginx)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

> ⚠️ **Gotcha:** `php artisan reverb:install` writes `BROADCAST_DRIVER=reverb`, but this app reads
> `BROADCAST_CONNECTION`. If the line says `BROADCAST_DRIVER`, broadcasting silently stays on the
> `log` driver and no sockets ever fire. Make sure it says **`BROADCAST_CONNECTION=reverb`**.

Then clear the cached config:
```bash
cd /var/www/vhosts/beydountech.com
php artisan config:clear
```

## 2. Run the Reverb daemon persistently (Supervisor)

`php artisan reverb:start` must run continuously and restart on crash/reboot. Create
`/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
command=php /var/www/vhosts/beydountech.com/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/vhosts/beydountech.com
autostart=true
autorestart=true
user=<the site's system user>
redirect_stderr=true
stdout_logfile=/var/www/vhosts/beydountech.com/storage/logs/reverb.log
stopwaitsecs=10
```

Load it:
```bash
supervisorctl reread
supervisorctl update
supervisorctl start reverb
supervisorctl status reverb      # should show RUNNING
```

> After every deploy that changes broadcasting/event code, restart the daemon so it picks up new
> code: `php artisan reverb:restart` (or `supervisorctl restart reverb`). Consider adding that line
> to `deploy.sh`.

## 3. Proxy the websocket through nginx (Plesk) — ⚠️ THE STEP THAT'S USUALLY MISSED

**This is the step people skip after doing the `.env`, and it's why the mobile app still loops
even when `/api/realtime/config` says `enabled: true`.** Setting the env keys does NOT make the
socket reachable — nginx has to hand the `/app/` path to the Reverb daemon. If it doesn't, the URL
falls through to Laravel and returns a **404**, the websocket upgrade never happens, and the mobile
client retries forever (looks identical to the old 4001 loop, but the cause is different).

The public `wss://beydountech.com/app/...` (client socket) **and** `/apps/...` (the HTTP API Laravel
uses to publish events) must both reach the daemon on `127.0.0.1:8080`.
In Plesk: **Websites & Domains → beydountech.com → Apache & nginx Settings → Additional nginx
directives**, add:

```nginx
# Laravel Reverb (Pusher-protocol websocket). /app = client socket, /apps = server publish API.
location ~ ^/(app|apps)/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_read_timeout 300s;
    proxy_send_timeout 300s;
}
```
Apply/OK (Plesk reloads nginx). Make sure the daemon (step 2) is actually **RUNNING** first —
otherwise this same path returns **502** (nginx reached Reverb but nothing answered) instead of 404.

---

## 4. Verify it's live

From any machine with a caregiver bearer token:
```bash
curl -s https://beydountech.com/api/realtime/config -H "Authorization: Bearer <token>" -H "Accept: application/json"
```
Expect `"enabled": true`, `"host": "beydountech.com"`, `"port": 443`, `"scheme": "https"`, and the
prod `key`. If `enabled` is `false`, step 1 didn't take (wrong key name or config not cleared).

Then confirm the socket path is reaching Reverb. **Do not treat a plain `curl` GET as proof of
failure.** `/app/{key}` is a WebSocket endpoint. A bare HTTP GET has no `Upgrade: websocket`
header, so Reverb skips the handshake and invokes `PusherController` with an HTTP connection
object. That hits a known upstream TypeError ([laravel/reverb#344](https://github.com/laravel/reverb/issues/344))
and returns **500** even when the daemon is healthy. That 500 is **not** an nginx / Supervisor /
`.env` problem.

Correct checks:

```bash
# 1) Plain GET — may return 500 on Reverb ≤1.10.2 (upstream #344). Useful only to see whether
#    nginx reaches the daemon at all (404 = Laravel, 502 = daemon down, 500 = Reverb HTTP path).
curl -s -o /dev/null -w "%{http_code}\n" "https://beydountech.com/app/<REVERB_APP_KEY>"

# 2) Real WebSocket Upgrade — this is the authoritative health check.
curl -s -i -N \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
  "https://beydountech.com/app/<REVERB_APP_KEY>"
```

Read the results:
- **`404`** on either request → nginx is NOT proxying `/app` (step 3 missing / wrong). The request
  hit Laravel. Fix the nginx directive.
- **`502`** → nginx proxies correctly but the **daemon is down** (step 2). Start/restart Reverb under
  Supervisor.
- **`500`** on plain GET + **`101 Switching Protocols`** on Upgrade → ✅ healthy. Reverb is up; the
  500 is the known non-WebSocket code path, not a broken install.
- **`101`** on Upgrade → ✅ socket path works end-to-end through TLS/nginx.

Automated local proof (starts a throwaway Reverb process):

```bash
php artisan test --group=reverb
```

Fastest end-to-end proof: with two caregiver test logins, open a Pusher-protocol client against
`wss://beydountech.com/app/<key>`, subscribe to `private-conversation.{id}`, POST a message via REST,
and confirm `message.sent` arrives live.

## 5. Hand-off

- Give the **mobile dev**: `REVERB_APP_KEY`, host `beydountech.com`, port `443`, scheme `https`/`wss`.
- **Never** give out `REVERB_APP_SECRET`.
- The mobile dev can always self-check the exact values with `GET /api/realtime/config`. If the app
  still loops after matching them, it's an app-side key typo — see the debugging table in
  `docs/MOBILE_API.md` (Reverb error `4001`).
