<?php
if (!defined('ABSPATH')) exit;

$event_id = intval($atts['event_id'] ?? 0);
if (!$event_id) {
    echo '<p class="ve-error">Error: event_id is required.</p>';
    return;
}

$event_title = get_the_title($event_id);
$can_issue   = function_exists('ve_user_can_issue_complimentary') && ve_user_can_issue_complimentary();
$logged_in   = is_user_logged_in();
$issuer      = $can_issue ? ve_complimentary_issuer_label() : '';
?>

<div class="venture-events-registration ve-complimentary-form">
    <h2>Complimentary tickets: <?php echo esc_html($event_title); ?></h2>

    <?php if (!$logged_in): ?>
        <p class="ve-error">You must be logged in as an administrator to issue complimentary tickets.</p>
        <p><a class="ve-btn ve-btn-primary" href="<?php echo esc_url(wp_login_url(get_permalink())); ?>">Log in</a></p>
    <?php elseif (!$can_issue): ?>
        <p class="ve-error">Only administrators can issue complimentary tickets.</p>
    <?php else: ?>
        <p class="ve-comp-meta">
            Tier: <strong>Complimentary Pass</strong> (N$ 0.00)
            <?php if ($issuer !== ''): ?>
                · Issued as <strong><?php echo esc_html($issuer); ?></strong>
            <?php endif; ?>
        </p>

        <div id="ve-comp-result" class="ve-comp-result" hidden role="status" aria-live="polite"></div>

        <form id="ve-registration-form" data-mode="complimentary">
            <input type="hidden" id="ve-event-id" value="<?php echo esc_attr($event_id); ?>">
            <input type="hidden" id="ve-form-mode" value="complimentary">

            <div id="tickets-container"></div>

            <button type="button" id="add-ticket-btn" class="ve-btn ve-btn-secondary">
                <span class="dashicons dashicons-insert"></span> Add another ticket
            </button>

            <p class="ve-total-line">
                <strong>Total: N$ <span id="price-amount">0.00</span></strong>
            </p>

            <span class="ve-checkout-wrap is-disabled" title="Complete guest details first">
                <button type="button" id="ve-checkout-btn" class="ve-btn ve-btn-primary is-disabled" disabled>
                    Issue complimentary tickets
                </button>
            </span>
        </form>
    <?php endif; ?>
</div>

<?php if ($can_issue): ?>
<script>
    window.veRegistrationMode = 'complimentary';
    window.veTierOptions = '';
    if (!window.veComplimentary || !window.veComplimentary.nonce) {
        window.veComplimentary = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: <?php echo wp_json_encode(wp_create_nonce('ve_complimentary_nonce')); ?>
        };
    }
    // Keep veGateway for shared frontend.js helpers that read ajax_url only
    if (!window.veGateway) {
        window.veGateway = {
            ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            nonce: ''
        };
    }
</script>
<?php endif; ?>
