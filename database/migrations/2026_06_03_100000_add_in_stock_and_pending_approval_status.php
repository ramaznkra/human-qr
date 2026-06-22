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
            $table->boolean('in_stock')->default(true)->after('is_available');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending_approval','pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending_approval'");
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('in_stock');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('orders')->where('status', 'pending_approval')->update(['status' => 'pending']);
            DB::statement("ALTER TABLE orders MODIFY status ENUM('pending','preparing','ready','delivered','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
