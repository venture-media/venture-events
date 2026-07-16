<?php
if (!defined('ABSPATH')) exit;

/**
 * Generate and save QR Code for a registration ticket
 *
 * @param string $redirect_url   Full URL the QR should point to (e.g. /read-qr/?id=123&token=hash)
 * @param int    $registration_id
 * @return string|false          Public URL of the QR image or false on failure
 */
function ve_generate_qr_code($redirect_url, $registration_id) {
    if (empty($redirect_url) || empty($registration_id)) {
        return false;
    }

    // Set up directory
    $upload_dir = wp_upload_dir();
    $qr_dir     = $upload_dir['basedir'] . '/venture-qrcodes/';

    if (!file_exists($qr_dir)) {
        if (!wp_mkdir_p($qr_dir)) {
            error_log('Venture Events: Failed to create QR code directory');
            return false;
        }
    }

    $filename = 'ticket-' . absint($registration_id) . '.png';
    $filepath = $qr_dir . $filename;

    // Return existing QR if it already exists
    if (file_exists($filepath)) {
        return $upload_dir['baseurl'] . '/venture-qrcodes/' . $filename;
    }

    // Include phpqrcode library
    $qrcode_path = VE_PATH . 'includes/phpqrcode/qrlib.php';
    if (!file_exists($qrcode_path)) {
        error_log('Venture Events: phpqrcode/qrlib.php not found at ' . $qrcode_path);
        return false;
    }

    require_once $qrcode_path;

    // Generate QR Code
    QRcode::png(
        $redirect_url,           // Data to encode
        $filepath,               // Save path
        QR_ECLEVEL_L,            // Error correction level
        8,                       // Size (pixel size of each module)
        2                        // Margin
    );

    if (file_exists($filepath)) {
        // Return public URL
        return $upload_dir['baseurl'] . '/venture-qrcodes/' . $filename;
    }

    error_log('Venture Events: Failed to generate QR code for registration #' . $registration_id);
    return false;
}