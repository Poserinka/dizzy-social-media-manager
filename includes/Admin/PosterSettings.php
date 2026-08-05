<?php

declare(strict_types=1);

namespace Dizzy\SocialMedia\Admin;

defined('ABSPATH') || exit;

final class PosterSettings
{
    private const GROUP = 'dizzy-social-media';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page('dizzy-social-media', __('Poster Settings', 'dizzy-social-media-manager'), __('Poster Settings', 'dizzy-social-media-manager'), 'manage_options', 'dizzy-poster-settings', [$this, 'renderPage']);
    }

    public function registerSettings(): void
    {
        if ((int) get_option('dizzy_social_logo_image_id', 0) <= 0) {
            $legacyLogoId = (int) get_option('dizzy_social_watermark_image_id', 0);
            if ($legacyLogoId > 0) update_option('dizzy_social_logo_image_id', $legacyLogoId);
        }
        foreach (['layer_image_id', 'logo_image_id'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        }
        foreach (['title_font', 'date_font'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeFont'], 'default' => '']);
        }
        foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 88, 'logo_x' => 70, 'logo_y' => 5, 'title_size' => 6.4, 'date_size' => 2.6, 'logo_width' => 25] as $key => $default) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => $default]);
        }
        foreach (['title_enabled', 'date_enabled', 'logo_enabled'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizeEnabled'], 'default' => 1]);
        }
        foreach (['openai_api_key', 'watermark_image_id', 'watermark_alignment', 'watermark_offset_x', 'watermark_offset_y', 'watermark_offset_unit', 'watermark_size_mode', 'watermark_custom_width', 'watermark_scale', 'watermark_opacity', 'watermark_social', 'watermark_print', 'title_font_id', 'date_font_id'] as $legacy) {
            delete_option('dizzy_social_' . $legacy);
        }
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) return;
        wp_enqueue_media();
        $fonts = $this->fontFiles();
        $layerId = (int) get_option('dizzy_social_layer_image_id', 0);
        $logoId = (int) get_option('dizzy_social_logo_image_id', 0);
        $layerUrl = $layerId > 0 ? (string) wp_get_attachment_image_url($layerId, 'full') : '';
        $logoUrl = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'full') : '';
        $values = [];
        foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 88, 'logo_x' => 70, 'logo_y' => 5, 'title_size' => 6.4, 'date_size' => 2.6, 'logo_width' => 25] as $key => $default) $values[$key] = (float) get_option('dizzy_social_' . $key, $default);
        foreach (['title', 'date', 'logo'] as $key) $values[$key . '_enabled'] = (int) get_option('dizzy_social_' . $key . '_enabled', 1);
        ?>
        <div class="wrap dizzy-poster-layout-settings">
            <h1><?php esc_html_e('Poster Settings', 'dizzy-social-media-manager'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields(self::GROUP); ?>
                <h2><?php esc_html_e('Layer and Logo', 'dizzy-social-media-manager'); ?></h2>
                <table class="form-table">
                    <?php $this->imageRow('layer', __('PNG layer', 'dizzy-social-media-manager'), $layerId, __('Select / Upload PNG', 'dizzy-social-media-manager'), __('Remove layer', 'dizzy-social-media-manager')); ?>
                    <?php $this->imageRow('logo', __('Logo', 'dizzy-social-media-manager'), $logoId, __('Select / Upload Logo', 'dizzy-social-media-manager'), __('Remove logo', 'dizzy-social-media-manager')); ?>
                </table>

                <h2><?php esc_html_e('Typography', 'dizzy-social-media-manager'); ?></h2>
                <table class="form-table">
                    <?php foreach (['title' => __('Title font', 'dizzy-social-media-manager'), 'date' => __('Date font', 'dizzy-social-media-manager')] as $key => $label) : ?>
                        <tr><th><label for="dizzy-<?php echo esc_attr($key); ?>-font"><?php echo esc_html($label); ?></label></th><td>
                            <select id="dizzy-<?php echo esc_attr($key); ?>-font" name="dizzy_social_<?php echo esc_attr($key); ?>_font">
                                <option value=""><?php esc_html_e('Default font', 'dizzy-social-media-manager'); ?></option>
                                <?php foreach ($fonts as $filename => $font) : ?><option value="<?php echo esc_attr($filename); ?>" data-url="<?php echo esc_url($font['url']); ?>" <?php selected((string) get_option('dizzy_social_' . $key . '_font', ''), $filename); ?>><?php echo esc_html($font['label']); ?></option><?php endforeach; ?>
                            </select>
                        </td></tr>
                    <?php endforeach; ?>
                </table>
                <?php if ($fonts === []) : ?><p class="notice notice-warning inline"><?php esc_html_e('No fonts were found in dizzy-events-manager/assets/fonts. Upload and commit TTF or OTF files to that folder.', 'dizzy-social-media-manager'); ?></p><?php endif; ?>

                <h2><?php esc_html_e('Drag / Drop Layout', 'dizzy-social-media-manager'); ?></h2>
                <p><?php esc_html_e('Drag elements to move them. Drag the square handle to resize. Select an element and press Delete to hide it.', 'dizzy-social-media-manager'); ?></p>
                <p class="dizzy-layout-tools"><button type="button" class="button" data-add="title"><?php esc_html_e('Add Title', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-add="date"><?php esc_html_e('Add Date', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-add="logo"><?php esc_html_e('Add Logo', 'dizzy-social-media-manager'); ?></button></p>
                <?php foreach ($values as $key => $value) : ?><input id="dizzy-<?php echo esc_attr(str_replace('_', '-', $key)); ?>" type="hidden" name="dizzy_social_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>"><?php endforeach; ?>
                <div id="dizzy-layout-stage" tabindex="0">
                    <img id="dizzy-layer-preview"<?php echo $layerUrl !== '' ? ' src="' . esc_url($layerUrl) . '"' : ''; ?> alt="">
                    <div class="dizzy-layout-item" data-item="title"><span><?php esc_html_e('EVENT TITLE', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <div class="dizzy-layout-item" data-item="date"><span><?php esc_html_e('DATE · TIME', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <div class="dizzy-layout-item dizzy-logo-item" data-item="logo"><img<?php echo $logoUrl !== '' ? ' src="' . esc_url($logoUrl) . '"' : ''; ?> alt=""><i class="dizzy-resize-handle"></i></div>
                </div>
                <p class="description"><?php esc_html_e('The preview is square; saved percentage positions and sizes are applied proportionally to every output format.', 'dizzy-social-media-manager'); ?></p>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
        #dizzy-layout-stage{position:relative;width:min(600px,100%);aspect-ratio:1;overflow:hidden;background:linear-gradient(135deg,#343840,#101114);border:2px solid #8c8f94;touch-action:none;outline:none}#dizzy-layout-stage:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}#dizzy-layer-preview{position:absolute;inset:0;width:100%;height:100%;object-fit:fill;pointer-events:none}#dizzy-layer-preview:not([src]){display:none}.dizzy-layout-item{position:absolute;color:#fff;font-weight:700;cursor:move;user-select:none;border:1px dashed transparent;padding:4px;line-height:1;white-space:nowrap;text-shadow:0 1px 3px #000;transform-origin:top left}.dizzy-layout-item.is-selected{border-color:#72aee6;background:rgba(34,113,177,.2)}.dizzy-layout-item[data-item=title]{font-size:38px}.dizzy-layout-item[data-item=date]{font-size:16px}.dizzy-logo-item{width:25%;padding:0}.dizzy-logo-item img{display:block;width:100%;height:auto}.dizzy-logo-item img:not([src]){min-height:50px;background:rgba(255,255,255,.2)}.dizzy-resize-handle{display:none;position:absolute;right:-6px;bottom:-6px;width:12px;height:12px;background:#2271b1;border:1px solid #fff;cursor:nwse-resize}.is-selected .dizzy-resize-handle{display:block}.dizzy-layout-tools{margin-bottom:10px}
        </style>
        <script>
        (()=>{const stage=document.querySelector('#dizzy-layout-stage'),layer=document.querySelector('#dizzy-layer-preview'),logo=document.querySelector('[data-item=logo] img');if(!stage)return;const cfg={title:{x:'title-x',y:'title-y',size:'title-size'},date:{x:'date-x',y:'date-y',size:'date-size'},logo:{x:'logo-x',y:'logo-y',size:'logo-width'}};let selected=null;const field=n=>document.querySelector('#dizzy-'+n),item=k=>stage.querySelector('[data-item='+k+']');const render=k=>{const c=cfg[k],el=item(k),enabled=Number(field(k+'-enabled').value);el.hidden=!enabled;el.style.left=field(c.x).value+'%';el.style.top=field(c.y).value+'%';const size=Number(field(c.size).value);if(k==='logo')el.style.width=size+'%';else el.style.fontSize=(size*6)+'px'};Object.keys(cfg).forEach(render);const choose=(el)=>{stage.querySelectorAll('.dizzy-layout-item').forEach(x=>x.classList.remove('is-selected'));selected=el?.dataset.item||null;if(el)el.classList.add('is-selected');stage.focus()};stage.querySelectorAll('.dizzy-layout-item').forEach(el=>{el.addEventListener('pointerdown',e=>{e.preventDefault();choose(el);const k=el.dataset.item,c=cfg[k],resizing=e.target.classList.contains('dizzy-resize-handle'),box=stage.getBoundingClientRect(),startX=e.clientX,startSize=Number(field(c.size).value);el.setPointerCapture(e.pointerId);const move=m=>{if(resizing){const delta=(m.clientX-startX)/box.width*100;field(c.size).value=Math.max(1,Math.min(100,startSize+delta)).toFixed(2)}else{field(c.x).value=Math.max(0,Math.min(100,(m.clientX-box.left)/box.width*100)).toFixed(2);field(c.y).value=Math.max(0,Math.min(100,(m.clientY-box.top)/box.height*100)).toFixed(2)}render(k)};el.addEventListener('pointermove',move);el.addEventListener('pointerup',()=>el.removeEventListener('pointermove',move),{once:true})})});stage.addEventListener('keydown',e=>{if((e.key==='Delete'||e.key==='Backspace')&&selected){e.preventDefault();field(selected+'-enabled').value='0';render(selected);selected=null}});document.querySelectorAll('[data-add]').forEach(b=>b.addEventListener('click',()=>{const k=b.dataset.add;field(k+'-enabled').value='1';render(k);choose(item(k))}));const media=(kind)=>{const frame=wp.media({title:kind==='layer'?'Select transparent PNG layer':'Select logo',button:{text:'Use image'},library:{type:'image'},multiple:false});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();field(kind+'-image-id').value=a.id;if(kind==='layer')layer.src=a.url;else{logo.src=a.url;field('logo-enabled').value='1';render('logo')}});frame.open()};document.querySelector('#dizzy-select-layer').addEventListener('click',()=>media('layer'));document.querySelector('#dizzy-remove-layer').addEventListener('click',()=>{field('layer-image-id').value='0';layer.removeAttribute('src')});document.querySelector('#dizzy-select-logo').addEventListener('click',()=>media('logo'));document.querySelector('#dizzy-remove-logo').addEventListener('click',()=>{field('logo-image-id').value='0';logo.removeAttribute('src');field('logo-enabled').value='0';render('logo')});const fonts={title:document.querySelector('#dizzy-title-font'),date:document.querySelector('#dizzy-date-font')};Object.entries(fonts).forEach(([k,select])=>{const apply=()=>{const url=select.selectedOptions[0]?.dataset.url||'';if(!url){item(k).style.removeProperty('font-family');return}const style=document.createElement('style');style.textContent='@font-face{font-family:Dizzy'+k+';src:url("'+url.replace(/"/g,'')+'")}';document.head.appendChild(style);item(k).style.fontFamily='Dizzy'+k};select.addEventListener('change',apply);apply()})})();
        </script>
        <?php
    }

    private function imageRow(string $key, string $label, int $id, string $select, string $remove): void
    {
        echo '<tr><th>' . esc_html($label) . '</th><td><input id="dizzy-' . esc_attr($key) . '-image-id" type="hidden" name="dizzy_social_' . esc_attr($key) . '_image_id" value="' . esc_attr((string) $id) . '"><button id="dizzy-select-' . esc_attr($key) . '" type="button" class="button">' . esc_html($select) . '</button> <button id="dizzy-remove-' . esc_attr($key) . '" type="button" class="button">' . esc_html($remove) . '</button></td></tr>';
    }

    /** @return array<string,array{label:string,path:string,url:string}> */
    private function fontFiles(): array
    {
        $basePath = defined('DIZZY_EVENTS_PATH') ? (string) DIZZY_EVENTS_PATH : trailingslashit(WP_PLUGIN_DIR) . 'dizzy-events-manager/';
        $baseUrl = plugins_url('assets/fonts/', trailingslashit($basePath) . 'dizzy-events-manager.php');
        $directory = trailingslashit($basePath) . 'assets/fonts';
        if (! is_dir($directory)) return [];
        $fonts = [];
        foreach ((array) glob($directory . '/*.{ttf,otf,TTF,OTF}', GLOB_BRACE) as $path) {
            $filename = basename((string) $path);
            $fonts[$filename] = ['label' => pathinfo($filename, PATHINFO_FILENAME), 'path' => (string) $path, 'url' => trailingslashit($baseUrl) . rawurlencode($filename)];
        }
        ksort($fonts, SORT_NATURAL | SORT_FLAG_CASE);
        return $fonts;
    }

    public function sanitizeFont(mixed $value): string
    {
        $value = sanitize_file_name((string) $value);
        return isset($this->fontFiles()[$value]) ? $value : '';
    }

    public function sanitizePercent(mixed $value): float { return max(0, min(100, (float) $value)); }
    public function sanitizeEnabled(mixed $value): int { return (int) ((bool) $value); }
}
