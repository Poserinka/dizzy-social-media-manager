<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Database;

defined('ABSPATH') || exit;

final class Migrations
{
    private const VERSION = '1.0.0';

    public static function run(): void
    {
        if (version_compare((string) get_option('dizzy_social_db_version', '0'), self::VERSION, '>=')) return;
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'dizzy_social_posters';
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id bigint(20) unsigned NOT NULL,
            attachment_id bigint(20) unsigned NULL,
            prompt text NULL,
            image_url text NULL,
            provider varchar(64) NOT NULL DEFAULT 'ai',
            status varchar(32) NOT NULL DEFAULT 'draft',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY event_id (event_id),
            KEY attachment_id (attachment_id),
            KEY status (status)
        ) {$charset};");
        update_option('dizzy_social_db_version', self::VERSION);
    }
}
