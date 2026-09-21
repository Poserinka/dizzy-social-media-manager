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
        foreach (['title_font', 'date_font', 'hours_font'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeFont'], 'default' => '']);
        }
        foreach (['title_color', 'date_color', 'hours_color'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeColor'], 'default' => '#ffffff']);
        }
        foreach (['title_align', 'date_align', 'hours_align'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'string', 'sanitize_callback' => [$this, 'sanitizeAlignment'], 'default' => 'left']);
        }
        foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 86, 'hours_x' => 7.5, 'hours_y' => 92, 'logo_x' => 70, 'logo_y' => 5, 'title_size' => 6.4, 'date_size' => 2.6, 'hours_size' => 2.6, 'logo_width' => 25] as $key => $default) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => $default]);
        }
        foreach (['background_x' => 0, 'background_y' => 0, 'background_width' => 100, 'background_height' => 100] as $key => $default) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'number', 'sanitize_callback' => [$this, 'sanitizePercent'], 'default' => $default]);
        }
        foreach (['title_enabled', 'date_enabled', 'hours_enabled', 'logo_enabled'] as $key) {
            register_setting(self::GROUP, 'dizzy_social_' . $key, ['type' => 'integer', 'sanitize_callback' => [$this, 'sanitizeEnabled'], 'default' => 1]);
        }
        foreach (['openai_api_key', 'watermark_image_id', 'watermark_alignment', 'watermark_offset_x', 'watermark_offset_y', 'watermark_offset_unit', 'watermark_size_mode', 'watermark_custom_width', 'watermark_scale', 'watermark_opacity', 'watermark_social', 'watermark_print', 'title_font_id', 'date_font_id', 'title_rotation', 'date_rotation', 'hours_rotation', 'logo_rotation'] as $legacy) {
            delete_option('dizzy_social_' . $legacy);
        }
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) return;
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        $fonts = $this->fontFiles();
        $layerId = (int) get_option('dizzy_social_layer_image_id', 0);
        $logoId = (int) get_option('dizzy_social_logo_image_id', 0);
        $layerUrl = $layerId > 0 ? (string) wp_get_attachment_image_url($layerId, 'full') : '';
        $logoUrl = $logoId > 0 ? (string) wp_get_attachment_image_url($logoId, 'full') : '';
        $values = [];
        foreach (['title_x' => 7.5, 'title_y' => 68, 'date_x' => 7.5, 'date_y' => 86, 'hours_x' => 7.5, 'hours_y' => 92, 'logo_x' => 70, 'logo_y' => 5, 'title_size' => 6.4, 'date_size' => 2.6, 'hours_size' => 2.6, 'logo_width' => 25] as $key => $default) $values[$key] = (float) get_option('dizzy_social_' . $key, $default);
        foreach (['background_x' => 0, 'background_y' => 0, 'background_width' => 100, 'background_height' => 100] as $key => $default) $values[$key] = (float) get_option('dizzy_social_' . $key, $default);
        foreach (['title', 'date', 'hours', 'logo'] as $key) $values[$key . '_enabled'] = (int) get_option('dizzy_social_' . $key . '_enabled', 1);
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
                    <?php foreach (['title' => __('Title font', 'dizzy-social-media-manager'), 'date' => __('Date font', 'dizzy-social-media-manager'), 'hours' => __('Hours font', 'dizzy-social-media-manager')] as $key => $label) : ?>
                        <tr><th><label for="dizzy-<?php echo esc_attr($key); ?>-font"><?php echo esc_html($label); ?></label></th><td>
                            <select id="dizzy-<?php echo esc_attr($key); ?>-font" name="dizzy_social_<?php echo esc_attr($key); ?>_font">
                                <option value=""><?php esc_html_e('Default font', 'dizzy-social-media-manager'); ?></option>
                                <?php foreach ($fonts as $filename => $font) : ?><option value="<?php echo esc_attr($filename); ?>" data-url="<?php echo esc_url($font['url']); ?>" <?php selected((string) get_option('dizzy_social_' . $key . '_font', ''), $filename); ?>><?php echo esc_html($font['label']); ?></option><?php endforeach; ?>
                            </select>
                        </td></tr>
                    <?php endforeach; ?>
                    <?php foreach (['title' => __('Title alignment', 'dizzy-social-media-manager'), 'date' => __('Date alignment', 'dizzy-social-media-manager'), 'hours' => __('Hours alignment', 'dizzy-social-media-manager')] as $key => $label) : ?>
                        <tr><th><label for="dizzy-<?php echo esc_attr($key); ?>-align"><?php echo esc_html($label); ?></label></th><td>
                            <select id="dizzy-<?php echo esc_attr($key); ?>-align" name="dizzy_social_<?php echo esc_attr($key); ?>_align">
                                <?php foreach (['left' => __('Left', 'dizzy-social-media-manager'), 'center' => __('Center', 'dizzy-social-media-manager'), 'right' => __('Right', 'dizzy-social-media-manager')] as $value => $text) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string) get_option('dizzy_social_' . $key . '_align', 'left'), $value); ?>><?php echo esc_html($text); ?></option><?php endforeach; ?>
                            </select>
                        </td></tr>
                    <?php endforeach; ?>
                    <?php foreach (['title' => __('Title color', 'dizzy-social-media-manager'), 'date' => __('Date color', 'dizzy-social-media-manager'), 'hours' => __('Hours color', 'dizzy-social-media-manager')] as $key => $label) : ?>
                        <tr><th><label for="dizzy-<?php echo esc_attr($key); ?>-color"><?php echo esc_html($label); ?></label></th><td>
                            <input
                                id="dizzy-<?php echo esc_attr($key); ?>-color"
                                class="dizzy-poster-color-picker"
                                type="text"
                                name="dizzy_social_<?php echo esc_attr($key); ?>_color"
                                value="<?php echo esc_attr((string) get_option('dizzy_social_' . $key . '_color', '#ffffff')); ?>"
                                data-item="<?php echo esc_attr($key); ?>"
                                data-default-color="#ffffff"
                            >
                        </td></tr>
                    <?php endforeach; ?>
                </table>
                <?php if ($fonts === []) : ?><p class="notice notice-warning inline"><?php esc_html_e('No fonts were found in dizzy-events-manager/assets/fonts. Upload and commit TTF or OTF files to that folder.', 'dizzy-social-media-manager'); ?></p><?php endif; ?>

                <h2><?php esc_html_e('Drag / Drop Layout', 'dizzy-social-media-manager'); ?></h2>
                <p><?php esc_html_e('Drag elements to move them and use the square handle to resize. Use the arrow keys for one-pixel movement; hold Shift for ten pixels. The background photo frame can be moved and resized independently.', 'dizzy-social-media-manager'); ?></p>
                <p class="dizzy-layout-tools"><button type="button" class="button" data-add="title"><?php esc_html_e('Add Title', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-add="date"><?php esc_html_e('Add Date', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-add="hours"><?php esc_html_e('Add Hours', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-add="logo"><?php esc_html_e('Add Logo', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-select-background><?php esc_html_e('Edit Background Photo', 'dizzy-social-media-manager'); ?></button> <button type="button" class="button" data-reset-background><?php esc_html_e('Reset Background Photo', 'dizzy-social-media-manager'); ?></button></p>
                <?php foreach ($values as $key => $value) : ?><input id="dizzy-<?php echo esc_attr(str_replace('_', '-', $key)); ?>" type="hidden" name="dizzy_social_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $value); ?>"><?php endforeach; ?>
                <div class="dizzy-background-controls">
                    <strong><?php esc_html_e('Background photo frame', 'dizzy-social-media-manager'); ?></strong>
                    <?php foreach (['x' => __('Left', 'dizzy-social-media-manager'), 'y' => __('Top', 'dizzy-social-media-manager'), 'width' => __('Width', 'dizzy-social-media-manager'), 'height' => __('Height', 'dizzy-social-media-manager')] as $key => $label) : ?>
                        <label><?php echo esc_html($label); ?> <input type="number" min="0" max="100" step="0.1" data-background-control="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr((string) $values['background_' . $key]); ?>">%</label>
                    <?php endforeach; ?>
                </div>
                <div id="dizzy-layout-stage" tabindex="0">
                    <div class="dizzy-layout-item dizzy-background-item" data-item="background"><span><?php esc_html_e('BACKGROUND PHOTO', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <img id="dizzy-layer-preview"<?php echo $layerUrl !== '' ? ' src="' . esc_url($layerUrl) . '"' : ''; ?> alt="">
                    <div class="dizzy-layout-item" data-item="title"><span><?php esc_html_e('EVENT TITLE', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <div class="dizzy-layout-item" data-item="date"><span><?php esc_html_e('EVENT DATE', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <div class="dizzy-layout-item" data-item="hours"><span><?php esc_html_e('STARTING HOUR', 'dizzy-social-media-manager'); ?></span><i class="dizzy-resize-handle"></i></div>
                    <div class="dizzy-layout-item dizzy-logo-item" data-item="logo"><img<?php echo $logoUrl !== '' ? ' src="' . esc_url($logoUrl) . '"' : ''; ?> alt=""><i class="dizzy-resize-handle"></i></div>
                </div>
                <p class="description"><?php esc_html_e('The preview uses the 1080 × 1350 portrait format. Saved percentage positions and sizes are applied proportionally during poster generation.', 'dizzy-social-media-manager'); ?></p>
                <?php submit_button(); ?>
            </form>
        </div>
        <style>
        #dizzy-layout-stage{position:relative;width:min(600px,100%);aspect-ratio:4/5;overflow:hidden;background:#101114;border:2px solid #8c8f94;touch-action:none;outline:none}#dizzy-layout-stage:focus{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}#dizzy-layer-preview{position:absolute;z-index:1;inset:0;width:100%;height:100%;object-fit:fill;pointer-events:none}#dizzy-layer-preview:not([src]){display:none}.dizzy-layout-item{position:absolute;z-index:2;color:#fff;font-weight:700;cursor:move;user-select:none;border:0;padding:0;line-height:1;white-space:nowrap;text-shadow:0 1px 3px #000;transform-origin:top left}.dizzy-layout-item.is-selected{outline:2px dashed #72aee6;background:rgba(34,113,177,.2)}.dizzy-layout-item[data-item=title]{font-size:38px}.dizzy-layout-item[data-item=date],.dizzy-layout-item[data-item=hours]{font-size:16px}.dizzy-background-item{z-index:0;display:flex;align-items:center;justify-content:center;box-sizing:border-box;min-width:5%;min-height:5%;overflow:visible;background:linear-gradient(135deg,#3f4650,#191c20);color:rgba(255,255,255,.65);font-size:14px;letter-spacing:1px;text-shadow:none}.dizzy-background-item.is-selected{z-index:3;outline-color:#d63638;background:rgba(214,54,56,.16)}.dizzy-background-item .dizzy-resize-handle{right:6px;bottom:6px;width:22px;height:22px;background:#d63638;box-shadow:0 0 0 2px rgba(255,255,255,.8)}.dizzy-logo-item{width:25%;padding:0}.dizzy-logo-item img{display:block;width:100%;height:auto}.dizzy-logo-item img:not([src]){min-height:50px;background:rgba(255,255,255,.2)}.dizzy-resize-handle{display:none;position:absolute;right:-6px;bottom:-6px;width:12px;height:12px;background:#2271b1;border:1px solid #fff;box-sizing:border-box;cursor:nwse-resize}.is-selected .dizzy-resize-handle{display:block}.dizzy-layout-tools{margin-bottom:10px}.dizzy-background-controls{display:flex;align-items:center;flex-wrap:wrap;gap:10px 16px;margin:0 0 12px;padding:10px 12px;width:min(576px,100%);box-sizing:border-box;background:#fff;border:1px solid #c3c4c7}.dizzy-background-controls label{display:flex;align-items:center;gap:5px}.dizzy-background-controls input{width:76px}
        #dizzy-layout-stage::after{content:"";position:absolute;z-index:10;pointer-events:none;top:0;bottom:0;left:50%;width:1px;background:rgba(255,255,255,.65);box-shadow:0 0 1px rgba(0,0,0,.8);transition:background .12s,box-shadow .12s}#dizzy-layout-stage.is-snapping::after{width:2px;background:#72aee6;box-shadow:0 0 8px #72aee6}
        </style>
        <script>
        (() => {
            const stage = document.querySelector('#dizzy-layout-stage');
            const layer = document.querySelector('#dizzy-layer-preview');
            const logo = document.querySelector('[data-item=logo] img');
            if (!stage) return;

            const cfg = {
                background: {x: 'background-x', y: 'background-y', width: 'background-width', height: 'background-height'},
                title: {x: 'title-x', y: 'title-y', size: 'title-size', color: 'title-color'},
                date: {x: 'date-x', y: 'date-y', size: 'date-size', color: 'date-color'},
                hours: {x: 'hours-x', y: 'hours-y', size: 'hours-size', color: 'hours-color'},
                logo: {x: 'logo-x', y: 'logo-y', size: 'logo-width'}
            };
            let selected = null;
            const field = name => document.querySelector('#dizzy-' + name);
            const item = key => stage.querySelector('[data-item=' + key + ']');
            const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

            const render = key => {
                const c = cfg[key];
                const el = item(key);
                if (key === 'background') {
                    el.style.left = field(c.x).value + '%';
                    el.style.top = field(c.y).value + '%';
                    el.style.width = field(c.width).value + '%';
                    el.style.height = field(c.height).value + '%';
                    Object.entries({x: c.x, y: c.y, width: c.width, height: c.height}).forEach(([control, source]) => {
                        const input = document.querySelector('[data-background-control="' + control + '"]');
                        if (input && document.activeElement !== input) input.value = Number(field(source).value).toFixed(2);
                    });
                    return;
                }

                el.hidden = !Number(field(key + '-enabled').value);
                el.style.left = field(c.x).value + '%';
                el.style.top = field(c.y).value + '%';
                const size = Number(field(c.size).value);
                if (key === 'logo') {
                    el.style.width = size + '%';
                    el.style.transform = 'none';
                    return;
                }

                const fontSelect = document.querySelector('#dizzy-' + key + '-font');
                const usesTtf = Boolean(fontSelect?.selectedOptions[0]?.dataset.url);
                const pointScale = usesTtf ? 96 / 72 : 1;
                el.style.fontSize = (size * stage.clientWidth / 100 * pointScale) + 'px';
                el.style.color = field(c.color)?.value || '#ffffff';
                const align = document.querySelector('#dizzy-' + key + '-align')?.value || 'left';
                el.style.transform = align === 'center' ? 'translateX(-50%)' : align === 'right' ? 'translateX(-100%)' : 'none';
                el.style.textAlign = align;
            };

            Object.keys(cfg).forEach(render);
            ['title', 'date', 'hours'].forEach(key => document.querySelector('#dizzy-' + key + '-align').addEventListener('change', () => render(key)));

            const choose = el => {
                stage.querySelectorAll('.dizzy-layout-item').forEach(node => node.classList.remove('is-selected'));
                selected = el?.dataset.item || null;
                if (el) el.classList.add('is-selected');
                stage.focus();
            };

            stage.querySelectorAll('.dizzy-layout-item').forEach(el => {
                el.addEventListener('pointerdown', event => {
                    event.preventDefault();
                    choose(el);
                    const key = el.dataset.item;
                    const c = cfg[key];
                    const resizing = event.target.classList.contains('dizzy-resize-handle');
                    const box = stage.getBoundingClientRect();
                    const startPointerX = event.clientX;
                    const startPointerY = event.clientY;
                    const startX = Number(field(c.x).value);
                    const startY = Number(field(c.y).value);
                    const startSize = key === 'background' ? 0 : Number(field(c.size).value);
                    const startWidth = key === 'background' ? Number(field(c.width).value) : 0;
                    const startHeight = key === 'background' ? Number(field(c.height).value) : 0;
                    el.setPointerCapture(event.pointerId);

                    const move = pointer => {
                        const deltaX = (pointer.clientX - startPointerX) / box.width * 100;
                        const deltaY = (pointer.clientY - startPointerY) / box.height * 100;
                        stage.classList.remove('is-snapping');

                        if (resizing) {
                            if (key === 'background') {
                                field(c.width).value = clamp(startWidth + deltaX, 5, 100 - startX).toFixed(2);
                                field(c.height).value = clamp(startHeight + deltaY, 5, 100 - startY).toFixed(2);
                            } else {
                                field(c.size).value = clamp(startSize + deltaX, 1, 100).toFixed(2);
                            }
                        } else {
                            const maxX = key === 'background' ? 100 - Number(field(c.width).value) : 100;
                            const maxY = key === 'background' ? 100 - Number(field(c.height).value) : 100;
                            let x = clamp(startX + deltaX, 0, maxX);
                            const snap = !['logo', 'background'].includes(key) && Math.abs(x - 50) <= 2.5;
                            if (snap) {
                                x = 50;
                                const align = document.querySelector('#dizzy-' + key + '-align');
                                if (align) align.value = 'center';
                            }
                            stage.classList.toggle('is-snapping', snap);
                            field(c.x).value = x.toFixed(2);
                            field(c.y).value = clamp(startY + deltaY, 0, maxY).toFixed(2);
                        }
                        render(key);
                    };

                    const end = () => {
                        stage.classList.remove('is-snapping');
                        el.removeEventListener('pointermove', move);
                    };
                    el.addEventListener('pointermove', move);
                    el.addEventListener('pointerup', end, {once: true});
                    el.addEventListener('pointercancel', end, {once: true});
                });
            });

            stage.addEventListener('keydown', event => {
                if (!selected) return;
                if ((event.key === 'Delete' || event.key === 'Backspace') && selected !== 'background') {
                    event.preventDefault();
                    field(selected + '-enabled').value = '0';
                    render(selected);
                    selected = null;
                    return;
                }

                const movement = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]}[event.key];
                if (!movement) return;
                event.preventDefault();
                const c = cfg[selected];
                const box = stage.getBoundingClientRect();
                const pixels = event.shiftKey ? 10 : 1;
                const deltaX = movement[0] * pixels / box.width * 100;
                const deltaY = movement[1] * pixels / box.height * 100;
                const maxX = selected === 'background' ? 100 - Number(field(c.width).value) : 100;
                const maxY = selected === 'background' ? 100 - Number(field(c.height).value) : 100;
                field(c.x).value = clamp(Number(field(c.x).value) + deltaX, 0, maxX).toFixed(4);
                field(c.y).value = clamp(Number(field(c.y).value) + deltaY, 0, maxY).toFixed(4);
                render(selected);
            });

            document.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => {
                const key = button.dataset.add;
                field(key + '-enabled').value = '1';
                render(key);
                choose(item(key));
            }));
            document.querySelector('[data-select-background]').addEventListener('click', () => choose(item('background')));
            document.querySelectorAll('[data-background-control]').forEach(control => control.addEventListener('input', () => {
                const c = cfg.background;
                const key = control.dataset.backgroundControl;
                const target = {x: c.x, y: c.y, width: c.width, height: c.height}[key];
                let value = Number(control.value);
                if (!Number.isFinite(value)) return;
                if (key === 'x') value = clamp(value, 0, 100 - Number(field(c.width).value));
                if (key === 'y') value = clamp(value, 0, 100 - Number(field(c.height).value));
                if (key === 'width') value = clamp(value, 5, 100 - Number(field(c.x).value));
                if (key === 'height') value = clamp(value, 5, 100 - Number(field(c.y).value));
                field(target).value = value.toFixed(2);
                render('background');
                stage.querySelectorAll('.dizzy-layout-item').forEach(node => node.classList.remove('is-selected'));
                selected = 'background';
                item('background').classList.add('is-selected');
            }));
            document.querySelector('[data-reset-background]').addEventListener('click', () => {
                field('background-x').value = '0';
                field('background-y').value = '0';
                field('background-width').value = '100';
                field('background-height').value = '100';
                render('background');
                choose(item('background'));
            });

            const media = kind => {
                const frame = wp.media({title: kind === 'layer' ? 'Select transparent PNG layer' : 'Select logo', button: {text: 'Use image'}, library: {type: 'image'}, multiple: false});
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    field(kind + '-image-id').value = attachment.id;
                    if (kind === 'layer') layer.src = attachment.url;
                    else {
                        logo.src = attachment.url;
                        field('logo-enabled').value = '1';
                        render('logo');
                    }
                });
                frame.open();
            };
            document.querySelector('#dizzy-select-layer').addEventListener('click', () => media('layer'));
            document.querySelector('#dizzy-remove-layer').addEventListener('click', () => {
                field('layer-image-id').value = '0';
                layer.removeAttribute('src');
            });
            document.querySelector('#dizzy-select-logo').addEventListener('click', () => media('logo'));
            document.querySelector('#dizzy-remove-logo').addEventListener('click', () => {
                field('logo-image-id').value = '0';
                logo.removeAttribute('src');
                field('logo-enabled').value = '0';
                render('logo');
            });

            const fonts = {title: document.querySelector('#dizzy-title-font'), date: document.querySelector('#dizzy-date-font'), hours: document.querySelector('#dizzy-hours-font')};
            Object.entries(fonts).forEach(([key, select]) => {
                const apply = () => {
                    const url = select.selectedOptions[0]?.dataset.url || '';
                    if (!url) {
                        item(key).style.removeProperty('font-family');
                        return;
                    }
                    const style = document.createElement('style');
                    style.textContent = '@font-face{font-family:Dizzy' + key + ';src:url("' + url.replace(/"/g, '') + '")}';
                    document.head.appendChild(style);
                    item(key).style.fontFamily = 'Dizzy' + key;
                };
                select.addEventListener('change', () => {
                    apply();
                    render(key);
                });
                apply();
            });

            window.addEventListener('resize', () => Object.keys(cfg).forEach(render));
            document.addEventListener('DOMContentLoaded', () => {
                jQuery('.dizzy-poster-color-picker').each(function () {
                    const input = this;
                    jQuery(input).wpColorPicker({
                        change: (_, ui) => {
                            input.value = ui.color.toString();
                            render(input.dataset.item);
                        },
                        clear: () => {
                            input.value = '#ffffff';
                            render(input.dataset.item);
                        }
                    });
                });
            });
        })();
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

    public function sanitizeColor(mixed $value): string
    {
        return sanitize_hex_color((string) $value) ?: '#ffffff';
    }

    public function sanitizeAlignment(mixed $value): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? (string) $value : 'left';
    }

    public function sanitizePercent(mixed $value): float { return max(0, min(100, (float) $value)); }
    public function sanitizeEnabled(mixed $value): int { return (int) ((bool) $value); }
}
