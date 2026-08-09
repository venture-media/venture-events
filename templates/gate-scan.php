<?php
/**
 * Gate scanner UI — rendered by [venture_gate_scan event_id="N"]
 *
 * Expects: $event_id (int), $event_title (string), $can_scan (bool), $logged_in (bool)
 */
if (!defined('ABSPATH')) {
    exit;
}

$event_id    = isset($event_id) ? (int) $event_id : 0;
$event_title = isset($event_title) ? (string) $event_title : '';
$can_scan    = !empty($can_scan);
$logged_in   = !empty($logged_in);

$login_url = wp_login_url(get_permalink() ?: home_url('/'));
$nonce     = wp_create_nonce('ve_gate_scan_nonce');
?>
<div
    class="ve-gate-scan"
    id="ve-gate-scan"
    data-event-id="<?php echo esc_attr((string) $event_id); ?>"
    data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
    data-nonce="<?php echo esc_attr($nonce); ?>"
    data-can-scan="<?php echo $can_scan ? '1' : '0'; ?>"
>
    <header class="ve-gate-scan__header">
        <h2 class="ve-gate-scan__title">Gate check-in</h2>
        <?php if ($event_title !== '') : ?>
            <p class="ve-gate-scan__event"><?php echo esc_html($event_title); ?></p>
        <?php endif; ?>
    </header>

    <?php if (!$logged_in) : ?>
        <div class="ve-gate-scan__notice ve-gate-scan__notice--warn" role="status">
            <p>Log in with your Staff, Shop manager, or Gate account to scan tickets.</p>
            <p><a class="ve-gate-scan__btn ve-gate-scan__btn--primary" href="<?php echo esc_url($login_url); ?>">Log in</a></p>
        </div>
    <?php elseif (!$can_scan) : ?>
        <div class="ve-gate-scan__notice ve-gate-scan__notice--warn" role="status">
            <p>Your account is not allowed to scan tickets. Contact an administrator.</p>
        </div>
    <?php else : ?>
        <div class="ve-gate-scan__controls">
            <button type="button" class="ve-gate-scan__btn ve-gate-scan__btn--primary" id="ve-gate-start" aria-controls="ve-gate-reader">
                Start scanner
            </button>
            <button type="button" class="ve-gate-scan__btn ve-gate-scan__btn--secondary" id="ve-gate-stop" hidden>
                Stop scanner
            </button>
        </div>

        <div class="ve-gate-scan__reader-wrap">
            <div id="ve-gate-reader" class="ve-gate-scan__reader" aria-live="polite"></div>
        </div>

        <div
            class="ve-gate-scan__result"
            id="ve-gate-result"
            hidden
            role="status"
            aria-live="assertive"
            aria-atomic="true"
        >
            <p class="ve-gate-scan__headline" id="ve-gate-headline"></p>
            <p class="ve-gate-scan__line ve-gate-scan__tier" id="ve-gate-tier"></p>
            <p class="ve-gate-scan__line ve-gate-scan__name" id="ve-gate-name"></p>
            <p class="ve-gate-scan__line ve-gate-scan__entry" id="ve-gate-entry"></p>
            <p class="ve-gate-scan__line ve-gate-scan__msg" id="ve-gate-msg" hidden></p>
        </div>
    <?php endif; ?>
</div>
