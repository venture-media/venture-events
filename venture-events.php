<?php
/**
 * Plugin Name:       Venture Events
 * Plugin URI:        https://github.com/venture-media/venture-events
 * Description:       Event registration with flexible payment gateways + Zoho Books invoicing + QR tickets.
 * Version:           0.9.12
 * Author:            Leon de Klerk
 * Author URI:        https://github.com/Leon2332
 * License:           MIT
 * License URI:       https://github.com/venture-media/venture-events/blob/main/LICENSE
 */

if (!defined('ABSPATH')) exit;

define('VE_VERSION', '0.9.12');
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

// Deactivation (optional - keeps all data)
register_deactivation_hook(__FILE__, 've_deactivate');
function ve_deactivate() {
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
add_shortcode('venture_registration', 've_registration_form_shortcode');
function ve_registration_form_shortcode($atts) {
    $atts = shortcode_atts(['event_id' => ''], $atts, 'venture_registration');
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

    $should_load = $force
        || (is_singular() && has_shortcode(get_post()->post_content ?? '', 'venture_registration'));

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
