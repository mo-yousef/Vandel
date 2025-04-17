<?php
namespace VandelBooking\Admin;

use VandelBooking\Booking\BookingManager;
use VandelBooking\Booking\NoteModel;
use VandelBooking\Helpers;

/**
 * Enhanced Booking Details Management Class
 * Provides a more comprehensive view of booking details
 */
class BookingDetails {
    /**
     * @var BookingManager
     */
    private $booking_manager;
    
    /**
     * @var NoteModel
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
        if (class_exists('\\VandelBooking\\Booking\\NoteModel')) {
            $this->note_model = new NoteModel();
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
        
        // Handle updating booking details
        if (isset($_POST['update_booking']) && isset($_POST['booking_update_nonce']) && 
            wp_verify_nonce($_POST['booking_update_nonce'], 'update_booking_' . $booking_id)) {
            
            $this->updateBookingDetails($booking_id, $_POST);
            wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_updated'));
            exit;
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
        if ($this->note_model && method_exists($this->note_model, 'addNote')) {
            $user_id = get_current_user_id();
            return $this->note_model->addNote($booking_id, $note_content, $user_id);
        }
        
        // Fallback if NoteModel not available
        global $wpdb;
        $notes_table = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes_table'") !== $notes_table) {
            return false;
        }
        
        $user_id = get_current_user_id();
        
        $result = $wpdb->insert(
            $notes_table,
            [
                'booking_id' => $booking_id,
                'note_content' => $note_content,
                'created_at' => current_time('mysql'),
                'created_by' => $user_id
            ],
            [
                '%d', // booking_id
                '%s', // note_content
                '%s', // created_at
                '%d'  // created_by
            ]
        );
        
        return $result !== false;
    }
    
    /**
     * Update booking details
     * 
     * @param int $booking_id Booking ID
     * @param array $data Form data
     * @return bool Success
     */
    private function updateBookingDetails($booking_id, $data) {
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Prepare booking data for update
        $update_data = [];
        
        // Service ID
        if (isset($data['service_id']) && !empty($data['service_id'])) {
            $update_data['service'] = intval($data['service_id']);
        }
        
        // Date and time
        if (isset($data['booking_date']) && !empty($data['booking_date'])) {
            $update_data['booking_date'] = sanitize_text_field($data['booking_date']);
        }
        
        // Status
        if (isset($data['booking_status']) && !empty($data['booking_status'])) {
            $old_status = $wpdb->get_var($wpdb->prepare(
                "SELECT status FROM $bookings_table WHERE id = %d",
                $booking_id
            ));
            
            $new_status = sanitize_text_field($data['booking_status']);
            $update_data['status'] = $new_status;
            
            // Add status change note if status changed
            if ($old_status !== $new_status) {
                $this->addBookingNote(
                    $booking_id,
                    sprintf(__('Status changed from %s to %s', 'vandel-booking'), 
                        ucfirst($old_status),
                        ucfirst($new_status)
                    ),
                    'system'
                );
                
                // Trigger status change action for other plugins
                do_action('vandel_booking_status_changed', $booking_id, $old_status, $new_status);
            }
        }
        
        // Total price
        if (isset($data['total_price']) && !empty($data['total_price'])) {
            $update_data['total_price'] = floatval($data['total_price']);
        }
        
        // Customer name
        if (isset($data['customer_name']) && !empty($data['customer_name'])) {
            $update_data['customer_name'] = sanitize_text_field($data['customer_name']);
        }
        
        // Customer email
        if (isset($data['customer_email']) && !empty($data['customer_email'])) {
            $update_data['customer_email'] = sanitize_email($data['customer_email']);
        }
        
        // Phone
        if (isset($data['phone'])) {
            $update_data['phone'] = sanitize_text_field($data['phone']);
        }
        
        // Access info
        if (isset($data['access_info'])) {
            $update_data['access_info'] = sanitize_textarea_field($data['access_info']);
        }
        
        // Only update if there's data to update
        if (empty($update_data)) {
            return false;
        }
        
        // Add updated_at timestamp
        $update_data['updated_at'] = current_time('mysql');
        
        // Perform update
        $result = $wpdb->update(
            $bookings_table,
            $update_data,
            ['id' => $booking_id],
            array_map(function ($value) {
                return is_numeric($value) ? (is_float($value) ? '%f' : '%d') : '%s';
            }, $update_data),
            ['%d']
        );
        
        // Add note about update
        if ($result !== false) {
            $this->addBookingNote(
                $booking_id,
                __('Booking details updated by admin', 'vandel-booking'),
                'system'
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Get booking notes
     * 
     * @param int $booking_id Booking ID
     * @return array Notes
     */
    private function get_booking_notes($booking_id) {
        if ($this->note_model && method_exists($this->note_model, 'getNotes')) {
            return $this->note_model->getNotes($booking_id);
        }
        
        // Fallback if NoteModel not available
        global $wpdb;
        $notes_table = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes_table'") !== $notes_table) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT n.*, u.display_name as user_name 
             FROM $notes_table n 
             LEFT JOIN {$wpdb->users} u ON n.created_by = u.ID 
             WHERE booking_id = %d 
             ORDER BY created_at DESC",
            $booking_id
        ));
    }
    
    /**
     * Get client details
     * 
     * @param int $client_id Client ID
     * @return object|false Client object or false if not found
     */
    private function get_client($client_id) {
        if ($client_id <= 0) {
            return false;
        }
        
        global $wpdb;
        $clients_table = $wpdb->prefix . 'vandel_clients';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$clients_table'") !== $clients_table) {
            return false;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $clients_table WHERE id = %d",
            $client_id
        ));
    }
    
    /**
     * Get client booking history
     * 
     * @param int $client_id Client ID
     * @param int $current_booking_id Current booking ID to exclude
     * @return array Client's bookings
     */
    private function get_client_bookings($client_id, $current_booking_id) {
        if ($client_id <= 0) {
            return [];
        }
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT b.*, p.post_title as service_name 
             FROM $bookings_table b
             LEFT JOIN {$wpdb->posts} p ON b.service = p.ID
             WHERE b.client_id = %d AND b.id != %d
             ORDER BY b.booking_date DESC
             LIMIT 5",
            $client_id, $current_booking_id
        ));
    }
    
    /**
     * Get sub services data
     * 
     * @param string $sub_services_json JSON string of sub services
     * @return array Sub services data
     */
    private function get_sub_services_data($sub_services_json) {
        $sub_services = [];
        
        if (empty($sub_services_json)) {
            return $sub_services;
        }
        
        $sub_services_array = json_decode($sub_services_json, true);
        if (!is_array($sub_services_array)) {
            return $sub_services;
        }
        
        foreach ($sub_services_array as $id => $value) {
            $sub_service = get_post($id);
            if (!$sub_service) {
                continue;
            }
            
            $sub_services[] = [
                'id' => $id,
                'name' => $sub_service->post_title,
                'value' => $value,
                'price' => get_post_meta($id, '_vandel_sub_service_price', true),
                'type' => get_post_meta($id, '_vandel_sub_service_type', true)
            ];
        }
        
        return $sub_services;
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
        
        // Get service info
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        
        // Get service price
        $service_price = 0;
        if ($service) {
            $service_price = get_post_meta($service->ID, '_vandel_service_base_price', true);
        }
        
        // Get sub services data
        $sub_services = $this->get_sub_services_data($booking->sub_services);
        
        // Get client info if available
        $client = null;
        if (!empty($booking->client_id)) {
            $client = $this->get_client($booking->client_id);
        }
        
        // Get client's other bookings
        $client_bookings = [];
        if ($client) {
            $client_bookings = $this->get_client_bookings($client->id, $booking_id);
        }
        
        // Get booking notes
        $notes = $this->get_booking_notes($booking_id);
        
        // Get services for the edit form
        $services = get_posts([
            'post_type' => 'vandel_service',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        // Display status messages
        $this->display_status_messages();
        
        // Render the booking details
        $this->render_booking_details($booking, $service_name, $service_price, $service, $sub_services, $client, $client_bookings, $notes, $services);
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
                
            case 'booking_updated':
                $message = __('Booking details updated successfully.', 'vandel-booking');
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
     * Render enhanced booking details with improved layout
     */
    private function render_booking_details($booking, $service_name, $service_price, $service, $sub_services, $client, $client_bookings, $notes, $services) {
        $booking_date = new \DateTime($booking->booking_date);
        $created_date = new \DateTime($booking->created_at);
        
        $status_classes = [
            'pending' => 'vandel-status-badge-warning',
            'confirmed' => 'vandel-status-badge-success',
            'completed' => 'vandel-status-badge-info',
            'canceled' => 'vandel-status-badge-danger'
        ];
        
        $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
        
        // Calculate subtotals
        $sub_services_total = 0;
        foreach ($sub_services as $sub_service) {
            if ($sub_service['type'] === 'number') {
                $sub_services_total += floatval($sub_service['price']) * intval($sub_service['value']);
            } else {
                $sub_services_total += floatval($sub_service['price']);
            }
        }
        
        $base_price = floatval($service_price);
        $adjustments = floatval($booking->total_price) - ($base_price + $sub_services_total);
        
        ?>
<div class="vandel-booking-details-container">
    <div class="vandel-booking-header-bar">
        <div class="vandel-booking-id">
            <h2><?php printf(__('Booking #%s', 'vandel-booking'), $booking->id); ?></h2>
        </div>
        <div class="vandel-booking-status">
            <span
                class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span>
        </div>
        <div class="vandel-booking-actions">
            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php _e('Back to Bookings', 'vandel-booking'); ?>
            </a>
            <?php if ($booking->status !== 'confirmed'): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=approve'), 'approve_booking_' . $booking->id); ?>"
                class="button button-primary">
                <span class="dashicons dashicons-yes"></span> <?php _e('Confirm Booking', 'vandel-booking'); ?>
            </a>
            <?php endif; ?>
            <?php if ($booking->status !== 'completed'): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=complete'), 'complete_booking_' . $booking->id); ?>"
                class="button button-secondary">
                <span class="dashicons dashicons-saved"></span> <?php _e('Mark as Completed', 'vandel-booking'); ?>
            </a>
            <?php endif; ?>
            <?php if ($booking->status !== 'canceled'): ?>
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=cancel'), 'cancel_booking_' . $booking->id); ?>"
                class="button button-secondary">
                <span class="dashicons dashicons-dismiss"></span> <?php _e('Cancel Booking', 'vandel-booking'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="vandel-booking-details-grid">
        <!-- Main Booking Information Column -->
        <div class="vandel-grid-col vandel-main-col">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Booking Information', 'vandel-booking'); ?></h3>
                    <button type="button" class="vandel-edit-toggle button button-small"
                        data-target="booking-info-edit">
                        <span class="dashicons dashicons-edit"></span> <?php _e('Edit', 'vandel-booking'); ?>
                    </button>
                </div>
                <div class="vandel-card-body">
                    <!-- View mode -->
                    <div id="booking-info-view" class="vandel-view-mode">
                        <div class="vandel-info-grid">
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Service', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value"><?php echo esc_html($service_name); ?></span>
                            </div>
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Date & Time', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value">
                                    <?php echo $booking_date->format(get_option('date_format') . ' ' . get_option('time_format')); ?>
                                </span>
                            </div>
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Status', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value">
                                    <span
                                        class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span>
                                </span>
                            </div>
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Created', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value">
                                    <?php echo $created_date->format(get_option('date_format') . ' ' . get_option('time_format')); ?>
                                </span>
                            </div>
                            <?php if (!empty($booking->updated_at)): ?>
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Last Updated', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value">
                                    <?php 
                                            $updated_date = new \DateTime($booking->updated_at);
                                            echo $updated_date->format(get_option('date_format') . ' ' . get_option('time_format')); 
                                            ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <div class="vandel-info-item">
                                <span class="vandel-info-label"><?php _e('Total Price', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value vandel-price-value">
                                    <?php echo Helpers::formatPrice($booking->total_price); ?>
                                </span>
                            </div>
                            <?php if (!empty($booking->access_info)): ?>
                            <div class="vandel-info-item vandel-info-full">
                                <span
                                    class="vandel-info-label"><?php _e('Access Information', 'vandel-booking'); ?></span>
                                <span class="vandel-info-value">
                                    <?php echo nl2br(esc_html($booking->access_info)); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Edit mode (initially hidden) -->
                    <div id="booking-info-edit" class="vandel-edit-mode" style="display: none;">
                        <form method="post" action="" class="vandel-form">
                            <?php wp_nonce_field('update_booking_' . $booking->id, 'booking_update_nonce'); ?>
                            <input type="hidden" name="update_booking" value="1">

                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="service_id"><?php _e('Service', 'vandel-booking'); ?></label>
                                    <select name="service_id" id="service_id" class="vandel-select">
                                        <?php foreach ($services as $service_item): ?>
                                        <option value="<?php echo $service_item->ID; ?>"
                                            <?php selected($booking->service, $service_item->ID); ?>>
                                            <?php echo esc_html($service_item->post_title); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="vandel-form-group">
                                    <label for="booking_date"><?php _e('Date & Time', 'vandel-booking'); ?></label>
                                    <input type="datetime-local" id="booking_date" name="booking_date"
                                        value="<?php echo $booking_date->format('Y-m-d\TH:i'); ?>"
                                        class="vandel-datetime-field">
                                </div>
                            </div>

                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="booking_status"><?php _e('Status', 'vandel-booking'); ?></label>
                                    <select name="booking_status" id="booking_status" class="vandel-select">
                                        <option value="pending" <?php selected($booking->status, 'pending'); ?>>
                                            <?php _e('Pending', 'vandel-booking'); ?></option>
                                        <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>>
                                            <?php _e('Confirmed', 'vandel-booking'); ?></option>
                                        <option value="completed" <?php selected($booking->status, 'completed'); ?>>
                                            <?php _e('Completed', 'vandel-booking'); ?></option>
                                        <option value="canceled" <?php selected($booking->status, 'canceled'); ?>>
                                            <?php _e('Canceled', 'vandel-booking'); ?></option>
                                    </select>
                                </div>
                                <div class="vandel-form-group">
                                    <label for="total_price"><?php _e('Total Price', 'vandel-booking'); ?></label>