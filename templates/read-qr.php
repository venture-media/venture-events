<?php
if (!defined('ABSPATH')) {
    exit;
}

$id    = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

$reg = $id ? ve_get_registration($id) : null;
if (!$reg || $token === '' || !hash_equals((string) wp_hash($reg->id . '|' . $reg->email), $token)) {
    wp_die('Invalid or expired ticket link.', 'Ticket', ['response' => 403]);
}

// Packages are not person tickets
if (function_exists('ve_is_package_registration') && ve_is_package_registration($reg)) {
    wp_die('This reference is a package purchase, not a personal ticket.', 'Ticket', ['response' => 403]);
}

$is_gate = is_user_logged_in()
    && current_user_can('read')
    && in_array('event_gate', (array) (wp_get_current_user()->roles ?? []), true);

if ($is_gate && empty($reg->entered_at) && function_exists('ve_mark_as_entered')) {
    ve_mark_as_entered($reg->id);
    $reg = ve_get_registration($id); // refresh
}

$status = '';
if (!empty($reg->entered_at)) {
    $status = 'Entered at ' . date_i18n('H:i:s d M Y', strtotime($reg->entered_at));
}

$tier_name = function_exists('ve_registration_tier_label')
    ? ve_registration_tier_label($reg)
    : (string) ($reg->tier ?? '—');

get_header();
?>

<div class="ve-ticket" style="max-width:640px;margin:40px auto;padding:24px;">
    <h1>Ticket for <?php echo esc_html(get_the_title($reg->event_id)); ?></h1>
    <p><strong>Tier:</strong> <?php echo esc_html($tier_name); ?> (N$ <?php echo esc_html(number_format((float) $reg->price, 2)); ?>)</p>
    <p><strong>Name:</strong> <?php echo esc_html(trim($reg->first_name . ' ' . $reg->last_name)); ?></p>
    <p><strong>Organisation:</strong> <?php echo esc_html($reg->organisation ?: '—'); ?></p>
    <p><strong>Invoice:</strong> <?php echo esc_html($reg->invoice_number ?: '—'); ?></p>
    <?php if (!empty($reg->sage_invoice)): ?>
        <p><strong>Sage Invoice:</strong> <?php echo esc_html($reg->sage_invoice); ?></p>
    <?php endif; ?>

    <?php if (!empty($reg->qr_url)): ?>
        <p><img src="<?php echo esc_url($reg->qr_url); ?>" alt="QR Code" style="max-width:300px;height:auto;"></p>
    <?php endif; ?>

    <?php if ($status !== ''): ?>
        <h2 style="color:#3d4a10;"><?php echo esc_html($status); ?></h2>
    <?php endif; ?>
</div>

<?php
get_footer();
