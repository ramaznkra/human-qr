<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_en', 100)->nullable()->after('name');
            $table->string('name_ru', 100)->nullable()->after('name_en');
            if (Schema::hasColumn('categories', 'image')) {
                $table->dropColumn('image');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en', 150)->nullable()->after('name');
            $table->string('name_ru', 150)->nullable()->after('name_en');
            $table->text('description_en')->nullable()->after('description');
            $table->text('description_ru')->nullable()->after('description_en');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ru']);
            $table->string('image')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name_en', 'name_ru', 'description_en', 'description_ru']);
        });
    }
};
