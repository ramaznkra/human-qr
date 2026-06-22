<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('type', 20)->default('kitchen')->after('category_id');
        });

        if (Schema::hasTable('categories')) {
            DB::table('products')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('categories.slug', 'icecek')
                ->update(['products.type' => 'bar']);

            DB::table('products')
                ->join('categories', 'categories.id', '=', 'products.category_id')
                ->where('categories.slug', '!=', 'icecek')
                ->update(['products.type' => 'kitchen']);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
