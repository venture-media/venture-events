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

$ve_payment_mode = (isset($ve_payment_mode) && $ve_payment_mode === 'eft') ? 'eft' : 'card';
$is_eft          = ($ve_payment_mode === 'eft');
$checkout_label  = $is_eft ? 'Complete order' : 'Proceed to Payment';

$event_title   = get_the_title($event_id);
$special_tiers = ve_get_special_tiers($event_id);
$normal_tiers  = ve_get_event_tiers($event_id);

// Package options for the top selector (Select is empty default)
$package_options_html = '<option value="">Select</option>';
$any_package_in_stock  = false;
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

    $cap       = max(0, (int) ($tier['available'] ?? 0));
    $remaining = function_exists('ve_get_special_tier_remaining')
        ? ve_get_special_tier_remaining($event_id, (string) $key)
        : null;
    $sold_out  = ($remaining !== null && $remaining < 1);
    if (!$sold_out) {
        $any_package_in_stock = true;
    }

    $label = $name . ' — N$ ' . number_format($price, 2);
    if ($cap > 0) {
        if ($sold_out) {
            $label .= ' (Sold out)';
        } elseif ($remaining === 1) {
            $label .= ' (1 left)';
        } else {
            $label .= ' (' . (int) $remaining . ' left)';
        }
    }

    $package_options_html .= sprintf(
        '<option value="%s" data-price="%s" data-free-tickets="%d" data-free-tier-key="%s" data-free-tier-name="%s" data-available="%d" data-remaining="%s"%s>%s</option>',
        esc_attr($key),
        esc_attr($price),
        $free_count,
        esc_attr($free_key),
        esc_attr($free_name),
        $cap,
        $remaining === null ? '' : (string) (int) $remaining,
        $sold_out ? ' disabled' : '',
        esc_html($label)
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
    $cap       = max(0, (int) ($tier['available'] ?? 0));
    $remaining = function_exists('ve_get_special_tier_remaining')
        ? ve_get_special_tier_remaining($event_id, (string) $key)
        : null;
    $special_js[$key] = [
        'name'            => $name,
        'price'           => $price,
        'free_tickets'    => $free_count,
        'free_tier_key'   => $free_key,
        'free_tier_name'  => $free_name,
        'available'       => $cap,
        // null = unlimited; 0 = sold out
        'remaining'       => $remaining,
        'sold_out'        => ($remaining !== null && $remaining < 1),
    ];
}
?>

<div class="venture-events-registration ve-registration-special<?php echo $is_eft ? ' ve-eft-form' : ''; ?>" data-mode="special">
    <h2>Register for: <?php echo esc_html($event_title); ?></h2>

    <?php if ($is_eft): ?>
        <div id="ve-eft-result" class="ve-comp-result" hidden role="status" aria-live="polite"></div>
    <?php endif; ?>

    <form id="ve-registration-form" data-mode="special">
        <input type="hidden" id="ve-event-id" value="<?php echo esc_attr($event_id); ?>">
        <input type="hidden" id="ve-form-mode" value="special">
        <input type="hidden" id="ve-payment-mode" value="<?php echo esc_attr($ve_payment_mode); ?>">

        <div class="ve-package-section">
            <p>
                <label for="ve-special-tier-select">Package <span class="ve-required">*</span></label><br>
                <select id="ve-special-tier-select" required<?php echo empty($special_js) || !$any_package_in_stock ? ' disabled' : ''; ?>>
                    <?php echo $package_options_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built with esc_* above ?>
                </select>
            </p>
            <?php if (empty($special_js)): ?>
                <p class="ve-error">No packages are configured for this event.</p>
            <?php elseif (!$any_package_in_stock): ?>
                <p class="ve-error">All packages are currently sold out.</p>
            <?php else: ?>
                <p class="ve-hint ve-package-hint">Select a package to continue. Included free tickets (if any) and optional extra tickets will appear below.</p>
            <?php endif; ?>
            <p id="ve-package-stock-hint" class="ve-hint" hidden></p>
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
                    <?php echo esc_html($checkout_label); ?>
                </button>
            </span>
        </div>
    </form>
</div>

<script>
    window.veRegistrationMode = 'special';
    window.vePaymentMode = <?php echo wp_json_encode($ve_payment_mode); ?>;
    window.veSpecialTiers = <?php echo wp_json_encode($special_js); ?>;
    window.veTierOptions = <?php echo wp_json_encode($tier_options_html); ?>;
    if (!window.veGateway || !window.veGateway.nonce) {
        window.veGateway = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('ve_registration_nonce')); ?>
        };
    }
    <?php if ($is_eft): ?>
    if (!window.veEft || !window.veEft.nonce) {
        window.veEft = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('ve_eft_nonce')); ?>
        };
    }
    <?php endif; ?>
</script>
