<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

defined('ABSPATH') || exit;

final class AutoPostAdmin
{
    private const GROUP='dizzy-social-autopost';

    public function register(): void { add_action('admin_init',[$this,'settings']);add_action('admin_menu',[$this,'menu']); }
    public function menu(): void { add_submenu_page('dizzy-social-media',__('WP Auto Post','dizzy-social-media-manager'),__('WP Auto Post','dizzy-social-media-manager'),'manage_options','dizzy-social-autopost',[$this,'render']); }
    public function settings(): void
    {
        register_setting(self::GROUP,'dizzy_social_autopost_enabled',['type'=>'boolean','sanitize_callback'=>fn($v)=>(bool)$v]);
        register_setting(self::GROUP,'dizzy_social_autopost_delay',['type'=>'integer','sanitize_callback'=>'absint','default'=>0]);
        register_setting(self::GROUP,'dizzy_social_autopost_log_enabled',['type'=>'boolean','sanitize_callback'=>fn($v)=>(bool)$v]);
        register_setting(self::GROUP,'dizzy_social_autopost_facebook',['type'=>'boolean','sanitize_callback'=>fn($v)=>(bool)$v]);
        register_setting(self::GROUP,'dizzy_social_autopost_instagram',['type'=>'boolean','sanitize_callback'=>fn($v)=>(bool)$v]);
    }

    public function render(): void
    {
        if(!current_user_can('manage_options'))return;$connected=(string)get_option('dizzy_social_page_token','')!==''; ?>
        <div class="wrap dizzy-social-page"><h1><?php esc_html_e('Auto Post Settings','dizzy-social-media-manager'); ?></h1><form method="post" action="options.php"><?php settings_fields(self::GROUP); ?>
        <?php $this->toggle('dizzy_social_autopost_enabled','Share events automatically','When a Dizzy event is published, share it on active social accounts.',$connected); ?>
        <div class="dizzy-card"><h2><?php esc_html_e('Social channels','dizzy-social-media-manager'); ?></h2><label><input type="checkbox" name="dizzy_social_autopost_facebook" value="1" <?php checked((bool)get_option('dizzy_social_autopost_facebook',true)); ?>> Facebook</label><br><label><input type="checkbox" name="dizzy_social_autopost_instagram" value="1" <?php checked((bool)get_option('dizzy_social_autopost_instagram',true)); ?>> Instagram</label></div>
        <div class="dizzy-card"><h2><?php esc_html_e('Share post delay','dizzy-social-media-manager'); ?></h2><p><?php esc_html_e('Publish immediately or schedule the social post after the event is published.','dizzy-social-media-manager'); ?></p><select name="dizzy_social_autopost_delay"><?php foreach([0=>'Immediately',5=>'5 minutes',15=>'15 minutes',30=>'30 minutes',60=>'1 hour'] as $value=>$label): ?><option value="<?php echo esc_attr((string)$value); ?>" <?php selected((int)get_option('dizzy_social_autopost_delay',0),$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></div>
        <?php $this->toggle('dizzy_social_autopost_log_enabled','Enable auto post log','Keep the latest publishing results for troubleshooting.',true); ?>
        <?php submit_button(); ?></form>
        <div class="dizzy-card"><h2><?php esc_html_e('Recent log','dizzy-social-media-manager'); ?></h2><?php $logs=(array)get_option('dizzy_social_autopost_logs',[]);if($logs===[])echo '<p>No entries yet.</p>';else echo '<pre style="white-space:pre-wrap">'.esc_html(implode("\n",array_slice($logs,-20))).'</pre>'; ?></div><?php $this->style(); ?></div><?php
    }

    private function toggle(string $name,string $title,string $description,bool $enabled): void
    {
        echo '<div class="dizzy-card"><label style="display:flex;justify-content:space-between;gap:20px"><span><strong style="font-size:16px">'.esc_html($title).'</strong><br><span class="description">'.esc_html($description).'</span></span><input type="checkbox" name="'.esc_attr($name).'" value="1" '.checked((bool)get_option($name,false),true,false).' '.disabled($enabled,false,false).'></label>';if(!$enabled)echo '<p><mark>Create and test an account first to enable this option.</mark></p>';echo '</div>';
    }
    private function style(): void { echo '<style>.dizzy-social-page{max-width:1000px}.dizzy-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;margin:18px 0}</style>'; }
}
