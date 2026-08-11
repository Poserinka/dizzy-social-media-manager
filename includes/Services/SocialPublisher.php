<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Services;

use Dizzy\SocialMedia\Core\Config;
use Dizzy\SocialMedia\Poster\Repositories\PosterRepository;
use Dizzy\SocialMedia\Poster\Services\PosterService;
use WP_Post;
use Throwable;

defined('ABSPATH') || exit;

final class SocialPublisher
{
    public function __construct(
        private PosterRepository $posters,
        private PosterService $posterService,
    ) {}

    public function register(): void
    {
        add_action('transition_post_status',[$this,'transition'],20,3);
        add_action('dizzy_social_publish_event',[$this,'publish']);
        add_action('save_post_'.Config::POST_TYPE_EVENT,[$this,'manualPublishAfterSave'],999,3);
        add_action('admin_footer-post.php',[$this,'renderManualButtons']);
        add_action('admin_footer-post-new.php',[$this,'renderManualButtons']);
        add_action('admin_notices',[$this,'manualPublishNotice']);
    }

    public function transition(string $new,string $old,WP_Post $post): void
    {
        if($post->post_type!==Config::POST_TYPE_EVENT||$new!=='publish'||$old==='publish'||!(bool)get_option('dizzy_social_autopost_enabled',false))return;
        $delay=(int)get_option('dizzy_social_autopost_delay',0);
        if($delay>0)wp_schedule_single_event(time()+$delay*MINUTE_IN_SECONDS,'dizzy_social_publish_event',[$post->ID]);else $this->publish($post->ID);
    }

    public function publish(int $postId,?string $onlyPlatform=null,bool $manual=false): bool
    {
        if(get_post_type($postId)!==Config::POST_TYPE_EVENT||(!$manual&&get_post_meta($postId,'_dizzy_social_autoposted',true)))return false;
        $token=(string)get_option('dizzy_social_page_token','');$page=(string)get_option('dizzy_social_page_id','');$ig=(string)get_option('dizzy_social_instagram_id','');
        if($token===''||$page===''){ $this->log('Event '.$postId.': missing account connection.');return false; }
        $version=preg_replace('/[^v0-9.]/','',(string)get_option('dizzy_social_graph_version','v21.0'));$base='https://graph.facebook.com/'.$version.'/';
        $facebookEnabled=$onlyPlatform!==null?$onlyPlatform==='facebook':(bool)get_option('dizzy_social_autopost_facebook',true);
        $instagramEnabled=$onlyPlatform!==null?$onlyPlatform==='instagram':(bool)get_option('dizzy_social_autopost_instagram',true);
        $facebookType=(string)get_option('dizzy_social_facebook_type','poster');
        $instagramType=(string)get_option('dizzy_social_instagram_type','poster');
        $poster=$this->posters->findByEvent($postId);
        $needsPoster=($facebookEnabled&&$facebookType==='poster')||($instagramEnabled&&$instagramType==='poster');
        if($needsPoster&&(!$poster||$poster->imageUrl===''))$poster=$this->generatePoster($postId);
        if($needsPoster&&!$poster){$this->log('Event '.$postId.': generated poster is required, but automatic poster generation failed. Publishing stopped.');return false;}
        $posterImage=$poster?->imageUrl?:'';
        $featuredImage=(string)(get_the_post_thumbnail_url($postId,'full')?:'');
        $facebookImage=$facebookType==='poster'?$posterImage:$featuredImage;
        $instagramImage=$instagramType==='poster'?$posterImage:$featuredImage;
        $ok=true;
        if($facebookEnabled){
            $body=['message'=>$this->message($postId,'facebook'),'access_token'=>$token];
            $endpoint=$base.rawurlencode($page).'/feed';if($facebookImage!==''){$endpoint=$base.rawurlencode($page).'/photos';$body=['caption'=>$body['message'],'url'=>$facebookImage,'access_token'=>$token];}
            $ok=$this->request($endpoint,$body,'Facebook',$postId)&&$ok;
        }
        if($instagramEnabled&&($ig===''||$instagramImage==='')){
            $this->log('Event '.$postId.': Instagram failed. Missing Instagram account ID or publishable image.');
            $ok=false;
        }elseif($instagramEnabled){
            $creation=$this->json($base.rawurlencode($ig).'/media',['image_url'=>$instagramImage,'caption'=>$this->message($postId,'instagram'),'access_token'=>$token]);$creationId=(string)($creation['id']??'');
            if($creationId===''){
                $this->log('Event '.$postId.': Instagram container creation failed. '.$this->metaError($creation));
                $ok=false;
            }else{
            $container=$this->waitForContainer($base,$creationId,$token);
            $statusCode=(string)($container['status_code']??'');
            if($statusCode!=='FINISHED'&&$statusCode!=='PUBLISHED'){
                $status=(string)($container['status']??'Container did not become ready.');
                $this->log('Event '.$postId.': Instagram container '.$statusCode.'. '.$status.' '.$this->metaError($container));
                $ok=false;
            }else{
            $published=$this->json($base.rawurlencode($ig).'/media_publish',['creation_id'=>$creationId,'access_token'=>$token]);
            $mediaId=(string)($published['id']??'');
            $igOk=$mediaId!=='';
            if($igOk){
                $media=$this->getJson($base.rawurlencode($mediaId).'?fields=id,permalink&access_token='.rawurlencode($token));
                $permalink=isset($media['permalink'])&&is_string($media['permalink'])?$media['permalink']:'';
                $this->log('Event '.$postId.': Instagram published. Media ID: '.$mediaId.($permalink!==''?' Permalink: '.$permalink:''));
            }else{
                $this->log('Event '.$postId.': Instagram publish failed. '.$this->metaError($published));
            }
            $ok=$igOk&&$ok;
            }
            }
        }
        if($ok&&!$manual)update_post_meta($postId,'_dizzy_social_autoposted',current_time('mysql',true));
        return $ok;
    }

    public function renderManualButtons(): void
    {
        $screen=function_exists('get_current_screen')?get_current_screen():null;
        if(!$screen||$screen->post_type!==Config::POST_TYPE_EVENT||(bool)get_option('dizzy_social_autopost_enabled',false)||!current_user_can('edit_posts'))return;
        $nonce=wp_create_nonce('dizzy_social_manual_publish');
        ?>
        <style>.dizzy-editor-header-actions .dizzy-social-manual{white-space:nowrap}.dizzy-editor-header-actions{flex-wrap:wrap}</style>
        <script>(()=>{const add=()=>{const actions=document.querySelector('.dizzy-editor-header-actions'),form=document.querySelector('#post');if(!actions||!form||actions.querySelector('.dizzy-social-manual'))return false;const save=actions.querySelector('.dizzy-editor-save');['facebook','instagram'].forEach(platform=>{const button=document.createElement('button');button.type='button';button.className='button dizzy-social-manual';button.textContent=platform==='facebook'?'Post on Facebook':'Post on Instagram';button.addEventListener('click',()=>{let field=form.querySelector('[name=dizzy_social_manual_platform]');if(!field){field=document.createElement('input');field.type='hidden';field.name='dizzy_social_manual_platform';form.appendChild(field)}field.value=platform;let nonce=form.querySelector('[name=dizzy_social_manual_nonce]');if(!nonce){nonce=document.createElement('input');nonce.type='hidden';nonce.name='dizzy_social_manual_nonce';form.appendChild(nonce)}nonce.value='<?php echo esc_js($nonce); ?>';actions.querySelectorAll('button').forEach(item=>item.disabled=true);button.textContent='Saving and publishing...';(document.querySelector('#publish')||document.querySelector('#save-post'))?.click()});actions.insertBefore(button,save)});return true};if(!add()){const observer=new MutationObserver(()=>{if(add())observer.disconnect()});observer.observe(document.body,{childList:true,subtree:true})}})();</script>
        <?php
    }

    public function manualPublishAfterSave(int $postId,WP_Post $post,bool $update): void
    {
        if((bool)get_option('dizzy_social_autopost_enabled',false)||(defined('DOING_AUTOSAVE')&&DOING_AUTOSAVE)||wp_is_post_revision($postId)!==false)return;
        $platform=isset($_POST['dizzy_social_manual_platform'])?sanitize_key(wp_unslash((string)$_POST['dizzy_social_manual_platform'])):'';
        $nonce=isset($_POST['dizzy_social_manual_nonce'])?sanitize_text_field(wp_unslash((string)$_POST['dizzy_social_manual_nonce'])):'';
        if(!in_array($platform,['facebook','instagram'],true)||!wp_verify_nonce($nonce,'dizzy_social_manual_publish')||!current_user_can('edit_post',$postId))return;
        $ok=$post->post_status==='publish'&&$this->publish($postId,$platform,true);
        set_transient('dizzy_social_manual_notice_'.get_current_user_id().'_'.$postId,$ok?'success':'error',5*MINUTE_IN_SECONDS);
    }

    public function manualPublishNotice(): void
    {
        $postId=isset($_GET['post'])?absint($_GET['post']):0;
        if($postId<=0)return;
        $key='dizzy_social_manual_notice_'.get_current_user_id().'_'.$postId;
        $status=get_transient($key);
        if(!is_string($status)||$status==='')return;
        delete_transient($key);
        $message=$status==='success'?'Social media post published successfully.':'Social media publishing failed. Check Social Media > WP Auto Post log for details.';
        echo '<div class="notice notice-'.($status==='success'?'success':'error').' is-dismissible"><p>'.esc_html($message).'</p></div>';
    }

    private function generatePoster(int $postId): ?\Dizzy\SocialMedia\Poster\Models\Poster
    {
        $backgroundId=(int)get_post_meta($postId,'_dizzy_social_poster_background_id',true);
        if($backgroundId<=0||!wp_attachment_is_image($backgroundId))$backgroundId=(int)get_post_thumbnail_id($postId);
        if($backgroundId<=0||!wp_attachment_is_image($backgroundId)){
            $this->log('Event '.$postId.': automatic poster generation failed because no featured image is available.');
            return null;
        }
        global $wpdb;
        $start=$wpdb->get_var($wpdb->prepare("SELECT start_datetime FROM {$wpdb->prefix}dizzy_event_occurrences WHERE event_id=%d AND status=%s ORDER BY start_datetime LIMIT 1",$postId,'publish'));
        $timestamp=is_string($start)?strtotime($start):false;
        try{
            $poster=$this->posterService->create([
                'event_id'=>$postId,
                'source_attachment_id'=>$backgroundId,
                'format'=>'social_portrait',
                'title'=>get_the_title($postId),
                'date'=>$timestamp!==false?wp_date('d F Y',$timestamp,wp_timezone()):'',
                'hours'=>$timestamp!==false?wp_date('H:i',$timestamp,wp_timezone()):'',
            ]);
            update_post_meta($postId,'_dizzy_social_poster_background_id',$backgroundId);
            $this->log('Event '.$postId.': social_portrait (1080x1350) poster generated automatically.');
            return $poster;
        }catch(Throwable $exception){
            $this->log('Event '.$postId.': automatic poster generation failed: '.$exception->getMessage());
            return null;
        }
    }

    private function message(int $postId,string $platform): string
    {
        global $wpdb;$start=$wpdb->get_var($wpdb->prepare("SELECT start_datetime FROM {$wpdb->prefix}dizzy_event_occurrences WHERE event_id=%d AND status=%s ORDER BY start_datetime LIMIT 1",$postId,'publish'));
        $venues=wp_get_post_terms($postId,Config::TAX_VENUE,['fields'=>'names']);$tags=['{post_title}'=>get_the_title($postId),'{post_excerpt}'=>$this->eventDescription($postId),'{post_url}'=>get_permalink($postId),'{event_date}'=>is_string($start)?wp_date('d F Y - H:i',strtotime($start),wp_timezone()):'','{venue}'=>!is_wp_error($venues)&&isset($venues[0])?(string)$venues[0]:''];
        $text=strtr((string)get_option('dizzy_social_'.$platform.'_message','{post_title}\n\n{post_excerpt}\n\n{post_url}'),$tags);
        $text=str_replace(["\\r\\n","\\n","\\r"],"\n",$text);
        $limit=$platform==='instagram'?2200:63206;
        return (bool)get_option('dizzy_social_'.$platform.'_trim',true)?mb_substr($text,0,$limit):$text;
    }

    private function eventDescription(int $postId): string
    {
        $excerpt=(string)get_post_field('post_excerpt',$postId);
        $content=$excerpt!==''?$excerpt:(string)get_post_field('post_content',$postId);
        $content=strip_shortcodes($content);
        $content=preg_replace('/<br\s*\/?>/i',"\n",$content)??$content;
        $content=preg_replace('/<\/(p|div|li|h[1-6])>/i',"\n\n",$content)??$content;
        $text=html_entity_decode(wp_strip_all_tags($content),ENT_QUOTES,get_bloginfo('charset')?:'UTF-8');
        $text=preg_replace('/[ \t]+/u',' ',$text)??$text;
        $text=preg_replace('/ *\R */u',"\n",$text)??$text;
        $text=preg_replace('/\n{3,}/u',"\n\n",$text)??$text;
        return trim($text);
    }

    private function waitForContainer(string $base,string $creationId,string $token): array
    {
        $status=[];
        for($attempt=0;$attempt<6;$attempt++){
            $status=$this->getJson($base.rawurlencode($creationId).'?fields=status_code,status&access_token='.rawurlencode($token));
            $code=(string)($status['status_code']??'');
            if(in_array($code,['FINISHED','PUBLISHED','ERROR','EXPIRED'],true))return $status;
            if($attempt<5)sleep(2);
        }
        return $status;
    }

    private function metaError(array $data): string
    {
        $error=isset($data['error'])&&is_array($data['error'])?$data['error']:[];
        if($error===[])return '';
        $parts=[];
        if(isset($error['code']))$parts[]='Meta code '.(string)$error['code'];
        if(isset($error['error_subcode']))$parts[]='subcode '.(string)$error['error_subcode'];
        if(isset($error['message'])&&is_string($error['message']))$parts[]=$error['message'];
        if(isset($error['error_user_msg'])&&is_string($error['error_user_msg']))$parts[]=$error['error_user_msg'];
        return implode(' - ',$parts);
    }

    private function request(string $url,array $body,string $platform,int $postId): bool { $data=$this->json($url,$body);$ok=!empty($data['id']);$this->log('Event '.$postId.': '.$platform.' '.($ok?'published.':'failed. '.$this->metaError($data)));return $ok; }
    private function json(string $url,array $body): array { $r=wp_remote_post($url,['timeout'=>30,'body'=>$body]);if(is_wp_error($r))return []; $d=json_decode(wp_remote_retrieve_body($r),true);return is_array($d)?$d:[]; }
    private function getJson(string $url): array { $r=wp_remote_get($url,['timeout'=>15]);if(is_wp_error($r))return []; $d=json_decode(wp_remote_retrieve_body($r),true);return is_array($d)?$d:[]; }
    private function log(string $message): void { if(!(bool)get_option('dizzy_social_autopost_log_enabled',true))return;$logs=(array)get_option('dizzy_social_autopost_logs',[]);$logs[]=current_time('mysql').' '.$message;update_option('dizzy_social_autopost_logs',array_slice($logs,-100),false); }
}

