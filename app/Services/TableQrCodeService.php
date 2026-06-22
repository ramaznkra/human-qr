<?php

namespace App\Services;

use App\Models\Table;
use App\Support\QrPngEncoder;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Encoder\QrCode;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use Illuminate\Support\Facades\File;

class TableQrCodeService
{
    /** Quiet zone in QR modules (ISO standard is 4). */
    public const MARGIN_MODULES = 4;

    /** Pixels per QR module (5×5 blocks). */
    public const MODULE_PIXELS = 5;

    private const FG = [38, 34, 32];

    private const BG = [255, 255, 255];

    public function menuUrl(Table $table): string
    {
        return route('menu.table', ['uuid' => $table->uuid]);
    }

    public function generateFor(Table $table): void
    {
        $directory = storage_path('app/public/qr');
        File::ensureDirectoryExists($directory);

        $qrCode = $this->encode($this->menuUrl($table));
        $base = 'qr/table-'.$table->id;

        $svgPath = storage_path('app/public/'.$base.'.svg');
        File::put($svgPath, $this->renderSvg($qrCode));

        $pngPath = storage_path('app/public/'.$base.'.png');
        File::put($pngPath, $this->renderPng($qrCode));

        $table->update(['qr_image_path' => $base.'.png']);
    }

    public function deleteFor(Table $table): void
    {
        foreach (['.svg', '.png'] as $ext) {
            $path = storage_path('app/public/qr/table-'.$table->id.$ext);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }

    public function downloadPath(Table $table, string $format): ?string
    {
        $path = storage_path('app/public/qr/table-'.$table->id.'.'.$format);

        return File::exists($path) ? $path : null;
    }

    public function ensureFiles(Table $table): void
    {
        if ($this->downloadPath($table, 'svg') && $this->downloadPath($table, 'png')) {
            return;
        }

        $this->generateFor($table);
    }

    public function pngContents(Table $table): string
    {
        $this->ensureFiles($table);

        $path = $this->downloadPath($table, 'png');
        if ($path) {
            return (string) File::get($path);
        }

        return $this->renderPng($this->encode($this->menuUrl($table)));
    }

    public function svgContents(Table $table): string
    {
        $this->ensureFiles($table);

        $path = $this->downloadPath($table, 'svg');
        if ($path) {
            return (string) File::get($path);
        }

        return $this->renderSvg($this->encode($this->menuUrl($table)));
    }

    private function encode(string $url): QrCode
    {
        return Encoder::encode($url, ErrorCorrectionLevel::H());
    }

    private function rendererStyle(QrCode $qrCode): RendererStyle
    {
        $matrixSize = $qrCode->getMatrix()->getWidth();
        $pixelSize = ($matrixSize + (self::MARGIN_MODULES * 2)) * self::MODULE_PIXELS;

        return new RendererStyle(
            $pixelSize,
            self::MARGIN_MODULES,
            null,
            null,
            Fill::uniformColor(
                new Rgb(self::BG[0], self::BG[1], self::BG[2]),
                new Rgb(self::FG[0], self::FG[1], self::FG[2]),
            ),
        );
    }

    private function renderSvg(QrCode $qrCode): string
    {
        $renderer = new ImageRenderer(
            $this->rendererStyle($qrCode),
            new SvgImageBackEnd,
        );

        return $renderer->render($qrCode);
    }

    private function renderPng(QrCode $qrCode): string
    {
        return QrPngEncoder::encode(
            $qrCode->getMatrix(),
            self::MARGIN_MODULES,
            self::MODULE_PIXELS,
            self::FG[0],
            self::FG[1],
            self::FG[2],
            self::BG[0],
            self::BG[1],
            self::BG[2],
        );
    }
}
