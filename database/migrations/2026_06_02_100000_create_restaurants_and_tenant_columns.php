<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** @var list<string> */
    private array $tenantTables = [
        'users',
        'categories',
        'products',
        'tables',
        'orders',
        'table_calls',
        'settings',
        'display_slides',
        'cafe_galleries',
    ];

    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kitchen_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $defaultKitchenToken = Str::random(48);

        DB::table('restaurants')->insert([
            'id' => 1,
            'name' => 'Human Cafe',
            'slug' => 'human',
            'kitchen_token' => $defaultKitchenToken,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($this->tenantTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('restaurant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
            });

            DB::table($tableName)->whereNull('restaurant_id')->update(['restaurant_id' => 1]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->after('restaurant_id');
        });

        foreach (DB::table('orders')->select('id')->get() as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'public_token' => (string) Str::uuid(),
            ]);
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unique('public_token');
        });

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['slug']);
                $table->unique(['restaurant_id', 'slug']);
            });
        }

        if (Schema::hasTable('tables')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->unique(['restaurant_id', 'number']);
            });
        }

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->unique(['restaurant_id', 'key']);
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['order_number']);
                $table->unique(['restaurant_id', 'order_number']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique(['restaurant_id', 'order_number']);
                $table->unique('order_number');
                $table->dropUnique(['public_token']);
                $table->dropColumn('public_token');
            });
        }

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropUnique(['restaurant_id', 'key']);
            });
        }

        if (Schema::hasTable('tables')) {
            Schema::table('tables', function (Blueprint $table) {
                $table->dropUnique(['restaurant_id', 'number']);
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropUnique(['restaurant_id', 'slug']);
                $table->unique('slug');
            });
        }

        foreach (array_reverse($this->tenantTables) as $tableName) {
            if (Schema::hasColumn($tableName, 'restaurant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('restaurant_id');
                });
            }
        }

        Schema::dropIfExists('restaurants');
    }
};
