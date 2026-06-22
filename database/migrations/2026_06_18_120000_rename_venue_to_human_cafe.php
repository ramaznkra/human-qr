<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_NAMES = [
        'Human',
        'Human Restaurant',
        'Human Social',
        'HUMAN SOCIAL',
    ];

    public function up(): void
    {
        foreach (['venue_name', 'brand_mark'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->whereIn('value', self::LEGACY_NAMES)
                ->update(['value' => 'Human Cafe']);
        }

        DB::table('restaurants')
            ->whereIn('name', self::LEGACY_NAMES)
            ->update(['name' => 'Human Cafe']);
    }

    public function down(): void
    {
        //
    }
};
