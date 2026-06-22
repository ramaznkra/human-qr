<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // waiter | bill
            $table->string('status', 20)->default('pending'); // pending | acknowledged
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_calls');
    }
};
