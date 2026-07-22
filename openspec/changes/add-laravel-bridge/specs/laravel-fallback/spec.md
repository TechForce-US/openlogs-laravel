## ADDED Requirements

### Requirement: Resolve a Laravel channel as the fallback handler

The bridge SHALL resolve a configured `fallback_channel` (default `single`) into a
Monolog fallback handler and pass it to the core handler, so failed deliveries are
written to a real Laravel log channel.

#### Scenario: Failed delivery writes to the fallback channel

- **WHEN** delivery to OpenLogs fails and `fallback_channel` is `single`
- **THEN** the records are written to the `single` channel's log destination

### Requirement: Circular fallback guard

The bridge SHALL prevent the fallback from resolving back to the `openlogs` channel,
disabling fallback and warning once if such a configuration is detected.

#### Scenario: Fallback points at openlogs

- **WHEN** `fallback_channel` resolves to (or transitively includes) the `openlogs`
  channel
- **THEN** fallback is disabled and a one-time warning is emitted, and no recursive
  logging loop occurs
