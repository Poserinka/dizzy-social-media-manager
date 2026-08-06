<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

use Dizzy\SocialMedia\Core\Config;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;
use Dizzy\SocialMedia\Poster\Services\PosterService;
use Dizzy\SocialMedia\Poster\Support\PosterFormats;
use Throwable;
use WP_Post;

defined('ABSPATH') || exit;

final class PosterAdmin
{
    /** @var array{post_id:int,background_id:int,format:string}|null */
    private ?array $pendingGeneration = null;

    public function __construct(
        private readonly PosterService $service,
        private readonly PosterRepository $repository,
    ) {
    }

    public function register(): void
    {
        add_action(
            'add_meta_boxes_' . Config::POST_TYPE_EVENT,
            [$this, 'addMetaBox']
        );

        add_action(
            'admin_post_dizzy_social_generate_poster',
            [$this, 'generate']
        );

        add_action(
            'admin_post_dizzy_social_export_poster',
            [$this, 'export']
        );

        add_action('save_post_' . Config::POST_TYPE_EVENT, [$this, 'queueGenerationAfterSave'], 30, 3);
        add_filter('redirect_post_location', [$this, 'addGenerationToRedirect'], 20, 2);
    }

    public function addMetaBox(): void
    {
        add_meta_box(
            'dizzy_event_poster_generator',
            esc_html__('Poster Generator', 'dizzy-social-media-manager'),
            [$this, 'render'],
            Config::POST_TYPE_EVENT,
            'side'
        );
    }

    public function render(WP_Post $post): void
    {
        wp_enqueue_media();

        $poster = $this->repository->findByEvent($post->ID);
        $status = isset($_GET['dizzy_social_poster_status']) && is_string($_GET['dizzy_social_poster_status'])
            ? sanitize_key(wp_unslash($_GET['dizzy_social_poster_status']))
            : '';
        $autoGenerate = isset($_GET['dizzy_social_generate_after_save']) && $_GET['dizzy_social_generate_after_save'] === '1';
        $pendingBackgroundId = isset($_GET['dizzy_social_background_id']) ? absint($_GET['dizzy_social_background_id']) : 0;
        $pendingFormat = isset($_GET['dizzy_social_format']) && is_string($_GET['dizzy_social_format'])
            ? PosterFormats::sanitize(sanitize_key(wp_unslash($_GET['dizzy_social_format'])))
            : 'social_square';

        wp_nonce_field(
            'dizzy_social_generate_poster_' . $post->ID,
            'dizzy_poster_nonce'
        );

        echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $post->ID) . '">';

        $backgroundId = (int) get_post_meta($post->ID, '_dizzy_social_poster_background_id', true);
        if ($backgroundId <= 0) {
            $backgroundId = (int) get_post_thumbnail_id($post->ID);
        }
        if ($autoGenerate && $pendingBackgroundId > 0) {
            $backgroundId = $pendingBackgroundId;
        }
        $backgroundUrl = $backgroundId > 0 ? (string) wp_get_attachment_image_url($backgroundId, 'medium') : '';

        echo '<p>' . esc_html__('Create a poster from the event image. No AI or API key is required.', 'dizzy-social-media-manager') . '</p>';
        echo '<p><label><strong>' . esc_html__('Background image', 'dizzy-social-media-manager') . '</strong></label></p>';
        echo '<input type="hidden" id="dizzy_poster_background_id" name="background_id" value="' . esc_attr((string) $backgroundId) . '">';
        echo '<div id="dizzy_poster_background_preview" style="margin-bottom:8px">';
        if ($backgroundUrl !== '') {
            echo '<img src="' . esc_url($backgroundUrl) . '" alt="" style="display:block;width:100%;max-width:300px;height:auto">';
        }
        echo '</div>';
        echo '<p><button type="button" id="dizzy_select_poster_background" class="button">' . esc_html__('Select image', 'dizzy-social-media-manager') . '</button> ';
        echo '<button type="button" id="dizzy_use_featured_background" class="button">' . esc_html__('Use featured image', 'dizzy-social-media-manager') . '</button></p>';

        echo '<p><label for="dizzy_poster_format"><strong>' . esc_html__('Output format', 'dizzy-social-media-manager') . '</strong></label><br>';
        echo '<select id="dizzy_poster_format" name="format" style="width:100%">';
        foreach (PosterFormats::all() as $key => $format) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($pendingFormat, $key, false) . '>' . esc_html($format['label']) . '</option>';
        }
        echo '</select></p>';

        if ($status === 'success') {
            echo '<div class="notice notice-success inline"><p>' . esc_html__('Poster generated successfully.', 'dizzy-social-media-manager') . '</p></div>';
        } elseif ($status === 'error') {
            $error = get_transient('dizzy_social_poster_error_' . get_current_user_id() . '_' . $post->ID);
            delete_transient('dizzy_social_poster_error_' . get_current_user_id() . '_' . $post->ID);
            $message = is_string($error) && $error !== ''
                ? $error
                : __('Poster generation failed. Check the selected image and server image support, then try again.', 'dizzy-social-media-manager');
            echo '<div class="notice notice-error inline"><p>' . esc_html($message) . '</p></div>';
        }

        if ($poster && $poster->imageUrl !== '') {
            echo '<img src="' . esc_url($poster->imageUrl) . '" style="display:block;width:100%;max-width:300px;height:auto;" alt="">';
            echo '<p><a class="button button-secondary" href="' . esc_url($poster->imageUrl) . '" download>' . esc_html__('Download latest poster', 'dizzy-social-media-manager') . '</a></p>';

            $storedFormat = $poster->attachmentId ? (string) get_post_meta($poster->attachmentId, '_dizzy_poster_format', true) : '';
            $formatKey = $this->socialFormatKey($storedFormat);

            if (str_starts_with($formatKey, 'social_')) {
                foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook'] as $platform => $platformLabel) {
                    $exportUrl = wp_nonce_url(
                        admin_url('admin-post.php?action=dizzy_social_export_poster&post_id=' . $post->ID . '&platform=' . $platform),
                        'dizzy_social_export_poster_' . $post->ID
                    );
                    echo '<p><a class="button button-primary" href="' . esc_url($exportUrl) . '">' . esc_html(sprintf(__('Export for %s', 'dizzy-social-media-manager'), $platformLabel)) . '</a></p>';
                }
            }
        }

        echo '<button type="button" id="dizzy_generate_poster" class="button button-primary">' . esc_html__('Generate Poster', 'dizzy-social-media-manager') . '</button>';
        $featuredId = (int) get_post_thumbnail_id($post->ID);
        $featuredUrl = $featuredId > 0 ? (string) wp_get_attachment_image_url($featuredId, 'medium') : '';
        echo '<script>(()=>{const select=document.getElementById("dizzy_select_poster_background"),featured=document.getElementById("dizzy_use_featured_background"),generate=document.getElementById("dizzy_generate_poster"),input=document.getElementById("dizzy_poster_background_id"),preview=document.getElementById("dizzy_poster_background_preview");if(!select||!generate||!input||!preview)return;const show=(id,url)=>{input.value=id;preview.innerHTML=url?"<img src=\""+url.replace(/\"/g,"&quot;")+"\" alt=\"\" style=\"display:block;width:100%;max-width:300px;height:auto\">":""};select.addEventListener("click",()=>{const frame=wp.media({title:"' . esc_js(__('Select poster background', 'dizzy-social-media-manager')) . '",button:{text:"' . esc_js(__('Use this image', 'dizzy-social-media-manager')) . '"},library:{type:"image"},multiple:false});frame.on("select",()=>{const image=frame.state().get("selection").first().toJSON();show(image.id,image.sizes&&image.sizes.medium?image.sizes.medium.url:image.url)});frame.open()});featured?.addEventListener("click",()=>show(' . $featuredId . ',"' . esc_js($featuredUrl) . '"));generate.addEventListener("click",()=>{generate.disabled=true;generate.textContent="' . esc_js(__('Generating...', 'dizzy-social-media-manager')) . '";const form=document.createElement("form");form.method="post";form.action="' . esc_js(admin_url('admin-post.php')) . '";const values={action:"dizzy_social_generate_poster",post_id:"' . (int) $post->ID . '",dizzy_poster_nonce:document.querySelector("[name=dizzy_poster_nonce]")?.value||"",background_id:input.value,format:document.getElementById("dizzy_poster_format")?.value||"social_square"};Object.entries(values).forEach(([name,value])=>{const field=document.createElement("input");field.type="hidden";field.name=name;field.value=value;form.appendChild(field)});document.body.appendChild(form);form.submit()})})();</script>';
        echo '<script>(()=>{const generate=document.getElementById("dizzy_generate_poster");if(!generate)return;const auto=' . ($autoGenerate ? 'true' : 'false') . ';if(auto){window.setTimeout(()=>generate.click(),250);return}generate.addEventListener("click",event=>{const postForm=document.getElementById("post"),save=document.getElementById("publish")||document.getElementById("save-post");if(!postForm||!save)return;event.preventDefault();event.stopImmediatePropagation();generate.disabled=true;generate.textContent="' . esc_js(__('Saving event...', 'dizzy-social-media-manager')) . '";const background=document.getElementById("dizzy_poster_background_id"),format=document.getElementById("dizzy_poster_format"),values={dizzy_social_generate_after_save:"1",dizzy_social_pending_background_id:background?.value||"0",dizzy_social_pending_format:format?.value||"social_square"};Object.entries(values).forEach(([name,value])=>{let field=postForm.querySelector("[name="+name+"]");if(!field){field=document.createElement("input");field.type="hidden";field.name=name;postForm.appendChild(field)}field.value=value});save.click()},true)})();</script>';
    }

    public function queueGenerationAfterSave(int $postId, WP_Post $post, bool $update): void
    {
        $nonce = isset($_POST['dizzy_poster_nonce']) && is_string($_POST['dizzy_poster_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['dizzy_poster_nonce']))
            : '';

        if (
            ! isset($_POST['dizzy_social_generate_after_save'])
            || $_POST['dizzy_social_generate_after_save'] !== '1'
            || $nonce === ''
            || ! wp_verify_nonce($nonce, 'dizzy_social_generate_poster_' . $postId)
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || ! current_user_can('edit_post', $postId)
        ) {
            return;
        }

        $this->pendingGeneration = [
            'post_id' => $postId,
            'background_id' => isset($_POST['dizzy_social_pending_background_id']) ? absint($_POST['dizzy_social_pending_background_id']) : 0,
            'format' => PosterFormats::sanitize(
                isset($_POST['dizzy_social_pending_format'])
                    ? sanitize_key(wp_unslash((string) $_POST['dizzy_social_pending_format']))
                    : 'social_square'
            ),
        ];
    }

    public function addGenerationToRedirect(string $location, int $postId): string
    {
        if ($this->pendingGeneration === null || $this->pendingGeneration['post_id'] !== $postId) {
            return $location;
        }

        return add_query_arg([
            'dizzy_social_generate_after_save' => '1',
            'dizzy_social_background_id' => $this->pendingGeneration['background_id'],
            'dizzy_social_format' => $this->pendingGeneration['format'],
        ], $location);
    }

    public function generate(): void
    {
        $postId = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;

        if (
            ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || ! $postId
            || ! isset($_POST['dizzy_poster_nonce'])
            || ! is_string($_POST['dizzy_poster_nonce'])
        ) {
            wp_safe_redirect(admin_url());
            exit;
        }

        if (! current_user_can('edit_post', $postId)) {
            wp_die(esc_html__('Permission denied.', 'dizzy-social-media-manager'));
        }

        check_admin_referer(
            'dizzy_social_generate_poster_' . $postId,
            'dizzy_poster_nonce'
        );

        $redirectUrl = get_edit_post_link($postId, '')
            ?: admin_url('post.php?post=' . $postId . '&action=edit');

        try {
            $formatKey = PosterFormats::sanitize(isset($_POST['format']) && is_string($_POST['format']) ? sanitize_key(wp_unslash($_POST['format'])) : 'social_square');
            $backgroundId = isset($_POST['background_id']) ? absint($_POST['background_id']) : 0;
            if ($backgroundId <= 0) {
                $backgroundId = (int) get_post_thumbnail_id($postId);
            }
            if ($backgroundId <= 0 || ! wp_attachment_is_image($backgroundId)) {
                throw new \RuntimeException(__('Select a background image or set an Event Featured Image first.', 'dizzy-social-media-manager'));
            }
            $details = $this->eventDetails($postId);

            $this->service->create([
                'event_id' => $postId,
                'source_attachment_id' => $backgroundId,
                'format' => $formatKey,
                'title' => get_the_title($postId),
                'date' => $details['date'],
                'hours' => $details['hours'],
            ]);
            update_post_meta($postId, '_dizzy_social_poster_background_id', $backgroundId);
        } catch (Throwable $exception) {
            set_transient(
                'dizzy_social_poster_error_' . get_current_user_id() . '_' . $postId,
                $exception->getMessage(),
                5 * MINUTE_IN_SECONDS
            );
            wp_safe_redirect(add_query_arg('dizzy_social_poster_status', 'error', $redirectUrl));
            exit;
        }

        wp_safe_redirect(add_query_arg('dizzy_social_poster_status', 'success', $redirectUrl));
        exit;
    }

    public function export(): void
    {
        $postId = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $platform = isset($_GET['platform']) && is_string($_GET['platform'])
            ? sanitize_key(wp_unslash($_GET['platform']))
            : '';

        if ($postId <= 0 || ! in_array($platform, ['instagram', 'facebook'], true)) {
            wp_die(esc_html__('Invalid poster export request.', 'dizzy-social-media-manager'));
        }

        check_admin_referer('dizzy_social_export_poster_' . $postId);

        if (! current_user_can('edit_post', $postId)) {
            wp_die(esc_html__('Permission denied.', 'dizzy-social-media-manager'));
        }

        $poster = $this->repository->findByEvent($postId);
        $attachmentId = $poster?->attachmentId ?? 0;
        $storedFormat = $attachmentId > 0 ? (string) get_post_meta($attachmentId, '_dizzy_poster_format', true) : '';
        $formatKey = $this->socialFormatKey($storedFormat);
        $path = $attachmentId > 0 ? get_attached_file($attachmentId) : '';

        if (! str_starts_with($formatKey, 'social_') || ! is_string($path) || ! is_readable($path)) {
            wp_die(esc_html__('No matching social poster is available for export.', 'dizzy-social-media-manager'));
        }

        $slug = sanitize_title(get_the_title($postId)) ?: 'event';
        $baseName = 'dizzy-' . $slug . '-' . $formatKey;
        $caption = $this->socialCaption($postId, $platform);

        if (class_exists(\ZipArchive::class)) {
            if (! function_exists('wp_tempnam')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            $temporary = wp_tempnam($baseName . '.zip');
            $zip = is_string($temporary) ? new \ZipArchive() : null;

            if ($zip && $zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
                $zip->addFile($path, $baseName . '.' . $extension);
                $zip->addFromString($baseName . '-caption.txt', $caption);
                $zip->close();
                $this->sendDownload($temporary, $baseName . '.zip', 'application/zip', true);
            }

            if (is_string($temporary) && is_file($temporary)) {
                wp_delete_file($temporary);
            }
        }

        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) ?: 'png';
        $this->sendDownload($path, $baseName . '.' . $extension, 'image/png');
    }

    private function socialCaption(int $postId, string $platform): string
    {
        $title = get_the_title($postId);
        $description = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $postId)), 45, '...');
        $url = get_permalink($postId);
        $tags = $platform === 'instagram'
            ? '#JazzcafeDizzy #Rotterdam #LiveMusic #Jazz'
            : '#JazzcafeDizzy #Rotterdam #LiveMusic';

        return trim($title . "\n\n" . $description . "\n\n" . $url . "\n\n" . $tags) . "\n";
    }

    private function socialFormatKey(string $key): string
    {
        $compatible = [
            'social_square',
            'social_portrait',
            'social_story',
            'instagram_square',
            'instagram_portrait',
            'instagram_story',
            'facebook_square',
            'facebook_portrait',
            'facebook_story',
        ];

        return in_array($key, $compatible, true) ? PosterFormats::sanitize($key) : '';
    }

    private function sendDownload(string $path, string $name, string $mime, bool $deleteAfter = false): never
    {
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($name) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);

        if ($deleteAfter) {
            wp_delete_file($path);
        }

        exit;
    }

    /** @return array{date:string,hours:string} */
    private function eventDetails(int $postId): array
    {
        global $wpdb;

        $start = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT start_datetime FROM {$wpdb->prefix}dizzy_event_occurrences WHERE event_id = %d AND status = %s ORDER BY start_datetime ASC LIMIT 1",
                $postId,
                'publish'
            )
        );
        $date = is_string($start) && $start !== ''
            ? wp_date('d F Y', strtotime($start), wp_timezone())
            : '';
        $hours = is_string($start) && $start !== ''
            ? wp_date('H:i', strtotime($start), wp_timezone())
            : '';
        return ['date' => $date, 'hours' => $hours];
    }
}
