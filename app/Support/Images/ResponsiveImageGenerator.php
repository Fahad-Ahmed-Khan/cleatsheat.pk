<?php

namespace App\Support\Images;

use App\Support\Storage\PublicAssetUrl;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Generates resized WebP variants for a stored image on the public disk.
 *
 * Uses the native GD extension (no external dependency) so it works on
 * shared hosting where adding/compiling extra libraries is awkward.
 */
class ResponsiveImageGenerator
{
    /** Target widths in pixels for product cards. Only ever downscales (never upscales). */
    public const WIDTHS = [320, 640, 960];

    /** Larger widths for the full-bleed hero/LCP image. */
    public const HERO_WIDTHS = [640, 960, 1280, 1920];

    private const WEBP_QUALITY = 78;

    public function isSupported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /**
     * Generate WebP variants for the given stored path (relative, /storage/... or full URL).
     *
     * @param  list<int>|null  $widths  Target widths; defaults to product-card WIDTHS.
     * @return array{width:int,height:int,variants:list<array{w:int,path:string}>}|null
     */
    public function generate(?string $storedPath, ?array $widths = null): ?array
    {
        if (! $this->isSupported() || $storedPath === null) {
            return null;
        }

        // External URLs can't be reprocessed locally.
        if (preg_match('#^https?://#i', trim($storedPath)) === 1
            && PublicAssetUrl::normalizeForStorage($storedPath) === trim($storedPath)) {
            return null;
        }

        $relative = PublicAssetUrl::normalizeForStorage($storedPath);
        if ($relative === null || $relative === '') {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            return null;
        }

        $raw = $disk->get($relative);
        if ($raw === null) {
            return null;
        }

        $src = @imagecreatefromstring($raw);
        if (! $src instanceof GdImage) {
            return null;
        }

        $src = $this->applyExifOrientation($src, $raw);
        $origW = imagesx($src);
        $origH = imagesy($src);

        if ($origW < 1 || $origH < 1) {
            imagedestroy($src);

            return null;
        }

        $variants = [];
        $seenWidths = [];

        $targetWidths = $widths ?? self::WIDTHS;

        foreach ($targetWidths as $targetW) {
            $width = min($targetW, $origW);
            if (in_array($width, $seenWidths, true)) {
                continue; // skip duplicate when original is smaller than a target
            }
            $seenWidths[] = $width;

            $height = max(1, (int) round($origH * ($width / $origW)));
            $resized = $this->resample($src, $width, $height);
            $data = $this->encodeWebp($resized);
            imagedestroy($resized);

            if ($data === null) {
                continue;
            }

            $variantPath = $this->variantPath($relative, $targetW);
            $disk->put($variantPath, $data);
            $variants[] = ['w' => $width, 'path' => $variantPath];
        }

        imagedestroy($src);

        if ($variants === []) {
            return null;
        }

        return [
            'width' => $origW,
            'height' => $origH,
            'variants' => $variants,
        ];
    }

    /**
     * Remove all variant files previously generated for a stored path.
     *
     * @param  list<int>|null  $widths  Target widths to purge; defaults to product-card WIDTHS.
     */
    public function purge(?string $storedPath, ?array $widths = null): void
    {
        $relative = PublicAssetUrl::normalizeForStorage($storedPath);
        if ($relative === null || $relative === '') {
            return;
        }

        $disk = Storage::disk('public');
        foreach ($widths ?? self::WIDTHS as $targetW) {
            $path = $this->variantPath($relative, $targetW);
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }

    private function variantPath(string $relative, int $width): string
    {
        $dir = trim((string) pathinfo($relative, PATHINFO_DIRNAME), '.');
        $name = pathinfo($relative, PATHINFO_FILENAME);
        $prefix = $dir !== '' && $dir !== '/' ? rtrim($dir, '/').'/' : '';

        return $prefix.$name.'-'.$width.'.webp';
    }

    private function resample(GdImage $src, int $width, int $height): GdImage
    {
        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, imagesx($src), imagesy($src));

        return $dst;
    }

    private function encodeWebp(GdImage $image): ?string
    {
        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $data = ob_get_clean();

        return $ok && is_string($data) && $data !== '' ? $data : null;
    }

    private function applyExifOrientation(GdImage $image, string $raw): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
        } catch (\Throwable) {
            return $image;
        }

        $orientation = is_array($exif) ? ($exif['Orientation'] ?? null) : null;
        if (! in_array($orientation, [3, 6, 8], true)) {
            return $image;
        }

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
