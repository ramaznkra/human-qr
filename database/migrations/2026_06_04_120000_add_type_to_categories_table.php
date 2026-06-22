<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('type', 20)->default('kitchen')->after('slug');
            });
        }

        DB::table('categories')
            ->whereIn('slug', ['icecek', 'drinks', 'bar', 'icecekler'])
            ->update(['type' => 'bar']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'type')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
