## 1. Package scaffolding

- [x] 1.1 Create `composer.json` (requires `techforce/openlogs-monolog`, `illuminate/support`, `illuminate/bus`, `illuminate/queue`; PHP constraint)
- [x] 1.2 Add Laravel package-discovery `extra.laravel.providers` entry
- [x] 1.3 Add dev tooling (Orchestra Testbench, PHPUnit) and `.gitignore`

## 2. Service provider + config

- [x] 2.1 Implement `OpenLogsServiceProvider` (register + publish config)
- [x] 2.2 Create `config/openlogs.php` (url, api_key, buffer, timeout, level, fallback_channel, queue block)

## 3. Channel factory

- [x] 3.1 Implement `OpenLogsChannel::__invoke(array $config): Monolog\Logger`
- [x] 3.2 Select deliverer from config (sync default vs queued)
- [x] 3.3 Resolve `fallback_channel` handlers and apply the circular guard

## 4. Queued delivery

- [x] 4.1 Implement `QueuedDeliverer` (implements core `BatchDeliverer`) dispatching `SendLogBatch`
- [x] 4.2 Implement `SendLogBatch` job (`ShouldQueue`) carrying normalized entries; run `SyncGuzzleDeliverer` in `handle()`
- [x] 4.3 Target configurable connection + queue name (default `openlogs`)
- [x] 4.4 Implement `failed()` to replay records to the fallback channel after retries

## 5. Tests

- [x] 5.1 Channel factory builds a working logger from config
- [x] 5.2 Queue disabled → inline delivery; enabled → job dispatched with normalized payload
- [x] 5.3 Job dispatched onto the configured/default `openlogs` queue and connection
- [x] 5.4 Fallback resolves the channel; circular guard disables + warns once

## 6. Docs

- [x] 6.1 README: install, `openlogs` channel snippet, env vars
- [x] 6.2 README: enabling the queue and running `php artisan queue:work --queue=openlogs`
- [x] 6.3 README: fallback config and the recursion caveat
