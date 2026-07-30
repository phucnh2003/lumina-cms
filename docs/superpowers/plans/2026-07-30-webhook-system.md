# Webhook System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `plugins/webhook` plugin that lets `Product`, `Order`, and `User` (customer) fire signed HTTP webhooks on lifecycle events, configurable per-endpoint from a new dashboard page, with queued delivery, retry, and a delivery log.

**Architecture:** A `HasWebhookEvents` trait (opt-in, no central model registry) hooks Eloquent lifecycle events and lets a model fire domain events explicitly. Firing an event queries active `WebhookEndpoint` rows whose `events` JSON column contains the fired `{model}.{event}` name, and dispatches one `DispatchWebhookJob` per match. The job signs the payload (HMAC-SHA256), POSTs it, and records the outcome in `webhook_deliveries`. The dashboard uses the existing generic `items/{resource}` CRUD (`WebhookEndpoint` + `QueryBuilder` trait) plus one small custom controller endpoint that lists available `{model}.{event}` options for the form's multi-select.

**Tech Stack:** Laravel (Eloquent, Queue/Horizon, `Illuminate\Support\Facades\Http`), Inertia + React dashboard pages (existing `plugins/cms` conventions), Pest/PHPUnit feature tests.

---

## File Structure

```
plugins/webhook/
├── composer.json
├── database/migrations/
│   ├── 2026_07_30_000001_create_webhook_endpoints_table.php
│   └── 2026_07_30_000002_create_webhook_deliveries_table.php
├── routes/web.php
├── src/
│   ├── Controllers/WebhookEventsController.php
│   ├── Jobs/DispatchWebhookJob.php
│   ├── Models/WebhookEndpoint.php
│   ├── Models/WebhookDelivery.php
│   ├── Providers/WebhookServiceProvider.php
│   └── Traits/HasWebhookEvents.php
└── resources/js/pages/webhook-endpoints/
    ├── index.tsx
    └── form.tsx
```

Modified:
- `plugins/core/configs/plugins.php` — add `'webhook' => ['enable' => true]`
- `composer.json` (root) — add `"lumina/webhook": "*"` to `require`
- `plugins/e-commerce/src/Models/Product.php` — add trait + `webhookEvents()`
- `plugins/e-commerce/src/Models/Order.php` — add trait + `webhookEvents()` + explicit fire calls for `status_changed`/`paid`/`cancelled`
- `app/Models/User.php` — add trait + `webhookEvents()`

---

### Task 1: Scaffold the plugin package

**Files:**
- Create: `plugins/webhook/composer.json`
- Modify: `composer.json` (root)
- Modify: `plugins/core/configs/plugins.php`

- [ ] **Step 1: Create the plugin's composer.json**

```json
{
    "name": "lumina/webhook",
    "description": "Lumina Webhook Plugin — outgoing webhook endpoints and delivery",
    "type": "library",
    "autoload": {
        "psr-4": { "Lumina\\Webhook\\": "src/" }
    },
    "extra": {
        "laravel": { "providers": ["Lumina\\Webhook\\Providers\\WebhookServiceProvider"] }
    }
}
```

- [ ] **Step 2: Register the package in the root composer.json**

Add `"lumina/webhook": "*"` alongside the other `"lumina/*": "*"` entries in the root `require` block.

Run:
```bash
composer update lumina/webhook
```
Expected: composer resolves the local path repo and adds `Lumina\Webhook\Providers\WebhookServiceProvider` to `bootstrap/cache/packages.php` (or discovers it via the package's `extra.laravel.providers`, matching how e.g. `lumina/redirects` is wired).

- [ ] **Step 3: Enable the plugin flag**

In `plugins/core/configs/plugins.php`, add (alphabetically, matching existing style):
```php
    'webhook' => ['enable' => true],
```

- [ ] **Step 4: Commit**

```bash
git add plugins/webhook/composer.json composer.json composer.lock plugins/core/configs/plugins.php
git commit -m "chore(webhook): scaffold plugin package"
```

---

### Task 2: Migrations for `webhook_endpoints` and `webhook_deliveries`

**Files:**
- Create: `plugins/webhook/database/migrations/2026_07_30_000001_create_webhook_endpoints_table.php`
- Create: `plugins/webhook/database/migrations/2026_07_30_000002_create_webhook_deliveries_table.php`

- [ ] **Step 1: Write the endpoints migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events')->nullable();
            $table->string('secret')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
```

- [ ] **Step 2: Write the deliveries migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('status')->default('pending'); // pending|success|failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
```

- [ ] **Step 3: Run migrations**

Run: `php artisan migrate`
Expected: both tables created, no errors. (`RegistersPlugins` auto-loads `plugins/webhook/database/migrations` because `plugins.webhook.enable` is `true`.)

- [ ] **Step 4: Commit**

```bash
git add plugins/webhook/database/migrations
git commit -m "feat(webhook): add webhook_endpoints and webhook_deliveries tables"
```

---

### Task 3: `WebhookEndpoint` and `WebhookDelivery` models

**Files:**
- Create: `plugins/webhook/src/Models/WebhookEndpoint.php`
- Create: `plugins/webhook/src/Models/WebhookDelivery.php`
- Modify: `plugins/webhook/src/Providers/WebhookServiceProvider.php` (created in this task)

- [ ] **Step 1: Write `WebhookEndpoint`**

```php
<?php

namespace Lumina\Webhook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lumina\Core\Traits\QueryBuilder;

class WebhookEndpoint extends Model
{
    use QueryBuilder;

    const STATUS_LIST = [
        'active' => ['label' => 'Đang bật', 'text' => '#15803d', 'background' => '#dcfce7'],
        'inactive' => ['label' => 'Tắt', 'text' => '#6b7280', 'background' => '#f3f4f6'],
    ];

    protected $fillable = ['name', 'url', 'events', 'secret', 'status'];

    protected static function booted(): void
    {
        static::creating(function (self $endpoint) {
            if (empty($endpoint->secret)) {
                $endpoint->secret = Str::random(40);
            }
        });
    }

    public static function rules(?self $ignoring = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:255'],
            'events' => ['nullable', 'array'],
            'events.*' => ['string'],
            'secret' => ['nullable', 'string'],
            'status' => ['required', Rule::in(array_keys(self::STATUS_LIST))],
        ];
    }

    protected function casts(): array
    {
        return ['events' => 'array'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
```

- [ ] **Step 2: Write `WebhookDelivery`**

```php
<?php

namespace Lumina\Webhook\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Lumina\Core\Traits\QueryBuilder;

class WebhookDelivery extends Model
{
    use QueryBuilder;

    protected $fillable = [
        'webhook_endpoint_id', 'event', 'payload', 'response_status', 'response_body', 'attempt', 'status',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
```

- [ ] **Step 3: Write the `WebhookServiceProvider`**

```php
<?php

namespace Lumina\Webhook\Providers;

use Illuminate\Support\ServiceProvider;
use Lumina\Core\Traits\RegistersPlugins;

class WebhookServiceProvider extends ServiceProvider
{
    use RegistersPlugins {
        register as registerPlugins;
    }

    public function register(): void
    {
        $this->registerPlugins();

        config([
            'core.model_namespaces' => array_merge(
                config('core.model_namespaces', []),
                ['Lumina\\Webhook\\Models']
            ),
        ]);
    }
}
```

- [ ] **Step 4: Verify resource resolution**

Run:
```bash
php artisan tinker --execute="dd(config('core.model_namespaces'));"
```
Expected: array contains `Lumina\Webhook\Models`.

- [ ] **Step 5: Commit**

```bash
git add plugins/webhook/src/Models plugins/webhook/src/Providers
git commit -m "feat(webhook): add WebhookEndpoint/WebhookDelivery models and service provider"
```

---

### Task 4: `HasWebhookEvents` trait

**Files:**
- Create: `plugins/webhook/src/Traits/HasWebhookEvents.php`
- Test: `plugins/webhook/tests/Feature/HasWebhookEventsTest.php`

This is the opt-in mechanism. A model uses the trait and implements `webhookEvents(): array` to declare which event names it can fire. `created`/`updated`/`deleted` fire automatically if listed; anything else (`status_changed`, `paid`, ...) must be fired explicitly by the model via `$this->fireWebhookEvent('paid')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use Illuminate\Database\Eloquent\Model;
use Lumina\Webhook\Jobs\DispatchWebhookJob;
use Lumina\Webhook\Models\WebhookEndpoint;
use Lumina\Webhook\Traits\HasWebhookEvents;
use Illuminate\Support\Facades\Queue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

class WebhookTestWidget extends Model
{
    use HasWebhookEvents;

    protected $table = 'webhook_test_widgets';
    protected $fillable = ['name'];
    public $timestamps = false;

    protected static function webhookEvents(): array
    {
        return ['created', 'shipped'];
    }
}

beforeEach(function () {
    Schema::create('webhook_test_widgets', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

it('dispatches a job per matching active endpoint when a declared event fires', function () {
    Queue::fake();

    WebhookEndpoint::create([
        'name' => 'Test',
        'url' => 'https://example.test/hook',
        'events' => ['webhook-test-widget.created'],
        'status' => 'active',
    ]);
    WebhookEndpoint::create([
        'name' => 'Inactive',
        'url' => 'https://example.test/hook2',
        'events' => ['webhook-test-widget.created'],
        'status' => 'inactive',
    ]);
    WebhookEndpoint::create([
        'name' => 'Different event',
        'url' => 'https://example.test/hook3',
        'events' => ['webhook-test-widget.shipped'],
        'status' => 'active',
    ]);

    WebhookTestWidget::create(['name' => 'Widget A']);

    Queue::assertPushed(DispatchWebhookJob::class, 1);
});

it('does not dispatch for events not declared on the model', function () {
    Queue::fake();

    $widget = WebhookTestWidget::create(['name' => 'Widget B']);
    $widget->fireWebhookEvent('not-declared');

    Queue::assertNotPushed(DispatchWebhookJob::class);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/webhook/tests/Feature/HasWebhookEventsTest.php`
Expected: FAIL — `Trait "Lumina\Webhook\Traits\HasWebhookEvents" not found` (or class/job not found).

- [ ] **Step 3: Write the trait**

```php
<?php

namespace Lumina\Webhook\Traits;

use Illuminate\Support\Str;
use Lumina\Webhook\Jobs\DispatchWebhookJob;
use Lumina\Webhook\Models\WebhookEndpoint;

trait HasWebhookEvents
{
    public static function bootHasWebhookEvents(): void
    {
        static::created(fn (self $model) => $model->fireWebhookEvent('created'));
        static::updated(fn (self $model) => $model->fireWebhookEvent('updated'));
        static::deleted(fn (self $model) => $model->fireWebhookEvent('deleted'));
    }

    public function fireWebhookEvent(string $event): void
    {
        if (! in_array($event, static::webhookEvents(), true)) {
            return;
        }

        $eventName = static::webhookEventName($event);

        WebhookEndpoint::query()
            ->where('status', 'active')
            ->whereJsonContains('events', $eventName)
            ->get()
            ->each(fn (WebhookEndpoint $endpoint) => DispatchWebhookJob::dispatch(
                $endpoint,
                $eventName,
                $this->toWebhookPayload()
            ));
    }

    public static function webhookEventName(string $event): string
    {
        return Str::kebab(class_basename(static::class)).'.'.$event;
    }

    public static function availableWebhookEvents(): array
    {
        return array_map(
            fn (string $event) => static::webhookEventName($event),
            static::webhookEvents()
        );
    }

    public function toWebhookPayload(): array
    {
        return $this->toArray();
    }

    /**
     * Event names this model can fire. 'created'/'updated'/'deleted' fire
     * automatically; any other name must be fired via fireWebhookEvent().
     */
    abstract protected static function webhookEvents(): array;
}
```

- [ ] **Step 4: Write `DispatchWebhookJob` stub (minimal, fleshed out in Task 5)**

```php
<?php

namespace Lumina\Webhook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Lumina\Webhook\Models\WebhookEndpoint;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $data,
    ) {}

    public function handle(): void
    {
        // implemented in Task 5
    }
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/webhook/tests/Feature/HasWebhookEventsTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add plugins/webhook/src/Traits plugins/webhook/src/Jobs plugins/webhook/tests
git commit -m "feat(webhook): add HasWebhookEvents opt-in trait"
```

---

### Task 5: `DispatchWebhookJob` — sign, send, record delivery

**Files:**
- Modify: `plugins/webhook/src/Jobs/DispatchWebhookJob.php`
- Test: `plugins/webhook/tests/Feature/DispatchWebhookJobTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Illuminate\Support\Facades\Http;
use Lumina\Webhook\Jobs\DispatchWebhookJob;
use Lumina\Webhook\Models\WebhookDelivery;
use Lumina\Webhook\Models\WebhookEndpoint;

uses(Tests\TestCase::class);

it('signs the payload, posts it, and records a successful delivery', function () {
    Http::fake(['example.test/*' => Http::response(['ok' => true], 200)]);

    $endpoint = WebhookEndpoint::create([
        'name' => 'Test',
        'url' => 'https://example.test/hook',
        'secret' => 'my-secret',
        'events' => ['order.paid'],
        'status' => 'active',
    ]);

    (new DispatchWebhookJob($endpoint, 'order.paid', ['id' => 1]))->handle();

    Http::assertSent(function ($request) {
        $body = $request->body();
        $expectedSignature = hash_hmac('sha256', $body, 'my-secret');
        return $request->url() === 'https://example.test/hook'
            && $request->hasHeader('X-Webhook-Signature', $expectedSignature);
    });

    $delivery = WebhookDelivery::first();
    expect($delivery->status)->toBe('success');
    expect($delivery->response_status)->toBe(200);
    expect($delivery->event)->toBe('order.paid');
});

it('marks the delivery failed when the endpoint responds with an error', function () {
    Http::fake(['example.test/*' => Http::response('nope', 500)]);

    $endpoint = WebhookEndpoint::create([
        'name' => 'Test',
        'url' => 'https://example.test/hook',
        'secret' => 'my-secret',
        'events' => ['order.paid'],
        'status' => 'active',
    ]);

    $job = new DispatchWebhookJob($endpoint, 'order.paid', ['id' => 1]);

    expect(fn () => $job->handle())->toThrow(\RuntimeException::class);

    $delivery = WebhookDelivery::first();
    expect($delivery->status)->toBe('failed');
    expect($delivery->response_status)->toBe(500);
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/webhook/tests/Feature/DispatchWebhookJobTest.php`
Expected: FAIL — no delivery is created (handle() is empty), or assertion errors on null.

- [ ] **Step 3: Implement `handle()`**

```php
<?php

namespace Lumina\Webhook\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lumina\Webhook\Models\WebhookDelivery;
use Lumina\Webhook\Models\WebhookEndpoint;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        public WebhookEndpoint $endpoint,
        public string $event,
        public array $data,
    ) {}

    public function handle(): void
    {
        $payload = [
            'event' => $this->event,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $this->endpoint->secret ?? '');

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $this->endpoint->id,
            'event' => $this->event,
            'payload' => $payload,
            'attempt' => $this->attempts() ?: 1,
            'status' => 'pending',
        ]);

        $response = Http::withBody($body, 'application/json')
            ->withHeaders(['X-Webhook-Signature' => $signature])
            ->timeout(10)
            ->post($this->endpoint->url);

        $delivery->update([
            'response_status' => $response->status(),
            'response_body' => Str::limit($response->body(), 2000),
            'status' => $response->successful() ? 'success' : 'failed',
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Webhook endpoint {$this->endpoint->url} responded with {$response->status()}");
        }
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test plugins/webhook/tests/Feature/DispatchWebhookJobTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add plugins/webhook/src/Jobs plugins/webhook/tests
git commit -m "feat(webhook): sign, send, and record webhook deliveries"
```

---

### Task 6: Opt in `Product`, `Order`, `User`

**Files:**
- Modify: `plugins/e-commerce/src/Models/Product.php`
- Modify: `plugins/e-commerce/src/Models/Order.php`
- Modify: `app/Models/User.php`
- Test: `plugins/webhook/tests/Feature/ModelWebhookEventsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use Lumina\Ecommerce\Models\Order;
use Lumina\Ecommerce\Models\Product;
use App\Models\User;

it('declares the expected webhook events per model', function () {
    expect(Product::availableWebhookEvents())->toBe([
        'product.created', 'product.updated', 'product.deleted',
    ]);

    expect(Order::availableWebhookEvents())->toBe([
        'order.created', 'order.status_changed', 'order.paid', 'order.cancelled',
    ]);

    expect(User::availableWebhookEvents())->toBe([
        'user.created', 'user.updated',
    ]);
});
```

Adjust the `Lumina\Ecommerce\Models\...` namespace to whatever `Product`/`Order` actually use — confirm via `grep -r "^namespace" plugins/e-commerce/src/Models/Product.php plugins/e-commerce/src/Models/Order.php` before writing this test.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/webhook/tests/Feature/ModelWebhookEventsTest.php`
Expected: FAIL — `Call to undefined method Product::availableWebhookEvents()`.

- [ ] **Step 3: Add the trait to `Product`**

In `plugins/e-commerce/src/Models/Product.php`, add the import and trait usage alongside existing traits, and add the method:

```php
use Lumina\Webhook\Traits\HasWebhookEvents;

class Product extends Model
{
    use HasWebhookEvents; // add alongside existing traits

    // ... existing code ...

    protected static function webhookEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }
}
```

- [ ] **Step 4: Add the trait to `Order`, with explicit fires for domain events**

In `plugins/e-commerce/src/Models/Order.php`:

```php
use Lumina\Webhook\Traits\HasWebhookEvents;

class Order extends Model
{
    use HasWebhookEvents;

    // ... existing code ...

    protected static function webhookEvents(): array
    {
        return ['created', 'status_changed', 'paid', 'cancelled'];
    }
}
```

Find the existing method that updates `Order`'s status (search `grep -n "function.*[Ss]tatus" plugins/e-commerce/src/Models/Order.php` first). Inside it, after the status column is saved, add:

```php
$this->fireWebhookEvent('status_changed');

if ($this->status === 'paid') {
    $this->fireWebhookEvent('paid');
}

if ($this->status === 'cancelled') {
    $this->fireWebhookEvent('cancelled');
}
```

If no single status-update method exists, add these three lines at the end of whichever method(s) actually mutate `status` (there may be separate `markAsPaid()`/`cancel()` methods — call `fireWebhookEvent('paid')` / `fireWebhookEvent('cancelled')` from those directly instead of branching on `status`).

- [ ] **Step 5: Add the trait to `User`**

In `app/Models/User.php`:

```php
use Lumina\Webhook\Traits\HasWebhookEvents;

class User extends Authenticatable
{
    use HasWebhookEvents;

    // ... existing code ...

    protected static function webhookEvents(): array
    {
        return ['created', 'updated'];
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test plugins/webhook/tests/Feature/ModelWebhookEventsTest.php`
Expected: PASS.

- [ ] **Step 7: Run the full webhook test suite to check nothing regressed**

Run: `php artisan test plugins/webhook`
Expected: all tests PASS.

- [ ] **Step 8: Commit**

```bash
git add plugins/e-commerce/src/Models/Product.php plugins/e-commerce/src/Models/Order.php app/Models/User.php plugins/webhook/tests
git commit -m "feat(webhook): opt Product, Order, and User into webhook events"
```

---

### Task 7: `available-events` endpoint for the dashboard form

**Files:**
- Create: `plugins/webhook/src/Controllers/WebhookEventsController.php`
- Create: `plugins/webhook/routes/web.php`
- Test: `plugins/webhook/tests/Feature/WebhookEventsControllerTest.php`

Lists every `{model}.{event}` string across models using `HasWebhookEvents`, by combining `config('core.model_namespaces')` (populated by each plugin's own `ServiceProvider`) with the model class basenames found under each plugin's `src/Models` directory.

- [ ] **Step 1: Write the failing test**

```php
<?php

it('lists available webhook events from models using the trait', function () {
    $response = $this->actingAsAdmin()->getJson('/webhook-events/available-events');

    $response->assertOk();
    $response->assertJsonFragment(['event' => 'product.created']);
    $response->assertJsonFragment(['event' => 'order.paid']);
    $response->assertJsonFragment(['event' => 'user.updated']);
});
```

Check the repo's existing feature tests (e.g. `grep -rn "actingAsAdmin\|actingAs(" plugins/*/tests | head -5`) for the correct admin-authentication helper and adjust this test to match it.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test plugins/webhook/tests/Feature/WebhookEventsControllerTest.php`
Expected: FAIL — 404, route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace Lumina\Webhook\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Lumina\Webhook\Traits\HasWebhookEvents;

class WebhookEventsController extends Controller
{
    public function index(): JsonResponse
    {
        $events = $this->modelsUsingWebhookTrait()
            ->flatMap(fn (string $class) => $class::availableWebhookEvents())
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $event) => ['event' => $event]);

        return response()->json($events);
    }

    protected function modelsUsingWebhookTrait(): Collection
    {
        $namespaces = config('core.model_namespaces', []);

        return collect(File::glob(base_path('plugins/*/src/Models/*.php')))
            ->map(fn (string $path) => pathinfo($path, PATHINFO_FILENAME))
            ->push('User') // app/Models/User.php isn't under plugins/*/src/Models
            ->unique()
            ->flatMap(fn (string $className) => collect($namespaces)
                ->push('App\\Models')
                ->map(fn (string $namespace) => $namespace.'\\'.$className)
                ->filter(fn (string $class) => class_exists($class)))
            ->filter(fn (string $class) => in_array(HasWebhookEvents::class, class_uses_recursive($class)))
            ->values();
    }
}
```

- [ ] **Step 4: Register the route**

```php
<?php

use Illuminate\Support\Facades\Route;
use Lumina\Webhook\Controllers\WebhookEventsController;

Route::middleware(['web', 'auth'])
    ->get('webhook-events/available-events', [WebhookEventsController::class, 'index'])
    ->name('webhook-events.available-events');
```

(`RegistersPlugins` auto-loads every `*.php` file under `plugins/webhook/routes/` once the plugin is enabled — no manual registration needed.)

- [ ] **Step 5: Run the test to verify it passes**

Run: `php artisan test plugins/webhook/tests/Feature/WebhookEventsControllerTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add plugins/webhook/src/Controllers plugins/webhook/routes plugins/webhook/tests
git commit -m "feat(webhook): add available-events endpoint for the dashboard form"
```

---

### Task 8: Dashboard pages — list, create/edit, delivery log

**Files:**
- Create: `plugins/webhook/resources/js/pages/webhook-endpoints/index.tsx`
- Create: `plugins/webhook/resources/js/pages/webhook-endpoints/form.tsx`
- Create: `plugins/webhook/resources/js/pages/webhook-endpoints/deliveries.tsx`

Before writing these, read `plugins/redirects/resources/js/pages/redirects/index.tsx` and `form.tsx` (or the closest existing simple CRUD page) to confirm current prop names for `IndexLayout`/`FormLayout`/`Field`, since these evolve — match whatever that file currently uses rather than the shape below if they differ.

- [ ] **Step 1: Write the list page**

```tsx
import type { PageConfig } from '@cms/layouts/index-layout';
import IndexLayout from '@cms/layouts/index-layout';

type WebhookEndpointRow = {
    id: number;
    name: string;
    url: string;
    status: string;
    created_at: string;
};

const config: PageConfig<WebhookEndpointRow> = {
    title: 'Webhook Endpoints',
    searchPlaceholder: 'Tìm kiếm webhook...',
    actions: { create: true, import: false, export: true },
    defaultSort: ['created_at:desc'],
    columns: [
        { accessorKey: 'name', label: 'Tên', link: true },
        { accessorKey: 'url', label: 'URL' },
        { type: 'badge', accessorKey: 'status', label: 'Trạng thái' },
        { accessorKey: 'created_at', label: 'Ngày tạo' },
    ],
};

export default function WebhookEndpointsIndex() {
    return <IndexLayout<WebhookEndpointRow> config={config} />;
}
```

- [ ] **Step 2: Write the create/edit form page**

```tsx
import { useEffect, useState } from 'react';
import axios from 'axios';
import { Field } from '@cms/components/form';
import FormLayout, { Group, Section, Sidebar } from '@cms/layouts/form-layout';

export default function WebhookEndpointForm() {
    const [eventOptions, setEventOptions] = useState<{ label: string; value: string }[]>([]);

    useEffect(() => {
        axios.get<{ event: string }[]>('/webhook-events/available-events').then((res) => {
            setEventOptions(res.data.map((row) => ({ label: row.event, value: row.event })));
        });
    }, []);

    return (
        <FormLayout title="Webhook Endpoint" collection="webhook-endpoints">
            <Group>
                <Section withSidebar>
                    <Field name="name" ui="input" label="Tên" />
                    <Field name="url" ui="input" label="URL" />
                    <Field name="events" ui="multi-select" label="Sự kiện" options={eventOptions} />
                    <Field name="secret" ui="input" label="Secret" readOnly copyable />
                </Section>
                <Sidebar>
                    <Field name="status" ui="select" label="Trạng thái" />
                </Sidebar>
            </Group>
        </FormLayout>
    );
}
```

Check `@cms/components/form`'s `Field` for the exact prop names supported by the `multi-select` and `input` variants (e.g. `copyable`/`readOnly` may be named differently) — adjust to match before this step is considered done.

- [ ] **Step 3: Write the delivery log page**

```tsx
import { useEffect, useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';

type Delivery = {
    id: number;
    event: string;
    status: string;
    response_status: number | null;
    attempt: number;
    created_at: string;
};

export default function WebhookEndpointDeliveries() {
    const { props } = usePage<{ endpointId: number }>();
    const [deliveries, setDeliveries] = useState<Delivery[]>([]);

    useEffect(() => {
        axios
            .get<{ data: Delivery[] }>('/items/webhook-deliveries', {
                params: { 'filter[webhook_endpoint_id][_eq]': props.endpointId, sort: '-created_at' },
            })
            .then((res) => setDeliveries(res.data.data));
    }, [props.endpointId]);

    return (
        <table>
            <thead>
                <tr>
                    <th>Sự kiện</th>
                    <th>Trạng thái</th>
                    <th>HTTP status</th>
                    <th>Lần thử</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
                {deliveries.map((delivery) => (
                    <tr key={delivery.id}>
                        <td>{delivery.event}</td>
                        <td>{delivery.status}</td>
                        <td>{delivery.response_status ?? '-'}</td>
                        <td>{delivery.attempt}</td>
                        <td>{delivery.created_at}</td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}
```

This reads `webhook_deliveries` through the existing generic `items/{resource}` endpoint (`WebhookDelivery` already uses `QueryBuilder`, so it's automatically a valid resource) — no extra backend code needed. Style this table using whatever table primitives the codebase already provides (check `plugins/cms/resources/js/components/tables/data-table-filter.tsx`, currently open in the editor, for the existing pattern) rather than a bare `<table>`.

- [ ] **Step 4: Manually verify in the browser**

Run: `php artisan serve` and `npm run dev` (or whatever the repo's existing dev script is — check `package.json`), then visit `/webhook-endpoints`, create an endpoint, and confirm the events multi-select is populated and the form saves.

- [ ] **Step 5: Commit**

```bash
git add plugins/webhook/resources/js
git commit -m "feat(webhook): add dashboard pages for webhook endpoints and deliveries"
```

---

### Task 9: End-to-end verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full webhook test suite**

Run: `php artisan test plugins/webhook`
Expected: all tests PASS.

- [ ] **Step 2: Run the full test suite to check for regressions**

Run: `php artisan test`
Expected: all tests PASS (no regressions in `Product`, `Order`, or `User` behavior from the added trait).

- [ ] **Step 3: Manual smoke test with a real HTTP sink**

Use a local request-catcher (e.g. `https://webhook.site` or `php -S localhost:8001` with a tiny inspect script) as the endpoint URL, create a `Product` via the dashboard or `php artisan tinker`, and confirm:
- A queue job runs (`php artisan queue:work --once` if `QUEUE_CONNECTION` isn't `sync`)
- The sink receives a POST with `X-Webhook-Signature` header and JSON body `{event, data, timestamp}`
- A `webhook_deliveries` row is created with `status = success`

- [ ] **Step 4: Final commit**

```bash
git add -A
git commit -m "chore(webhook): verify end-to-end delivery flow"
```

---

## Self-Review Notes

- **Spec coverage:** trait opt-in (Task 4/6), queued delivery + retry + signing (Task 5), dashboard create/edit form with URL+secret+model/event+active toggle (Task 8 form.tsx), delivery log (Task 8 deliveries.tsx), plugin registration via `plugins.php` (Task 1) — all spec sections have a task.
- **Known unknowns to confirm while executing:** exact namespace of `Product`/`Order` (Task 6 Step 1 asks to `grep` it first), exact `Field`/`IndexLayout`/`FormLayout` prop names (Task 8 asks to check a reference page first), and the admin-auth test helper name (Task 7 asks to `grep` for it). These are flagged inline rather than guessed, since getting them wrong silently breaks the build.
