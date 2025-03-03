<?php
namespace VandelBooking\Booking;

/**
 * Booking Notification
 */
class BookingNotification {
    /**
     * Send client confirmation email
     * 
     * @param int $booking_id Booking ID
     * @return bool Whether the email was sent
     */
    public function sendClientConfirmation($booking_id) {
        $booking = $this->getBooking($booking_id);
        if (!$booking) {
            return false;
        }
        
        $to = $booking->customer_email;
        $subject = $this->getEmailSubject();
        $message = $this->formatClientEmail($booking);
        $headers = $this->getEmailHeaders();
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Send admin notification email
     * 
     * @param int $booking_id Booking ID
     * @return bool Whether the email was sent
     */
    public function sendAdminNotification($booking_id) {
        $booking = $this->getBooking($booking_id);
        if (!$booking) {
            return false;
        }
        
        $recipients = $this->getAdminRecipients();
        if (empty($recipients)) {
            return false;
        }
        
        $subject = sprintf(__('New Booking Received - Booking ID #%s', 'vandel-booking'), $booking_id);
        $message = $this->formatAdminEmail($booking);
        $headers = $this->getEmailHeaders();
        
        return wp_mail($recipients, $subject, $message, $headers);
    }
    
    /**
     * Send booking status update notification
     * 
     * @param int $booking_id Booking ID
     * @param string $new_status New booking status
     * @return bool Whether the email was sent
     */
    public function sendStatusUpdateNotification($booking_id, $new_status) {
        $booking = $this->getBooking($booking_id);
        if (!$booking) {
            return false;
        }
        
        // Check if email should be sent for this status
        $triggers = get_option('vandel_email_triggers', ['confirmed', 'canceled']);
        if (!in_array($new_status, $triggers)) {
            return false;
        }
        
        $to = $booking->customer_email;
        $subject = sprintf(__('Booking Status Update - %s', 'vandel-booking'), ucfirst($new_status));
        $message = $this->formatStatusUpdateEmail($booking, $new_status);
        $headers = $this->getEmailHeaders();
        
        return wp_mail($to, $subject, $message, $headers);
    }
    
    /**
     * Get booking information
     * 
     * @param int $booking_id Booking ID
     * @return object|false Booking object or false if not found
     */
    private function getBooking($booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_bookings';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $booking_id
        ));
    }
    
    /**
     * Get email subject
     * 
     * @return string Email subject
     */
    private function getEmailSubject() {
        return get_option('vandel_email_subject', __('Booking Confirmation', 'vandel-booking'));
    }
    
    /**
     * Get email headers
     * 
     * @return array Email headers
     */
    private function getEmailHeaders() {
        $site_name = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        
        return [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$site_name} <{$admin_email}>"
        ];
    }
    
    /**
     * Get admin recipients
     * 
     * @return string Admin recipients
     */
    private function getAdminRecipients() {
        $default_email = get_option('admin_email');
        return get_option('vandel_email_recipients', $default_email);
    }
    
    /**
     * Format client email
     * 
     * @param object $booking Booking object
     * @return string Formatted email
     */
    private function formatClientEmail($booking) {
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Service', 'vandel-booking');
        $booking_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date));
        $total_price = \VandelBooking\Helpers::formatPrice($booking->total_price);
        
        $message = get_option('vandel_email_message', __('Thank you for your booking. We look forward to serving you.', 'vandel-booking'));
        
        $email_template = file_get_contents(VANDEL_PLUGIN_DIR . 'templates/emails/client-confirmation.php');
        
        // Replace placeholders with actual values
        $placeholders = [
            '{customer_name}' => $booking->customer_name,
            '{service_name}' => $service_name,
            '{booking_date}' => $booking_date,
            '{booking_id}' => $booking->id,
            '{total_price}' => $total_price,
            '{message}' => $message,
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url')
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $email_template = str_replace($placeholder, $value, $email_template);
        }
        
        return $email_template;
    }
    
    /**
     * Format admin email
     * 
     * @param object $booking Booking object
     * @return string Formatted email
     */
    private function formatAdminEmail($booking) {
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Service', 'vandel-booking');
        $booking_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date));
        $total_price = \VandelBooking\Helpers::formatPrice($booking->total_price);
        
        $email_template = file_get_contents(VANDEL_PLUGIN_DIR . 'templates/emails/admin-notification.php');
        
        // Replace placeholders with actual values
        $placeholders = [
            '{customer_name}' => $booking->customer_name,
            '{customer_email}' => $booking->customer_email,
            '{customer_phone}' => $booking->phone ?? 'N/A',
            '{service_name}' => $service_name,
            '{booking_date}' => $booking_date,
            '{booking_id}' => $booking->id,
            '{total_price}' => $total_price,
            '{status}' => ucfirst($booking->status),
            '{access_info}' => $booking->access_info ?? 'N/A',
            '{admin_url}' => admin_url("admin.php?page=vandel-dashboard&tab=booking-details&booking_id={$booking->id}"),
            '{site_name}' => get_bloginfo('name')
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $email_template = str_replace($placeholder, $value, $email_template);
        }
        
        return $email_template;
    }
    
    /**
     * Format status update email
     * 
     * @param object $booking Booking object
     * @param string $new_status New booking status
     * @return string Formatted email
     */
    private function formatStatusUpdateEmail($booking, $new_status) {
        $service = get_post($booking->service);
        $service_name = $service ? $service->post_title : __('Service', 'vandel-booking');
        $booking_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($booking->booking_date));
        
        $status_messages = [
            'confirmed' => __('Your booking has been confirmed. We look forward to serving you!', 'vandel-booking'),
            'canceled' => __('Your booking has been canceled. Please contact us if you have any questions.', 'vandel-booking'),
            'completed' => __('Your booking has been marked as completed. Thank you for your business!', 'vandel-booking'),
            'pending' => __('Your booking is pending review. We will contact you shortly to confirm.', 'vandel-booking')
        ];
        
        $status_message = $status_messages[$new_status] ?? '';
        
        $email_template = file_get_contents(VANDEL_PLUGIN_DIR . 'templates/emails/status-update.php');
        
        // Replace placeholders with actual values
        $placeholders = [
            '{customer_name}' => $booking->customer_name,
            '{service_name}' => $service_name,
            '{booking_date}' => $booking_date,
            '{booking_id}' => $booking->id,
            '{status}' => ucfirst($new_status),
            '{status_message}' => $status_message,
            '{site_name}' => get_bloginfo('name'),
            '{site_url}' => get_bloginfo('url')
        ];
        
        foreach ($placeholders as $placeholder => $value) {
            $email_template = str_replace($placeholder, $value, $email_template);
        }
        
        return $email_template;
    }
}