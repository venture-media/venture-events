<?php
if (!defined('ABSPATH')) exit;

class VE_Guest_List_Table extends WP_List_Table {

    public function prepare_items($event_id = 0) {
        global $wpdb;

        $table_name = $wpdb->prefix . 've_registrations';
        $per_page   = 25;
        $current_page = $this->get_pagenum();
        $search     = isset($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';

        // Build WHERE conditions and arguments safely
        $where_conditions = [];
        $args = [];

        if ($event_id > 0) {
            $where_conditions[] = 'r.event_id = %d';
            $args[] = $event_id;
        }

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR organisation LIKE %s OR internal_reference LIKE %s OR status LIKE %s)";
            $args = array_merge($args, [$like, $like, $like, $like, $like, $like]);
        }

        $where = '';
        if (!empty($where_conditions)) {
            $where = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Count total items
        $count_query = "SELECT COUNT(*) FROM $table_name r $where";
        $total_items = !empty($args) 
            ? $wpdb->get_var($wpdb->prepare($count_query, $args)) 
            : $wpdb->get_var($count_query);

        $this->set_pagination_args([
            'total_items' => (int) $total_items,
            'per_page'    => $per_page,
        ]);

        // Get the data
        $query = "SELECT r.*, p.post_title AS event_title 
                FROM $table_name r 
                LEFT JOIN {$wpdb->posts} p ON r.event_id = p.ID 
                $where 
                ORDER BY r.created_at DESC 
                LIMIT %d OFFSET %d";

        $args_for_query = array_merge($args, [$per_page, ($current_page - 1) * $per_page]);

        $this->items = $wpdb->get_results($wpdb->prepare($query, $args_for_query));
    }

    public function get_column_info() {
        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $primary  = 'name';

        return [$columns, $hidden, $sortable, $primary];
    }

    public function __construct() {
        parent::__construct([
            'singular' => 'guest',
            'plural'   => 'guests',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'name'                => 'Full Name',
            'organisation'        => 'Organisation',
            'phone'               => 'Phone',
            'email'               => 'Email',
            'event'               => 'Event',
            'tier'                => 'Tier',
            'price'               => 'Price (N$)',
            'invoice_number'      => 'Invoice #',
            'internal_reference'  => 'Internal Ref',
            'status'              => 'Status',           // ← NEW
            'entered_at'          => 'Entered At',
            'created_at'          => 'Registered On'
        ];
    }

    public function get_sortable_columns() {
        return [
            'name'                => ['name', false],
            'event'               => ['event', false],
            'tier'                => ['tier', false],
            'entered_at'          => ['entered_at', false],
            'created_at'          => ['created_at', true],
            'internal_reference'  => ['internal_reference', false],
            'status'              => ['status', false],     // ← NEW
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'name':
                return esc_html(trim($item->first_name . ' ' . ($item->last_name ?? '')));

            case 'organisation':
                return esc_html($item->organisation ?: '—');

            case 'phone':
                return esc_html($item->phone ?: '—');

            case 'email':
                return '<a href="mailto:' . esc_attr($item->email) . '">' . esc_html($item->email) . '</a>';

            case 'event':
                return esc_html($item->event_title ?: '—');

            case 'tier':
                $tier_name = function_exists('ve_registration_tier_label')
                    ? ve_registration_tier_label($item)
                    : (string) ($item->tier ?? '');
                return '<strong>' . esc_html($tier_name) . '</strong>';

            case 'price':
                return 'N$ ' . number_format((float)($item->price ?? 0), 2);

            case 'invoice_number':
                return esc_html($item->invoice_number ?: '—');

            case 'internal_reference':
                return esc_html($item->internal_reference ?: '—');

            case 'status':                                      // ← NEW
                $status = $item->status ?? 'pending';
                $color  = $status === 'paid' ? 'green' : 'orange';
                return '<strong style="color:' . $color . ';">' . esc_html(ucfirst($status)) . '</strong>';

            case 'entered_at':
                return $item->entered_at 
                    ? date('d M Y H:i', strtotime($item->entered_at)) 
                    : '<span style="color:#999;">Not entered</span>';

            case 'created_at':
                return date('d M Y H:i', strtotime($item->created_at));

            default:
                return isset($item->$column_name) ? esc_html($item->$column_name) : '—';
        }
    }

    // The rest of the class stays exactly the same (get_bulk_actions, etc.)
    public function get_bulk_actions() { return []; }
    public function current_action() { return false; }
    public function process_bulk_action() {}
    public function bulk_actions($which = '') { return ''; }
}