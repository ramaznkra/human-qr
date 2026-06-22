<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('table_calls')) {
            return;
        }

        DB::table('table_calls')->where('status', 'pending')->update(['status' => 'active']);
        DB::table('table_calls')->where('status', 'acknowledged')->update(['status' => 'resolved']);
        DB::table('table_calls')->where('type', 'bill')->update(['type' => 'bill_cash']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('table_calls')) {
            return;
        }

        DB::table('table_calls')->where('status', 'active')->update(['status' => 'pending']);
        DB::table('table_calls')->where('status', 'resolved')->update(['status' => 'acknowledged']);
        DB::table('table_calls')->whereIn('type', ['bill_cash', 'bill_card'])->update(['type' => 'bill']);
    }
};
