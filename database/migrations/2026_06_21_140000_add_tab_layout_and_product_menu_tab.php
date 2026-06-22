<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_tabs', function (Blueprint $table) {
            $table->string('layout', 20)->default('grouped')->after('slug');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('menu_tab_id')
                ->nullable()
                ->after('menu_section_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_tab_id');
        });

        Schema::table('menu_tabs', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
