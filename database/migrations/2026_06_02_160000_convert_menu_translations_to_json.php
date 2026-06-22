<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->migrateProductsToJson();
        $this->migrateCategoriesToJson();
    }

    public function down(): void
    {
        $this->revertCategoriesFromJson();
        $this->revertProductsFromJson();
    }

    private function migrateProductsToJson(): void
    {
        if (! Schema::hasColumn('products', 'name_en')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->json('name_json')->nullable();
            $table->json('description_json')->nullable();
        });

        foreach (DB::table('products')->orderBy('id')->get() as $row) {
            DB::table('products')->where('id', $row->id)->update([
                'name_json' => json_encode($this->buildTranslationPayload(
                    $row->name ?? null,
                    $row->name_en ?? null,
                    $row->name_ru ?? null,
                ), JSON_UNESCAPED_UNICODE),
                'description_json' => json_encode($this->buildTranslationPayload(
                    $row->description ?? null,
                    $row->description_en ?? null,
                    $row->description_ru ?? null,
                ), JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'name_en',
                'name_ru',
                'description',
                'description_en',
                'description_ru',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
            $table->renameColumn('description_json', 'description');
        });
    }

    private function migrateCategoriesToJson(): void
    {
        if (! Schema::hasColumn('categories', 'name_en')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->json('name_json')->nullable();
            $table->json('description_json')->nullable();
        });

        foreach (DB::table('categories')->orderBy('id')->get() as $row) {
            DB::table('categories')->where('id', $row->id)->update([
                'name_json' => json_encode($this->buildTranslationPayload(
                    $row->name ?? null,
                    $row->name_en ?? null,
                    $row->name_ru ?? null,
                ), JSON_UNESCAPED_UNICODE),
                'description_json' => null,
            ]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name', 'name_en', 'name_ru']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('name_json', 'name');
            $table->renameColumn('description_json', 'description');
        });
    }

    private function revertProductsFromJson(): void
    {
        if (! Schema::hasColumn('products', 'name') || Schema::hasColumn('products', 'name_en')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('name_plain', 150)->nullable();
            $table->string('name_en', 150)->nullable();
            $table->string('name_ru', 150)->nullable();
            $table->text('description_plain')->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_ru')->nullable();
        });

        foreach (DB::table('products')->orderBy('id')->get() as $row) {
            $name = $this->decodeTranslations($row->name);
            $description = $this->decodeTranslations($row->description);

            DB::table('products')->where('id', $row->id)->update([
                'name_plain' => $name['tr'] ?? $name['en'] ?? $name['ru'] ?? null,
                'name_en' => $name['en'] ?? null,
                'name_ru' => $name['ru'] ?? null,
                'description_plain' => $description['tr'] ?? $description['en'] ?? $description['ru'] ?? null,
                'description_en' => $description['en'] ?? null,
                'description_ru' => $description['ru'] ?? null,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('name_plain', 'name');
            $table->renameColumn('description_plain', 'description');
        });
    }

    private function revertCategoriesFromJson(): void
    {
        if (! Schema::hasColumn('categories', 'name') || Schema::hasColumn('categories', 'name_en')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->string('name_plain', 100)->nullable();
            $table->string('name_en', 100)->nullable();
            $table->string('name_ru', 100)->nullable();
        });

        foreach (DB::table('categories')->orderBy('id')->get() as $row) {
            $name = $this->decodeTranslations($row->name);

            DB::table('categories')->where('id', $row->id)->update([
                'name_plain' => $name['tr'] ?? $name['en'] ?? $name['ru'] ?? null,
                'name_en' => $name['en'] ?? null,
                'name_ru' => $name['ru'] ?? null,
            ]);
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['name', 'description']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->renameColumn('name_plain', 'name');
        });
    }

    /**
     * @return array<string, string>
     */
    private function buildTranslationPayload(?string $tr, ?string $en, ?string $ru): array
    {
        return array_filter([
            'tr' => filled($tr) ? (string) $tr : null,
            'en' => filled($en) ? (string) $en : null,
            'ru' => filled($ru) ? (string) $ru : null,
        ], fn ($value) => filled($value));
    }

    /**
     * @return array<string, string>
     */
    private function decodeTranslations(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_filter($decoded, fn ($v) => filled($v));
            }

            return filled($value) ? ['tr' => $value] : [];
        }

        if (is_array($value)) {
            return array_filter($value, fn ($v) => filled($v));
        }

        return [];
    }
};
