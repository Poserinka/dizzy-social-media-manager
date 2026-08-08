<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Core;

use Dizzy\SocialMedia\Admin\SocialMediaAdmin;
use Dizzy\SocialMedia\Admin\PosterAdmin;
use Dizzy\SocialMedia\Admin\PosterSettings;
use Dizzy\SocialMedia\Admin\AccountsAdmin;
use Dizzy\SocialMedia\Admin\AutoPostAdmin;
use Dizzy\SocialMedia\Admin\TemplatesAdmin;
use Dizzy\SocialMedia\Services\SocialPublisher;
use Dizzy\SocialMedia\Poster\Providers\PosterServiceProvider;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;
use Dizzy\SocialMedia\Poster\Services\PosterService;

defined('ABSPATH') || exit;

final class Application
{
    public function boot(): void
    {
        if (! post_type_exists(Config::POST_TYPE_EVENT)) {
            add_action('admin_notices', static function (): void {
                echo '<div class="notice notice-error"><p>' . esc_html__('Dizzy Social Media Manager requires Dizzy Events Manager to be active.', 'dizzy-social-media-manager') . '</p></div>';
            });
            return;
        }

        $container = new Container();
        (new PosterServiceProvider())->register($container);

        $dashboard = new SocialMediaAdmin($container->get(PosterRepository::class));
        $poster = new PosterAdmin($container->get(PosterService::class), $container->get(PosterRepository::class));
        $settings = new PosterSettings();
        $accounts = new AccountsAdmin();
        $autopost = new AutoPostAdmin();
        $templates = new TemplatesAdmin();
        $publisher = new SocialPublisher(
            $container->get(PosterRepository::class),
            $container->get(PosterService::class),
        );

        $dashboard->register();
        $poster->register();
        $settings->register();
        $accounts->register();
        $autopost->register();
        $templates->register();
        $publisher->register();
    }
}

