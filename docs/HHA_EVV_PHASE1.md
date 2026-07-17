# HHAeXchange EVV Aggregator — Postman / Phase 1

## Test 1-001 Authenticate (Postman)

| Field | Value |
|-------|--------|
| Method | `POST` |
| URL (Implementation) | `https://implementation.hhaexchange.com/identity/connect/token` |
| URL (Production) | `https://cloud.hhaexchange.com/identity/connect/token` |
| Body | `x-www-form-urlencoded` |

```
grant_type=client_credentials
client_id={{your_client_id}}
client_secret={{your_client_secret}}
scope=write:aggregator
```

Expect **HTTP 200** with `access_token` (reuse ~30 minutes).

## Subsequent API calls

Header: `Authorization: Bearer {{access_token}}`

| Use | Method | Implementation URL |
|-----|--------|-------------------|
| Caregiver create/update | `POST` | `https://implementation.hhaexchange.com/api/v2/caregivers` → 200 + Transaction ID |
| Visits create (batch) | `POST` | `https://implementation.hhaexchange.com/api/v2/visits` → 202 + Transaction ID |
| Transaction status | `GET` | `https://implementation.hhaexchange.com/api/v2/visits/transactions/{transactionId}` → 200 + EVVMSID |
| Visit update | `PUT` | `https://implementation.hhaexchange.com/api/v2/visits/{evvmsid}` → 202 |
| Visit delete | `DELETE` | `https://implementation.hhaexchange.com/api/v2/visits/{evvmsid}` → 202 |

Swagger payload validation: https://implementation.hhaexchange.com/api/index.html

## Vault “Test connection”

Matches Swagger **Authorize** / Test **1-001**:
1. POST token URL with client credentials + `scope=write:aggregator`
2. Passes when an `access_token` is returned
3. Attestation can still be **Pending** — that only blocks live EVV sync, not OAuth

If Swagger Authorize works but the vault test fails, confirm vault fields match:
- Token URL = `https://implementation.hhaexchange.com/identity/connect/token`
- Scope = `write:aggregator`
- Same `client_id` / `client_secret` used in Swagger
