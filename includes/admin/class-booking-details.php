<?php
namespace VandelBooking\Admin;

use VandelBooking\Booking\BookingManager;
use VandelBooking\Booking\NoteModel;
use VandelBooking\Helpers;

/**
 * Enhanced Booking Details Management Class
 * Provides a more comprehensive view of booking details with fixed action handling
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
        
        // Handle booking actions - high priority to run early
        add_action('admin_init', [$this, 'handleBookingActions'], 5);
    }
    
    /**
     * Handle booking actions with improved debugging and status handling
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
        
        // Debug log
        error_log("BookingDetails->handleBookingActions: Processing for booking ID $booking_id");
        
        // Handle status changes
        if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
            $action = sanitize_key($_GET['action']);
            
            // Debug log
            error_log("BookingDetails->handleBookingActions: Processing action '$action'");
            
            // Check nonce
            $nonce = $_GET['_wpnonce'];
            $expected_nonce_action = $action . '_booking_' . $booking_id;
            $valid_nonce = wp_verify_nonce($nonce, $expected_nonce_action);
            
            if (!$valid_nonce) {
                error_log("BookingDetails->handleBookingActions: Nonce verification failed for action '$action'");
                wp_die(__('Security check failed. Please try again.', 'vandel-booking'));
                return;
            }
            
            error_log("BookingDetails->handleBookingActions: Nonce verification passed for action '$action'");
            
            switch ($action) {
                case 'approve':
                    $result = $this->updateBookingStatus($booking_id, 'confirmed');
                    if ($result) {
                        error_log("BookingDetails->handleBookingActions: Successfully approved booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_approved'));
                        exit;
                    } else {
                        error_log("BookingDetails->handleBookingActions: Failed to approve booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=status_update_failed'));
                        exit;
                    }
                    break;
                    
                case 'cancel':
                    $result = $this->updateBookingStatus($booking_id, 'canceled');
                    if ($result) {
                        error_log("BookingDetails->handleBookingActions: Successfully canceled booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_canceled'));
                        exit;
                    } else {
                        error_log("BookingDetails->handleBookingActions: Failed to cancel booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=status_update_failed'));
                        exit;
                    }
                    break;
                    
                case 'complete':
                    $result = $this->updateBookingStatus($booking_id, 'completed');
                    if ($result) {
                        error_log("BookingDetails->handleBookingActions: Successfully completed booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_completed'));
                        exit;
                    } else {
                        error_log("BookingDetails->handleBookingActions: Failed to complete booking #$booking_id");
                        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=status_update_failed'));
                        exit;
                    }
                    break;
                    
                case 'download_invoice':
                    $this->downloadInvoice($booking_id);
                    exit;
                    
                default:
                    error_log("BookingDetails->handleBookingActions: Unknown action '$action'");
                    break;
            }
        }
        
        // Handle adding notes
        if (isset($_POST['add_booking_note']) && isset($_POST['booking_note_nonce']) && 
            wp_verify_nonce($_POST['booking_note_nonce'], 'add_booking_note')) {
            
            $note_content = isset($_POST['note_content']) ? sanitize_textarea_field($_POST['note_content']) : '';
            
            if (!empty($note_content)) {
                $result = $this->addBookingNote($booking_id, $note_content);
                if ($result) {
                    error_log("BookingDetails->handleBookingActions: Successfully added note to booking #$booking_id");
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=note_added'));
                    exit;
                } else {
                    error_log("BookingDetails->handleBookingActions: Failed to add note to booking #$booking_id");
                    wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=note_failed'));
                    exit;
                }
            }
        }
        
        // Handle updating booking details
        if (isset($_POST['update_booking']) && isset($_POST['booking_update_nonce']) && 
            wp_verify_nonce($_POST['booking_update_nonce'], 'update_booking_' . $booking_id)) {
            
            $result = $this->updateBookingDetails($booking_id, $_POST);
            if ($result) {
                error_log("BookingDetails->handleBookingActions: Successfully updated booking #$booking_id");
                wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_updated'));
                exit;
            } else {
                error_log("BookingDetails->handleBookingActions: Failed to update booking #$booking_id");
                wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_update_failed'));
                exit;
            }
        }
    }
    
    /**
     * Update booking status with enhanced error handling
     * 
     * @param int $booking_id Booking ID
     * @param string $status New status
     * @return bool Success
     */
    private function updateBookingStatus($booking_id, $status) {
        error_log("BookingDetails->updateBookingStatus: Attempting to change booking #$booking_id status to '$status'");
        
        // First try to use BookingManager
        if ($this->booking_manager && method_exists($this->booking_manager, 'updateBookingStatus')) {
            error_log("BookingDetails->updateBookingStatus: Using BookingManager");
            $result = $this->booking_manager->updateBookingStatus($booking_id, $status);
            
            // Debug log
            if ($result) {
                error_log("BookingDetails->updateBookingStatus: Successfully updated status using BookingManager");
            } else {
                error_log("BookingDetails->updateBookingStatus: Failed to update status using BookingManager");
            }
            
            return $result;
        }
        
        // Fallback if BookingManager not available
        error_log("BookingDetails->updateBookingStatus: Using direct database query (fallback)");
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            error_log("BookingDetails->updateBookingStatus: Bookings table does not exist");
            return false;
        }
        
        // Get current status for comparison
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $bookings_table WHERE id = %d",
            $booking_id
        ));
        
        if ($old_status === null) {
            error_log("BookingDetails->updateBookingStatus: Could not retrieve current status for booking #$booking_id");
            return false;
        }
        
        error_log("BookingDetails->updateBookingStatus: Current status is '$old_status'");
        
        // Update the status
        $result = $wpdb->update(
            $bookings_table,
            ['status' => $status],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        
        // Debug log
        if ($result === false) {
            error_log("BookingDetails->updateBookingStatus: Database error: " . $wpdb->last_error);
            return false;
        } elseif ($result === 0) {
            // This happens when the status is already set to the requested value
            error_log("BookingDetails->updateBookingStatus: No rows affected (booking #$booking_id already has status '$status')");
            
            // Consider this a success if the status already matches what we want
            if ($old_status === $status) {
                return true;
            }
            
            return false;
        }
        
        error_log("BookingDetails->updateBookingStatus: Successfully updated status in database");
        
        // Trigger status change action for other plugins
        if ($old_status !== $status) {
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
        
        return true;
    }
    
    /**
     * Add booking note with improved error handling
     * 
     * @param int $booking_id Booking ID
     * @param string $note_content Note content
     * @param string $type Note type (user or system)
     * @return int|bool Note ID or false on failure
     */
    private function addBookingNote($booking_id, $note_content, $type = 'user') {
        // Try to use NoteModel
        if ($this->note_model && method_exists($this->note_model, 'addNote')) {
            $user_id = $type === 'system' ? 0 : get_current_user_id();
            return $this->note_model->addNote($booking_id, $note_content, $user_id);
        }
        
        // Fallback if NoteModel not available
        global $wpdb;
        $notes_table = $wpdb->prefix . 'vandel_booking_notes';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$notes_table'") !== $notes_table) {
            error_log("BookingDetails->addBookingNote: Notes table does not exist");
            return false;
        }
        
        $user_id = $type === 'system' ? 0 : get_current_user_id();
        
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
        
        if ($result === false) {
            error_log("BookingDetails->addBookingNote: Database error: " . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
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
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            error_log("BookingDetails->updateBookingDetails: Bookings table does not exist");
            return false;
        }
        
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
        if (isset($data['total_price']) && $data['total_price'] !== '') {
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
            error_log("BookingDetails->updateBookingDetails: No data to update");
            return false;
        }
        
        // Add updated_at timestamp
        $update_data['updated_at'] = current_time('mysql');
        
        // Prepare formats for wpdb update
        $formats = [];
        foreach ($update_data as $key => $value) {
            if (is_numeric($value)) {
                $formats[] = is_float($value + 0) ? '%f' : '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        // Perform update
        $result = $wpdb->update(
            $bookings_table,
            $update_data,
            ['id' => $booking_id],
            $formats,
            ['%d']
        );
        
        if ($result === false) {
            error_log("BookingDetails->updateBookingDetails: Database error: " . $wpdb->last_error);
            return false;
        }
        
        // Add note about update
        $this->addBookingNote(
            $booking_id,
            __('Booking details updated by admin', 'vandel-booking'),
            'system'
        );
        
        return true;
    }












/**
 * Render location information section
 * 
 * @param object $booking Booking object
 */
private function render_location_info($booking) {
    $location_info = null;
    
    // Try to parse access_info as JSON first
    if (!empty($booking->access_info)) {
        $decoded = json_decode($booking->access_info, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $location_info = $decoded;
        }
    }
    
    // If not JSON, use it as a ZIP code
    $zip_code = empty($location_info) ? $booking->access_info : ($location_info['zip_code'] ?? '');
    
    // Get location details from the database if available
    $location_details = null;
    
    if (!empty($zip_code) && class_exists('\\VandelBooking\\Location\\LocationModel')) {
        $location_model = new \VandelBooking\Location\LocationModel();
        $location_details = $location_model->getByZipCode($zip_code);
    }
    
    if (!$location_details && !empty($zip_code) && class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
        $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
        $location_details = $zip_code_model->get($zip_code);
    }
    
    // Get location adjustment and fees from post meta
    $location_adjustment = get_post_meta($booking->id, '_vandel_location_adjustment', true);
    $location_fee = get_post_meta($booking->id, '_vandel_location_fee', true);
    
    // Display location information
    ?>
    <div class="vandel-card vandel-location-info-card">
        <div class="vandel-card-header">
            <h3><?php _e('Location Information', 'vandel-booking'); ?></h3>
        </div>
        <div class="vandel-card-body">
            <?php if ($location_details || $location_info): ?>
                <div class="vandel-location-details">
                    <div class="vandel-location-field">
                        <label><?php _e('ZIP Code:', 'vandel-booking'); ?></label>
                        <span><?php echo esc_html($zip_code); ?></span>
                    </div>
                    
                    <?php if (!empty($location_info['area_name']) || !empty($location_details->area_name)): ?>
                    <div class="vandel-location-field">
                        <label><?php _e('Area:', 'vandel-booking'); ?></label>
                        <span><?php echo esc_html($location_info['area_name'] ?? $location_details->area_name); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="vandel-location-field">
                        <label><?php _e('City:', 'vandel-booking'); ?></label>
                        <span><?php echo esc_html($location_info['city'] ?? $location_details->city ?? '--'); ?></span>
                    </div>
                    
                    <?php if (!empty($location_info['state']) || !empty($location_details->state)): ?>
                    <div class="vandel-location-field">
                        <label><?php _e('State/Region:', 'vandel-booking'); ?></label>
                        <span><?php echo esc_html($location_info['state'] ?? $location_details->state); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="vandel-location-field">
                        <label><?php _e('Country:', 'vandel-booking'); ?></label>
                        <span><?php echo esc_html($location_info['country'] ?? $location_details->country ?? '--'); ?></span>
                    </div>
                    
                    <?php if (!empty($location_adjustment) || !empty($location_details->price_adjustment)): 
                        $adjustment = !empty($location_adjustment) ? $location_adjustment : $location_details->price_adjustment;
                        $sign = floatval($adjustment) >= 0 ? '+' : '';
                    ?>
                    <div class="vandel-location-field">
                        <label><?php _e('Location Adjustment:', 'vandel-booking'); ?></label>
                        <span><?php echo $sign . \VandelBooking\Helpers::formatPrice($adjustment); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($location_fee) || !empty($location_details->service_fee)): 
                        $fee = !empty($location_fee) ? $location_fee : $location_details->service_fee;
                    ?>
                    <div class="vandel-location-field">
                        <label><?php _e('Service Fee:', 'vandel-booking'); ?></label>
                        <span><?php echo \VandelBooking\Helpers::formatPrice($fee); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p><?php _e('No location information available for this booking.', 'vandel-booking'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}











    /**
     * Generate and download booking invoice
     * 
     * @param int $booking_id Booking ID
     */
    private function downloadInvoice($booking_id) {
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
            wp_die(__('Booking not found', 'vandel-booking'));
            return;
        }
        
        // Get service information
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        
        // Business information
        $business_name = get_option('vandel_business_name', get_bloginfo('name'));
        $business_address = get_option('vandel_business_address', '');
        $business_phone = get_option('vandel_business_phone', '');
        $business_email = get_option('vandel_business_email', get_option('admin_email'));
        
        // Generate invoice number
        $invoice_prefix = get_option('vandel_invoice_prefix', 'INV-');
        $invoice_number = $invoice_prefix . $booking_id . '-' . date('Ymd');
        
        // Prepare booking date
        $booking_date = new \DateTime($booking->booking_date);
        $formatted_date = $booking_date->format(get_option('date_format') . ' ' . get_option('time_format'));
        
        // Prepare invoice date
        $invoice_date = new \DateTime();
        $formatted_invoice_date = $invoice_date->format(get_option('date_format'));
        
        // Base price
        $base_price = $service ? floatval(get_post_meta($service->ID, '_vandel_service_base_price', true)) : 0;
        
        // Parse sub services
        $sub_services = [];
        $sub_services_total = 0;
        
        if (!empty($booking->sub_services)) {
            $parsed_sub_services = is_string($booking->sub_services) 
                ? json_decode($booking->sub_services, true) 
                : (is_object($booking->sub_services) ? (array)$booking->sub_services : $booking->sub_services);
            
            if (is_array($parsed_sub_services)) {
                foreach ($parsed_sub_services as $id => $value) {
                    $sub_service = get_post($id);
                    if (!$sub_service) continue;
                    
                    $price = floatval(get_post_meta($id, '_vandel_service_base_price', true));
                    
                    // Handle different sub service types
                    $sub_service_type = get_post_meta($id, '_vandel_sub_service_type', true);
                    
                    if ($sub_service_type === 'number' && is_numeric($value)) {
                        $item_price = $price * intval($value);
                        $sub_services[] = [
                            'name' => $sub_service->post_title,
                            'value' => intval($value),
                            'price' => $item_price
                        ];
                        $sub_services_total += $item_price;
                    } else {
                        $sub_services[] = [
                            'name' => $sub_service->post_title,
                            'value' => $value,
                            'price' => $price
                        ];
                        $sub_services_total += $price;
                    }
                }
            }
        }
        
        // Calculate potential adjustments
        $adjustments = floatval($booking->total_price) - ($base_price + $sub_services_total);
        
        // Start generating PDF
        if (!class_exists('TCPDF')) {
            // Fallback to HTML invoice
            $this->generateHtmlInvoice($booking, $invoice_number, $formatted_invoice_date, 
                $service_name, $formatted_date, $base_price, $sub_services, $sub_services_total, 
                $adjustments, $business_name, $business_address, $business_phone, $business_email);
            return;
        }
        
        // Initialize PDF if TCPDF is available
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($business_name);
        $pdf->SetTitle('Invoice #' . $invoice_number);
        $pdf->SetSubject('Booking Invoice');
        
        // Set default header data
        $pdf->SetHeaderData('', 0, $business_name, 'Invoice #' . $invoice_number);
        
        // Set margins
        $pdf->SetMargins(15, 25, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Create HTML content for PDF
        $html = '<h1>INVOICE</h1>';
        $html .= '<div style="margin-bottom: 20px;">';
        $html .= '<div style="width: 50%; float: left;">';
        $html .= '<h3>' . $business_name . '</h3>';
        $html .= '<p>' . nl2br($business_address) . '</p>';
        $html .= '<p>Phone: ' . $business_phone . '</p>';
        $html .= '<p>Email: ' . $business_email . '</p>';
        $html .= '</div>';
        
        $html .= '<div style="width: 50%; float: right; text-align: right;">';
        $html .= '<h3>Invoice #' . $invoice_number . '</h3>';
        $html .= '<p>Date: ' . $formatted_invoice_date . '</p>';
        $html .= '<p>Booking ID: #' . $booking_id . '</p>';
        $html .= '</div>';
        $html .= '<div style="clear: both;"></div>';
        $html .= '</div>';
        
        // Client information
        $html .= '<div style="margin-bottom: 20px;">';
        $html .= '<h3>Bill To:</h3>';
        $html .= '<p>' . $booking->customer_name . '</p>';
        $html .= '<p>Email: ' . $booking->customer_email . '</p>';
        if (!empty($booking->phone)) {
            $html .= '<p>Phone: ' . $booking->phone . '</p>';
        }
        $html .= '</div>';
        
        // Line items
        $html .= '<table border="1" cellpadding="5" style="width: 100%;">';
        $html .= '<tr style="background-color: #f5f5f5; font-weight: bold;">';
        $html .= '<th width="10%" style="text-align: left;">#</th>';
        $html .= '<th width="40%" style="text-align: left;">Description</th>';
        $html .= '<th width="15%" style="text-align: right;">Quantity</th>';
        $html .= '<th width="15%" style="text-align: right;">Unit Price</th>';
        $html .= '<th width="20%" style="text-align: right;">Amount</th>';
        $html .= '</tr>';
        
        // Base service
        $html .= '<tr>';
        $html .= '<td>1</td>';
        $html .= '<td>' . $service_name . '<br>Date: ' . $formatted_date . '</td>';
        $html .= '<td style="text-align: right;">1</td>';
        $html .= '<td style="text-align: right;">' . Helpers::formatPrice($base_price) . '</td>';
        $html .= '<td style="text-align: right;">' . Helpers::formatPrice($base_price) . '</td>';
        $html .= '</tr>';
        
        // Sub services
        $count = 2;
        foreach ($sub_services as $sub_service) {
            $html .= '<tr>';
            $html .= '<td>' . $count . '</td>';
            $html .= '<td>' . $sub_service['name'];
            if (isset($sub_service['value']) && $sub_service['value'] !== 'yes') {
                $html .= '<br>Option: ' . $sub_service['value'];
            }
            $html .= '</td>';
            $html .= '<td style="text-align: right;">1</td>';
            $html .= '<td style="text-align: right;">' . Helpers::formatPrice($sub_service['price']) . '</td>';
            $html .= '<td style="text-align: right;">' . Helpers::formatPrice($sub_service['price']) . '</td>';
            $html .= '</tr>';
            $count++;
        }
        
        // Adjustments if any
        if ($adjustments != 0) {
            $html .= '<tr>';
            $html .= '<td>' . $count . '</td>';
            $html .= '<td>Additional fees/discounts</td>';
            $html .= '<td style="text-align: right;">1</td>';
            $html .= '<td style="text-align: right;">' . Helpers::formatPrice($adjustments) . '</td>';
            $html .= '<td style="text-align: right;">' . Helpers::formatPrice($adjustments) . '</td>';
            $html .= '</tr>';
        }
        
        // Totals
        $html .= '<tr>';
        $html .= '<td colspan="4" style="text-align: right; font-weight: bold;">Total</td>';
        $html .= '<td style="text-align: right; font-weight: bold;">' . Helpers::formatPrice($booking->total_price) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        // Payment information and notes
        $html .= '<div style="margin-top: 30px;">';
        $html .= '<h3>Payment Information</h3>';
        $html .= '<p>Status: ' . ucfirst($booking->status) . '</p>';
        $html .= '<p>Thank you for your business!</p>';
        $html .= '</div>';
        
        // Output the PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Close and output PDF document
        $pdf->Output('invoice_' . $invoice_number . '.pdf', 'D');
        exit;
    }
    
    /**
     * Generate HTML invoice (fallback if TCPDF is not available)
     */
    private function generateHtmlInvoice($booking, $invoice_number, $invoice_date, $service_name, 
                                        $booking_date, $base_price, $sub_services, $sub_services_total, 
                                        $adjustments, $business_name, $business_address, $business_phone, $business_email) {
        
        // Start HTML document
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="invoice_' . $invoice_number . '.html"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice #' . $invoice_number . '</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.5; color: #333; }
                .container { width: 800px; margin: 0 auto; padding: 20px; }
                .header { margin-bottom: 30px; }
                .business-info { float: left; width: 60%; }
                .invoice-info { float: right; width: 40%; text-align: right; }
                .client-info { margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-right { text-align: right; }
                .total-row { font-weight: bold; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; }
                .clearfix:after { content: ""; display: table; clear: both; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header clearfix">
                    <div class="business-info">
                        <h1>' . $business_name . '</h1>
                        <p>' . nl2br($business_address) . '</p>
                        <p>Phone: ' . $business_phone . '</p>
                        <p>Email: ' . $business_email . '</p>
                    </div>
                    <div class="invoice-info">
                        <h2>INVOICE</h2>
                        <p><strong>Invoice #:</strong> ' . $invoice_number . '</p>
                        <p><strong>Date:</strong> ' . $invoice_date . '</p>
                        <p><strong>Booking ID:</strong> #' . $booking->id . '</p>
                    </div>
                </div>
                
                <div class="client-info">
                    <h3>Bill To:</h3>
                    <p>' . $booking->customer_name . '</p>
                    <p>Email: ' . $booking->customer_email . '</p>';
                    
                    if (!empty($booking->phone)) {
                        echo '<p>Phone: ' . $booking->phone . '</p>';
                    }
                    
                echo '</div>
                
                <table>
                    <tr>
                        <th width="5%">#</th>
                        <th width="45%">Description</th>
                        <th width="15%" class="text-right">Quantity</th>
                        <th width="15%" class="text-right">Unit Price</th>
                        <th width="20%" class="text-right">Amount</th>
                    </tr>
                    
                    <tr>
                        <td>1</td>
                        <td>' . $service_name . '<br>Date: ' . $booking_date . '</td>
                        <td class="text-right">1</td>
                        <td class="text-right">' . Helpers::formatPrice($base_price) . '</td>
                        <td class="text-right">' . Helpers::formatPrice($base_price) . '</td>
                    </tr>';
                    
                    // Sub services
                    $count = 2;
                    foreach ($sub_services as $sub_service) {
                        echo '<tr>
                            <td>' . $count . '</td>
                            <td>' . $sub_service['name'];
                            
                            if (isset($sub_service['value']) && $sub_service['value'] !== 'yes') {
                                echo '<br>Option: ' . $sub_service['value'];
                            }
                            
                            echo '</td>
                            <td class="text-right">1</td>
                            <td class="text-right">' . Helpers::formatPrice($sub_service['price']) . '</td>
                            <td class="text-right">' . Helpers::formatPrice($sub_service['price']) . '</td>
                        </tr>';
                        $count++;
                    }
                    
                    // Adjustments if any
                    if ($adjustments != 0) {
                        echo '<tr>
                            <td>' . $count . '</td>
                            <td>Additional fees/discounts</td>
                            <td class="text-right">1</td>
                            <td class="text-right">' . Helpers::formatPrice($adjustments) . '</td>
                            <td class="text-right">' . Helpers::formatPrice($adjustments) . '</td>
                        </tr>';
                    }
                    
                    // Total
                    echo '<tr class="total-row">
                        <td colspan="4" class="text-right">Total</td>
                        <td class="text-right">' . Helpers::formatPrice($booking->total_price) . '</td>
                    </tr>
                </table>
                
                <div class="payment-info">
                    <h3>Payment Information</h3>
                    <p><strong>Status:</strong> ' . ucfirst($booking->status) . '</p>
                    <p>Thank you for your business!</p>
                </div>
                
                <div class="footer">
                    <p>This is an electronically generated invoice and does not require a signature.</p>
                </div>
            </div>
        </body>
        </html>';
        
        exit;
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
 * @param string|array|object $sub_services Sub services data
 * @return array Sub services data
 */
private function get_sub_services_data($sub_services) {
    $sub_services_array = [];
    
    // Handle different input types
    if (is_string($sub_services)) {
        // If it's a JSON string, decode it
        $sub_services = json_decode($sub_services, true);
    } elseif (is_object($sub_services)) {
        // If it's an object, convert to array
        $sub_services = (array)$sub_services;
    }
    
    // Ensure we have an array
    if (!is_array($sub_services)) {
        return [];
    }
    
    foreach ($sub_services as $id => $value) {
        $sub_service = get_post($id);
        if (!$sub_service) {
            continue;
        }
        
        $sub_services_array[] = [
            'id' => $id,
            'name' => $sub_service->post_title,
            'value' => $value,
            'price' => get_post_meta($id, '_vandel_sub_service_price', true),
            'type' => get_post_meta($id, '_vandel_sub_service_type', true)
        ];
    }
    
    return $sub_services_array;
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
                                    <div class="vandel-input-group">
                                        <span
                                            class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                        <input type="number" name="total_price" id="total_price"
                                            value="<?php echo esc_attr($booking->total_price); ?>" step="0.01" min="0"
                                            class="vandel-price-field">
                                    </div>
                                </div>
                            </div>

                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="customer_name"><?php _e('Customer Name', 'vandel-booking'); ?></label>
                                    <input type="text" name="customer_name" id="customer_name"
                                        value="<?php echo esc_attr($booking->customer_name); ?>"
                                        class="vandel-text-field">
                                </div>
                                <div class="vandel-form-group">
                                    <label for="customer_email"><?php _e('Customer Email', 'vandel-booking'); ?></label>
                                    <input type="email" name="customer_email" id="customer_email"
                                        value="<?php echo esc_attr($booking->customer_email); ?>"
                                        class="vandel-text-field">
                                </div>
                            </div>

                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                                    <input type="tel" name="phone" id="phone"
                                        value="<?php echo esc_attr($booking->phone); ?>" class="vandel-text-field">
                                </div>
                                <div class="vandel-form-group">
                                    <label
                                        for="access_info"><?php _e('Access Information', 'vandel-booking'); ?></label>
                                    <textarea name="access_info" id="access_info"
                                        class="vandel-textarea"><?php echo esc_textarea($booking->access_info); ?></textarea>
                                </div>
                            </div>

                            <div class="vandel-form-actions">
                                <button type="button" class="button vandel-edit-toggle" data-target="booking-info-view">
                                    <?php _e('Cancel', 'vandel-booking'); ?>
                                </button>
                                <button type="submit" class="button button-primary">
                                    <?php _e('Update Booking', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sub-Services Column -->
            <div class="vandel-grid-col vandel-secondary-col">
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h3><?php _e('Sub-Services', 'vandel-booking'); ?></h3>
                    </div>
                    <div class="vandel-card-body">
                        <?php if (empty($sub_services)): ?>
                        <div class="vandel-notice vandel-notice-info">
                            <p><?php _e('No sub-services selected for this booking.', 'vandel-booking'); ?></p>
                        </div>
                        <?php else: ?>
                        <table class="vandel-sub-services-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Sub-Service', 'vandel-booking'); ?></th>
                                    <th><?php _e('Type', 'vandel-booking'); ?></th>
                                    <th><?php _e('Value', 'vandel-booking'); ?></th>
                                    <th><?php _e('Price', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sub_services as $sub_service): ?>
                                <tr>
                                    <td><?php echo esc_html($sub_service['name']); ?></td>
                                    <td><?php echo esc_html(ucfirst($sub_service['type'])); ?></td>
                                    <td><?php 
                                                        // Display value based on type
                                                        if ($sub_service['type'] === 'number') {
                                                            echo intval($sub_service['value']);
                                                        } elseif ($sub_service['type'] === 'checkbox') {
                                                            echo $sub_service['value'] === 'yes' ? 'Yes' : 'No';
                                                        } else {
                                                            echo esc_html($sub_service['value']);
                                                        }
                                                    ?></td>
                                    <td><?php echo Helpers::formatPrice($sub_service['price']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3"><?php _e('Sub-Services Total', 'vandel-booking'); ?></th>
                                    <th><?php echo Helpers::formatPrice($sub_services_total); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes and Client History Column -->
        <div class="vandel-booking-notes-section">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Booking Notes', 'vandel-booking'); ?></h3>
                    <button type="button" class="button button-small" id="vandel-add-note-toggle">
                        <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add Note', 'vandel-booking'); ?>
                    </button>
                </div>
                <div class="vandel-card-body">
                    <!-- Add Note Form (initially hidden) -->
                    <div id="vandel-add-note-form" style="display: none;">
                        <form method="post" action="">
                            <?php wp_nonce_field('add_booking_note', 'booking_note_nonce'); ?>
                            <textarea name="note_content" rows="3"
                                placeholder="<?php _e('Enter your note...', 'vandel-booking'); ?>" required></textarea>
                            <div class="vandel-form-actions">
                                <button type="submit" name="add_booking_note" class="button button-primary">
                                    <?php _e('Save Note', 'vandel-booking'); ?>
                                </button>
                                <button type="button" id="vandel-cancel-note" class="button">
                                    <?php _e('Cancel', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Notes List -->
                    <?php if (empty($notes)): ?>
                    <div class="vandel-notice vandel-notice-info">
                        <p><?php _e('No notes for this booking yet.', 'vandel-booking'); ?></p>
                    </div>
                    <?php else: ?>
                    <div class="vandel-notes-list">
                        <?php foreach ($notes as $note): ?>
                        <div class="vandel-note">
                            <div class="vandel-note-header">
                                <span class="vandel-note-author">
                                    <?php echo esc_html($note->user_name ?: __('System', 'vandel-booking')); ?>
                                </span>
                                <span class="vandel-note-date">
                                    <?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($note->created_at)); ?>
                                </span>
                            </div>
                            <div class="vandel-note-content">
                                <?php echo nl2br(esc_html($note->note_content)); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Client Booking History -->
            <?php if ($client): ?>
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Client Booking History', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <?php if (empty($client_bookings)): ?>
                    <div class="vandel-notice vandel-notice-info">
                        <p><?php _e('No previous bookings for this client.', 'vandel-booking'); ?></p>
                    </div>
                    <?php else: ?>
                    <table class="vandel-client-bookings-table">
                        <thead>
                            <tr>
                                <th><?php _e('ID', 'vandel-booking'); ?></th>
                                <th><?php _e('Service', 'vandel-booking'); ?></th>
                                <th><?php _e('Date', 'vandel-booking'); ?></th>
                                <th><?php _e('Status', 'vandel-booking'); ?></th>
                                <th><?php _e('Total', 'vandel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($client_bookings as $client_booking): ?>
                            <tr>
                                <td>
                                    <a
                                        href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $client_booking->id); ?>">#<?php echo $client_booking->id; ?></a>
                                </td>
                                <td><?php echo esc_html($client_booking->service_name); ?></td>
                                <td><?php echo date_i18n(get_option('date_format'), strtotime($client_booking->booking_date)); ?>
                                </td>
                                <td>
                                    <span class="vandel-status-badge 
                                                            <?php 
                                                            $status_classes = [
                                                                'pending' => 'vandel-status-badge-warning',
                                                                'confirmed' => 'vandel-status-badge-success',
                                                                'completed' => 'vandel-status-badge-info',
                                                                'canceled' => 'vandel-status-badge-danger'
                                                            ];
                                                            echo isset($status_classes[$client_booking->status]) ? $status_classes[$client_booking->status] : '';
                                                            ?>">
                                        <?php echo ucfirst($client_booking->status); ?>
                                    </span>
                                </td>
                                <td><?php echo Helpers::formatPrice($client_booking->total_price); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit toggle functionality
    const editToggleButtons = document.querySelectorAll('.vandel-edit-toggle');
    editToggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const viewMode = document.getElementById(targetId);
            const editMode = document.getElementById(this.closest('.vandel-edit-mode').id);

            if (viewMode && editMode) {
                viewMode.style.display = 'block';
                editMode.style.display = 'none';
            }
        });
    });

    // Add Note Toggle
    const addNoteToggle = document.getElementById('vandel-add-note-toggle');
    const addNoteForm = document.getElementById('vandel-add-note-form');
    const cancelNoteButton = document.getElementById('vandel-cancel-note');

    if (addNoteToggle && addNoteForm && cancelNoteButton) {
        addNoteToggle.addEventListener('click', function() {
            addNoteForm.style.display = 'block';
            this.style.display = 'none';
        });

        cancelNoteButton.addEventListener('click', function() {
            addNoteForm.style.display = 'none';
            addNoteToggle.style.display = 'block';
        });
    }
});
</script>

<style>
.vandel-booking-details-container {
    max-width: 1200px;
    margin: 0 auto;
}

.vandel-booking-notes-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 20px;
}

.vandel-booking-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #fff;
    border-radius: 10px;
    margin-bottom: 25px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
}

.vandel-notes-list {
    max-height: 400px;
    overflow-y: auto;
}

.vandel-note {
    margin-bottom: 15px;
    padding: 10px;
    background: #f4f4f4;
    border-radius: 5px;
}

.vandel-note-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    color: #666;
    font-size: 12px;
}

.vandel-sub-services-table,
.vandel-client-bookings-table {
    width: 100%;
    border-collapse: collapse;
}

.vandel-sub-services-table th,
.vandel-sub-services-table td,
.vandel-client-bookings-table th,
.vandel-client-bookings-table td {
    border: 1px solid #e0e0e0;
    padding: 8px;
    text-align: left;
}

.vandel-sub-services-table thead,
.vandel-client-bookings-table thead {
    background: #f9f9f9;
}

.vandel-form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.vandel-form-group {
    flex: 1;
}

.vandel-input-group {
    display: flex;
    align-items: center;
}

.vandel-input-prefix {
    margin-right: 10px;
}

.vandel-card {
    background: #fff;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

.vandel-card-header {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.vandel-card-body {
    padding: 15px;
}

.vandel-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.vandel-info-full {
    grid-column: 1 / -1;
}

.vandel-info-label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #555;
}

.vandel-info-value {
    display: block;
}

.vandel-price-value {
    font-weight: bold;
    color: #0073aa;
}

.vandel-status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    color: #fff;
}

.vandel-status-badge-success {
    background-color: #46b450;
}

.vandel-status-badge-warning {
    background-color: #ffba00;
}

.vandel-status-badge-info {
    background-color: #00a0d2;
}

.vandel-status-badge-danger {
    background-color: #dc3232;
}

/* Add this for responsive design */
@media screen and (max-width: 1024px) {
    .vandel-booking-details-grid {
        grid-template-columns: 1fr;
    }

    .vandel-booking-notes-section {
        grid-template-columns: 1fr;
    }
}
</style>
<?php
    }
}