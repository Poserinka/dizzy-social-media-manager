<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Poster\Contracts;

defined('ABSPATH') || exit;

interface PosterGenerator
{
    /** @param array{size?:string} $options */
    public function generate(string $prompt, array $options = []): string;
}
