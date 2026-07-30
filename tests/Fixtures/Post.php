<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Lumina\Core\Traits\QueryBuilder;

/**
 * Test-only fixture resource: exercises `show` by slug and `$translatable`
 * fields, since no real model in the app has either yet.
 */
class Post extends Model
{
    use QueryBuilder;

    protected $table = 'fixture_posts';

    protected $fillable = ['slug', 'title'];

    protected array $translatable = ['title'];

    protected array $fulltextSearchable = ['slug'];
}
