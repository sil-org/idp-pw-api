# Required ID Broker API Changes

This document describes the changes needed in the
[ID Broker](https://github.com/sil-org/idp-id-broker) service to support the
elimination of the local database in `idp-pw-api`.  All user data (including
access-token state) is now managed exclusively by ID Broker.

---

## Background

`idp-pw-api` previously kept a local `user` table that stored, among other
things:

| Column | Purpose |
|---|---|
| `access_token` | HMAC-SHA256 hash of the raw cookie value |
| `access_token_expiration` | Datetime after which the token is invalid |
| `auth_type` | `login` or `reset` — the authentication level |

With the database eliminated, these three fields must be stored on the user
record in ID Broker and must be accessible via the ID Broker REST API.

---

## New / Changed Endpoints

### 1. `PUT /user/{employee_id}` — accept new fields

The existing update-user endpoint must accept three new optional fields in the
request body:

| Field | Type | Description |
|---|---|---|
| `access_token` | `string\|null` | HMAC-SHA256 hash of the raw token. Send `null` to clear. |
| `access_token_expiration` | `datetime string\|null` | Expiry in `YYYY-MM-DD HH:MM:SS` format (UTC). Send `null` to clear. |
| `auth_type` | `"login"\|"reset"\|null` | Authentication scope. Send `null` to clear. |

When `access_token` is `null` (or omitted), ID Broker must treat any
existing token as deleted (i.e. `findByAccessToken` must not return this
user).

**Example request body:**

```json
{
  "employee_id": "12345",
  "access_token": "a3f...8e1",
  "access_token_expiration": "2026-06-01 14:30:00",
  "auth_type": "login"
}
```

---

### 2. `GET /user` (list users) — filter by `access_token`

The list-users endpoint must support filtering by the new `access_token` field:

```
GET /user?access_token=<hash>
```

**Behaviour:**

* Return at most **one** user whose stored `access_token` hash matches the
  given value **and** whose `access_token_expiration` is in the future.
* If no such user exists (or the token has expired), return an empty list
  (HTTP 200 with `[]`).
* Expired tokens must be treated as absent — they must **not** be returned
  even if the hash matches.

The response for each matching user must include the new fields so that
`idp-pw-api` can reconstruct full identity information:

```json
[
  {
    "employee_id": "12345",
    "uuid": "...",
    "first_name": "...",
    "...": "...",
    "access_token": "<hash>",
    "access_token_expiration": "2026-06-01 14:30:00",
    "auth_type": "login",
    "active": "yes"
  }
]
```

---

## Notes for the ID Broker PHP Client (`idp-id-broker-php-client`)

Once the API changes above are in place, no new methods are strictly required in
the PHP client because `idp-pw-api` uses the existing `updateUser()` and
`listUsers()` methods.  However, the following improvements are recommended:

* **`updateUser()`** — already accepts an arbitrary properties array; no change
  needed, but documentation should be updated to note the new fields.
* **`listUsers()`** — already accepts a search filter array; no change needed.

---

## Security Considerations

* The `access_token` field stored in ID Broker must always be the **HMAC-SHA256
  hash** of the raw cookie value (using the `ACCESS_TOKEN_HASH_KEY` secret
  configured in `idp-pw-api`).  The raw token is never stored anywhere.
* ID Broker should enforce that `auth_type` is one of `login`, `reset`, or
  `null` and reject any other value.
* The `access_token_expiration` field is set by `idp-pw-api` when the token is
  created; ID Broker must honour it for expiry checks in the list endpoint.

---

## Deployment Order

Because the code change and the ID Broker update must be coordinated:

1. **Deploy the updated ID Broker** (accepts the new fields and supports the new
   filter).
2. **Deploy `idp-pw-api`** (new code that calls ID Broker for access-token
   management and no longer writes to the local `user` table).
3. **Run database migrations** on every environment (`yii migrate`) — this will
   execute `m260601_000000_drop_user_table` and drop the now-unused `user` table.
4. After all environments are migrated, the `db` component and the MariaDB
   service can be removed from the application configuration and
   `compose.yaml`.
