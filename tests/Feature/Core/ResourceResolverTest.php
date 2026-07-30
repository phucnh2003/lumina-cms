<?php

use Lumina\Cms\Models\Admin;
use Lumina\Core\Support\ResourceResolver;

it('resolves a known resource with QueryBuilder to its model class', function () {
    expect(ResourceResolver::resolve('admins'))->toBe(Admin::class);
});

it('returns null for a class that does not use QueryBuilder', function () {
    // Passkey exists in Lumina\Core\Models but does not use QueryBuilder
    expect(ResourceResolver::resolve('passkeys'))->toBeNull();
});

it('returns null for a resource with no matching class at all', function () {
    expect(ResourceResolver::resolve('does-not-exist-anywhere'))->toBeNull();
});
