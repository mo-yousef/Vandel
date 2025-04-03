<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Calendar Tab
 * Handles the booking calendar view
 */
class Calendar_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // Register AJAX handlers for calendar
        add_action('wp_ajax_vandel_get_calendar_events', [$this, 'get_calendar_events']);
        add_action('wp_ajax_vandel_get_booking_details', [$this, 'get_booking_details']);
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // No immediate actions to process for calendar tab
    }
    
    /**
     * Render tab content
     */
    public function render() {
        // Get available services for filtering
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Get current month/year
        $current_month = date('m');
        $current_year = date('Y');
        
        if (isset($_GET['month']) && isset($_GET['year'])) {
            $month = intval($_GET['month']);
            $year = intval($_GET['year']);
            
            // Validate month/year
            if ($month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
                $current_month = $month;
                $current_year = $year;
            }
        }
        
        // Get month name
        $month_name = date_i18n('F Y', strtotime("{$current_year}-{$current_month}-01"));
        
        // Get previous/next month
        $prev_month = $current_month == 1 ? 12 : $current_month - 1;
        $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
        
        $next_month = $current_month == 12 ? 1 : $current_month + 1;
        $next_year = $current_month == 12 ? $current_year + 1 : $current_year;
        
        // Get booking status counts
        $status_counts = $this->get_status_counts($current_year, $current_month);
        
        ?>
        <div id="calendar" class="vandel-tab-content">
            <div class="vandel-calendar-wrap">
                <div class="vandel-calendar-header">
                    <div class="vandel-calendar-navigation">
                        <a href="<?php echo add_query_arg(['month' => $prev_month, 'year' => $prev_year]); ?>" class="button">
                            <span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e('Previous Month', 'vandel-booking'); ?>
                        </a>
                        <h2 class="vandel-calendar-title"><?php echo $month_name; ?></h2>
                        <a href="<?php echo add_query_arg(['month' => $next_month, 'year' => $next_year]); ?>" class="button">
                            <?php _e('Next Month', 'vandel-booking'); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </a>
                    </div>
                    
                    <div class="vandel-calendar-filters">
                        <select id="vandel-calendar-service-filter" class="vandel-calendar-filter">
                            <option value=""><?php _e('All Services', 'vandel-booking'); ?></option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo esc_attr($service->ID); ?>"><?php echo esc_html($service->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select id="vandel-calendar-status-filter" class="vandel-calendar-filter">
                            <option value=""><?php _e('All Statuses', 'vandel-booking'); ?></option>
                            <option value="pending"><?php _e('Pending', 'vandel-booking'); ?></option>
                            <option value="confirmed"><?php _e('Confirmed', 'vandel-booking'); ?></option>
                            <option value="completed"><?php _e('Completed', 'vandel-booking'); ?></option>
                            <option value="canceled"><?php _e('Canceled', 'vandel-booking'); ?></option>
                        </select>
                        
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add'); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add Booking', 'vandel-booking'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="vandel-calendar-stats">
                    <div class="vandel-stat-summary">
                        <div class="vandel-stat-card vandel-stat-all">
                            <span class="vandel-stat-count"><?php echo number_format_i18n($status_counts['total']); ?></span>
                            <span class="vandel-stat-label"><?php _e('Total', 'vandel-booking'); ?></span>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-pending">
                            <span class="vandel-stat-count"><?php echo number_format_i18n($status_counts['pending']); ?></span>
                            <span class="vandel-stat-label"><?php _e('Pending', 'vandel-booking'); ?></span>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-confirmed">
                            <span class="vandel-stat-count"><?php echo number_format_i18n($status_counts['confirmed']); ?></span>
                            <span class="vandel-stat-label"><?php _e('Confirmed', 'vandel-booking'); ?></span>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-completed">
                            <span class="vandel-stat-count"><?php echo number_format_i18n($status_counts['completed']); ?></span>
                            <span class="vandel-stat-label"><?php _e('Completed', 'vandel-booking'); ?></span>
                        </div>
                        
                        <div class="vandel-stat-card vandel-stat-canceled">
                            <span class="vandel-stat-count"><?php echo number_format_i18n($status_counts['canceled']); ?></span>
                            <span class="vandel-stat-label"><?php _e('Canceled', 'vandel-booking'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="vandel-calendar-container">
                    <div id="vandel-calendar"></div>
                </div>
            </div>
            
            <!-- Booking Details Modal -->
            <div id="vandel-booking-modal" class="vandel-modal">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <div class="vandel-booking-details-content">
                        <!-- Content will be populated via AJAX -->
                        <div class="vandel-loading">
                            <span class="spinner is-active"></span>
                            <p><?php _e('Loading booking details...', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            // Enqueue FullCalendar
            wp_enqueue_script('vandel-fullcalendar', VANDEL_PLUGIN_URL . 'assets/js/fullcalendar.min.js', ['jquery'], VANDEL_VERSION, true);
            wp_enqueue_style('vandel-fullcalendar-style', VANDEL_PLUGIN_URL . 'assets/css/fullcalendar.min.css', [], VANDEL_VERSION);
            
            // Add inline script for calendar
            $calendar_settings = [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_calendar_nonce'),
                'month' => $current_month,
                'year' => $current_year,
                'texts' => [
                    'loading' => __('Loading...', 'vandel-booking'),
                    'noEvents' => __('No bookings found', 'vandel-booking'),
                    'allDay' => __('All Day', 'vandel-booking'),
                    'today' => __('Today', 'vandel-booking'),
                    'month' => __('Month', 'vandel-booking'),
                    'week' => __('Week', 'vandel-booking'),
                    'day' => __('Day', 'vandel-booking'),
                    'list' => __('List', 'vandel-booking')
                ]
            ];
            
            wp_add_inline_script('vandel-fullcalendar', '
                document.addEventListener("DOMContentLoaded", function() {
                    const calendarEl = document.getElementById("vandel-calendar");
                    const modal = document.getElementById("vandel-booking-modal");
                    const closeBtn = document.querySelector(".vandel-modal-close");
                    const serviceFilter = document.getElementById("vandel-calendar-service-filter");
                    const statusFilter = document.getElementById("vandel-calendar-status-filter");
                    
                    const settings = ' . json_encode($calendar_settings) . ';
                    
                    // Initialize FullCalendar
                    const calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: "dayGridMonth",
                        initialDate: settings.year + "-" + String(settings.month).padStart(2, "0") + "-01",
                        headerToolbar: {
                            left: "prev,next today",
                            center: "title",
                            right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth"
                        },
                        navLinks: true,
                        editable: false,
                        dayMaxEvents: true,
                        events: function(info, successCallback, failureCallback) {
                            // Fetch events via AJAX
                            jQuery.ajax({
                                url: settings.ajaxUrl,
                                type: "POST",
                                data: {
                                    action: "vandel_get_calendar_events",
                                    nonce: settings.nonce,
                                    start: info.startStr,
                                    end: info.endStr,
                                    service: serviceFilter.value,
                                    status: statusFilter.value
                                },
                                success: function(response) {
                                    if (response.success) {
                                        successCallback(response.data);
                                    } else {
                                        failureCallback(response.data);
                                    }
                                },
                                error: function() {
                                    failureCallback("Error loading events");
                                }
                            });
                        },
                        eventClick: function(info) {
                            // Show booking details in modal
                            showBookingDetails(info.event.id);
                        },
                        eventTimeFormat: {
                            hour: "2-digit",
                            minute: "2-digit",
                            meridiem: "short"
                        },
                        buttonText: {
                            today: settings.texts.today,
                            month: settings.texts.month,
                            week: settings.texts.week,
                            day: settings.texts.day,
                            list: settings.texts.list
                        }
                    });
                    
                    calendar.render();
                    
                    // Handle filters
                    serviceFilter.addEventListener("change", function() {
                        calendar.refetchEvents();
                    });
                    
                    statusFilter.addEventListener("change", function() {
                        calendar.refetchEvents();
                    });
                    
                    // Modal functionality
                    function showBookingDetails(bookingId) {
                        const contentDiv = document.querySelector(".vandel-booking-details-content");
                        contentDiv.innerHTML = \'<div class="vandel-loading"><span class="spinner is-active"></span><p>\' + settings.texts.loading + \'</p></div>\';
                        modal.style.display = "block";
                        
                        // Fetch booking details via AJAX
                        jQuery.ajax({
                            url: settings.ajaxUrl,
                            type: "POST",
                            data: {
                                action: "vandel_get_booking_details",
                                nonce: settings.nonce,
                                booking_id: bookingId
                            },
                            success: function(response) {
                                if (response.success) {
                                    contentDiv.innerHTML = response.data;
                                    
                                    // Initialize status update actions
                                    initStatusActions();
                                } else {
                                    contentDiv.innerHTML = "<p>Error: " + response.data + "</p>";
                                }
                            },
                            error: function() {
                                contentDiv.innerHTML = "<p>Error loading booking details</p>";
                            }
                        });
                    }
                    
                    function initStatusActions() {
                        const statusBtns = document.querySelectorAll(".vandel-status-action");
                        statusBtns.forEach(btn => {
                            btn.addEventListener("click", function(e) {
                                e.preventDefault();
                                const bookingId = this.getAttribute("data-booking-id");
                                const status = this.getAttribute("data-status");
                                
                                // Update booking status via AJAX
                                jQuery.ajax({
                                    url: settings.ajaxUrl,
                                    type: "POST",
                                    data: {
                                        action: "vandel_update_booking_status",
                                        nonce: settings.nonce,
                                        booking_id: bookingId,
                                        status: status
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            // Refresh calendar events
                                            calendar.refetchEvents();
                                            
                                            // Close modal
                                            modal.style.display = "none";
                                        } else {
                                            alert("Error: " + response.data);
                                        }
                                    },
                                    error: function() {
                                        alert("Error updating booking status");
                                    }
                                });
                            });
                        });
                    }
                    
                    // Close modal
                    closeBtn.addEventListener("click", function() {
                        modal.style.display = "none";
                    });
                    
                    window.addEventListener("click", function(event) {
                        if (event.target == modal) {
                            modal.style.display = "none";
                        }
                    });
                });
            ');
            ?>
        </div>
        <?php
    }
    
    /**
     * Get booking status counts for a specific month
     * 
     * @param int $year Year
     * @param int $month Month
     * @return array Status counts
     */
    private function get_status_counts($year, $month) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Initialize counts
        $counts = [
            'total' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0,
            'canceled' => 0
        ];
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return $counts;
        }
        
        // Build date range
        $start_date = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $end_date = date('Y-m-t 23:59:59', strtotime($start_date));
        
        // Get counts
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count
             FROM $bookings_table
             WHERE booking_date BETWEEN %s AND %s
             GROUP BY status",
            $start_date,
            $end_date
        ));
        
        if ($results) {
            foreach ($results as $result) {
                if (isset($counts[$result->status])) {
                    $counts[$result->status] = intval($result->count);
                    $counts['total'] += intval($result->count);
                }
            }
        }
        
        return $counts;
    }
    
    /**
     * AJAX handler to get calendar events
     */
    public function get_calendar_events() {
        // Verify nonce
        check_ajax_referer('vandel_calendar_nonce', 'nonce');
        
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : '';
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : '';
        $service = isset($_POST['service']) ? intval($_POST['service']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';
        
        // Get bookings for date range
        $bookings = $this->get_bookings_for_calendar($start, $end, $service, $status);
        
        wp_send_json_success($bookings);
    }
    
    /**
     * AJAX handler to get booking details
     */
    public function get_booking_details() {
        // Verify nonce
        check_ajax_referer('vandel_calendar_nonce', 'nonce');
        
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        
        if (!$booking_id) {
            wp_send_json_error(__('Invalid booking ID', 'vandel-booking'));
            return;
        }
        
        // Get booking details
        $booking = $this->get_booking($booking_id);
        
        if (!$booking) {
            wp_send_json_error(__('Booking not found', 'vandel-booking'));
            return;
        }
        
        // Get service details
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        
        // Format status class
        $status_classes = [
            'pending' => 'warning',
            'confirmed' => 'info',
            'completed' => 'success',
            'canceled' => 'danger'
        ];
        
        $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
        
        // Build HTML for booking details
        $html = '
        <div class="vandel-booking-header">
            <h3>' . __('Booking Details', 'vandel-booking') . '</h3>
            <span class="vandel-booking-id">#' . $booking_id . '</span>
        </div>
        
        <div class="vandel-booking-info">
            <p><strong>' . __('Client:', 'vandel-booking') . '</strong> ' . esc_html($booking->customer_name) . '</p>
            <p><strong>' . __('Email:', 'vandel-booking') . '</strong> <a href="mailto:' . esc_attr($booking->customer_email) . '">' . esc_html($booking->customer_email) . '</a></p>
            <p><strong>' . __('Service:', 'vandel-booking') . '</strong> ' . esc_html($service_name) . '</p>
            <p><strong>' . __('Date & Time:', 'vandel-booking') . '</strong> ' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)) . '</p>
            <p><strong>' . __('Price:', 'vandel-booking') . '</strong> ' . \VandelBooking\Helpers::formatPrice($booking->total_price) . '</p>
            <p><strong>' . __('Status:', 'vandel-booking') . '</strong> <span class="vandel-status-badge vandel-status-badge-' . $status_class . '">' . ucfirst($booking->status) . '</span></p>
        </div>
        
        <div class="vandel-booking-actions">
            <a href="' . admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id) . '" class="button">
                ' . __('View Full Details', 'vandel-booking') . '
            </a>
            
            <div class="vandel-status-actions">';
        
        // Add status change buttons
        if ($booking->status !== 'confirmed') {
            $html .= '
                <a href="#" class="button vandel-status-action" data-booking-id="' . $booking_id . '" data-status="confirmed">
                    ' . __('Confirm', 'vandel-booking') . '
                </a>';
        }
        
        if ($booking->status !== 'completed') {
            $html .= '
                <a href="#" class="button vandel-status-action" data-booking-id="' . $booking_id . '" data-status="completed">
                    ' . __('Complete', 'vandel-booking') . '
                </a>';
        }
        
        if ($booking->status !== 'canceled') {
            $html .= '
                <a href="#" class="button vandel-status-action" data-booking-id="' . $booking_id . '" data-status="canceled">
                    ' . __('Cancel', 'vandel-booking') . '
                </a>';
        }
        
        $html .= '
            </div>
        </div>';
        
        wp_send_json_success($html);
    }
    
    /**
     * Get bookings for calendar
     * 
     * @param string $start Start date
     * @param string $end End date
     * @param int $service Service ID filter
     * @param string $status Status filter
     * @return array Bookings formatted for FullCalendar
     */
    private function get_bookings_for_calendar($start, $end, $service = 0, $status = '') {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return [];
        }
        
        // Build query
        $where = ['1=1'];
        $values = [];
        
        if ($start && $end) {
            $where[] = 'booking_date BETWEEN %s AND %s';
            $values[] = $start;
            $values[] = $end;
        }
        
        if ($service > 0) {
            $where[] = 'service = %d';
            $values[] = $service;
        }
        
        if ($status) {
            $where[] = 'status = %s';
            $values[] = $status;
        }
        
        // Execute query
        $query = "SELECT * FROM $bookings_table WHERE " . implode(' AND ', $where) . " ORDER BY booking_date ASC";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        $bookings = $wpdb->get_results($query);
        
        // Format for FullCalendar
        $events = [];
        
        if ($bookings) {
            foreach ($bookings as $booking) {
                // Define colors based on booking status
                $colors = [
                    'pending' => '#f0ad4e',   // Yellow/Orange
                    'confirmed' => '#5bc0de', // Blue
                    'completed' => '#5cb85c', // Green
                    'canceled' => '#d9534f'   // Red
                ];
                
                $color = isset($colors[$booking->status]) ? $colors[$booking->status] : '#999999';
                
                // Get service name
                $service = get_post($booking->service);
                $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
                
                // Format time - if booking has time component or is all day
                $has_time = strtotime($booking->booking_date) !== strtotime(date('Y-m-d', strtotime($booking->booking_date)));
                
                $events[] = [
                    'id' => $booking->id,
                    'title' => esc_html($booking->customer_name) . ' - ' . esc_html($service_name),
                    'start' => $booking->booking_date,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'status' => $booking->status,
                        'service' => $service_name
                    ],
                    'allDay' => !$has_time
                ];
            }
        }
        
        return $events;
    }
    
    /**
     * Get booking by ID
     * 
     * @param int $booking_id Booking ID
     * @return object|false Booking object or false if not found
     */
    private function get_booking($booking_id) {
        // Try to use BookingManager if available
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $booking_manager = new \VandelBooking\Booking\BookingManager();
            return $booking_manager->getBooking($booking_id);
        }
        
        // Fallback to direct database query
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $bookings_table WHERE id = %d",
            $booking_id
        ));
    }
}