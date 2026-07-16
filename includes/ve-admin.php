<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Admin Features
 * Version: 0.9.6
 */

// Load WP_List_Table safely (only when needed)
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
require_once __DIR__ . '/class-ve-guest-list-table.php';

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
    $shortcode = sprintf('[venture_registration event_id="%d"]', (int) $post_id);
    printf(
        '<code class="ve-event-shortcode" style="user-select:all;cursor:text;white-space:nowrap;">%s</code>',
        esc_html($shortcode)
    );
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



// ====================== GUEST LIST PAGE (your preferred layout) ======================
function ve_guest_list_page() {
    if (! current_user_can('manage_options')) {
        wp_die(__('Sorry, you are not allowed to access this page.'));
    }

    // Get selected event filter
    $event_id = isset($_REQUEST['event_id']) ? intval($_REQUEST['event_id']) : 0;

    $table = new VE_Guest_List_Table();
    $table->prepare_items($event_id);

    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Guest Lists</h1>
        <p>All registrations. Searchable and sortable.</p>

        <!-- Filter form (this was the broken part) -->
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
    if (isset($_POST['ve_zoho_self_check']) && function_exists('ve_zoho_permission_self_check')) {
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
                    <th>Tax IDs (optional)</th>
                    <td>
                        <p class="description">Org-specific Zoho tax_id values from Settings → Taxes. Leave blank to omit tax_id on line items.</p>
                        <label>Domestic (e.g. Namibia):</label><br>
                        <input type="text" name="tax_id_domestic" value="<?php echo esc_attr(get_option('ve_zoho_tax_id_domestic', '')); ?>" size="40" placeholder="Zoho tax_id"><br>
                        <label style="margin-top:6px;display:inline-block;">Export / non-domestic:</label><br>
                        <input type="text" name="tax_id_export" value="<?php echo esc_attr(get_option('ve_zoho_tax_id_export', '')); ?>" size="40" placeholder="Zoho tax_id">
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