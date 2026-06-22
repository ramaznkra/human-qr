<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tables', 'uuid')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }

        // Mevcut masalar için UUID üret (sıralı id yerine tahmin edilemez kimlik).
        foreach (DB::table('tables')->whereNull('uuid')->pluck('id') as $id) {
            DB::table('tables')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
        }

        Schema::table('tables', function (Blueprint $table) {
            $table->unique('uuid');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tables MODIFY uuid CHAR(36) NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tables', 'uuid')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->dropUnique(['uuid']);
                $table->dropColumn('uuid');
            });
        }
    }
};
