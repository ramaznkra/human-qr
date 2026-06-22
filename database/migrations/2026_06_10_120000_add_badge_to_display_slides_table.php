<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('display_slides', function (Blueprint $table) {
            $table->string('badge', 80)->nullable()->after('subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('display_slides', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
