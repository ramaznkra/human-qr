<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_options')) {
            return;
        }

        if (Schema::hasColumn('product_options', 'is_active')) {
            return;
        }

        Schema::table('product_options', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('price_modifier');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_options') || ! Schema::hasColumn('product_options', 'is_active')) {
            return;
        }

        Schema::table('product_options', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
