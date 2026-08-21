# Webhook Delivery Service

A small service that receives events, fans them out to registered webhook endpoints, and
tracks the result of every delivery attempt.

```
Receive Event → Process Event → Deliver Webhook → Track Result
```

## 1. Project Overview

The backend is a Laravel 13 (PHP 8.4) JSON API. When an event is posted to `POST /api/events`,
the app creates the event, creates one `delivery` record per **active** webhook endpoint, and
dispatches a queued job per delivery that performs the actual HTTP POST and records the
outcome (status, HTTP code, attempt count, error message).

The frontend (`frontend/`) is a small Vue 3 + Vite SPA with four screens — a dashboard with
summary counts, manage webhook endpoints, create an event, and browse delivery history/detail
— talking to the API over `fetch`. It includes the conventional bits you'd expect from a real
admin UI: toast notifications for action feedback, a confirm dialog before destructive
actions, paginated tables, and loading/empty states, all built as small local composables and
components rather than pulling in a UI framework.

Delivery happens off the request/response cycle via Laravel's queue, so creating an event
returns immediately; the actual HTTP calls to (potentially slow or unreachable) third-party
endpoints happen in the background with a bounded number of retries.

## 2. Architecture

```
 ┌────────────┐          ┌─────────────────────────┐
 │  Vue SPA   │──HTTP──▶│      Laravel API        │
 │ (frontend) │◀────────│   routes/api.php        │
 └────────────┘          └────────────┬────────────┘
                                      │
                    ┌─────────────────┼─────────────────┐
                    ▼                 ▼                 ▼
           WebhookEndpointCtrl  EventController   DeliveryController
                    │                 │                 │
                    ▼                 ▼                 ▼
             WebhookEndpoint     EventService      Delivery (read)
                  model               │
                                      ▼
                          creates Event + one Delivery
                          per active WebhookEndpoint,
                          dispatches DeliverWebhookJob
                                      │
                                      ▼
                          ┌───────────────────────┐
                          │  Queue (DB driver)    │
                          └───────────┬───────────┘
                                      ▼
                          ┌───────────────────────┐
                          │  DeliverWebhookJob    │
                          │  → WebhookDeliveryService
                          │  → Laravel HTTP client│
                          └───────────┬───────────┘
                                      ▼
                         updates the Delivery record
                         (status / http_status / attempt_count / last_error)
```

**Layers**

- **Controllers** (`app/Http/Controllers/Api`) — thin, only handle HTTP concerns
  (validation via Form Requests, calling a service, shaping the response via API Resources).
- **Services** (`app/Services`) — `EventService` owns idempotent event creation + fan-out to
  active endpoints; `WebhookDeliveryService` owns a single delivery attempt (HTTP call +
  recording the result).
- **Jobs** (`app/Jobs/DeliverWebhookJob.php`) — queued, retryable unit of work per delivery.
- **Models** — `WebhookEndpoint`, `Event`, `Delivery`, plain Eloquent models with the
  relationships between them.

## 3. Database Design

Three tables:

| Table | Purpose |
|---|---|
| `webhook_endpoints` | `name`, `url`, `is_active` |
| `events` | `event_id` (unique), `event_type`, `payload` (JSON) |
| `deliveries` | one row per `(event, webhook_endpoint)` pair: `status`, `http_status`, `attempt_count`, `last_error`, `delivered_at` |

`deliveries` has a unique constraint on `(event_id, webhook_endpoint_id)` — one event is
never delivered twice to the same endpoint.

## 4. API

All endpoints are under `/api` and return JSON. There is no authentication layer (see
[Technical Decisions](#6-technical-decisions)).

### Webhook endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/webhooks` | List endpoints (paginated) |
| `POST` | `/api/webhooks` | Create an endpoint |
| `PATCH` | `/api/webhooks/{id}` | Update an endpoint (e.g. toggle `is_active`) |
| `DELETE` | `/api/webhooks/{id}` | Remove an endpoint |

```bash
curl -X POST https://webhookdeliveryservice.test/api/webhooks \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"name": "CRM", "url": "https://crm.example.com/webhooks"}'

curl -X PATCH https://webhookdeliveryservice.test/api/webhooks/1 \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"is_active": false}'
```

Every endpoint gets a server-generated `secret` (returned in the response above), used to sign
outgoing deliveries — see [Signature verification](#signature-verification) below.

### Events

| Method | Path | Description |
|---|---|---|
| `POST` | `/api/events` | Create (and deliver) an event |
| `GET` | `/api/events` | List events |
| `GET` | `/api/events/{event_id}` | Show one event by its public `event_id` |

```bash
curl -X POST https://webhookdeliveryservice.test/api/events \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "event_type": "user.created",
        "event_id": "evt_01JABC123",
        "data": {"id": 123, "name": "John Doe", "email": "john@example.com"}
      }'
```

`event_id` is optional — if omitted, the server generates a ULID-based one. Response:

```json
{
  "data": {
    "id": 1,
    "event_id": "evt_01JABC123",
    "event_type": "user.created",
    "payload": { "id": 123, "name": "John Doe", "email": "john@example.com" },
    "deliveries_count": 2,
    "created_at": "2026-08-21T10:31:00.000000Z"
  }
}
```

### Deliveries

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/deliveries` | List deliveries (paginated). Filter with `?status=success\|pending\|failed` or `?event_id=evt_...` |
| `GET` | `/api/deliveries/{id}` | Show one delivery, including the event payload and endpoint |
| `POST` | `/api/deliveries/{id}/retry` | Manually retry a delivery — only valid when its status is `failed` |

```bash
curl "https://webhookdeliveryservice.test/api/deliveries?status=failed"

curl -X POST https://webhookdeliveryservice.test/api/deliveries/1/retry \
  -H "Accept: application/json"
```

Retrying sets the delivery back to `pending` and dispatches one more attempt through the same
`DeliverWebhookJob` — if that attempt still fails, it goes straight back to `failed` (a manual
retry is "one more try," not a fresh 3-attempt budget). Retrying anything that isn't currently
`failed` (still `pending`, or already `success`) returns a `422`.

### Signature verification

Every outgoing delivery includes an `X-Webhook-Signature` header:

```
X-Webhook-Signature: sha256=<hex-encoded HMAC-SHA256 of the raw JSON body, keyed with the endpoint's secret>
```

A receiver can verify a request genuinely came from this service (and wasn't tampered with in
transit) by recomputing the same HMAC over the exact request body using the endpoint's
`secret` and comparing it to the header, e.g. in PHP:

```php
$expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $endpointSecret);
hash_equals($expected, $request->header('X-Webhook-Signature'));
```

### Stats

| Method | Path | Description |
|---|---|---|
| `GET` | `/api/stats` | Summary counts (endpoints/active, events, deliveries by status) — powers the dashboard so the frontend doesn't have to fetch every list just to show totals |

## 5. Installation

### Docker (fastest way to try it)

Requires only Docker Desktop (or Docker Engine + Compose). No PHP, Composer, or Node needed
on your machine — everything runs in containers.

```bash
docker compose up --build
```

That single command builds the backend and frontend images, runs migrations once (via a
one-off `migrate` service), then starts the API, the queue worker, and the frontend:

- Frontend: http://localhost:5173
- API: http://localhost:8000/api

Each container bootstraps its own `.env` from `.env.example` and generates an app key on
first start — there's nothing to configure by hand. The SQLite database lives in a named
Docker volume (`sqlite_data`) so it survives `docker compose down` / `up` restarts; run
`docker compose down -v` to wipe it and start fresh (e.g. after changing a migration).

Useful follow-up commands:

```bash
docker compose exec app php artisan test        # run the backend test suite in-container
docker compose exec app php artisan db:seed     # load the sample data (see below)
docker compose logs -f queue                     # watch deliveries get processed
```

> If port 8000 or 5173 is already in use on your machine, change the left-hand side of the
> `ports:` mapping for that service in `docker-compose.yml`.

### Native setup (without Docker)

#### Requirements

- PHP 8.2+ (developed against 8.4)
- Composer
- Node.js 20+ (the frontend's `engines` field targets 22.18+/24.12+, but any recent LTS works)
- SQLite (bundled with PHP's `pdo_sqlite` extension — no separate server needed)

#### Backend

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite      # (on Windows: New-Item database/database.sqlite)
php artisan migrate

php artisan serve                   # http://localhost:8000
php artisan queue:work              # in a second terminal — processes webhook deliveries
```

Optionally, seed some realistic sample data (5 endpoints, 20 events, ~80 deliveries spread
across `success`/`pending`/`failed`) so the UI/API have something to look at immediately:

```bash
php artisan migrate:fresh --seed
```

`WebhookDeliverySeeder` writes delivery rows directly with a settled status rather than
firing real HTTP requests — it's for populating the UI, not for exercising the delivery
pipeline (use the API in section 4 above for that, with `queue:work` running).

If you use [Laravel Herd](https://herd.laravel.com), the site is served automatically at
`APP_URL` (see `.env`) once the project directory is picked up — you only need the
`queue:work` process to actually run deliveries.

#### Frontend

```bash
cd frontend
cp .env.example .env   # set VITE_API_BASE_URL to wherever the API is served
npm install
npm run dev             # http://localhost:5173
```

> If `npm install` reports a peer dependency conflict between `oxlint` and
> `eslint-plugin-oxlint`, it's a version-pin mismatch in the dev tooling, unrelated to the
> app itself — install with `npm install --legacy-peer-deps`.

## 6. Technical Decisions

**Queue-based delivery.** `POST /api/events` returns as soon as the `Delivery` rows are
created; the HTTP calls to external endpoints happen in `DeliverWebhookJob`, run by
`php artisan queue:work` against the `database` queue driver. This means a slow or hanging
endpoint never blocks the event-creation request, and one endpoint being unreachable never
affects delivery to the others. The database driver was chosen over Redis to keep the stack
to what the assessment already ships with (SQLite + Laravel) — no extra infrastructure to run
locally.

**One delivery row per (event, endpoint) pair.** Enforced with a DB-level unique constraint.
This is what makes "did event X reach endpoint Y" a single, independently-retryable record
instead of something derived from logs.

**Idempotent event creation.** `events.event_id` is unique at the database level.
`EventService::createEvent()` looks the event up by `event_id` first; if it already exists,
the existing record is returned as-is and **no new deliveries are created**. This means
retrying a `POST /api/events` call with the same `event_id` (e.g. after a network blip on the
caller's side) is safe and won't double-fire webhooks.

**Retry strategy.** Up to `Delivery::MAX_ATTEMPTS` (3) attempts per delivery. A delivery stays
`pending` after a failed attempt as long as attempts remain — `pending` here means "still in
play," not just "not yet tried" — and only becomes `failed` once the budget is exhausted. The
job re-queues itself with a short escalating delay (10s, then 30s) between attempts via
`$this->release()`, rather than throwing and relying on Laravel's generic exception-retry
path — the failure paths we care about (non-2xx, timeout, connection error) are all
*expected* outcomes of calling someone else's server, not application bugs, so they're
handled as normal control flow inside `WebhookDeliveryService`, not exceptions.

**Timeouts.** The HTTP client uses a 5-second timeout (`WebhookDeliveryService::TIMEOUT_SECONDS`)
so an endpoint that never responds can't tie up a queue worker indefinitely.

**Only active endpoints receive new events.** `EventService` looks up
`WebhookEndpoint::where('is_active', true)` at event-creation time. Disabling an endpoint has
no effect on deliveries already in flight or already recorded — it only stops it from
receiving *future* events, which matches how a real "pause this integration" toggle should
behave.

**No API authentication, but signed outgoing webhooks.** The assessment explicitly lists an
auth system as out of scope, so the API itself is open by design — in a real deployment this
would sit behind an API key or Sanctum token on the write endpoints. Outgoing deliveries *are*
signed, though: each endpoint gets a server-generated `secret`, and every request carries an
`X-Webhook-Signature: sha256=...` header (HMAC-SHA256 over the exact raw JSON body). That's
the half of "authentication" that matters most for a webhook system specifically — it lets a
receiver trust that a payload genuinely came from us — so it felt worth including even with
the API itself left open.

**Manual retry as a separate, deliberate action.** Automatic retries (above) exist to ride out
transient failures without a human involved. `POST /api/deliveries/{id}/retry` is a distinct
mechanism for after that budget is exhausted — a person decides to redrive it (e.g. once
they've fixed a misconfigured endpoint URL). It's intentionally restricted to `failed`
deliveries only: retrying a `pending` one would race the job that's already about to run it,
and retrying a `success` one has no purpose. It runs through the exact same `DeliverWebhookJob`
→ `WebhookDeliveryService` path as an automatic attempt, so there's only one delivery code path
to reason about, not two.

**Backend/frontend split.** The brief asked specifically for a Laravel API + a separate
Vue app rather than server-rendered Blade, so the two are decoupled over HTTP/JSON
(CORS is left at Laravel's framework default, which already allows all origins on `/api/*`,
since this is a local assessment project rather than a multi-tenant public API).

## 7. Edge Cases & Limitations

Handled:

- **Duplicate `event_id`** → ignored, returns the original event, no new deliveries.
- **Disabled endpoint** → excluded from delivery fan-out at creation time.
- **Non-2xx response, connection failure, DNS failure, timeout** → all caught, recorded on
  the delivery with a human-readable `last_error`, and retried up to the attempt limit.
- **Invalid webhook URL / malformed event payload** → rejected at the request-validation
  layer (`422` with field errors) before anything is persisted.
- **Endpoint deleted while deliveries reference it** → deliveries cascade-delete with the
  endpoint (acceptable for this scope; a production system would likely soft-delete
  endpoints instead so delivery history survives removal).
- **Permanently failed delivery** → filterable via `status=failed` in the delivery list, and
  redrivable with `POST /api/deliveries/{id}/retry` (button in the UI too) without needing
  direct database access.
- **Receiver verifying authenticity** → every delivery is HMAC-signed (see
  [Signature verification](#signature-verification)) so a receiver isn't just trusting
  whatever hits their endpoint.

Known limitations (reasonable to leave out given the assessment's scope):

- No authentication/authorization on the API itself (see Technical Decisions above) — signing
  covers "did this webhook really come from us," not "who's allowed to call this API."
- Retry delays are fixed (10s/30s), not a configurable exponential backoff.
- Pagination is a flat page size (25) with no cursor/keyset pagination — fine for the data
  volumes this exercise produces.
- The event `payload` accepts any JSON object; there's no per-`event_type` schema validation.
- The Docker Compose setup optimizes for "clone and run it," not active development inside
  containers — app/frontend source is baked into the image at build time rather than bind-
  mounted, so a code change needs `docker compose up --build` to take effect. The native setup
  above (or Herd) is the better fit for actively working on the code.

## 8. Testing

**Backend** (Pest, run from the project root):

```bash
php artisan test
```

96 tests covering:

- Webhook endpoint CRUD, including validation errors.
- Event creation, duplicate `event_id` handling, and that only active endpoints receive
  deliveries (feature tests, using `Http::fake()` so nothing hits the network).
- `WebhookDeliveryService`: success (2xx), non-2xx failure, connection-exception failure, a
  delivery being marked `failed` for good once the retry budget is exhausted, and that every
  request is signed with the correct HMAC over the exact raw body.
- `DeliverWebhookJob`: retrying up to the max attempt count, stopping early on a later
  success, and handling a missing delivery gracefully.
- Delivery listing/filtering, the delivery detail endpoint, and manually retrying a delivery
  (including rejecting a retry on anything that isn't currently `failed`).
- The `/api/stats` summary endpoint.

Tests run against an in-memory SQLite database with `QUEUE_CONNECTION=sync` (see
`phpunit.xml`), so dispatched jobs execute inline within the same request/test — no worker
process needed to see delivery results in feature tests.

**Frontend:**

```bash
cd frontend
npm run test:unit
```

Covers `src/api/client.js` (`src/api/__tests__/client.spec.js`) — the one piece of frontend
code with actual logic worth testing: parsing a successful JSON response, turning a `422`
into an `ApiError` with its field errors attached, treating `204 No Content` as no body
instead of trying to parse one, and dropping empty query-string filters. Given the
assessment's time budget, testing effort was otherwise concentrated on the backend (retries,
idempotency, failure handling), while the Vue views themselves are thin and were verified
manually in the browser rather than with component tests.
