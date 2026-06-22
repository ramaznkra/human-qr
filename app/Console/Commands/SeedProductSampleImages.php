<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedProductSampleImages extends Command
{
    protected $signature = 'products:seed-sample-images {--force : Mevcut görsellerin üzerine yaz}';

    protected $description = 'Menü ürünlerine konuya uygun örnek fotoğraflar indirir';

    /**
     * @var array<string, array{foodish?: string, url?: string}>
     */
    private const IMAGES = [
        'Human Burger' => ['url' => 'https://loremflickr.com/1200/900/burger,food?lock=10'],
        'Sosyal Tabağı' => ['foodish' => 'pizza'],
        'Nachos' => ['foodish' => 'pasta'],
        'Espresso' => ['url' => 'https://loremflickr.com/1200/900/espresso,coffee?lock=1'],
        'Latte' => ['url' => 'https://loremflickr.com/1200/900/latte,coffee?lock=2'],
        'Limonata' => ['url' => 'https://loremflickr.com/1200/900/lemonade,drink?lock=3'],
        'Mojito' => ['url' => 'https://loremflickr.com/1200/900/mojito,cocktail?lock=4'],
        'Elma Nargile' => ['url' => 'https://loremflickr.com/1200/900/hookah,shisha?lock=5'],
        'Üzüm Nargile' => ['url' => 'https://loremflickr.com/1200/900/shisha,lounge?lock=6'],
        'Karışık Nargile' => ['url' => 'https://loremflickr.com/1200/900/hookah,bowl?lock=7'],
        'Okey Masası (Saatlik)' => ['url' => 'https://loremflickr.com/1200/900/okey,tiles?lock=8'],
        'Okey + İçecek Paketi' => ['url' => 'https://loremflickr.com/1200/900/coffee,drinks?lock=9'],
    ];

    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('products');

        $force = (bool) $this->option('force');
        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach (self::IMAGES as $productName => $source) {
            $product = Product::where('name->tr', $productName)->first();

            if (! $product) {
                $this->warn("Ürün bulunamadı: {$productName}");
                $fail++;

                continue;
            }

            if ($product->image && ! $force) {
                $this->line("Atlandı (zaten görsel var): {$productName}");
                $skip++;

                continue;
            }

            if ($product->image && $force) {
                $this->deleteStoredImage($product->image);
            }

            $url = $this->resolveImageUrl($source);
            if (! $url) {
                $this->error("URL alınamadı: {$productName}");
                $fail++;

                continue;
            }

            try {
                $response = Http::withoutVerifying()
                    ->withOptions(['allow_redirects' => true])
                    ->timeout(90)
                    ->withHeaders(['User-Agent' => 'Human-QR-Menu/1.0'])
                    ->get($url);

                if (! $response->successful()) {
                    $this->error("İndirilemedi ({$response->status()}): {$productName}");
                    $fail++;

                    continue;
                }

                $relativePath = 'products/'.Str::slug($productName).'-'.Str::random(6).'.jpg';
                $binary = $response->body();
                $binary = $this->optimizeIfLarge($binary) ?? $binary;

                Storage::disk('public')->put($relativePath, $binary);

                $product->update(['image' => $relativePath]);
                $sizeKb = (int) round(strlen($binary) / 1024);

                $this->info("✓ {$productName} → {$relativePath} ({$sizeKb} KB)");
                $ok++;
                sleep(1);
            } catch (\Throwable $e) {
                $this->error("Hata ({$productName}): {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        $this->info("Tamamlandı: {$ok} yüklendi, {$skip} atlandı, {$fail} hata.");

        if ($ok > 0) {
            $this->comment('Menüyü yenileyin (Ctrl+F5).');
        }

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{foodish?: string, url?: string}  $source
     */
    private function resolveImageUrl(array $source): ?string
    {
        if (! empty($source['url'])) {
            return $source['url'];
        }

        if (empty($source['foodish'])) {
            return null;
        }

        $response = Http::withoutVerifying()
            ->timeout(30)
            ->get('https://foodish-api.com/api/images/'.$source['foodish']);

        if (! $response->successful()) {
            return null;
        }

        return $response->json('image');
    }

    private function deleteStoredImage(string $path): void
    {
        if (str_starts_with($path, 'http')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    /** 500 KB üzeri JPEG görselleri menü için küçültür (GD varsa). */
    private function optimizeIfLarge(string $binary): ?string
    {
        if (strlen($binary) < 500_000 || ! extension_loaded('gd')) {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $max = 1200;
        $ratio = min($max / $width, $max / $height, 1);
        $newW = max(1, (int) round($width * $ratio));
        $newH = max(1, (int) round($height * $ratio));

        $canvas = $newW !== $width || $newH !== $height
            ? imagescale($source, $newW, $newH)
            : $source;

        if ($canvas === false) {
            imagedestroy($source);

            return null;
        }

        ob_start();
        imagejpeg($canvas, null, 85);
        $out = ob_get_clean();

        if ($canvas !== $source) {
            imagedestroy($canvas);
        }
        imagedestroy($source);

        return $out !== false ? $out : null;
    }
}
