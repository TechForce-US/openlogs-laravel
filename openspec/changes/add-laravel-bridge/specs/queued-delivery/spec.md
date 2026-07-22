## ADDED Requirements

### Requirement: Opt-in queued delivery

The bridge SHALL provide a `QueuedDeliverer` (implementing the core `BatchDeliverer`)
that dispatches a job to deliver the batch in a worker, used only when queued
delivery is enabled in config; otherwise inline synchronous delivery is the default.

#### Scenario: Queue enabled dispatches a job

- **WHEN** `queue.enabled` is true and a batch flushes
- **THEN** a `SendLogBatch` job carrying the normalized entries is dispatched, and
  the request does not POST to OpenLogs inline

#### Scenario: Queue disabled delivers inline

- **WHEN** `queue.enabled` is false (default) and a batch flushes
- **THEN** the batch is delivered synchronously via the core `SyncGuzzleDeliverer`

#### Scenario: Only normalized entries are queued

- **WHEN** the job is dispatched
- **THEN** its payload is the plain normalized wire entries, not Monolog record
  objects

### Requirement: Dedicated, configurable queue

The dispatched job SHALL target a configurable connection and queue name, defaulting
the queue name to `openlogs` and never the application's `default` queue.

#### Scenario: Default dedicated queue

- **WHEN** no queue name is configured
- **THEN** the job is dispatched onto the `openlogs` queue

#### Scenario: Configured connection and queue are honored

- **WHEN** `queue.connection` and `queue.queue` are set in config
- **THEN** the job is dispatched onto that connection and queue

### Requirement: Queued failure semantics

The job SHALL rely on the queue's retry/backoff for transient failures and fall back
to the local channel only after retries are exhausted.

#### Scenario: Transient failure retried then fell back

- **WHEN** delivery in the worker fails and retry attempts remain
- **THEN** the job is retried, and only after attempts are exhausted are the records
  replayed to the fallback channel
