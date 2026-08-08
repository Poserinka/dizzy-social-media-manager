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
    }

    public function transition(string $new,string $old,WP_Post $post): void
    {
        if($post->post_type!==Config::POST_TYPE_EVENT||$new!=='publish'||$old==='publish'||!(bool)get_option('dizzy_social_autopost_enabled',false))return;
        $delay=(int)get_option('dizzy_social_autopost_delay',0);
        if($delay>0)wp_schedule_single_event(time()+$delay*MINUTE_IN_SECONDS,'dizzy_social_publish_event',[$post->ID]);else $this->publish($post->ID);
    }

    public function publish(int $postId): void
    {
        if(get_post_type($postId)!==Config::POST_TYPE_EVENT||get_post_meta($postId,'_dizzy_social_autoposted',true))return;
        $token=(string)get_option('dizzy_social_page_token','');$page=(string)get_option('dizzy_social_page_id','');$ig=(string)get_option('dizzy_social_instagram_id','');
        if($token===''||$page===''){ $this->log('Event '.$postId.': missing account connection.');return; }
        $version=preg_replace('/[^v0-9.]/','',(string)get_option('dizzy_social_graph_version','v21.0'));$base='https://graph.facebook.com/'.$version.'/';
        $facebookEnabled=(bool)get_option('dizzy_social_autopost_facebook',true);
        $instagramEnabled=(bool)get_option('dizzy_social_autopost_instagram',true);
        $facebookType=(string)get_option('dizzy_social_facebook_type','poster');
        $instagramType=(string)get_option('dizzy_social_instagram_type','poster');
        $poster=$this->posters->findByEvent($postId);
        $needsPoster=($facebookEnabled&&$facebookType==='poster')||($instagramEnabled&&$instagramType==='poster');
        if($needsPoster&&(!$poster||$poster->imageUrl===''))$poster=$this->generatePoster($postId);
        if($needsPoster&&!$poster){$this->log('Event '.$postId.': generated poster is required, but automatic poster generation failed. Auto post stopped.');return;}
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
        if($instagramEnabled&&$ig!==''&&$instagramImage!==''){
            $creation=$this->json($base.rawurlencode($ig).'/media',['image_url'=>$instagramImage,'caption'=>$this->message($postId,'instagram'),'access_token'=>$token]);$creationId=(string)($creation['id']??'');
            $published=$creationId!==''?$this->json($base.rawurlencode($ig).'/media_publish',['creation_id'=>$creationId,'access_token'=>$token]):[];
            $igOk=!empty($published['id']);$this->log('Event '.$postId.': Instagram '.($igOk?'published.':'failed.'));$ok=$igOk&&$ok;
        }
        if($ok)update_post_meta($postId,'_dizzy_social_autoposted',current_time('mysql',true));
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
        $venues=wp_get_post_terms($postId,Config::TAX_VENUE,['fields'=>'names']);$tags=['{post_title}'=>get_the_title($postId),'{post_excerpt}'=>wp_trim_words(wp_strip_all_tags((string)get_post_field('post_content',$postId)),45,'...'),'{post_url}'=>get_permalink($postId),'{event_date}'=>is_string($start)?wp_date('d F Y - H:i',strtotime($start),wp_timezone()):'','{venue}'=>!is_wp_error($venues)&&isset($venues[0])?(string)$venues[0]:''];
        $text=strtr((string)get_option('dizzy_social_'.$platform.'_message','{post_title}\n\n{post_excerpt}\n\n{post_url}'),$tags);
        $text=str_replace(["\\r\\n","\\n","\\r"],"\n",$text);
        $limit=$platform==='instagram'?2200:63206;
        return (bool)get_option('dizzy_social_'.$platform.'_trim',true)?mb_substr($text,0,$limit):$text;
    }

    private function request(string $url,array $body,string $platform,int $postId): bool { $data=$this->json($url,$body);$ok=!empty($data['id']);$this->log('Event '.$postId.': '.$platform.' '.($ok?'published.':'failed.'));return $ok; }
    private function json(string $url,array $body): array { $r=wp_remote_post($url,['timeout'=>30,'body'=>$body]);if(is_wp_error($r))return []; $d=json_decode(wp_remote_retrieve_body($r),true);return is_array($d)?$d:[]; }
    private function log(string $message): void { if(!(bool)get_option('dizzy_social_autopost_log_enabled',true))return;$logs=(array)get_option('dizzy_social_autopost_logs',[]);$logs[]=current_time('mysql').' '.$message;update_option('dizzy_social_autopost_logs',array_slice($logs,-100),false); }
}

