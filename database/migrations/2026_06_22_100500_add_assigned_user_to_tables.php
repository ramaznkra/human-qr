<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (! Schema::hasColumn('tables', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (Schema::hasColumn('tables', 'assigned_user_id')) {
                $table->dropConstrainedForeignId('assigned_user_id');
            }
        });
    }
};
