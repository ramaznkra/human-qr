<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private array $photos = [
        'yiyecek' => 'images/categories/photos/yiyecek.jpg',
        'icecek' => 'images/categories/photos/icecek.jpg',
        'nargile' => 'images/categories/photos/nargile.jpg',
        'okey' => 'images/categories/photos/okey.jpg',
        'biblo' => 'images/categories/photos/biblo.jpg',
    ];

    public function up(): void
    {
        foreach ($this->photos as $slug => $path) {
            if (! file_exists(public_path($path))) {
                continue;
            }

            DB::table('categories')
                ->where('slug', $slug)
                ->update(['image' => $path]);
        }
    }

    public function down(): void
    {
        $samples = [
            'yiyecek' => 'images/categories/samples/yiyecek.svg',
            'icecek' => 'images/categories/samples/icecek.svg',
            'nargile' => 'images/categories/samples/nargile.svg',
            'okey' => 'images/categories/samples/okey.svg',
            'biblo' => 'images/categories/samples/okey.svg',
        ];

        foreach ($samples as $slug => $path) {
            DB::table('categories')
                ->where('slug', $slug)
                ->where('image', $this->photos[$slug] ?? '')
                ->update(['image' => $path]);
        }
    }
};
