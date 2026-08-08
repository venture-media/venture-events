<?php
if (!defined('ABSPATH')) exit;

$event_id = intval($atts['event_id'] ?? 0);
if (!$event_id) {
    echo '<p class="ve-error">Error: event_id is required.</p>';
    return;
}

$event_title = get_the_title($event_id);
$tiers = get_post_meta($event_id, '_ve_tiers', true) ?: [];

// Pre-render tier options HTML for JavaScript
$tier_options_html = '';
foreach ($tiers as $key => $tier) {
    $price = number_format((float)$tier['price'], 2);
    $tier_options_html .= '<option value="' . esc_attr($key) . '" data-price="' . esc_attr($tier['price']) . '">'
                  . esc_html($tier['name']) . ' — N$ ' . $price . '</option>';
}
?>

<div class="venture-events-registration">
    <h2>Register for: <?php echo esc_html($event_title); ?></h2>

    <form id="ve-registration-form">
        <input type="hidden" id="ve-event-id" value="<?php echo esc_attr($event_id); ?>">

        <div id="tickets-container"></div>

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
                <p><label>Company / Organisation Name</label><br>
                    <input type="text" id="billing_company"></p>
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
    </form>
</div>

<?php
// Pass tier options + ensure veGateway exists even if script enqueue detection missed the shortcode
// (e.g. Elementor / page builders that store content outside post_content)
?>
<script>
    window.veRegistrationMode = 'normal';
    window.veTierOptions = <?php echo wp_json_encode($tier_options_html); ?>;
    if (!window.veGateway || !window.veGateway.nonce) {
        window.veGateway = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('ve_registration_nonce')); ?>
        };
    }
</script>
