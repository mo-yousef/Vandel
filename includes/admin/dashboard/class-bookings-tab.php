<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Enhanced Bookings Tab
 * Handles the bookings listing and management tab with improved UI and functionality
 */
class Bookings_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // Register hooks for any booking actions
        add_action('admin_init', [$this, 'handle_booking_actions']);
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Process bookings bulk actions
        if (isset($_POST['vandel_bulk_action']) && isset($_POST['booking_ids']) && is_array($_POST['booking_ids'])) {
            $this->process_bulk_actions();
        }
    }
    
    /**
     * Handle booking actions like approve, cancel
     */
    public function handle_booking_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || !isset($_GET['tab']) || $_GET['tab'] !== 'bookings') {
            return;
        }
        
        // Handle individual booking actions
        if (isset($_GET['action']) && isset($_GET['booking_id']) && is_numeric($_GET['booking_id'])) {
            $booking_id = intval($_GET['booking_id']);
            $action = sanitize_key($_GET['action']);
            
            // Verify nonce
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], $action . '_booking_' . $booking_id)) {
                wp_die(__('Security check failed', 'vandel-booking'));
                return;
            }
            
            // Process the action
            switch ($action) {
                case 'approve':
                    $this->update_booking_status($booking_id, 'confirmed');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=bookings&message=booking_approved'));
                    exit;
                    
                case 'cancel':
                    $this->update_booking_status($booking_id, 'canceled');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=bookings&message=booking_canceled'));
                    exit;
                    
                case 'complete':
                    $this->update_booking_status($booking_id, 'completed');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=bookings&message=booking_completed'));
                    exit;
                    
                case 'delete':
                    $this->delete_booking($booking_id);
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=bookings&message=booking_deleted'));
                    exit;
            }
        }
    }
    
    /**
     * Process bulk actions
     */
    private function process_bulk_actions() {
        // Verify nonce
        if (!isset($_POST['vandel_bulk_nonce']) || !wp_verify_nonce($_POST['vandel_bulk_nonce'], 'vandel_bulk_booking_actions')) {
            return;
        }
        
        $booking_ids = array_map('intval', $_POST['booking_ids']);
        if (empty($booking_ids)) {
            return;
        }
        
        // Get action
        $action = '-1';
        if (isset($_POST['bulk_action']) && $_POST['bulk_action'] !== '-1') {
            $action = $_POST['bulk_action'];
        } elseif (isset($_POST['bulk_action_bottom']) && $_POST['bulk_action_bottom'] !== '-1') {
            $action = $_POST['bulk_action_bottom'];
        }
        
        // Process based on action
        $count = 0;
        switch ($action) {
            case 'approve':
                foreach ($booking_ids as $booking_id) {
                    if ($this->update_booking_status($booking_id, 'confirmed')) {
                        $count++;
                    }
                }
                $message = $count > 0 ? 'bulk_approved' : 'bulk_action_failed';
                break;
                
            case 'cancel':
                foreach ($booking_ids as $booking_id) {
                    if ($this->update_booking_status($booking_id, 'canceled')) {
                        $count++;
                    }
                }
                $message = $count > 0 ? 'bulk_canceled' : 'bulk_action_failed';
                break;
                
            case 'complete':
                foreach ($booking_ids as $booking_id) {
                    if ($this->update_booking_status($booking_id, 'completed')) {
                        $count++;
                    }
                }
                $message = $count > 0 ? 'bulk_completed' : 'bulk_action_failed';
                break;
                
            case 'delete':
                foreach ($booking_ids as $booking_id) {
                    if ($this->delete_booking($booking_id)) {
                        $count++;
                    }
                }
                $message = $count > 0 ? 'bulk_deleted' : 'bulk_action_failed';
                break;
                
            case 'export':
                $this->export_bookings($booking_ids);
                return; // Export handles its own redirect
                
            default:
                return;
        }
        
        wp_redirect(add_query_arg([
            'page' => 'vandel-dashboard',
            'tab' => 'bookings',
            'message' => $message,
            'count' => $count
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Update booking status
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Success
     */
    private function update_booking_status($booking_id, $status) {
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $booking_manager = new \VandelBooking\Booking\BookingManager();
            return $booking_manager->updateBookingStatus($booking_id, $status);
        }
        
        // Fallback direct database update
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        $result = $wpdb->update(
            $table_name,
            ['status' => $status],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete booking
     * 
     * @param int $booking_id Booking ID
     * @return bool Success
     */
    private function delete_booking($booking_id) {
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $booking_manager = new \VandelBooking\Booking\BookingManager();
            
            if (method_exists($booking_manager, 'deleteBooking')) {
                return $booking_manager->deleteBooking($booking_id);
            }
        }
        
        // Fallback direct database delete
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        $result = $wpdb->delete(
            $table_name,
            ['id' => $booking_id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Export bookings to CSV with enhanced data
     * 
     * @param array $booking_ids Booking IDs to export
     */
    private function export_bookings($booking_ids) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        // Get bookings with more detailed query
        $placeholders = array_fill(0, count($booking_ids), '%d');
        $placeholders = implode(', ', $placeholders);
        
        $bookings = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT b.*, 
                p.post_title as service_name,
                pm.meta_value as service_price
                FROM {$table_name} b
                LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON b.service = pm.post_id AND pm.meta_key = '_vandel_service_base_price'
                WHERE b.id IN ({$placeholders})",
                $booking_ids
            )
        );
        
        if (empty($bookings)) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'bookings',
                'message' => 'export_empty'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Prepare enhanced CSV data with more fields
        $csv_headers = [
            'ID',
            'Client ID',
            'Client Name',
            'Client Email',
            'Phone',
            'Service',
            'Service Base Price',
            'Booking Date',
            'Created Date',
            'Status',
            'Total Price',
            'Payment Status',
            'Access Info',
            'Comments'
        ];
        
        $csv_data = [$csv_headers];
        
        foreach ($bookings as $booking) {
            $service_name = !empty($booking->service_name) ? $booking->service_name : 'Unknown Service';
            $service_price = !empty($booking->service_price) ? $booking->service_price : '0.00';
            
            $csv_data[] = [
                $booking->id,
                $booking->client_id,
                $booking->customer_name,
                $booking->customer_email,
                isset($booking->phone) ? $booking->phone : 'N/A',
                $service_name,
                $service_price,
                $booking->booking_date,
                $booking->created_at,
                $booking->status,
                $booking->total_price,
                'N/A', // Payment status placeholder (can be enhanced further)
                isset($booking->access_info) ? $booking->access_info : 'N/A',
                isset($booking->comments) ? $booking->comments : 'N/A'
            ];
        }
        
        // Generate CSV file
        $filename = 'vandel-bookings-export-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render tab content
     */
    public function render() {
        // Check if adding new booking
        if (isset($_GET['action']) && $_GET['action'] === 'add') {
            $this->render_add_booking_form();
            return;
        }
        
        // Display status messages
        $this->display_status_messages();
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $bookings_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") === $bookings_table;
        
        // Get booking data for list
        $bookings = [];
        if ($bookings_table_exists) {
            // Handle paging
            $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
            $per_page = 20;
            $offset = ($page - 1) * $per_page;
            
            // Handle filters
            $where = "1=1";
            $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
            if ($status_filter) {
                $where .= $wpdb->prepare(" AND status = %s", $status_filter);
            }
            
            $date_filter = isset($_GET['date_range']) ? sanitize_key($_GET['date_range']) : '';
            if ($date_filter) {
                switch ($date_filter) {
                    case 'today':
                        $where .= " AND DATE(booking_date) = CURDATE()";
                        break;
                    case 'tomorrow':
                        $where .= " AND DATE(booking_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
                        break;
                    case 'this_week':
                        $where .= " AND YEARWEEK(booking_date, 1) = YEARWEEK(CURDATE(), 1)";
                        break;
                    case 'next_week':
                        $where .= " AND YEARWEEK(booking_date, 1) = YEARWEEK(DATE_ADD(CURDATE(), INTERVAL 1 WEEK), 1)";
                        break;
                    case 'this_month':
                        $where .= " AND YEAR(booking_date) = YEAR(CURDATE()) AND MONTH(booking_date) = MONTH(CURDATE())";
                        break;
                    case 'last_month':
                        $where .= " AND YEAR(booking_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) 
                                   AND MONTH(booking_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                        break;
                    case 'custom':
                        // Custom date range handling
                        if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                            $date_from = sanitize_text_field($_GET['date_from']);
                            $date_to = sanitize_text_field($_GET['date_to']);
                            $where .= $wpdb->prepare(" AND DATE(booking_date) BETWEEN %s AND %s", $date_from, $date_to);
                        }
                        break;
                }
            }
            
            // Service filter
            if (!empty($_GET['service'])) {
                $service_id = intval($_GET['service']);
                $where .= $wpdb->prepare(" AND service = %d", $service_id);
            }
            
            $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
            if ($search_query) {
                $where .= $wpdb->prepare(
                    " AND (id LIKE %s OR customer_name LIKE %s OR customer_email LIKE %s OR phone LIKE %s OR access_info LIKE %s)",
                    "%{$search_query}%",
                    "%{$search_query}%",
                    "%{$search_query}%",
                    "%{$search_query}%",
                    "%{$search_query}%"
                );
            }
            
            // Get total count for pagination
            $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE $where");
            
            // Enhanced query with service information
            $bookings = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT b.*, p.post_title as service_name 
                     FROM $bookings_table b
                     LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
                     WHERE $where
                     ORDER BY b.booking_date DESC
                     LIMIT %d OFFSET %d",
                    $per_page, $offset
                )
            );
            
            // Calculate pagination
            $total_pages = ceil($total_bookings / $per_page);
        }
        
        // Get services for filter dropdown
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Render the bookings list
        $this->render_bookings_list(
            $bookings,
            $bookings_table_exists,
            isset($total_pages) ? $total_pages : 0,
            isset($page) ? $page : 1,
            isset($status_filter) ? $status_filter : '',
            isset($date_filter) ? $date_filter : '',
            isset($search_query) ? $search_query : '',
            isset($_GET['service']) ? intval($_GET['service']) : 0,
            $services
        );
    }
    
    /**
     * Display status messages
     */
    private function display_status_messages() {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message_type = 'success';
        $message = '';
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        
        switch ($_GET['message']) {
            case 'booking_approved':
                $message = __('Booking approved successfully.', 'vandel-booking');
                break;
            case 'booking_canceled':
                $message = __('Booking canceled successfully.', 'vandel-booking');
                break;
            case 'booking_completed':
                $message = __('Booking marked as completed.', 'vandel-booking');
                break;
            case 'booking_deleted':
                $message = __('Booking deleted successfully.', 'vandel-booking');
                break;
            case 'bulk_approved':
                $message = sprintf(
                    _n('%d booking approved successfully.', '%d bookings approved successfully.', $count, 'vandel-booking'),
                    $count
                );
                break;
            case 'bulk_canceled':
                $message = sprintf(
                    _n('%d booking canceled successfully.', '%d bookings canceled successfully.', $count, 'vandel-booking'),
                    $count
                );
                break;
            case 'bulk_completed':
                $message = sprintf(
                    _n('%d booking marked as completed.', '%d bookings marked as completed.', $count, 'vandel-booking'),
                    $count
                );
                break;
            case 'bulk_deleted':
                $message = sprintf(
                    _n('%d booking deleted successfully.', '%d bookings deleted successfully.', $count, 'vandel-booking'),
                    $count
                );
                break;
            case 'export_empty':
                $message = __('No bookings selected for export.', 'vandel-booking');
                $message_type = 'warning';
                break;
            case 'bulk_action_failed':
                $message = __('Failed to process action on bookings.', 'vandel-booking');
                $message_type = 'error';
                break;
        }
        
        if (!empty($message)) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message_type),
                esc_html($message)
            );
        }
    }
    
    /**
     * Render bookings list with enhanced UI and filtering
     */
    private function render_bookings_list($bookings, $bookings_table_exists, $total_pages, $current_page, $status_filter, $date_filter, $search_query, $service_filter, $services) {
        ?>
<div id="bookings" class="vandel-tab-content">
    <div class="vandel-card">
        <div class="vandel-card-header vandel-flex-header">
            <h3><?php _e('All Bookings', 'vandel-booking'); ?></h3>
            <div class="vandel-header-actions">
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>"
                    class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Booking', 'vandel-booking'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=calendar'); ?>" class="button">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php _e('Calendar View', 'vandel-booking'); ?>
                </a>
            </div>
        </div>
        <div class="vandel-card-body">
            <!-- Enhanced Filters -->
            <div class="vandel-filters-toolbar">
                <form method="get" action="<?php echo admin_url('admin.php'); ?>"
                    class="vandel-filter-form vandel-advanced-filters">
                    <input type="hidden" name="page" value="vandel-dashboard">
                    <input type="hidden" name="tab" value="bookings">

                    <div class="vandel-filter-group vandel-filter-row">
                        <!-- Status Filter -->
                        <div class="vandel-filter-item">
                            <label for="status"><?php _e('Status', 'vandel-booking'); ?></label>
                            <select name="status" id="status" class="vandel-filter-select">
                                <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                                <option value="pending" <?php selected($status_filter, 'pending'); ?>>
                                    <?php _e('Pending', 'vandel-booking'); ?></option>
                                <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>>
                                    <?php _e('Confirmed', 'vandel-booking'); ?></option>
                                <option value="completed" <?php selected($status_filter, 'completed'); ?>>
                                    <?php _e('Completed', 'vandel-booking'); ?></option>
                                <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>
                                    <?php _e('Canceled', 'vandel-booking'); ?></option>
                            </select>
                        </div>

                        <!-- Service Filter -->
                        <div class="vandel-filter-item">
                            <label for="service"><?php _e('Service', 'vandel-booking'); ?></label>
                            <select name="service" id="service" class="vandel-filter-select">
                                <option value=""><?php _e('All Services', 'vandel-booking'); ?></option>
                                <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service->ID; ?>"
                                    <?php selected($service_filter, $service->ID); ?>>
                                    <?php echo esc_html($service->post_title); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Date Range Filter -->
                        <div class="vandel-filter-item">
                            <label for="date_range"><?php _e('Date', 'vandel-booking'); ?></label>
                            <select name="date_range" id="date_range"
                                class="vandel-filter-select vandel-date-range-select">
                                <option value=""><?php _e('All Dates', 'vandel-booking'); ?></option>
                                <option value="today" <?php selected($date_filter, 'today'); ?>>
                                    <?php _e('Today', 'vandel-booking'); ?></option>
                                <option value="tomorrow" <?php selected($date_filter, 'tomorrow'); ?>>
                                    <?php _e('Tomorrow', 'vandel-booking'); ?></option>
                                <option value="this_week" <?php selected($date_filter, 'this_week'); ?>>
                                    <?php _e('This Week', 'vandel-booking'); ?></option>
                                <option value="next_week" <?php selected($date_filter, 'next_week'); ?>>
                                    <?php _e('Next Week', 'vandel-booking'); ?></option>
                                <option value="this_month" <?php selected($date_filter, 'this_month'); ?>>
                                    <?php _e('This Month', 'vandel-booking'); ?></option>
                                <option value="last_month" <?php selected($date_filter, 'last_month'); ?>>
                                    <?php _e('Last Month', 'vandel-booking'); ?></option>
                                <option value="custom" <?php selected($date_filter, 'custom'); ?>>
                                    <?php _e('Custom Range', 'vandel-booking'); ?></option>
                            </select>
                        </div>

                        <!-- Custom Date Range (initially hidden) -->
                        <div class="vandel-filter-item vandel-custom-date-range"
                            style="<?php echo $date_filter === 'custom' ? '' : 'display:none;'; ?>">
                            <div class="vandel-date-inputs">
                                <div class="vandel-date-from">
                                    <label for="date_from"><?php _e('From', 'vandel-booking'); ?></label>
                                    <input type="date" id="date_from" name="date_from"
                                        value="<?php echo isset($_GET['date_from']) ? esc_attr($_GET['date_from']) : ''; ?>">
                                </div>
                                <div class="vandel-date-to">
                                    <label for="date_to"><?php _e('To', 'vandel-booking'); ?></label>
                                    <input type="date" id="date_to" name="date_to"
                                        value="<?php echo isset($_GET['date_to']) ? esc_attr($_GET['date_to']) : ''; ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Search Field -->
                        <div class="vandel-filter-item vandel-search-field-container">
                            <label for="booking-search"><?php _e('Search', 'vandel-booking'); ?></label>
                            <div class="vandel-search-field">
                                <input type="text" name="s" id="booking-search"
                                    placeholder="<?php _e('Search bookings...', 'vandel-booking'); ?>"
                                    value="<?php echo esc_attr($search_query); ?>">
                                <button type="submit" class="vandel-search-button">
                                    <span class="dashicons dashicons-search"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="vandel-filter-actions">
                        <button type="submit" class="button"><?php _e('Apply Filters', 'vandel-booking'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>"
                            class="button vandel-reset-btn"><?php _e('Reset', 'vandel-booking'); ?></a>
                    </div>
                </form>
            </div>

            <!-- Bookings Summary Stats -->
            <?php if (!empty($bookings)): ?>
            <div class="vandel-booking-stats">
                <?php 
                        // Calculate quick stats
                        $statuses = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'canceled' => 0];
                        $total_value = 0;
                        
                        foreach ($bookings as $booking) {
                            if (isset($statuses[$booking->status])) {
                                $statuses[$booking->status]++;
                            }
                            $total_value += floatval($booking->total_price);
                        }
                        
                        // Display stats
                        ?>
                <div class="vandel-stat-cards">
                    <div class="vandel-stat-card">
                        <span class="vandel-stat-number"><?php echo count($bookings); ?></span>
                        <span class="vandel-stat-label"><?php _e('Bookings', 'vandel-booking'); ?></span>
                    </div>
                    <div class="vandel-stat-card vandel-stat-pending">
                        <span class="vandel-stat-number"><?php echo $statuses['pending']; ?></span>
                        <span class="vandel-stat-label"><?php _e('Pending', 'vandel-booking'); ?></span>
                    </div>
                    <div class="vandel-stat-card vandel-stat-confirmed">
                        <span class="vandel-stat-number"><?php echo $statuses['confirmed']; ?></span>
                        <span class="vandel-stat-label"><?php _e('Confirmed', 'vandel-booking'); ?></span>
                    </div>
                    <div class="vandel-stat-card vandel-stat-completed">
                        <span class="vandel-stat-number"><?php echo $statuses['completed']; ?></span>
                        <span class="vandel-stat-label"><?php _e('Completed', 'vandel-booking'); ?></span>
                    </div>
                    <div class="vandel-stat-card vandel-stat-total">
                        <span
                            class="vandel-stat-number"><?php echo \VandelBooking\Helpers::formatPrice($total_value); ?></span>
                        <span class="vandel-stat-label"><?php _e('Total Value', 'vandel-booking'); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Bookings Table -->
            <?php if (!$bookings_table_exists || empty($bookings)): ?>
            <div class="vandel-empty-state">
                <span class="dashicons dashicons-calendar-alt"></span>
                <p><?php _e('No bookings found. Create your first booking to get started.', 'vandel-booking'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>"
                    class="button button-primary">
                    <?php _e('Create First Booking', 'vandel-booking'); ?>
                </a>
            </div>
            <?php else: ?>
            <form method="post" id="vandel-bookings-form">
                <?php wp_nonce_field('vandel_bulk_booking_actions', 'vandel_bulk_nonce'); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top"
                            class="screen-reader-text"><?php _e('Select bulk action', 'vandel-booking'); ?></label>
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                            <option value="approve"><?php _e('Approve', 'vandel-booking'); ?></option>
                            <option value="cancel"><?php _e('Cancel', 'vandel-booking'); ?></option>
                            <option value="complete"><?php _e('Mark as Completed', 'vandel-booking'); ?></option>
                            <option value="export"><?php _e('Export Selected', 'vandel-booking'); ?></option>
                            <option value="delete"><?php _e('Delete Selected', 'vandel-booking'); ?></option>
                        </select>
                        <input type="submit" id="doaction" class="button action" name="vandel_bulk_action"
                            value="<?php esc_attr_e('Apply', 'vandel-booking'); ?>">
                    </div>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php echo sprintf(
                                            _n('%s item', '%s items', count($bookings), 'vandel-booking'),
                                            number_format_i18n(count($bookings))
                                        ); ?>
                        </span>
                    </div>
                </div>

                <div class="vandel-bookings-table-wrapper">
                    <table class="vandel-bookings-table vandel-data-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </td>
                                <th><?php _e('ID', 'vandel-booking'); ?></th>
                                <th><?php _e('Client', 'vandel-booking'); ?></th>
                                <th><?php _e('Service', 'vandel-booking'); ?></th>
                                <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                                <th><?php _e('Created', 'vandel-booking'); ?></th>
                                <th><?php _e('Total', 'vandel-booking'); ?></th>
                                <th><?php _e('Status', 'vandel-booking'); ?></th>
                                <th><?php _e('Actions', 'vandel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): 
                                            $service_name = isset($booking->service_name) ? $booking->service_name : __('Unknown Service', 'vandel-booking');
                                            
                                            $status_classes = [
                                                'pending' => 'vandel-status-badge-warning',
                                                'confirmed' => 'vandel-status-badge-success',
                                                'completed' => 'vandel-status-badge-info',
                                                'canceled' => 'vandel-status-badge-danger'
                                            ];
                                            
                                            $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
                                            
                                            // Format the date and time
                                            $booking_date = new DateTime($booking->booking_date);
                                            $formatted_date = $booking_date->format(get_option('date_format'));
                                            $formatted_time = $booking_date->format(get_option('time_format'));
                                            
                                            // Format created date
                                            $created_date = new DateTime($booking->created_at);
                                            $formatted_created = $created_date->format(get_option('date_format'));
                                        ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="booking_ids[]"
                                        value="<?php echo esc_attr($booking->id); ?>"
                                        id="cb-select-<?php echo esc_attr($booking->id); ?>">
                                </th>
                                <td>#<?php echo $booking->id; ?></td>
                                <td>
                                    <div class="vandel-client-info">
                                        <strong><?php echo esc_html($booking->customer_name); ?></strong>
                                        <?php if (!empty($booking->customer_email)): ?>
                                        <span
                                            class="vandel-client-email"><?php echo esc_html($booking->customer_email); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($booking->phone)): ?>
                                        <span
                                            class="vandel-client-phone"><?php echo esc_html($booking->phone); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo esc_html($service_name); ?></td>
                                <td>
                                    <div class="vandel-booking-datetime">
                                        <span class="vandel-booking-date"><?php echo $formatted_date; ?></span>
                                        <span class="vandel-booking-time"><?php echo $formatted_time; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="vandel-booking-created"><?php echo $formatted_created; ?></span>
                                </td>
                                <td><?php echo \VandelBooking\Helpers::formatPrice($booking->total_price); ?></td>
                                <td><span
                                        class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span>
                                </td>
                                <td>
                                    <div class="vandel-row-actions">
                                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>"
                                            class="button button-small"
                                            title="<?php esc_attr_e('View Details', 'vandel-booking'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <div class="vandel-dropdown">
                                            <button type="button" class="button button-small vandel-dropdown-trigger"
                                                title="<?php esc_attr_e('Actions', 'vandel-booking'); ?>">
                                                <span class="dashicons dashicons-admin-tools"></span>
                                            </button>
                                            <div class="vandel-dropdown-content">
                                                <?php if ($booking->status !== 'confirmed'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=approve&booking_id=' . $booking->id), 'approve_booking_' . $booking->id); ?>"
                                                    class="vandel-dropdown-item">
                                                    <span class="dashicons dashicons-yes"></span>
                                                    <?php _e('Confirm', 'vandel-booking'); ?>
                                                </a>
                                                <?php endif; ?>

                                                <?php if ($booking->status !== 'completed'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=complete&booking_id=' . $booking->id), 'complete_booking_' . $booking->id); ?>"
                                                    class="vandel-dropdown-item">
                                                    <span class="dashicons dashicons-saved"></span>
                                                    <?php _e('Complete', 'vandel-booking'); ?>
                                                </a>
                                                <?php endif; ?>

                                                <?php if ($booking->status !== 'canceled'): ?>
                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=cancel&booking_id=' . $booking->id), 'cancel_booking_' . $booking->id); ?>"
                                                    class="vandel-dropdown-item">
                                                    <span class="dashicons dashicons-dismiss"></span>
                                                    <?php _e('Cancel', 'vandel-booking'); ?>
                                                </a>
                                                <?php endif; ?>

                                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=delete&booking_id=' . $booking->id), 'delete_booking_' . $booking->id); ?>"
                                                    class="vandel-dropdown-item vandel-dropdown-delete"
                                                    onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this booking?', 'vandel-booking'); ?>')">
                                                    <span class="dashicons dashicons-trash"></span>
                                                    <?php _e('Delete', 'vandel-booking'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-2">
                                </td>
                                <th><?php _e('ID', 'vandel-booking'); ?></th>
                                <th><?php _e('Client', 'vandel-booking'); ?></th>
                                <th><?php _e('Service', 'vandel-booking'); ?></th>
                                <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                                <th><?php _e('Created', 'vandel-booking'); ?></th>
                                <th><?php _e('Total', 'vandel-booking'); ?></th>
                                <th><?php _e('Status', 'vandel-booking'); ?></th>
                                <th><?php _e('Actions', 'vandel-booking'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="tablenav bottom">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-bottom"
                            class="screen-reader-text"><?php _e('Select bulk action', 'vandel-booking'); ?></label>
                        <select name="bulk_action_bottom" id="bulk-action-selector-bottom">
                            <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                            <option value="approve"><?php _e('Approve', 'vandel-booking'); ?></option>
                            <option value="cancel"><?php _e('Cancel', 'vandel-booking'); ?></option>
                            <option value="complete"><?php _e('Mark as Completed', 'vandel-booking'); ?></option>
                            <option value="export"><?php _e('Export Selected', 'vandel-booking'); ?></option>
                            <option value="delete"><?php _e('Delete Selected', 'vandel-booking'); ?></option>
                        </select>
                        <input type="submit" id="doaction2" class="button action" name="vandel_bulk_action"
                            value="<?php esc_attr_e('Apply', 'vandel-booking'); ?>">
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="vandel-pagination">
                        <?php
                                        // Build pagination links
                                        $current_url = add_query_arg(array_filter([
                                            'page' => 'vandel-dashboard',
                                            'tab' => 'bookings',
                                            'status' => $status_filter,
                                            'date_range' => $date_filter,
                                            'service' => $service_filter,
                                            's' => $search_query
                                        ]));
                                        
                                        // Previous page
                                        if ($current_page > 1) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $current_url)) . '" class="vandel-pagination-btn">&laquo; ' . __('Previous', 'vandel-booking') . '</a>';
                                        }
                                        
                                        // Page numbers
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        if ($start_page > 1) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', 1, $current_url)) . '" class="vandel-pagination-btn">1</a>';
                                            if ($start_page > 2) {
                                                echo '<span class="vandel-pagination-ellipsis">...</span>';
                                            }
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<span class="vandel-pagination-btn vandel-pagination-current">' . $i . '</span>';
                                            } else {
                                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $current_url)) . '" class="vandel-pagination-btn">' . $i . '</a>';
                                            }
                                        }
                                        
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="vandel-pagination-ellipsis">...</span>';
                                            }
                                            echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $current_url)) . '" class="vandel-pagination-btn">' . $total_pages . '</a>';
                                        }
                                        
                                        // Next page
                                        if ($current_page < $total_pages) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $current_url)) . '" class="vandel-pagination-btn">' . __('Next', 'vandel-booking') . ' &raquo;</a>';
                                        }
                                        ?>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Additional styles for enhanced booking list */
.vandel-booking-stats {
    margin-bottom: 20px;
}

.vandel-stat-cards {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 20px;
}

.vandel-stat-card {
    flex: 1;
    min-width: 120px;
    padding: 15px;
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    text-align: center;
    border-left: 4px solid #ddd;
}

.vandel-stat-card.vandel-stat-pending {
    border-left-color: #f0b849;
}

.vandel-stat-card.vandel-stat-confirmed {
    border-left-color: #2ea2cc;
}

.vandel-stat-card.vandel-stat-completed {
    border-left-color: #46b450;
}

.vandel-stat-card.vandel-stat-total {
    border-left-color: #5c3896;
}

.vandel-stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.vandel-stat-label {
    font-size: 13px;
    color: #666;
}

.vandel-advanced-filters {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.vandel-filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.vandel-filter-item {
    flex: 1;
    min-width: 150px;
}

.vandel-filter-item label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.vandel-date-inputs {
    display: flex;
    gap: 10px;
}

.vandel-date-from,
.vandel-date-to {
    flex: 1;
}

.vandel-date-from input,
.vandel-date-to input {
    width: 100%;
}

.vandel-client-info {
    display: flex;
    flex-direction: column;
}

.vandel-client-email,
.vandel-client-phone {
    font-size: 12px;
    color: #666;
}

.vandel-booking-datetime {
    display: flex;
    flex-direction: column;
}

.vandel-booking-date {
    font-weight: 500;
}

.vandel-booking-time {
    font-size: 12px;
    color: #666;
}

.vandel-dropdown {
    position: relative;
    display: inline-block;
}

.vandel-dropdown-content {
    display: none;
    position: absolute;
    right: 0;
    min-width: 160px;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    z-index: 1;
    border-radius: 3px;
}

.vandel-dropdown:hover .vandel-dropdown-content {
    display: block;
}

.vandel-dropdown-item {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    text-decoration: none;
    color: #333;
}

.vandel-dropdown-item:hover {
    background: #f5f5f5;
}

.vandel-dropdown-item .dashicons {
    margin-right: 5px;
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.vandel-dropdown-delete {
    color: #d63638;
}

.vandel-dropdown-delete:hover {
    background: #fdeeee;
}

.vandel-row-actions {
    display: flex;
    gap: 5px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle date range selection
    const dateRangeSelect = document.getElementById('date_range');
    const customDateRange = document.querySelector('.vandel-custom-date-range');

    if (dateRangeSelect && customDateRange) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.style.display = 'block';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }

    // Handle "Select All" checkboxes
    const selectAllTop = document.getElementById('cb-select-all-1');
    const selectAllBottom = document.getElementById('cb-select-all-2');
    const checkboxes = document.querySelectorAll('input[name="booking_ids[]"]');

    if (selectAllTop && selectAllBottom && checkboxes.length) {
        // Top checkbox toggle
        selectAllTop.addEventListener('change', function() {
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            if (selectAllBottom) selectAllBottom.checked = this.checked;
        });

        // Bottom checkbox toggle
        selectAllBottom.addEventListener('change', function() {
            checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            if (selectAllTop) selectAllTop.checked = this.checked;
        });

        // Individual checkbox toggle
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(c => c.checked);
                const someChecked = Array.from(checkboxes).some(c => c.checked);

                if (selectAllTop) selectAllTop.checked = allChecked;
                if (selectAllBottom) selectAllBottom.checked = allChecked;

                // Enable/disable bulk action buttons
                const bulkButtons = document.querySelectorAll(
                    'input[name="vandel_bulk_action"]');
                bulkButtons.forEach(button => {
                    button.disabled = !someChecked;
                });
            });
        });

        // Initial check for bulk action buttons
        const someChecked = Array.from(checkboxes).some(c => c.checked);
        const bulkButtons = document.querySelectorAll('input[name="vandel_bulk_action"]');
        bulkButtons.forEach(button => {
            button.disabled = !someChecked;
        });
    }
});
</script>
<?php
    }
    
    /**
     * Render add booking form
     */
    private function render_add_booking_form() {
        // Get services
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        ?>
<div class="vandel-card">
    <div class="vandel-card-header">
        <h3><?php _e('Create New Booking', 'vandel-booking'); ?></h3>
    </div>
    <div class="vandel-card-body">
        <form method="post" id="vandel-add-booking-form">
            <?php wp_nonce_field('vandel_add_booking', 'vandel_booking_nonce'); ?>

            <div class="vandel-form-section">
                <h4><?php _e('Service Details', 'vandel-booking'); ?></h4>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="service_id"><?php _e('Service', 'vandel-booking'); ?> <span
                                class="required">*</span></label>
                        <select name="service_id" id="service_id" required class="vandel-select">
                            <option value=""><?php _e('Select a service', 'vandel-booking'); ?></option>
                            <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service->ID; ?>"
                                data-price="<?php echo esc_attr(get_post_meta($service->ID, '_vandel_service_base_price', true)); ?>">
                                <?php echo esc_html($service->post_title); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vandel-col">
                        <label for="booking_date"><?php _e('Date & Time', 'vandel-booking'); ?> <span
                                class="required">*</span></label>
                        <input type="datetime-local" name="booking_date" id="booking_date" required
                            class="vandel-datetime-field">
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="booking_status"><?php _e('Status', 'vandel-booking'); ?></label>
                        <select name="booking_status" id="booking_status" class="vandel-select">
                            <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                            <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                            <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                            <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                        </select>
                    </div>
                    <div class="vandel-col">
                        <label for="booking_price"><?php _e('Total Price', 'vandel-booking'); ?> <span
                                class="required">*</span></label>
                        <div class="vandel-input-group">
                            <span
                                class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                            <input type="number" name="booking_price" id="booking_price" step="0.01" min="0" required
                                class="vandel-price-field">
                        </div>
                    </div>
                </div>
            </div>

            <div class="vandel-form-section">
                <h4><?php _e('Client Information', 'vandel-booking'); ?></h4>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="customer_name"><?php _e('Name', 'vandel-booking'); ?> <span
                                class="required">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" required class="vandel-text-field">
                    </div>
                    <div class="vandel-col">
                        <label for="customer_email"><?php _e('Email', 'vandel-booking'); ?></label>
                        <input type="email" name="customer_email" id="customer_email" class="vandel-text-field">
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="customer_phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                        <input type="tel" name="customer_phone" id="customer_phone" class="vandel-text-field">
                    </div>
                    <div class="vandel-col">
                        <label for="customer_address"><?php _e('Address', 'vandel-booking'); ?></label>
                        <input type="text" name="customer_address" id="customer_address" class="vandel-text-field">
                    </div>
                </div>
            </div>

            <div class="vandel-form-section">
                <h4><?php _e('Additional Information', 'vandel-booking'); ?></h4>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="booking_notes"><?php _e('Notes', 'vandel-booking'); ?></label>
                        <textarea name="booking_notes" id="booking_notes" rows="4" class="vandel-textarea"></textarea>
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label class="vandel-checkbox-label">
                            <input type="checkbox" name="send_notification" value="yes" checked>
                            <?php _e('Send notification email to customer', 'vandel-booking'); ?>
                        </label>
                    </div>
                </div>
            </div>

            <div class="vandel-form-actions">
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>"
                    class="button button-secondary"><?php _e('Cancel', 'vandel-booking'); ?></a>
                <button type="submit" name="vandel_add_booking_submit"
                    class="button button-primary"><?php _e('Create Booking', 'vandel-booking'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle service selection and price update
    const serviceSelect = document.getElementById('service_id');
    const priceField = document.getElementById('booking_price');

    if (serviceSelect && priceField) {
        serviceSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                priceField.value = price;
            }
        });
    }

    // Form validation
    const bookingForm = document.getElementById('vandel-add-booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            const requiredFields = bookingForm.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('vandel-error');
                    isValid = false;
                } else {
                    field.classList.remove('vandel-error');
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('<?php echo esc_js(__('Please fill all required fields', 'vandel-booking')); ?>');
            }
        });
    }
});
</script>
<?php
    }
}