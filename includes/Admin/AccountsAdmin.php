<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

defined('ABSPATH') || exit;

final class AccountsAdmin
{
    private const GROUP='dizzy-social-accounts';

    public function register(): void
    {
        add_action('admin_init',[$this,'settings']);
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_post_dizzy_social_test_account',[$this,'test']);
    }

    public function menu(): void
    {
        add_submenu_page('dizzy-social-media',__('Accounts','dizzy-social-media-manager'),__('Accounts','dizzy-social-media-manager'),'manage_options','dizzy-social-accounts',[$this,'render']);
    }

    public function settings(): void
    {
        foreach(['graph_version','app_id','app_secret','page_id','page_token','instagram_id'] as $key){
            register_setting(self::GROUP,'dizzy_social_'.$key,['type'=>'string','sanitize_callback'=>'sanitize_text_field']);
        }
    }

    public function render(): void
    {
        if(!current_user_can('manage_options'))return;
        $status=sanitize_key((string)($_GET['connection']??'')); ?>
        <div class="wrap dizzy-social-page"><h1><?php esc_html_e('Social Accounts','dizzy-social-media-manager'); ?></h1><p><?php esc_html_e('Connect the Facebook Page and Instagram Business account managed by the same Meta app.','dizzy-social-media-manager'); ?></p>
        <?php if($status!==''): ?><div class="notice <?php echo $status==='success'?'notice-success':'notice-error'; ?>"><p><?php echo esc_html($status==='success'?__('Connection successful.','dizzy-social-media-manager'):__('Connection failed. Check the IDs, token and app permissions.','dizzy-social-media-manager')); ?></p></div><?php endif; ?>
        <form method="post" action="options.php"><?php settings_fields(self::GROUP); ?><div class="dizzy-card"><h2>Meta Graph API</h2><table class="form-table">
        <?php $this->field('graph_version','Graph API version','v21.0');$this->field('app_id','Meta App ID');$this->field('app_secret','Meta App Secret','',true);$this->field('page_id','Facebook Page ID');$this->field('page_token','Page access token','',true);$this->field('instagram_id','Instagram Business Account ID'); ?>
        </table></div><?php submit_button(); ?></form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="dizzy_social_test_account"><?php wp_nonce_field('dizzy_social_test_account'); ?><button class="button button-secondary"><?php esc_html_e('Test Facebook connection','dizzy-social-media-manager'); ?></button></form><?php $this->style(); ?></div><?php
    }

    private function field(string $key,string $label,string $default='',bool $secret=false): void
    {
        $name='dizzy_social_'.$key;echo '<tr><th><label for="'.esc_attr($name).'">'.esc_html($label).'</label></th><td><input id="'.esc_attr($name).'" class="regular-text" type="'.($secret?'password':'text').'" name="'.esc_attr($name).'" value="'.esc_attr((string)get_option($name,$default)).'"></td></tr>';
    }

    public function test(): void
    {
        if(!current_user_can('manage_options'))wp_die('Unauthorized');check_admin_referer('dizzy_social_test_account');
        $version=preg_replace('/[^v0-9.]/','',(string)get_option('dizzy_social_graph_version','v21.0'));
        $page=rawurlencode((string)get_option('dizzy_social_page_id',''));$token=(string)get_option('dizzy_social_page_token','');
        $response=wp_remote_get('https://graph.facebook.com/'.$version.'/'.$page.'?fields=id,name&access_token='.rawurlencode($token),['timeout'=>15]);
        $ok=!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200;
        wp_safe_redirect(admin_url('admin.php?page=dizzy-social-accounts&connection='.($ok?'success':'error')));exit;
    }

    private function style(): void { echo '<style>.dizzy-social-page{max-width:1000px}.dizzy-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;margin:18px 0}</style>'; }
}
