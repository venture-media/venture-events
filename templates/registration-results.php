<?php
if (!defined('ABSPATH')) exit;

$event_id    = isset($event_id) ? (int) $event_id : 0;
$ve_results  = (isset($ve_results) && is_array($ve_results)) ? $ve_results : [];
$uid         = isset($ve_results_uid) ? (string) $ve_results_uid : 've-results-1';
$sets        = isset($ve_results['sets']) && is_array($ve_results['sets']) ? $ve_results['sets'] : [];
$payload = wp_json_encode($ve_results, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
if (!is_string($payload) || $payload === '') {
    $payload = '{}';
}
?>

<div class="venture-events-results" id="<?php echo esc_attr($uid); ?>">
    <script type="application/json" class="ve-results-json"><?php echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — JSON_HEX_TAG ?></script>

    <?php foreach ($sets as $set): ?>
        <?php
        $set_id = sanitize_html_class((string) ($set['id'] ?? 'set'));
        $title  = (string) ($set['title'] ?? '');
        $total    = (int) ($set['total'] ?? 0);
        $empty    = (string) ($set['empty'] ?? 'No data yet.');
        $tiers    = isset($set['tiers']) && is_array($set['tiers']) ? $set['tiers'] : [];
        $revenue    = (string) ($set['revenue_label'] ?? ('N$ ' . number_format((float) ($set['revenue'] ?? 0), 2)));
        $industries = isset($set['industries']) && is_array($set['industries']) ? $set['industries'] : [];
        ?>
        <section class="ve-results-set ve-results-set--<?php echo esc_attr($set_id); ?>">
            <h3 class="ve-results-heading">
                <?php echo esc_html($title); ?>
                <span class="ve-results-total"><?php echo esc_html((string) $total); ?></span>
            </h3>
            <p class="ve-results-revenue"><?php echo esc_html($revenue); ?></p>
            <div class="ve-results-row">
                <div class="ve-results-bar-wrap">
                    <?php if ($total < 1): ?>
                        <p class="ve-results-empty"><?php echo esc_html($empty); ?></p>
                    <?php else: ?>
                        <div class="ve-results-chart ve-results-chart--bar">
                            <canvas class="ve-results-bar" aria-label="<?php echo esc_attr($title . ' by month'); ?>"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="ve-results-doughnut-wrap">
                    <?php if (empty($tiers)): ?>
                        <p class="ve-results-empty"><?php echo esc_html($empty); ?></p>
                    <?php else: ?>
                        <div class="ve-results-chart ve-results-chart--doughnut">
                            <canvas class="ve-results-doughnut" aria-label="<?php echo esc_attr($title . ' by tier'); ?>"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($set_id === 'packages' && $total > 0): ?>
                <div class="ve-results-industry-wrap">
                    <?php if (empty($industries)): ?>
                        <p class="ve-results-empty">No industry data yet.</p>
                    <?php else: ?>
                        <div class="ve-results-chart ve-results-chart--industry">
                            <canvas class="ve-results-industry" aria-label="Packages by industry"></canvas>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
