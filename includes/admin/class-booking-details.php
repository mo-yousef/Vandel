<?php
namespace VandelBooking\Admin;

use VandelBooking\Booking\BookingManager;
use VandelBooking\Booking\NoteModel;
use VandelBooking\Helpers;

/**
 * Enhanced Booking Details Management Class
 * Provides a comprehensive view of booking details with client history and statistics
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
    
    // Handle status changes and invoice download
    if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_key($_GET['action']);
        $nonce = $_GET['_wpnonce'];
        $expected_nonce_action = $action . '_booking_' . $booking_id;
        
        // Verify nonce
        if (!wp_verify_nonce($nonce, $expected_nonce_action)) {
            wp_die(__('Security check failed. Please try again.', 'vandel-booking'));
            return;
        }
        
        // Special handling for invoice download
        if ($action === 'download_invoice') {
            $this->downloadInvoice($booking_id);
            exit; // Exit after sending the invoice
        }
        
        // Other actions will be handled by AJAX now
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
        
        $result = $this->updateBookingDetails($booking_id, $_POST);
        if ($result) {
            wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=booking_updated'));
        } else {
            wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking_id . '&message=update_failed'));
        }
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
    // Try to use BookingManager
    if ($this->booking_manager && method_exists($this->booking_manager, 'updateBookingStatus')) {
        $result = $this->booking_manager->updateBookingStatus($booking_id, $status);
        if ($result) {
            return true;
        }
    }
    
    // Fallback if BookingManager not available or failed
    global $wpdb;
    $bookings_table = $wpdb->prefix . 'vandel_bookings';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
        return false;
    }
    
    // Get current status for comparison
    $old_status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM $bookings_table WHERE id = %d",
        $booking_id
    ));
    
    if ($old_status === null) {
        return false;
    }
    
    // Update the status
    $result = $wpdb->update(
        $bookings_table,
        ['status' => $status],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );
    
    if ($result === false) {
        return false;
    }
    
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
     * Add booking note
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
    
    // Get service info
    $service = get_post($booking->service);
    $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
    
    // Business info
    $business_name = get_option('vandel_business_name', get_bloginfo('name'));
    $business_address = get_option('vandel_business_address', '');
    
    // Format dates
    $booking_date = new \DateTime($booking->booking_date);
    $invoice_date = new \DateTime();
    
    // Generate invoice number
    $invoice_number = 'INV-' . $booking->id . '-' . date('Ymd');
    
    // Start output buffering to capture HTML content
    ob_start();
    
    // Generate invoice HTML
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Invoice #' . $invoice_number . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { width: 800px; margin: 0 auto; padding: 20px; }
            .header { margin-bottom: 30px; }
            .header:after { content: ""; display: table; clear: both; }
            .business-info { float: left; width: 50%; }
            .invoice-info { float: right; width: 50%; text-align: right; }
            .client-info { margin-bottom: 30px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f5f5f5; font-weight: bold; }
            .text-right { text-align: right; }
            .total-row { font-weight: bold; }
            .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <div class="business-info">
                    <h1>' . $business_name . '</h1>
                    <p>' . nl2br($business_address) . '</p>
                </div>
                <div class="invoice-info">
                    <h2>INVOICE</h2>
                    <p><strong>Invoice #:</strong> ' . $invoice_number . '</p>
                    <p><strong>Date:</strong> ' . $invoice_date->format(get_option('date_format')) . '</p>
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
                    <th width="50%">Description</th>
                    <th width="15%" class="text-right">Price</th>
                    <th width="15%" class="text-right">Quantity</th>
                    <th width="15%" class="text-right">Amount</th>
                </tr>
                
                <tr>
                    <td>1</td>
                    <td>' . $service_name . '<br>Date: ' . $booking_date->format(get_option('date_format') . ' ' . get_option('time_format')) . '</td>
                    <td class="text-right">' . \VandelBooking\Helpers::formatPrice($booking->total_price) . '</td>
                    <td class="text-right">1</td>
                    <td class="text-right">' . \VandelBooking\Helpers::formatPrice($booking->total_price) . '</td>
                </tr>
                
                <tr class="total-row">
                    <td colspan="4" class="text-right">Total</td>
                    <td class="text-right">' . \VandelBooking\Helpers::formatPrice($booking->total_price) . '</td>
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
    
    $html_content = ob_get_clean();
    
    // Set headers for download
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="invoice_' . $invoice_number . '.html"');
    header('Cache-Control: max-age=0');
    
    // Output the HTML content
    echo $html_content;
    exit;
}
    
    /**
     * Generate HTML invoice
     * 
     * @param object $booking Booking object
     */
    private function generateHtmlInvoice($booking) {
        // Get service info
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Unknown Service', 'vandel-booking');
        
        // Business info
        $business_name = get_option('vandel_business_name', get_bloginfo('name'));
        $business_address = get_option('vandel_business_address', '');
        
        // Format dates
        $booking_date = new \DateTime($booking->booking_date);
        $invoice_date = new \DateTime();
        
        // Generate invoice number
        $invoice_number = 'INV-' . $booking->id . '-' . date('Ymd');
        
        // Start HTML document
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="invoice_' . $invoice_number . '.html"');
        
        echo '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Invoice #' . $invoice_number . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { width: 800px; margin: 0 auto; padding: 20px; }
                .header { margin-bottom: 30px; }
                .header:after { content: ""; display: table; clear: both; }
                .business-info { float: left; width: 50%; }
                .invoice-info { float: right; width: 50%; text-align: right; }
                .client-info { margin-bottom: 30px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background-color: #f5f5f5; font-weight: bold; }
                .text-right { text-align: right; }
                .total-row { font-weight: bold; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="business-info">
                        <h1>' . $business_name . '</h1>
                        <p>' . nl2br($business_address) . '</p>
                    </div>
                    <div class="invoice-info">
                        <h2>INVOICE</h2>
                        <p><strong>Invoice #:</strong> ' . $invoice_number . '</p>
                        <p><strong>Date:</strong> ' . $invoice_date->format(get_option('date_format')) . '</p>
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
                        <th width="50%">Description</th>
                        <th width="15%" class="text-right">Price</th>
                        <th width="15%" class="text-right">Quantity</th>
                        <th width="15%" class="text-right">Amount</th>
                    </tr>
                    
                    <tr>
                        <td>1</td>
                        <td>' . $service_name . '<br>Date: ' . $booking_date->format(get_option('date_format') . ' ' . get_option('time_format')) . '</td>
                        <td class="text-right">' . Helpers::formatPrice($booking->total_price) . '</td>
                        <td class="text-right">1</td>
                        <td class="text-right">' . Helpers::formatPrice($booking->total_price) . '</td>
                    </tr>
                    
                    <tr class="total-row">
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
     * Determine if a client is a repeat customer
     * 
     * @param int $client_id Client ID
     * @return bool Whether this is a repeat customer
     */
    private function is_repeat_customer($client_id) {
        if ($client_id <= 0) {
            return false;
        }
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return false;
        }
        
        // Count completed bookings for this client
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $bookings_table 
             WHERE client_id = %d AND status = 'completed'",
            $client_id
        ));
        
        return intval($count) > 1;
    }
    
    /**
     * Get client lifetime value
     * 
     * @param int $client_id Client ID
     * @return float Client lifetime value
     */
    private function get_client_lifetime_value($client_id) {
        if ($client_id <= 0) {
            return 0;
        }
        
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vandel_bookings';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") !== $bookings_table) {
            return 0;
        }
        
        // Sum total_price of completed bookings
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(total_price) FROM $bookings_table 
             WHERE client_id = %d AND status = 'completed'",
            $client_id
        ));
        
        return floatval($total);
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
            
        case 'update_failed':
            $message = __('Failed to update booking. Please try again.', 'vandel-booking');
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
        $is_repeat_customer = false;
        $client_lifetime_value = 0;
        
        if ($client) {
            $client_bookings = $this->get_client_bookings($client->id, $booking_id);
            $is_repeat_customer = $this->is_repeat_customer($client->id);
            $client_lifetime_value = $this->get_client_lifetime_value($client->id);
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
        $this->render_booking_details_ui($booking, $service_name, $service_price, $service, $sub_services, $client, $client_bookings, $notes, $services, $is_repeat_customer, $client_lifetime_value);
    }
    
    /**
     * Render modern booking details UI
     */
    private function render_booking_details_ui($booking, $service_name, $service_price, $service, $sub_services, $client, $client_bookings, $notes, $services, $is_repeat_customer, $client_lifetime_value) {
        $booking_date = new \DateTime($booking->booking_date);
        $created_date = new \DateTime($booking->created_at);
        
        $status_classes = [
            'pending' => 'vandel-status-badge-warning',
            'confirmed' => 'vandel-status-badge-success',
            'completed' => 'vandel-status-badge-info',
            'canceled' => 'vandel-status-badge-danger'
        ];
        
        $status_class = isset($status_classes[$booking->status]) ? $status_classes[$booking->status] : '';
        ?>
        <div class="vandel-booking-details-container">
            <!-- Header Bar -->
            <div class="vandel-booking-header-bar">
                <div class="vandel-booking-id">
                    <h2><?php printf(__('Booking #%s', 'vandel-booking'), $booking->id); ?></h2>
                </div>
                <div class="vandel-booking-status">
                    <span class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span>
                </div>
                <div class="vandel-booking-actions">
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings'); ?>" class="button">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e('Back to Bookings', 'vandel-booking'); ?>
                    </a>
                    <?php if ($booking->status !== 'confirmed'): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=approve'), 'approve_booking_' . $booking->id); ?>" class="button button-primary">
                        <span class="dashicons dashicons-yes"></span> <?php _e('Confirm Booking', 'vandel-booking'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($booking->status !== 'completed'): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=complete'), 'complete_booking_' . $booking->id); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-saved"></span> <?php _e('Mark as Completed', 'vandel-booking'); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($booking->status !== 'canceled'): ?>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=cancel'), 'cancel_booking_' . $booking->id); ?>" class="button button-secondary">
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
                            <button type="button" class="vandel-edit-toggle button button-small" data-target="booking-info-edit">
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
                                            <span class="vandel-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($booking->status); ?></span>
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
                                        <span class="vandel-info-label"><?php _e('Access Information', 'vandel-booking'); ?></span>
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
                                                <option value="<?php echo $service_item->ID; ?>" <?php selected($booking->service, $service_item->ID); ?>>
                                                    <?php echo esc_html($service_item->post_title); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="vandel-form-group">
                                            <label for="booking_date"><?php _e('Date & Time', 'vandel-booking'); ?></label>
                                            <input type="datetime-local" id="booking_date" name="booking_date" value="<?php echo $booking_date->format('Y-m-d\TH:i'); ?>" class="vandel-datetime-field">
                                        </div>
                                    </div>

                                    <div class="vandel-form-row">
                                        <div class="vandel-form-group">
                                            <label for="booking_status"><?php _e('Status', 'vandel-booking'); ?></label>
                                            <select name="booking_status" id="booking_status" class="vandel-select">
                                                <option value="pending" <?php selected($booking->status, 'pending'); ?>><?php _e('Pending', 'vandel-booking'); ?></option>
                                                <option value="confirmed" <?php selected($booking->status, 'confirmed'); ?>><?php _e('Confirmed', 'vandel-booking'); ?></option>
                                                <option value="completed" <?php selected($booking->status, 'completed'); ?>><?php _e('Completed', 'vandel-booking'); ?></option>
                                                <option value="canceled" <?php selected($booking->status, 'canceled'); ?>><?php _e('Canceled', 'vandel-booking'); ?></option>
                                            </select>
                                        </div>

                                        <div class="vandel-form-group">
                                            <label for="total_price"><?php _e('Total Price', 'vandel-booking'); ?></label>
                                            <div class="vandel-input-group">
                                                <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                                <input type="number" name="total_price" id="total_price" value="<?php echo esc_attr($booking->total_price); ?>" step="0.01" min="0" class="vandel-price-field">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="vandel-form-row">
                                        <div class="vandel-form-group">
                                            <label for="customer_name"><?php _e('Customer Name', 'vandel-booking'); ?></label>
                                            <input type="text" name="customer_name" id="customer_name" value="<?php echo esc_attr($booking->customer_name); ?>" class="vandel-text-field">
                                        </div>
                                        <div class="vandel-form-group">
                                            <label for="customer_email"><?php _e('Customer Email', 'vandel-booking'); ?></label>
                                            <input type="email" name="customer_email" id="customer_email" value="<?php echo esc_attr($booking->customer_email); ?>" class="vandel-text-field">
                                        </div>
                                    </div>

                                    <div class="vandel-form-row">
                                        <div class="vandel-form-group">
                                            <label for="phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                                            <input type="tel" name="phone" id="phone" value="<?php echo esc_attr($booking->phone); ?>" class="vandel-text-field">
                                        </div>
                                        <div class="vandel-form-group">
                                            <label for="access_info"><?php _e('Access Information', 'vandel-booking'); ?></label>
                                            <textarea name="access_info" id="access_info" class="vandel-textarea"><?php echo esc_textarea($booking->access_info); ?></textarea>
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
                                    <?php 
                                    $sub_services_total = 0;
                                    foreach ($sub_services as $sub_service): 
                                        // Calculate price based on type and value
                                        $item_price = floatval($sub_service['price']);
                                        if ($sub_service['type'] === 'number') {
                                            $item_price *= intval($sub_service['value']);
                                        }
                                        $sub_services_total += $item_price;
                                    ?>
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
                                        <td><?php echo Helpers::formatPrice($item_price); ?></td>
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
                    
                    <!-- Price Breakdown -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Price Breakdown', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <?php
                            // Calculate price components
                            $base_price = floatval($service_price);
                            $sub_services_total = 0;
                            
                            foreach ($sub_services as $sub_service) {
                                $item_price = floatval($sub_service['price']);
                                if ($sub_service['type'] === 'number') {
                                    $item_price *= intval($sub_service['value']);
                                }
                                $sub_services_total += $item_price;
                            }
                            
                            $adjustments = floatval($booking->total_price) - ($base_price + $sub_services_total);
                            ?>
                            
                            <table class="vandel-price-breakdown-table">
                                <tbody>
                                    <tr>
                                        <td><?php echo esc_html($service_name); ?> (<?php _e('Base Price', 'vandel-booking'); ?>)</td>
                                        <td class="vandel-price-cell"><?php echo Helpers::formatPrice($base_price); ?></td>
                                    </tr>
                                    
                                    <?php if ($sub_services_total > 0): ?>
                                    <tr>
                                        <td><?php _e('Sub-Services', 'vandel-booking'); ?></td>
                                        <td class="vandel-price-cell"><?php echo Helpers::formatPrice($sub_services_total); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    
                                    <?php if ($adjustments != 0): ?>
                                    <tr>
                                        <td><?php _e('Adjustments', 'vandel-booking'); ?></td>
                                        <td class="vandel-price-cell"><?php echo Helpers::formatPrice($adjustments); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th><?php _e('Total', 'vandel-booking'); ?></th>
                                        <th class="vandel-price-cell"><?php echo Helpers::formatPrice($booking->total_price); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            <div class="vandel-price-actions">
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=booking-details&booking_id=' . $booking->id . '&action=download_invoice'), 'download_invoice_booking_' . $booking->id); ?>" class="button">
                                    <span class="dashicons dashicons-media-document"></span> <?php _e('Download Invoice', 'vandel-booking'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Client Information and History Column -->
                <div class="vandel-grid-col vandel-secondary-col">
                    <!-- Client Information -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Client Information', 'vandel-booking'); ?></h3>
                            <?php if ($client): ?>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id); ?>" class="vandel-view-all">
                                <?php _e('View Client Profile', 'vandel-booking'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-client-info-display">
                                <div class="vandel-client-header">
                                    <div class="vandel-client-identity">
                                        <h4><?php echo esc_html($booking->customer_name); ?></h4>
                                        <div class="vandel-client-contact">
                                            <p><i class="dashicons dashicons-email"></i> <?php echo esc_html($booking->customer_email); ?></p>
                                            <?php if (!empty($booking->phone)): ?>
                                            <p><i class="dashicons dashicons-phone"></i> <?php echo esc_html($booking->phone); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($client && $is_repeat_customer): ?>
                                    <div class="vandel-client-badge">
                                        <span class="vandel-repeat-customer-badge">
                                            <i class="dashicons dashicons-awards"></i> <?php _e('Repeat Customer', 'vandel-booking'); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($client): ?>
                                <div class="vandel-client-stats">
                                    <div class="vandel-stat-row">
                                        <div class="vandel-stat-item">
                                            <span class="vandel-stat-label"><?php _e('Total Spent', 'vandel-booking'); ?></span>
                                            <span class="vandel-stat-value"><?php echo Helpers::formatPrice($client_lifetime_value); ?></span>
                                        </div>
                                        <div class="vandel-stat-item">
                                            <span class="vandel-stat-label"><?php _e('Bookings', 'vandel-booking'); ?></span>
                                            <span class="vandel-stat-value"><?php echo $client->bookings_count ?? count($client_bookings) + 1; ?></span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($client->created_at)): ?>
                                    <div class="vandel-stat-row">
                                        <div class="vandel-stat-item">
                                            <span class="vandel-stat-label"><?php _e('Client Since', 'vandel-booking'); ?></span>
                                            <span class="vandel-stat-value"><?php 
                                                $created_date = new \DateTime($client->created_at);
                                                echo $created_date->format(get_option('date_format'));
                                            ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($client && !empty($client_bookings)): ?>
                    <!-- Previous Bookings -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Previous Bookings', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <div class="vandel-previous-bookings">
                                <?php foreach ($client_bookings as $prev_booking): ?>
                                <div class="vandel-booking-history-item">
                                    <div class="vandel-booking-history-details">
                                        <div class="vandel-booking-history-service">
                                            <?php echo esc_html($prev_booking->service_name); ?>
                                        </div>
                                        <div class="vandel-booking-history-date">
                                            <?php 
                                                $prev_date = new \DateTime($prev_booking->booking_date);
                                                echo $prev_date->format(get_option('date_format'));
                                            ?>
                                        </div>
                                        <div class="vandel-booking-history-price">
                                            <?php echo Helpers::formatPrice($prev_booking->total_price); ?>
                                        </div>
                                    </div>
                                    <div class="vandel-booking-history-status">
                                        <span class="vandel-status-badge vandel-status-badge-<?php echo esc_attr($prev_booking->status); ?>">
                                            <?php echo ucfirst($prev_booking->status); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Booking Notes -->
                    <div class="vandel-card">
                        <div class="vandel-card-header">
                            <h3><?php _e('Booking Notes', 'vandel-booking'); ?></h3>
                        </div>
                        <div class="vandel-card-body">
                            <!-- Add Note Form -->
                            <div class="vandel-add-note-form">
                                <form method="post" action="">
                                    <?php wp_nonce_field('add_booking_note', 'booking_note_nonce'); ?>
                                    <div class="vandel-form-row">
                                        <textarea name="note_content" rows="3" placeholder="<?php _e('Add a note about this booking...', 'vandel-booking'); ?>" required></textarea>
                                    </div>
                                    <div class="vandel-form-actions">
                                        <button type="submit" name="add_booking_note" class="button button-primary">
                                            <?php _e('Add Note', 'vandel-booking'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Notes List -->
                            <?php if (empty($notes)): ?>
                            <div class="vandel-empty-state-small">
                                <p><?php _e('No notes for this booking yet.', 'vandel-booking'); ?></p>
                            </div>
                            <?php else: ?>
                            <div class="vandel-notes-list">
                                <?php foreach ($notes as $note): ?>
                                <div class="vandel-note">
                                    <div class="vandel-note-header">
                                        <span class="vandel-note-author">
                                            <?php 
                                                echo $note->created_by 
                                                    ? esc_html($note->user_name ?: __('Admin', 'vandel-booking')) 
                                                    : __('System', 'vandel-booking'); 
                                            ?>
                                        </span>
                                        <span class="vandel-note-date">
                                            <?php 
                                                $note_date = new \DateTime($note->created_at);
                                                echo $note_date->format(get_option('date_format') . ' ' . get_option('time_format')); 
                                            ?>
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
                </div>
            </div>
        </div>

        <style>
        .vandel-booking-details-container {
            max-width: 1200px;
            margin: 0 auto;
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
        
        .vandel-booking-details-grid {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 25px;
        }
        
        .vandel-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        
        .vandel-form-row {
            margin-bottom: 15px;
        }
        
        .vandel-form-actions {
            margin-top: 15px;
            text-align: right;
        }
        
        .vandel-sub-services-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vandel-sub-services-table th,
        .vandel-sub-services-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .vandel-sub-services-table th:last-child,
        .vandel-sub-services-table td:last-child {
            text-align: right;
        }
        
        .vandel-sub-services-table tfoot {
            font-weight: bold;
        }
        
        .vandel-price-breakdown-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vandel-price-breakdown-table td,
        .vandel-price-breakdown-table th {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .vandel-price-cell {
            text-align: right;
        }
        
        .vandel-price-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .vandel-client-info-display {
            padding: 0;
        }
        
        .vandel-client-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .vandel-client-identity h4 {
            margin: 0 0 10px 0;
            font-size: 18px;
        }
        
        .vandel-client-contact p {
            margin: 5px 0;
            display: flex;
            align-items: center;
        }
        
        .vandel-client-contact .dashicons {
            margin-right: 5px;
            color: #555;
        }
        
        .vandel-repeat-customer-badge {
            display: inline-block;
            background-color: #4caf50;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .vandel-repeat-customer-badge .dashicons {
            margin-right: 3px;
        }
        
        .vandel-client-stats {
            margin-top: 15px;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 4px;
        }
        
        .vandel-stat-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .vandel-stat-item {
            flex: 1;
        }
        
        .vandel-stat-label {
            display: block;
            font-size: 12px;
            color: #666;
        }
        
        .vandel-stat-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        
        .vandel-previous-bookings {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .vandel-booking-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .vandel-booking-history-item:last-child {
            border-bottom: none;
        }
        
        .vandel-booking-history-service {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .vandel-booking-history-date,
        .vandel-booking-history-price {
            font-size: 13px;
            color: #666;
        }
        
        .vandel-add-note-form {
            margin-bottom: 20px;
        }
        
        .vandel-add-note-form textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .vandel-notes-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .vandel-note {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 10px;
        }
        
        .vandel-note-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #666;
            font-size: 12px;
        }
        
        .vandel-note-author {
            font-weight: bold;
        }
        
        .vandel-note-content {
            color: #333;
            line-height: 1.5;
        }
        
        .vandel-empty-state-small {
            padding: 20px;
            text-align: center;
            color: #999;
        }
        
        /* Responsive Styles */
        @media screen and (max-width: 1024px) {
            .vandel-booking-details-grid {
                grid-template-columns: 1fr;
            }
            
            .vandel-secondary-col {
                order: -1; /* Move client info above booking details on mobile */
            }
        }
        </style>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle between view and edit modes
            const editToggle = document.querySelector('.vandel-edit-toggle');
            if (editToggle) {
                const viewMode = document.getElementById('booking-info-view');
                const editMode = document.getElementById('booking-info-edit');
                
                editToggle.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    
                    if (target === 'booking-info-edit') {
                        // Switch to edit mode
                        viewMode.style.display = 'none';
                        editMode.style.display = 'block';
                        this.innerHTML = '<span class="dashicons dashicons-no"></span> <?php _e("Cancel", "vandel-booking"); ?>';
                        this.setAttribute('data-target', 'booking-info-view');
                    } else {
                        // Switch back to view mode
                        viewMode.style.display = 'block';
                        editMode.style.display = 'none';
                        this.innerHTML = '<span class="dashicons dashicons-edit"></span> <?php _e("Edit", "vandel-booking"); ?>';
                        this.setAttribute('data-target', 'booking-info-edit');
                    }
                });
            }
            
            // Handle all other edit toggle buttons
            document.querySelectorAll('.vandel-edit-toggle').forEach(function(button) {
                button.addEventListener('click', function() {
                    const target = this.getAttribute('data-target');
                    if (target) {
                        const targetElement = document.getElementById(target);
                        if (targetElement) {
                            if (targetElement.style.display === 'none' || !targetElement.style.display) {
                                targetElement.style.display = 'block';
                            } else {
                                targetElement.style.display = 'none';
                            }
                        }
                    }
                });
            });
        });
        </script>

<script>
jQuery(document).ready(function($) {
// Handle status button clicks
$('.vandel-booking-actions a').on('click', function(e) {
    // Don't intercept the "Back to Bookings" button
    if ($(this).text().trim().indexOf('Back to Bookings') >= 0) {
        return true;
    }

    e.preventDefault();
    var actionUrl = $(this).attr('href');
    
    // Special handling for invoice downloads
    if (actionUrl.indexOf('action=download_invoice') >= 0) {
        // Use direct window location for downloads
        window.location.href = actionUrl;
        return false;
    }
    
    var actionType = '';
    if (actionUrl.indexOf('action=approve') >= 0) {
        actionType = 'confirm';
    } else if (actionUrl.indexOf('action=complete') >= 0) {
        actionType = 'complete';
    } else if (actionUrl.indexOf('action=cancel') >= 0) {
        actionType = 'cancel';
    }
    
    if (actionType) {
        updateBookingStatus(actionType, <?php echo $booking->id; ?>);
    }
    
    return false;
});
    
    // Function to update booking status via AJAX
    function updateBookingStatus(action, bookingId) {
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'vandel_update_booking_status',
                booking_id: bookingId,
                status: action,
                security: '<?php echo wp_create_nonce('vandel_booking_status_nonce'); ?>'
            },
            beforeSend: function() {
                // Show loading state
                $('body').append('<div class="vandel-loading-overlay"><div class="vandel-spinner"></div></div>');
            },
            success: function(response) {
                if (response.success) {
                    // Show success message and reload
                    alert('Booking status updated successfully!');
                    window.location.reload();
                } else {
                    alert(response.data.message || 'Error updating booking status');
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                alert('An error occurred while updating the booking status: ' + error);
            },
            complete: function() {
                // Remove loading overlay
                $('.vandel-loading-overlay').remove();
            }
        });
    }
    
    // Toggle edit mode for booking info
    $('.vandel-edit-toggle').on('click', function() {
        var target = $(this).data('target');
        
        if (target === 'booking-info-edit') {
            // Switch to edit mode
            $('#booking-info-view').hide();
            $('#booking-info-edit').show();
            $(this).html('<span class="dashicons dashicons-no"></span> <?php _e('Cancel', 'vandel-booking'); ?>');
            $(this).data('target', 'booking-info-view');
        } else {
            // Switch back to view mode
            $('#booking-info-view').show();
            $('#booking-info-edit').hide();
            $(this).html('<span class="dashicons dashicons-edit"></span> <?php _e('Edit', 'vandel-booking'); ?>');
            $(this).data('target', 'booking-info-edit');
        }
    });
});





</script>

<style>
.vandel-loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.vandel-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 2s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
        <?php
    }
}