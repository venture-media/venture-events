<?php
if (!defined('ABSPATH')) exit;

/**
 * Payment initiation page – shows only the active gateway checkout UI.
 *
 * Variables expected from caller:
 * @var string $payment_reference
 * @var string $gateway_html  Markup from ve_gateway_initiate_payment
 * @var int    $event_id
 * @var float  $total_amount
 */

$payment_reference = $payment_reference ?? '';
$gateway_html      = $gateway_html ?? '';
$event_id          = (int) ($event_id ?? 0);
$total_amount      = (float) ($total_amount ?? 0);
$event_title       = $event_id ? get_the_title($event_id) : '';

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html('Complete payment'); ?> – <?php bloginfo('name'); ?></title>
    <?php
    // Standalone page (no theme chrome). Load plugin CSS only — no font overrides in that file.
    $css = VE_PATH . 'assets/frontend.css';
    if (file_exists($css)) {
        echo '<style id="venture-events-frontend">';
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo file_get_contents($css);
        echo '</style>';
    }
    ?>
    <style>
        /* Layout only for this full-page shell — no font-family / weight / size */
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f6f2;
            color: #54595f;
        }
        .ve-payment-body {
            text-align: center;
        }
        /* Keep gateway blocks horizontally centered (do not zero out margin: auto) */
        .ve-payment-body > div {
            display: block;
            width: 100%;
            margin-left: auto !important;
            margin-right: auto !important;
            margin-top: 0 !important;
            margin-bottom: 0 !important;
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>
<div class="ve-payment-page">
    <div class="ve-payment-card">
        <div class="ve-payment-header">
            <h1>Complete your payment</h1>
            <?php if ($event_title): ?>
                <p><?php echo esc_html($event_title); ?></p>
            <?php else: ?>
                <p>Secure checkout for your event registration</p>
            <?php endif; ?>
        </div>
        <?php if ($payment_reference || $total_amount > 0): ?>
            <div class="ve-payment-meta">
                <?php if ($payment_reference): ?>
                    <div><strong>Reference:</strong> <?php echo esc_html($payment_reference); ?></div>
                <?php endif; ?>
                <?php if ($total_amount > 0): ?>
                    <div><strong>Amount:</strong> N$ <?php echo esc_html(number_format($total_amount, 2)); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="ve-payment-body">
            <?php echo $gateway_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gateway plugins output trusted HTML ?>
        </div>
    </div>
</div>
</body>
</html>
