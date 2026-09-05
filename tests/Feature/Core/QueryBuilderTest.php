<?php

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('filters with _eq and _like operators', function () {
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Bob', 'status' => 'locked']);

    $results = Admin::query()->applyQuery(['filter' => ['status' => ['_eq' => 'active']], 'limit' => -1]);
    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Alice');

    $results = Admin::query()->applyQuery(['filter' => ['name' => ['_like' => 'ali']], 'limit' => -1]);
    expect($results)->toHaveCount(1);
});

it('filters with _in operator', function () {
    $a = Admin::factory()->create(['status' => 'active']);
    $b = Admin::factory()->create(['status' => 'locked']);
    Admin::factory()->create(['status' => 'active']);

    $results = Admin::query()->applyQuery(['filter' => ['id' => ['_in' => [$a->id, $b->id]]], 'limit' => -1]);
    expect($results)->toHaveCount(2);
});

it('sorts ascending and descending, including multi-column', function () {
    Admin::factory()->create(['name' => 'Bob', 'status' => 'active']);
    Admin::factory()->create(['name' => 'Alice', 'status' => 'active']);

    $results = Admin::query()->applyQuery(['sort' => 'name', 'limit' => -1]);
    expect($results->pluck('name')->all())->toBe(['Alice', 'Bob']);

    $results = Admin::query()->applyQuery(['sort' => '-name', 'limit' => -1]);
    expect($results->pluck('name')->all())->toBe(['Bob', 'Alice']);
});

it('selects only requested fields', function () {
    Admin::factory()->create(['name' => 'Alice']);

    $result = Admin::query()->applyQuery(['fields' => ['id', 'name']])->first();
    expect($result->getAttributes())->toHaveKeys(['id', 'name']);
    expect($result->getAttributes())->not->toHaveKey('email');
});

it('paginates by default and supports limit=-1 and paginate=false', function () {
    Admin::factory()->count(3)->create();

    $paginated = Admin::query()->applyQuery(['limit' => 2, 'page' => 1]);
    expect($paginated)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($paginated->count())->toBe(2);

    $all = Admin::query()->applyQuery(['limit' => -1]);
    expect($all)->toBeInstanceOf(Collection::class);
    expect($all)->toHaveCount(3);

    $noPaginate = Admin::query()->applyQuery(['paginate' => 'false']);
    expect($noPaginate)->toBeInstanceOf(Collection::class);
    expect($noPaginate)->toHaveCount(3);
});

it('searches across searchFields with a plain LIKE match', function () {
    Admin::factory()->create(['name' => 'Alice Johnson', 'email' => 'alice@example.com']);
    Admin::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);

    $results = Admin::query()->applyQuery([
        'search' => 'alice',
        'searchFields' => ['name', 'email'],
        'limit' => -1,
    ]);

    expect($results)->toHaveCount(1);
    expect($results->first()->name)->toBe('Alice Johnson');
});

it('ignores search when searchFields is empty', function () {
    Admin::factory()->create(['name' => 'Alice']);
    Admin::factory()->create(['name' => 'Bob']);

    $results = Admin::query()->applyQuery(['search' => 'alice', 'limit' => -1]);

    expect($results)->toHaveCount(2);
});

it('supports nested relationship loading via dot notation in fields', function () {
    $parent = \Lumina\Taxonomies\Models\Taxonomy::create([
        'name' => 'Parent',
        'slug' => 'parent',
        'type' => 'category',
        'status' => 'active',
    ]);

    $child = \Lumina\Taxonomies\Models\Taxonomy::create([
        'parent_id' => $parent->id,
        'name' => 'Child',
        'slug' => 'child',
        'type' => 'category',
        'status' => 'active',
    ]);

    $grandchild = \Lumina\Taxonomies\Models\Taxonomy::create([
        'parent_id' => $child->id,
        'name' => 'Grandchild',
        'slug' => 'grandchild',
        'type' => 'category',
        'status' => 'active',
    ]);

    $results = \Lumina\Taxonomies\Models\Taxonomy::query()
        ->whereNull('parent_id')
        ->applyQuery([
            'fields' => ['id', 'name', 'children.name', 'children.children.name'],
            'limit' => -1,
        ]);

    expect($results->first()->children->first()->name)->toBe('Child');
    expect($results->first()->children->first()->children->first()->name)->toBe('Grandchild');
    expect($results->first()->children->first()->toArray())->not->toHaveKey('parent_id');
});

