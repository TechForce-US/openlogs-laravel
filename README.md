# openlogs-laravel

A Laravel bridge for [OpenLogs](https://github.com/jmstewart1127/openlogs): adds a
first-class `openlogs` log channel with **optional queued delivery** on a dedicated
queue. Built on the framework-agnostic
[`techforce/openlogs-monolog`](https://github.com/techforce/openlogs-monolog)
handler.

## Install

```sh
composer require techforce/openlogs-laravel
```

The service provider is auto-discovered. Publish the config if you want to tweak
defaults:

```sh
php artisan vendor:publish --tag=openlogs-config
```

## Configure

Set your OpenLogs connection in `.env`:

```dotenv
OPENLOGS_URL=https://logs.example.com
OPENLOGS_API_KEY=your-project-api-key
```

Add the channel to `config/logging.php`:

```php
'channels' => [
    'openlogs' => [
        'driver' => 'custom',
        'via'    => \TechForce\OpenLogs\Laravel\OpenLogsChannel::class,
    ],

    // ...or send everything there via a stack:
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['single', 'openlogs'],
    ],
],
```

Then log as usual:

```php
Log::channel('openlogs')->info('User signed up', ['user_id' => 42]);
```

Records are buffered and delivered to OpenLogs as a single batch at the end of the
request (or when the buffer fills).

## Config reference (`config/openlogs.php`)

| Key                  | Env                        | Default    | Description                                   |
|----------------------|----------------------------|------------|-----------------------------------------------|
| `url`                | `OPENLOGS_URL`             | —          | OpenLogs base URL                             |
| `api_key`            | `OPENLOGS_API_KEY`         | —          | Project API key                               |
| `level`              | `OPENLOGS_LEVEL`           | `debug`    | Minimum level                                 |
| `buffer_limit`       | `OPENLOGS_BUFFER_LIMIT`    | `500`      | Records buffered before an automatic flush    |
| `timeout`            | `OPENLOGS_TIMEOUT`         | `5.0`      | HTTP timeout (seconds)                        |
| `fallback_channel`   | `OPENLOGS_FALLBACK_CHANNEL`| `single`   | Channel used when delivery fails              |
| `queue.enabled`      | `OPENLOGS_QUEUE`           | `false`    | Deliver via a background job                  |
| `queue.connection`   | `OPENLOGS_QUEUE_CONNECTION`| default    | Queue connection for the job                  |
| `queue.queue`        | `OPENLOGS_QUEUE_NAME`      | `openlogs` | Queue name (dedicated — never `default`)      |

## Queued delivery

To move the HTTP POST off the request and onto a background worker:

```dotenv
OPENLOGS_QUEUE=true
```

Each batch is dispatched as a `SendLogBatch` job carrying the normalized entries.
It targets a **dedicated `openlogs` queue** by default so log shipping never
competes with your application's jobs. Run a worker for it:

```sh
php artisan queue:work --queue=openlogs
```

Failure handling in queued mode: the job uses the queue's retry/backoff for
transient outages, and after retries are exhausted it replays the batch to your
`fallback_channel`.

> **Note:** don't route your queue worker's own logs through the `openlogs`
> channel — that can create a delivery loop. The `fallback_channel` is
> automatically guarded against pointing back at `openlogs`.

## Fallback

If OpenLogs is unreachable (or returns an error), the batch is written to
`fallback_channel` (default `single`) so logs are never lost. A fallback that
would resolve back to the `openlogs` channel is detected and disabled.

## License

MIT
