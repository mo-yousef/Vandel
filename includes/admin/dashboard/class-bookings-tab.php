<?php
namespace VandelBooking\Admin\Dashboard;

use DateTime;
use VandelBooking\Helpers;

class Bookings_Tab implements Tab_Interface {
    /**
     * @var int Number of bookings per page
     */
    private $per_page = 25;

    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_vandel-dashboard' || 
            !isset($_GET['tab']) || 
            $_GET['tab'] !== 'bookings') {
            return;
        }

        // Enqueue select2 for enhanced dropdowns
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);

        // Enqueue date range picker
        wp_enqueue_style('daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css');
        wp_enqueue_script('moment', 'https://cdn.jsdelivr.net/npm/moment@2.29.1/moment.min.js', [], null, true);
        wp_enqueue_script('daterangepicker', 'https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js', ['jquery', 'moment'], null, true);

        // Custom bookings tab script
        wp_enqueue_script('vandel-bookings-tab', VANDEL_PLUGIN_URL . 'assets/js/admin/bookings-tab.js', ['jquery', 'select2', 'daterangepicker'], VANDEL_VERSION, true);
        
        // Localize script with necessary data
        wp_localize_script('vandel-bookings-tab', 'vandelBookingsTab', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_bookings_tab_nonce'),
            'confirmBulkAction' => __('Are you sure you want to perform this bulk action?', 'vandel-booking'),
            'noRowsSelected' => __('Please select at least one booking.', 'vandel-booking')
        ]);
    }
    
    /**
     * Process actions for this tab
     */
    public function process_actions() {
        // Existing action handling from current implementation
        $this->handle_bulk_actions();
    }
    
    /**
     * Render enhanced bookings tab
     */
    public function render() {
        // Get booking data
        $booking_data = $this->get_bookings_data();
        
        // Render the enhanced bookings interface
        ?>
        <div class="vandel-bookings-container">
            <!-- Top Summary Cards -->
            <?php $this->render_booking_summary_cards($booking_data['stats']); ?>
            
            <!-- Advanced Filters -->
            <?php $this->render_advanced_filters($booking_data['services']); ?>
            
            <!-- Bookings Table -->
            <?php $this->render_bookings_table($booking_data['bookings'], $booking_data['total_pages'], $booking_data['current_page']); ?>
        </div>

        <!-- Bulk Action Modal -->
        <?php $this->render_bulk_action_modal(); ?>
        <?php
    }

    /**
     * Render booking summary cards
     */
    private function render_booking_summary_cards($stats) {
        ?>
        <div class="vandel-bookings-summary-grid">
            <div class="vandel-summary-card total-bookings">
                <div class="vandel-summary-icon">
                    <span class="dashicons dashicons-calendar-alt"></span>
                </div>
                <div class="vandel-summary-content">
                    <h3><?php _e('Total Bookings', 'vandel-booking'); ?></h3>
                    <div class="vandel-summary-value"><?php echo number_format_i18n($stats['total']); ?></div>
                    <div class="vandel-summary-trend <?php echo $stats['total_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php 
                        echo $stats['total_trend'] >= 0 ? '+' : '';
                        echo number_format($stats['total_trend'], 1) . '%'; 
                        ?>
                    </div>
                </div>
            </div>

            <div class="vandel-summary-card pending-bookings">
                <div class="vandel-summary-icon">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="vandel-summary-content">
                    <h3><?php _e('Pending Bookings', 'vandel-booking'); ?></h3>
                    <div class="vandel-summary-value"><?php echo number_format_i18n($stats['pending']); ?></div>
                    <div class="vandel-summary-trend <?php echo $stats['pending_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php 
                        echo $stats['pending_trend'] >= 0 ? '+' : '';
                        echo number_format($stats['pending_trend'], 1) . '%'; 
                        ?>
                    </div>
                </div>
            </div>

            <div class="vandel-summary-card completed-bookings">
                <div class="vandel-summary-icon">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="vandel-summary-content">
                    <h3><?php _e('Completed Bookings', 'vandel-booking'); ?></h3>
                    <div class="vandel-summary-value"><?php echo number_format_i18n($stats['completed']); ?></div>
                    <div class="vandel-summary-trend <?php echo $stats['completed_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php 
                        echo $stats['completed_trend'] >= 0 ? '+' : '';
                        echo number_format($stats['completed_trend'], 1) . '%'; 
                        ?>
                    </div>
                </div>
            </div>

            <div class="vandel-summary-card total-revenue">
                <div class="vandel-summary-icon">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="vandel-summary-content">
                    <h3><?php _e('Total Revenue', 'vandel-booking'); ?></h3>
                    <div class="vandel-summary-value"><?php echo Helpers::formatPrice($stats['total_revenue']); ?></div>
                    <div class="vandel-summary-trend <?php echo $stats['revenue_trend'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php 
                        echo $stats['revenue_trend'] >= 0 ? '+' : '';
                        echo number_format($stats['revenue_trend'], 1) . '%'; 
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render advanced filters
     */
    private function render_advanced_filters($services) {
        ?>
        <div class="vandel-advanced-filters">
            <form id="vandel-bookings-filter-form" method="get">
                <input type="hidden" name="page" value="vandel-dashboard">
                <input type="hidden" name="tab" value="bookings">
                
                <div class="vandel-filter-row">
                    <!-- Service Filter -->
                    <div class="vandel-filter-group">
                        <label for="service-select"><?php _e('Service', 'vandel-booking'); ?></label>
                        <select id="service-select" name="service_id" class="vandel-select2">
                            <option value=""><?php _e('All Services', 'vandel-booking'); ?></option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service->ID; ?>"><?php echo esc_html($service->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div class="vandel-filter-group">
                        <label for="status-select"><?php _e('Status', 'vandel-booking'); ?></label>
                        <select id="status-select" name="status" class="vandel-select2">
                            <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                            <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                            <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                            <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                            <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                        </select>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="vandel-filter-group">
                        <label for="date-range"><?php _e('Date Range', 'vandel-booking'); ?></label>
                        <input type="text" id="date-range" name="date_range" 
                               placeholder="<?php _e('Select Date Range', 'vandel-booking'); ?>" 
                               class="vandel-daterange-picker">
                    </div>

                    <!-- Search Input -->
                    <div class="vandel-filter-group">
                        <label for="booking-search"><?php _e('Search', 'vandel-booking'); ?></label>
                        <input type="search" id="booking-search" name="s" 
                               placeholder="<?php _e('Search bookings', 'vandel-booking'); ?>"
                               class="vandel-search-input">
                    </div>
                </div>

                <div class="vandel-filter-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Apply Filters', 'vandel-booking'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="button">
                        <?php _e('Reset Filters', 'vandel-booking'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render bookings table
     */
    private function render_bookings_table($bookings, $total_pages, $current_page) {
        ?>
        <div class="vandel-bookings-table-container">
            <form id="vandel-bookings-list-form" method="post">
                <?php wp_nonce_field('vandel_bookings_bulk_action', '_wpnonce'); ?>
                
                <div class="vandel-table-actions">
                    <div class="vandel-bulk-actions">
                        <select name="bulk_action" class="vandel-bulk-action-select">
                            <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                            <option value="confirm"><?php _e('Confirm', 'vandel-booking'); ?></option>
                            <option value="complete"><?php _e('Complete', 'vandel-booking'); ?></option>
                            <option value="cancel"><?php _e('Cancel', 'vandel-booking'); ?></option>
                            <option value="export"><?php _e('Export', 'vandel-booking'); ?></option>
                            <option value="delete"><?php _e('Delete', 'vandel-booking'); ?></option>
                        </select>
                        <button type="submit" class="button action vandel-bulk-action-submit">
                            <?php _e('Apply', 'vandel-booking'); ?>
                        </button>
                    </div>
                </div>

                <table class="vandel-bookings-table wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="vandel-select-all-bookings">
                            </td>
                            <th><?php _e('ID', 'vandel-booking'); ?></th>
                            <th><?php _e('Client', 'vandel-booking'); ?></th>
                            <th><?php _e('Service', 'vandel-booking'); ?></th>
                            <th><?php _e('Date & Time', 'vandel-booking'); ?></th>
                            <th><?php _e('Total Price', 'vandel-booking'); ?></th>
                            <th><?php _e('Status', 'vandel-booking'); ?></th>
                            <th><?php _e('Actions', 'vandel-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="booking_ids[]" value="<?php echo $booking->id; ?>">
                                </th>
                                <td><?php echo $booking->id; ?></td>
                                <td>
                                    <div class="vandel-client-info">
                                        <strong><?php echo esc_html($booking->customer_name); ?></strong>
                                        <div class="vandel-client-contact">
                                            <?php echo esc_html($booking->customer_email); ?>
                                            <?php if (!empty($booking->phone)): ?>
                                                <br><?php echo esc_html($booking->phone); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $service = get_post($booking->service);
                                    echo $service ? esc_html($service->post_title) : __('Unknown', 'vandel-booking'); 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    $booking_date = new DateTime($booking->booking_date);
                                    echo $booking_date->format(get_option('date_format') . ' ' . get_option('time_format')); 
                                    ?>
                                </td>
                                <td><?php echo Helpers::formatPrice($booking->total_price); ?></td>
                                <td>
                                    <span class="vandel-status-badge vandel-status-<?php echo esc_attr($booking->status); ?>">
                                        <?php echo ucfirst(esc_html($booking->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="vandel-row-actions">
                                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id); ?>" 
                                           class="button button-small vandel-view-booking" 
                                           title="<?php _e('View Details', 'vandel-booking'); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <div class="vandel-action-dropdown">
                                            <button type="button" class="button button-small vandel-dropdown-toggle">
                                                <span class="dashicons dashicons-admin-generic"></span>
                                            </button>
                                            <div class="vandel-dropdown-menu">
                                                <?php if ($booking->status !== 'confirmed'): ?>
                                                    <a href="#" class="vandel-booking-action" 
                                                       data-action="confirm" 
                                                       data-id="<?php echo $booking->id; ?>">
                                                        <?php _e('Confirm', 'vandel-booking'); ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($booking->status !== 'completed'): ?>
                                                    <a href="#" class="vandel-booking-action" 
                                                       data-action="complete" 
                                                       data-id="<?php echo $booking->id; ?>">
                                                        <?php _e('Complete', 'vandel-booking'); ?>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($booking->status !== 'canceled'): ?>
                                                    <a href="#" class="vandel-booking-action" 
                                                       data-action="cancel" 
                                                       data-id="<?php echo $booking->id; ?>">
                                                        <?php _e('Cancel', 'vandel-booking'); ?>
                                                    </a>
                                                <?php endif; ?>
                                                <a href="#" class="vandel-booking-action vandel-delete-booking" 
                                                   data-action="delete" 
                                                   data-id="<?php echo $booking->id; ?>">
                                                    <?php _e('Delete', 'vandel-booking'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php $this->render_pagination($total_pages, $current_page); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render pagination
     */
    private function render_pagination($total_pages, $current_page) {
        if ($total_pages <= 1) {
            return;
        }
        ?>
        <div class="vandel-pagination">
            <?php
            $base_url = admin_url('admin.php?page=vandel-dashboard&tab=bookings');
            
            // Previous page link
            if ($current_page > 1) {
                $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
                echo '<a href="' . esc_url($prev_url) . '" class="button">&laquo; ' . __('Previous', 'vandel-booking') . '</a>';
            }

            // Page numbers
            echo '<div class="vandel-page-numbers">';
            $range = 2; // Number of pages to show on each side of current page
            $start = max(1, $current_page - $range);
            $end = min($total_pages, $current_page + $range);

            // First page
            if ($start > 1) {
                $first_url = add_query_arg('paged', 1, $base_url);
                echo '<a href="' . esc_url($first_url) . '" class="button">' . 1 . '</a>';
                if ($start > 2) {
                    echo '<span class="vandel-ellipsis">...</span>';
                }
            }

            // Page numbers
            for ($i = $start; $i <= $end; $i++) {
                if ($i == $current_page) {
                    echo '<span class="button vandel-current-page">' . $i . '</span>';
                } else {
                    $page_url = add_query_arg('paged', $i, $base_url);
                    echo '<a href="' . esc_url($page_url) . '" class="button">' . $i . '</a>';
                }
            }

            // Last page
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) {
                    echo '<span class="vandel-ellipsis">...</span>';
                }
                $last_url = add_query_arg('paged', $total_pages, $base_url);
                echo '<a href="' . esc_url($last_url) . '" class="button">' . $total_pages . '</a>';
            }
            echo '</div>';

            // Next page link
            if ($current_page < $total_pages) {
                $next_url = add_query_arg('paged', $current_page + 1, $base_url);
                echo '<a href="' . esc_url($next_url) . '" class="button">' . __('Next', 'vandel-booking') . ' &raquo;</a>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render bulk action modal
     */
    private function render_bulk_action_modal() {
        ?>
        <div id="vandel-bulk-action-modal" class="vandel-modal" style="display:none;">
            <div class="vandel-modal-content">
                <div class="vandel-modal-header">
                    <h3><?php _e('Confirm Bulk Action', 'vandel-booking'); ?></h3>
                    <span class="vandel-modal-close">&times;</span>
                </div>
                <div class="vandel-modal-body">
                    <p><?php _e('Are you sure you want to perform this action on the selected bookings?', 'vandel-booking'); ?></p>
                </div>
                <div class="vandel-modal-footer">
                    <button class="button vandel-modal-cancel"><?php _e('Cancel', 'vandel-booking'); ?></button>
                    <button class="button button-primary vandel-modal-confirm"><?php _e('Confirm', 'vandel-booking'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get bookings data
     */
    private function get_bookings_data() {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        $services_table = $wpdb->posts;

        // Pagination
        $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page = $this->per_page;
        $offset = ($current_page - 1) * $per_page;

        // Build query conditions
        $where = ['1=1'];
        $params = [];

        // Status filter
        if (!empty($_GET['status'])) {
            $where[] = 'status = %s';
            $params[] = sanitize_text_field($_GET['status']);
        }

        // Service filter
        if (!empty($_GET['service_id'])) {
            $where[] = 'service = %d';
            $params[] = intval($_GET['service_id']);
        }

        // Date range filter
        if (!empty($_GET['date_range'])) {
            // Assuming date range is in format 'YYYY-MM-DD to YYYY-MM-DD'
            $date_parts = explode(' to ', sanitize_text_field($_GET['date_range']));
            if (count($date_parts) === 2) {
                $where[] = 'DATE(booking_date) BETWEEN %s AND %s';
                $params[] = $date_parts[0];
                $params[] = $date_parts[1];
            }
        }

        // Search filter
        if (!empty($_GET['s'])) {
            $search = '%' . $wpdb->esc_like(sanitize_text_field($_GET['s'])) . '%';
            $where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR phone LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        // Combine where clause
        $where_clause = 'WHERE ' . implode(' AND ', $where);

        // Get total count
        $total_query = $wpdb->prepare("SELECT COUNT(*) FROM $bookings_table $where_clause", $params);
        $total_bookings = $wpdb->get_var($total_query);
        
        // Calculate total pages
        $total_pages = ceil($total_bookings / $per_page);

        // Get bookings query
        $bookings_query = $wpdb->prepare(
            "SELECT * FROM $bookings_table 
            $where_clause 
            ORDER BY booking_date DESC 
            LIMIT %d OFFSET %d", 
            array_merge($params, [$per_page, $offset])
        );
        $bookings = $wpdb->get_results($bookings_query);

        // Calculate statistics
        $stats = $this->calculate_booking_stats($bookings_table);

        // Get services for filter
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        return [
            'bookings' => $bookings,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
            'services' => $services,
            'stats' => $stats
        ];
    }

    /**
     * Calculate booking statistics
     */
    private function calculate_booking_stats($bookings_table) {
        global $wpdb;

        // Total bookings
        $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
        $total_bookings_last_month = $wpdb->get_var("
            SELECT COUNT(*) FROM $bookings_table 
            WHERE booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)
        ");

        // Pending bookings
        $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'pending'");
        $pending_bookings_last_month = $wpdb->get_var("
            SELECT COUNT(*) FROM $bookings_table 
            WHERE status = 'pending' 
            AND booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)
        ");

        // Completed bookings
        $completed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'completed'");
        $completed_bookings_last_month = $wpdb->get_var("
            SELECT COUNT(*) FROM $bookings_table 
            WHERE status = 'completed' 
            AND booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)
        ");

        // Total revenue
        $total_revenue = $wpdb->get_var("SELECT SUM(total_price) FROM $bookings_table WHERE status != 'canceled'");
        $total_revenue_last_month = $wpdb->get_var("
            SELECT SUM(total_price) FROM $bookings_table 
            WHERE status != 'canceled' 
            AND booking_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)
        ");

        return [
            'total' => $total_bookings,
            'total_trend' => $total_bookings_last_month > 0 
                ? (($total_bookings - $total_bookings_last_month) / $total_bookings_last_month) * 100 
                : 0,
            
            'pending' => $pending_bookings,
            'pending_trend' => $pending_bookings_last_month > 0 
                ? (($pending_bookings - $pending_bookings_last_month) / $pending_bookings_last_month) * 100 
                : 0,
            
            'completed' => $completed_bookings,
            'completed_trend' => $completed_bookings_last_month > 0 
                ? (($completed_bookings - $completed_bookings_last_month) / $completed_bookings_last_month) * 100 
                : 0,
            
            'total_revenue' => $total_revenue,
            'revenue_trend' => $total_revenue_last_month > 0 
                ? (($total_revenue - $total_revenue_last_month) / $total_revenue_last_month) * 100 
                : 0
        ];
    }

    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        if (!isset($_POST['bulk_action']) || $_POST['bulk_action'] === '-1') {
            return;
        }

        // Verify nonce
        check_admin_referer('vandel_bookings_bulk_action');

        // Check if any bookings are selected
        if (!isset($_POST['booking_ids']) || !is_array($_POST['booking_ids']) || empty($_POST['booking_ids'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                    __('No bookings selected for bulk action.', 'vandel-booking') . 
                    '</p></div>';
            });
            return;
        }

        $booking_ids = array_map('intval', $_POST['booking_ids']);
        $action = sanitize_key($_POST['bulk_action']);
        $success_count = 0;
        $error_count = 0;

        // Ensure BookingManager is loaded
        if (!class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            require_once VANDEL_PLUGIN_DIR . 'includes/booking/class-booking-manager.php';
        }

        $booking_manager = new \VandelBooking\Booking\BookingManager();

        // Process bulk action
        foreach ($booking_ids as $booking_id) {
            try {
                switch ($action) {
                    case 'confirm':
                        $result = $booking_manager->updateBookingStatus($booking_id, 'confirmed');
                        break;
                    case 'complete':
                        $result = $booking_manager->updateBookingStatus($booking_id, 'completed');
                        break;
                    case 'cancel':
                        $result = $booking_manager->updateBookingStatus($booking_id, 'canceled');
                        break;
                    case 'delete':
                        $result = $booking_manager->deleteBooking($booking_id);
                        break;
                    case 'export':
                        // Export selected bookings 
                        $this->export_bookings($booking_ids);
                        break;
                    default:
                        // Allow custom bulk actions via filter
                        $result = apply_filters('vandel_bookings_bulk_action', true, $booking_id, $action);
                        break;
                }

                // Track success/failure
                if ($result) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            } catch (\Exception $e) {
                $error_count++;
                error_log('Bulk action error for booking ' . $booking_id . ': ' . $e->getMessage());
            }
        }

        // Add admin notice
        add_action('admin_notices', function() use ($action, $success_count, $error_count) {
            $message = '';
            $type = 'success';

            switch ($action) {
                case 'confirm':
                    $message = sprintf(
                        _n('%d booking confirmed.', '%d bookings confirmed.', $success_count, 'vandel-booking'), 
                        $success_count
                    );
                    break;
                case 'complete':
                    $message = sprintf(
                        _n('%d booking completed.', '%d bookings completed.', $success_count, 'vandel-booking'), 
                        $success_count
                    );
                    break;
                case 'cancel':
                    $message = sprintf(
                        _n('%d booking canceled.', '%d bookings canceled.', $success_count, 'vandel-booking'), 
                        $success_count
                    );
                    break;
                case 'delete':
                    $message = sprintf(
                        _n('%d booking deleted.', '%d bookings deleted.', $success_count, 'vandel-booking'), 
                        $success_count
                    );
                    break;
                case 'export':
                    $message = sprintf(
                        _n('%d booking exported.', '%d bookings exported.', $success_count, 'vandel-booking'), 
                        $success_count
                    );
                    break;
            }

            // Add error message if any errors occurred
            if ($error_count > 0) {
                $message .= ' ' . sprintf(
                    _n('%d booking could not be processed.', '%d bookings could not be processed.', $error_count, 'vandel-booking'), 
                    $error_count
                );
                $type = 'warning';
            }

            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', 
                esc_attr($type), 
                esc_html($message)
            );
        });
    }

    /**
     * Export selected bookings to CSV
     * 
     * @param array $booking_ids Booking IDs to export
     */
    private function export_bookings($booking_ids) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';

        // Prepare bookings query
        $placeholders = implode(',', array_fill(0, count($booking_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT b.*, p.post_title as service_name 
             FROM $bookings_table b
             LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
             WHERE b.id IN ($placeholders)",
            $booking_ids
        );

        $bookings = $wpdb->get_results($query);

        // Prepare CSV headers
        $headers = [
            'ID', 
            'Service', 
            'Customer Name', 
            'Customer Email', 
            'Phone', 
            'Booking Date', 
            'Status', 
            'Total Price', 
            'Created At'
        ];

        // Prepare CSV data
        $csv_data = [
            $headers // First row is headers
        ];

        foreach ($bookings as $booking) {
            $csv_data[] = [
                $booking->id,
                $booking->service_name ?? 'Unknown Service',
                $booking->customer_name,
                $booking->customer_email,
                $booking->phone ?? '',
                $booking->booking_date,
                $booking->status,
                $booking->total_price,
                $booking->created_at
            ];
        }

        // Generate filename
        $filename = 'vandel_bookings_export_' . date('Y-m-d_H-i-s') . '.csv';

        // Output CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        // Open output stream
        $output = fopen('php://output', 'w');

        // Output CSV data
        foreach ($csv_data as $row) {
            fputcsv($output, $row);
        }

        // Close output stream
        fclose($output);
        exit;
    }
}