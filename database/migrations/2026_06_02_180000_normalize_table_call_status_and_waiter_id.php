<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('table_calls')->where('status', 'active')->update(['status' => 'pending']);
        DB::table('table_calls')->where('status', 'resolved')->update(['status' => 'completed']);

        if (Schema::hasColumn('table_calls', 'assigned_user_id') && ! Schema::hasColumn('table_calls', 'waiter_id')) {
            Schema::table('table_calls', function (Blueprint $table) {
                $table->renameColumn('assigned_user_id', 'waiter_id');
            });
        } elseif (! Schema::hasColumn('table_calls', 'waiter_id')) {
            Schema::table('table_calls', function (Blueprint $table) {
                $table->foreignId('waiter_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        DB::table('table_calls')->where('status', 'pending')->update(['status' => 'active']);
        DB::table('table_calls')->where('status', 'completed')->update(['status' => 'resolved']);

        if (Schema::hasColumn('table_calls', 'waiter_id') && ! Schema::hasColumn('table_calls', 'assigned_user_id')) {
            Schema::table('table_calls', function (Blueprint $table) {
                $table->renameColumn('waiter_id', 'assigned_user_id');
            });
        }
    }
};
