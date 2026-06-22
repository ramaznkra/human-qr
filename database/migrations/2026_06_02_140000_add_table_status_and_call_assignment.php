<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->string('status', 20)->default('available')->after('is_active');
        });

        DB::table('tables')->update(['status' => 'available']);

        Schema::table('table_calls', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('table_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
