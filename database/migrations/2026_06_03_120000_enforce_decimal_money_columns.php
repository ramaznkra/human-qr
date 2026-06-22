<?php

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $moneyColumns = [
        'products' => ['price'],
        'orders' => ['total'],
        'order_items' => ['unit_price'],
        'product_options' => ['price_modifier'],
    ];

    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY price DECIMAL(10,2) NOT NULL');
            DB::statement('ALTER TABLE orders MODIFY total DECIMAL(10,2) NOT NULL DEFAULT 0.00');
            DB::statement('ALTER TABLE order_items MODIFY unit_price DECIMAL(10,2) NOT NULL');
            DB::statement('ALTER TABLE product_options MODIFY price_modifier DECIMAL(10,2) NOT NULL DEFAULT 0.00');
        } elseif ($driver === 'pgsql') {
            foreach ($this->moneyColumns as $table => $columns) {
                foreach ($columns as $column) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE NUMERIC(10,2)");
                }
            }
        } else {
            // SQLite: sütunlar zaten NUMERIC/decimal — yalnızca mevcut kayıtları normalize et.
        }

        $this->normalizeExistingValues();
    }

    public function down(): void
    {
        // Geri alma: sütun tipleri zaten decimal(10,2); veri kaybı olmadan bırakılır.
    }

    private function normalizeExistingValues(): void
    {
        foreach ($this->moneyColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->lazy()->each(function ($row) use ($table, $columns) {
                $updates = [];

                foreach ($columns as $column) {
                    if (! property_exists($row, $column) && ! isset($row->{$column})) {
                        continue;
                    }

                    $updates[$column] = Money::normalize($row->{$column});
                }

                if ($updates !== []) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            });
        }
    }
};
