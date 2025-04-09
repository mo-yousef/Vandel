<?php
namespace VandelBooking\Admin;

use VandelBooking\Booking\BookingManager;
use VandelBooking\Booking\BookingNoteModel;
use VandelBooking\Helpers;

/**
 * Booking Details Management Class
 */
class BookingDetails {
    /**
     * @var BookingManager
     */
    private $booking_manager;
    
    /**
     * @var BookingNoteModel
     */
    private $note_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize booking manager
        if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
            $this->booking_manager = new BookingManager();
        }
        
        // Initialize note model
        if (class_exists('\\VandelBooking\\Booking\\BookingNoteModel')) {
            $this->note_model = new BookingNoteModel();
        }
        
        // Handle booking actions
        add_action('admin_init', [$this, 'handleBookingActions']);
    }
    
    /**
     * Handle booking actions
     */
    public function handleBookingActions() {
        // Only process if we're on our dashboard page with booking-details tab
        if (!isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'booking-details') {
            return;
        }
        
        $booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
        if ($booking_id === 0) {
            return;
        }
        
        // Handle status changes
        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            $action = sanitize_key($_GET['action']);
            
            // Verify nonce
            if (!wp_verify_nonce($_GET['_wpnonce'], $action . '_booking_' . $booking_id)) {
                wp_die(__('Security check failed', 'vandel-booking'));
                return;
            }
            
            switch ($action) {
                case 'approve':
                    $this->updateBookingStatus($booking_id, 'confirmed');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_approved'));
                    exit;
                    
                case 'cancel':
                    $this->updateBookingStatus($booking_id, 'canceled');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_canceled'));
                    exit;
                    
                case 'complete':
                    $this->updateBookingStatus($booking_id, 'completed');
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_completed'));
                    exit;
            }
        }
        
        // Handle adding notes
        if (isset($_POST['add_booking_note']) && isset($_POST['booking_note_nonce']) && 
            wp_verify_nonce($_POST['booking_note_nonce'], 'add_booking_note')) {
            
            $note_content = isset($_POST['note_content']) ? sanitize_textarea_field($_POST['note_content']) : '';
            
            if (!empty($note_content)) {
                $this->addBookingNote($booking_id, $note_content);
                wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=note_added'));
                exit;
            }
        }
    }
    
    /**
     * Update booking status
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Success
     */
    private function updateBookingStatus($booking_id, $status) {
        if ($this->booking_manager && method_exists($this->booking_manager, 'updateBookingStatus')) {
            return $this->booking_manager->updateBookingStatus($booking_id, $status);
        }
        
        // Fallback if BookingManager not available
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
            ['status' => $status],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        
        // Trigger status change action for other plugins
        if ($result && $old_status !== $status) {
            do_action('vandel_booking_status_changed', $booking_id, $old_status, $status);
            
            // Add note about status change
            $this->addBookingNote(
                $booking_id,
                sprintf(__('Status changed from %s to %s', 'vandel-booking'), 
                    ucfirst($old_status),
                    ucfirst($status)
                ),
                'system'
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Add booking note
     * 
     * @param int $booking_id Booking ID
     * @param string $note_content Note content
     * @param string $type Note type (user or system)
     * @return bool Success
     */
    private function addBookingNote($booking_id, $note_content, $type = 'user') {
        if ($this->note_model && method_exists($this->note_model, 'add')) {
            $user_id = get_current_user_id();
            $user_name = '';
            
            if ($user_id > 0) {
                $user = get_userdata($user_id);
                $user_name = $user ? $user->display_name : '';
            }
            
            return $this->note_model->add([
                'booking_id' => $booking_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'note_content' => $note_content,
                'note_type' => $type
            ]);
        }
        
        // Fallback if NoteModel not available
        global $wpdb;
        $notes_table = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes_table'") !== $notes_table) {
            return false;
        }
        
        $user_id = get_current_user_id();
        $user_name = '';
        
        if ($user_id > 0) {
            $user = get_userdata($user_id);
            $user_name = $user ? $user->display_name : '';
        }
        
        $result = $wpdb->insert(
            $notes_table,
            [
                'booking_id' => $booking_id,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'note_content' => $note_content,
                'note_type' => $type,
                'created_at' => current_time('mysql')
            ],
            [
                '%d', // booking_id
                '%d', // user_id
                '%s', // user_name
                '%s', // note_content
                '%s', // note_type
                '%s'  // created_at
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Get booking notes
     * 
     * @param int $booking_id Booking ID
     * @return array Notes
     */
    private function get_booking_notes($booking_id) {
        if ($this->note_model && method_exists($this->note_model, 'getByBookingId')) {
            return $this->note_model->getByBookingId($booking_id);
        }
        
        // Fallback if NoteModel not available
        global $wpdb;
        $notes_table = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes_table'") !== $notes_table) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $notes_table WHERE booking_id = %d ORDER BY created_at DESC",
            $booking_id
        ));
    }
    
    /**
     * Render booking details
     * 
     * @param int $booking_id Booking ID
     */
    public function render($booking_id) {
        // Get booking data
        $booking = null;
        
        if ($this->booking_manager && method_exists($this->booking_manager, 'getBooking')) {
            $booking = $this->booking_manager->getBooking($booking_id);
        } else {
            // Fallback to direct database query
            global $wpdb;
            $bookings_table = $wpdb->prefix . 'vandel_bookings';
            $booking = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $bookings_table WHERE id = %d",
                $booking_id
            ));
        }
        
        if (!$booking) {
            echo '<div class="notice notice-error"><p>' . __('Booking not found', 'vandel-booking') . '</p></div>';
            echo '<p><a href="' . admin_url('admin.php?page=vandel-dashboard&tab=bookings') . '" class="button">' . __('Back to Bookings List', 'vandel-booking') . '</a></p>';
            return;
        }
        
        // Display status messages
        $this->display_status_messages();
        
        // Render the booking details
        $this->render_booking_details($booking);
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
        
        switch ($_GET['message']) {
            case 'booking_approved':
                $message = __('Booking confirmed successfully.', 'vandel-booking');
                break;
                
            case 'booking_canceled':
                $message = __('Booking canceled successfully.', 'vandel-booking');
                break;
                
            case 'booking_completed':
                $message = __('Booking marked as completed.', 'vandel-booking');
                break;
                
            case 'note_added':
                $message = __('Note added successfully.', 'vandel-booking');
                break;
                
            case 'status_update_failed':
                $message = __('Failed to update booking status.', 'vandel-booking');
                $message_type = 'error';
                break;
                
            case 'note_failed':
                $message = __('Failed to add note.', 'vandel-booking');
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
     * Render booking details with improved layout
     * 
     * @param object $booking Booking object
     */
    private function render_booking_details($booking) {
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        $notes = $this->get_booking_notes($booking->id);
        
        ?>
        <div class="vandel-booking-details-grid">
            <div class="vandel-grid-col">
                <div class="vandel-card">
                    <div class="vandel-card-header vandel-flex-header">
                        <h3><?php _e('Booking Information', 'vandel-booking'); ?></h3>
                        <span class="vandel-status-badge vandel-status-badge-<?php echo esc_attr($booking->status); ?>">
                            <?php echo ucfirst($booking->status); ?>
                        </span>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-booking-main-details">
                            <div class="vandel-detail-group">
                                <div class="vandel-detail-label"><?php _e('Booking ID', 'vandel-booking'); ?></div>
                                <div class="vandel-detail-value">#<?php echo esc_html($booking->id); ?></div>
                            </div>
                            
                            <div class="vandel-detail-group">
                                <div class="vandel-detail-label"><?php _e('Created On', 'vandel-booking'); ?></div>
                                <div class="vandel-detail-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at)); ?></div>
                            </div>
                            
                            <div class="vandel-detail-group">
                                <div class="vandel-detail-label"><?php _e('Service', 'vandel-booking'); ?></div>
                                <div class="vandel-detail-value"><?php echo esc_html($service_name); ?></div>
                            </div>
                            
                            <div class="vandel-detail-group">
                                <div class="vandel-detail-label"><?php _e('Booking Date', 'vandel-booking'); ?></div>
                                <div class="vandel-detail-value"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date)); ?></div>
                            </div>
                            
                            <div class="vandel-detail-group">
                                <div class="vandel-detail-label"><?php _e('Total Price', 'vandel-booking'); ?></div>
                                <div class="vandel-detail-value"><?php echo Helpers::formatPrice($booking->total_price); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking->access_info)): ?>
                        <div class="vandel-detail-group">
                            <div class="vandel-detail-label"><?php _e('Access Information', 'vandel-booking'); ?></div>
                            <div class="vandel-detail-value"><?php echo nl2br(esc_html($booking->access_info)); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Booking timeline -->
                        <div class="vandel-booking-timeline">
                            <h4><?php _e('Booking History', 'vandel-booking'); ?></h4>
                            <div class="vandel-timeline-item">
                                <div class="vandel-timeline-dot"></div>
                                <div class="vandel-timeline-content">
                                    <div class="vandel-timeline-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->created_at)); ?></div>
                                    <div class="vandel-timeline-title"><?php _e('Booking Created', 'vandel-booking'); ?></div>
                                    <div class="vandel-timeline-description">
                                        <?php echo sprintf(__('Booking was created with status: %s', 'vandel-booking'), '<strong>' . ucfirst($booking->status) . '</strong>'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php foreach ($notes as $note): ?>
                            <div class="vandel-timeline-item">
                                <div class="vandel-timeline-dot"></div>
                                <div class="vandel-timeline-content">
                                    <div class="vandel-timeline-date"><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($note->created_at)); ?></div>
                                    <div class="vandel-timeline-title">
                                        <?php echo $note->user_name ? esc_html($note->user_name) : __('System', 'vandel-booking'); ?>
                                    </div>
                                    <div class="vandel-timeline-description">
                                        <?php echo nl2br(esc_html($note->note_content)); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="vandel-grid-col">
                <!-- Client Information -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Client Information', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-detail-group">
                            <div class="vandel-detail-label"><?php _e('Name', 'vandel-booking'); ?></div>
                            <div class="vandel-detail-value"><?php echo esc_html($booking->customer_name); ?></div>
                        </div>
                        
                        <div class="vandel-detail-group">
                            <div class="vandel-detail-label"><?php _e('Email', 'vandel-booking'); ?></div>
                            <div class="vandel-detail-value">
                                <a href="mailto:<?php echo esc_attr($booking->customer_email); ?>"><?php echo esc_html($booking->customer_email); ?></a>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking->phone)): ?>
                        <div class="vandel-detail-group">
                            <div class="vandel-detail-label"><?php _e('Phone', 'vandel-booking'); ?></div>
                            <div class="vandel-detail-value">
                                <a href="tel:<?php echo esc_attr($booking->phone); ?>"><?php echo esc_html($booking->phone); ?></a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($booking->client_id > 0): ?>
                        <div class="vandel-detail-group">
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $booking->client_id); ?>" class="button">
                                <span class="dashicons dashicons-admin-users"></span> <?php _e('View Full Client Profile', 'vandel-booking'); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Add Note -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Add Note', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <form method="post" action="">
                            <?php wp_nonce_field('add_booking_note', 'booking_note_nonce'); ?>
                            <input type="hidden" name="booking_id" value="<?php echo esc_attr($booking->id); ?>">
                            
                            <div class="vandel-form-row">
                                <label for="note_content"><?php _e('Note', 'vandel-booking'); ?></label>
                                <textarea id="note_content" name="note_content" rows="4" class="widefat" required></textarea>
                            </div>
                            
                            <div class="vandel-form-actions">
                                <button type="submit" name="add_booking_note" class="button button-primary">
                                    <?php _e('Add Note', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Booking Actions', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-action-buttons">
                            <?php if ($booking->status !== 'confirmed'): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=approve'), 'approve_booking_' . $booking->id); ?>" class="button button-primary" style="margin-right: 10px;">
                                <span class="dashicons dashicons-yes"></span> <?php _e('Confirm Booking', 'vandel-booking'); ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($booking->status !== 'completed'): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=complete'), 'complete_booking_' . $booking->id); ?>" class="button" style="margin-right: 10px;">
                                <span class="dashicons dashicons-saved"></span> <?php _e('Mark as Completed', 'vandel-booking'); ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($booking->status !== 'canceled'): ?>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=cancel'), 'cancel_booking_' . $booking->id); ?>" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to cancel this booking?', 'vandel-booking'); ?>');">
                                <span class="dashicons dashicons-dismiss"></span> <?php _e('Cancel Booking', 'vandel-booking'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}