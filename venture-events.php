<?php
/**
 * Plugin Name:       Venture Events
 * Plugin URI:        https://github.com/venture-media/venture-events
 * Description:       Event registration with flexible payment gateways + Zoho Books invoicing + QR tickets.
 * Version:           0.9.27.0
 * Author:            Leon de Klerk
 * Author URI:        https://github.com/Leon2332
 * License:           MIT
 * License URI:       https://github.com/venture-media/venture-events/blob/main/LICENSE
 */

if (!defined('ABSPATH')) exit;

define('VE_VERSION', '0.9.27.0');
define('VE_PATH', plugin_dir_path(__FILE__));
define('VE_URL', plugin_dir_url(__FILE__));

// Core includes
require_once VE_PATH . 'includes/ve-functions.php';
require_once VE_PATH . 'includes/ve-admin.php';
require_once VE_PATH . 'includes/ve-qrcode.php';
require_once VE_PATH . 'includes/ve-zoho.php';
require_once VE_PATH . 'includes/class-ve-gateway-manager.php';
require_once VE_PATH . 'includes/class-ve-guest-list-table.php';
require_once VE_PATH . 'includes/class-ve-special-list-table.php';

// Activation Hook
register_activation_hook(__FILE__, 've_activate');
function ve_activate() {
    ve_create_roles();      // Creates 'event_gate' role
    ve_create_tables();     // Creates ve_registrations table (+ column upgrades)
    ve_add_rewrite_rules();
    flush_rewrite_rules();
    update_option('ve_rewrite_version', VE_VERSION, false);
    if (function_exists('ve_schedule_pending_cleanup')) {
        ve_schedule_pending_cleanup();
    }
}

// Ensure schema upgrades run after plugin file updates (not only on activate)
add_action('plugins_loaded', function () {
    if (get_option('ve_db_version') === VE_VERSION) {
        return;
    }
    if (function_exists('ve_create_tables')) {
        ve_create_tables();
    }
    update_option('ve_db_version', VE_VERSION, false);
}, 5);

// Ensure cron is registered even when the plugin was updated without re-activate
add_action('init', function () {
    if (function_exists('ve_schedule_pending_cleanup')) {
        ve_schedule_pending_cleanup();
    }
}, 30);

// Deactivation (optional - keeps all data; clears scheduled cleanup)
register_deactivation_hook(__FILE__, 've_deactivate');
function ve_deactivate() {
    if (function_exists('ve_unschedule_pending_cleanup')) {
        ve_unschedule_pending_cleanup();
    }
    flush_rewrite_rules();
}

/**
 * Flush rewrite rules once after deploy / version bump (covers live sites
 * where the plugin was updated without deactivation → reactivation).
 */
add_action('init', 've_maybe_flush_rewrite_rules', 20);
function ve_maybe_flush_rewrite_rules() {
    if (get_option('ve_rewrite_version') === VE_VERSION) {
        return;
    }
    ve_add_rewrite_rules();
    flush_rewrite_rules(false);
    update_option('ve_rewrite_version', VE_VERSION, false);
    error_log('Venture Events: Flushed rewrite rules for version ' . VE_VERSION);
}

// Register Event CPT
add_action('init', 've_register_cpt');
function ve_register_cpt() {
    register_post_type('ve_event', [
        'labels' => [
            'name'               => 'Events',
            'singular_name'      => 'Event',
            'add_new'            => 'Add Event',
            'add_new_item'       => 'Add Event',
            'edit_item'          => 'Edit Event',
            'new_item'           => 'New Event',
            'view_item'          => 'View Event',
            'search_items'       => 'Search Events',
            'not_found'          => 'No events found',
            'not_found_in_trash' => 'No events found in Trash',
            'all_items'          => 'All Events',
            // Top-level admin menu only — avoid clash with child-theme "Events" CPT (post_type=events)
            'menu_name'          => 'Tickets',
            'name_admin_bar'     => 'Event',
        ],
        'public'       => true,
        'has_archive'  => true,
        'supports'     => ['title', 'editor', 'thumbnail'],
        'menu_icon'    => 'dashicons-tickets-alt',
    ]);
}

// Shortcode for registration form
// Normal:  [venture_registration event_id="123"]
// Special: [venture_registration event_id="S123"]
// Results: [venture_registration results_id="123"]
add_shortcode('venture_registration', 've_registration_form_shortcode');
function ve_registration_form_shortcode($atts) {
    $atts = shortcode_atts([
        'event_id'   => '',
        'results_id' => '',
    ], $atts, 'venture_registration');

    $results_id = absint($atts['results_id']);
    if ($results_id) {
        return ve_registration_results_shortcode($results_id);
    }

    $parsed   = ve_parse_registration_event_attr($atts['event_id']);
    $event_id = (int) $parsed['event_id'];
    $mode     = $parsed['mode'];

    // Ensure CSS/JS load even when the shortcode lives in a page builder (not post_content).
    ve_enqueue_registration_assets(true);

    if (!$event_id) {
        return '<p class="ve-error">Error: Please provide event_id in the shortcode, e.g. [venture_registration event_id="123"] or [venture_registration event_id="S123"] for special packages.</p>';
    }

    if (get_post_type($event_id) !== 've_event') {
        return '<p class="ve-error">Error: Event not found.</p>';
    }

    if ($mode === 'special') {
        $special = ve_get_special_tiers($event_id);
        if (empty($special)) {
            return '<p class="ve-error">Error: This event has no special ticket tiers configured.</p>';
        }
    }

    ob_start();
    $template_path = ($mode === 'special')
        ? VE_PATH . 'templates/registration-form-special.php'
        : VE_PATH . 'templates/registration-form.php';

    // Make mode available to templates if needed
    $ve_registration_mode = $mode;

    if (file_exists($template_path)) {
        include $template_path;
    } else {
        echo '<p class="ve-error">Error: Registration form template not found at ' . esc_html($template_path) . '</p>';
    }

    return ob_get_clean();
}

/**
 * Purchase results charts: [venture_registration results_id="123"]
 *
 * @param int $event_id
 * @return string
 */
function ve_registration_results_shortcode($event_id) {
    $event_id = (int) $event_id;

    ve_enqueue_results_assets(true);

    if (!$event_id) {
        return '<p class="ve-error">Error: Provide results_id, e.g. [venture_registration results_id="123"].</p>';
    }

    if (get_post_type($event_id) !== 've_event') {
        return '<p class="ve-error">Error: Event not found.</p>';
    }

    $ve_results = function_exists('ve_get_event_results_chart_data')
        ? ve_get_event_results_chart_data($event_id)
        : [];

    static $instance = 0;
    $instance++;
    $ve_results_uid = 've-results-' . $instance;

    ob_start();
    $template = VE_PATH . 'templates/registration-results.php';
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<p class="ve-error">Error: Results template missing.</p>';
    }
    return ob_get_clean();
}

/**
 * Chart.js + results CSS/JS for the results shortcode.
 *
 * @param bool $force
 */
function ve_enqueue_results_assets($force = false) {
    static $done = false;

    $content = is_singular() ? (get_post()->post_content ?? '') : '';
    $should_load = $force
        || ($content !== ''
            && has_shortcode($content, 'venture_registration')
            && preg_match('/\[venture_registration[^\]]*results_id\s*=/i', $content));

    if (!$should_load) {
        return;
    }

    if (!$done) {
        wp_register_style(
            'venture-events-frontend',
            VE_URL . 'assets/frontend.css',
            [],
            VE_VERSION
        );

        wp_register_script(
            'chartjs',
            VE_URL . 'assets/chart.umd.min.js',
            [],
            '4.4.9',
            true
        );

        wp_register_script(
            'venture-events-results',
            VE_URL . 'assets/results.js',
            ['chartjs'],
            VE_VERSION,
            true
        );

        $done = true;
    }

    wp_enqueue_style('venture-events-frontend');
    wp_enqueue_script('chartjs');
    wp_enqueue_script('venture-events-results');

    if (did_action('wp_enqueue_scripts') && !doing_action('wp_enqueue_scripts')) {
        if (wp_style_is('venture-events-frontend', 'enqueued') && !wp_style_is('venture-events-frontend', 'done')) {
            wp_print_styles('venture-events-frontend');
        }
    }
}
add_action('wp_enqueue_scripts', 've_enqueue_results_assets');

// Complimentary tickets (admin only): [venture_complimentary event_id="123"]
add_shortcode('venture_complimentary', 've_complimentary_form_shortcode');
function ve_complimentary_form_shortcode($atts) {
    $atts     = shortcode_atts(['event_id' => ''], $atts, 'venture_complimentary');
    $event_id = absint($atts['event_id']);

    ve_enqueue_registration_assets(true);

    if (!$event_id) {
        return '<p class="ve-error">Error: Provide event_id, e.g. [venture_complimentary event_id="123"].</p>';
    }

    if (get_post_type($event_id) !== 've_event') {
        return '<p class="ve-error">Error: Event not found.</p>';
    }

    // Ensure shortcode att is available to the template as $atts
    $atts = ['event_id' => (string) $event_id];

    ob_start();
    $template = VE_PATH . 'templates/registration-form-complimentary.php';
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<p class="ve-error">Error: Complimentary form template missing.</p>';
    }
    return ob_get_clean();
}

// EFT checkout (no gateway): [venture_eft event_id="123"] / [venture_eft event_id="S123"]
add_shortcode('venture_eft', 've_eft_form_shortcode');
function ve_eft_form_shortcode($atts) {
    $atts = shortcode_atts(['event_id' => ''], $atts, 'venture_eft');
    $parsed   = ve_parse_registration_event_attr($atts['event_id']);
    $event_id = (int) $parsed['event_id'];
    $mode     = $parsed['mode'];

    ve_enqueue_registration_assets(true);

    if (!$event_id) {
        return '<p class="ve-error">Error: Please provide event_id in the shortcode, e.g. [venture_eft event_id="123"] or [venture_eft event_id="S123"] for special packages.</p>';
    }

    if (get_post_type($event_id) !== 've_event') {
        return '<p class="ve-error">Error: Event not found.</p>';
    }

    if ($mode === 'special') {
        $special = ve_get_special_tiers($event_id);
        if (empty($special)) {
            return '<p class="ve-error">Error: This event has no special ticket tiers configured.</p>';
        }
    }

    ob_start();
    $template_path = ($mode === 'special')
        ? VE_PATH . 'templates/registration-form-special.php'
        : VE_PATH . 'templates/registration-form.php';

    $ve_registration_mode = $mode;
    $ve_payment_mode      = 'eft';
    $atts                 = ['event_id' => (string) $event_id];

    if (file_exists($template_path)) {
        include $template_path;
    } else {
        echo '<p class="ve-error">Error: Registration form template not found at ' . esc_html($template_path) . '</p>';
    }

    return ob_get_clean();
}

// Gate scanner shortcode (per event): [venture_gate_scan event_id="123"]
add_shortcode('venture_gate_scan', 've_gate_scan_shortcode');
function ve_gate_scan_shortcode($atts) {
    $atts     = shortcode_atts(['event_id' => ''], $atts, 'venture_gate_scan');
    $event_id = absint($atts['event_id']);

    ve_enqueue_gate_scan_assets(true);

    if (!$event_id) {
        return '<p class="ve-error">Error: Provide event_id, e.g. [venture_gate_scan event_id="123"].</p>';
    }

    if (get_post_type($event_id) !== 've_event') {
        return '<p class="ve-error">Error: Event not found.</p>';
    }

    $event_title = get_the_title($event_id);
    $can_scan    = function_exists('ve_user_can_gate_scan') && ve_user_can_gate_scan();
    $logged_in   = is_user_logged_in();

    ob_start();
    $template = VE_PATH . 'templates/gate-scan.php';
    if (file_exists($template)) {
        include $template;
    } else {
        echo '<p class="ve-error">Error: Gate scan template missing.</p>';
    }
    return ob_get_clean();
}

/**
 * Register + enqueue assets for the gate scanner shortcode.
 *
 * @param bool $force
 */
function ve_enqueue_gate_scan_assets($force = false) {
    static $done = false;

    $should_load = $force
        || (is_singular() && has_shortcode(get_post()->post_content ?? '', 'venture_gate_scan'));

    if (!$should_load) {
        return;
    }

    if (!$done) {
        wp_register_style(
            'venture-events-gate-scan',
            VE_URL . 'assets/gate-scan.css',
            [],
            VE_VERSION
        );

        wp_register_script(
            'html5-qrcode',
            VE_URL . 'assets/html5-qrcode.min.js',
            [],
            '2.3.8',
            true
        );

        wp_register_script(
            'venture-events-gate-scan',
            VE_URL . 'assets/gate-scan.js',
            ['html5-qrcode'],
            VE_VERSION,
            true
        );

        $done = true;
    }

    wp_enqueue_style('venture-events-gate-scan');
    wp_enqueue_script('html5-qrcode');
    wp_enqueue_script('venture-events-gate-scan');

    // Shortcodes may run after wp_head; print CSS immediately when needed.
    if (did_action('wp_enqueue_scripts') && !doing_action('wp_enqueue_scripts')) {
        if (wp_style_is('venture-events-gate-scan', 'enqueued') && !wp_style_is('venture-events-gate-scan', 'done')) {
            wp_print_styles('venture-events-gate-scan');
        }
    }
}
add_action('wp_enqueue_scripts', 've_enqueue_gate_scan_assets');

// ====================== GATEWAY SYSTEM ======================

// Initialize the Gateway Manager
add_action('init', 've_init_gateway_manager');
function ve_init_gateway_manager() {
    if (class_exists('VE_Gateway_Manager')) {
        VE_Gateway_Manager::get_instance();
    }
}

// Listen for successful payment from ANY gateway plugin
add_action('ve_gateway_payment_success', 've_process_payment_success');


// ====================== ADMIN MENU ======================
add_action('admin_menu', 've_add_admin_menu');

// Pretty URL: /read-qr/?id=…&token=…
add_action('init', 've_add_rewrite_rules', 5);
function ve_add_rewrite_rules() {
    add_rewrite_rule('^read-qr/?$', 'index.php?ve_page=read-qr', 'top');
}
add_filter('query_vars', function ($vars) {
    $vars[] = 've_page';
    return $vars;
});

/**
 * Whether the current request is the ticket / QR reader page.
 * Uses rewrite query var, explicit ?ve_page=, or raw path fallback so we
 * still work if rewrite rules were never flushed on the live site.
 */
function ve_is_read_qr_request() {
    if (get_query_var('ve_page') === 'read-qr') {
        return true;
    }
    if (isset($_GET['ve_page']) && sanitize_text_field(wp_unslash($_GET['ve_page'])) === 'read-qr') {
        return true;
    }

    $request_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($request_path) || $request_path === '') {
        return false;
    }

    $home_path = parse_url(home_url('/'), PHP_URL_PATH);
    $home_path = is_string($home_path) ? untrailingslashit($home_path) : '';
    if ($home_path !== '' && strpos($request_path, $home_path) === 0) {
        $request_path = substr($request_path, strlen($home_path));
    }

    $request_path = '/' . trim($request_path, '/');
    return ($request_path === '/read-qr');
}

// Priority 0: run before theme 404 handling when rewrites are missing
add_action('template_redirect', 've_read_qr_template', 0);
function ve_read_qr_template() {
    if (!ve_is_read_qr_request()) {
        return;
    }

    // Avoid theme "page not found" chrome when rewrite vars were not registered
    status_header(200);
    nocache_headers();

    $template = VE_PATH . 'templates/read-qr.php';
    if (file_exists($template)) {
        include $template;
        exit;
    }

    wp_die('Ticket page template missing.', 'Ticket', ['response' => 500]);
}

/**
 * Register + enqueue frontend assets for the registration form.
 * Called from wp_enqueue_scripts and from the shortcode (page builders).
 */
function ve_enqueue_registration_assets($force = false) {
    static $done = false;

    $content = is_singular() ? (get_post()->post_content ?? '') : '';
    $should_load = $force
        || ($content !== '' && (
            (has_shortcode($content, 'venture_registration')
                && preg_match('/\[venture_registration[^\]]*event_id\s*=/i', $content))
            || has_shortcode($content, 'venture_complimentary')
            || has_shortcode($content, 'venture_eft')
        ));

    if (!$should_load) {
        return;
    }

    if (!$done) {
        // Dashicons are admin-only by default; load them so form button icons work on the front end.
        wp_enqueue_style('dashicons');

        wp_register_style(
            'venture-events-frontend',
            VE_URL . 'assets/frontend.css',
            ['dashicons'],
            VE_VERSION
        );

        wp_register_script(
            'venture-events-frontend',
            VE_URL . 'assets/frontend.js',
            ['jquery'],
            VE_VERSION,
            true
        );

        wp_localize_script('venture-events-frontend', 'veGateway', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ve_registration_nonce'),
        ]);

        wp_localize_script('venture-events-frontend', 'veComplimentary', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ve_complimentary_nonce'),
        ]);

        wp_localize_script('venture-events-frontend', 'veEft', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('ve_eft_nonce'),
        ]);

        $done = true;
    }

    wp_enqueue_style('venture-events-frontend');
    wp_enqueue_script('venture-events-frontend');

    // Shortcodes often run after wp_head; print CSS immediately so styles still apply.
    if (did_action('wp_enqueue_scripts') && !doing_action('wp_enqueue_scripts')) {
        if (wp_style_is('dashicons', 'enqueued') && !wp_style_is('dashicons', 'done')) {
            wp_print_styles('dashicons');
        }
        if (wp_style_is('venture-events-frontend', 'enqueued') && !wp_style_is('venture-events-frontend', 'done')) {
            wp_print_styles('venture-events-frontend');
        }
    }
}
add_action('wp_enqueue_scripts', 've_enqueue_registration_assets');

// === Trigger gateway after AJAX save ===
// Renders a dedicated payment page (no registration form) so only the gateway UI shows.
add_action('template_redirect', 've_handle_payment_start');
function ve_handle_payment_start() {
    if (!isset($_GET['ve_payment']) || $_GET['ve_payment'] !== 'start' || empty($_GET['ref'])) {
        return;
    }

    $payment_reference = sanitize_text_field(wp_unslash($_GET['ref']));

    error_log("Venture Events: Payment start triggered for ref={$payment_reference}");

    if (!class_exists('VE_Gateway_Manager')) {
        return;
    }

    $manager = VE_Gateway_Manager::get_instance();
    do_action('ve_register_gateways', $manager);

    global $wpdb;
    $event_id = $wpdb->get_var($wpdb->prepare(
        "SELECT event_id FROM {$wpdb->prefix}ve_registrations WHERE payment_reference = %s LIMIT 1",
        $payment_reference
    ));

    if (!$event_id) {
        error_log("Venture Events: Could not find event_id for payment ref={$payment_reference}");
        return;
    }

    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(price) FROM {$wpdb->prefix}ve_registrations WHERE payment_reference = %s",
        $payment_reference
    )) ?: 0;

    $total_amount = (float) $total;

    // Capture gateway markup (gateways echo their UI on this action).
    ob_start();
    $manager->initiate_payment($payment_reference, $event_id, $total_amount);
    $gateway_html = ob_get_clean();

    status_header(200);
    nocache_headers();

    $template = VE_PATH . 'templates/payment-start.php';
    if (file_exists($template)) {
        include $template;
        exit;
    }

    // Fallback if template is missing: show gateway UI only.
    echo $gateway_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit;
}

/**
 * Show a dedicated confirmation page after gateway return.
 * Gateways redirect to: /?payment=success|failed&ref=VE-...
 */
add_action('template_redirect', 've_handle_payment_result_page', 5);
function ve_handle_payment_result_page() {
    if (empty($_GET['payment']) || empty($_GET['ref'])) {
        return;
    }

    $status = sanitize_text_field(wp_unslash($_GET['payment']));
    if (!in_array($status, ['success', 'failed'], true)) {
        return;
    }

    $payment_reference = sanitize_text_field(wp_unslash($_GET['ref']));
    $registrations     = function_exists('ve_get_registrations_by_reference')
        ? ve_get_registrations_by_reference($payment_reference)
        : [];

    // If success was claimed but rows are still pending, avoid a false "confirmed" feel
    // (processing may still be mid-flight on a slow request — rare).
    status_header(200);
    nocache_headers();

    $template = VE_PATH . 'templates/payment-result.php';
    if (file_exists($template)) {
        include $template;
        exit;
    }

    // Minimal fallback if template missing
    wp_die(
        $status === 'success'
            ? 'Payment successful. Reference: ' . esc_html($payment_reference)
            : 'Payment failed. Reference: ' . esc_html($payment_reference),
        $status === 'success' ? 'Payment successful' : 'Payment failed',
        ['response' => 200]
    );
}
