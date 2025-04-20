<?php
namespace VandelBooking\Frontend;

/**
 * Client Dashboard
 * Provides a frontend interface for clients to view and manage their bookings
 */
class ClientDashboard {
    /**
     * @var string Dashboard page slug
     */
    private $page_slug = 'booking-dashboard';
    
    /**
     * @var \VandelBooking\Booking\BookingManager
     */
    private $booking_manager;
    
    /**
     * @var \VandelBooking\Client\ClientModel
     */
    private $client_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize dependencies
        $this->initializeDependencies();
        
        // Register hooks
        add_action('init', [$this, 'registerShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // Register AJAX handlers
        add_action('wp_ajax_vandel_client_get_bookings', [$this, 'ajaxGetClientBookings']);
        add_action('wp_ajax_nopriv_vandel_client_get_bookings', [$this, 'ajaxGetClientBookings']);
        add_action('wp_ajax_vandel_client_cancel_booking', [$this, 'ajaxCancelBooking']);
        add_action('wp_ajax_nopriv_vandel_client_cancel_booking', [$this, 'ajaxCancelBooking']);
    }
    
    /**
     * Initialize dependencies
     */
    private function initializeDependencies() {
        // Initialize booking manager
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $this->booking_manager = new \VandelBooking\Booking\BookingManager();
        }
        
        // Initialize client model
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $this->client_model = new \VandelBooking\Client\ClientModel();
        }
    }
    
    /**
     * Register shortcode
     */
    public function registerShortcode() {
        add_shortcode('vandel_client_dashboard', [$this, 'renderDashboard']);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueueAssets() {
        global $post;
        
        // Only enqueue on pages with our shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'vandel_client_dashboard')) {
            wp_enqueue_style(
                'vandel-client-dashboard',
                VANDEL_PLUGIN_URL . 'assets/css/client-dashboard.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-client-dashboard',
                VANDEL_PLUGIN_URL . 'assets/js/client-dashboard.js',
                ['jquery'],
                VANDEL_VERSION,
                true
            );
            
            wp_localize_script(
                'vandel-client-dashboard',
                'vandelClientDashboard',
                [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vandel_client_dashboard'),
                    'strings' => [
                        'loading' => __('Loading your bookings...', 'vandel-booking'),
                        'noBookings' => __('No bookings found.', 'vandel-booking'),
                        'confirmCancel' => __('Are you sure you want to cancel this booking?', 'vandel-booking'),
                        'canceling' => __('Canceling booking...', 'vandel-booking'),
                        'error' => __('An error occurred. Please try again.', 'vandel-booking')
                    ]
                ]
            );
        }
    }
    
    /**
     * Render client dashboard
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public function renderDashboard($atts = []) {
        // Extract shortcode attributes
        $atts = shortcode_atts([
            'title' => __('My Bookings', 'vandel-booking'),
            'login_required' => 'no',
            'show_past' => 'yes',
            'allow_cancel' => 'yes'
        ], $atts);
        
        // Check if login is required and user is not logged in
        if ($atts['login_required'] === 'yes' && !is_user_logged_in()) {
            return $this->renderLoginPrompt();
        }
        
        // Start output buffer
        ob_start();
        ?>
        <div class="vandel-client-dashboard">
            <div class="vandel-dashboard-header">
                <h2><?php echo esc_html($atts['title']); ?></h2>
                
                <div class="vandel-dashboard-actions">
                    <div class="vandel-search-box">
                        <input type="text" id="vandel-booking-search" placeholder="<?php esc_attr_e('Search bookings...', 'vandel-booking'); ?>">
                        <button type="button" id="vandel-search-button">
                            <span class="dashicons dashicons-search"></span>
                        </button>
                    </div>
                    
                    <div class="vandel-view-toggle">
                        <button type="button" class="vandel-view-btn active" data-view="all">
                            <?php _e('All', 'vandel-booking'); ?>
                        </button>
                        <button type="button" class="vandel-view-btn" data-view="upcoming">
                            <?php _e('Upcoming', 'vandel-booking'); ?>
                        </button>
                        <?php if ($atts['show_past'] === 'yes'): ?>
                        <button type="button" class="vandel-view-btn" data-view="past">
                            <?php _e('Past', 'vandel-booking'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="vandel-dashboard-authentication">
                <div class="vandel-auth-form">
                    <h3><?php _e('Access Your Bookings', 'vandel-booking'); ?></h3>
                    <p><?php _e('Enter your email to see all your bookings.', 'vandel-booking'); ?></p>
                    
                    <div class="vandel-form-group">
                        <label for="vandel-client-email"><?php _e('Email Address', 'vandel-booking'); ?></label>
                        <input type="email" id="vandel-client-email" required>
                    </div>
                    
                    <button type="button" id="vandel-authenticate-btn" class="vandel-btn">
                        <?php _e('View My Bookings', 'vandel-booking'); ?>
                    </button>
                </div>
            </div>
            
            <div class="vandel-bookings-container" style="display: none;">
                <div id="vandel-bookings-loading" class="vandel-loading">
                    <span class="vandel-spinner"></span>
                    <p><?php _e('Loading your bookings...', 'vandel-booking'); ?></p>
                </div>
                
                <div id="vandel-bookings-list" class="vandel-bookings-list"></div>
                
                <div id="vandel-no-bookings" class="vandel-empty-state" style="display: none;">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <p><?php _e('No bookings found.', 'vandel-booking'); ?></p>
                    <a href="<?php echo esc_url(get_permalink(get_option('vandel_booking_page'))); ?>" class="vandel-btn">
                        <?php _e('Book Now', 'vandel-booking'); ?>
                    </a>
                </div>
            </div>
            
            <!-- Booking Details Modal -->
            <div id="vandel-booking-details-modal" class="vandel-modal">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <h3><?php _e('Booking Details', 'vandel-booking'); ?></h3>
                    
                    <div id="vandel-booking-details-content"></div>
                    
                    <div class="vandel-modal-actions">
                        <?php if ($atts['allow_cancel'] === 'yes'): ?>
                        <button type="button" id="vandel-cancel-booking" class="vandel-btn vandel-btn-danger">
                            <?php _e('Cancel Booking', 'vandel-booking'); ?>
                        </button>
                        <?php endif; ?>
                        
                        <button type="button" class="vandel-btn vandel-btn-neutral vandel-modal-close">
                            <?php _e('Close', 'vandel-booking'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render login prompt for non-logged in users
     * 
     * @return string Rendered HTML
     */
    private function renderLoginPrompt() {
        ob_start();
        ?>
        <div class="vandel-login-prompt">
            <div class="vandel-login-message">
                <h3><?php _e('Please Log In', 'vandel-booking'); ?></h3>
                <p><?php _e('You need to be logged in to view your bookings.', 'vandel-booking'); ?></p>
                
                <?php if (function_exists('wp_login_url')): ?>
                <a href="<?php echo esc_url(wp_login_url(get_permalink())); ?>" class="vandel-btn">
                    <?php _e('Log In', 'vandel-booking'); ?>
                </a>
                <?php endif; ?>
                
                <?php if (function_exists('wp_registration_url')): ?>
                <a href="<?php echo esc_url(wp_registration_url()); ?>" class="vandel-btn vandel-btn-secondary">
                    <?php _e('Register', 'vandel-booking'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * AJAX handler to get client bookings
     */
    public function ajaxGetClientBookings() {
        // Verify nonce
        check_ajax_referer('vandel_client_dashboard', 'nonce');
        
        try {
            // Get client email
            $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
            
            if (empty($email)) {
                throw new \Exception(__('Email is required', 'vandel-booking'));
            }
            
            // Get view filter
            $view = isset($_POST['view']) ? sanitize_key($_POST['view']) : 'all';
            
            // Get search term
            $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
            
            // Get client bookings
            $bookings = $this->getClientBookings($email, $view, $search);
            
            if (empty($bookings)) {
                wp_send_json_success([
                    'bookings' => [],
                    'count' => 0,
                    'html' => ''
                ]);
                return;
            }
            
            // Render bookings HTML
            $html = $this->renderBookingsList($bookings);
            
            // Return success response
            wp_send_json_success([
                'bookings' => $bookings,
                'count' => count($bookings),
                'html' => $html
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Get client bookings based on email
     * 
     * @param string $email Client email
     * @param string $view View filter (all, upcoming, past)
     * @param string $search Search term
     * @return array Bookings
     */
    private function getClientBookings($email, $view = 'all', $search = '') {
        global $wpdb;
        
        // Get bookings table
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Build basic query
        $query = "SELECT b.*, p.post_title as service_name 
                 FROM $bookings_table b
                 LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
                 WHERE b.customer_email = %s";
        
        $params = [$email];
        
        // Add view filter
        if ($view === 'upcoming') {
            $query .= " AND b.booking_date >= %s";
            $params[] = current_time('mysql');
        } elseif ($view === 'past') {
            $query .= " AND b.booking_date < %s";
            $params[] = current_time('mysql');
        }
        
        // Add search filter
        if (!empty($search)) {
            $query .= " AND (b.id LIKE %s OR p.post_title LIKE %s OR b.status LIKE %s OR b.customer_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        // Add order by
        $query .= " ORDER BY b.booking_date DESC";
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, $params);
        $bookings = $wpdb->get_results($prepared_query);
        
        // Process bookings
        foreach ($bookings as &$booking) {
            // Format dates
            $booking->formatted_date = date_i18n(get_option('date_format'), strtotime($booking->booking_date));
            $booking->formatted_time = date_i18n(get_option('time_format'), strtotime($booking->booking_date));
            
            // Format status
            $booking->status_label = $this->getStatusLabel($booking->status);
            
            // Calculate if cancellable
            $booking->can_cancel = $this->canCancelBooking($booking);
        }
        
        return $bookings;
    }
    
    /**
     * Render bookings list HTML
     * 
     * @param array $bookings Bookings array
     * @return string Rendered HTML
     */
    private function renderBookingsList($bookings) {
        ob_start();
        
        if (empty($bookings)) {
            return '';
        }
        
        ?>
        <div class="vandel-bookings-grid">
            <?php foreach ($bookings as $booking): ?>
                <div class="vandel-booking-card" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                    <div class="vandel-booking-header">
                        <div class="vandel-booking-id">#<?php echo esc_html($booking->id); ?></div>
                        <div class="vandel-booking-status <?php echo esc_attr('status-' . $booking->status); ?>">
                            <?php echo esc_html($booking->status_label); ?>
                        </div>
                    </div>
                    
                    <div class="vandel-booking-body">
                        <h4 class="vandel-booking-service"><?php echo esc_html($booking->service_name); ?></h4>
                        
                        <div class="vandel-booking-details">
                            <div class="vandel-booking-detail">
                                <span class="vandel-detail-icon dashicons dashicons-calendar-alt"></span>
                                <span class="vandel-detail-text"><?php echo esc_html($booking->formatted_date); ?></span>
                            </div>
                            
                            <div class="vandel-booking-detail">
                                <span class="vandel-detail-icon dashicons dashicons-clock"></span>
                                <span class="vandel-detail-text"><?php echo esc_html($booking->formatted_time); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-booking-footer">
                        <button type="button" class="vandel-btn vandel-btn-view-details" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <?php _e('View Details', 'vandel-booking'); ?>
                        </button>
                        
                        <?php if ($booking->can_cancel): ?>
                        <button type="button" class="vandel-btn vandel-btn-cancel" data-booking-id="<?php echo esc_attr($booking->id); ?>">
                            <?php _e('Cancel', 'vandel-booking'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Check if booking can be canceled
     * 
     * @param object $booking Booking object
     * @return bool Whether booking can be canceled
     */
    private function canCancelBooking($booking) {
        // Only pending or confirmed bookings can be canceled
        if (!in_array($booking->status, ['pending', 'confirmed'])) {
            return false;
        }
        
        // Check cancellation window
        $cancellation_window = intval(get_option('vandel_booking_cancellation_window', 24));
        if ($cancellation_window > 0) {
            $booking_time = strtotime($booking->booking_date);
            $current_time = current_time('timestamp');
            $time_diff = $booking_time - $current_time;
            $hours_diff = $time_diff / 3600; // Convert to hours
            
            // Check if we're within the cancellation window
            if ($hours_diff < $cancellation_window) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get human-readable status label
     * 
     * @param string $status Status key
     * @return string Status label
     */
    private function getStatusLabel($status) {
        $status_labels = [
            'pending' => __('Pending', 'vandel-booking'),
            'confirmed' => __('Confirmed', 'vandel-booking'),
            'completed' => __('Completed', 'vandel-booking'),
            'canceled' => __('Canceled', 'vandel-booking')
        ];
        
        return isset($status_labels[$status]) ? $status_labels[$status] : ucfirst($status);
    }
}