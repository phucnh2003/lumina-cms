<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates a nullable json column via $table->multilingual()', function () {
    Schema::create('macro_test_table', function (Blueprint $table) {
        $table->id();
        $table->multilingual('title');
    });

    expect(Schema::hasColumn('macro_test_table', 'title'))->toBeTrue();

    // Nullable: inserting a row without "title" must not fail.
    DB::table('macro_test_table')->insert(['id' => 1]);
    expect(DB::table('macro_test_table')->find(1)->title)->toBeNull();

    Schema::dropIfExists('macro_test_table');
});
