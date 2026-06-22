<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menü / slider / ürün görsellerini yüklemeden önce küçültür ve .webp olarak saklar.
 *
 * Bu sınıf yerel PHP GD eklentisini kullanır (ek paket gerektirmez):
 *  - Yeniden boyutlandırma için `imagescale`
 *  - .webp çıktısı için `imagewebp` (GD, libwebp desteğiyle derlenmiş olmalı — `gd_info()` → "WebP Support")
 *
 * GD veya webp desteği yoksa otomatik olarak .jpg'e, o da olmazsa orijinal dosyaya düşülür.
 *
 * Daha gelişmiş ihtiyaçlar (Imagick, akıllı kırpma, otomatik format, sorumluluk ayrımı) için
 * standart kütüphaneler önerilir:
 *  - Intervention Image:  composer require intervention/image
 *      $img = (new \Intervention\Image\ImageManager(\Intervention\Image\Drivers\Gd\Driver::class))
 *                  ->read($file)->scaleDown(width: 800);
 *      Storage::disk('public')->put($path, (string) $img->toWebp(82));
 *  - Spatie Media Library: composer require spatie/laravel-medialibrary
 *      (model'e attachMedia + otomatik conversion: ->fit(Manipulations::FIT_MAX, 800, 800)->format('webp'))
 */
class MenuImageOptimizer
{
    public function storeGallery(UploadedFile $file): string
    {
        return $this->store($file, 'cafe-galleries', 1200, 800, 80);
    }

    public function storeProduct(UploadedFile $file): string
    {
        // Ürün görseli: maksimum 800x800, .webp.
        return $this->store($file, 'products', 800, 800, 82);
    }

    public function storeCategory(UploadedFile $file): string
    {
        return $this->store($file, 'categories', 1000, 600, 82);
    }

    public function store(
        UploadedFile $file,
        string $directory,
        int $maxWidth = 1200,
        int $maxHeight = 1200,
        int $quality = 82,
    ): string {
        if (! extension_loaded('gd')) {
            return $file->store($directory, 'public');
        }

        $source = $this->loadImage($file->getRealPath(), $file->getMimeType());
        if ($source === null) {
            return $file->store($directory, 'public');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        [$newWidth, $newHeight] = $this->scaledDimensions($width, $height, $maxWidth, $maxHeight);

        $canvas = $newWidth !== $width || $newHeight !== $height
            ? imagescale($source, $newWidth, $newHeight)
            : $source;

        if ($canvas === false) {
            imagedestroy($source);

            return $file->store($directory, 'public');
        }

        // webp tercih edilir; GD'de webp desteği yoksa .jpg'e düşülür.
        $useWebp = function_exists('imagewebp');
        $extension = $useWebp ? 'webp' : 'jpg';

        Storage::disk('public')->makeDirectory($directory);
        $relativePath = $directory.'/'.Str::uuid().'.'.$extension;
        $fullPath = Storage::disk('public')->path($relativePath);

        if ($useWebp) {
            // PNG → webp şeffaflığını koru.
            imagepalettetotruecolor($canvas);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $saved = imagewebp($canvas, $fullPath, $quality);
        } else {
            $saved = imagejpeg($canvas, $fullPath, $quality);
        }

        if ($canvas !== $source) {
            imagedestroy($canvas);
        }
        imagedestroy($source);

        if (! $saved) {
            return $file->store($directory, 'public');
        }

        return $relativePath;
    }

    private function loadImage(string $path, string $mime): ?\GdImage
    {
        return match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($path) ?: null,
            'image/png' => $this->loadPng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($path) ?: null) : null,
            'image/gif' => @imagecreatefromgif($path) ?: null,
            default => null,
        };
    }

    private function loadPng(string $path): ?\GdImage
    {
        $img = @imagecreatefrompng($path);
        if ($img === false) {
            return null;
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $img;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function scaledDimensions(int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        if ($width <= $maxWidth && $height <= $maxHeight) {
            return [$width, $height];
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);

        return [
            max(1, (int) round($width * $ratio)),
            max(1, (int) round($height * $ratio)),
        ];
    }
}
