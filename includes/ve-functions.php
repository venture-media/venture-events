<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Core Functions
 * Version: 0.9.6
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
        KEY invoice_number (invoice_number)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Upgrade existing installations (adds missing columns without destroying data)
    $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    
    $needed = [
        'tier_name'      => "ALTER TABLE $table_name ADD COLUMN tier_name varchar(100) DEFAULT NULL AFTER tier",
        'transaction_id' => "ALTER TABLE $table_name ADD COLUMN transaction_id varchar(100) DEFAULT NULL AFTER status",
        'invoice_number' => "ALTER TABLE $table_name ADD COLUMN invoice_number varchar(50) DEFAULT NULL AFTER transaction_id",
        'paid_at'        => "ALTER TABLE $table_name ADD COLUMN paid_at datetime DEFAULT NULL AFTER invoice_number",
        'entered_at'     => "ALTER TABLE $table_name ADD COLUMN entered_at datetime DEFAULT NULL AFTER paid_at",
        'qr_url'         => "ALTER TABLE $table_name ADD COLUMN qr_url varchar(255) DEFAULT NULL AFTER entered_at",
        'internal_reference' => "ALTER TABLE $table_name ADD COLUMN internal_reference varchar(100) DEFAULT NULL AFTER qr_url",
        'sage_invoice'   => "ALTER TABLE $table_name ADD COLUMN sage_invoice varchar(100) DEFAULT NULL AFTER internal_reference",
    ];

    foreach ($needed as $col => $alter_sql) {
        if (!in_array($col, $existing_columns, true)) {
            $wpdb->query($alter_sql);
            error_log("Venture Events: Added missing column '$col' to $table_name");
        }
    }

    error_log("Venture Events: Table $table_name verified/updated successfully");
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

// ====================== PENDING REGISTRATION (NEW) ======================
add_action('wp_ajax_ve_save_pending_registrations', 've_handle_pending_registrations');
add_action('wp_ajax_nopriv_ve_save_pending_registrations', 've_handle_pending_registrations');

function ve_handle_pending_registrations() {
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 've_registration_nonce')) {
        wp_send_json_error(['message' => 'Security check failed.']);
    }

    $event_id = intval($_POST['event_id'] ?? 0);
    $tickets  = $_POST['tickets'] ?? [];

    if (!$event_id || empty($tickets) || !is_array($tickets)) {
        wp_send_json_error(['message' => 'Invalid data received.']);
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 've_registrations';

        // One shared payment reference + invoice for the whole batch
        // Format: VE-YYYYMMDD-XXXX (incremental daily counter – easy to audit gaps)
        $today = date('Ymd');
        $option_key = 've_last_payment_ref_' . $today;

        $last_number = (int) get_option($option_key, 0);
        $next_number = $last_number + 1;

        // Persist the counter
        update_option($option_key, $next_number, false);

        $payment_reference = 'VE-' . $today . '-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);

    $total_amount = 0;
    foreach ($tickets as $ticket) {
        $total_amount += floatval($ticket['price'] ?? 0);
    }

    $inserted = 0;
    foreach ($tickets as $ticket) {
        $tier_key  = sanitize_text_field($ticket['tier'] ?? '');
        $tier_name = ve_get_tier_name($event_id, $tier_key);

        $data = [
            'event_id'          => $event_id,
            'payment_reference' => $payment_reference,
            'first_name'        => sanitize_text_field($ticket['first_name'] ?? ''),
            'last_name'         => sanitize_text_field($ticket['last_name'] ?? ''),
            'organisation'      => sanitize_text_field($ticket['organisation'] ?? ''),
            'phone'             => sanitize_text_field($ticket['phone'] ?? ''),
            'email'             => sanitize_email($ticket['email'] ?? ''),
            'tier'              => $tier_key,
            'tier_name'         => $tier_name,
            'price'             => floatval($ticket['price'] ?? 0),
            'status'            => 'pending',
            'created_at'        => current_time('mysql'),
            'internal_reference'=> $payment_reference,
            // Billing info...
            'billing_company'   => sanitize_text_field($_POST['billing_company'] ?? ''),
            'billing_address'   => sanitize_textarea_field($_POST['billing_address'] ?? ''),
            'billing_country'   => sanitize_text_field($_POST['billing_country'] ?? 'NA'),
            'accounting_email'  => sanitize_email($_POST['accounting_email'] ?? ''),
            'billing_notes'     => sanitize_textarea_field($_POST['billing_notes'] ?? ''),
        ];

        if ($wpdb->insert($table_name, $data)) {
            $inserted++;
        } else {
            error_log('Venture Events: insert failed: ' . $wpdb->last_error);
        }
    }

    if ($inserted === 0) {
        wp_send_json_error(['message' => 'Failed to save any registrations.']);
    }

    wp_send_json_success([
        'payment_reference' => $payment_reference,
        'total_amount'      => $total_amount,
        'message'           => sprintf('%d registration(s) saved successfully', $inserted)
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

    // Update status + QR immediately
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

    // Send ticket emails (reload rows so qr_url / tier_name are present on the object)
    $fresh_regs = ve_get_registrations_by_reference($payment_reference) ?: $registrations;
    foreach ($fresh_regs as $reg) {
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
    error_log("Venture Events: Ticket emails sent for ref={$payment_reference}");

    // Zoho invoice (best-effort, non-blocking)
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

    $message = '
    <h2>Your Ticket for ' . esc_html($event_title) . '</h2>
    <p><strong>Tier:</strong> ' . esc_html($tier_name) . ' (N$ ' . number_format($reg->price, 2) . ')</p>
    <p><strong>Name:</strong> ' . esc_html($reg->first_name . ' ' . $reg->last_name) . '</p>
    <p><strong>Organisation:</strong> ' . esc_html($reg->organisation ?: '—') . '</p>
    <p><strong>Internal Reference:</strong> ' . esc_html($reg->payment_reference) . '</p>
    ' . $qr_img . '
    <p>
        <a href="' . esc_url(ve_get_ticket_url($reg)) . '" 
           style="background:#f48c26;color:white;padding:12px 24px;text-decoration:none;border-radius:10px;">
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