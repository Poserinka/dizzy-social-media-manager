<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

use Dizzy\SocialMedia\Core\Config;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;

defined('ABSPATH') || exit;

final class SocialMediaAdmin
{
    public function __construct(private PosterRepository $posters) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Social Media Manager', 'dizzy-social-media-manager'),
            __('Social Media', 'dizzy-social-media-manager'),
            'edit_posts',
            'dizzy-social-media',
            [$this, 'render'],
            'dashicons-share',
            27
        );
        add_submenu_page(
            'dizzy-social-media',
            __('Poster Generator', 'dizzy-social-media-manager'),
            __('Poster Generator', 'dizzy-social-media-manager'),
            'edit_posts',
            'dizzy-social-media',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (! current_user_can('edit_posts')) return;
        $events = get_posts([
            'post_type' => Config::POST_TYPE_EVENT,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        echo '<div class="wrap"><h1>' . esc_html__('Social Media & Poster Generator', 'dizzy-social-media-manager') . '</h1>';
        echo '<p>' . esc_html__('Choose an event to create posters and social media exports.', 'dizzy-social-media-manager') . '</p>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Event', 'dizzy-social-media-manager') . '</th><th>' . esc_html__('Status', 'dizzy-social-media-manager') . '</th><th>' . esc_html__('Latest poster', 'dizzy-social-media-manager') . '</th><th>' . esc_html__('Action', 'dizzy-social-media-manager') . '</th></tr></thead><tbody>';
        foreach ($events as $event) {
            $poster = $this->posters->findByEvent((int) $event->ID);
            echo '<tr><td><strong>' . esc_html($event->post_title) . '</strong></td><td>' . esc_html($event->post_status) . '</td><td>';
            if ($poster && $poster->imageUrl !== '') echo '<img src="' . esc_url($poster->imageUrl) . '" width="80" height="80" style="object-fit:cover" alt="">';
            else echo esc_html__('Not generated', 'dizzy-social-media-manager');
            echo '</td><td><a class="button button-primary" href="' . esc_url(get_edit_post_link($event->ID, '')) . '#dizzy_event_poster_generator">' . esc_html__('Open generator', 'dizzy-social-media-manager') . '</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}
