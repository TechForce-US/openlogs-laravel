## Why

`techforce/openlogs-monolog` is a framework-agnostic Monolog handler for shipping
logs to OpenLogs. It works in Laravel today, but only with hand-wiring: a user
must define a `custom` channel, build the handler in a factory, and supply a
fallback handler. It also has no way to move delivery off the request — every
flush POSTs inline.

This bridge, `techforce/openlogs-laravel`, makes OpenLogs a **first-class Laravel
log channel**: `composer require`, set two env vars, add a channel, done. It also
adds **queued delivery** — dispatching each batch to a background job instead of
POSTing inline — on a **dedicated, configurable queue** so log shipping never
pollutes the app's default queue or blocks the request.

The bridge lives in its own repository (`../openlogs-laravel`), separate from the
agnostic core, and depends on it.

## What Changes

- Add a new **Laravel bridge package**, `composer require techforce/openlogs-laravel`,
  depending on `techforce/openlogs-monolog` and `illuminate/*`.
- **Auto-discovered `ServiceProvider`** registers a publishable
  `config/openlogs.php` and the channel/deliverer wiring — no manual provider
  registration.
- **`openlogs` log channel**: users add a channel driven by `OPENLOGS_URL` /
  `OPENLOGS_API_KEY`; the bridge's factory builds the core handler for them
  (buffer size, timeout, level all from config).
- **Queued delivery (opt-in)** via a `QueuedDeliverer` implementing the core's
  `BatchDeliverer`: instead of POSTing inline, it dispatches a `SendLogBatch` job
  carrying the already-normalized wire payload; the job runs the core's
  `SyncGuzzleDeliverer` inside the worker. Sync (inline) delivery remains the
  default when queueing is disabled.
- **Dedicated, configurable queue**: the job targets a configurable connection and
  queue name, defaulting the queue to **`openlogs`** (never the app's `default`
  queue), so log shipping is isolated and independently scalable.
- **Fallback wired to a Laravel channel**: config names a local `fallback_channel`
  (default `single`); the bridge resolves its handlers and passes them to the core
  as the fallback handler, guarded so it can never point back at the `openlogs`
  channel (no circular logging).
- **README**: install, the channel snippet, env vars, and the queue/worker setup
  (including `php artisan queue:work --queue=openlogs`).

## Capabilities

### New Capabilities

- `laravel-channel`: Installing and configuring OpenLogs as a Laravel log channel
  — auto-discovered service provider, publishable config, the `openlogs` channel
  factory, and env-driven URL/API key/handler tunables.
- `queued-delivery`: Opt-in background delivery — the `QueuedDeliverer`, the
  `SendLogBatch` job, dedicated connection/queue selection (defaulting to a
  dedicated `openlogs` queue, not `default`), and how queued failure semantics
  combine job retries with the core's local fallback.
- `laravel-fallback`: Resolving a Laravel `fallback_channel` into a Monolog
  fallback handler for the core, with the circular-guard preventing it from
  resolving back to the `openlogs` channel.

### Modified Capabilities

<!-- none — new bridge package; the agnostic core's behavior is unchanged and
     consumed via its BatchDeliverer seam. -->

## Impact

- **New repository/package**: `techforce/openlogs-laravel` at `../openlogs-laravel`.
  - `composer.json`: requires `techforce/openlogs-monolog`, `illuminate/support`,
    `illuminate/bus`/`illuminate/queue`; Laravel package-discovery `extra` block.
  - `src/`: `OpenLogsServiceProvider`, the channel factory, `QueuedDeliverer`
    (implements the core `BatchDeliverer`), the `SendLogBatch` job (`ShouldQueue`),
    and fallback-channel resolution.
  - `config/openlogs.php`: url, api_key, buffer size, timeout, level,
    `fallback_channel`, and a `queue` block (`enabled`, `connection`, `queue` name
    default `openlogs`).
- **Depends on the core seam**: uses `techforce/openlogs-monolog`'s
  `BatchDeliverer` interface and `SyncGuzzleDeliverer`; adds no new server
  contract (still `POST /api/ingest/batch`).
- **Requires a running queue worker** only when queued delivery is enabled;
  otherwise delivery is inline (sync). Document the dedicated `--queue=openlogs`
  worker.
- **Recursion note**: queued jobs must not route their own logs back through the
  `openlogs` channel; the fallback circular-guard and docs address this.
- **Consumer impact**: additive — a new optional log channel; existing channels
  and queues are untouched.
