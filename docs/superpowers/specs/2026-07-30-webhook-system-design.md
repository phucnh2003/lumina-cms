# Webhook System — Design

## Goal

Let external systems subscribe to model lifecycle events (created/updated/deleted/custom) in Lumina CMS and receive signed HTTP POST payloads. Only models that explicitly opt in can fire webhooks — not every model.

## Scope (first pass)

Models: `Product`, `Order`, `User` (customer). Other models can opt in later by adding the trait — no code change needed elsewhere.

## Architecture

### 1. Model opt-in via trait

A new `plugins/webhook` plugin ships a `HasWebhookEvents` trait. A model opts in by using the trait and declaring which events it fires:

```php
class Order extends Model
{
    use HasWebhookEvents;

    protected static function webhookEvents(): array
    {
        return ['created', 'status_changed', 'paid', 'cancelled'];
    }
}
```

- The trait hooks Eloquent's `created`/`updated`/`deleted` observers and, for domain-specific events (`status_changed`, `paid`), exposes a `fireWebhookEvent(string $event)` method the model calls explicitly (e.g. inside its status-change method).
- Models without the trait are invisible to the webhook system — no central registry lists them.
- Event name is namespaced as `{model-slug}.{event}` (e.g. `order.paid`) in the payload.

### 2. Subscription storage

New tables (migrations in `plugins/webhook/database/migrations`):

- `webhook_endpoints`: `id, url, secret, events (json array of "order.paid" style strings), active (bool), timestamps`
- `webhook_deliveries`: `id, webhook_endpoint_id, event, payload (json), response_status, response_body, attempt, status (pending/success/failed), timestamps`

An endpoint's `events` array is the only place "which model + which event" is configured at runtime — the trait only defines what's *possible* to subscribe to.

### 3. Delivery: queued job with retry

- Firing an event dispatches `DispatchWebhookJob` per matching active endpoint (looked up by `events` JSON contains).
- Job builds payload `{event, data, timestamp}`, signs body with HMAC-SHA256 using the endpoint's `secret`, sends as `X-Webhook-Signature` header, POSTs to `url`.
- Uses Laravel's queue (repo already runs Horizon) with default retry/backoff (e.g. 3 tries, exponential backoff), records outcome in `webhook_deliveries`.
- Failure after final retry marks delivery `failed`; endpoint stays active (no auto-disable in v1).

### 4. Dashboard page (admin UI)

New CRUD page under the webhook plugin's dashboard routes, following the existing CRUD/trait pattern (`plugins/webhook/resources/js`):

- **List view**: table of endpoints (URL, active toggle, event count, last delivery status).
- **Create/edit form**: URL, secret (auto-generate button + reveal/copy), multi-select for events — options populated dynamically from all models' `webhookEvents()` across models using `HasWebhookEvents` (backend endpoint lists available `{model}.{event}` strings).
- **Deliveries log tab**: per-endpoint table of recent deliveries (event, status, attempt, response code, timestamp), read-only, with a "retry" action for failed ones.

### 5. Plugin registration

`plugins/webhook` follows the standard plugin skeleton (`composer.json`, `src/Providers/WebhookServiceProvider.php`, `routes/`, `database/migrations/`, `resources/js/`), registered in `plugins/core/configs/plugins.php` as `'webhook' => ['enable' => true]`.

## Out of scope (v1)

- Auto-disabling endpoints after repeated failures
- UI for browsing raw available events beyond the multi-select (no separate "event catalog" page)
- Rate limiting / batching multiple events into one delivery
