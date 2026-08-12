<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Admin Features
 * Version: 0.9.8
 */

// Load WP_List_Table safely (only when needed)
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
require_once __DIR__ . '/class-ve-guest-list-table.php';

// ====================== HEALTH CHECKS ======================

/**
 * Ticket emails require QR images; QR generation needs phpqrcode (see Third-party libraries.md).
 * Without it, payment success marks paid but skips every ticket email (D19).
 */
add_action('admin_notices', 've_admin_notice_missing_phpqrcode');
function ve_admin_notice_missing_phpqrcode() {
    if (!current_user_can('manage_options')) {
        return;
    }
    // Only on our screens + plugins list (where deploys go wrong)
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $show   = false;
    if ($screen) {
        $id = (string) $screen->id;
        if (
            strpos($id, 've_event') !== false
            || strpos($id, 'venture-events') !== false
            || $id === 'plugins'
        ) {
            $show = true;
        }
    }
    if (!$show) {
        return;
    }

    $qrlib = (defined('VE_PATH') ? VE_PATH : '') . 'includes/phpqrcode/qrlib.php';
    if ($qrlib !== '' && file_exists($qrlib)) {
        return;
    }

    echo '<div class="notice notice-error"><p><strong>Venture Events:</strong> '
        . esc_html__('The phpqrcode library is missing (includes/phpqrcode/qrlib.php). QR codes cannot be generated, so ticket emails will not send. Restore the library from the plugin package or original includes/phpqrcode folder.', 'venture-events')
        . '</p></div>';
}

// ====================== EVENTS LIST TABLE ======================

/** Drop the frontend "View" row action — events are embedded via shortcode. */
add_filter('post_row_actions', 've_event_row_actions', 10, 2);
function ve_event_row_actions($actions, $post) {
    if ($post instanceof WP_Post && $post->post_type === 've_event') {
        unset($actions['view']);
    }
    return $actions;
}

/** Add a Shortcode column on the Events list. */
add_filter('manage_ve_event_posts_columns', 've_event_list_columns');
function ve_event_list_columns($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        // Place Shortcode right after the title.
        if ($key === 'title') {
            $new['ve_shortcode'] = 'Shortcode';
        }
    }
    if (!isset($new['ve_shortcode'])) {
        $new['ve_shortcode'] = 'Shortcode';
    }
    return $new;
}

add_action('manage_ve_event_posts_custom_column', 've_event_list_column_content', 10, 2);
function ve_event_list_column_content($column, $post_id) {
    if ($column !== 've_shortcode') {
        return;
    }
    $id = (int) $post_id;
    $normal = sprintf('[venture_registration event_id="%d"]', $id);
    printf(
        '<div><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code></div>',
        esc_html($normal)
    );

    $special_tiers = function_exists('ve_get_special_tiers')
        ? ve_get_special_tiers($id)
        : (get_post_meta($id, '_ve_special_tiers', true) ?: []);
    if (!empty($special_tiers) && is_array($special_tiers)) {
        $special = sprintf('[venture_registration event_id="S%d"]', $id);
        printf(
            '<div style="margin-top:4px;"><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code> <span style="color:#646970;">special</span></div>',
            esc_html($special)
        );
    }

    $gate = sprintf('[venture_gate_scan event_id="%d"]', $id);
    printf(
        '<div style="margin-top:4px;"><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code> <span style="color:#646970;">gate</span></div>',
        esc_html($gate)
    );

    $comp = sprintf('[venture_complimentary event_id="%d"]', $id);
    printf(
        '<div style="margin-top:4px;"><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code> <span style="color:#646970;">comp</span></div>',
        esc_html($comp)
    );

    $eft = sprintf('[venture_eft event_id="%d"]', $id);
    printf(
        '<div style="margin-top:4px;"><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code> <span style="color:#646970;">eft</span></div>',
        esc_html($eft)
    );
    if (!empty($special_tiers) && is_array($special_tiers)) {
        $eft_special = sprintf('[venture_eft event_id="S%d"]', $id);
        printf(
            '<div style="margin-top:4px;"><code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code> <span style="color:#646970;">eft special</span></div>',
            esc_html($eft_special)
        );
    }
}

// ====================== TIERS META BOX ======================
add_action('add_meta_boxes', 've_add_tiers_meta_box');
function ve_add_tiers_meta_box() {
    add_meta_box(
        've_tiers_meta',
        'Event Ticket Tiers',
        've_tiers_meta_box_html',
        've_event',
        'normal',
        'high'
    );
}

function ve_tiers_meta_box_html($post) {
    $tiers = get_post_meta($post->ID, '_ve_tiers', true) ?: [];
    wp_nonce_field('ve_save_tiers', 've_tiers_nonce');
    ?>
    <div id="ve-tiers-wrapper">
        <?php foreach ($tiers as $key => $tier): ?>
            <div class="ve-tier-row" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">
                <input type="text" name="ve_tiers[<?php echo esc_attr($key); ?>][name]" 
                       value="<?php echo esc_attr($tier['name']); ?>" style="width:30%;">
                <input type="number" name="ve_tiers[<?php echo esc_attr($key); ?>][price]" 
                       value="<?php echo esc_attr($tier['price']); ?>" step="0.01" style="width:20%;">
                <button type="button" class="button ve-remove-tier">Remove</button>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="ve-add-tier" class="button">+ Add Tier</button>

    <script>
    jQuery(document).ready(function($) {
        // Unique temp keys only until save (then converted to name slugs)
        let count = Date.now();
        $('#ve-add-tier').on('click', function() {
            count++;
            const html = `
                <div class="ve-tier-row" style="border:1px solid #ddd; padding:10px; margin-bottom:10px;">
                    <input type="text" name="ve_tiers[new${count}][name]" placeholder="Tier Name (e.g. Standard)" style="width:30%;">
                    <input type="number" name="ve_tiers[new${count}][price]" placeholder="Price (N$)" step="0.01" style="width:20%;">
                    <button type="button" class="button ve-remove-tier">Remove</button>
                </div>`;
            $('#ve-tiers-wrapper').append(html);
        });

        $(document).on('click', '.ve-remove-tier', function() {
            $(this).parent('.ve-tier-row').remove();
        });
    });
    </script>
    <?php
}

// Save tiers
add_action('save_post_ve_event', 've_save_tiers_meta');
function ve_save_tiers_meta($post_id) {
    if (!isset($_POST['ve_tiers_nonce']) || !wp_verify_nonce($_POST['ve_tiers_nonce'], 've_save_tiers')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    $previous = get_post_meta($post_id, '_ve_tiers', true);
    if (!is_array($previous)) {
        $previous = [];
    }

    $tiers = [];
    if (isset($_POST['ve_tiers']) && is_array($_POST['ve_tiers'])) {
        foreach ($_POST['ve_tiers'] as $key => $tier) {
            $name  = sanitize_text_field($tier['name'] ?? '');
            $price = floatval($tier['price'] ?? 0);
            if (!$name || $price <= 0) {
                continue;
            }

            $key = (string) $key;
            // Only slugify brand-new temp keys (new…). Keep keys already stored on the
            // event (including historic new1/new2) so existing registrations still resolve.
            $is_temp_new   = ($key === '' || preg_match('/^new\d+$/i', $key));
            $already_saved = array_key_exists($key, $previous);
            if ($is_temp_new && !$already_saved) {
                $base = sanitize_title($name);
                if ($base === '') {
                    $base = 'tier';
                }
                $slug = $base;
                $i    = 2;
                while (isset($tiers[$slug])) {
                    $slug = $base . '-' . $i;
                    $i++;
                }
                $key = $slug;
            }

            $tiers[$key] = [
                'name'  => $name,
                'price' => $price,
            ];
        }
    }
    update_post_meta($post_id, '_ve_tiers', $tiers);
}

// ====================== SPECIAL EVENT TICKET TIERS META BOX ======================
add_action('add_meta_boxes', 've_add_special_tiers_meta_box');
function ve_add_special_tiers_meta_box() {
    add_meta_box(
        've_special_tiers_meta',
        'Special Event Ticket Tiers',
        've_special_tiers_meta_box_html',
        've_event',
        'normal',
        'high'
    );
}

function ve_special_tiers_meta_box_html($post) {
    $special = get_post_meta($post->ID, '_ve_special_tiers', true) ?: [];
    $tiers   = get_post_meta($post->ID, '_ve_tiers', true) ?: [];
    if (!is_array($special)) {
        $special = [];
    }
    if (!is_array($tiers)) {
        $tiers = [];
    }

    wp_nonce_field('ve_save_special_tiers', 've_special_tiers_nonce');
    ?>
    <p class="description" style="margin-bottom:12px;">
        Packages such as tables or exhibition stands. Each package can include a fixed number of free tickets
        (tier chosen here — buyers cannot change it). Use shortcode
        <code>[venture_registration event_id="S<?php echo (int) $post->ID; ?>"]</code> on a separate page.
        Save normal <strong>Event Ticket Tiers</strong> first if you need to pick an included free ticket tier.
        <strong>Amount available</strong> limits how many of that package can be sold (paid + pending + EFT orders count).
        Use <code>0</code> for unlimited.
    </p>

    <div id="ve-special-tiers-wrapper">
        <?php foreach ($special as $key => $tier): ?>
            <?php
            $name          = esc_attr($tier['name'] ?? '');
            $price         = esc_attr($tier['price'] ?? '');
            $free_tickets  = (int) ($tier['free_tickets'] ?? 0);
            $free_tier_key = (string) ($tier['free_tier_key'] ?? '');
            $available     = max(0, (int) ($tier['available'] ?? 0));
            $sold          = function_exists('ve_count_special_tier_sold')
                ? ve_count_special_tier_sold((int) $post->ID, (string) $key)
                : 0;
            $remaining     = ($available > 0) ? max(0, $available - $sold) : null;
            ?>
            <div class="ve-special-tier-row" style="border:1px solid #ddd; padding:12px; margin-bottom:10px; background:#fafafa;">
                <p style="margin:0 0 8px;">
                    <label>Package name<br>
                        <input type="text" name="ve_special_tiers[<?php echo esc_attr($key); ?>][name]"
                               value="<?php echo $name; ?>" style="width:100%; max-width:420px;"
                               placeholder="e.g. Village Table, Medium Exhibition Space">
                    </label>
                </p>
                <p style="margin:0 0 8px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                    <label>Price (N$)<br>
                        <input type="number" name="ve_special_tiers[<?php echo esc_attr($key); ?>][price]"
                               value="<?php echo $price; ?>" step="0.01" min="0.01" style="width:120px;">
                    </label>
                    <label>Amount available<br>
                        <input type="number" name="ve_special_tiers[<?php echo esc_attr($key); ?>][available]"
                               value="<?php echo esc_attr($available); ?>" step="1" min="0" style="width:100px;"
                               title="0 = unlimited">
                    </label>
                    <label>Free tickets included<br>
                        <input type="number" name="ve_special_tiers[<?php echo esc_attr($key); ?>][free_tickets]"
                               value="<?php echo esc_attr($free_tickets); ?>" step="1" min="0" style="width:100px;">
                    </label>
                    <label>Free ticket tier<br>
                        <select name="ve_special_tiers[<?php echo esc_attr($key); ?>][free_tier_key]" style="min-width:180px;">
                            <option value="">— Select tier —</option>
                            <?php foreach ($tiers as $tkey => $t): ?>
                                <option value="<?php echo esc_attr($tkey); ?>" <?php selected($free_tier_key, (string) $tkey); ?>>
                                    <?php echo esc_html($t['name'] ?? $tkey); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="button ve-remove-special-tier">Remove</button>
                </p>
                <p class="description" style="margin:0;">
                    If free tickets is 0, the free ticket tier is not used.
                    <?php if ($available > 0): ?>
                        · Sold / held: <strong><?php echo (int) $sold; ?></strong>
                        · Remaining: <strong><?php echo (int) $remaining; ?></strong>
                        <?php if ($remaining === 0): ?>
                            <span style="color:#b32d2e;">(sold out)</span>
                        <?php endif; ?>
                    <?php else: ?>
                        · Stock: <strong>unlimited</strong>
                        <?php if ($sold > 0): ?>
                            (<?php echo (int) $sold; ?> sold / held so far)
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
        <?php endforeach; ?>
    </div>

    <button type="button" id="ve-add-special-tier" class="button">+ Add Special Tier</button>

    <script type="text/html" id="ve-special-tier-template">
        <div class="ve-special-tier-row" style="border:1px solid #ddd; padding:12px; margin-bottom:10px; background:#fafafa;">
            <p style="margin:0 0 8px;">
                <label>Package name<br>
                    <input type="text" name="ve_special_tiers[__KEY__][name]" value="" style="width:100%; max-width:420px;"
                           placeholder="e.g. Village Table, Medium Exhibition Space">
                </label>
            </p>
            <p style="margin:0 0 8px; display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
                <label>Price (N$)<br>
                    <input type="number" name="ve_special_tiers[__KEY__][price]" value="" step="0.01" min="0.01" style="width:120px;" placeholder="0.00">
                </label>
                <label>Amount available<br>
                    <input type="number" name="ve_special_tiers[__KEY__][available]" value="0" step="1" min="0" style="width:100px;" title="0 = unlimited">
                </label>
                <label>Free tickets included<br>
                    <input type="number" name="ve_special_tiers[__KEY__][free_tickets]" value="0" step="1" min="0" style="width:100px;">
                </label>
                <label>Free ticket tier<br>
                    <select name="ve_special_tiers[__KEY__][free_tier_key]" style="min-width:180px;">
                        <option value="">— Select tier —</option>
                        <?php foreach ($tiers as $tkey => $t): ?>
                            <option value="<?php echo esc_attr($tkey); ?>">
                                <?php echo esc_html($t['name'] ?? $tkey); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="button ve-remove-special-tier">Remove</button>
            </p>
            <p class="description" style="margin:0;">If free tickets is 0, the free ticket tier is not used. Amount available: 0 = unlimited.</p>
        </div>
    </script>

    <script>
    jQuery(document).ready(function($) {
        let count = Date.now();
        $('#ve-add-special-tier').on('click', function() {
            count++;
            const key = 'new' + count;
            let html = $('#ve-special-tier-template').html().replace(/__KEY__/g, key);
            $('#ve-special-tiers-wrapper').append(html);
        });
        $(document).on('click', '.ve-remove-special-tier', function() {
            $(this).closest('.ve-special-tier-row').remove();
        });
    });
    </script>
    <?php
}

add_action('save_post_ve_event', 've_save_special_tiers_meta', 20);
function ve_save_special_tiers_meta($post_id) {
    if (!isset($_POST['ve_special_tiers_nonce']) || !wp_verify_nonce($_POST['ve_special_tiers_nonce'], 've_save_special_tiers')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $previous = get_post_meta($post_id, '_ve_special_tiers', true);
    if (!is_array($previous)) {
        $previous = [];
    }

    // Prefer freshly saved normal tiers from this same request when available
    $normal_tiers = get_post_meta($post_id, '_ve_tiers', true);
    if (!is_array($normal_tiers)) {
        $normal_tiers = [];
    }

    $special = [];
    if (isset($_POST['ve_special_tiers']) && is_array($_POST['ve_special_tiers'])) {
        foreach ($_POST['ve_special_tiers'] as $key => $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $name         = sanitize_text_field($tier['name'] ?? '');
            $price        = floatval($tier['price'] ?? 0);
            $free_tickets = max(0, intval($tier['free_tickets'] ?? 0));
            $free_tier    = sanitize_text_field($tier['free_tier_key'] ?? '');
            $available    = max(0, intval($tier['available'] ?? 0));

            if ($name === '' || $price <= 0) {
                continue;
            }

            // Free tier required only when free tickets > 0
            if ($free_tickets > 0) {
                if ($free_tier === '' || !isset($normal_tiers[$free_tier])) {
                    // Skip invalid free-tier config rather than saving a broken package
                    continue;
                }
            } else {
                $free_tier = '';
            }

            $key = (string) $key;
            $is_temp_new   = ($key === '' || preg_match('/^new\d+$/i', $key));
            $already_saved = array_key_exists($key, $previous);
            if ($is_temp_new && !$already_saved) {
                $base = sanitize_title($name);
                if ($base === '') {
                    $base = 'package';
                }
                $slug = $base;
                $i    = 2;
                while (isset($special[$slug])) {
                    $slug = $base . '-' . $i;
                    $i++;
                }
                $key = $slug;
            }

            $special[$key] = [
                'name'          => $name,
                'price'         => $price,
                'available'     => $available,
                'free_tickets'  => $free_tickets,
                'free_tier_key' => $free_tier,
            ];
        }
    }

    update_post_meta($post_id, '_ve_special_tiers', $special);
}

// ====================== DANGER: CLEAR ALL TICKETS META BOX ======================

add_action('add_meta_boxes', 've_add_clear_tickets_meta_box');
function ve_add_clear_tickets_meta_box() {
    add_meta_box(
        've_clear_tickets_meta',
        'Danger zone: Clear all tickets',
        've_clear_tickets_meta_box_html',
        've_event',
        'side',
        'low'
    );
}

/**
 * Meta box UI — type exact event title (GitHub-style) then submit via admin-post
 * (separate form so it is not nested inside the post editor form).
 */
function ve_clear_tickets_meta_box_html($post) {
    if (!current_user_can('manage_options')) {
        echo '<p>' . esc_html__('You do not have permission to clear tickets.', 'venture-events') . '</p>';
        return;
    }

    $event_id = (int) $post->ID;
    if ($event_id < 1) {
        echo '<p class="description">' . esc_html__('Save the event first before clearing tickets.', 'venture-events') . '</p>';
        return;
    }

    $title = (string) $post->post_title;
    $count = function_exists('ve_count_event_registrations')
        ? ve_count_event_registrations($event_id)
        : 0;

    $action_url = admin_url('admin-post.php');
    $nonce      = wp_create_nonce('ve_clear_event_tickets_' . $event_id);
    ?>
    <div class="ve-danger-zone" style="border:1px solid #b32d2e;border-radius:2px;padding:10px;background:#fcf0f1;">
        <p style="margin:0 0 8px;color:#1d2327;">
            <strong>Permanently delete</strong> all registration rows for this event
            (paid, pending, complimentary, free included people, and special packages).
            Guest list and special list will empty. QR images for those tickets are removed.
        </p>
        <p style="margin:0 0 8px;color:#646970;font-size:12px;">
            Does <strong>not</strong> delete Zoho invoices, payment gateway records, or this event’s tier settings.
            This cannot be undone.
        </p>
        <p style="margin:0 0 10px;">
            <strong>Registrations on this event:</strong>
            <span id="ve-clear-tickets-count"><?php echo (int) $count; ?></span>
        </p>

        <?php if ($title === ''): ?>
            <p class="description" style="margin:0;color:#b32d2e;">
                Set and save an event title first. You must type the exact title to confirm.
            </p>
        <?php elseif ($count < 1): ?>
            <p class="description" style="margin:0;">There are no tickets to clear.</p>
        <?php else: ?>
            <div id="ve-clear-tickets-ui"
                 data-action-url="<?php echo esc_url($action_url); ?>"
                 data-event-id="<?php echo (int) $event_id; ?>"
                 data-nonce="<?php echo esc_attr($nonce); ?>"
                 data-required-title="<?php echo esc_attr($title); ?>">
                <p style="margin:0 0 6px;font-size:12px;">
                    Type <strong style="user-select:all;"><?php echo esc_html($title); ?></strong> to confirm.
                </p>
                <p style="margin:0 0 8px;">
                    <label for="ve-clear-tickets-confirm" class="screen-reader-text">Confirm event name</label>
                    <input type="text" id="ve-clear-tickets-confirm" class="widefat"
                           autocomplete="off" spellcheck="false" autocorrect="off"
                           autocapitalize="off" placeholder="Type the event name">
                </p>
                <p style="margin:0;">
                    <button type="button" id="ve-clear-tickets-btn" class="button button-secondary"
                            style="color:#b32d2e;border-color:#b32d2e;" disabled>
                        Clear all tickets
                    </button>
                </p>
            </div>
            <?php
            // Inline script must not depend on a nested <form> (invalid inside #post and
            // browsers drop it, which previously aborted the whole enable/submit logic).
            ?>
            <script>
            (function () {
                var root = document.getElementById('ve-clear-tickets-ui');
                if (!root) return;
                var input = document.getElementById('ve-clear-tickets-confirm');
                var btn = document.getElementById('ve-clear-tickets-btn');
                if (!input || !btn) return;

                // Prefer dataset (auto-decodes HTML entities in attributes)
                var required = (root.dataset && root.dataset.requiredTitle != null)
                    ? String(root.dataset.requiredTitle)
                    : (root.getAttribute('data-required-title') || '');
                var actionUrl = (root.dataset && root.dataset.actionUrl) || root.getAttribute('data-action-url') || '';
                var eventId = (root.dataset && root.dataset.eventId) || root.getAttribute('data-event-id') || '';
                var nonce = (root.dataset && root.dataset.nonce) || root.getAttribute('data-nonce') || '';

                function titlesMatch(a, b) {
                    // Exact match first (GitHub-style); also allow trim if title has no edge spaces
                    if (a === b) return true;
                    if (a.trim() === b.trim() && a.trim() !== '') return true;
                    return false;
                }

                function sync() {
                    var ok = required !== '' && titlesMatch(input.value, required);
                    btn.disabled = !ok;
                    if (ok) {
                        btn.removeAttribute('aria-disabled');
                        btn.classList.remove('disabled');
                    }
                }

                input.addEventListener('input', sync);
                input.addEventListener('keyup', sync);
                input.addEventListener('change', sync);
                input.addEventListener('paste', function () { setTimeout(sync, 0); });
                sync();

                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (!titlesMatch(input.value, required) || !actionUrl) return;

                    var n = document.getElementById('ve-clear-tickets-count');
                    var msg = 'Permanently delete all ' +
                        (n ? n.textContent : '') +
                        ' ticket/registration row(s) for this event?\n\nThis cannot be undone.';
                    if (!window.confirm(msg)) return;

                    // Build form on body — never nest inside the post editor form
                    var form = document.createElement('form');
                    form.method = 'post';
                    form.action = actionUrl;
                    form.style.display = 'none';

                    function addField(name, value) {
                        var el = document.createElement('input');
                        el.type = 'hidden';
                        el.name = name;
                        el.value = value;
                        form.appendChild(el);
                    }
                    addField('action', 've_clear_event_tickets');
                    addField('event_id', eventId);
                    addField('ve_clear_tickets_nonce', nonce);
                    addField('confirm_title', input.value);

                    document.body.appendChild(form);
                    form.submit();
                });
            })();
            </script>
        <?php endif; ?>
    </div>
    <?php
}

add_action('admin_post_ve_clear_event_tickets', 've_handle_clear_event_tickets');
function ve_handle_clear_event_tickets() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to clear tickets.', 'venture-events'), 403);
    }

    $event_id = isset($_POST['event_id']) ? absint($_POST['event_id']) : 0;
    $nonce    = isset($_POST['ve_clear_tickets_nonce']) ? (string) wp_unslash($_POST['ve_clear_tickets_nonce']) : '';

    if ($event_id < 1 || !wp_verify_nonce($nonce, 've_clear_event_tickets_' . $event_id)) {
        wp_die(__('Invalid request (security check failed).', 'venture-events'), 403);
    }

    $post = get_post($event_id);
    if (!$post || $post->post_type !== 've_event') {
        wp_die(__('Event not found.', 'venture-events'), 404);
    }

    $expected = (string) $post->post_title;
    $typed    = isset($_POST['confirm_title']) ? (string) wp_unslash($_POST['confirm_title']) : '';
    // Exact match (GitHub-style), with trim so accidental edge spaces don't block
    $match = ($expected !== '' && ($typed === $expected || trim($typed) === trim($expected)));
    if (!$match) {
        set_transient(
            've_clear_tickets_notice_' . get_current_user_id(),
            [
                'type'    => 'error',
                'message' => 'Clear cancelled: event name did not match exactly. Nothing was deleted.',
            ],
            60
        );
        wp_safe_redirect(get_edit_post_link($event_id, 'raw'));
        exit;
    }

    if (!function_exists('ve_clear_event_registrations')) {
        wp_die(__('Clear function unavailable.', 'venture-events'), 500);
    }

    $result = ve_clear_event_registrations($event_id);

    if (!empty($result['error'])) {
        set_transient(
            've_clear_tickets_notice_' . get_current_user_id(),
            [
                'type'    => 'error',
                'message' => 'Could not clear tickets (error: ' . $result['error'] . '). Check debug.log.',
            ],
            60
        );
    } else {
        $n  = (int) $result['deleted'];
        $qr = (int) $result['qr_files_removed'];
        set_transient(
            've_clear_tickets_notice_' . get_current_user_id(),
            [
                'type'    => 'success',
                'message' => sprintf(
                    'Cleared %d registration(s) for “%s”. Removed %d QR image file(s).',
                    $n,
                    $expected,
                    $qr
                ),
            ],
            60
        );
    }

    wp_safe_redirect(get_edit_post_link($event_id, 'raw'));
    exit;
}

add_action('admin_notices', 've_clear_tickets_admin_notice');
function ve_clear_tickets_admin_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $key  = 've_clear_tickets_notice_' . get_current_user_id();
    $data = get_transient($key);
    if (!$data || !is_array($data)) {
        return;
    }
    delete_transient($key);

    $type = ($data['type'] ?? '') === 'success' ? 'success' : 'error';
    $msg  = (string) ($data['message'] ?? '');
    if ($msg === '') {
        return;
    }
    printf(
        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr($type),
        esc_html($msg)
    );
}

// ====================== GUEST LIST PAGE ======================
function ve_guest_list_page() {
    if (! current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }

    $event_id = isset($_REQUEST['event_id']) ? intval($_REQUEST['event_id']) : 0;

    $table = new VE_Guest_List_Table();
    $table->prepare_items($event_id);

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Guest Lists</h1>
        <p>People only (attendees and free included tickets). Packages appear under <strong>Special Lists</strong>.</p>

        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="ve-guest-list">
            <input type="hidden" name="post_type" value="ve_event">

            <select name="event_id" style="float:left; margin-right:8px;">
                <option value="0">— All Events —</option>
                <?php
                $events = get_posts([
                    'post_type'      => 've_event',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC'
                ]);
                foreach ($events as $event) {
                    $selected = ($event_id == $event->ID) ? ' selected' : '';
                    echo '<option value="' . esc_attr($event->ID) . '"' . $selected . '>'
                         . esc_html($event->post_title) . '</option>';
                }
                ?>
            </select>

            <button type="submit" class="button">Filter</button>

            <?php $table->search_box('Search by name or email', 'search'); ?>
        </form>

        <?php $table->display(); ?>
    </div>
    <?php
}

// ====================== SPECIAL PACKAGE LIST PAGE ======================
function ve_special_list_page() {
    if (! current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }

    $event_id = isset($_REQUEST['event_id']) ? intval($_REQUEST['event_id']) : 0;

    require_once __DIR__ . '/class-ve-special-list-table.php';
    $table = new VE_Special_List_Table();
    $table->prepare_items($event_id);

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Special Lists</h1>
        <p>Package purchases (tables, stands, etc.). Guest names are on the <strong>Guest List</strong>.</p>

        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="ve-special-list">
            <input type="hidden" name="post_type" value="ve_event">

            <select name="event_id" style="float:left; margin-right:8px;">
                <option value="0">— All Events —</option>
                <?php
                $events = get_posts([
                    'post_type'      => 've_event',
                    'posts_per_page' => -1,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]);
                foreach ($events as $event) {
                    $label = $event->post_title . ' - Special';
                    $selected = ($event_id == $event->ID) ? ' selected' : '';
                    echo '<option value="' . esc_attr($event->ID) . '"' . $selected . '>'
                         . esc_html($label) . '</option>';
                }
                ?>
            </select>

            <button type="submit" class="button">Filter</button>

            <?php $table->search_box('Search packages', 'search'); ?>
        </form>

        <?php $table->display(); ?>
    </div>
    <?php
}

// ====================== ZOHO SETTINGS PAGE ======================
add_action('admin_menu', 've_add_admin_menu');
function ve_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=ve_event',
        'Venture Events Settings',
        'Settings',
        'manage_options',
        've-settings',
        've_settings_page'
    );

    add_submenu_page(
        'edit.php?post_type=ve_event',
        'Guest List',
        'Guest List',
        'manage_options',
        've-guest-list',
        've_guest_list_page'
    );

    add_submenu_page(
        'edit.php?post_type=ve_event',
        'Special Lists',
        'Special Lists',
        'manage_options',
        've-special-list',
        've_special_list_page'
    );
}

function ve_settings_page() {
    if (isset($_POST['ve_zoho_settings'])) {
        update_option('ve_zoho_client_id', sanitize_text_field($_POST['client_id']));
        update_option('ve_zoho_client_secret', sanitize_text_field($_POST['client_secret']));
        update_option('ve_zoho_access_token', sanitize_text_field($_POST['access_token']));
        update_option('ve_zoho_refresh_token', sanitize_text_field($_POST['refresh_token']));
        update_option('ve_zoho_org_id', sanitize_text_field($_POST['org_id']));
        update_option('ve_zoho_currency', sanitize_text_field($_POST['currency'] ?: 'NAD'));
        update_option('ve_zoho_payment_mode', sanitize_text_field($_POST['payment_mode'] ?? 'banktransfer'));
        update_option('ve_zoho_api_base', esc_url_raw($_POST['api_base'] ?? 'https://www.zohoapis.com'));
        update_option('ve_zoho_accounts_base', esc_url_raw($_POST['accounts_base'] ?? 'https://accounts.zoho.com'));
        // Line item Account (⋯ → Show additional information → account dropdown, e.g. "Sales")
        update_option('ve_zoho_line_account_name', sanitize_text_field($_POST['line_account_name'] ?? ''));
        update_option('ve_zoho_line_account_id', sanitize_text_field($_POST['line_account_id'] ?? ''));
        update_option('ve_zoho_tax_id_domestic', sanitize_text_field($_POST['tax_id_domestic'] ?? ''));
        update_option('ve_zoho_tax_id_export', sanitize_text_field($_POST['tax_id_export'] ?? ''));
        update_option('ve_zoho_salesperson_id', sanitize_text_field($_POST['salesperson_id'] ?? ''));
        update_option('ve_ticket_mail_from', sanitize_email($_POST['ticket_mail_from'] ?? ''));
        update_option('ve_ticket_mail_from_name', sanitize_text_field($_POST['ticket_mail_from_name'] ?? ''));
        // Cleanup older/incorrect options
        delete_option('ve_zoho_journal_reference');
        delete_option('ve_zoho_deposit_account_id');
        delete_option('ve_zoho_report_tag_name');
        delete_option('ve_zoho_report_name');
        echo '<div class="notice notice-success"><p>Zoho settings saved.</p></div>';
    }

    $api_result = '';
    if (isset($_POST['ve_zoho_list_taxes']) && function_exists('ve_zoho_list_taxes')) {
        $check = ve_zoho_list_taxes();
        $api_result = implode("\n", $check['lines']);
        // One-click apply suggested 15% tax_id if requested
        if (!empty($_POST['ve_zoho_apply_suggested_tax']) && !empty($check['suggest_domestic'])) {
            update_option('ve_zoho_tax_id_domestic', sanitize_text_field($check['suggest_domestic']));
            $api_result .= "\n\nSaved Domestic tax_id = " . $check['suggest_domestic'];
            echo '<div class="notice notice-success"><p>Domestic tax_id saved from Zoho 15% tax.</p></div>';
        }
    } elseif (isset($_POST['ve_zoho_self_check']) && function_exists('ve_zoho_permission_self_check')) {
        $check = ve_zoho_permission_self_check();
        $api_result = implode("\n", $check['lines']);
    } elseif (isset($_POST['ve_zoho_api_test'])) {
        $endpoint = sanitize_text_field(wp_unslash($_POST['api_endpoint'] ?? ''));
        $token    = function_exists('ve_get_zoho_token') ? ve_get_zoho_token() : get_option('ve_zoho_access_token');
        $org_id   = get_option('ve_zoho_org_id');
        $api_base = function_exists('ve_zoho_api_base') ? ve_zoho_api_base() : 'https://www.zohoapis.com';

        if ($token && $org_id && $endpoint) {
            if ($endpoint[0] !== '/') {
                $endpoint = '/' . $endpoint;
            }
            $url = $api_base . '/books/v3' . $endpoint;
            if (strpos($endpoint, 'organization_id=') === false) {
                $url = add_query_arg('organization_id', $org_id, $url);
            }

            $response = wp_remote_get($url, [
                'timeout' => 45,
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
            ]);

            if (is_wp_error($response)) {
                $api_result = 'Error: ' . $response->get_error_message();
            } else {
                $http = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $api_result = "URL: {$url}\nHTTP {$http}\n{$body}";

                $decoded = json_decode($body, true);
                if (($http === 401 || (isset($decoded['code']) && (int) $decoded['code'] === 57))) {
                    $api_result .= "\n\n---\n"
                        . "Zoho code 57 / HTTP 401 = not authorized for this module.\n"
                        . "Your OAuth refresh token was created without the required scopes (or wrong org / data centre).\n"
                        . "Re-authorize with scope: " . (function_exists('ve_zoho_required_scopes') ? ve_zoho_required_scopes() : 'ZohoBooks.fullaccess.all offline_access') . "\n"
                        . "Then paste the new refresh token above and run “Permission self-check”.";
                }
            }
        } else {
            $api_result = 'Error: Missing token, organization ID, or endpoint. Token refresh result: '
                . ($token ? 'got token' : 'FAILED — check refresh token / client credentials');
        }
    }

    $scopes  = function_exists('ve_zoho_required_scopes') ? ve_zoho_required_scopes() : 'ZohoBooks.fullaccess.all';
    $minimal = function_exists('ve_zoho_minimal_scopes') ? ve_zoho_minimal_scopes() : '';
    $help    = function_exists('ve_zoho_oauth_help_text') ? ve_zoho_oauth_help_text() : null;

    ?>
    <div class="wrap">
        <h1>Venture Events – Zoho Books Settings</h1>

        <div class="notice notice-warning" style="padding:12px;">
            <p>Open <a href="https://api-console.zoho.com/" target="_blank" rel="noopener">Zoho API Console</a> to generate your tokens.</p>
            <p>Perform token exchange via terminal:</p>
            <pre style="background:#1d1e22;color:#fff;padding:10px;overflow:auto;font-size:12px;border-radius:5px;max-width:500px;">curl -X POST '<?php echo esc_html(function_exists('ve_zoho_accounts_base') ? ve_zoho_accounts_base() : 'https://accounts.zoho.com'); ?>/oauth/v2/token' \
  -d 'grant_type=authorization_code' \
  -d 'client_id=YOUR_CLIENT_ID' \
  -d 'client_secret=YOUR_CLIENT_SECRET' \
  -d 'redirect_uri=YOUR_REDIRECT_URI' \
  -d 'code=PASTE_GRANT_CODE'</pre>
        </div>

        <form method="post">
            <table class="form-table">
                <tr><th>Zoho Client ID</th><td><input type="text" name="client_id" value="<?php echo esc_attr(get_option('ve_zoho_client_id')); ?>" size="50"></td></tr>
                <tr><th>Zoho Client Secret</th><td><input type="password" name="client_secret" value="<?php echo esc_attr(get_option('ve_zoho_client_secret')); ?>" size="50"></td></tr>
                <tr><th>Access Token</th><td><input type="text" name="access_token" value="<?php echo esc_attr(get_option('ve_zoho_access_token')); ?>" size="80"><p class="description">Optional if refresh token works — refreshed automatically on each request.</p></td></tr>
                <tr><th>Refresh Token</th><td><input type="text" name="refresh_token" value="<?php echo esc_attr(get_option('ve_zoho_refresh_token')); ?>" size="80"></td></tr>
                <tr><th>Organization ID</th><td><input type="text" name="org_id" value="<?php echo esc_attr(get_option('ve_zoho_org_id')); ?>" size="30"></td></tr>
                <tr><th>Currency Code</th><td><input type="text" name="currency" value="<?php echo esc_attr(get_option('ve_zoho_currency', 'NAD')); ?>" size="10"></td></tr>
                <tr>
                    <th>API base URL</th>
                    <td>
                        <input type="text" name="api_base" value="<?php echo esc_attr(get_option('ve_zoho_api_base', 'https://www.zohoapis.com')); ?>" size="50">
                    </td>
                </tr>
                <tr>
                    <th>Accounts base URL</th>
                    <td>
                        <input type="text" name="accounts_base" value="<?php echo esc_attr(get_option('ve_zoho_accounts_base', 'https://accounts.zoho.com')); ?>" size="50">
                    </td>
                </tr>
                <tr>
                    <th>Payment Mode</th>
                    <td>
                        <select name="payment_mode">
                            <?php
                            $mode = get_option('ve_zoho_payment_mode', 'banktransfer');
                            $modes = [
                                'banktransfer' => 'Bank Transfer',
                                'creditcard'   => 'Credit Card',
                                'cash'         => 'Cash',
                                'others'       => 'Others',
                            ];
                            foreach ($modes as $value => $label) {
                                printf(
                                    '<option value="%s"%s>%s</option>',
                                    esc_attr($value),
                                    selected($mode, $value, false),
                                    esc_html($label)
                                );
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Line item account</th>
                    <td>
                        <input type="text" name="line_account_name" value="<?php echo esc_attr(get_option('ve_zoho_line_account_name', '')); ?>" size="40" placeholder="e.g. Sales">
                        <p style="margin-top:8px;">
                            <label>Or paste Account ID (optional override):</label><br>
                            <input type="text" name="line_account_id" value="<?php echo esc_attr(get_option('ve_zoho_line_account_id', '')); ?>" size="40" placeholder="Zoho account_id">
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Tax IDs</th>
                    <td>
                        <label>Domestic override (optional):</label><br>
                        <input type="text" name="tax_id_domestic" value="<?php echo esc_attr(get_option('ve_zoho_tax_id_domestic', '')); ?>" size="40" placeholder="2737296000000102001"><br>
                        <label style="margin-top:6px;display:inline-block;">Export override (optional):</label><br>
                        <input type="text" name="tax_id_export" value="<?php echo esc_attr(get_option('ve_zoho_tax_id_export', '')); ?>" size="40" placeholder="2737296000000102009">
                    </td>
                </tr>
                <tr>
                    <th>Salesperson ID (optional)</th>
                    <td>
                        <input type="text" name="salesperson_id" value="<?php echo esc_attr(get_option('ve_zoho_salesperson_id', '')); ?>" size="40" placeholder="Zoho salesperson_id">
                        <p class="description">Leave blank to omit salesperson on invoices.</p>
                    </td>
                </tr>
                <tr>
                    <th>Ticket email From</th>
                    <td>
                        <input type="email" name="ticket_mail_from" value="<?php echo esc_attr(get_option('ve_ticket_mail_from', '')); ?>" size="40" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                        <p class="description">Optional. Defaults to the WordPress admin email.</p>
                        <label style="margin-top:6px;display:inline-block;">From name:</label><br>
                        <input type="text" name="ticket_mail_from_name" value="<?php echo esc_attr(get_option('ve_ticket_mail_from_name', '')); ?>" size="40" placeholder="<?php echo esc_attr(get_bloginfo('name') . ' Tickets'); ?>">
                    </td>
                </tr>
            </table>
            <button type="submit" name="ve_zoho_settings" class="button button-primary">Save Zoho Settings</button>
        </form>

        <hr>
        <h2>List taxes (optional)</h2>
        <p>
            Plugin defaults (original hardcodes): Domestic <code>2737296000000102001</code>, Export <code>2737296000000102009</code>.
            Use this only if you need to verify or override IDs against Zoho.
        </p>
        <p>
            Effective Domestic: <code><?php echo esc_html(function_exists('ve_zoho_resolve_tax_id') ? ve_zoho_resolve_tax_id(true) : '2737296000000102001'); ?></code>
            · Export: <code><?php echo esc_html(function_exists('ve_zoho_resolve_tax_id') ? ve_zoho_resolve_tax_id(false) : '2737296000000102009'); ?></code>
        </p>
        <form method="post" style="margin-bottom:12px;">
            <button type="submit" name="ve_zoho_list_taxes" class="button button-secondary">List taxes from Zoho</button>
        </form>

        <hr>
        <h2>Permission self-check</h2>
        <p>Probes Contacts, Invoices, Payments, Bank accounts, and Chart of accounts with a refreshed token.</p>
        <form method="post">
            <button type="submit" name="ve_zoho_self_check" class="button button-secondary">Run permission self-check</button>
        </form>

        <hr>
        <h2>Zoho API Tester</h2>
        <p class="description">GET helper. Try <code>/contacts?per_page=1</code> first (should work if invoices already work).</p>
        <form method="post">
            <p><label><strong>Endpoint (after /books/v3)</strong></label><br>
            <input type="text" name="api_endpoint" value="/contacts?per_page=1" style="width:600px;"></p>
            <button type="submit" name="ve_zoho_api_test" class="button button-secondary">Run GET Request</button>
        </form>

        <?php if ($api_result): ?>
        <h3>Response:</h3>
        <pre style="background:#f1f1f1;padding:15px;overflow:auto;max-height:480px;white-space:pre-wrap;"><?php echo esc_html($api_result); ?></pre>
        <?php endif; ?>
    </div>
    <?php
}