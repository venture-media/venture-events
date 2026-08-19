<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin list of package (special tier) purchases — not people.
 */
class VE_Special_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'package',
            'plural'   => 'packages',
            'ajax'     => false,
        ]);
    }

    public function prepare_items($event_id = 0) {
        global $wpdb;

        $table_name   = $wpdb->prefix . 've_registrations';
        $per_page     = 25;
        $current_page = $this->get_pagenum();
        $search       = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';

        $where_conditions = ["(r.line_type = %s)"];
        $args             = ['package'];

        if ($event_id > 0) {
            $where_conditions[] = 'r.event_id = %d';
            $args[]             = $event_id;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(r.tier_name LIKE %s OR r.first_name LIKE %s OR r.organisation LIKE %s OR r.billing_company LIKE %s OR r.accounting_email LIKE %s OR r.internal_reference LIKE %s OR r.status LIKE %s OR r.industry LIKE %s OR r.industry_other LIKE %s)";
            $args = array_merge($args, [$like, $like, $like, $like, $like, $like, $like, $like, $like]);
        }

        $where = 'WHERE ' . implode(' AND ', $where_conditions);

        $count_query = "SELECT COUNT(*) FROM $table_name r $where";
        $total_items = (int) $wpdb->get_var($wpdb->prepare($count_query, $args));

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);

        $query = "SELECT r.*, p.post_title AS event_title
                FROM $table_name r
                LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID
                $where
                ORDER BY r.created_at DESC
                LIMIT %d OFFSET %d";

        $args_for_query = array_merge($args, [$per_page, ($current_page - 1) * $per_page]);
        $this->items    = $wpdb->get_results($wpdb->prepare($query, $args_for_query));
    }

    public function get_column_info() {
        return [$this->get_columns(), [], $this->get_sortable_columns(), 'package'];
    }

    public function get_columns() {
        return [
            'package'            => 'Package',
            'organisation'       => 'Organisation',
            'industry'           => 'Industry',
            'accounting_email'   => 'Accounting Email',
            'event'              => 'Event',
            'price'              => 'Price (N$)',
            'invoice_number'     => 'Invoice #',
            'internal_reference' => 'Internal Ref',
            'status'             => 'Status',
            'created_at'         => 'Registered On',
        ];
    }

    public function get_sortable_columns() {
        return [
            'package'    => ['package', false],
            'event'      => ['event', false],
            'created_at' => ['created_at', true],
            'status'     => ['status', false],
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'package':
                $name = function_exists('ve_registration_tier_label')
                    ? ve_registration_tier_label($item)
                    : (string) ($item->tier_name ?: $item->first_name ?: $item->tier);
                return '<strong>' . esc_html($name) . '</strong>';

            case 'organisation':
                $org = $item->billing_company ?: $item->organisation ?: '';
                return esc_html($org !== '' ? $org : '—');

            case 'industry':
                $label = function_exists('ve_registration_industry_admin_label')
                    ? ve_registration_industry_admin_label($item)
                    : trim((string) ($item->industry ?? ''));
                return esc_html($label !== '' ? $label : '—');

            case 'accounting_email':
                $email = $item->accounting_email ?: $item->email ?: '';
                if ($email === '') {
                    return '—';
                }
                return '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';

            case 'event':
                $title = $item->event_title ?: '—';
                if ($title !== '—') {
                    $title .= ' - Special';
                }
                return esc_html($title);

            case 'price':
                return 'N$ ' . number_format((float) ($item->price ?? 0), 2);

            case 'invoice_number':
                return esc_html($item->invoice_number ?: '—');

            case 'internal_reference':
                return esc_html($item->internal_reference ?: '—');

            case 'status':
                $status = $item->status ?? 'pending';
                $label  = function_exists('ve_registration_status_label')
                    ? ve_registration_status_label($status)
                    : ucfirst((string) $status);
                $color  = function_exists('ve_registration_status_color')
                    ? ve_registration_status_color($status)
                    : ($status === 'paid' ? 'green' : 'orange');
                return '<strong style="color:' . esc_attr($color) . ';">' . esc_html($label) . '</strong>';

            case 'created_at':
                return $item->created_at
                    ? esc_html(date('d M Y H:i', strtotime($item->created_at)))
                    : '—';

            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
        }
    }

    public function get_bulk_actions() {
        return [];
    }

    public function current_action() {
        return false;
    }

    public function process_bulk_action() {}

    public function bulk_actions($which = '') {
        return '';
    }
}
