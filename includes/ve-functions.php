<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Core Functions
 * Version: 0.9.8
 */

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
        'qr_url'             => "ALTER TABLE $table_name ADD COLUMN qr_url varchar(255) DEFAULT NULL AFTER entered_at",
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
 * @return array<string,array{name:string,price:float,free_tickets:int,free_tier_key:string}>
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
 * @return array{name:string,price:float,free_tickets:int,free_tier_key:string}|null
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
    ];
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
        ve_handle_pending_special_registrations($event_id, $billing);
        return;
    }

    ve_handle_pending_normal_registrations($event_id, $billing);
}

/**
 * Normal shortcode checkout: person tickets only.
 *
 * @param int   $event_id
 * @param array $billing
 */
function ve_handle_pending_normal_registrations($event_id, array $billing) {
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
            'status'            => 'pending',
            'created_at'        => current_time('mysql'),
        ]);
    }

    if (empty($rows)) {
        wp_send_json_error(['message' => 'Invalid data received.']);
    }

    ve_insert_pending_batch($rows);
}

/**
 * Special shortcode checkout: one package + free people + optional paid extras.
 *
 * @param int   $event_id
 * @param array $billing
 */
function ve_handle_pending_special_registrations($event_id, array $billing) {
    if (trim((string) ($billing['billing_company'] ?? '')) === '') {
        wp_send_json_error(['message' => 'Please enter a company / organisation name.']);
    }

    $special_key = sanitize_text_field(wp_unslash($_POST['special_tier'] ?? ''));
    $package     = ve_get_special_tier($event_id, $special_key);
    if (!$package || $package['name'] === '' || $package['price'] <= 0) {
        wp_send_json_error(['message' => 'Please select a valid package.']);
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
        'status'            => 'pending',
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
            'status'            => 'pending',
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
            'status'            => 'pending',
            'created_at'        => current_time('mysql'),
        ]);
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

        // Generate QR code (pretty path; ve_is_read_qr_request() also handles rewrite-miss 404s)
        $ticket_url = ve_get_ticket_url($reg);
        $qr_url     = ve_generate_qr_code($ticket_url, $reg->id);
        if ($qr_url) {
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
            } else {
                error_log("Venture Events: QR generated for registration #{$reg->id}");
            }
        } else {
            error_log("Venture Events: QR generation FAILED for registration #{$reg->id}");
        }
    }

    // Send ticket emails only for people (reload rows so qr_url / tier_name are present)
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
        ve_send_ticket_email($reg, $reg->event_id, ve_registration_tier_label($reg));
    }
    error_log("Venture Events: Ticket emails sent for person rows on ref={$payment_reference}");

    // Zoho invoice (best-effort, non-blocking) — all lines including packages & free @ 0
    $master_reg = $registrations[0];
    $master_reg->line_items = $registrations;

    $invoice = ve_generate_zoho_invoice($master_reg, $registrations[0]->event_id);

    if ($invoice && !empty($invoice['invoice_number'])) {
        $invoice_number = $invoice['invoice_number'];
        foreach ($registrations as $reg) {
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

function ve_send_ticket_email($reg, $event_id, $tier_name) {
    $event_title = get_the_title($event_id);

    $subject = 'Your Ticket for ' . $event_title;

    $qr_img = $reg->qr_url
        ? '<img src="' . esc_url($reg->qr_url) . '" alt="QR Code" style="max-width:300px; border:1px solid #ddd;">'
        : '<p><em>QR code will be available shortly.</em></p>';

    $price_note = 'N$ ' . number_format((float) $reg->price, 2);
    if (!empty($reg->included_free)) {
        $price_note .= ' — included with package';
    }

    $message = '
    <h2>Your Ticket for ' . esc_html($event_title) . '</h2>
    <p><strong>Tier:</strong> ' . esc_html($tier_name) . ' (' . esc_html($price_note) . ')</p>
    <p><strong>Name:</strong> ' . esc_html($reg->first_name . ' ' . $reg->last_name) . '</p>
    <p><strong>Organisation:</strong> ' . esc_html($reg->organisation ?: '—') . '</p>
    <p><strong>Internal Reference:</strong> ' . esc_html($reg->payment_reference) . '</p>
    ' . $qr_img . '
    <p>
        <a href="' . esc_url(ve_get_ticket_url($reg)) . '" 
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

    $sent = wp_mail($reg->email, $subject, $message, $headers);

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

function ve_mark_as_entered($reg_id) {
    global $wpdb;
    $table = $wpdb->prefix . 've_registrations';
    $wpdb->update($table, ['entered_at' => current_time('mysql')], ['id' => $reg_id]);
}