<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lumina\Cms\Models\Admin;
use Tests\Fixtures\Post;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Locked so it never accidentally matches the `status=active` filter
    // assertions below.
    $this->actingAs(Admin::factory()->create(['status' => 'locked']), 'admin');
});

it('returns 404 for an unresolvable resource', function () {
    $this->getJson('/api/items/does-not-exist')->assertNotFound();
});

it('lists a resource with data/meta shape', function () {
    $before = Admin::query()->count();

    Admin::factory()->count(3)->create();

    $response = $this->getJson('/api/items/admins?limit=-1');

    $response->assertOk();
    $response->assertJsonStructure(['data', 'meta' => ['total']]);
    expect($response->json('meta.total'))->toBe($before + 3);
});

it('applies filters and sort through the query string', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $response = $this->getJson('/api/items/admins?'.http_build_query([
        'filter' => ['status' => ['_eq' => 'active']],
        'sort' => 'name',
        'limit' => -1,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.name'))->toBe('Alice');
});

it('creates a record', function () {
    $response = $this->postJson('/api/items/admins', [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'secret-password-123',
        'status' => 'active',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('admins', ['email' => 'new-admin@example.com']);
});

it('updates a record', function () {
    $admin = Admin::factory()->create(['name' => 'Old Name']);

    $response = $this->putJson("/api/items/admins/{$admin->id}", ['name' => 'New Name']);

    $response->assertOk();
    $this->assertDatabaseHas('admins', ['id' => $admin->id, 'name' => 'New Name']);
});

it('soft deletes a record with SoftDeletes', function () {
    $admin = Admin::factory()->create();

    $response = $this->deleteJson("/api/items/admins/{$admin->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('admins', ['id' => $admin->id]);
});

it('shows a single record by numeric id', function () {
    $admin = Admin::factory()->create(['name' => 'Solo']);

    $response = $this->getJson("/api/items/admins/{$admin->id}");

    $response->assertOk();
    expect($response->json('data.id'))->toBe($admin->id);
});

it('shows a single record by slug', function () {
    config(['core.model_namespaces' => array_merge(['Tests\\Fixtures'], config('core.model_namespaces', []))]);

    Schema::create('fixture_posts', function (Blueprint $table) {
        $table->id();
        $table->string('slug')->unique();
        $table->multilingual('title');
        $table->timestamps();
    });

    Post::create(['slug' => 'hello-world', 'title' => json_encode(['vi' => 'Xin chào', 'en' => 'Hello'])]);

    $response = $this->getJson('/api/items/posts/hello-world');

    $response->assertOk();
    expect($response->json('data.slug'))->toBe('hello-world');
});
