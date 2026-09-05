<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Lumina\Cms\Models\Admin;
use Lumina\Taxonomies\Models\ProductCategory;
use Tests\Fixtures\Post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(Admin::factory()->create(), 'admin');

    config(['core.model_namespaces' => array_merge(['Tests\\Fixtures'], config('core.model_namespaces', []))]);

    Schema::create('fixture_posts', function (Blueprint $table) {
        $table->id();
        $table->string('slug')->unique();
        $table->multilingual('title');
        $table->timestamps();
    });

    Post::create(['slug' => 'hello-world', 'title' => json_encode(['vi' => 'Xin chào', 'en' => 'Hello'])]);
});

it('resolves translatable fields to the app locale by default', function () {
    app()->setLocale('en');

    $response = $this->getJson('/api/items/posts?limit=-1');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Hello');
});

it('resolves translatable fields to the ?locale= query param', function () {
    $response = $this->getJson('/api/items/posts?limit=-1&locale=vi');

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Xin chào');
});

it('resolves a single field to a specific locale via fields[]=field->locale, overriding ?locale=', function () {
    $response = $this->getJson('/api/items/posts?'.http_build_query([
        'limit' => -1,
        'locale' => 'en',
        'fields' => ['title->vi'],
    ]));

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe('Xin chào');
});

it('returns the raw locale object via fields[]=field->toRaw', function () {
    $response = $this->getJson('/api/items/posts?'.http_build_query([
        'limit' => -1,
        'fields' => ['title->toRaw'],
    ]));

    $response->assertOk();
    expect($response->json('data.0.title'))->toBe(['vi' => 'Xin chào', 'en' => 'Hello']);
});

it('resolves translatable fields on the show endpoint too', function () {
    $response = $this->getJson('/api/items/posts/hello-world?locale=vi');

    $response->assertOk();
    expect($response->json('data.title'))->toBe('Xin chào');
});

it('searches a translatable field using the correct locale JSON path', function () {
    Post::create(['slug' => 'goodbye', 'title' => json_encode(['vi' => 'Tạm biệt', 'en' => 'Goodbye'])]);

    $response = $this->getJson('/api/items/posts?'.http_build_query([
        'search' => 'Xin',
        'searchFields' => ['title'],
        'locale' => 'vi',
        'limit' => -1,
    ]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.slug'))->toBe('hello-world');
});

it('uses a real fulltext query for fields declared in $fulltextSearchable', function () {
    $this->withoutExceptionHandling();

    // SQLite's query grammar doesn't support fulltext search and throws on
    // compile; hitting that specific error is how we prove whereFullText()
    // was actually called for this field (instead of a LIKE fallback).
    $call = fn () => $this->getJson('/api/items/posts?'.http_build_query([
        'search' => 'hello',
        'searchFields' => ['slug'],
        'limit' => -1,
    ]));

    expect($call)->toThrow(RuntimeException::class, 'does not support fulltext search');
});

it('transforms translatable fields on loaded relations', function () {
    $parent = ProductCategory::create([
        'name' => json_encode(['vi' => 'Thời trang Nam', 'en' => "Men's Fashion"]),
        'slug' => 'thoi-trang-nam-test',
        'status' => 'active',
    ]);

    ProductCategory::create([
        'name' => json_encode(['vi' => 'Áo thun Nam', 'en' => "Men's T-Shirts"]),
        'slug' => 'ao-thun-nam-test',
        'status' => 'active',
        'parent_id' => $parent->id,
    ]);

    $response = $this->getJson('/api/items/product-categories?'.http_build_query([
        'filter' => ['slug' => ['_eq' => 'thoi-trang-nam-test']],
        'fields' => ['slug', 'name', 'id', 'children.name'],
        'locale' => 'vi',
        'limit' => -1,
    ]));

    $response->assertOk();
    expect($response->json('data.0.name'))->toBe('Thời trang Nam');
    expect($response->json('data.0.children.0.name'))->toBe('Áo thun Nam');
});
