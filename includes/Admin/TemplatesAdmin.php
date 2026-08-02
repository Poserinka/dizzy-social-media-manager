<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

defined('ABSPATH') || exit;

final class TemplatesAdmin
{
    private const GROUP='dizzy-social-templates';

    public function register(): void { add_action('admin_init',[$this,'settings']);add_action('admin_menu',[$this,'menu']); }
    public function menu(): void { add_submenu_page('dizzy-social-media',__('Templates','dizzy-social-media-manager'),__('Templates','dizzy-social-media-manager'),'manage_options','dizzy-social-templates',[$this,'render']); }
    public function settings(): void
    {
        foreach(['facebook_message','instagram_message','instagram_first_comment'] as $key)register_setting(self::GROUP,'dizzy_social_'.$key,['type'=>'string','sanitize_callback'=>'sanitize_textarea_field']);
        foreach(['facebook_trim','instagram_trim'] as $key)register_setting(self::GROUP,'dizzy_social_'.$key,['type'=>'boolean','sanitize_callback'=>fn($v)=>(bool)$v]);
        foreach(['facebook_type','instagram_type'] as $key)register_setting(self::GROUP,'dizzy_social_'.$key,['type'=>'string','sanitize_callback'=>fn($v)=>in_array($v,['poster','featured','link'],true)?$v:'poster']);
    }

    public function render(): void
    {
        if(!current_user_can('manage_options'))return; ?>
        <div class="wrap dizzy-social-page"><h1><?php esc_html_e('Social Templates','dizzy-social-media-manager'); ?></h1><p><?php esc_html_e('Available smart tags: {post_title}, {post_excerpt}, {post_url}, {event_date}, {venue}.','dizzy-social-media-manager'); ?></p><form method="post" action="options.php"><?php settings_fields(self::GROUP); ?><div class="dizzy-grid">
        <?php $this->platform('facebook','Facebook',63206);$this->platform('instagram','Instagram',2200,true); ?>
        </div><?php submit_button(); ?></form><style>.dizzy-social-page{max-width:1200px}.dizzy-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(380px,1fr));gap:20px}.dizzy-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px}.dizzy-card textarea{width:100%;min-height:150px}</style></div><?php
    }

    private function platform(string $key,string $label,int $limit,bool $comment=false): void
    {
        $message='dizzy_social_'.$key.'_message';$type='dizzy_social_'.$key.'_type';$trim='dizzy_social_'.$key.'_trim';$default="{post_title}\n\n{post_excerpt}\n\n{event_date}\n{venue}\n\n{post_url}";
        echo '<section class="dizzy-card"><h2>'.esc_html($label.' Template Settings').'</h3><h3>Custom Message</h3><textarea name="'.esc_attr($message).'" maxlength="'.$limit.'">'.esc_textarea((string)get_option($message,$default)).'</textarea><p class="description">Maximum '.$limit.' characters.</p><h3>Posting type</h3><select name="'.esc_attr($type).'">';foreach(['poster'=>'Generated poster','featured'=>'Featured image','link'=>'Link card'] as $v=>$l)echo '<option value="'.$v.'" '.selected((string)get_option($type,'poster'),$v,false).'>'.$l.'</option>';echo '</select>';
        if($comment)echo '<h3>First comment</h3><textarea name="dizzy_social_instagram_first_comment" maxlength="2200">'.esc_textarea((string)get_option('dizzy_social_instagram_first_comment','')).'</textarea>';
        echo '<h3>Trim Message</h3><label><input type="checkbox" name="'.esc_attr($trim).'" value="1" '.checked((bool)get_option($trim,true),true,false).'> Trim content to the platform limit</label><h3>Preview</h3><div style="border:1px solid #dcdcde;border-radius:10px;padding:16px;background:#f6f7f7"><strong>'.esc_html(get_bloginfo('name')).'</strong><p>'.nl2br(esc_html((string)get_option($message,$default))).'</p></div></section>';
    }
}
