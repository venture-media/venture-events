<?php
if (!defined('ABSPATH')) exit;

$event_id = isset($event_id) ? (int) $event_id : intval($atts['event_id'] ?? 0);
// Re-parse in case template is included without shortcode context
if (!$event_id && !empty($atts['event_id'])) {
    $parsed   = ve_parse_registration_event_attr($atts['event_id']);
    $event_id = (int) $parsed['event_id'];
}

if (!$event_id) {
    echo '<p class="ve-error">Error: event_id is required.</p>';
    return;
}

$event_title   = get_the_title($event_id);
$special_tiers = ve_get_special_tiers($event_id);
$normal_tiers  = ve_get_event_tiers($event_id);

// Package options for the top selector (Select is empty default)
$package_options_html = '<option value="">Select</option>';
foreach ($special_tiers as $key => $tier) {
    if (!is_array($tier)) {
        continue;
    }
    $name  = (string) ($tier['name'] ?? '');
    $price = (float) ($tier['price'] ?? 0);
    if ($name === '' || $price <= 0) {
        continue;
    }
    $free_count = max(0, (int) ($tier['free_tickets'] ?? 0));
    $free_key   = (string) ($tier['free_tier_key'] ?? '');
    $free_name  = '';
    if ($free_count > 0 && $free_key !== '' && isset($normal_tiers[$free_key]['name'])) {
        $free_name = (string) $normal_tiers[$free_key]['name'];
    }

    $package_options_html .= sprintf(
        '<option value="%s" data-price="%s" data-free-tickets="%d" data-free-tier-key="%s" data-free-tier-name="%s">%s — N$ %s</option>',
        esc_attr($key),
        esc_attr($price),
        $free_count,
        esc_attr($free_key),
        esc_attr($free_name),
        esc_html($name),
        esc_html(number_format($price, 2))
    );
}

// Normal tier options for additional paid tickets
$tier_options_html = '';
foreach ($normal_tiers as $key => $tier) {
    if (!is_array($tier)) {
        continue;
    }
    $price = number_format((float) ($tier['price'] ?? 0), 2);
    $tier_options_html .= '<option value="' . esc_attr($key) . '" data-price="' . esc_attr($tier['price']) . '">'
        . esc_html($tier['name'] ?? $key) . ' — N$ ' . $price . '</option>';
}

// Structured data for JS (preferred over parsing option attributes alone)
$special_js = [];
foreach ($special_tiers as $key => $tier) {
    if (!is_array($tier)) {
        continue;
    }
    $name  = (string) ($tier['name'] ?? '');
    $price = (float) ($tier['price'] ?? 0);
    if ($name === '' || $price <= 0) {
        continue;
    }
    $free_count = max(0, (int) ($tier['free_tickets'] ?? 0));
    $free_key   = (string) ($tier['free_tier_key'] ?? '');
    $free_name  = '';
    if ($free_count > 0 && $free_key !== '' && isset($normal_tiers[$free_key]['name'])) {
        $free_name = (string) $normal_tiers[$free_key]['name'];
    }
    $special_js[$key] = [
        'name'            => $name,
        'price'           => $price,
        'free_tickets'    => $free_count,
        'free_tier_key'   => $free_key,
        'free_tier_name'  => $free_name,
    ];
}
?>

<div class="venture-events-registration ve-registration-special" data-mode="special">
    <h2>Register for: <?php echo esc_html($event_title); ?></h2>

    <form id="ve-registration-form" data-mode="special">
        <input type="hidden" id="ve-event-id" value="<?php echo esc_attr($event_id); ?>">
        <input type="hidden" id="ve-form-mode" value="special">

        <div class="ve-package-section">
            <p>
                <label for="ve-special-tier-select">Package <span class="ve-required">*</span></label><br>
                <select id="ve-special-tier-select" required>
                    <?php echo $package_options_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built with esc_* above ?>
                </select>
            </p>
            <p class="ve-hint ve-package-hint">Select a package to continue. Included free tickets (if any) and optional extra tickets will appear below.</p>
        </div>

        <div id="ve-special-body" class="ve-special-body" hidden>
            <div id="free-tickets-container" class="ve-free-tickets-container"></div>

            <div id="tickets-container" class="ve-extra-tickets-container"></div>

            <button type="button" id="add-ticket-btn" class="ve-btn ve-btn-secondary">
                <span class="dashicons dashicons-insert"></span> Add another ticket
            </button>

            <details id="billing-details">
                <summary>
                    <span class="ve-billing-summary-label">
                        <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                        Billing Details
                    </span>
                    <span class="dashicons dashicons-arrow-down ve-billing-chevron" aria-hidden="true"></span>
                </summary>
                <div class="billing-fields">
                    <p><label>Company / Organisation Name <span class="ve-required">*</span></label><br>
                        <input type="text" id="billing_company" required></p>
                    <p><label>Postal Address <span class="ve-required">*</span></label><br>
                        <textarea id="billing_address" rows="3" placeholder="PO Box..." required></textarea></p>
                    <p><label>Country <span class="ve-required">*</span></label><br>
                        <select id="billing_country" required>
                            <option value="NA" selected>Namibia</option>
                        </select>
                    </p>
                    <p><label>Accounting Email <span class="ve-required">*</span></label><br>
                        <input type="email" id="accounting_email" required>
                        <small class="ve-hint">Invoice will be sent here</small>
                    </p>
                    <p><label>Additional Notes</label><br>
                        <textarea id="billing_notes" rows="3"></textarea></p>
                </div>
            </details>

            <p class="ve-total-line">
                <strong>Total: N$ <span id="price-amount">0.00</span></strong>
            </p>
            <div id="vat-breakdown"></div>

            <span class="ve-checkout-wrap is-disabled" title="Complete the form before proceeding">
                <button type="button" id="ve-checkout-btn" class="ve-btn ve-btn-primary is-disabled" disabled>
                    Proceed to Payment
                </button>
            </span>
        </div>
    </form>
</div>

<script>
    window.veRegistrationMode = 'special';
    window.veSpecialTiers = <?php echo wp_json_encode($special_js); ?>;
    window.veTierOptions = <?php echo wp_json_encode($tier_options_html); ?>;
    if (!window.veGateway || !window.veGateway.nonce) {
        window.veGateway = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('ve_registration_nonce')); ?>
        };
    }
</script>
