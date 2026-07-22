## Context

`techforce/openlogs-monolog` provides a framework-agnostic Monolog handler with a
`BatchDeliverer` seam (default `SyncGuzzleDeliverer`, inline Guzzle POST) and an
injectable fallback `HandlerInterface`. This bridge makes OpenLogs a first-class
Laravel log channel and adds queued delivery on a dedicated queue, by plugging into
that seam — it adds no new server contract.

## Goals / Non-Goals

**Goals:**
- Zero-ceremony install: auto-discovered provider, publishable config, an
  `openlogs` channel driven by env.
- Opt-in queued delivery that moves the Guzzle POST off the request into a worker.
- A dedicated, configurable queue so log shipping never lands on `default`.
- Fallback wired to a real Laravel channel, guarded against recursion.

**Non-Goals:**
- Re-implementing handler/normalization/delivery — all reused from the core.
- Changing the OpenLogs server or wire contract.
- Symfony support (a separate bridge could mirror this design).

## Decisions

### D1. Channel via Laravel's `custom` driver

Users add a channel with `'driver' => 'custom', 'via' => OpenLogsChannel::class`.
`OpenLogsChannel::__invoke(array $config)` builds the core handler, selecting the
deliverer per config, and returns a `Monolog\Logger`.

*Alternative:* the `monolog` driver pointing straight at the handler class —
rejected: it can't express deliverer selection or fallback resolution cleanly.

### D2. `QueuedDeliverer` implements the core seam

When `queue.enabled` is true the channel uses a `QueuedDeliverer` (implements
`BatchDeliverer`) whose `deliver($entries, $records)` dispatches a `SendLogBatch`
job carrying the **already-normalized `$entries`** (plain, serializable — never
`LogRecord` objects). When disabled (default), the channel uses the core
`SyncGuzzleDeliverer` directly (inline).

The job does **not** reuse the core `SyncGuzzleDeliverer` in the worker: that
deliverer swallows failures (it never throws), which would defeat the queue's
retry mechanism. Instead `SendLogBatch::handle()` performs its own Guzzle POST and
**throws** on a transport error or non-201 response, so the queue retries it.

*Alternative:* queue the `LogRecord`s — rejected: heavier to serialize and would
re-open poison-record risk in the worker; normalization must happen before queueing.
*Alternative:* reuse `SyncGuzzleDeliverer` in the job — rejected: its swallow-and-
fallback behavior hides failures from the queue, so retries never fire.

### D3. Dedicated queue, never `default`

`SendLogBatch` is dispatched with `->onConnection($config['queue']['connection'])
->onQueue($config['queue']['queue'])`, where the queue name defaults to `openlogs`.
Operators run `php artisan queue:work --queue=openlogs` to isolate/scale it.

*Alternative:* use the app default queue — rejected: log volume would compete with
business jobs; the user explicitly wants isolation.

### D4. Failure semantics in queued mode

The job relies on Laravel's retry/backoff (`$tries`) for transient OpenLogs
outages. After retries are exhausted, `failed()` replays to the fallback channel.
Because original `LogRecord` objects are not serialized onto the queue, the job
replays the **normalized entries** — writing each to the fallback channel via
`Log::channel($fallback)->log($level, $message, $context)`. So sync mode = replay
original records immediately; queued mode = retries, then replay entries. In both
cases the fallback channel is circular-guarded (D5).

### D5. Fallback channel resolution + circular guard

Config `fallback_channel` (default `single`) is resolved to that channel's Monolog
handlers, wrapped so the core sees a single fallback `HandlerInterface`. If
`fallback_channel` resolves to (or transitively includes) the `openlogs` channel,
the bridge disables fallback and emits a one-time warning — no circular logging.

## Risks / Trade-offs

- **Queued mode needs a running worker; logs are delayed until processed.** →
  Document the dedicated worker; sync remains the zero-infra default.
- **Worker logging its own activity through `openlogs` could recurse.** → Circular
  guard (D5) plus docs: keep worker/queue channels off `openlogs`.
- **Serialized payload sits in the queue store.** → It is compact normalized JSON;
  bounded by the core's per-entry truncation and batch size.
- **Job carries the API key (in the deliverer config).** → Pass endpoint/key via
  the job's config, not logged; rely on the queue store's protections.

## Open Questions

- Should the bridge offer a `FingersCrossedHandler` "errors-only" channel preset?
  (Lean: defer, follow the core.)
- Provide an artisan command to test connectivity (`openlogs:ping`)? (Lean: nice
  to have, defer.)
