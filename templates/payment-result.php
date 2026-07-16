<?php
if (!defined('ABSPATH')) exit;

/**
 * Payment result / confirmation page (success or failure).
 *
 * Variables expected from caller:
 * @var string $status  'success'|'failed'
 * @var string $payment_reference
 * @var array  $registrations  rows from ve_registrations
 */

$status            = $status ?? 'failed';
$payment_reference = $payment_reference ?? '';
$registrations     = $registrations ?? [];
$is_success        = ($status === 'success');

$event_title = '';
$total       = 0.0;
if (!empty($registrations)) {
    $event_title = get_the_title((int) $registrations[0]->event_id);
    foreach ($registrations as $reg) {
        $total += (float) $reg->price;
    }
}



?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($is_success ? 'Payment successful' : 'Payment failed'); ?> – <?php bloginfo('name'); ?></title>
    <?php
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
            background: #f5f6f2;
            color: #54595f;
        }
        .ve-card {
            max-width: 640px;
            margin: 40px auto;
        }
        .ve-body h2 {
            margin: 0 0 8px;
            color: #54595f;
        }
    </style>
</head>
<body>
<div class="ve-card">
    <div class="ve-header <?php echo $is_success ? 'success' : 'failed'; ?>">
        <?php if ($is_success): ?>
            <h1>Payment successful</h1>
            <p>Thank you — your ticket purchase is confirmed.</p>
        <?php else: ?>
            <h1>Payment failed</h1>
            <p>Your payment was not completed. No charge should have been taken, or it may reverse shortly.</p>
        <?php endif; ?>
    </div>

    <div class="ve-body">
        <div class="ve-meta">
            <?php if ($event_title): ?>
                <div><strong>Event:</strong> <?php echo esc_html($event_title); ?></div>
            <?php endif; ?>
            <div><strong>Reference:</strong> <?php echo esc_html($payment_reference ?: '—'); ?></div>
            <?php if ($is_success && $total > 0): ?>
                <div><strong>Amount paid:</strong> N$ <?php echo esc_html(number_format($total, 2)); ?></div>
            <?php endif; ?>
        </div>

        <?php if ($is_success && !empty($registrations)): ?>
            <h2>Your tickets</h2>
            <table class="ve-tickets">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($registrations as $reg):
                    $tier_name = function_exists('ve_registration_tier_label')
                        ? ve_registration_tier_label($reg)
                        : (string) ($reg->tier ?? '—');
                    $status_class = ($reg->status === 'paid') ? 'paid' : 'pending';
                    ?>
                    <tr>
                        <td><?php echo esc_html(trim($reg->first_name . ' ' . ($reg->last_name ?? ''))); ?></td>
                        <td><?php echo esc_html($tier_name); ?></td>
                        <td><span class="ve-status-pill <?php echo esc_attr($status_class); ?>"><?php echo esc_html($reg->status); ?></span></td>
                        <td>N$ <?php echo esc_html(number_format((float) $reg->price, 2)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="ve-total"><strong>Total: N$ <?php echo esc_html(number_format($total, 2)); ?></strong></p>
            <p class="ve-note">
                A ticket email with your QR code will be sent to each ticket holder when email is configured on this site.
                You can also keep this reference for support: <strong><?php echo esc_html($payment_reference); ?></strong>
            </p>
        <?php elseif ($is_success): ?>
            <p class="ve-note">Payment was recorded for reference <strong><?php echo esc_html($payment_reference); ?></strong>, but ticket details could not be loaded. Please contact the organiser with this reference if needed.</p>
        <?php else: ?>
            <p class="ve-note">
                If you believe you were charged in error, contact the organiser with reference
                <strong><?php echo esc_html($payment_reference ?: 'N/A'); ?></strong>.
                You can return to the event page and try again.
            </p>
        <?php endif; ?>

        <div class="ve-actions">
            <a class="ve-btn" href="<?php echo esc_url(home_url('/')); ?>">Back to site</a>
        </div>
    </div>
</div>
</body>
</html>
