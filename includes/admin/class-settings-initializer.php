<?php
namespace VandelBooking\Admin;

/**
 * Settings Initializer
 */
class SettingsInitializer {
    /**
     * Initialize plugin settings
     */
    public static function initializeSettings() {
        // General Settings
        self::initGeneralSettings();
        
        // Booking Settings
        self::initBookingSettings();
        
        // Notification Settings
        self::initNotificationSettings();
        
        // Pricing Settings
        self::initPricingSettings();
        
        // Availability Settings
        self::initAvailabilitySettings();
    }
    
    /**
     * Initialize General Settings
     */
    private static function initGeneralSettings() {
        $defaults = [
            'vandel_business_name' => get_bloginfo('name'),
            'vandel_primary_color' => '#286cd6',
            'vandel_default_timezone' => wp_timezone_string(),
            'vandel_date_format' => get_option('date_format'),
            'vandel_time_format' => get_option('time_format'),
            'vandel_currency' => 'USD',
            'vandel_language' => get_locale()
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value, '', 'no');
        }
    }
    
    /**
     * Initialize Booking Settings
     */
    private static function initBookingSettings() {
        $defaults = [
            'vandel_min_advance_booking' => 1,      // 1 hour
            'vandel_max_advance_booking' => 90,     // 90 days
            'vandel_booking_cancellation_window' => 24, // 24 hours before booking
            'vandel_default_booking_status' => 'pending',
            'vandel_enable_multiple_bookings' => 'no',
            'vandel_booking_slots_interval' => 30   // 30 minutes
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value, '', 'no');
        }
    }
    
    /**
     * Initialize Notification Settings
     */
    private static function initNotificationSettings() {
        $defaults = [
            'vandel_enable_email_notifications' => 'yes',
            'vandel_email_sender_name' => get_bloginfo('name'),
            'vandel_email_sender_address' => get_option('admin_email'),
            'vandel_notification_email' => get_option('admin_email'),
            'vandel_email_logo' => '',
            'vandel_sms_notifications' => 'no',
            'vandel_sms_provider' => '',
            'vandel_sms_api_key' => ''
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value, '', 'no');
        }
    }
    
    /**
     * Initialize Pricing Settings
     */
    private static function initPricingSettings() {
        $defaults = [
            'vandel_base_price' => 0,
            'vandel_enable_tax' => 'no',
            'vandel_tax_rate' => 0,
            'vandel_tax_calculation_method' => 'inclusive',
            'vandel_enable_dynamic_pricing' => 'no',
            'vandel_pricing_rules' => '[]'
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value, '', 'no');
        }
    }
    
    /**
     * Initialize Availability Settings
     */
    private static function initAvailabilitySettings() {
        $defaults = [
            'vandel_business_hours_start' => '09:00',
            'vandel_business_hours_end' => '17:00',
            'vandel_business_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'vandel_holidays' => '[]',
            'vandel_buffer_time_before' => 15,
            'vandel_buffer_time_after' => 15
        ];
        
        foreach ($defaults as $option => $value) {
            add_option($option, $value, '', 'no');
        }
    }
    
    /**
     * Sanitize and validate settings
     * 
     * @param string $option Option name
     * @param mixed $value Option value
     * @return mixed Sanitized value
     */
    public static function sanitizeSettings($option, $value) {
        switch ($option) {
            case 'vandel_business_hours_start':
            case 'vandel_business_hours_end':
                // Validate time format
                return preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $value) ? $value : '';
            
            case 'vandel_min_advance_booking':
            case 'vandel_max_advance_booking':
                return intval($value);
            
            case 'vandel_base_price':
                return floatval($value);
            
            case 'vandel_enable_email_notifications':
            case 'vandel_enable_tax':
                return $value === 'yes' ? 'yes' : 'no';
            
            default:
                return sanitize_text_field($value);
        }
    }
}

// Initialize settings when plugin loads
add_action('init', ['\\VandelBooking\\Admin\\SettingsInitializer', 'initializeSettings']);