<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Lumina\Cms\Models\Admin;

uses(RefreshDatabase::class);

it('lists trashed admins on the trash page', function () {
    $viewer = Admin::factory()->create();
    $trashed = Admin::factory()->create(['name' => 'Trashed Guy']);
    $active = Admin::factory()->create(['name' => 'Active Guy']);
    $trashed->delete();

    // The "admins/trash" frontend page doesn't exist yet (backend-only work),
    // so skip Inertia's component-file check and read the page props directly.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = $this->actingAs($viewer, 'admin')->get(route('admins.trash'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('admins.data.0.id', $trashed->id));
    expect($active->fresh())->not->toBeNull();
});

it('restores a trashed admin as a new record, leaving the original in the trash', function () {
    $viewer = Admin::factory()->create();
    $trashed = Admin::factory()->create(['name' => 'Deleted Guy']);
    $trashed->delete();

    $response = $this->actingAs($viewer, 'admin')->post(route('admins.trash.restore', $trashed->id));

    $response->assertRedirect(route('admins.index'));
    $this->assertSoftDeleted('admins', ['id' => $trashed->id]);
    $this->assertDatabaseHas('admins', ['name' => 'Deleted Guy', 'deleted_at' => null]);
    expect(Admin::whereNull('deleted_at')->where('name', 'Deleted Guy')->count())->toBe(1);
});

it('permanently deletes a trashed admin', function () {
    $viewer = Admin::factory()->create();
    $trashed = Admin::factory()->create();
    $trashed->delete();

    $response = $this->actingAs($viewer, 'admin')->delete(route('admins.trash.forceDelete', $trashed->id));

    $response->assertRedirect(route('admins.trash'));
    $this->assertDatabaseMissing('admins', ['id' => $trashed->id]);
});
