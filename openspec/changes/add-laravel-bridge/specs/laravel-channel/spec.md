## ADDED Requirements

### Requirement: Auto-discovered service provider and publishable config

The bridge SHALL register a service provider via Laravel package discovery that
publishes a `config/openlogs.php` file, so no manual provider registration is
required.

#### Scenario: Install without manual wiring

- **WHEN** the package is installed via Composer into a Laravel app
- **THEN** its service provider is auto-discovered and `php artisan vendor:publish`
  can publish `config/openlogs.php`

### Requirement: OpenLogs log channel

The bridge SHALL provide a `custom` channel factory so users can define an
`openlogs` channel driven by `OPENLOGS_URL` and `OPENLOGS_API_KEY`.

#### Scenario: Logging through the openlogs channel

- **WHEN** an app configures an `openlogs` channel with the bridge's `via` factory
  and logs a message to it
- **THEN** the message is delivered to OpenLogs using the core handler configured
  from the channel config

#### Scenario: Handler tunables come from config

- **WHEN** the channel config or `config/openlogs.php` sets buffer size, timeout,
  or level
- **THEN** the built handler uses those values, falling back to defaults otherwise
