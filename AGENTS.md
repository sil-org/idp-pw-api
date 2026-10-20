# AGENTS.md

This file provides guidance to AI agents (such as [Claude Code](claude.ai/code)) when working with code in this repository.

## What this is

`idp-pw-api` is a Yii2 (PHP) backend API for an Identity Provider's password-management functions:
login, password reset, password change/assessment, MFA, and recovery-method management. It's one
piece of a larger IdP system — it delegates identity/personnel lookups and password storage to
pluggable "components" (see Architecture below), and pairs with a separate frontend
(`idp-profile-ui`). Everything runs in Docker; there is no native PHP dev setup documented.

## Setup

- Copy `local.env.dist` to `local.env` and fill in values.
- Set `DOCKER_UIDGID="$(id -u):$(id -g)"` so containers write files as your user.
- Set `COMPOSER_CONFIG_FILE` to your local `composer` `config.json` path (needed for the private
  GitHub composer auth token when pulling deps).
- Optionally copy `application/common/config/local.php.dist` to
  `application/common/config/local.php` for local overrides.
- Add `127.0.0.1 idp-pw-api.local` to `/etc/hosts`.

## Commands

All commands run through Docker Compose via the Makefile — there's no bare `php`/`composer` workflow.

```bash
make start           # bring up broker, composer install, adminer, api, zxcvbn (aliases to `make api`)
make composer        # composer install (in `cli` container)
make composerupdate  # composer update, then regenerate dependencies.json
make bounce          # restart just the api container
make clean           # kill and remove all containers
make psr2            # run php-cs-fixer over the codebase
```

Testing:

```bash
make test              # testlocal + testintegration (full suite)
make testlocal         # testunit + testapi (this is what CI runs)
make testunit          # Codeception `unit` suite (PHPUnit-based) + Behat "multiple" suite
make testapi           # Codeception `api` suite (REST/API tests, against a live `broker` container)
make testintegration   # Behat "integration" suite (e.g. real Google Workspace, needs TEST_GOOGLE_* creds)
```

Run a single unit test (verbose):

```bash
docker compose run --rm unittest vendor/bin/codecept -vv run tests/unit/common/models/PasswordTest.php:testBadBytes
```

**Note:** CI only runs `testlocal` (unit + api), not the integration suite.

## Architecture

### Pluggable component pattern

The app is built around three swappable Yii2 components, each defined by an interface in
`application/common/components/<name>/`, configured by class name via env vars, and accessed as
`\Yii::$app->{componentId}`:

| Component ID    | Interface                                                | Env var               | Built-in implementations                                      |
|-----------------|----------------------------------------------------------|-----------------------|---------------------------------------------------------------|
| `auth`          | `common\components\auth\AuthnInterface`                  | `AUTH_CLASS`          | `Saml` (SAML-based), configured via `AUTH_SAML_*` env vars    |
| `personnel`     | `common\components\personnel\PersonnelInterface`         | `PERSONNEL_CLASS`     | `IdBroker`                                                    |
| `passwordStore` | `common\components\passwordStore\PasswordStoreInterface` | `PASSWORDSTORE_CLASS` | `IdBroker`, `Google`, `Multiple` (fans out to several stores) |

When adding a new backend for one of these, implement the relevant interface rather than modifying
callers — controllers and models only ever talk to `\Yii::$app->auth` / `->personnel` /
`->passwordStore`.

Exception to this pattern: `MfaController` talks directly to a raw `Sil\Idp\IdBroker\Client\IdBrokerClient`
(constructed from `Yii::$app->params['idBrokerConfig']`) rather than going through the `personnel`/
`passwordStore` interfaces — MFA is not currently abstracted behind a swappable component.

### Three Yii "applications"

- `common/` — shared config, models, and components used by both of the below.
- `frontend/` — the REST API app (`frontend/config/main.php` defines all routes explicitly as
  `'METHOD /path' => 'controller/action'` — no auto-inferred REST routing). Route paths and their
  controller/action mappings live only here; there's no separate router file.
- `console/` — CLI commands (Yii console app), separate config/bootstrap from `frontend`.

`frontend/config/load-configs.php` merges `common/config/main.php` → optional
`common/config/local.php` (gitignored, local-only overrides) → `frontend/config/main.php`, in that
order (later entries win). Configuration values are read from env vars via `Sil\PhpEnv\Env` helpers
at the top of `common/config/main.php`, with an optional AWS Parameter Store backing (see README's
Configuration section) — this is where new config values should be wired in, not scattered
`getenv()` calls in controllers/models.

### Auth flow

- `POST/GET /auth/login` (`AuthController::actionLogin`) authenticates via the `auth` component
  (SAML by default) or an invite code, then issues an HttpOnly `access_token` cookie
  (`User::createAccessToken`) with an `auth_type` of `login` or `reset`.
- Every other endpoint is protected by `BaseRestController`, which wires up `HttpOnlyAuth` (a
  custom `yii\filters\auth\AuthMethod` reading the `access_token` cookie) via `CompositeAuth`, plus
  an `AccessControl` filter that turns "no user" into 401 and "user but denied" into 403.
- `common\models\User` is the Yii `IdentityInterface` implementation; `findIdentityByAccessToken`
  hashes the raw token (`Utils::getAccessTokenHash`) and asks the `personnel` component to resolve
  it — token storage/lookup is delegated to IdBroker, not a local DB table.
- `User::isAuthScopeFull()` (true only when `auth_type === AUTH_TYPE_LOGIN`) gates full-scope-only
  actions like MFA management (see `MfaController::behaviors()`) — a password-reset-scoped token
  can change the password but not manage MFA methods.
- CORS headers are set globally in `frontend/config/main.php`'s `response` component
  (`Access-Control-Allow-Origin` from `params['uiCorsOrigin']`), and every controller extending
  `BaseRestController` gets a generic `OPTIONS` action for preflight.

### Controllers (frontend/controllers/)

One controller per resource: `AuthController` (login/logout), `ConfigController` (public config for
the UI), `MethodController` (recovery email methods), `MfaController` (MFA factors, including
WebAuthn sub-resources), `PasswordController` (view/update/assess password), `ResetController`
(password-reset-by-UUID flow, no auth required), `UserController` (`/user/me`), `SiteController`
(catch-all/error/system-status).

### API contract

`openapi.yaml` at the repo root is the source of truth for the API surface — check/update it
alongside route and controller changes in `frontend/config/main.php`.

## Test configuration

Three frameworks are used, all launched via Codeception/Behat inside Docker containers (see
`README.md`'s "Test configuration" table for the full framework/config/bootstrap matrix):

- **Unit** (PHPUnit-under-Codeception + Behat "multiple" suite) — runs in the `unittest` container,
  covers models/helpers/components with mocked component implementations (see `tests/mock/`).
- **API** (Codeception REST/PhpBrowser) — runs in the `apitest` container against a live `broker`
  service, covers controllers end-to-end.
- **Integration** (Behat "integration" suite) — real external services (e.g. Google Workspace via
  `TEST_GOOGLE_*` env vars in `local.env`); not run in CI.

Env vars for tests come from `common.env` + `test.env`. Mock component implementations for auth,
personnel, and password store live under `application/tests/mock/`.
