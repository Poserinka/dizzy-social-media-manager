<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Poster\Renderers;

use RuntimeException;

defined('ABSPATH') || exit;

final class PosterRenderer
{
    /** @param array{width:int,height:int,dpi:int} $format */
    public function render(int $attachmentId, array $format, array $content): void
    {
        $path = get_attached_file($attachmentId);
        if (! is_string($path) || $path === '' || ! is_file($path) || ! function_exists('imagecreatefromstring')) {
            throw new RuntimeException('The server image library is not available. Enable the PHP GD extension.');
        }

        $bytes = file_get_contents($path);
        $source = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($source === false) {
            throw new RuntimeException('The selected background image could not be opened.');
        }

        $width = (int) $format['width'];
        $height = (int) $format['height'];
        $canvas = imagecreatetruecolor($width, $height);
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);
        $sourceX = max(0, (int) (($sourceWidth - $cropWidth) / 2));
        $sourceY = max(0, (int) (($sourceHeight - $cropHeight) / 2));
        imagecopyresampled($canvas, $source, 0, 0, $sourceX, $sourceY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($source);

        imagealphablending($canvas, true);
        $this->drawLayer($canvas, $width, $height);
        $this->drawWatermark($canvas, $width, $height, (int) $format['dpi'] >= 300);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        $muted = imagecolorallocate($canvas, 224, 224, 224);
        $title = trim((string) ($content['title'] ?? ''));
        $date = trim((string) ($content['date'] ?? ''));
        $venue = trim((string) ($content['venue'] ?? 'Jazzcafe Dizzy Rotterdam'));
        $titleSize = max(32, (int) round($width * 0.064));
        $dateSize = max(20, (int) round($width * 0.026));
        $titleX = $this->position($width, 'dizzy_social_title_x', 7.5);
        $titleY = $this->position($height, 'dizzy_social_title_y', 68) + $titleSize;
        $dateX = $this->position($width, 'dizzy_social_date_x', 7.5);
        $dateY = $this->position($height, 'dizzy_social_date_y', 88) + $dateSize;
        $titleFont = $this->fontPath('dizzy_social_title_font_id');
        $dateFont = $this->fontPath('dizzy_social_date_font_id');

        $this->drawWrapped($canvas, $title, $titleX, $titleY, $width - $titleX - (int) round($width * 0.05), $titleSize, $white, $titleFont, 3);
        $this->drawText($canvas, strtoupper($date), $dateX, $dateY, $dateSize, $white, $dateFont);
        $this->drawText($canvas, $venue, $dateX, $dateY + (int) ($dateSize * 1.65), $dateSize, $muted, $dateFont);

        if (function_exists('imageresolution')) {
            imageresolution($canvas, (int) $format['dpi'], (int) $format['dpi']);
        }
        if (! imagepng($canvas, $path, 7)) {
            imagedestroy($canvas);
            throw new RuntimeException('The final poster could not be saved.');
        }
        imagedestroy($canvas);
        wp_update_attachment_metadata($attachmentId, wp_generate_attachment_metadata($attachmentId, $path));
    }

    private function position(int $size, string $option, float $default): int
    {
        $percent = max(0, min(100, (float) get_option($option, $default)));
        return (int) round($size * ($percent / 100));
    }

    private function fontPath(string $option): string
    {
        $fontId = (int) get_option($option, 0);
        $font = $fontId > 0 ? get_attached_file($fontId) : '';
        if (is_string($font) && $font !== '' && is_readable($font)) {
            return $font;
        }
        $fallback = (string) apply_filters('dizzy_social_poster_font_path', '', $option);
        return $fallback !== '' && is_readable($fallback) ? $fallback : '';
    }

    private function drawLayer($canvas, int $width, int $height): void
    {
        $layerId = (int) get_option('dizzy_social_layer_image_id', 0);
        $path = $layerId > 0 ? get_attached_file($layerId) : '';
        if (! is_string($path) || $path === '' || ! is_readable($path)) return;
        $bytes = file_get_contents($path);
        $layer = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($layer === false) return;
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $layer, 0, 0, 0, 0, $width, $height, imagesx($layer), imagesy($layer));
        imagedestroy($layer);
    }

    private function drawWrapped($image, string $text, int $x, int $y, int $maxWidth, int $size, int $color, string $font, int $maxLines): int
    {
        $words = preg_split('/\s+/u', $text) ?: [];
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = trim($line . ' ' . $word);
            if ($line !== '' && $this->textWidth($candidate, $size, $font) > $maxWidth) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
            if (count($lines) >= $maxLines - 1) break;
        }
        if ($line !== '') $lines[] = $line;
        foreach (array_slice($lines, 0, $maxLines) as $lineText) {
            $this->drawText($image, $lineText, $x, $y, $size, $color, $font);
            $y += (int) round($size * 1.25);
        }
        return $y;
    }

    private function drawText($image, string $text, int $x, int $y, int $size, int $color, string $font): void
    {
        if ($font !== '' && function_exists('imagettftext')) {
            imagettftext($image, $size, 0, $x, $y, $color, $font, $text);
            return;
        }
        $baseWidth = max(1, strlen($text) * imagefontwidth(5));
        $baseHeight = imagefontheight(5);
        $scale = max(1, $size / $baseHeight);
        $textImage = imagecreatetruecolor($baseWidth, $baseHeight);
        imagecolortransparent($textImage, imagecolorallocate($textImage, 0, 0, 0));
        $components = imagecolorsforindex($image, $color);
        $temporaryColor = imagecolorallocate($textImage, (int) $components['red'], (int) $components['green'], (int) $components['blue']);
        imagestring($textImage, 5, 0, 0, $text, $temporaryColor);
        imagecopyresampled($image, $textImage, $x, max(0, $y - $size), 0, 0, (int) round($baseWidth * $scale), $size, $baseWidth, $baseHeight);
        imagedestroy($textImage);
    }

    private function textWidth(string $text, int $size, string $font): int
    {
        if ($font !== '' && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            return is_array($box) ? abs($box[2] - $box[0]) : 0;
        }
        return (int) round(strlen($text) * imagefontwidth(5) * max(1, $size / imagefontheight(5)));
    }

    private function drawWatermark($canvas, int $canvasWidth, int $canvasHeight, bool $isPrint): void
    {
        $enabled = (bool) get_option($isPrint ? 'dizzy_social_watermark_print' : 'dizzy_social_watermark_social', ! $isPrint);
        if (! $enabled) return;
        $logoId = (int) get_option('dizzy_social_watermark_image_id', 0);
        if ($logoId <= 0) $logoId = (int) get_theme_mod('custom_logo', 0);
        $path = $logoId > 0 ? get_attached_file($logoId) : '';
        if (! is_string($path) || $path === '' || ! is_readable($path)) return;
        $bytes = file_get_contents($path);
        $logo = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($logo === false) return;
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $sizeMode = (string) get_option('dizzy_social_watermark_size_mode', 'scaled');
        $targetWidth = match ($sizeMode) {
            'original' => $logoWidth,
            'custom' => (int) get_option('dizzy_social_watermark_custom_width', 400),
            default => (int) round($canvasWidth * ((int) get_option('dizzy_social_watermark_scale', 35) / 100)),
        };
        $targetWidth = max(1, min($canvasWidth, $targetWidth));
        $targetHeight = min($canvasHeight, max(1, (int) round($logoHeight * ($targetWidth / $logoWidth))));
        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, $logoWidth, $logoHeight);
        imagedestroy($logo);
        $this->applyOpacity($scaled, max(0, min(100, (int) get_option('dizzy_social_watermark_opacity', 85))));
        $alignment = (string) get_option('dizzy_social_watermark_alignment', 'top_center');
        [$vertical, $horizontal] = array_pad(explode('_', $alignment, 2), 2, 'center');
        $x = match ($horizontal) {'left' => 0, 'right' => $canvasWidth - $targetWidth, default => (int) round(($canvasWidth - $targetWidth) / 2)};
        $y = match ($vertical) {'top' => 0, 'bottom' => $canvasHeight - $targetHeight, default => (int) round(($canvasHeight - $targetHeight) / 2)};
        $offsetX = (float) get_option('dizzy_social_watermark_offset_x', 0);
        $offsetY = (float) get_option('dizzy_social_watermark_offset_y', 0);
        if ((string) get_option('dizzy_social_watermark_offset_unit', 'percentages') === 'percentages') {
            $offsetX = $canvasWidth * ($offsetX / 100);
            $offsetY = $canvasHeight * ($offsetY / 100);
        }
        $x = max(0, min($canvasWidth - $targetWidth, $x + (int) round($offsetX)));
        $y = max(0, min($canvasHeight - $targetHeight, $y + (int) round($offsetY)));
        imagecopy($canvas, $scaled, $x, $y, 0, 0, $targetWidth, $targetHeight);
        imagedestroy($scaled);
    }

    private function applyOpacity($image, int $opacity): void
    {
        if ($opacity >= 100) return;
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $visible = (127 - $alpha) * ($opacity / 100);
                imagesetpixel($image, $x, $y, ($rgba & 0xFFFFFF) | ((127 - (int) round($visible)) << 24));
            }
        }
    }
}
