<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Poster\Providers;

use Dizzy\SocialMedia\Core\Container;
use Dizzy\SocialMedia\Poster\Contracts\PosterGenerator;
use Dizzy\SocialMedia\Poster\Generators\OpenAIImageGenerator;
use Dizzy\SocialMedia\Poster\Generators\PlaceholderGenerator;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;
use Dizzy\SocialMedia\Poster\Renderers\PosterRenderer;
use Dizzy\SocialMedia\Poster\Services\PosterService;

defined('ABSPATH') || exit;

final class PosterServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            PosterRepository::class,
            static function (): PosterRepository {
                return new PosterRepository();
            }
        );

        $container->singleton(
            PosterGenerator::class,
            static function (): PosterGenerator {
                $apiKey = (string) get_option('dizzy_social_openai_api_key', '');

                if ($apiKey !== '') {
                    return new OpenAIImageGenerator($apiKey);
                }

                return new PlaceholderGenerator();
            }
        );

        $container->singleton(
            PosterRenderer::class,
            static fn (): PosterRenderer => new PosterRenderer()
        );

        $container->singleton(
            PosterService::class,
            static function () use ($container): PosterService {
                return new PosterService(
                    $container->get(PosterRepository::class),
                    $container->get(PosterGenerator::class),
                    $container->get(PosterRenderer::class)
                );
            }
        );
    }
}
