<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Core Functions
 * Version: 0.9.22.0
 */

/** Hours a pending (unpaid) registration may sit before automatic deletion. */
if (!defined('VE_PENDING_TTL_HOURS')) {
    define('VE_PENDING_TTL_HOURS', 24);
}

// ====================== DATABASE SETUP ======================
function ve_create_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        event_id bigint(20) unsigned NOT NULL,
        payment_reference varchar(100) NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) DEFAULT NULL,
        organisation varchar(200) DEFAULT NULL,
        phone varchar(50) DEFAULT NULL,
        email varchar(100) NOT NULL,
        tier varchar(50) NOT NULL,
        tier_name varchar(100) DEFAULT NULL,
        price decimal(10,2) NOT NULL DEFAULT 0.00,
        line_type varchar(20) NOT NULL DEFAULT 'person',
        included_free tinyint(1) NOT NULL DEFAULT 0,
        special_tier_key varchar(50) DEFAULT NULL,
        status varchar(20) NOT NULL DEFAULT 'pending',
        transaction_id varchar(100) DEFAULT NULL,
        invoice_number varchar(50) DEFAULT NULL,
        paid_at datetime DEFAULT NULL,
        entered_at datetime DEFAULT NULL,
        entered_by bigint(20) unsigned DEFAULT NULL,
        qr_url varchar(255) DEFAULT NULL,
        billing_company varchar(200) DEFAULT NULL,
        billing_address text DEFAULT NULL,
        billing_country varchar(10) DEFAULT 'NA',
        accounting_email varchar(100) DEFAULT NULL,
        billing_notes text DEFAULT NULL,
        internal_reference varchar(100) DEFAULT NULL,
        sage_invoice varchar(100) DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY payment_reference (payment_reference),
        KEY event_id (event_id),
        KEY status (status),
        KEY invoice_number (invoice_number),
        KEY line_type (line_type)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Upgrade existing installations (adds missing columns without destroying data)
    $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    $needed = [
        'tier_name'          => "ALTER TABLE $table_name ADD COLUMN tier_name varchar(100) DEFAULT NULL AFTER tier",
        'line_type'          => "ALTER TABLE $table_name ADD COLUMN line_type varchar(20) NOT NULL DEFAULT 'person' AFTER price",
        'included_free'      => "ALTER TABLE $table_name ADD COLUMN included_free tinyint(1) NOT NULL DEFAULT 0 AFTER line_type",
        'special_tier_key'   => "ALTER TABLE $table_name ADD COLUMN special_tier_key varchar(50) DEFAULT NULL AFTER included_free",
        'transaction_id'     => "ALTER TABLE $table_name ADD COLUMN transaction_id varchar(100) DEFAULT NULL AFTER status",
        'invoice_number'     => "ALTER TABLE $table_name ADD COLUMN invoice_number varchar(50) DEFAULT NULL AFTER transaction_id",
        'paid_at'            => "ALTER TABLE $table_name ADD COLUMN paid_at datetime DEFAULT NULL AFTER invoice_number",
        'entered_at'         => "ALTER TABLE $table_name ADD COLUMN entered_at datetime DEFAULT NULL AFTER paid_at",
        'entered_by'         => "ALTER TABLE $table_name ADD COLUMN entered_by bigint(20) unsigned DEFAULT NULL AFTER entered_at",
        'qr_url'             => "ALTER TABLE $table_name ADD COLUMN qr_url varchar(255) DEFAULT NULL AFTER entered_by",
        'internal_reference' => "ALTER TABLE $table_name ADD COLUMN internal_reference varchar(100) DEFAULT NULL AFTER qr_url",
        'sage_invoice'       => "ALTER TABLE $table_name ADD COLUMN sage_invoice varchar(100) DEFAULT NULL AFTER internal_reference",
    ];

    foreach ($needed as $col => $alter_sql) {
        if (!in_array($col, $existing_columns, true)) {
            $wpdb->query($alter_sql);
            error_log("Venture Events: Added missing column '$col' to $table_name");
        }
    }

    // Ensure legacy rows (pre line_type) are treated as people
    $wpdb->query(
        "UPDATE $table_name SET line_type = 'person' WHERE line_type IS NULL OR line_type = ''"
    );

    error_log("Venture Events: Table $table_name verified/updated successfully");
}

/**
 * Parse shortcode event_id attribute.
 *
 * "123"  → normal form for post 123
 * "S123" → special package form for post 123
 *
 * @param mixed $raw
 * @return array{event_id:int,mode:string,raw:string}
 */
function ve_parse_registration_event_attr($raw) {
    $raw = trim((string) $raw);
    if ($raw !== '' && preg_match('/^[Ss](\d+)$/', $raw, $m)) {
        return [
            'event_id' => (int) $m[1],
            'mode'     => 'special',
            'raw'      => $raw,
        ];
    }

    return [
        'event_id' => absint($raw),
        'mode'     => 'normal',
        'raw'      => $raw,
    ];
}

/**
 * Statuses that grant a valid ticket (gate + QR page).
 *
 * paid  = card / complimentary
 * eft   = EFT order (invoice outstanding; tickets already issued)
 *
 * @param string $status
 * @return bool
 */
function ve_registration_status_allows_entry($status) {
    return in_array((string) $status, ['paid', 'eft'], true);
}

/**
 * Guest/special list label. EFT stays all-caps (ucfirst would show "Eft").
 *
 * @param string $status
 * @return string
 */
function ve_registration_status_label($status) {
    $status = (string) $status;
    if ($status === 'eft') {
        return 'EFT';
    }
    return $status !== '' ? ucfirst($status) : 'Pending';
}

/**
 * Guest/special list colour: paid green, EFT blue, else orange (pending).
 *
 * @param string $status
 * @return string
 */
function ve_registration_status_color($status) {
    $status = (string) $status;
    if ($status === 'paid') {
        return 'green';
    }
    if ($status === 'eft') {
        return '#2271b1';
    }
    return 'orange';
}

/**
 * Normal ticket tiers for an event.
 *
 * @param int $event_id
 * @return array<string,array{name:string,price:float}>
 */
function ve_get_event_tiers($event_id) {
    $tiers = get_post_meta((int) $event_id, '_ve_tiers', true);
    return is_array($tiers) ? $tiers : [];
}

/**
 * Special package tiers for an event.
 *
 * @param int $event_id
 * @return array<string,array{name:string,price:float,free_tickets:int,free_tier_key:string,available:int}>
 */
function ve_get_special_tiers($event_id) {
    $tiers = get_post_meta((int) $event_id, '_ve_special_tiers', true);
    return is_array($tiers) ? $tiers : [];
}

/**
 * One special package tier by key.
 *
 * @param int    $event_id
 * @param string $key
 * @return array{name:string,price:float,free_tickets:int,free_tier_key:string,available:int}|null
 */
function ve_get_special_tier($event_id, $key) {
    $key   = (string) $key;
    $tiers = ve_get_special_tiers($event_id);
    if ($key === '' || !isset($tiers[$key]) || !is_array($tiers[$key])) {
        return null;
    }
    $t = $tiers[$key];
    return [
        'name'          => (string) ($t['name'] ?? ''),
        'price'         => (float) ($t['price'] ?? 0),
        'free_tickets'  => max(0, (int) ($t['free_tickets'] ?? 0)),
        'free_tier_key' => (string) ($t['free_tier_key'] ?? ''),
        // 0 = unlimited (legacy packages without a cap)
        'available'     => max(0, (int) ($t['available'] ?? 0)),
    ];
}

/**
 * How many package rows are already taken for a special tier.
 *
 * Counts paid + pending + EFT package lines only (one package per order).
 * Pending holds stock until paid or cleaned up by the 24h TTL.
 * EFT orders issue tickets immediately, so they consume stock.
 *
 * @param int    $event_id
 * @param string $special_key
 * @return int
 */
function ve_count_special_tier_sold($event_id, $special_key) {
    global $wpdb;
    $event_id     = absint($event_id);
    $special_key  = sanitize_text_field((string) $special_key);
    if ($event_id < 1 || $special_key === '') {
        return 0;
    }
    $table = $wpdb->prefix . 've_registrations';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table
         WHERE event_id = %d
           AND line_type = 'package'
           AND special_tier_key = %s
           AND status IN ('pending', 'paid', 'eft')",
        $event_id,
        $special_key
    ));
}

/**
 * Remaining units for a special package, or null if unlimited.
 *
 * @param int    $event_id
 * @param string $special_key
 * @param array|null $package Optional pre-loaded tier from ve_get_special_tier()
 * @return int|null null = unlimited
 */
function ve_get_special_tier_remaining($event_id, $special_key, $package = null) {
    if ($package === null) {
        $package = ve_get_special_tier($event_id, $special_key);
    }
    if (!$package) {
        return 0;
    }
    $cap = max(0, (int) ($package['available'] ?? 0));
    if ($cap < 1) {
        return null; // unlimited
    }
    $sold = ve_count_special_tier_sold($event_id, $special_key);
    return max(0, $cap - $sold);
}

/**
 * Whether at least one unit of the package can still be purchased.
 *
 * @param int    $event_id
 * @param string $special_key
 * @param array|null $package
 * @return bool
 */
function ve_special_tier_has_stock($event_id, $special_key, $package = null) {
    $remaining = ve_get_special_tier_remaining($event_id, $special_key, $package);
    return $remaining === null || $remaining > 0;
}

/**
 * Whether a registration row is a package (table/stand), not a person.
 *
 * @param object|array|null $reg
 * @return bool
 */
function ve_is_package_registration($reg) {
    if (!$reg) {
        return false;
    }
    $type = is_array($reg) ? ($reg['line_type'] ?? '') : ($reg->line_type ?? '');
    return $type === 'package';
}

/**
 * Whether a registration row is a person (guest) ticket.
 * Legacy rows without line_type count as people.
 *
 * @param object|array|null $reg
 * @return bool
 */
function ve_is_person_registration($reg) {
    if (!$reg) {
        return false;
    }
    $type = is_array($reg) ? ($reg['line_type'] ?? 'person') : ($reg->line_type ?? 'person');
    return $type === '' || $type === 'person' || $type === null;
}

/**
 * Next daily payment reference (VE-YYYYMMDD-NNNN).
 *
 * @return string
 */
function ve_generate_payment_reference() {
    $today      = date('Ymd');
    $option_key = 've_last_payment_ref_' . $today;
    $next       = (int) get_option($option_key, 0) + 1;
    update_option($option_key, $next, false);
    return 'VE-' . $today . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

/** Hardcoded tier key for complimentary tickets (not an event meta tier). */
if (!defined('VE_COMPLIMENTARY_TIER_KEY')) {
    define('VE_COMPLIMENTARY_TIER_KEY', 'complimentary');
}
if (!defined('VE_COMPLIMENTARY_TIER_NAME')) {
    define('VE_COMPLIMENTARY_TIER_NAME', 'Complimentary Pass');
}

/**
 * Whether the current user may issue complimentary tickets (administrator).
 *
 * @return bool
 */
function ve_user_can_issue_complimentary() {
    return is_user_logged_in() && current_user_can('manage_options');
}

/**
 * Issuer label for complimentary Internal Ref: first name, else username.
 *
 * @param int|null $user_id
 * @return string Safe alphanumeric slug for refs (e.g. Leon)
 */
function ve_complimentary_issuer_label($user_id = null) {
    $user = $user_id ? get_userdata((int) $user_id) : wp_get_current_user();
    if (!$user || !$user->ID) {
        return 'Admin';
    }

    $first = trim((string) ($user->first_name ?? ''));
    $raw   = $first !== '' ? $first : (string) $user->user_login;
    $label = preg_replace('/[^A-Za-z0-9]+/', '', $raw);
    if ($label === '' || $label === null) {
        $label = 'Admin';
    }

    // Cap length so refs stay readable in the guest list
    return substr($label, 0, 24);
}

/**
 * Complimentary batch reference: {FirstName}-{YYYYMMDD}-{NNNN}
 * Matches paid refs (VE-20260809-0001) → e.g. Leon-20260809-0001.
 * Sequence is per admin, per calendar day.
 *
 * @param int|null $user_id
 * @return string
 */
function ve_generate_complimentary_reference($user_id = null) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    $name    = ve_complimentary_issuer_label($user_id);
    $date    = date('Ymd'); // e.g. 20260809 (same as paid VE- refs)
    $key     = 've_last_comp_ref_' . $user_id . '_' . $date;
    $next    = (int) get_option($key, 0) + 1;
    update_option($key, $next, false);

    return $name . '-' . $date . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

/**
 * Shared billing fields from the registration AJAX POST.
 *
 * @return array<string,string>
 */
function ve_collect_billing_from_request() {
    return [
        'billing_company'  => sanitize_text_field(wp_unslash($_POST['billing_company'] ?? '')),
        'billing_address'  => sanitize_textarea_field(wp_unslash($_POST['billing_address'] ?? '')),
        'billing_country'  => sanitize_text_field(wp_unslash($_POST['billing_country'] ?? 'NA')),
        'accounting_email' => sanitize_email(wp_unslash($_POST['accounting_email'] ?? '')),
        'billing_notes'    => sanitize_textarea_field(wp_unslash($_POST['billing_notes'] ?? '')),
    ];
}

/**
 * Resolve a human-readable tier label for an event.
 *
 * Registrations store the tier *key* (e.g. new1). The display name lives in
 * event meta `_ve_tiers[key][name]`. Prefer a snapshotted $fallback_name when provided.
 *
 * @param int         $event_id
 * @param string      $tier_key
 * @param string|null $fallback_name Optional name already stored on the registration
 * @return string
 */
function ve_get_tier_name($event_id, $tier_key, $fallback_name = null) {
    $fallback_name = is_string($fallback_name) ? trim($fallback_name) : '';
    if ($fallback_name !== '' && !preg_match('/^new\d+$/i', $fallback_name)) {
        return $fallback_name;
    }

    $tier_key = trim((string) $tier_key);
    $tiers    = get_post_meta((int) $event_id, '_ve_tiers', true);
    if (!is_array($tiers)) {
        $tiers = [];
    }

    if ($tier_key !== '' && isset($tiers[$tier_key]) && is_array($tiers[$tier_key])) {
        $name = trim((string) ($tiers[$tier_key]['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    // Loose match (string/int key quirks)
    foreach ($tiers as $key => $tier) {
        if ((string) $key !== $tier_key || !is_array($tier)) {
            continue;
        }
        $name = trim((string) ($tier['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    if ($fallback_name !== '') {
        return $fallback_name;
    }

    return $tier_key !== '' ? $tier_key : '—';
}

/**
 * Display label for a registration row (prefers snapshotted tier_name).
 *
 * @param object $reg
 * @return string
 */
function ve_registration_tier_label($reg) {
    if (!$reg) {
        return '—';
    }
    $stored = isset($reg->tier_name) ? $reg->tier_name : null;
    return ve_get_tier_name((int) ($reg->event_id ?? 0), (string) ($reg->tier ?? ''), $stored);
}

function ve_create_roles() {
    if (!get_role('event_gate')) {
        add_role('event_gate', 'Event Gate', [
            'read' => true,
        ]);
    }
}

/**
 * True when the user has the Event Gate role and is not a full admin.
 * Used to hide the admin bar and block wp-admin (scanner AJAX still allowed).
 *
 * @param int|null $user_id
 * @return bool
 */
function ve_user_is_gate_staff($user_id = null) {
    if ($user_id === null) {
        if (!is_user_logged_in()) {
            return false;
        }
        $user = wp_get_current_user();
    } else {
        $user = get_userdata((int) $user_id);
    }
    if (!$user || empty($user->ID)) {
        return false;
    }
    // Administrators / anyone who can manage the site keep full access.
    if (user_can($user, 'manage_options')) {
        return false;
    }
    $roles = (array) ($user->roles ?? []);
    return in_array('event_gate', $roles, true);
}

/**
 * Hide the WP admin bar for Gate staff (frontend + would-be admin).
 */
add_filter('show_admin_bar', 've_hide_admin_bar_for_gate_staff');
function ve_hide_admin_bar_for_gate_staff($show) {
    if (ve_user_is_gate_staff()) {
        return false;
    }
    return $show;
}

/**
 * Block wp-admin for Gate staff. Keep admin-ajax.php (and admin-post.php)
 * so the gate scanner AJAX check-in still works.
 */
add_action('admin_init', 've_block_admin_for_gate_staff');
function ve_block_admin_for_gate_staff() {
    if (!ve_user_is_gate_staff()) {
        return;
    }

    // Allow AJAX / form handlers used from the front end.
    if (wp_doing_ajax()) {
        return;
    }
    global $pagenow;
    if (in_array((string) $pagenow, ['admin-ajax.php', 'admin-post.php'], true)) {
        return;
    }

    // No backend access — send them to the front of the site.
    wp_safe_redirect(home_url('/'));
    exit;
}

// ====================== PENDING REGISTRATION ======================
add_action('wp_ajax_ve_save_pending_registrations', 've_handle_pending_registrations');
add_action('wp_ajax_nopriv_ve_save_pending_registrations', 've_handle_pending_registrations');

function ve_handle_pending_registrations() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 've_registration_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $event_id = absint($_POST['event_id'] ?? 0);
    $mode     = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'normal'));
    if ($mode !== 'special') {
        $mode = 'normal';
    }

    if (!$event_id || get_post_type($event_id) !== 've_event') {
        wp_send_json_error(['message' => 'Invalid event.']);
    }

    $billing = ve_collect_billing_from_request();
    if ($billing['billing_address'] === '' || $billing['accounting_email'] === '' || $billing['billing_country'] === '') {
        wp_send_json_error(['message' => 'Please complete billing details.']);
    }

    if ($mode === 'special') {
        ve_handle_pending_special_registrations($event_id, $billing, 'pending');
        return;
    }

    ve_handle_pending_normal_registrations($event_id, $billing, 'pending');
}

// ====================== EFT CHECKOUT (no gateway) ======================
add_action('wp_ajax_ve_save_eft_registrations', 've_handle_eft_registrations');
add_action('wp_ajax_nopriv_ve_save_eft_registrations', 've_handle_eft_registrations');

/**
 * EFT shortcode checkout: same validation as card forms, but status=eft,
 * no gateway, tickets + draft/Sent unpaid Zoho invoice immediately.
 */
function ve_handle_eft_registrations() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 've_eft_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $event_id = absint($_POST['event_id'] ?? 0);
    $mode     = sanitize_text_field(wp_unslash($_POST['mode'] ?? 'normal'));
    if ($mode !== 'special') {
        $mode = 'normal';
    }

    if (!$event_id || get_post_type($event_id) !== 've_event') {
        wp_send_json_error(['message' => 'Invalid event.']);
    }

    $billing = ve_collect_billing_from_request();
    if ($billing['billing_address'] === '' || $billing['accounting_email'] === '' || $billing['billing_country'] === '') {
        wp_send_json_error(['message' => 'Please complete billing details.']);
    }

    if ($mode === 'special') {
        ve_handle_pending_special_registrations($event_id, $billing, 'eft');
        return;
    }

    ve_handle_pending_normal_registrations($event_id, $billing, 'eft');
}

// ====================== COMPLIMENTARY TICKETS (admin only) ======================
add_action('wp_ajax_ve_save_complimentary_registrations', 've_handle_complimentary_registrations');
// No nopriv — administrators only.

/**
 * Issue complimentary tickets: no billing, gateway, or Zoho.
 * Saves as paid @ N$0, generates QR, emails guests.
 */
function ve_handle_complimentary_registrations() {
    if (!check_ajax_referer('ve_complimentary_nonce', 'nonce', false)) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    if (!ve_user_can_issue_complimentary()) {
        wp_send_json_error(['message' => 'Only administrators can issue complimentary tickets.']);
    }

    $event_id = absint($_POST['event_id'] ?? 0);
    if (!$event_id || get_post_type($event_id) !== 've_event') {
        wp_send_json_error(['message' => 'Invalid event.']);
    }

    $tickets = $_POST['tickets'] ?? [];
    if (!is_array($tickets) || empty($tickets)) {
        wp_send_json_error(['message' => 'Add at least one guest.']);
    }
    $tickets = array_values($tickets);

    if (count($tickets) > 30) {
        wp_send_json_error(['message' => 'Maximum 30 complimentary tickets per batch.']);
    }

    $rows = [];
    foreach ($tickets as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }
        $first = sanitize_text_field(wp_unslash($ticket['first_name'] ?? ''));
        $last  = sanitize_text_field(wp_unslash($ticket['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($ticket['email'] ?? ''));
        if ($first === '' || $last === '' || $email === '') {
            wp_send_json_error(['message' => 'Each ticket needs first name, last name, and email.']);
        }

        $rows[] = [
            'event_id'          => $event_id,
            'first_name'        => $first,
            'last_name'         => $last,
            'organisation'      => sanitize_text_field(wp_unslash($ticket['organisation'] ?? '')),
            'phone'             => sanitize_text_field(wp_unslash($ticket['phone'] ?? '')),
            'email'             => $email,
            'tier'              => VE_COMPLIMENTARY_TIER_KEY,
            'tier_name'         => VE_COMPLIMENTARY_TIER_NAME,
            'price'             => 0.0,
            'line_type'         => 'person',
            'included_free'     => 0,
            'special_tier_key'  => '',
            'status'            => 'paid',
            'paid_at'           => current_time('mysql'),
            'billing_company'   => '',
            'billing_address'   => '',
            'billing_country'   => 'NA',
            'accounting_email'  => '',
            'billing_notes'     => '',
            'created_at'        => current_time('mysql'),
        ];
    }

    if (empty($rows)) {
        wp_send_json_error(['message' => 'Invalid data received.']);
    }

    ve_insert_complimentary_batch($rows);
}

/**
 * Insert complimentary rows (already paid), generate QR + email, no gateway/Zoho.
 *
 * @param array<int,array<string,mixed>> $rows
 */
function ve_insert_complimentary_batch(array $rows) {
    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

    $reference = ve_generate_complimentary_reference();
    $inserted  = 0;
    $ids       = [];

    foreach ($rows as $row) {
        $row['payment_reference']  = $reference;
        $row['internal_reference'] = $reference;

        $ok = $wpdb->insert($table_name, $row);
        if ($ok) {
            $inserted++;
            $ids[] = (int) $wpdb->insert_id;
        } else {
            error_log('Venture Events: complimentary insert failed: ' . $wpdb->last_error . ' | data=' . wp_json_encode($row));
        }
    }

    if ($inserted === 0) {
        wp_send_json_error(['message' => 'Failed to save any complimentary tickets.']);
    }

    // QR + ticket email for each person row (no Zoho, no gateway)
    $emailed = 0;
    foreach ($ids as $reg_id) {
        $reg = ve_get_registration($reg_id);
        if (!$reg || ve_is_package_registration($reg)) {
            continue;
        }

        if (!ve_ensure_registration_qr($reg)) {
            error_log("Venture Events: Complimentary QR generation FAILED for registration #{$reg->id}");
            continue;
        }

        if (ve_send_ticket_email($reg, (int) $reg->event_id, VE_COMPLIMENTARY_TIER_NAME)) {
            $emailed++;
        }
    }

    error_log(
        "Venture Events: Complimentary batch {$reference}: {$inserted} ticket(s), {$emailed} email(s) by user "
        . get_current_user_id()
    );

    wp_send_json_success([
        'internal_reference' => $reference,
        'count'              => $inserted,
        'emailed'            => $emailed,
        'message'            => sprintf(
            '%d complimentary ticket(s) issued (ref %s). Ticket emails sent where possible.',
            $inserted,
            $reference
        ),
    ]);
}

/**
 * Normal shortcode checkout: person tickets only.
 *
 * @param int    $event_id
 * @param array  $billing
 * @param string $row_status pending (card checkout) or eft (EFT shortcode)
 */
function ve_handle_pending_normal_registrations($event_id, array $billing, $row_status = 'pending') {
    if (!in_array($row_status, ['pending', 'eft'], true)) {
        $row_status = 'pending';
    }
    $tickets = $_POST['tickets'] ?? [];
    if (empty($tickets) || !is_array($tickets)) {
        wp_send_json_error(['message' => 'Invalid data received.']);
    }

    $tiers = ve_get_event_tiers($event_id);
    if (empty($tiers)) {
        wp_send_json_error(['message' => 'This event has no ticket tiers configured.']);
    }

    $rows = [];
    foreach ($tickets as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }
        $tier_key = sanitize_text_field(wp_unslash($ticket['tier'] ?? ''));
        if ($tier_key === '' || !isset($tiers[$tier_key]) || !is_array($tiers[$tier_key])) {
            wp_send_json_error(['message' => 'Please select a valid ticket tier for every guest.']);
        }

        $first = sanitize_text_field(wp_unslash($ticket['first_name'] ?? ''));
        $last  = sanitize_text_field(wp_unslash($ticket['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($ticket['email'] ?? ''));
        if ($first === '' || $last === '' || $email === '') {
            wp_send_json_error(['message' => 'Each ticket needs first name, last name, and email.']);
        }

        $price     = (float) ($tiers[$tier_key]['price'] ?? 0);
        $tier_name = ve_get_tier_name($event_id, $tier_key, $tiers[$tier_key]['name'] ?? null);

        $rows[] = array_merge($billing, [
            'event_id'          => $event_id,
            'first_name'        => $first,
            'last_name'         => $last,
            'organisation'      => sanitize_text_field(wp_unslash($ticket['organisation'] ?? '')),
            'phone'             => sanitize_text_field(wp_unslash($ticket['phone'] ?? '')),
            'email'             => $email,
            'tier'              => $tier_key,
            'tier_name'         => $tier_name,
            'price'             => $price,
            'line_type'         => 'person',
            'included_free'     => 0,
            'special_tier_key'  => '',
            'status'            => $row_status,
            'created_at'        => current_time('mysql'),
        ]);
    }

    if (empty($rows)) {
        wp_send_json_error(['message' => 'Invalid data received.']);
    }

    if ($row_status === 'eft') {
        ve_insert_eft_batch($rows);
        return;
    }

    ve_insert_pending_batch($rows);
}

/**
 * Special shortcode checkout: one package + free people + optional paid extras.
 *
 * @param int    $event_id
 * @param array  $billing
 * @param string $row_status pending (card checkout) or eft (EFT shortcode)
 */
function ve_handle_pending_special_registrations($event_id, array $billing, $row_status = 'pending') {
    if (!in_array($row_status, ['pending', 'eft'], true)) {
        $row_status = 'pending';
    }
    if (trim((string) ($billing['billing_company'] ?? '')) === '') {
        wp_send_json_error(['message' => 'Please enter a company / organisation name.']);
    }

    $special_key = sanitize_text_field(wp_unslash($_POST['special_tier'] ?? ''));
    $package     = ve_get_special_tier($event_id, $special_key);
    if (!$package || $package['name'] === '' || $package['price'] <= 0) {
        wp_send_json_error(['message' => 'Please select a valid package.']);
    }

    // Stock gate: paid + pending + EFT package rows count against amount available
    if (!ve_special_tier_has_stock($event_id, $special_key, $package)) {
        wp_send_json_error([
            'message' => sprintf(
                'Sorry, “%s” is sold out. Please choose another package.',
                $package['name']
            ),
        ]);
    }

    $normal_tiers = ve_get_event_tiers($event_id);
    $free_count   = (int) $package['free_tickets'];
    $free_key     = $package['free_tier_key'];

    if ($free_count > 0) {
        if ($free_key === '' || !isset($normal_tiers[$free_key])) {
            wp_send_json_error(['message' => 'This package is misconfigured (included ticket tier missing). Please contact the organiser.']);
        }
    }

    $free_tickets = $_POST['free_tickets'] ?? [];
    if (!is_array($free_tickets)) {
        $free_tickets = [];
    }
    // jQuery may send object-like arrays; reindex
    $free_tickets = array_values($free_tickets);

    if (count($free_tickets) !== $free_count) {
        wp_send_json_error([
            'message' => sprintf(
                'This package includes %d free ticket(s). Please complete all included guest details.',
                $free_count
            ),
        ]);
    }

    $payment_placeholder_rows = [];

    // Package line (not a guest; no QR)
    $package_org = $billing['billing_company'] !== '' ? $billing['billing_company'] : $package['name'];
    $payment_placeholder_rows[] = array_merge($billing, [
        'event_id'          => $event_id,
        'first_name'        => $package['name'],
        'last_name'         => '',
        'organisation'      => $package_org,
        'phone'             => '',
        'email'             => $billing['accounting_email'],
        'tier'              => $special_key,
        'tier_name'         => $package['name'],
        'price'             => (float) $package['price'],
        'line_type'         => 'package',
        'included_free'     => 0,
        'special_tier_key'  => $special_key,
        'status'            => $row_status,
        'created_at'        => current_time('mysql'),
    ]);

    $free_tier_name = $free_count > 0
        ? ve_get_tier_name($event_id, $free_key, $normal_tiers[$free_key]['name'] ?? null)
        : '';

    foreach ($free_tickets as $ticket) {
        if (!is_array($ticket)) {
            wp_send_json_error(['message' => 'Invalid free ticket data.']);
        }
        $first = sanitize_text_field(wp_unslash($ticket['first_name'] ?? ''));
        $last  = sanitize_text_field(wp_unslash($ticket['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($ticket['email'] ?? ''));
        if ($first === '' || $last === '' || $email === '') {
            wp_send_json_error(['message' => 'Each included free ticket needs first name, last name, and email.']);
        }

        $payment_placeholder_rows[] = array_merge($billing, [
            'event_id'          => $event_id,
            'first_name'        => $first,
            'last_name'         => $last,
            'organisation'      => sanitize_text_field(wp_unslash($ticket['organisation'] ?? '')),
            'phone'             => sanitize_text_field(wp_unslash($ticket['phone'] ?? '')),
            'email'             => $email,
            'tier'              => $free_key,
            'tier_name'         => $free_tier_name,
            'price'             => 0.0,
            'line_type'         => 'person',
            'included_free'     => 1,
            'special_tier_key'  => $special_key,
            'status'            => $row_status,
            'created_at'        => current_time('mysql'),
        ]);
    }

    // Optional extra paid tickets (same as normal tiers)
    $extras = $_POST['tickets'] ?? [];
    if (!is_array($extras)) {
        $extras = [];
    }
    $extras = array_values($extras);

    foreach ($extras as $ticket) {
        if (!is_array($ticket)) {
            continue;
        }
        $tier_key = sanitize_text_field(wp_unslash($ticket['tier'] ?? ''));
        if ($tier_key === '' || !isset($normal_tiers[$tier_key])) {
            wp_send_json_error(['message' => 'Please select a valid ticket tier for every additional guest.']);
        }

        $first = sanitize_text_field(wp_unslash($ticket['first_name'] ?? ''));
        $last  = sanitize_text_field(wp_unslash($ticket['last_name'] ?? ''));
        $email = sanitize_email(wp_unslash($ticket['email'] ?? ''));
        if ($first === '' || $last === '' || $email === '') {
            wp_send_json_error(['message' => 'Each additional ticket needs first name, last name, and email.']);
        }

        $price     = (float) ($normal_tiers[$tier_key]['price'] ?? 0);
        $tier_name = ve_get_tier_name($event_id, $tier_key, $normal_tiers[$tier_key]['name'] ?? null);

        $payment_placeholder_rows[] = array_merge($billing, [
            'event_id'          => $event_id,
            'first_name'        => $first,
            'last_name'         => $last,
            'organisation'      => sanitize_text_field(wp_unslash($ticket['organisation'] ?? '')),
            'phone'             => sanitize_text_field(wp_unslash($ticket['phone'] ?? '')),
            'email'             => $email,
            'tier'              => $tier_key,
            'tier_name'         => $tier_name,
            'price'             => $price,
            'line_type'         => 'person',
            'included_free'     => 0,
            'special_tier_key'  => $special_key,
            'status'            => $row_status,
            'created_at'        => current_time('mysql'),
        ]);
    }

    if ($row_status === 'eft') {
        ve_insert_eft_batch($payment_placeholder_rows);
        return;
    }

    ve_insert_pending_batch($payment_placeholder_rows);
}

/**
 * Insert a batch of registration rows sharing one payment reference; respond with JSON.
 *
 * @param array<int,array<string,mixed>> $rows Row data without payment_reference / internal_reference
 */
function ve_insert_pending_batch(array $rows) {
    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

    $payment_reference = ve_generate_payment_reference();
    $total_amount      = 0.0;
    $inserted          = 0;

    foreach ($rows as $row) {
        $row['payment_reference']  = $payment_reference;
        $row['internal_reference'] = $payment_reference;
        $total_amount             += (float) ($row['price'] ?? 0);

        $ok = $wpdb->insert($table_name, $row);
        if ($ok) {
            $inserted++;
        } else {
            error_log('Venture Events: insert failed: ' . $wpdb->last_error . ' | data=' . wp_json_encode($row));
        }
    }

    if ($inserted === 0) {
        wp_send_json_error(['message' => 'Failed to save any registrations.']);
    }

    if ($inserted < count($rows)) {
        error_log(
            "Venture Events: Partial insert for {$payment_reference}: {$inserted}/" . count($rows)
        );
    }

    wp_send_json_success([
        'payment_reference' => $payment_reference,
        'total_amount'      => $total_amount,
        'message'           => sprintf('%d line(s) saved successfully', $inserted),
    ]);
}

/**
 * Insert EFT rows (status=eft, no paid_at), then issue tickets + Zoho draft invoice.
 *
 * @param array<int,array<string,mixed>> $rows
 */
function ve_insert_eft_batch(array $rows) {
    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

    $payment_reference = ve_generate_payment_reference();
    $total_amount      = 0.0;
    $inserted          = 0;

    foreach ($rows as $row) {
        $row['payment_reference']  = $payment_reference;
        $row['internal_reference'] = $payment_reference;
        $row['status']             = 'eft';
        unset($row['paid_at']);
        $total_amount             += (float) ($row['price'] ?? 0);

        $ok = $wpdb->insert($table_name, $row);
        if ($ok) {
            $inserted++;
        } else {
            error_log('Venture Events: EFT insert failed: ' . $wpdb->last_error . ' | data=' . wp_json_encode($row));
        }
    }

    if ($inserted === 0) {
        wp_send_json_error(['message' => 'Failed to save any registrations.']);
    }

    if ($inserted < count($rows)) {
        error_log(
            "Venture Events: Partial EFT insert for {$payment_reference}: {$inserted}/" . count($rows)
        );
    }

    $fulfill = ve_fulfill_eft_order($payment_reference);

    $message = 'Order received. Tickets have been emailed to each guest. '
        . 'The invoice has been emailed to the billing address. Please pay by EFT within 30 days.';
    if (empty($fulfill['invoice_ok'])) {
        $message = 'Order received. Tickets have been emailed to each guest. '
            . 'The invoice could not be created automatically — the organiser will follow up.';
    }

    wp_send_json_success([
        'payment_reference' => $payment_reference,
        'total_amount'      => $total_amount,
        'eft'               => true,
        'invoice_ok'        => !empty($fulfill['invoice_ok']),
        'emailed'           => (int) ($fulfill['emailed'] ?? 0),
        'message'           => $message,
    ]);
}

/**
 * Issue QR tickets and create a Zoho EFT invoice (Sent, unpaid, Net 30).
 *
 * @param string $payment_reference
 * @return array{emailed:int,invoice_ok:bool}
 */
function ve_fulfill_eft_order($payment_reference) {
    $payment_reference = sanitize_text_field((string) $payment_reference);
    $result = [
        'emailed'    => 0,
        'invoice_ok' => false,
    ];

    if ($payment_reference === '') {
        return $result;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';
    $regs       = ve_get_eft_registrations_by_reference($payment_reference);
    if (empty($regs)) {
        error_log("Venture Events: EFT fulfill — no eft rows for ref={$payment_reference}");
        return $result;
    }

    foreach ($regs as $reg) {
        if (ve_is_package_registration($reg)) {
            continue;
        }

        if (empty($reg->tier_name) || preg_match('/^new\d+$/i', (string) $reg->tier_name)) {
            $resolved = ve_get_tier_name((int) $reg->event_id, (string) $reg->tier, $reg->tier_name ?? null);
            if ($resolved && $resolved !== $reg->tier) {
                $wpdb->update(
                    $table_name,
                    ['tier_name' => $resolved],
                    ['id' => (int) $reg->id],
                    ['%s'],
                    ['%d']
                );
                $reg->tier_name = $resolved;
            }
        }

        if (!ve_ensure_registration_qr($reg)) {
            error_log("Venture Events: EFT QR generation FAILED for registration #{$reg->id}");
            continue;
        }

        if (empty($reg->qr_url)) {
            error_log(
                "Venture Events: CRITICAL — skipping EFT ticket email for registration #{$reg->id} "
                . "({$reg->email}): QR still missing"
            );
            continue;
        }

        if (ve_send_ticket_email($reg, (int) $reg->event_id, ve_registration_tier_label($reg))) {
            $result['emailed']++;
        }
    }

    error_log(
        "Venture Events: EFT ticket email pass finished for ref={$payment_reference} "
        . "(emailed={$result['emailed']})"
    );

    $existing_invoice = '';
    foreach ($regs as $pr) {
        $inv = trim((string) ($pr->invoice_number ?? ''));
        if ($inv !== '') {
            $existing_invoice = $inv;
            break;
        }
    }
    if ($existing_invoice !== '') {
        error_log(
            "Venture Events: SKIPPING Zoho EFT invoice for ref={$payment_reference} — "
            . "already linked to invoice #{$existing_invoice}"
        );
        $result['invoice_ok'] = true;
        return $result;
    }

    $fresh = ve_get_eft_registrations_by_reference($payment_reference);
    if (empty($fresh)) {
        return $result;
    }

    $master_reg = $fresh[0];
    $master_reg->line_items = $fresh;

    $invoice = ve_generate_zoho_invoice($master_reg, (int) $fresh[0]->event_id, null, 'eft');

    if ($invoice && !empty($invoice['invoice_number'])) {
        $invoice_number = $invoice['invoice_number'];
        foreach ($fresh as $reg) {
            $wpdb->update($table_name, ['invoice_number' => $invoice_number], ['id' => $reg->id]);
        }
        error_log("Venture Events: Zoho EFT invoice #{$invoice_number} linked to ref={$payment_reference}");
        $result['invoice_ok'] = true;
    } else {
        error_log("Venture Events: Zoho EFT invoice generation failed for ref={$payment_reference}");
    }

    return $result;
}

// ====================== PENDING CLEANUP (24h TTL) ======================

/**
 * Schedule hourly cleanup of abandoned pending registrations.
 * Safe to call repeatedly (only schedules if not already queued).
 */
function ve_schedule_pending_cleanup() {
    if (!wp_next_scheduled('ve_cleanup_pending_registrations')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 've_cleanup_pending_registrations');
        error_log('Venture Events: Scheduled pending registration cleanup (hourly)');
    }
}

/**
 * Unschedule pending cleanup (plugin deactivation).
 */
function ve_unschedule_pending_cleanup() {
    wp_clear_scheduled_hook('ve_cleanup_pending_registrations');
}

/**
 * Delete unpaid pending rows older than VE_PENDING_TTL_HOURS (default 24).
 * Paid, complimentary, and EFT tickets are never touched.
 *
 * @return int Number of rows deleted
 */
function ve_cleanup_expired_pending_registrations() {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';

    $hours = (int) apply_filters('ve_pending_registration_ttl_hours', VE_PENDING_TTL_HOURS);
    if ($hours < 1) {
        $hours = 24;
    }

    // created_at is stored in blog-local time (current_time('mysql'))
    $cutoff = date('Y-m-d H:i:s', current_time('timestamp') - ($hours * HOUR_IN_SECONDS));

    // Only pending abandoned checkouts — never paid / complimentary / eft
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE status = %s AND created_at < %s",
            'pending',
            $cutoff
        )
    );

    if ($deleted === false) {
        error_log('Venture Events: Pending cleanup query failed: ' . $wpdb->last_error);
        return 0;
    }

    if ($deleted > 0) {
        error_log(
            "Venture Events: Deleted {$deleted} pending registration(s) older than {$hours}h (before {$cutoff})"
        );
    }

    return (int) $deleted;
}

add_action('ve_cleanup_pending_registrations', 've_cleanup_expired_pending_registrations');

// ====================== SUCCESS HANDLER ======================
function ve_process_payment_success($data) {
    $payment_reference = sanitize_text_field($data['payment_reference'] ?? '');

    error_log("Venture Events: Payment success received for ref={$payment_reference}");

    if (empty($payment_reference)) {
        error_log('Venture Events: ve_process_payment_success called with empty payment_reference');
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

    $registrations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE payment_reference = %s AND status = 'pending'",
        $payment_reference
    ));

    if (empty($registrations)) {
        error_log("Venture Events: No pending registrations found for ref={$payment_reference}");
        return;
    }

    error_log("Venture Events: Processing " . count($registrations) . " registration(s) for ref={$payment_reference}");

    // transaction_id column is varchar(100) — gateway tokens (e.g. Adumo JWT) can be much longer
    $transaction_id = ve_normalize_transaction_id($data['transaction_id'] ?? '');

    // Update status + QR immediately (QR only for people, not packages)
    foreach ($registrations as $reg) {
        $update_data = [
            'status'         => 'paid',
            'transaction_id' => $transaction_id,
            'paid_at'        => current_time('mysql'),
        ];

        $updated = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => (int) $reg->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            error_log(
                "Venture Events: FAILED to mark registration #{$reg->id} as paid for ref={$payment_reference}. "
                . "DB error: {$wpdb->last_error} | last_query: {$wpdb->last_query}"
            );
        } elseif ($updated === 0) {
            error_log(
                "Venture Events: Status update affected 0 rows for registration #{$reg->id} (ref={$payment_reference}). "
                . "Row may already be paid or id mismatch."
            );
        } else {
            error_log("Venture Events: Registration #{$reg->id} marked paid (tx={$transaction_id})");
        }

        if (ve_is_package_registration($reg)) {
            error_log("Venture Events: Skipping QR for package registration #{$reg->id}");
            continue;
        }

        // Generate QR + keep on the in-memory row (do not rely only on a later SELECT)
        $qr_url = ve_ensure_registration_qr($reg);
        if ($qr_url) {
            error_log("Venture Events: QR ready for registration #{$reg->id}");
        } else {
            error_log("Venture Events: QR generation FAILED for registration #{$reg->id}");
        }
    }

    // Ticket emails only for people — QR must exist first (no "available shortly")
    $fresh_regs = ve_get_registrations_by_reference($payment_reference) ?: $registrations;
    foreach ($fresh_regs as $reg) {
        if (ve_is_package_registration($reg)) {
            continue;
        }

        // Backfill display name for rows created before tier_name existed
        if (empty($reg->tier_name) || preg_match('/^new\d+$/i', (string) $reg->tier_name)) {
            $resolved = ve_get_tier_name((int) $reg->event_id, (string) $reg->tier, $reg->tier_name ?? null);
            if ($resolved && $resolved !== $reg->tier) {
                $wpdb->update(
                    $table_name,
                    ['tier_name' => $resolved],
                    ['id' => (int) $reg->id],
                    ['%s'],
                    ['%d']
                );
                $reg->tier_name = $resolved;
            }
        }

        // Final QR guarantee before mail (retry if first pass failed)
        if (empty($reg->qr_url)) {
            ve_ensure_registration_qr($reg);
        }

        if (empty($reg->qr_url)) {
            error_log(
                "Venture Events: CRITICAL — skipping ticket email for registration #{$reg->id} "
                . "({$reg->email}): QR still missing after retries"
            );
            continue;
        }

        ve_send_ticket_email($reg, $reg->event_id, ve_registration_tier_label($reg));
    }
    error_log("Venture Events: Ticket email pass finished for person rows on ref={$payment_reference}");

    // Zoho: only after every row for this ref is confirmed paid in the DB (no draft on pending).
    $paid_regs = ve_get_paid_registrations_by_reference($payment_reference);
    $pending_left = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE payment_reference = %s AND status = 'pending'",
        $payment_reference
    ));

    if ($pending_left > 0 || empty($paid_regs)) {
        error_log(
            "Venture Events: SKIPPING Zoho invoice for ref={$payment_reference} — "
            . "payment not fully confirmed (paid_rows=" . count($paid_regs) . ", pending_left={$pending_left})"
        );
        return;
    }

    // Already invoiced (idempotent) — do not create a second Zoho invoice
    $existing_invoice = '';
    foreach ($paid_regs as $pr) {
        $inv = trim((string) ($pr->invoice_number ?? ''));
        if ($inv !== '') {
            $existing_invoice = $inv;
            break;
        }
    }
    if ($existing_invoice !== '') {
        error_log(
            "Venture Events: SKIPPING Zoho invoice for ref={$payment_reference} — "
            . "already linked to invoice #{$existing_invoice}"
        );
        return;
    }

    $master_reg = $paid_regs[0];
    $master_reg->line_items = $paid_regs;

    $invoice = ve_generate_zoho_invoice($master_reg, (int) $paid_regs[0]->event_id);

    if ($invoice && !empty($invoice['invoice_number'])) {
        $invoice_number = $invoice['invoice_number'];
        foreach ($paid_regs as $reg) {
            $wpdb->update($table_name, ['invoice_number' => $invoice_number], ['id' => $reg->id]);
        }
        error_log("Venture Events: Zoho invoice #{$invoice_number} linked to registrations for ref={$payment_reference}");
    } else {
        error_log("Venture Events: Zoho invoice generation did not return invoice_number for ref={$payment_reference}");
    }
}


// ====================== HELPERS ======================

/**
 * Fit a gateway transaction id into ve_registrations.transaction_id (varchar 100).
 * Long tokens (Adumo JWT / response tokens) are stored as a short stable fingerprint.
 */
function ve_normalize_transaction_id($transaction_id) {
    $transaction_id = sanitize_text_field((string) $transaction_id);

    if ($transaction_id === '') {
        return '';
    }

    // Room for optional prefix; keep well under 100 chars
    if (strlen($transaction_id) <= 100) {
        return $transaction_id;
    }

    // Deterministic short id so retries / support can still match logs
    return 'h:' . hash('sha256', $transaction_id); // 2 + 64 = 66 chars
}

function ve_get_registrations_by_reference($payment_reference) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE payment_reference = %s ORDER BY id ASC",
        $payment_reference
    ));
}

/**
 * Registrations for a payment ref that are confirmed paid (status + paid_at).
 *
 * Used before Zoho invoice creation — never invent invoices for pending checkout.
 *
 * @param string $payment_reference
 * @return array<int,object>
 */
function ve_get_paid_registrations_by_reference($payment_reference) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    $payment_reference = sanitize_text_field((string) $payment_reference);
    if ($payment_reference === '') {
        return [];
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE payment_reference = %s
           AND status = 'paid'
           AND paid_at IS NOT NULL
           AND paid_at != ''
         ORDER BY id ASC",
        $payment_reference
    ));
    return is_array($rows) ? $rows : [];
}

/**
 * Whether every registration for a payment ref is paid with paid_at set.
 * Pending rows remaining → false (do not create Zoho invoice).
 *
 * @param string $payment_reference
 * @return bool
 */
function ve_payment_reference_fully_paid($payment_reference) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    $payment_reference = sanitize_text_field((string) $payment_reference);
    if ($payment_reference === '') {
        return false;
    }

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE payment_reference = %s",
        $payment_reference
    ));
    if ($total < 1) {
        return false;
    }

    $paid = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table
         WHERE payment_reference = %s
           AND status = 'paid'
           AND paid_at IS NOT NULL
           AND paid_at != ''",
        $payment_reference
    ));

    return $paid === $total;
}

/**
 * Registrations for a payment ref that are EFT-issued (status=eft).
 *
 * @param string $payment_reference
 * @return array<int,object>
 */
function ve_get_eft_registrations_by_reference($payment_reference) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    $payment_reference = sanitize_text_field((string) $payment_reference);
    if ($payment_reference === '') {
        return [];
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table
         WHERE payment_reference = %s
           AND status = 'eft'
         ORDER BY id ASC",
        $payment_reference
    ));
    return is_array($rows) ? $rows : [];
}

/**
 * Whether every registration for a payment ref is status=eft.
 *
 * @param string $payment_reference
 * @return bool
 */
function ve_payment_reference_fully_eft($payment_reference) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    $payment_reference = sanitize_text_field((string) $payment_reference);
    if ($payment_reference === '') {
        return false;
    }

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE payment_reference = %s",
        $payment_reference
    ));
    if ($total < 1) {
        return false;
    }

    $eft = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table
         WHERE payment_reference = %s
           AND status = 'eft'",
        $payment_reference
    ));

    return $eft === $total;
}

/**
 * From address used for ticket emails only (does not change other WP mail).
 *
 * Defaults to the site admin email (or ve_ticket_mail_from option if set).
 * Filter: ve_ticket_mail_from
 */
function ve_get_ticket_mail_from() {
    $configured = trim((string) get_option('ve_ticket_mail_from', ''));
    $default    = $configured !== '' ? $configured : (string) get_option('admin_email');
    $from       = apply_filters('ve_ticket_mail_from', $default);
    $from       = sanitize_email($from);
    return $from !== '' ? $from : (string) get_option('admin_email');
}

/**
 * From name used for ticket emails only.
 *
 * Defaults to "{Site Name} Tickets" (or ve_ticket_mail_from_name option if set).
 * Filter: ve_ticket_mail_from_name
 */
function ve_get_ticket_mail_from_name() {
    $configured = trim((string) get_option('ve_ticket_mail_from_name', ''));
    $default    = $configured !== '' ? $configured : (get_bloginfo('name') . ' Tickets');
    $name       = apply_filters('ve_ticket_mail_from_name', $default);
    $name       = sanitize_text_field($name);
    return $name !== '' ? $name : (get_bloginfo('name') . ' Tickets');
}

/**
 * Generate QR for a person registration, persist qr_url, set on $reg.
 *
 * @param object $reg Registration row (mutated: qr_url when successful)
 * @return string|false Public QR image URL or false
 */
function ve_ensure_registration_qr($reg) {
    if (!$reg || empty($reg->id)) {
        return false;
    }
    if (function_exists('ve_is_package_registration') && ve_is_package_registration($reg)) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

    $existing = trim((string) ($reg->qr_url ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    // Prefer DB value if already saved (e.g. retry / complimentary path)
    $from_db = $wpdb->get_var($wpdb->prepare(
        "SELECT qr_url FROM $table_name WHERE id = %d",
        (int) $reg->id
    ));
    if (is_string($from_db) && trim($from_db) !== '') {
        $reg->qr_url = trim($from_db);
        return $reg->qr_url;
    }

    if (!function_exists('ve_generate_qr_code')) {
        error_log('Venture Events: ve_generate_qr_code missing — cannot build ticket QR');
        return false;
    }

    $ticket_url = ve_get_ticket_url($reg);
    $qr_url     = ve_generate_qr_code($ticket_url, (int) $reg->id);
    if (!$qr_url) {
        // One immediate retry (transient filesystem / race)
        $qr_url = ve_generate_qr_code($ticket_url, (int) $reg->id);
    }
    if (!$qr_url) {
        return false;
    }

    $qr_updated = $wpdb->update(
        $table_name,
        ['qr_url' => $qr_url],
        ['id' => (int) $reg->id],
        ['%s'],
        ['%d']
    );
    if ($qr_updated === false) {
        error_log(
            "Venture Events: QR URL save FAILED for registration #{$reg->id}. "
            . "DB error: {$wpdb->last_error}"
        );
        // Still use URL in this request if the file exists
    }

    $reg->qr_url = $qr_url;
    return $qr_url;
}

/**
 * Resolve filesystem path for a registration QR image (for email attach/embed).
 *
 * @param object $reg
 * @return string|null Absolute path or null
 */
function ve_get_registration_qr_filepath($reg) {
    $id = (int) ($reg->id ?? 0);
    if ($id <= 0) {
        return null;
    }
    $upload_dir = wp_upload_dir();
    $path       = $upload_dir['basedir'] . '/venture-qrcodes/ticket-' . $id . '.png';
    return file_exists($path) ? $path : null;
}

/**
 * Permanently delete every registration row for an event (people + packages).
 *
 * Also removes matching QR PNG files under uploads/venture-qrcodes/.
 * Does not touch Zoho invoices, gateway records, or event post meta (tiers).
 *
 * @param int $event_id ve_event post ID
 * @return array{deleted:int,qr_files_removed:int,error:?string}
 */
function ve_clear_event_registrations($event_id) {
    $event_id = absint($event_id);
    $result   = [
        'deleted'          => 0,
        'qr_files_removed' => 0,
        'error'            => null,
    ];

    if ($event_id < 1 || get_post_type($event_id) !== 've_event') {
        $result['error'] = 'invalid_event';
        return $result;
    }

    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';

    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT id FROM $table WHERE event_id = %d",
        $event_id
    ));
    if (!is_array($ids)) {
        $ids = [];
    }

    $upload_dir = wp_upload_dir();
    $qr_dir     = trailingslashit($upload_dir['basedir']) . 'venture-qrcodes/';
    foreach ($ids as $id) {
        $id   = (int) $id;
        $path = $qr_dir . 'ticket-' . $id . '.png';
        if (is_string($path) && file_exists($path) && is_file($path)) {
            if (@unlink($path)) {
                $result['qr_files_removed']++;
            }
        }
    }

    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table WHERE event_id = %d",
        $event_id
    ));

    if ($deleted === false) {
        $result['error'] = 'db_error';
        error_log(
            "Venture Events: Failed to clear registrations for event_id={$event_id}: {$wpdb->last_error}"
        );
        return $result;
    }

    $result['deleted'] = (int) $deleted;
    error_log(
        "Venture Events: Cleared {$result['deleted']} registration(s) for event_id={$event_id} "
        . "(qr_files_removed={$result['qr_files_removed']}) by user_id="
        . (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0)
    );

    return $result;
}

/**
 * Count registration rows for an event (all line types and statuses).
 *
 * @param int $event_id
 * @return int
 */
function ve_count_event_registrations($event_id) {
    global $wpdb;
    $event_id = absint($event_id);
    if ($event_id < 1) {
        return 0;
    }
    $table = $wpdb->prefix . 've_registrations';
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE event_id = %d",
        $event_id
    ));
}

function ve_send_ticket_email($reg, $event_id, $tier_name) {
    // Never send a ticket without a QR image (Leon: "available shortly" is not acceptable)
    if (empty($reg->qr_url)) {
        ve_ensure_registration_qr($reg);
    }
    if (empty($reg->qr_url)) {
        error_log(
            'Venture Events: Refusing to send ticket email without QR for registration #'
            . (int) ($reg->id ?? 0) . ' to ' . (string) ($reg->email ?? '')
        );
        return false;
    }

    $event_title = get_the_title($event_id);
    $subject     = 'Your Ticket for ' . $event_title;

    $qr_path = ve_get_registration_qr_filepath($reg);
    // Prefer CID embed so clients show the QR without loading remote images
    $qr_img  = $qr_path
        ? '<img src="cid:ve_ticket_qr" alt="QR Code" style="max-width:300px; border:1px solid #ddd;">'
        : '<img src="' . esc_url($reg->qr_url) . '" alt="QR Code" style="max-width:300px; border:1px solid #ddd;">';

    $price_note = 'N$ ' . number_format((float) $reg->price, 2);
    if (!empty($reg->included_free)) {
        $price_note .= ' — included with package';
    }

    $ticket_url = ve_get_ticket_url($reg);
    $message    = '
    <h2>Your Ticket for ' . esc_html($event_title) . '</h2>
    <p><strong>Tier:</strong> ' . esc_html($tier_name) . ' (' . esc_html($price_note) . ')</p>
    <p><strong>Name:</strong> ' . esc_html($reg->first_name . ' ' . $reg->last_name) . '</p>
    <p><strong>Organisation:</strong> ' . esc_html($reg->organisation ?: '—') . '</p>
    <p><strong>Internal Reference:</strong> ' . esc_html($reg->payment_reference) . '</p>
    ' . $qr_img . '
    <p>
        <a href="' . esc_url($ticket_url) . '" 
           style="background:#d1d741;color:#ffffff;padding:12px 24px;text-decoration:none;border-radius:0;border:1px solid #d1d741;font-family:Effra,Arial,sans-serif;">
            View Ticket Online
        </a>
    </p>';

    $from_email = ve_get_ticket_mail_from();
    $from_name  = ve_get_ticket_mail_from_name();

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        sprintf('From: %s <%s>', $from_name, $from_email),
        sprintf('Reply-To: %s <%s>', $from_name, $from_email),
    ];

    // Also force wp_mail_from* for this send only — some SMTP plugins ignore From headers.
    $from_filter = static function () use ($from_email) {
        return $from_email;
    };
    $name_filter = static function () use ($from_name) {
        return $from_name;
    };

    add_filter('wp_mail_from', $from_filter, 999);
    add_filter('wp_mail_from_name', $name_filter, 999);

    // Inline CID so Gmail/etc. show the QR without remote image load
    $phpmailer_cb = null;
    if ($qr_path) {
        $phpmailer_cb = static function ($phpmailer) use ($qr_path) {
            try {
                $phpmailer->addEmbeddedImage($qr_path, 've_ticket_qr', 'ticket-qr.png', 'base64', 'image/png');
            } catch (Exception $e) {
                error_log('Venture Events: Failed to embed QR in ticket email: ' . $e->getMessage());
            }
        };
        add_action('phpmailer_init', $phpmailer_cb, 20);
    }

    $sent = wp_mail($reg->email, $subject, $message, $headers);

    if ($phpmailer_cb) {
        remove_action('phpmailer_init', $phpmailer_cb, 20);
    }
    remove_filter('wp_mail_from', $from_filter, 999);
    remove_filter('wp_mail_from_name', $name_filter, 999);

    if (!$sent) {
        error_log('Venture Events: wp_mail failed for ticket to ' . $reg->email . ' (from ' . $from_email . ')');
    }

    return $sent;
}

// ====================== LEGACY / HELPER FUNCTIONS (kept for compatibility) ======================

/**
 * Public ticket page URL encoded into QR codes and emails.
 *
 * @param object $reg Registration row (needs id + email)
 * @return string
 */
function ve_get_ticket_url($reg) {
    $id    = (int) ($reg->id ?? 0);
    $email = (string) ($reg->email ?? '');
    $token = wp_hash($id . '|' . $email);

    return add_query_arg(
        [
            'id'    => $id,
            'token' => $token,
        ],
        home_url('/read-qr/')
    );
}

function ve_get_registration($id) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
}

/**
 * HMAC token embedded in ticket QR / email links.
 *
 * @param object|array $reg
 * @return string
 */
function ve_ticket_token_for_reg($reg) {
    $id    = (int) (is_array($reg) ? ($reg['id'] ?? 0) : ($reg->id ?? 0));
    $email = (string) (is_array($reg) ? ($reg['email'] ?? '') : ($reg->email ?? ''));
    return (string) wp_hash($id . '|' . $email);
}

/**
 * @param object|null $reg
 * @param string      $token
 * @return bool
 */
function ve_verify_ticket_token($reg, $token) {
    if (!$reg || $token === '' || $token === null) {
        return false;
    }
    return hash_equals(ve_ticket_token_for_reg($reg), (string) $token);
}

/**
 * Roles allowed to use the gate scanner (in addition to administrators).
 * - event_gate: plugin-specific door role (optional dedicated accounts)
 * - staff: Venture-Media child theme role
 * - shop_manager: WooCommerce "Shop manager" (site label often "Shop keeper")
 *
 * @return string[]
 */
function ve_gate_scan_allowed_roles() {
    return ['event_gate', 'staff', 'shop_manager'];
}

/**
 * Whether the current user may run gate check-in.
 *
 * @return bool
 */
function ve_user_can_gate_scan() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (current_user_can('manage_options')) {
        return true;
    }
    $roles = (array) (wp_get_current_user()->roles ?? []);
    foreach (ve_gate_scan_allowed_roles() as $role) {
        if (in_array($role, $roles, true)) {
            return true;
        }
    }
    return false;
}

/**
 * Display name for the user who first checked a guest in.
 *
 * @param int $user_id
 * @return string
 */
function ve_gate_user_display_name($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }
    $user = get_userdata($user_id);
    if (!$user) {
        return '';
    }
    $name = trim((string) $user->display_name);
    return $name !== '' ? $name : (string) $user->user_login;
}

/**
 * Format check-in time for gate UI (e.g. "10:04 am").
 *
 * @param string $mysql_datetime
 * @return string
 */
function ve_format_gate_time($mysql_datetime) {
    if (empty($mysql_datetime)) {
        return '';
    }
    return date_i18n('g:i a', strtotime($mysql_datetime));
}

/**
 * Extract registration id + token from a scanned QR payload (full URL or query string).
 *
 * @param string $raw
 * @return array{id:int,token:string}
 */
function ve_parse_scanned_ticket_payload($raw) {
    $raw = trim((string) $raw);
    $id  = 0;
    $token = '';

    if ($raw === '') {
        return ['id' => 0, 'token' => ''];
    }

    // Full URL or path with query
    $query = '';
    if (strpos($raw, '?') !== false) {
        $parts = wp_parse_url($raw);
        if (is_array($parts) && !empty($parts['query'])) {
            $query = $parts['query'];
        } else {
            // Fallback: everything after first ?
            $query = substr($raw, strpos($raw, '?') + 1);
        }
    } elseif (strpos($raw, 'id=') !== false) {
        $query = $raw;
    }

    if ($query !== '') {
        parse_str($query, $args);
        $id    = absint($args['id'] ?? 0);
        $token = isset($args['token']) ? sanitize_text_field(wp_unslash((string) $args['token'])) : '';
    }

    return ['id' => $id, 'token' => $token];
}

/**
 * Mark registration as entered (first time only at DB level if already set — callers should check).
 *
 * @param int      $reg_id
 * @param int|null $user_id  WP user who scanned; defaults to current user when logged in
 * @return bool
 */
function ve_mark_as_entered($reg_id, $user_id = null) {
    global $wpdb;
    $table  = $wpdb->prefix . 've_registrations';
    $reg_id = absint($reg_id);
    if (!$reg_id) {
        return false;
    }

    if ($user_id === null && is_user_logged_in()) {
        $user_id = get_current_user_id();
    }
    $user_id = absint($user_id);

    $data = [
        'entered_at' => current_time('mysql'),
    ];
    // Only set entered_by when we have a user; never overwrite an existing entered_by via this helper
    // when already entered (callers pass only on first entry).
    if ($user_id > 0) {
        $data['entered_by'] = $user_id;
    }

    $updated = $wpdb->update($table, $data, ['id' => $reg_id]);
    return $updated !== false;
}

/**
 * Gate check-in for one ticket, scoped to a shortcode event.
 *
 * @param int    $event_id Shortcode event id
 * @param int    $reg_id
 * @param string $token
 * @return array{ok:bool,code:string,headline:string,tier_name?:string,guest_name?:string,entry_line?:string,message?:string,status?:string}
 */
function ve_gate_process_check_in($event_id, $reg_id, $token) {
    $event_id = absint($event_id);
    $reg_id   = absint($reg_id);
    $token    = (string) $token;

    if (!$event_id || get_post_type($event_id) !== 've_event') {
        return [
            'ok'       => false,
            'code'     => 'bad_event',
            'headline' => 'Invalid ticket',
            'message'  => 'Scanner is not configured for a valid event.',
        ];
    }

    if (!$reg_id || $token === '') {
        return [
            'ok'       => false,
            'code'     => 'bad_payload',
            'headline' => 'Invalid ticket',
            'message'  => 'Could not read ticket code.',
        ];
    }

    $reg = ve_get_registration($reg_id);
    if (!$reg || !ve_verify_ticket_token($reg, $token)) {
        return [
            'ok'       => false,
            'code'     => 'invalid',
            'headline' => 'Invalid ticket',
            'message'  => 'This ticket is not valid.',
        ];
    }

    if (ve_is_package_registration($reg)) {
        return [
            'ok'       => false,
            'code'     => 'package',
            'headline' => 'Invalid ticket',
            'message'  => 'This code is a package purchase, not a personal ticket.',
        ];
    }

    if ((int) $reg->event_id !== $event_id) {
        return [
            'ok'       => false,
            'code'     => 'wrong_event',
            'headline' => 'Invalid ticket',
            'message'  => 'This ticket is for a different event.',
        ];
    }

    $status = (string) ($reg->status ?? '');
    if (!ve_registration_status_allows_entry($status)) {
        return [
            'ok'       => false,
            'code'     => 'not_paid',
            'headline' => 'Invalid ticket',
            'message'  => 'This ticket has not been paid.',
        ];
    }

    $tier_name  = ve_registration_tier_label($reg);
    $guest_name = trim((string) ($reg->first_name ?? '') . ' ' . (string) ($reg->last_name ?? ''));
    if ($guest_name === '') {
        $guest_name = '—';
    }

    // Already entered — do not overwrite entered_at / entered_by
    if (!empty($reg->entered_at)) {
        $by_name = ve_gate_user_display_name((int) ($reg->entered_by ?? 0));
        $time    = ve_format_gate_time($reg->entered_at);
        if ($by_name !== '') {
            $entry_line = 'Entered by ' . $by_name . ' - ' . $time;
        } else {
            $entry_line = 'Entered - ' . $time;
        }

        return [
            'ok'         => true,
            'code'       => 'already',
            'status'     => 'already',
            'headline'   => 'Valid ticket',
            'tier_name'  => $tier_name,
            'guest_name' => $guest_name,
            'entry_line' => $entry_line,
        ];
    }

    // First entry
    ve_mark_as_entered($reg_id, get_current_user_id());
    $reg = ve_get_registration($reg_id);
    $by_name = ve_gate_user_display_name((int) ($reg->entered_by ?? get_current_user_id()));
    $time    = ve_format_gate_time($reg->entered_at ?? current_time('mysql'));

    return [
        'ok'              => true,
        'code'            => 'first',
        'status'          => 'first',
        'headline'        => 'Valid ticket',
        'tier_name'       => $tier_name,
        'guest_name'      => $guest_name,
        'entry_line'      => 'Entering - ' . $time,
        'entered_by_name' => $by_name,
    ];
}

// ====================== GATE SCAN AJAX ======================
add_action('wp_ajax_ve_gate_check_in', 've_ajax_gate_check_in');

/**
 * AJAX: Gate staff check-in (logged-in only; no nopriv).
 */
function ve_ajax_gate_check_in() {
    if (!check_ajax_referer('ve_gate_scan_nonce', 'nonce', false)) {
        wp_send_json_error([
            'headline' => 'Invalid ticket',
            'message'  => 'Security check failed. Reload the page and try again.',
            'code'     => 'nonce',
        ], 403);
    }

    if (!ve_user_can_gate_scan()) {
        wp_send_json_error([
            'headline' => 'Access denied',
            'message'  => 'You must be logged in with a Staff, Shop manager, or Gate account to scan tickets.',
            'code'     => 'forbidden',
        ], 403);
    }

    $event_id = absint($_POST['event_id'] ?? 0);
    $reg_id   = absint($_POST['id'] ?? 0);
    $token    = isset($_POST['token']) ? sanitize_text_field(wp_unslash((string) $_POST['token'])) : '';
    $raw      = isset($_POST['raw']) ? wp_unslash((string) $_POST['raw']) : '';

    if ((!$reg_id || $token === '') && $raw !== '') {
        $parsed = ve_parse_scanned_ticket_payload($raw);
        $reg_id = $parsed['id'];
        $token  = $parsed['token'];
    }

    $result = ve_gate_process_check_in($event_id, $reg_id, $token);

    if (empty($result['ok'])) {
        wp_send_json_error([
            'headline' => $result['headline'] ?? 'Invalid ticket',
            'message'  => $result['message'] ?? '',
            'code'     => $result['code'] ?? 'error',
        ]);
    }

    wp_send_json_success([
        'status'          => $result['status'] ?? 'first',
        'code'            => $result['code'] ?? 'first',
        'headline'        => $result['headline'] ?? 'Valid ticket',
        'tier_name'       => $result['tier_name'] ?? '',
        'guest_name'      => $result['guest_name'] ?? '',
        'entry_line'      => $result['entry_line'] ?? '',
        'entered_by_name' => $result['entered_by_name'] ?? '',
    ]);
}