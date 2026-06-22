<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'station')) {
                $table->string('station', 20)->nullable()->after('type');
                $table->index('station');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'preparation_status')) {
                $table->string('preparation_status', 20)->default('waiting')->after('notes');
                $table->index('preparation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'preparation_status')) {
                $table->dropIndex(['preparation_status']);
                $table->dropColumn('preparation_status');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'station')) {
                $table->dropIndex(['station']);
                $table->dropColumn('station');
            }
        });
    }
};
