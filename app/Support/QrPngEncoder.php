<?php

namespace App\Support;

use BaconQrCode\Encoder\ByteMatrix;

/**
 * Builds a true-color PNG from a QR matrix without GD or Imagick.
 */
final class QrPngEncoder
{
    public static function encode(
        ByteMatrix $matrix,
        int $marginModules,
        int $modulePixels,
        int $fgR,
        int $fgG,
        int $fgB,
        int $bgR,
        int $bgG,
        int $bgB,
    ): string {
        $modules = $matrix->getWidth();
        $side = ($modules + ($marginModules * 2)) * $modulePixels;
        $rows = '';

        for ($y = 0; $y < $side; $y++) {
            $rows .= "\x00";
            $moduleY = (int) floor($y / $modulePixels) - $marginModules;

            for ($x = 0; $x < $side; $x++) {
                $moduleX = (int) floor($x / $modulePixels) - $marginModules;
                $dark = $moduleX >= 0
                    && $moduleY >= 0
                    && $moduleX < $modules
                    && $moduleY < $modules
                    && (bool) $matrix->get($moduleX, $moduleY);

                if ($dark) {
                    $rows .= chr($fgR).chr($fgG).chr($fgB);
                } else {
                    $rows .= chr($bgR).chr($bgG).chr($bgB);
                }
            }
        }

        return self::packPng($side, $side, $rows);
    }

    private static function packPng(int $width, int $height, string $rgbRows): string
    {
        $ihdr = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $idat = gzcompress($rgbRows, 9);

        return self::signature()
            .self::chunk('IHDR', $ihdr)
            .self::chunk('IDAT', $idat)
            .self::chunk('IEND', '');
    }

    private static function signature(): string
    {
        return "\x89PNG\r\n\x1a\n";
    }

    private static function chunk(string $type, string $data): string
    {
        $chunk = $type.$data;

        return pack('N', strlen($data)).$chunk.pack('N', crc32($chunk) & 0xFFFFFFFF);
    }
}
