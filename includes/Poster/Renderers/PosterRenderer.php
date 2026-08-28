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
        $this->drawLogo($canvas, $width, $height);

        $titleColor = $this->textColor($canvas, 'dizzy_social_title_color');
        $dateColor = $this->textColor($canvas, 'dizzy_social_date_color');
        $hoursColor = $this->textColor($canvas, 'dizzy_social_hours_color');
        $title = trim((string) ($content['title'] ?? ''));
        $title = function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title);
        $date = trim((string) ($content['date'] ?? ''));
        $hours = trim((string) ($content['hours'] ?? ''));
        $titleSize = max(10, (int) round($width * ((float) get_option('dizzy_social_title_size', 6.4) / 100)));
        $dateSize = max(8, (int) round($width * ((float) get_option('dizzy_social_date_size', 2.6) / 100)));
        $hoursSize = max(8, (int) round($width * ((float) get_option('dizzy_social_hours_size', 2.6) / 100)));
        $titleX = $this->position($width, 'dizzy_social_title_x', 7.5);
        $titleY = $this->position($height, 'dizzy_social_title_y', 68);
        $dateX = $this->position($width, 'dizzy_social_date_x', 7.5);
        $dateY = $this->position($height, 'dizzy_social_date_y', 86);
        $hoursX = $this->position($width, 'dizzy_social_hours_x', 7.5);
        $hoursY = $this->position($height, 'dizzy_social_hours_y', 92);
        $titleFont = $this->fontPath('dizzy_social_title_font');
        $dateFont = $this->fontPath('dizzy_social_date_font');
        $hoursFont = $this->fontPath('dizzy_social_hours_font');
        $titleAlign = $this->alignment('dizzy_social_title_align');
        $dateAlign = $this->alignment('dizzy_social_date_align');
        $hoursAlign = $this->alignment('dizzy_social_hours_align');

        if ((bool) get_option('dizzy_social_title_enabled', true)) {
            $this->drawWrapped($canvas, $title, $titleX, $titleY, $this->availableWidth($width, $titleX, $titleAlign), $titleSize, $titleColor, $titleFont, 3, $titleAlign);
        }
        if ((bool) get_option('dizzy_social_date_enabled', true)) {
            $this->drawAlignedText($canvas, strtoupper($date), $dateX, $dateY, $dateSize, $dateColor, $dateFont, $dateAlign);
        }
        if ((bool) get_option('dizzy_social_hours_enabled', true)) {
            $this->drawAlignedText($canvas, $hours, $hoursX, $hoursY, $hoursSize, $hoursColor, $hoursFont, $hoursAlign);
        }

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

    private function textColor($canvas, string $option): int
    {
        $value = sanitize_hex_color((string) get_option($option, '#ffffff')) ?: '#ffffff';
        return imagecolorallocate(
            $canvas,
            hexdec(substr($value, 1, 2)),
            hexdec(substr($value, 3, 2)),
            hexdec(substr($value, 5, 2))
        );
    }

    private function position(int $size, string $option, float $default): int
    {
        $percent = max(0, min(100, (float) get_option($option, $default)));
        return (int) round($size * ($percent / 100));
    }

    private function alignment(string $option): string
    {
        $value = (string) get_option($option, 'left');
        return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
    }

    private function availableWidth(int $canvasWidth, int $anchorX, string $alignment): int
    {
        $margin = (int) round($canvasWidth * 0.04);
        return max(1, match ($alignment) {
            'center' => 2 * min(max(1, $anchorX - $margin), max(1, $canvasWidth - $anchorX - $margin)),
            'right' => $anchorX - $margin,
            default => $canvasWidth - $anchorX - $margin,
        });
    }

    private function fontPath(string $option): string
    {
        $filename = sanitize_file_name((string) get_option($option, ''));
        $basePath = defined('DIZZY_EVENTS_PATH') ? (string) DIZZY_EVENTS_PATH : trailingslashit(WP_PLUGIN_DIR) . 'dizzy-events-manager/';
        $font = $filename !== '' ? trailingslashit($basePath) . 'assets/fonts/' . $filename : '';
        if ($font !== '' && is_readable($font) && in_array(strtolower((string) pathinfo($font, PATHINFO_EXTENSION)), ['ttf', 'otf'], true)) return $font;
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
        $scaled = imagecreatetruecolor($width, $height);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $layer, 0, 0, 0, 0, $width, $height, imagesx($layer), imagesy($layer));
        imagedestroy($layer);
        imagealphablending($canvas, true);
        imagecopy($canvas, $scaled, 0, 0, 0, 0, $width, $height);
        imagedestroy($scaled);
    }

    private function drawWrapped($image, string $text, int $anchorX, int $y, int $maxWidth, int $size, int $color, string $font, int $maxLines, string $alignment): int
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
            $this->drawAlignedText($image, $lineText, $anchorX, $y, $size, $color, $font, $alignment);
            $y += (int) round($size * 1.25);
        }
        return $y;
    }

    private function drawAlignedText($image, string $text, int $anchorX, int $y, int $size, int $color, string $font, string $alignment): void
    {
        $width = $this->textWidth($text, $size, $font);
        $x = match ($alignment) {
            'center' => $anchorX - (int) round($width / 2),
            'right' => $anchorX - $width,
            default => $anchorX,
        };
        $this->drawText($image, $text, max(0, $x), $y, $size, $color, $font);
    }

    private function drawText($image, string $text, int $x, int $y, int $size, int $color, string $font): void
    {
        if ($font !== '' && function_exists('imagettftext') && function_exists('imagettfbbox')) {
            $box = imagettfbbox($size, 0, $font, $text);
            if (is_array($box)) {
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $minY = min($box[1], $box[3], $box[5], $box[7]);
                imagettftext($image, $size, 0, $x - $minX, $y - $minY, $color, $font, $text);
                return;
            }
        }

        $baseWidth = max(1, strlen($text) * imagefontwidth(5));
        $baseHeight = imagefontheight(5);
        $scale = max(1, $size / $baseHeight);
        $targetWidth = max(1, (int) round($baseWidth * $scale));
        $textImage = imagecreatetruecolor($baseWidth, $baseHeight);
        imagealphablending($textImage, false);
        imagesavealpha($textImage, true);
        imagefill($textImage, 0, 0, imagecolorallocatealpha($textImage, 0, 0, 0, 127));
        imagealphablending($textImage, true);
        $components = imagecolorsforindex($image, $color);
        $temporaryColor = imagecolorallocatealpha($textImage, (int) $components['red'], (int) $components['green'], (int) $components['blue'], 0);
        imagestring($textImage, 5, 0, 0, $text, $temporaryColor);
        imagealphablending($image, true);
        imagecopyresampled($image, $textImage, $x, $y, 0, 0, $targetWidth, $size, $baseWidth, $baseHeight);
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

    private function drawLogo($canvas, int $canvasWidth, int $canvasHeight): void
    {
        if (! (bool) get_option('dizzy_social_logo_enabled', true)) return;
        $logoId = (int) get_option('dizzy_social_logo_image_id', 0);
        $path = $logoId > 0 ? get_attached_file($logoId) : '';
        if (! is_string($path) || $path === '' || ! is_readable($path)) return;
        $bytes = file_get_contents($path);
        $logo = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
        if ($logo === false) return;
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        $targetWidth = (int) round($canvasWidth * ((float) get_option('dizzy_social_logo_width', 25) / 100));
        $targetWidth = max(1, min($canvasWidth, $targetWidth));
        $targetHeight = min($canvasHeight, max(1, (int) round($logoHeight * ($targetWidth / $logoWidth))));
        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $logo, 0, 0, 0, 0, $targetWidth, $targetHeight, $logoWidth, $logoHeight);
        imagedestroy($logo);
        $x = $this->position($canvasWidth, 'dizzy_social_logo_x', 70);
        $y = $this->position($canvasHeight, 'dizzy_social_logo_y', 5);
        $x = max(0, min($canvasWidth - $targetWidth, $x));
        $y = max(0, min($canvasHeight - $targetHeight, $y));
        imagecopy($canvas, $scaled, $x, $y, 0, 0, $targetWidth, $targetHeight);
        imagedestroy($scaled);
    }

}
