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
        add_filter('upload_mimes', [$this, 'allowFontUploads']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'dizzy-social-media',
            esc_html__('Poster Settings', 'dizzy-social-media-manager'),
            esc_html__('Poster Settings', 'dizzy-social-media-manager'),
            'manage_options',
            'dizzy-poster-settings',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        delete_option('dizzy_social_openai_api_key');
        register_setting(self::GROUP, 'dizzy_social_layer_image_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_social_title_font_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_social_date_font_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 88] as $key => $default) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizePosition'], 'default' => $default]);
        }
        register_setting(self::GROUP, 'dizzy_social_watermark_image_id', ['type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_social_watermark_alignment', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeAlignment'], 'default' => 'top_center']);
        register_setting(self::GROUP, 'dizzy_social_watermark_offset_x', ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizeOffset'], 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_social_watermark_offset_y', ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizeOffset'], 'default' => 0]);
        register_setting(self::GROUP, 'dizzy_social_watermark_offset_unit', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeUnit'], 'default' => 'percentages']);
        register_setting(self::GROUP, 'dizzy_social_watermark_size_mode', ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeSizeMode'], 'default' => 'scaled']);
        register_setting(self::GROUP, 'dizzy_social_watermark_custom_width', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizeWidth'], 'default' => 400]);
        register_setting(self::GROUP, 'dizzy_social_watermark_scale', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => 35]);
        register_setting(self::GROUP, 'dizzy_social_watermark_opacity', ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => 85]);
        register_setting(self::GROUP, 'dizzy_social_watermark_social', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeCheckbox'], 'default' => true]);
        register_setting(self::GROUP, 'dizzy_social_watermark_print', ['type' => 'boolean', 'sanitize_callback' => [$this, 'sanitizeCheckbox'], 'default' => false]);
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        wp_enqueue_media();

        $imageId = (int) get_option('dizzy_social_watermark_image_id', 0);
        $imageUrl = $imageId > 0 ? (string) wp_get_attachment_image_url($imageId, 'full') : '';
        $alignment = (string) get_option('dizzy_social_watermark_alignment', 'top_center');
        $unit = (string) get_option('dizzy_social_watermark_offset_unit', 'percentages');
        $sizeMode = (string) get_option('dizzy_social_watermark_size_mode', 'scaled');
        $positions = ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'];
        $layerId = (int) get_option('dizzy_social_layer_image_id', 0);
        $layerUrl = $layerId > 0 ? (string) wp_get_attachment_image_url($layerId, 'full') : '';
        $titleFontId = (int) get_option('dizzy_social_title_font_id', 0);
        $dateFontId = (int) get_option('dizzy_social_date_font_id', 0);
        $titleFontUrl = $titleFontId > 0 ? (string) wp_get_attachment_url($titleFontId) : '';
        $dateFontUrl = $dateFontId > 0 ? (string) wp_get_attachment_url($dateFontId) : '';
        ?>
        <div class="wrap dizzy-watermark-settings">
            <h1><?php esc_html_e('Poster Settings', 'dizzy-social-media-manager'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('options.php')); ?>">
                <?php settings_fields(self::GROUP); ?>
                <h2><?php esc_html_e('Poster Layer and Text Layout', 'dizzy-social-media-manager'); ?></h2>
                <p><?php esc_html_e('Upload a transparent PNG layer, select fonts, then drag the Title and Date to their desired positions. Positions scale proportionally for every poster size.', 'dizzy-social-media-manager'); ?></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('PNG layer', 'dizzy-social-media-manager'); ?></th><td>
                        <input id="dizzy-layer-id" type="hidden" name="dizzy_social_layer_image_id" value="<?php echo esc_attr((string) $layerId); ?>">
                        <button id="dizzy-select-layer" type="button" class="button"><?php esc_html_e('Select / Upload PNG', 'dizzy-social-media-manager'); ?></button>
                        <button id="dizzy-remove-layer" type="button" class="button"><?php esc_html_e('Remove layer', 'dizzy-social-media-manager'); ?></button>
                    </td></tr>
                    <tr><th><?php esc_html_e('Title font', 'dizzy-social-media-manager'); ?></th><td>
                        <input id="dizzy-title-font-id" type="hidden" name="dizzy_social_title_font_id" value="<?php echo esc_attr((string) $titleFontId); ?>">
                        <button type="button" class="button dizzy-select-font" data-target="title"><?php esc_html_e('Select / Upload TTF or OTF', 'dizzy-social-media-manager'); ?></button>
                        <button type="button" class="button dizzy-remove-font" data-target="title"><?php esc_html_e('Use default font', 'dizzy-social-media-manager'); ?></button>
                        <span id="dizzy-title-font-name"><?php echo esc_html($titleFontId > 0 ? get_the_title($titleFontId) : __('Default', 'dizzy-social-media-manager')); ?></span>
                    </td></tr>
                    <tr><th><?php esc_html_e('Date font', 'dizzy-social-media-manager'); ?></th><td>
                        <input id="dizzy-date-font-id" type="hidden" name="dizzy_social_date_font_id" value="<?php echo esc_attr((string) $dateFontId); ?>">
                        <button type="button" class="button dizzy-select-font" data-target="date"><?php esc_html_e('Select / Upload TTF or OTF', 'dizzy-social-media-manager'); ?></button>
                        <button type="button" class="button dizzy-remove-font" data-target="date"><?php esc_html_e('Use default font', 'dizzy-social-media-manager'); ?></button>
                        <span id="dizzy-date-font-name"><?php echo esc_html($dateFontId > 0 ? get_the_title($dateFontId) : __('Default', 'dizzy-social-media-manager')); ?></span>
                    </td></tr>
                    <tr><th><?php esc_html_e('Drag / drop layout', 'dizzy-social-media-manager'); ?></th><td>
                        <?php foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 88] as $key => $default) : ?>
                            <input id="dizzy-<?php echo esc_attr(str_replace('_', '-', $key)); ?>" type="hidden" name="dizzy_social_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) get_option('dizzy_social_' . $key, $default)); ?>">
                        <?php endforeach; ?>
                        <div id="dizzy-layout-stage">
                            <img id="dizzy-layer-preview"<?php echo $layerUrl !== '' ? ' src="' . esc_url($layerUrl) . '"' : ''; ?> alt="">
                            <div id="dizzy-drag-title" class="dizzy-drag-text"><?php esc_html_e('EVENT TITLE', 'dizzy-social-media-manager'); ?></div>
                            <div id="dizzy-drag-date" class="dizzy-drag-text"><?php esc_html_e('DATE · TIME', 'dizzy-social-media-manager'); ?></div>
                        </div>
                        <p class="description"><?php esc_html_e('Drag each text block. Save Changes when the layout is ready.', 'dizzy-social-media-manager'); ?></p>
                    </td></tr>
                </table>
                <h2><?php esc_html_e('Logo / Watermark', 'dizzy-social-media-manager'); ?></h2>
                <p><?php esc_html_e('These settings are applied proportionally to all social poster sizes.', 'dizzy-social-media-manager'); ?></p>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Apply watermark', 'dizzy-social-media-manager'); ?></th><td>
                        <label><input type="checkbox" name="dizzy_social_watermark_social" value="1" <?php checked((bool) get_option('dizzy_social_watermark_social', true)); ?>> <?php esc_html_e('Social media posters', 'dizzy-social-media-manager'); ?></label><br>
                        <label><input type="checkbox" name="dizzy_social_watermark_print" value="1" <?php checked((bool) get_option('dizzy_social_watermark_print', false)); ?>> <?php esc_html_e('A4 print posters', 'dizzy-social-media-manager'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark image', 'dizzy-social-media-manager'); ?></th><td>
                        <input id="dizzy-watermark-id" type="hidden" name="dizzy_social_watermark_image_id" value="<?php echo esc_attr((string) $imageId); ?>">
                        <button id="dizzy-select-watermark" type="button" class="button"><?php esc_html_e('Select image', 'dizzy-social-media-manager'); ?></button>
                        <button id="dizzy-remove-watermark" type="button" class="button"><?php esc_html_e('Remove image', 'dizzy-social-media-manager'); ?></button>
                        <p class="description"><?php esc_html_e('Use a transparent PNG or WebP. The WordPress site logo is used when no image is selected.', 'dizzy-social-media-manager'); ?></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark alignment', 'dizzy-social-media-manager'); ?></th><td><div id="dizzy-alignment-grid" class="dizzy-alignment-grid">
                        <?php foreach ($positions as $position) : ?><label class="<?php echo $alignment === $position ? 'is-selected' : ''; ?>"><input type="radio" name="dizzy_social_watermark_alignment" value="<?php echo esc_attr($position); ?>" <?php checked($alignment, $position); ?>><span></span></label><?php endforeach; ?>
                    </div></td></tr>
                    <tr><th><?php esc_html_e('Watermark offset', 'dizzy-social-media-manager'); ?></th><td>
                        <label>x: <input class="small-text" type="number" step="0.1" name="dizzy_social_watermark_offset_x" value="<?php echo esc_attr((string) get_option('dizzy_social_watermark_offset_x', 0)); ?>"></label>
                        <label>y: <input class="small-text" type="number" step="0.1" name="dizzy_social_watermark_offset_y" value="<?php echo esc_attr((string) get_option('dizzy_social_watermark_offset_y', 0)); ?>"></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Offset unit', 'dizzy-social-media-manager'); ?></th><td>
                        <label><input type="radio" name="dizzy_social_watermark_offset_unit" value="pixels" <?php checked($unit, 'pixels'); ?>> <?php esc_html_e('pixels', 'dizzy-social-media-manager'); ?></label>
                        <label><input type="radio" name="dizzy_social_watermark_offset_unit" value="percentages" <?php checked($unit, 'percentages'); ?>> <?php esc_html_e('percentages', 'dizzy-social-media-manager'); ?></label>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark preview', 'dizzy-social-media-manager'); ?></th><td><div id="dizzy-watermark-preview"><img<?php echo $imageUrl !== '' ? ' src="' . esc_url($imageUrl) . '"' : ''; ?> alt=""></div></td></tr>
                    <tr><th><?php esc_html_e('Watermark size', 'dizzy-social-media-manager'); ?></th><td>
                        <?php foreach (['original' => __('Original', 'dizzy-social-media-manager'), 'custom' => __('Custom', 'dizzy-social-media-manager'), 'scaled' => __('Scaled', 'dizzy-social-media-manager')] as $value => $label) : ?><label><input type="radio" name="dizzy_social_watermark_size_mode" value="<?php echo esc_attr($value); ?>" <?php checked($sizeMode, $value); ?>> <?php echo esc_html($label); ?></label> <?php endforeach; ?>
                        <p><label><?php esc_html_e('Custom width', 'dizzy-social-media-manager'); ?> <input class="small-text" type="number" min="1" max="4000" name="dizzy_social_watermark_custom_width" value="<?php echo esc_attr((string) get_option('dizzy_social_watermark_custom_width', 400)); ?>"> px</label></p>
                        <p><label><?php esc_html_e('Scaled width', 'dizzy-social-media-manager'); ?> <input type="range" min="1" max="100" name="dizzy_social_watermark_scale" value="<?php echo esc_attr((string) get_option('dizzy_social_watermark_scale', 35)); ?>"> <output></output>%</label></p>
                    </td></tr>
                    <tr><th><?php esc_html_e('Watermark opacity', 'dizzy-social-media-manager'); ?></th><td><label><input type="range" min="0" max="100" name="dizzy_social_watermark_opacity" value="<?php echo esc_attr((string) get_option('dizzy_social_watermark_opacity', 85)); ?>"> <output></output>%</label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
            .dizzy-alignment-grid{display:grid;grid-template-columns:repeat(3,40px);width:120px;border:1px solid #ccd0d4}.dizzy-alignment-grid label{height:40px;border:1px solid #e2e4e7;display:grid;place-items:center;cursor:pointer}.dizzy-alignment-grid input{display:none}.dizzy-alignment-grid span{width:9px;height:9px;border-radius:50%;background:#c8c9cc}.dizzy-alignment-grid .is-selected{background:#3858e9}.dizzy-alignment-grid .is-selected span{background:#fff}#dizzy-watermark-preview{position:relative;width:min(600px,100%);aspect-ratio:3/2;overflow:hidden;border:1px solid #ccd0d4;background-color:#fff;background-image:linear-gradient(45deg,#e7e7e7 25%,transparent 25%),linear-gradient(-45deg,#e7e7e7 25%,transparent 25%),linear-gradient(45deg,transparent 75%,#e7e7e7 75%),linear-gradient(-45deg,transparent 75%,#e7e7e7 75%);background-size:24px 24px;background-position:0 0,0 12px,12px -12px,-12px 0}#dizzy-watermark-preview img{position:absolute;max-width:none;display:none}.dizzy-watermark-settings label{margin-right:14px}#dizzy-layout-stage{position:relative;width:min(600px,100%);aspect-ratio:1/1;overflow:hidden;background:linear-gradient(135deg,#31343b,#101114);border:1px solid #8c8f94;touch-action:none}#dizzy-layer-preview{position:absolute;inset:0;width:100%;height:100%;object-fit:fill;pointer-events:none}#dizzy-layer-preview:not([src]){display:none}.dizzy-drag-text{position:absolute;color:#fff;font-weight:700;cursor:move;user-select:none;max-width:85%;padding:5px;border:1px dashed rgba(255,255,255,.75);text-shadow:0 1px 3px #000;white-space:nowrap}#dizzy-drag-title{font-size:28px}#dizzy-drag-date{font-size:16px}
        </style>
        <script>
        (()=>{const form=document.querySelector('.dizzy-watermark-settings form'),preview=document.querySelector('#dizzy-watermark-preview img'),id=document.querySelector('#dizzy-watermark-id');if(!form)return;let frame;const val=n=>form.querySelector(`[name="${n}"]:checked`)?.value||form.querySelector(`[name="${n}"]`)?.value||'';const update=()=>{document.querySelectorAll('#dizzy-alignment-grid label').forEach(l=>l.classList.toggle('is-selected',l.querySelector('input').checked));form.querySelectorAll('input[type=range]').forEach(r=>r.parentElement.querySelector('output').textContent=r.value);if(!preview.src){preview.style.display='none';return}preview.style.display='block';const mode=val('dizzy_social_watermark_size_mode'),scale=Number(val('dizzy_social_watermark_scale')),custom=Number(val('dizzy_social_watermark_custom_width'));preview.style.width=mode==='scaled'?scale+'%':mode==='custom'?Math.min(100,custom/6)+'%':'auto';preview.style.height='auto';preview.style.opacity=Number(val('dizzy_social_watermark_opacity'))/100;const p=val('dizzy_social_watermark_alignment').split('_'),unit=val('dizzy_social_watermark_offset_unit')==='percentages'?'%':'px',x=Number(val('dizzy_social_watermark_offset_x'))+unit,y=Number(val('dizzy_social_watermark_offset_y'))+unit;preview.style.left=p[1]==='left'?x:p[1]==='right'?'auto':'calc(50% + '+x+')';preview.style.right=p[1]==='right'?x:'auto';preview.style.top=p[0]==='top'?y:p[0]==='bottom'?'auto':'calc(50% + '+y+')';preview.style.bottom=p[0]==='bottom'?y:'auto';preview.style.transform=(p[1]==='center'?'translateX(-50%) ':'')+(p[0]==='middle'?'translateY(-50%)':'')};form.addEventListener('input',update);document.querySelector('#dizzy-select-watermark').addEventListener('click',()=>{frame=wp.media({title:'Select watermark',multiple:false,library:{type:'image'}});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();id.value=a.id;preview.src=a.url;update()});frame.open()});document.querySelector('#dizzy-remove-watermark').addEventListener('click',()=>{id.value='0';preview.removeAttribute('src');update()});update()})();
        (()=>{const stage=document.querySelector('#dizzy-layout-stage'),layer=document.querySelector('#dizzy-layer-preview'),layerId=document.querySelector('#dizzy-layer-id');if(!stage)return;const fields={title:{x:document.querySelector('#dizzy-title-x'),y:document.querySelector('#dizzy-title-y'),el:document.querySelector('#dizzy-drag-title')},date:{x:document.querySelector('#dizzy-date-x'),y:document.querySelector('#dizzy-date-y'),el:document.querySelector('#dizzy-drag-date')}};const place=key=>{const o=fields[key];o.el.style.left=o.x.value+'%';o.el.style.top=o.y.value+'%'};Object.keys(fields).forEach(key=>{place(key);const o=fields[key];o.el.addEventListener('pointerdown',event=>{event.preventDefault();o.el.setPointerCapture(event.pointerId);const move=e=>{const box=stage.getBoundingClientRect(),x=Math.max(0,Math.min(100,(e.clientX-box.left)/box.width*100)),y=Math.max(0,Math.min(100,(e.clientY-box.top)/box.height*100));o.x.value=x.toFixed(2);o.y.value=y.toFixed(2);place(key)};o.el.addEventListener('pointermove',move);o.el.addEventListener('pointerup',()=>o.el.removeEventListener('pointermove',move),{once:true})})});document.querySelector('#dizzy-select-layer').addEventListener('click',()=>{const frame=wp.media({title:'Select transparent PNG layer',button:{text:'Use layer'},multiple:false,library:{type:'image'}});frame.on('select',()=>{const a=frame.state().get('selection').first().toJSON();layerId.value=a.id;layer.src=a.url});frame.open()});document.querySelector('#dizzy-remove-layer').addEventListener('click',()=>{layerId.value='0';layer.removeAttribute('src')});const setFont=(target,a)=>{document.querySelector('#dizzy-'+target+'-font-id').value=a?.id||0;document.querySelector('#dizzy-'+target+'-font-name').textContent=a?.title||'Default';const el=fields[target].el;if(a?.url){const style=document.createElement('style');style.textContent='@font-face{font-family:Dizzy'+target+';src:url("'+a.url.replace(/"/g,'')+'")}.dizzy-drag-'+target+'{}';document.head.appendChild(style);el.style.fontFamily='Dizzy'+target}else el.style.removeProperty('font-family')};document.querySelectorAll('.dizzy-select-font').forEach(button=>button.addEventListener('click',()=>{const target=button.dataset.target,frame=wp.media({title:'Select or upload TTF / OTF font',button:{text:'Use font'},multiple:false});frame.on('select',()=>setFont(target,frame.state().get('selection').first().toJSON()));frame.open()}));document.querySelectorAll('.dizzy-remove-font').forEach(button=>button.addEventListener('click',()=>setFont(button.dataset.target,null)));<?php if ($titleFontUrl !== '') : ?>setFont('title',{id:<?php echo $titleFontId; ?>,url:<?php echo wp_json_encode($titleFontUrl); ?>,title:<?php echo wp_json_encode(get_the_title($titleFontId)); ?>});<?php endif; ?><?php if ($dateFontUrl !== '') : ?>setFont('date',{id:<?php echo $dateFontId; ?>,url:<?php echo wp_json_encode($dateFontUrl); ?>,title:<?php echo wp_json_encode(get_the_title($dateFontId)); ?>});<?php endif; ?>})();
        </script>
        <?php
    }

    public function sanitizeAlignment(mixed $value): string
    {
        $allowed = ['top_left', 'top_center', 'top_right', 'middle_left', 'middle_center', 'middle_right', 'bottom_left', 'bottom_center', 'bottom_right'];
        return in_array($value, $allowed, true) ? (string) $value : 'top_center';
    }

    public function allowFontUploads(array $mimes): array
    {
        if (current_user_can('manage_options')) {
            $mimes['ttf'] = 'font/ttf';
            $mimes['otf'] = 'font/otf';
        }
        return $mimes;
    }

    public function sanitizePosition(mixed $value): float { return max(0, min(100, (float) $value)); }

    public function sanitizeUnit(mixed $value): string { return $value === 'pixels' ? 'pixels' : 'percentages'; }
    public function sanitizeSizeMode(mixed $value): string { return in_array($value, ['original', 'custom', 'scaled'], true) ? (string) $value : 'scaled'; }
    public function sanitizeOffset(mixed $value): float { return max(-10000, min(10000, (float) $value)); }
    public function sanitizeWidth(mixed $value): int { return max(1, min(4000, (int) $value)); }
    public function sanitizePercent(mixed $value): int { return max(0, min(100, (int) $value)); }
    public function sanitizeCheckbox(mixed $value): bool { return (bool) $value; }
}
