<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events – Zoho Books Integration
 * Version: 0.9.7
 *
 * WEB_POS customers: exactly one contact person (accounting_email),
 * create on new / update on reuse; invoices associate + email that person.
 */

/**
 * Zoho Books API host (multi-DC: .com / .eu / .in / .com.au / .jp / .uk / .ca / .sa).
 */
function ve_zoho_api_base() {
    $base = trim((string) get_option('ve_zoho_api_base', 'https://www.zohoapis.com'));
    if ($base === '') {
        $base = 'https://www.zohoapis.com';
    }
    return untrailingslashit($base);
}

/**
 * Zoho Accounts host used for OAuth token refresh.
 */
function ve_zoho_accounts_base() {
    $base = trim((string) get_option('ve_zoho_accounts_base', 'https://accounts.zoho.com'));
    if ($base === '') {
        $base = 'https://accounts.zoho.com';
    }
    return untrailingslashit($base);
}

/**
 * Build a Zoho Books v3 URL.
 *
 * @param string $path  e.g. /contacts or /bankaccounts
 * @param array  $query Query args (organization_id added automatically if missing and $org_id given)
 */
function ve_zoho_books_url($path, array $query = [], $org_id = null) {
    $path = '/' . ltrim((string) $path, '/');
    if ($org_id === null) {
        $org_id = get_option('ve_zoho_org_id');
    }
    if ($org_id && empty($query['organization_id'])) {
        $query['organization_id'] = $org_id;
    }
    $url = ve_zoho_api_base() . '/books/v3' . $path;
    return $query ? add_query_arg($query, $url) : $url;
}

/**
 * OAuth scopes required for Venture Events (document for re-consent).
 *
 * Note: Zoho refresh tokens come from access_type=offline on the authorize URL,
 * not from an "offline_access" scope string.
 *
 * @return string space-separated scope string for authorize URL
 */
function ve_zoho_required_scopes() {
    // Prefer full access. If the org forbids it, grant at least the modules we use:
    // contacts, invoices, customerpayments, banking (or accountants for chartofaccounts).
    $scopes = 'ZohoBooks.fullaccess.all';
    return apply_filters('ve_zoho_required_scopes', $scopes);
}

/**
 * Minimal scopes if fullaccess is not available on the API client.
 *
 * @return string
 */
function ve_zoho_minimal_scopes() {
    $scopes = implode(',', [
        'ZohoBooks.contacts.CREATE',
        'ZohoBooks.contacts.UPDATE',
        'ZohoBooks.contacts.READ',
        'ZohoBooks.invoices.CREATE',
        'ZohoBooks.invoices.UPDATE',
        'ZohoBooks.invoices.READ',
        'ZohoBooks.customerpayments.CREATE',
        'ZohoBooks.customerpayments.READ',
        'ZohoBooks.banking.READ',
        'ZohoBooks.accountants.READ',
    ]);
    return apply_filters('ve_zoho_minimal_scopes', $scopes);
}

/**
 * Human-readable re-auth instructions for the admin UI.
 */
function ve_zoho_oauth_help_text() {
    $accounts = ve_zoho_accounts_base();
    $client   = get_option('ve_zoho_client_id', 'YOUR_CLIENT_ID');
    $scopes   = ve_zoho_required_scopes();
    $minimal  = ve_zoho_minimal_scopes();

    // Zoho often wants comma-separated scopes in the authorize URL
    $scope_q = rawurlencode(str_replace(' ', ',', $scopes));

    $auth_url = $accounts . '/oauth/v2/auth'
        . '?scope=' . $scope_q
        . '&client_id=' . rawurlencode((string) $client)
        . '&response_type=code'
        . '&access_type=offline'
        . '&prompt=consent'
        . '&redirect_uri=' . rawurlencode('https://www.zoho.com/books');

    return [
        'scopes'    => $scopes,
        'minimal'   => $minimal,
        'auth_url'  => $auth_url,
        'accounts'  => $accounts,
    ];
}

/**
 * GET helper for Zoho Books contacts list.
 *
 * @return array|null Decoded body or null on failure
 */
function ve_zoho_contacts_request($token, $org_id, array $query_args) {
    $url = ve_zoho_books_url('/contacts', $query_args, $org_id);

    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
    ]);

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: contacts request failed: ' . $response->get_error_message() . ' | query=' . wp_json_encode($query_args));
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code < 200 || $code >= 300) {
        error_log(
            'Venture Events Zoho: contacts request HTTP ' . $code
            . ' | query=' . wp_json_encode($query_args)
            . ' | body=' . wp_remote_retrieve_body($response)
        );
        return null;
    }

    return is_array($body) ? $body : null;
}

/**
 * Find a matching contact in a Zoho contacts list response.
 */
function ve_zoho_match_contact_from_list($body, $desired_contact_name, $base_name, $email) {
    if (empty($body['contacts']) || !is_array($body['contacts'])) {
        return null;
    }

    $desired_lower = strtolower(trim($desired_contact_name));
    $base_lower    = strtolower(trim($base_name));
    $email_lower   = strtolower(trim((string) $email));

    // 1) Exact contact_name match (preferred)
    foreach ($body['contacts'] as $contact) {
        $name = strtolower(trim($contact['contact_name'] ?? ''));
        if ($name !== '' && $name === $desired_lower) {
            return $contact;
        }
    }

    // 2) Exact email match among WEB_POS contacts
    if ($email_lower !== '') {
        foreach ($body['contacts'] as $contact) {
            $c_email = strtolower(trim($contact['email'] ?? ''));
            $c_name  = $contact['contact_name'] ?? '';
            if ($c_email === $email_lower && stripos($c_name, 'WEB_POS') !== false) {
                return $contact;
            }
        }
    }

    // 3) Fuzzy: WEB_POS + base company/person name
    if ($base_lower !== '') {
        foreach ($body['contacts'] as $contact) {
            $c_name = $contact['contact_name'] ?? '';
            if (stripos($c_name, 'WEB_POS') !== false && stripos($c_name, $base_name) !== false) {
                return $contact;
            }
        }
    }

    return null;
}

/**
 * Look up an existing WEB_POS contact using multiple Zoho search strategies.
 *
 * Important: Zoho's contact_name filter is NOT a reliable "contains WEB_POS" scan.
 * Searching only for "WEB_POS" often returns nothing useful, while create then fails
 * with code 3062 ("customer already exists").
 */
function ve_zoho_find_existing_contact($token, $org_id, $desired_contact_name, $base_name, $email) {
    // Strategy A: search by full desired name (best for reuse)
    $body = ve_zoho_contacts_request($token, $org_id, [
        'contact_name' => $desired_contact_name,
        'per_page'     => 25,
    ]);
    $match = ve_zoho_match_contact_from_list($body, $desired_contact_name, $base_name, $email);
    if ($match) {
        error_log("Venture Events Zoho: ✅ Found contact via name search → contact_id={$match['contact_id']} (name: {$match['contact_name']})");
        return $match['contact_id'];
    }

    // Strategy B: search by accounting email
    if ($email !== '') {
        $body = ve_zoho_contacts_request($token, $org_id, [
            'email'    => $email,
            'per_page' => 50,
        ]);
        $match = ve_zoho_match_contact_from_list($body, $desired_contact_name, $base_name, $email);
        if ($match) {
            error_log("Venture Events Zoho: ✅ Found contact via email search → contact_id={$match['contact_id']} (name: {$match['contact_name']})");
            return $match['contact_id'];
        }
    }

    // Strategy C: search by company / base name (without relying on WEB_POS alone)
    if ($base_name !== '') {
        $body = ve_zoho_contacts_request($token, $org_id, [
            'contact_name' => $base_name,
            'per_page'     => 50,
        ]);
        $match = ve_zoho_match_contact_from_list($body, $desired_contact_name, $base_name, $email);
        if ($match) {
            error_log("Venture Events Zoho: ✅ Found contact via base-name search → contact_id={$match['contact_id']} (name: {$match['contact_name']})");
            return $match['contact_id'];
        }

        $body = ve_zoho_contacts_request($token, $org_id, [
            'company_name' => $base_name,
            'per_page'     => 50,
        ]);
        $match = ve_zoho_match_contact_from_list($body, $desired_contact_name, $base_name, $email);
        if ($match) {
            error_log("Venture Events Zoho: ✅ Found contact via company_name search → contact_id={$match['contact_id']} (name: {$match['contact_name']})");
            return $match['contact_id'];
        }
    }

    return null;
}

/**
 * Billing email from the ticket form (Accounting Email field).
 */
function ve_zoho_billing_email_from_reg($reg) {
    $email = sanitize_email($reg->accounting_email ?? '');
    if ($email === '' && !empty($reg->email)) {
        // Fallback to first ticket holder email if accounting email missing
        $email = sanitize_email($reg->email);
    }
    return $email;
}

/**
 * Contact person first/last name from registration (Accounts Payable fallback).
 *
 * @return array{0:string,1:string} [first_name, last_name]
 */
function ve_zoho_person_names_from_reg($reg) {
    $first = trim((string) ($reg->first_name ?? ''));
    $last  = trim((string) ($reg->last_name ?? ''));
    if ($first === '') {
        return ['Accounts', 'Payable'];
    }
    return [$first, $last];
}

/**
 * GET a single Zoho contact (includes contact_persons).
 *
 * @return array|null contact object
 */
function ve_zoho_get_contact($token, $org_id, $contact_id) {
    $url = ve_zoho_books_url('/contacts/' . rawurlencode((string) $contact_id), [], $org_id);
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
    ]);
    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: get contact failed: ' . $response->get_error_message());
        return null;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['contact'] ?? null;
}

/**
 * Extract contact_person_id from Zoho create/update responses.
 * Docs sometimes return contact_person as an object, sometimes as an array.
 *
 * @param array|null $body
 * @return string|null
 */
function ve_zoho_extract_contact_person_id($body) {
    if (!is_array($body)) {
        return null;
    }

    if (!empty($body['contact_person']['contact_person_id'])) {
        return (string) $body['contact_person']['contact_person_id'];
    }

    if (!empty($body['contact_person'][0]['contact_person_id'])) {
        return (string) $body['contact_person'][0]['contact_person_id'];
    }

    if (!empty($body['contact']['contact_persons'][0]['contact_person_id'])) {
        return (string) $body['contact']['contact_persons'][0]['contact_person_id'];
    }

    if (!empty($body['contact_persons'][0]['contact_person_id'])) {
        return (string) $body['contact_persons'][0]['contact_person_id'];
    }

    return null;
}

/**
 * Pick the single canonical contact person for a WEB_POS customer.
 * Prefers primary; otherwise the first person. Never invents a second when one exists.
 *
 * @param array $persons
 * @return array|null person row
 */
function ve_zoho_canonical_contact_person(array $persons) {
    if (!$persons) {
        return null;
    }
    foreach ($persons as $person) {
        if (!empty($person['is_primary_contact']) && !empty($person['contact_person_id'])) {
            return $person;
        }
    }
    foreach ($persons as $person) {
        if (!empty($person['contact_person_id'])) {
            return $person;
        }
    }
    return null;
}

/**
 * Sync top-level contact email (list/search views) with accounting email.
 */
function ve_zoho_sync_contact_email($token, $org_id, $contact_id, $email, $contact_name = '') {
    $update = ['email' => $email];
    if ($contact_name !== '') {
        $update['contact_name'] = $contact_name;
    }
    $response = wp_remote_request(
        ve_zoho_books_url('/contacts/' . rawurlencode((string) $contact_id), [], $org_id),
        [
            'method'  => 'PUT',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($update),
        ]
    );
    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: contact email sync WP_Error: ' . $response->get_error_message());
        return;
    }
    error_log(
        'Venture Events Zoho: contact email sync HTTP '
        . wp_remote_retrieve_response_code($response)
        . ' | ' . wp_remote_retrieve_body($response)
    );
}

/**
 * Mark a contact person as primary (idempotent best-effort).
 */
function ve_zoho_mark_person_primary($token, $org_id, $contact_person_id) {
    $url = ve_zoho_books_url(
        '/contacts/contactpersons/' . rawurlencode((string) $contact_person_id) . '/primary',
        [],
        $org_id
    );
    $response = wp_remote_post($url, [
        'timeout' => 20,
        'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
    ]);
    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: mark primary WP_Error: ' . $response->get_error_message());
        return;
    }
    error_log(
        'Venture Events Zoho: mark primary HTTP '
        . wp_remote_retrieve_response_code($response)
        . ' person_id=' . $contact_person_id
        . ' | ' . wp_remote_retrieve_body($response)
    );
}

/**
 * Create the single contact person for a WEB_POS customer.
 *
 * @return string|null contact_person_id
 */
function ve_zoho_create_contact_person($token, $org_id, $contact_id, $reg, $email) {
    list($first, $last) = ve_zoho_person_names_from_reg($reg);

    // Note: this org rejects is_primary_contact on contactpersons create (code 8).
    // Mark primary via /contactpersons/{id}/primary after create instead.
    $person_payload = [
        'contact_id' => $contact_id,
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'phone'      => $reg->phone ?? '',
    ];

    error_log('Venture Events Zoho: Creating sole contact person → ' . wp_json_encode($person_payload));

    $response = wp_remote_post(
        ve_zoho_books_url('/contacts/contactpersons', [], $org_id),
        [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($person_payload),
        ]
    );

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: contact person create WP_Error: ' . $response->get_error_message());
        return null;
    }

    $raw  = wp_remote_retrieve_body($response);
    $body = json_decode($raw, true);
    $pid  = ve_zoho_extract_contact_person_id($body);
    if ($pid) {
        error_log("Venture Events Zoho: ✅ Contact person created → person_id={$pid} email={$email}");
        return $pid;
    }

    error_log('Venture Events Zoho: ❌ Contact person create failed: ' . $raw);
    return null;
}

/**
 * Update the existing sole contact person from the form accounting_email.
 *
 * @return string|null contact_person_id
 */
function ve_zoho_update_contact_person($token, $org_id, $contact_id, $contact_person_id, $reg, $email) {
    list($first, $last) = ve_zoho_person_names_from_reg($reg);

    // Note: avoid is_primary_contact here (org returns Invalid Element on contactpersons).
    $person_payload = [
        'contact_id' => $contact_id,
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'phone'      => $reg->phone ?? '',
    ];

    error_log(
        "Venture Events Zoho: Updating sole contact person person_id={$contact_person_id} → "
        . wp_json_encode($person_payload)
    );

    $response = wp_remote_request(
        ve_zoho_books_url('/contacts/contactpersons/' . rawurlencode((string) $contact_person_id), [], $org_id),
        [
            'method'  => 'PUT',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($person_payload),
        ]
    );

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: contact person update WP_Error: ' . $response->get_error_message());
        return null;
    }

    $raw  = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);
    $body = json_decode($raw, true);

    if ($code >= 200 && $code < 300) {
        $pid = ve_zoho_extract_contact_person_id($body) ?: (string) $contact_person_id;
        error_log("Venture Events Zoho: ✅ Contact person updated → person_id={$pid} email={$email}");
        return $pid;
    }

    error_log("Venture Events Zoho: ❌ Contact person update failed HTTP {$code}: {$raw}");
    return null;
}

/**
 * Ensure WEB_POS customer has exactly one canonical contact person set to accounting_email.
 *
 * - Create person if none exist
 * - Update existing primary/first person if they do (never add a second)
 * - Sync top-level contact email and mark primary
 *
 * @return string|null contact_person_id
 */
function ve_zoho_upsert_contact_person($token, $org_id, $contact_id, $reg) {
    $email = ve_zoho_billing_email_from_reg($reg);
    if ($email === '') {
        error_log('Venture Events Zoho: ⚠ No accounting/billing email on registration — cannot set invoice recipient');
        return null;
    }

    $contact = ve_zoho_get_contact($token, $org_id, $contact_id);
    if (!$contact) {
        error_log("Venture Events Zoho: ❌ Cannot upsert person — contact {$contact_id} not found");
        return null;
    }

    $persons = $contact['contact_persons'] ?? [];
    if (!is_array($persons)) {
        $persons = [];
    }

    $canonical = ve_zoho_canonical_contact_person($persons);
    $person_id = null;

    if ($canonical && !empty($canonical['contact_person_id'])) {
        $person_id = ve_zoho_update_contact_person(
            $token,
            $org_id,
            $contact_id,
            (string) $canonical['contact_person_id'],
            $reg,
            $email
        );
        // If update failed but person exists, still use the id so invoice can associate
        if (!$person_id) {
            $person_id = (string) $canonical['contact_person_id'];
            error_log("Venture Events Zoho: ⚠ Using existing person_id={$person_id} after update failure");
        }
    } else {
        $person_id = ve_zoho_create_contact_person($token, $org_id, $contact_id, $reg, $email);
    }

    if ($person_id) {
        ve_zoho_mark_person_primary($token, $org_id, $person_id);
        ve_zoho_sync_contact_email(
            $token,
            $org_id,
            $contact_id,
            $email,
            (string) ($contact['contact_name'] ?? '')
        );
    }

    if (count($persons) > 1) {
        error_log(
            'Venture Events Zoho: ⚠ Contact has ' . count($persons)
            . ' persons (legacy). Using only person_id=' . ($person_id ?: 'none')
            . ' for invoices; extras left in place.'
        );
    }

    return $person_id;
}

/**
 * @deprecated Use ve_zoho_upsert_contact_person(). Kept as alias for any external callers.
 * @return string|null
 */
function ve_zoho_ensure_contact_billing_email($token, $org_id, $contact_id, $reg) {
    return ve_zoho_upsert_contact_person($token, $org_id, $contact_id, $reg);
}

/**
 * Get or create Zoho Books WEB_POS contact and upsert its sole contact person.
 *
 * Strategy:
 * - Always use "WEB_POS / Company Name" (or person name) for online ticket sales.
 * - One contact person per customer: create on new, update on reuse from accounting_email.
 * - If create says "already exists" (code 3062) → re-lookup and upsert person.
 *
 * @return array{contact_id:string,contact_person_id:?string,email:string}|false
 */
function ve_zoho_get_or_create_contact($reg) {
    $token  = ve_get_zoho_token();
    $org_id = get_option('ve_zoho_org_id');

    if (!$token || !$org_id) {
        error_log('Venture Events: Zoho token or org_id missing for contact lookup');
        return false;
    }

    $base_name            = trim($reg->billing_company ?: ($reg->first_name . ' ' . ($reg->last_name ?? '')));
    $desired_contact_name = 'WEB_POS / ' . $base_name;
    $email                = ve_zoho_billing_email_from_reg($reg);

    error_log("Venture Events Zoho: Looking for existing WEB_POS contact → desired_name='{$desired_contact_name}' email='{$email}'");

    if ($email === '') {
        error_log('Venture Events Zoho: ⚠ Registration has empty accounting_email — invoice cannot be emailed');
    }

    $finish = function ($contact_id) use ($token, $org_id, $reg, $email) {
        $person_id = ve_zoho_upsert_contact_person($token, $org_id, $contact_id, $reg);
        if (!$person_id) {
            error_log("Venture Events Zoho: ❌ No contact_person_id after upsert for contact_id={$contact_id}");
        }
        return [
            'contact_id'        => (string) $contact_id,
            'contact_person_id' => $person_id ? (string) $person_id : null,
            'email'             => $email,
        ];
    };

    $existing_contact_id = ve_zoho_find_existing_contact($token, $org_id, $desired_contact_name, $base_name, $email);
    if ($existing_contact_id) {
        error_log("Venture Events Zoho: Reusing WEB_POS contact_id={$existing_contact_id} — updating sole contact person");
        return $finish($existing_contact_id);
    }

    list($first, $last) = ve_zoho_person_names_from_reg($reg);

    $payload = [
        'contact_name'    => $desired_contact_name,
        'company_name'    => $reg->billing_company ?: $base_name,
        'email'           => $email,
        'phone'           => $reg->phone ?? '',
        'billing_address' => [
            'address' => $reg->billing_address ?? '',
            'country' => ($reg->billing_country === 'NA') ? 'Namibia' : ($reg->billing_country ?? 'Namibia'),
        ],
        'contact_type'    => 'customer',
    ];

    if ($email !== '') {
        // Nested person on contact create (no is_primary_contact — rejected by this org on some endpoints)
        $payload['contact_persons'] = [
            [
                'first_name' => $first,
                'last_name'  => $last,
                'email'      => $email,
                'phone'      => $reg->phone ?? '',
            ],
        ];
    }

    error_log('Venture Events Zoho: No existing WEB_POS contact found. Creating NEW → ' . wp_json_encode($payload));

    $response = wp_remote_post(ve_zoho_books_url('/contacts', [], $org_id), [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: Contact creation WP_Error: ' . $response->get_error_message());
        return false;
    }

    $raw_body  = wp_remote_retrieve_body($response);
    $body      = json_decode($raw_body, true);
    $http_code = wp_remote_retrieve_response_code($response);

    if (!empty($body['contact']['contact_id'])) {
        $contact_id = $body['contact']['contact_id'];
        error_log("Venture Events Zoho: ✅ NEW WEB_POS contact created → contact_id={$contact_id}");
        // Upsert guarantees a person even if nested contact_persons was ignored
        return $finish($contact_id);
    }

    error_log('Venture Events Zoho: ❌ Contact creation FAILED. HTTP ' . $http_code . ' | Body: ' . $raw_body);

    $code = isset($body['code']) ? (int) $body['code'] : 0;
    if ($code === 3062 || stripos($raw_body, 'already exists') !== false) {
        error_log('Venture Events Zoho: Create reported existing contact — re-running lookup');
        $existing_contact_id = ve_zoho_find_existing_contact($token, $org_id, $desired_contact_name, $base_name, $email);
        if ($existing_contact_id) {
            return $finish($existing_contact_id);
        }
        error_log('Venture Events Zoho: Re-lookup after 3062 still found nothing for name=' . $desired_contact_name);
    }

    return false;
}

/**
 * Email a Zoho invoice to accounting_email (and mark it sent in Zoho).
 *
 * POST /invoices/{id}/email with to_mail_ids — required for real delivery.
 * /status/sent only changes status and does not send mail.
 *
 * @return bool
 */
function ve_zoho_email_invoice($token, $org_id, $invoice_id, $invoice_number, $to_email) {
    $to_email = sanitize_email($to_email);
    if ($to_email === '') {
        error_log("Venture Events Zoho: ❌ Cannot email invoice {$invoice_number} — empty recipient");
        return false;
    }

    $payload = [
        'to_mail_ids' => [$to_email],
        // Let Zoho use the org's default invoice email template when subject/body omitted
    ];

    error_log("Venture Events Zoho: Emailing invoice {$invoice_number} to {$to_email}");

    $response = wp_remote_post(
        ve_zoho_books_url('/invoices/' . rawurlencode((string) $invoice_id) . '/email', [], $org_id),
        [
            'timeout' => 45,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]
    );

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: Email invoice WP_Error: ' . $response->get_error_message());
        return false;
    }

    $http = wp_remote_retrieve_response_code($response);
    $raw  = wp_remote_retrieve_body($response);
    $body = json_decode($raw, true);

    if ($http >= 200 && $http < 300 && (empty($body['code']) || (int) $body['code'] === 0)) {
        error_log("Venture Events Zoho: ✅ Invoice {$invoice_number} emailed to {$to_email} | {$raw}");
        return true;
    }

    error_log("Venture Events Zoho: ❌ Email invoice failed HTTP {$http}: {$raw}");
    return false;
}

/**
 * Mark draft invoice as sent (status only — no email). Fallback if email fails.
 *
 * @return array|null updated amounts [balance, total] when present
 */
function ve_zoho_mark_invoice_sent($token, $org_id, $invoice_id) {
    $sent_response = wp_remote_post(
        ve_zoho_books_url('/invoices/' . rawurlencode((string) $invoice_id) . '/status/sent', [], $org_id),
        [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
            ],
            'timeout' => 30,
        ]
    );
    if (is_wp_error($sent_response)) {
        error_log('Venture Events Zoho: Mark invoice sent WP_Error: ' . $sent_response->get_error_message());
        return null;
    }

    $sent_raw = wp_remote_retrieve_body($sent_response);
    error_log(
        'Venture Events Zoho: Mark invoice sent HTTP '
        . wp_remote_retrieve_response_code($sent_response) . ' | ' . $sent_raw
    );
    $sent_body = json_decode($sent_raw, true);
    if (!is_array($sent_body) || empty($sent_body['invoice'])) {
        return null;
    }
    return [
        'balance' => isset($sent_body['invoice']['balance'])
            ? round((float) $sent_body['invoice']['balance'], 2)
            : null,
        'total'   => isset($sent_body['invoice']['total'])
            ? round((float) $sent_body['invoice']['total'], 2)
            : null,
    ];
}

/**
 * Generate Zoho Books Invoice (called ONLY after successful payment)
 *
 * @param object      $reg
 * @param int         $event_id
 * @param string|null $contact_id Optional customer id; person is still upserted from accounting_email
 */
function ve_generate_zoho_invoice($reg, $event_id, $contact_id = null) {
    $token  = ve_get_zoho_token();
    $org_id = get_option('ve_zoho_org_id');

    if (!$token || !$org_id) {
        error_log('Venture Events: Zoho token or org_id missing for invoice');
        return false;
    }

    $billing_email     = ve_zoho_billing_email_from_reg($reg);
    $contact_person_id = null;

    // Resolve customer + sole contact person (accounting_email).
    // Payment already succeeded: we still create the invoice even if person/email fails.
    if ($contact_id === null) {
        $ctx = ve_zoho_get_or_create_contact($reg);
        if (!$ctx || empty($ctx['contact_id'])) {
            error_log('Venture Events: Failed to get/create contact for invoice → aborting invoice creation');
            return false;
        }
        $contact_id        = $ctx['contact_id'];
        $contact_person_id = $ctx['contact_person_id'] ?? null;
        if (!empty($ctx['email'])) {
            $billing_email = $ctx['email'];
        }
    } else {
        // Caller passed contact_id only — still upsert the single accounting person
        $contact_person_id = ve_zoho_upsert_contact_person($token, $org_id, $contact_id, $reg);
    }

    if (!$contact_person_id) {
        error_log(
            "Venture Events Zoho: ⚠ No contact_person_id for contact_id={$contact_id} — "
            . "invoice will still be created; email may use to_mail_ids only"
        );
    }
    if ($billing_email === '') {
        error_log(
            "Venture Events Zoho: ⚠ No accounting email for contact_id={$contact_id} — "
            . "invoice will still be created but cannot be emailed"
        );
    }

    error_log(
        "Venture Events Zoho: Contact OK (ID: {$contact_id}, person_id: "
        . ($contact_person_id ?: 'null') . ", email: {$billing_email}). "
        . "Creating invoice for event #{$event_id}"
    );

    $event_title = get_the_title($event_id);

    // Line-item Account (⋯ → Show additional information → account dropdown, often "Sales")
    $line_account_id = ve_zoho_resolve_line_account_id($token, $org_id);

    // Multi-ticket support
    $is_multi = isset($reg->line_items) && is_array($reg->line_items);

    $zoho_line_items = [];

    if ($is_multi) {
        foreach ($reg->line_items as $item) {
            $rate = (float) $item->price;

            $is_namibia = (strtoupper($reg->billing_country ?? 'NA') === 'NA');
            $tax_id = ve_zoho_resolve_tax_id($is_namibia);

            // Zero-rated free included tickets stay at 0; do not invent tax on free lines.
            if ($is_namibia && $rate > 0) {
                $rate = round($rate / 1.15, 2);
            }

            $tier_name = function_exists('ve_registration_tier_label')
                ? ve_registration_tier_label($item)
                : (string) ($item->tier ?? 'Ticket');

            $is_package = function_exists('ve_is_package_registration') && ve_is_package_registration($item);
            $is_free    = !empty($item->included_free);

            if ($is_package) {
                // Package line: package name + billing company (no personal ticket wording)
                $org = trim((string) ($item->organisation ?? $item->billing_company ?? ''));
                $line_description = $tier_name;
                if ($org !== '' && strcasecmp($org, $tier_name) !== 0) {
                    $line_description = $org . ' - ' . $tier_name;
                }
            } else {
                $line_description = trim(
                    ($item->organisation ?? '') . ' - ' .
                    ($item->first_name ?? '') . ' ' .
                    ($item->last_name ?? '')
                ) . ' - ' . $tier_name;
                if ($is_free) {
                    $line_description .= ' (included)';
                }
            }

            $line = [
                'description' => $line_description,
                'rate'        => $rate,
                'quantity'    => 1,
            ];
            // Free @ 0: omit tax_id so Zoho does not require tax on a zero line
            if ($tax_id !== '' && $rate > 0) {
                $line['tax_id'] = $tax_id;
            }
            if ($line_account_id) {
                $line['account_id'] = $line_account_id;
            }
            $zoho_line_items[] = $line;
        }
    } else {
        $tier_name = function_exists('ve_registration_tier_label')
            ? ve_registration_tier_label($reg)
            : (string) ($reg->tier ?? 'Unknown Tier');

        $line_description = trim(
            ($reg->organisation ?? '') . ' - ' .
            ($reg->first_name ?? '') . ' ' .
            ($reg->last_name ?? '')
        ) . ' - ' . $tier_name;

        $is_namibia = (strtoupper($reg->billing_country ?? 'NA') === 'NA');
        $rate = (float) ($reg->price ?? 0);
        $tax_id = ve_zoho_resolve_tax_id($is_namibia);

        if ($is_namibia) {
            $rate = round($rate / 1.15, 2);
        }

        $line = [
            'description' => $line_description,
            'rate'        => $rate,
            'quantity'    => 1,
        ];
        if ($tax_id !== '') {
            $line['tax_id'] = $tax_id;
        }
        if ($line_account_id) {
            $line['account_id'] = $line_account_id;
        }
        $zoho_line_items[] = $line;
    }

    // Always create the invoice after payment. Contact person / email are best-effort.
    $payload = [
        'customer_id'      => $contact_id,
        'date'             => date('Y-m-d'),
        'status'           => 'draft',
        'line_items'       => $zoho_line_items,
        'notes'            => 'Ref: ' . ($reg->internal_reference ?? $reg->payment_reference ?? '') . "\n" . ($reg->billing_notes ?? ''),
        'reference_number' => ve_zoho_build_invoice_reference($event_title, $reg),
    ];

    $salesperson_id = trim((string) get_option('ve_zoho_salesperson_id', ''));
    if ($salesperson_id !== '') {
        $payload['salesperson_id'] = $salesperson_id;
    }

    if ($contact_person_id) {
        $payload['contact_persons'] = [(string) $contact_person_id];
        $payload['contact_persons_associated'] = [
            [
                'contact_person_id' => (string) $contact_person_id,
                'communication_preference' => [
                    'is_email_enabled' => true,
                ],
            ],
        ];
    }

    error_log(
        'Venture Events Zoho: Invoice recipient person_id=' . ($contact_person_id ?: 'none')
        . ' email=' . ($billing_email ?: 'none')
    );
    error_log('Venture Events Zoho: Creating invoice with payload: ' . wp_json_encode($payload));

    $response = wp_remote_post(ve_zoho_books_url('/invoices', [], $org_id), [
        'headers' => [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Content-Type'  => 'application/json',
        ],
        'timeout' => 45,
        'body'    => wp_json_encode($payload),
    ]);

    if (is_wp_error($response)) {
        error_log('Venture Events Zoho: Invoice creation WP_Error: ' . $response->get_error_message());
        return false;
    }

    $raw_body = wp_remote_retrieve_body($response);
    $body     = json_decode($raw_body, true);

    // If contact_persons_associated is rejected by older orgs, retry without it
    if ((!isset($body['invoice']['invoice_id']) || !isset($body['invoice']['invoice_number']))
        && isset($payload['contact_persons_associated'])
    ) {
        error_log('Venture Events Zoho: Invoice create failed with contact_persons_associated — retrying without it | ' . $raw_body);
        unset($payload['contact_persons_associated']);
        $response = wp_remote_post(ve_zoho_books_url('/invoices', [], $org_id), [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body'    => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            error_log('Venture Events Zoho: Invoice creation retry WP_Error: ' . $response->get_error_message());
            return false;
        }
        $raw_body = wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);
    }

    if (!isset($body['invoice']['invoice_id']) || !isset($body['invoice']['invoice_number'])) {
        error_log('Venture Events Zoho: ❌ Invoice creation FAILED. Full response: ' . $raw_body);
        return false;
    }

    error_log("Venture Events Zoho: ✅ Invoice created successfully → #{$body['invoice']['invoice_number']} (ID: {$body['invoice']['invoice_id']})");

    $invoice_id     = (string) $body['invoice']['invoice_id'];
    $invoice_number = $body['invoice']['invoice_number'];
    $total_amount   = round((float) $body['invoice']['total'], 2);
    $balance        = isset($body['invoice']['balance'])
        ? round((float) $body['invoice']['balance'], 2)
        : $total_amount;

    // Best-effort email to accounting_email (successful email also marks sent in Zoho).
    // Invoice already exists either way.
    $emailed = false;
    if ($billing_email !== '') {
        $emailed = ve_zoho_email_invoice($token, $org_id, $invoice_id, $invoice_number, $billing_email);
    }

    if (!$emailed) {
        // Open invoice for payment even if email failed/skipped (status only — not a second email)
        $marked = ve_zoho_mark_invoice_sent($token, $org_id, $invoice_id);
        if (is_array($marked)) {
            if ($marked['balance'] !== null) {
                $balance = $marked['balance'];
            }
            if ($marked['total'] !== null) {
                $total_amount = $marked['total'];
            }
        }
        if ($billing_email === '') {
            error_log("Venture Events Zoho: ⚠ Invoice {$invoice_number} created but not emailed (no accounting email)");
        } else {
            error_log(
                "Venture Events Zoho: ⚠ Invoice {$invoice_number} was marked sent but EMAIL FAILED — "
                . "recipient was {$billing_email}; check Zoho mail settings / debug.log"
            );
        }
    } else {
        // Refresh totals after email may have updated status/balance
        $after_mail = ve_zoho_get_invoice($token, $org_id, $invoice_id);
        if ($after_mail) {
            if (isset($after_mail['balance'])) {
                $balance = round((float) $after_mail['balance'], 2);
            }
            if (isset($after_mail['total'])) {
                $total_amount = round((float) $after_mail['total'], 2);
            }
        }
    }

    $payment_amount = $balance > 0 ? $balance : $total_amount;
    $payment_amount = round((float) $payment_amount, 2);

    $paid_ok = ve_zoho_record_customer_payment(
        $token,
        $org_id,
        $contact_id,
        $invoice_id,
        $invoice_number,
        $payment_amount,
        $reg
    );

    if (!$paid_ok) {
        error_log("Venture Events Zoho: Invoice {$invoice_number} remains unpaid after payment attempts — check OAuth scopes / debug.log");
    }

    $verify = ve_zoho_get_invoice($token, $org_id, $invoice_id);
    return $verify ?: $body['invoice'];
}

/**
 * GET invoice by id.
 *
 * @return array|null
 */
function ve_zoho_get_invoice($token, $org_id, $invoice_id) {
    $url = ve_zoho_books_url('/invoices/' . rawurlencode((string) $invoice_id), [], $org_id);
    $response = wp_remote_get($url, [
        'timeout' => 30,
        'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
    ]);
    if (is_wp_error($response)) {
        return null;
    }
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['invoice'] ?? null;
}

/**
 * Resolve Zoho tax_id for a line item from settings (org-specific IDs).
 *
 * Settings (leave empty to omit tax_id and let Zoho use its defaults):
 * - ve_zoho_tax_id_domestic  — e.g. Namibia / domestic VAT-inclusive tickets
 * - ve_zoho_tax_id_export    — e.g. non-domestic / export
 *
 * @param bool $is_domestic Whether billing country is treated as domestic (default NA).
 * @return string tax_id or empty string
 */
function ve_zoho_resolve_tax_id($is_domestic) {
    $option = $is_domestic ? 've_zoho_tax_id_domestic' : 've_zoho_tax_id_export';
    return trim((string) get_option($option, ''));
}

/**
 * Resolve Chart of Accounts account_id for invoice line items.
 *
 * This is the dropdown under each line: ⋯ → Show additional information →
 * the field that often defaults to "Sales" (income account — not P.O.# / Ref#).
 *
 * Setting: ve_zoho_line_account_name (exact or case-insensitive account name)
 * Optional override: ve_zoho_line_account_id (if set, used as-is)
 *
 * @return string|null account_id
 */
function ve_zoho_resolve_line_account_id($token, $org_id) {
    $direct_id = trim((string) get_option('ve_zoho_line_account_id', ''));
    if ($direct_id !== '') {
        error_log("Venture Events Zoho: Using configured line account_id={$direct_id}");
        return $direct_id;
    }

    $wanted = trim((string) get_option('ve_zoho_line_account_name', ''));
    if ($wanted === '') {
        // Default Zoho behaviour is usually "Sales" — leave unset so Zoho picks its default
        return null;
    }

    $wanted_l = strtolower($wanted);

    // Paginate chart of accounts (orgs can have many)
    $page     = 1;
    $per_page = 200;
    $matches  = [];

    do {
        $url = ve_zoho_books_url('/chartofaccounts', [
            'page'     => $page,
            'per_page' => $per_page,
        ], $org_id);

        $res = wp_remote_get($url, [
            'timeout' => 45,
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
        ]);

        if (is_wp_error($res)) {
            error_log('Venture Events Zoho: chartofaccounts error: ' . $res->get_error_message());
            return null;
        }

        $http = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $body = json_decode($raw, true);

        if ($http < 200 || $http >= 300) {
            error_log("Venture Events Zoho: chartofaccounts HTTP {$http}: {$raw}");
            return null;
        }

        $accounts = $body['chartofaccounts'] ?? $body['chart_of_accounts'] ?? [];
        if (!is_array($accounts) || !$accounts) {
            break;
        }

        foreach ($accounts as $acc) {
            $name = trim((string) ($acc['account_name'] ?? $acc['account_name'] ?? $acc['name'] ?? ''));
            $id   = (string) ($acc['account_id'] ?? $acc['id'] ?? '');
            if ($id === '' || $name === '') {
                continue;
            }
            if (strtolower($name) === $wanted_l) {
                // Prefer active income accounts if type is present
                $type = strtolower((string) ($acc['account_type'] ?? $acc['account_type_formatted'] ?? ''));
                $matches[] = [
                    'id'   => $id,
                    'name' => $name,
                    'type' => $type,
                ];
            }
        }

        $has_more = !empty($body['page_context']['has_more_page']);
        $page++;
    } while (!empty($has_more) && $page <= 10);

    if (!$matches) {
        error_log(
            "Venture Events Zoho: Line account name \"{$wanted}\" not found in chart of accounts. "
            . 'Use the exact name from the invoice line ⋯ → Show additional information dropdown.'
        );
        return null;
    }

    // Prefer income/sales-type accounts if multiple share the same name
    $preferred = null;
    foreach ($matches as $m) {
        if (strpos($m['type'], 'income') !== false || strpos($m['type'], 'sales') !== false) {
            $preferred = $m;
            break;
        }
    }
    if (!$preferred) {
        $preferred = $matches[0];
    }

    error_log(
        "Venture Events Zoho: ✅ Line item account \"{$preferred['name']}\" → account_id={$preferred['id']}"
        . ($preferred['type'] !== '' ? " (type={$preferred['type']})" : '')
    );

    return $preferred['id'];
}

/**
 * Build invoice reference_number (often visible on related journals).
 * Uses event title + payment/internal ref only.
 */
function ve_zoho_build_invoice_reference($event_title, $reg = null) {
    $event_title = trim((string) $event_title);
    $pay_ref = '';
    if ($reg) {
        $pay_ref = trim((string) ($reg->internal_reference ?? $reg->payment_reference ?? ''));
    }

    $parts = [];
    if ($event_title !== '') {
        $parts[] = $event_title;
    }
    if ($pay_ref !== '') {
        $parts[] = $pay_ref;
    }

    $ref = implode(' — ', $parts);
    // Zoho reference_number max length is typically 100
    if (strlen($ref) > 100) {
        $ref = substr($ref, 0, 97) . '...';
    }
    return $ref !== '' ? $ref : 'Venture Events';
}

/**
 * Build payment reference_number for customer payments / related journals.
 */
function ve_zoho_build_payment_reference($reg, $invoice_number = '') {
    $pay_ref = trim((string) ($reg->internal_reference ?? $reg->payment_reference ?? $invoice_number));

    $ref = $pay_ref !== '' ? $pay_ref : (string) $invoice_number;
    if (strlen($ref) > 100) {
        $ref = substr($ref, 0, 97) . '...';
    }
    return $ref;
}

/**
 * Record full customer payment against an open invoice (marks Paid / balance 0).
 *
 * Zoho Books Customer Payments API:
 * POST /books/v3/customerpayments
 * Required: customer_id, payment_mode, amount, date, invoices[{invoice_id, amount_applied}]
 *
 * Auto-created journal lines under Accountant often carry this payment's reference_number.
 *
 * @return bool
 */
function ve_zoho_record_customer_payment($token, $org_id, $contact_id, $invoice_id, $invoice_number, $payment_amount, $reg) {
    $payment_amount = round((float) $payment_amount, 2);
    if ($payment_amount <= 0) {
        error_log("Venture Events Zoho: Payment amount is 0 for invoice {$invoice_number} — nothing to apply");
        return false;
    }

    $payment_ref = ve_zoho_build_payment_reference($reg, $invoice_number);
    $mode_opt    = trim((string) get_option('ve_zoho_payment_mode', 'banktransfer'));
    if ($mode_opt === '') {
        $mode_opt = 'banktransfer';
    }

    // Modes to try if the first is rejected by org config
    $modes = array_values(array_unique([$mode_opt, 'banktransfer', 'creditcard', 'cash', 'others']));

    $base_payload = [
        'customer_id'      => (string) $contact_id,
        'amount'           => $payment_amount,
        'date'             => date('Y-m-d'),
        'reference_number' => (string) $payment_ref,
        'description'      => 'Card payment via Venture Events (ref: ' . $payment_ref . ')',
        'invoices'         => [
            [
                'invoice_id'     => (string) $invoice_id,
                'amount_applied' => $payment_amount,
            ],
        ],
        // Official docs sample also includes top-level fields:
        'invoice_id'     => (string) $invoice_id,
        'amount_applied' => $payment_amount,
    ];

    $url = ve_zoho_books_url('/customerpayments', [], $org_id);

    foreach ($modes as $mode) {
        $payload = $base_payload;
        $payload['payment_mode'] = $mode;

        error_log(
            "Venture Events Zoho: Recording customer payment for invoice {$invoice_number} "
            . "(mode={$mode}, amount={$payment_amount}) → " . wp_json_encode($payload)
        );

        $payment_response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 45,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($payment_response)) {
            error_log('Venture Events Zoho: Payment recording WP_Error: ' . $payment_response->get_error_message());
            continue;
        }

        $http         = wp_remote_retrieve_response_code($payment_response);
        $payment_raw  = wp_remote_retrieve_body($payment_response);
        $payment_body = json_decode($payment_raw, true);

        if (!empty($payment_body['payment']['payment_id'])) {
            error_log(
                "Venture Events Zoho: ✅ Payment recorded for invoice {$invoice_number}. "
                . "Payment ID: {$payment_body['payment']['payment_id']} (HTTP {$http}, mode={$mode})"
            );

            $inv = ve_zoho_get_invoice($token, $org_id, $invoice_id);
            if ($inv) {
                $bal = isset($inv['balance']) ? (float) $inv['balance'] : null;
                $st  = $inv['status'] ?? '';
                error_log("Venture Events Zoho: Invoice verify after payment → status={$st} balance={$bal}");
            }
            return true;
        }

        $msg = (string) ($payment_body['message'] ?? $payment_raw);
        error_log("Venture Events Zoho: ❌ Payment attempt failed (mode={$mode}, HTTP {$http}): {$msg}");
    }

    return false;
}


/**
 * Get a Zoho OAuth access token, always refreshing first when possible.
 *
 * Access tokens expire ~1 hour. We never trust a stored access token alone:
 * refresh before any API use, then cache for the rest of this PHP request
 * so contact + invoice + payment calls share one fresh token.
 */
function ve_get_zoho_token() {
    static $cached_token = null;

    if ($cached_token !== null) {
        return $cached_token;
    }

    $refresh_token = get_option('ve_zoho_refresh_token');
    $org_id        = get_option('ve_zoho_org_id');
    $client_id     = get_option('ve_zoho_client_id');
    $client_secret = get_option('ve_zoho_client_secret');

    if (!$org_id || !$client_id || !$client_secret) {
        error_log('Venture Events: Zoho org_id / client credentials missing');
        return false;
    }

    if (!$refresh_token) {
        // Last-resort fallback if only a manually pasted access token exists
        $access_token = get_option('ve_zoho_access_token');
        if ($access_token) {
            error_log('Venture Events: No Zoho refresh_token – using stored access_token (may be expired)');
            $cached_token = $access_token;
            return $cached_token;
        }
        error_log('Venture Events: Zoho refresh_token missing and no access_token stored');
        return false;
    }

    $response = wp_remote_post(ve_zoho_accounts_base() . '/oauth/v2/token', [
        'timeout' => 30,
        'body'    => [
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'refresh_token',
        ],
    ]);

    if (is_wp_error($response)) {
        error_log('Venture Events: Zoho token refresh WP_Error: ' . $response->get_error_message());
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($body['access_token'])) {
        update_option('ve_zoho_access_token', $body['access_token'], false);
        error_log('Venture Events: Zoho access token refreshed successfully');
        $cached_token = $body['access_token'];
        return $cached_token;
    }

    $http_code = wp_remote_retrieve_response_code($response);
    error_log(
        'Venture Events: Zoho token refresh failed. HTTP ' . $http_code
        . ' | Body: ' . wp_remote_retrieve_body($response)
    );
    return false;
}

/**
 * Probe several Zoho endpoints to diagnose 401 / code 57 (missing scopes).
 *
 * @return array{token_ok:bool, lines:string[]}
 */
function ve_zoho_permission_self_check() {
    $lines = [];
    $token = ve_get_zoho_token();
    $org   = get_option('ve_zoho_org_id');

    $lines[] = 'API base: ' . ve_zoho_api_base();
    $lines[] = 'Accounts base: ' . ve_zoho_accounts_base();
    $lines[] = 'Organization ID: ' . ($org ?: '(empty)');
    $lines[] = 'Token refresh: ' . ($token ? 'OK (access token available)' : 'FAILED');
    $lines[] = 'Required scope (preferred): ' . ve_zoho_required_scopes();
    $lines[] = 'Minimal scopes (if fullaccess blocked): ' . ve_zoho_minimal_scopes();
    $lines[] = 'Refresh tokens require access_type=offline&prompt=consent on the authorize URL (not an offline_access scope).';
    $lines[] = '';

    if (!$token) {
        $lines[] = 'Cannot call APIs without a valid access token. Check client id/secret + refresh token.';
        return ['token_ok' => false, 'lines' => $lines];
    }

    if (!$org) {
        $lines[] = 'Organization ID is empty — set it in settings.';
        return ['token_ok' => true, 'lines' => $lines];
    }

    $probes = [
        'Contacts (READ)'            => '/contacts?per_page=1',
        'Invoices (READ)'            => '/invoices?per_page=1',
        'Customer payments (READ)'   => '/customerpayments?per_page=1',
        'Bank accounts (READ)'       => '/bankaccounts',
        'Chart of accounts (READ)'   => '/chartofaccounts?per_page=5',
    ];

    $acct_name = trim((string) get_option('ve_zoho_line_account_name', ''));
    $acct_id   = trim((string) get_option('ve_zoho_line_account_id', ''));
    $lines[]   = 'Line item account name: ' . ($acct_name !== '' ? $acct_name : '(not set — Zoho default, usually Sales)');
    $lines[]   = 'Line item account_id override: ' . ($acct_id !== '' ? $acct_id : '(not set)');
    $lines[]   = '';

    foreach ($probes as $label => $path) {
        // path may include query
        $parts = explode('?', $path, 2);
        $only  = $parts[0];
        $query = [];
        if (!empty($parts[1])) {
            parse_str($parts[1], $query);
        }
        $url = ve_zoho_books_url($only, $query, $org);
        $res = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
        ]);
        if (is_wp_error($res)) {
            $lines[] = "{$label}: NETWORK ERROR — " . $res->get_error_message();
            continue;
        }
        $http = wp_remote_retrieve_response_code($res);
        $raw  = wp_remote_retrieve_body($res);
        $body = json_decode($raw, true);
        $code = isset($body['code']) ? (int) $body['code'] : null;
        $msg  = isset($body['message']) ? $body['message'] : '';

        if ($http >= 200 && $http < 300 && ($code === null || $code === 0)) {
            $lines[] = "{$label}: OK (HTTP {$http})";
        } else {
            $lines[] = "{$label}: FAIL HTTP {$http}" . ($code !== null ? " code={$code}" : '') . ($msg ? " — {$msg}" : '');
            if ($code === 57 || $http === 401) {
                $lines[] = '  → Not authorized: OAuth token is missing this module’s scope, or wrong org/API host.';
            }
        }
    }

    $lines[] = '';
    $payments_ok = false;
    $banking_ok  = false;
    foreach ($lines as $line) {
        if (strpos($line, 'Customer payments (READ): OK') === 0) {
            $payments_ok = true;
        }
        if (strpos($line, 'Bank accounts (READ): OK') === 0 || strpos($line, 'Chart of accounts (READ): OK') === 0) {
            $banking_ok = true;
        }
    }

    if (!$payments_ok || !$banking_ok) {
        $lines[] = 'DIAGNOSIS: Token can use Contacts/Invoices but NOT payments/accounts.';
        $lines[] = 'That is why invoices create but stay unpaid — customerpayments is forbidden for this token.';
        $lines[] = 'ACTION: Re-create the OAuth grant with broader scopes, then replace the refresh token in settings.';
        $lines[] = 'Preferred scope: ' . ve_zoho_required_scopes();
        $lines[] = 'Or minimal list: ' . ve_zoho_minimal_scopes();
        $lines[] = 'Use access_type=offline&prompt=consent so Zoho returns a refresh_token.';
    } else {
        $lines[] = 'Permissions look sufficient for contacts, invoices, payments, and deposit accounts.';
    }

    return ['token_ok' => true, 'lines' => $lines];
}