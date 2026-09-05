<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            if (! Schema::hasColumn('otps', 'type')) {
                $table->string('type')->default('register')->after('code');
            }
            if (! Schema::hasColumn('otps', 'status')) {
                $table->string('status')->default('pending')->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('otps', function (Blueprint $table) {
            $table->dropColumn(['type', 'status']);
        });
    }
};
