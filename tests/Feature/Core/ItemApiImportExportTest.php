<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('exports records as a JSON file honoring filters', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $response = $this->get('/api/items/admins/export?'.http_build_query([
        'filter' => ['status' => ['_eq' => 'active'], 'name' => ['_like' => 'Alice']],
    ]));

    $response->assertOk();
    $payload = json_decode($response->streamedContent(), true);
    expect($payload)->toHaveCount(1);
    expect($payload[0]['name'])->toBe('Alice');
});

it('imports records from a JSON file and reports success/failure counts', function () {
    $rows = [
        ['name' => 'Imported One', 'email' => 'imported-one@example.com', 'password' => 'secret-password', 'status' => 'active'],
        ['name' => 'Imported Two', 'email' => 'imported-two@example.com', 'password' => 'secret-password', 'status' => 'active'],
    ];

    $file = UploadedFile::fake()->createWithContent('admins.json', json_encode($rows));

    $response = $this->postJson('/api/items/admins/import', ['file' => $file]);

    $response->assertOk();
    expect($response->json('imported'))->toBe(2);
    expect($response->json('failed'))->toBe(0);
    $this->assertDatabaseHas('admins', ['email' => 'imported-one@example.com']);
    $this->assertDatabaseHas('admins', ['email' => 'imported-two@example.com']);
});

it('counts failed rows without aborting the whole import', function () {
    $rows = [
        ['name' => 'Good Row', 'email' => 'good-row@example.com', 'password' => 'secret-password', 'status' => 'active'],
        ['name' => null, 'email' => null],
    ];

    $file = UploadedFile::fake()->createWithContent('admins.json', json_encode($rows));

    $response = $this->postJson('/api/items/admins/import', ['file' => $file]);

    $response->assertOk();
    expect($response->json('imported'))->toBe(1);
    expect($response->json('failed'))->toBe(1);
});
