<?php
if (!defined('ABSPATH')) exit;

/**
 * Venture Events Gateway Manager
 *
 * Central registry for payment gateways.
 * Other plugins (ve-paytoday, ve-bankwindhoek, ve-fnb, ve-dummy-gateway, etc.)
 * register themselves here using the 've_register_gateways' action.
 *
 */
class VE_Gateway_Manager {

    private static $instance = null;
    private $gateways = [];

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        // Allow external gateway plugins to register themselves early
        add_action('init', [$this, 'register_gateways'], 5);
    }

    /**
     * Fire the registration hook so gateway plugins can register
     */
    public function register_gateways() {
        do_action('ve_register_gateways', $this);
    }

    /**
     * Register a new payment gateway
     *
     * @param string $id      Unique gateway ID (e.g. 'paytoday', 'dummy', 'fnb')
     * @param string $name    Human-readable name (e.g. 'PayToday', 'Dummy Gateway')
     * @param array  $args    Additional settings
     */
    public function register_gateway($id, $name, $args = []) {
        $defaults = [
            'name'        => $name,
            'description' => '',
            'icon'        => '',                    // future use (URL to logo)
            'callback'    => null,                  // function or class method to initiate payment
            'active'      => true,
            'priority'    => 10,
        ];

        $this->gateways[$id] = wp_parse_args($args, $defaults);

        error_log("Venture Events: Gateway registered → {$name} ({$id})");
    }

    /**
     * Get all registered gateways
     * @return array
     */
    public function get_gateways() {
        return $this->gateways;
    }

    /**
     * Get only active gateways, sorted by priority
     * @return array
     */
    public function get_active_gateways() {
        $active = array_filter($this->gateways, function($g) {
            return !empty($g['active']);
        });

        // Sort by priority
        uasort($active, function($a, $b) {
            return ($a['priority'] ?? 10) <=> ($b['priority'] ?? 10);
        });

        return $active;
    }

    /**
     * Get a specific gateway by ID
     * @return array|null
     */
    public function get_gateway($id) {
        return $this->gateways[$id] ?? null;
    }

    /**
     * Trigger the payment initiation for the chosen gateway
     * This is called from the registration form after pending registrations are saved.
     */
    public function initiate_payment($payment_reference, $event_id, $total_amount) {
        $active_gateways = $this->get_active_gateways();

        if (empty($active_gateways)) {
            error_log('Venture Events: CRITICAL - No active gateways registered when initiate_payment was called for ref=' . $payment_reference);
            wp_die('No payment gateway is active. Please activate the required gateway plugin (e.g. ve-dummy-gateway).');
        }

        // Take the first (highest priority) active gateway
        $gateway_id = key($active_gateways);
        $gateway    = $active_gateways[$gateway_id];

        error_log("Venture Events: Initiating payment via gateway '{$gateway_id}' for ref={$payment_reference}, amount=N$ {$total_amount}");

        do_action('ve_gateway_initiate_payment', $gateway_id, $payment_reference, $event_id, $total_amount, $gateway);
    }
}
