<?php
/**
 * Plugin Name: Dizzy Social Media Manager
 * Plugin URI: https://github.com/Poserinka/dizzy-social-media-manager
 * Description: Poster generation and social media exports for Dizzy Events Manager.
 * Version: 1.8.2
 * Author: Poserinka Design
 * Text Domain: dizzy-social-media-manager
 * Requires PHP: 8.2
 * Update URI: https://github.com/Poserinka/dizzy-social-media-manager
 * Requires Plugins: dizzy-events-manager
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('DIZZY_SOCIAL_VERSION', '1.8.2');
define('DIZZY_SOCIAL_PATH', plugin_dir_path(__FILE__));
define('DIZZY_SOCIAL_URL', plugin_dir_url(__FILE__));

require_once DIZZY_SOCIAL_PATH . 'includes/Core/Autoloader.php';
\Dizzy\SocialMedia\Core\Autoloader::register();

(new \Dizzy\SocialMedia\Core\GitHubUpdater(
    __FILE__,
    'dizzy-social-media-manager',
    'Poserinka/dizzy-social-media-manager',
    DIZZY_SOCIAL_VERSION
))->register();

register_activation_hook(__FILE__, [\Dizzy\SocialMedia\Database\Migrations::class, 'run']);

add_action('init', static function (): void {
    \Dizzy\SocialMedia\Database\Migrations::run();
    (new \Dizzy\SocialMedia\Core\Application())->boot();
}, 20);
