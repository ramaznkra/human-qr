<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('table_calls', 'forwarded_to_waiter')) {
            Schema::table('table_calls', function (Blueprint $table) {
                $table->boolean('forwarded_to_waiter')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('table_calls', 'forwarded_to_waiter')) {
            Schema::table('table_calls', function (Blueprint $table) {
                $table->dropColumn('forwarded_to_waiter');
            });
        }
    }
};
