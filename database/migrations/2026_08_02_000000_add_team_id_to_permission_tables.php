<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teams stays disabled (`config('permission.teams')` is `false`), so this doesn't turn the
 * feature on — no query gains team scoping. It only adds the `team_id` column that
 * Spatie\Permission\Traits\HasRoles::teams() unconditionally references (even in its
 * teams-disabled "no-op" branch), which otherwise throws "Unknown column" instead of
 * returning the empty result that branch is documented to return.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnName = config('permission.column_names.team_foreign_key', 'team_id');
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnName) {
            $table->unsignedBigInteger($columnName)->nullable();
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnName) {
            $table->unsignedBigInteger($columnName)->nullable();
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnName) {
            $table->unsignedBigInteger($columnName)->nullable();
        });
    }

    public function down(): void
    {
        $columnName = config('permission.column_names.team_foreign_key', 'team_id');
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnName) {
            $table->dropColumn($columnName);
        });

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnName) {
            $table->dropColumn($columnName);
        });

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnName) {
            $table->dropColumn($columnName);
        });
    }
};
