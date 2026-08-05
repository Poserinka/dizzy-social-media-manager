<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Poster\Services;

use Dizzy\SocialMedia\Poster\Models\Poster;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;
use Dizzy\SocialMedia\Poster\Renderers\PosterRenderer;
use Dizzy\SocialMedia\Poster\Support\PosterFormats;
use RuntimeException;
use Throwable;

defined('ABSPATH') || exit;

final class PosterService
{
    public function __construct(
        private readonly PosterRepository $repository,
        private readonly PosterRenderer $renderer,
    ) {
    }

    public function create(array $data): Poster
    {
        $formatKey = PosterFormats::sanitize((string) ($data['format'] ?? 'social_square'));
        $format = PosterFormats::get($formatKey);
        $sourceAttachmentId = (int) ($data['source_attachment_id'] ?? 0);
        $imageUrl = isset($data['image_url']) && is_string($data['image_url'])
            ? trim($data['image_url'])
            : '';

        $attachmentId = $sourceAttachmentId > 0
            ? $this->duplicateAttachment($sourceAttachmentId, (int) ($data['event_id'] ?? 0))
            : $this->importMedia($imageUrl, (int) ($data['event_id'] ?? 0));

        if ($attachmentId === 0) {
            throw new RuntimeException('Generated poster could not be imported into the media library.');
        }

        try {
            $this->renderer->render($attachmentId, $format, [
                'title' => (string) ($data['title'] ?? ''),
                'date' => (string) ($data['date'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            wp_delete_attachment($attachmentId, true);
            throw $exception;
        }

        update_post_meta($attachmentId, '_dizzy_poster_format', $formatKey);
        update_post_meta($attachmentId, '_dizzy_poster_dpi', (int) $format['dpi']);

        $localUrl = wp_get_attachment_url($attachmentId);

        if (! is_string($localUrl) || $localUrl === '') {
            throw new RuntimeException('Imported poster has no media URL.');
        }

        $data['image_url'] = $localUrl;
        $data['attachment_id'] = $attachmentId;

        try {
            $poster = $this->repository->create($data);
        } catch (Throwable $exception) {
            wp_delete_attachment($attachmentId, true);

            throw $exception;
        }

        do_action(
            'dizzy_social_poster_created',
            $poster
        );

        return $poster;
    }

    private function duplicateAttachment(int $sourceId, int $postId): int
    {
        $source = get_attached_file($sourceId);
        if (! is_string($source) || ! is_readable($source) || ! wp_attachment_is_image($sourceId)) {
            return 0;
        }

        $uploads = wp_upload_dir();
        if (! empty($uploads['error']) || empty($uploads['path'])) {
            return 0;
        }
        if (! wp_mkdir_p((string) $uploads['path'])) {
            return 0;
        }

        $filename = wp_unique_filename((string) $uploads['path'], 'dizzy-poster-' . wp_generate_uuid4() . '.png');
        $destination = trailingslashit((string) $uploads['path']) . $filename;
        if (! copy($source, $destination)) {
            return 0;
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => 'image/png',
            'post_title' => get_the_title($postId) . ' poster',
            'post_status' => 'inherit',
        ], $destination, $postId, true);

        if (is_wp_error($attachmentId)) {
            wp_delete_file($destination);
            return 0;
        }

        return (int) $attachmentId;
    }

    private function importMedia(string $url, int $postId): int
    {
        if (str_starts_with($url, 'data:image/')) {
            return $this->importBase64Media($url, $postId);
        }

        if (! function_exists('media_sideload_image')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachmentId = media_sideload_image(
            $url,
            $postId,
            null,
            'id'
        );

        return is_wp_error($attachmentId) ? 0 : (int) $attachmentId;
    }

    private function importBase64Media(string $dataUrl, int $postId): int
    {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#s', $dataUrl, $matches)) {
            return 0;
        }

        $contents = base64_decode($matches[2], true);

        if ($contents === false || $contents === '') {
            return 0;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
        $upload = wp_upload_bits(
            'dizzy-poster-' . wp_generate_uuid4() . '.' . $extension,
            null,
            $contents
        );

        if (! empty($upload['error']) || empty($upload['file'])) {
            return 0;
        }

        $attachmentId = wp_insert_attachment(
            [
                'post_mime_type' => (string) ($upload['type'] ?? 'image/' . $matches[1]),
                'post_title' => get_the_title($postId) . ' poster',
                'post_status' => 'inherit',
            ],
            (string) $upload['file'],
            $postId,
            true
        );

        if (is_wp_error($attachmentId)) {
            wp_delete_file((string) $upload['file']);

            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata(
            (int) $attachmentId,
            (string) $upload['file']
        );

        if (is_array($metadata)) {
            wp_update_attachment_metadata((int) $attachmentId, $metadata);
        }

        return (int) $attachmentId;
    }
}
