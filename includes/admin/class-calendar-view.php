<?php
namespace VandelBooking\Admin;

/**
 * Calendar View Class
 * Implements the calendar view for the booking calendar
 */
class CalendarView {
    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }
    
    /**
     * Enqueue calendar assets
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        // Only load on our plugin's calendar page
        if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
            return;
        }
        
        // Check if we're on the calendar tab
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'overview';
        if ($tab !== 'calendar') {
            return;
        }
        
        // Enqueue FullCalendar library
        wp_enqueue_style(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css',
            [],
            '5.10.1'
        );
        
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js',
            [],
            '5.10.1',
            true
        );
        
        // Enqueue our calendar script
        wp_enqueue_script(
            'vandel-calendar',
            VANDEL_PLUGIN_URL . 'assets/js/admin/calendar.js',
            ['jquery', 'fullcalendar'],
            VANDEL_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('vandel-calendar', 'vandelCalendar', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_calendar_nonce'),
            'bookingDetailsUrl' => admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id='),
            'editBookingUrl' => admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=edit&booking_id='),
            'strings' => [
                'confirmCancel' => __('Are you sure you want to cancel this booking?', 'vandel-booking'),
                'statusChanged' => __('Booking status changed successfully.', 'vandel-booking'),
                'errorOccurred' => __('An error occurred. Please try again.', 'vandel-booking'),
                'loading' => __('Loading...', 'vandel-booking'),
                'viewDetails' => __('View Details', 'vandel-booking'),
                'editBooking' => __('Edit Booking', 'vandel-booking'),
                'cancelBooking' => __('Cancel Booking', 'vandel-booking'),
                'completeBooking' => __('Complete Booking', 'vandel-booking'),
                'clientDetails' => __('Client Details', 'vandel-booking'),
                'price' => __('Price:', 'vandel-booking'),
                'status' => __('Status:', 'vandel-booking')
            ],
            'statusColors' => [
                'pending' => '#f0ad4e',    // Warning yellow
                'confirmed' => '#5bc0de',  // Info blue
                'completed' => '#5cb85c',  // Success green
                'canceled' => '#d9534f'    // Danger red
            ]
        ]);
    }
    
    /**
     * Register AJAX handlers
     */
    public function registerAjaxHandlers() {
        add_action('wp_ajax_vandel_get_calendar_events', [$this, 'getCalendarEvents']);
        add_action('wp_ajax_vandel_update_booking_status', [$this, 'updateBookingStatus']);
    }
    
    /**
     * Get calendar events for AJAX request
     */
    public function getCalendarEvents() {
        // Verify nonce
        check_ajax_referer('vandel_calendar_nonce', 'nonce');
        
        // Get date range from request
        $start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : date('Y-m-d', strtotime('-1 month'));
        $end = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : date('Y-m-d', strtotime('+1 month'));
        
        // Get bookings within the date range
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            wp_send_json_error(['message' => __('Bookings table does not exist', 'vandel-booking')]);
            return;
        }
        
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, 
                    p.post_title as service_title 
             FROM $bookings_table b
             LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
             WHERE DATE(b.booking_date) BETWEEN %s AND %s
             ORDER BY b.booking_date ASC",
            $start,
            $end
        ));
        
        // Format bookings as calendar events
        $events = [];
        
        foreach ($bookings as $booking) {
            // Determine event color based on status
            $colors = [
                'pending' => '#f0ad4e',    // Warning yellow
                'confirmed' => '#5bc0de',  // Info blue
                'completed' => '#5cb85c',  // Success green
                'canceled' => '#d9534f'    // Danger red
            ];
            
            $color = isset($colors[$booking->status]) ? $colors[$booking->status] : '#777';
            
            // Create event object
            $events[] = [
                'id' => $booking->id,
                'title' => $booking->customer_name,
                'start' => $booking->booking_date,
                'end' => date('Y-m-d H:i:s', strtotime($booking->booking_date . ' +2 hours')), // Assuming 2-hour appointments
                'backgroundColor' => $color,
                'borderColor' => $color,
                'textColor' => '#fff',
                'extendedProps' => [
                    'customer_name' => $booking->customer_name,
                    'customer_email' => $booking->customer_email,
                    'phone' => $booking->phone,
                    'service' => $booking->service_title,
                    'total_price' => $booking->total_price,
                    'status' => $booking->status
                ]
            ];
        }
        
        wp_send_json_success($events);
    }
    
    /**
     * Render the calendar view
     */
    public function render() {
        // Get current month and year
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        
        // Validate month and year
        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));
        
        // Calculate previous and next month links
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }
        
        $prev_link = admin_url(sprintf(
            'admin.php?page=vandel-dashboard&tab=calendar&month=%d&year=%d',
            $prev_month,
            $prev_year
        ));
        
        $next_link = admin_url(sprintf(
            'admin.php?page=vandel-dashboard&tab=calendar&month=%d&year=%d',
            $next_month,
            $next_year
        ));
        
        // Header for the current month
        $month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
        ?>
        <div class="wrap vandel-calendar-wrap">
            <h1 class="wp-heading-inline"><?php _e('Booking Calendar', 'vandel-booking'); ?></h1>
            
            <div class="vandel-calendar-header">
                <div class="vandel-calendar-navigation">
                    <a href="<?php echo esc_url($prev_link); ?>" class="button vandel-prev-month">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php _e('Previous Month', 'vandel-booking'); ?>
                    </a>
                    <h2 class="vandel-calendar-title">
                        <?php echo esc_html($month_name . ' ' . $year); ?>
                    </h2>
                    <a href="<?php echo esc_url($next_link); ?>" class="button vandel-next-month">
                        <?php _e('Next Month', 'vandel-booking'); ?>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                </div>
                
                <div class="vandel-calendar-filters">
                    <select id="vandel-calendar-view-filter">
                        <option value="month"><?php _e('Month View', 'vandel-booking'); ?></option>
                        <option value="week"><?php _e('Week View', 'vandel-booking'); ?></option>
                        <option value="day"><?php _e('Day View', 'vandel-booking'); ?></option>
                    </select>
                    
                    <select id="vandel-calendar-status-filter">
                        <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                        <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                        <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                        <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                        <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                    </select>
                    
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="button button-primary vandel-add-booking">
                        <span class="dashicons dashicons-plus"></span>
                        <?php _e('Add Booking', 'vandel-booking'); ?>
                    </a>
                </div>
            </div>
            
            <div id="vandel-calendar" class="vandel-calendar-container"></div>
            
            <!-- Booking Detail Modal -->
            <div id="vandel-booking-modal" class="vandel-modal">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <div id="vandel-booking-details" class="vandel-booking-details">
                        <div class="vandel-booking-header">
                            <h3 id="vandel-modal-title"><?php _e('Booking Details', 'vandel-booking'); ?></h3>
                            <span id="vandel-booking-id" class="vandel-booking-id">#<span class="id-placeholder"></span></span>
                        </div>
                        
                        <div class="vandel-booking-info">
                            <p>
                                <strong><?php _e('Client:', 'vandel-booking'); ?></strong>
                                <span id="vandel-client-name"></span>
                            </p>
                            <p>
                                <strong><?php _e('Email:', 'vandel-booking'); ?></strong>
                                <span id="vandel-client-email"></span>
                            </p>
                            <p>
                                <strong><?php _e('Phone:', 'vandel-booking'); ?></strong>
                                <span id="vandel-client-phone"></span>
                            </p>
                            <p>
                                <strong><?php _e('Service:', 'vandel-booking'); ?></strong>
                                <span id="vandel-service-name"></span>
                            </p>
                            <p>
                                <strong><?php _e('Date:', 'vandel-booking'); ?></strong>
                                <span id="vandel-booking-date"></span>
                            </p>
                            <p>
                                <strong><?php _e('Price:', 'vandel-booking'); ?></strong>
                                <span id="vandel-booking-price"></span>
                            </p>
                            <p>
                                <strong><?php _e('Status:', 'vandel-booking'); ?></strong>
                                <span id="vandel-booking-status" class="vandel-status-badge"></span>
                            </p>
                        </div>
                        
                        <div class="vandel-booking-actions">
                            <a href="#" id="vandel-view-details" class="button" target="_blank">
                                <?php _e('View Details', 'vandel-booking'); ?>
                            </a>
                            <a href="#" id="vandel-edit-booking" class="button" target="_blank">
                                <?php _e('Edit Booking', 'vandel-booking'); ?>
                            </a>
                            <div class="vandel-status-actions">
                                <select id="vandel-change-status">
                                    <option value=""><?php _e('Change Status', 'vandel-booking'); ?></option>
                                    <option value="pending"><?php _e('Set as Pending', 'vandel-booking'); ?></option>
                                    <option value="confirmed"><?php _e('Confirm Booking', 'vandel-booking'); ?></option>
                                    <option value="completed"><?php _e('Mark as Completed', 'vandel-booking'); ?></option>
                                    <option value="canceled"><?php _e('Cancel Booking', 'vandel-booking'); ?></option>
                                </select>
                                <button id="vandel-update-status" class="button button-primary">
                                    <?php _e('Update', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Update booking status via AJAX
     */
    public function updateBookingStatus() {
        // Verify nonce
        check_ajax_referer('vandel_calendar_nonce', 'nonce');
        
        // Check for required data
        if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
            wp_send_json_error(['message' => __('Missing required data', 'vandel-booking')]);
            return;
        }
        
        $booking_id = intval($_POST['booking_id']);
        $new_status = sanitize_key($_POST['status']);
        
        // Validate status
        $valid_statuses = ['pending', 'confirmed', 'completed', 'canceled'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => __('Invalid status', 'vandel-booking')]);
            return;
        }
        
        // Update booking status
        $success = false;
        
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            // Use BookingManager if available
            $booking_manager = new \VandelBooking\Booking\BookingManager();
            $success = $booking_manager->updateBookingStatus($booking_id, $new_status);
        } else {
            // Fallback to direct database update
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'vandel_bookings';
            
            // Get current status for comparison
            $old_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $bookings_table WHERE id = %d",
                $booking_id
            ));
            
            // Update the status
            $result = $wpdb->update(
                $bookings_table,
                ['status' => $new_status],
                ['id' => $booking_id],
                ['%s'],
                ['%d']
            );
            
            $success = $result !== false;
            
            // Trigger status change action for other plugins
            if ($success && $old_status !== $new_status) {
                do_action('vandel_booking_status_changed', $booking_id, $old_status, $new_status);
            }
        }
        
        if ($success) {
            wp_send_json_success(['message' => __('Booking status updated successfully', 'vandel-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to update booking status', 'vandel-booking')]);
        }
    }
}