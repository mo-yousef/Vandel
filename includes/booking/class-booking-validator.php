<?php
namespace VandelBooking\Booking;

/**
 * Booking Validator
 */
class BookingValidator {
    /**
     * Validate booking data
     * 
     * @param array $data Booking data
     * @return true|\WP_Error True if valid, WP_Error otherwise
     */
    public function validate($data) {
        // Check required fields
        $required_fields = ['service', 'name', 'email', 'date', 'total'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return new \WP_Error(
                    'missing_field',
                    sprintf(__('Missing required field: %s', 'vandel-booking'), $field)
                );
            }
        }
        
        // Validate email
        if (!is_email($data['email'])) {
            return new \WP_Error(
                'invalid_email',
                __('Please enter a valid email address.', 'vandel-booking')
            );
        }
        
        // Validate service
        $service = get_post($data['service']);
        if (!$service || $service->post_type !== 'vandel_service') {
            return new \WP_Error(
                'invalid_service',
                __('Please select a valid service.', 'vandel-booking')
            );
        }
        
        // Validate date
        $date = strtotime($data['date']);
        if (!$date) {
            return new \WP_Error(
                'invalid_date',
                __('Please select a valid date.', 'vandel-booking')
            );
        }
        
        // Ensure date is in the future
        if ($date < strtotime('today')) {
            return new \WP_Error(
                'past_date',
                __('Please select a future date.', 'vandel-booking')
            );
        }
        
        // Validate total (must be numeric and greater than 0)
        if (!is_numeric($data['total']) || floatval($data['total']) <= 0) {
            return new \WP_Error(
                'invalid_total',
                __('Invalid total amount.', 'vandel-booking')
            );
        }
        
        return true;
    }
}