<?php
namespace VandelBooking\Frontend;

/**
 * Enhanced Booking Form with multi-step process
 */
class BookingForm {
    /**
     * @var bool Whether ZIP Code feature is enabled
     */
    private $zip_code_feature_enabled;
    
    /**
     * @var array Available services
     */
    private $services;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->zip_code_feature_enabled = get_option('vandel_enable_zip_code_feature', 'no') === 'yes';
        $this->services = $this->getServices();
        
        // Register AJAX handlers
        add_action('wp_ajax_vandel_validate_zip_code', [$this, 'ajaxValidateZipCode']);
        add_action('wp_ajax_nopriv_vandel_validate_zip_code', [$this, 'ajaxValidateZipCode']);
        
        add_action('wp_ajax_vandel_get_service_details', [$this, 'ajaxGetServiceDetails']);
        add_action('wp_ajax_nopriv_vandel_get_service_details', [$this, 'ajaxGetServiceDetails']);
        
        add_action('wp_ajax_vandel_submit_booking', [$this, 'ajaxSubmitBooking']);
        add_action('wp_ajax_nopriv_vandel_submit_booking', [$this, 'ajaxSubmitBooking']);
    }
    
    /**
     * Render booking form
     * 
     * @param array $atts Shortcode attributes
     * @return string Rendered form
     */
    public function render($atts = []) {
        // Extract shortcode attributes
        $atts = shortcode_atts([
            'title' => __('Book our Services', 'vandel-booking'),
            'subtitle' => __('Please fill out the form below to book your service', 'vandel-booking'),
            'service_id' => 0, // Specific service to show
            'show_zip' => 'auto', // auto, yes, no
        ], $atts);
        
        // Handle specific show_zip settings
        $show_zip = $atts['show_zip'];
        if ($show_zip === 'auto') {
            $show_zip_code = $this->zip_code_feature_enabled;
        } else {
            $show_zip_code = $show_zip === 'yes';
        }
        
        // Enqueue necessary assets
        wp_enqueue_style('vandel-booking-form');
        wp_enqueue_script('vandel-booking-form');
        
        // Localize script with data
        wp_localize_script('vandel-booking-form', 'vandelBooking', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_booking_nonce'),
            'zipCodeEnabled' => $show_zip_code,
            'currency' => get_option('vandel_currency', 'USD'),
            'currencySymbol' => \VandelBooking\Helpers::getCurrencySymbol(),
            'strings' => [
                'next' => __('Next', 'vandel-booking'),
                'previous' => __('Previous', 'vandel-booking'),
                'submit' => __('Book Now', 'vandel-booking'),
                'zipCodeError' => __('Please enter a valid ZIP code', 'vandel-booking'),
                'requiredField' => __('This field is required', 'vandel-booking'),
                'invalidEmail' => __('Please enter a valid email address', 'vandel-booking'),
                'invalidPhone' => __('Please enter a valid phone number', 'vandel-booking'),
                'processingPayment' => __('Processing your booking...', 'vandel-booking'),
                'thankYou' => __('Thank you for your booking!', 'vandel-booking'),
                'errorOccurred' => __('An error occurred. Please try again.', 'vandel-booking')
            ]
        ]);
        
        ob_start();
        ?>
<div class="vandel-booking-form-container">
    <div class="vandel-booking-header">
        <?php if (!empty($atts['title'])): ?>
        <h2 class="vandel-booking-title"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>

        <?php if (!empty($atts['subtitle'])): ?>
        <p class="vandel-booking-subtitle"><?php echo esc_html($atts['subtitle']); ?></p>
        <?php endif; ?>
    </div>

    <div class="vandel-booking-progress">
        <ul class="vandel-steps">
            <?php if ($show_zip_code): ?>
            <li class="vandel-step active" data-step="location">
                <span class="vandel-step-number">1</span>
                <span class="vandel-step-label"><?php _e('Location', 'vandel-booking'); ?></span>
            </li>
            <li class="vandel-step" data-step="service">
                <span class="vandel-step-number">2</span>
                <span class="vandel-step-label"><?php _e('Service', 'vandel-booking'); ?></span>
            </li>
            <li class="vandel-step" data-step="details">
                <span class="vandel-step-number">3</span>
                <span class="vandel-step-label"><?php _e('Details', 'vandel-booking'); ?></span>
            </li>
            <li class="vandel-step" data-step="confirmation">
                <span class="vandel-step-number">4</span>
                <span class="vandel-step-label"><?php _e('Confirmation', 'vandel-booking'); ?></span>
            </li>
            <?php else: ?>
            <li class="vandel-step active" data-step="service">
                <span class="vandel-step-number">1</span>
                <span class="vandel-step-label"><?php _e('Service', 'vandel-booking'); ?></span>
            </li>
            <li class="vandel-step" data-step="details">
                <span class="vandel-step-number">2</span>
                <span class="vandel-step-label"><?php _e('Details', 'vandel-booking'); ?></span>
            </li>
            <li class="vandel-step" data-step="confirmation">
                <span class="vandel-step-number">3</span>
                <span class="vandel-step-label"><?php _e('Confirmation', 'vandel-booking'); ?></span>
            </li>
            <?php endif; ?>
        </ul>
    </div>

    <div id="vandel-booking-form" class="vandel-booking-form">
        <input type="hidden" id="vandel-selected-service" name="service_id"
            value="<?php echo esc_attr($atts['service_id']); ?>">
        <input type="hidden" id="vandel-zip-code-data" name="zip_code_data" value="">
        <input type="hidden" id="vandel-total-price" name="total_price" value="0">

        <?php if ($show_zip_code): ?>
        <!-- Step 1: ZIP Code Validation -->
        <div class="vandel-booking-step active" data-step="location">
            <?php $this->renderZipCodeStep(); ?>

            <div class="vandel-booking-nav">
                <button type="button" class="vandel-btn vandel-btn-next"><?php _e('Next', 'vandel-booking'); ?></button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Step 2: Service Selection -->
        <div class="vandel-booking-step <?php echo !$show_zip_code ? 'active' : ''; ?>" data-step="service">
            <?php $this->renderServiceStep($atts['service_id']); ?>

            <div class="vandel-booking-nav">
                <?php if ($show_zip_code): ?>
                <button type="button"
                    class="vandel-btn vandel-btn-prev"><?php _e('Previous', 'vandel-booking'); ?></button>
                <?php endif; ?>
                <button type="button" class="vandel-btn vandel-btn-next"><?php _e('Next', 'vandel-booking'); ?></button>
            </div>
        </div>

        <!-- Step 3: Customer Details -->
        <div class="vandel-booking-step" data-step="details">
            <?php $this->renderDetailsStep(); ?>

            <div class="vandel-booking-nav">
                <button type="button"
                    class="vandel-btn vandel-btn-prev"><?php _e('Previous', 'vandel-booking'); ?></button>
                <button type="button" class="vandel-btn vandel-btn-next"><?php _e('Next', 'vandel-booking'); ?></button>
            </div>
        </div>

        <!-- Step 4: Confirmation -->
        <div class="vandel-booking-step" data-step="confirmation">
            <?php $this->renderConfirmationStep(); ?>

            <div class="vandel-booking-nav">
                <button type="button"
                    class="vandel-btn vandel-btn-prev"><?php _e('Previous', 'vandel-booking'); ?></button>
                <button type="button"
                    class="vandel-btn vandel-btn-primary vandel-btn-submit"><?php _e('Book Now', 'vandel-booking'); ?></button>
            </div>
        </div>

        <!-- Success Message (Initially Hidden) -->
        <div class="vandel-booking-success" style="display: none;">
            <div class="vandel-success-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <h3><?php _e('Thank you for your booking!', 'vandel-booking'); ?></h3>
            <p><?php _e('Your booking has been submitted successfully. You will receive a confirmation email shortly.', 'vandel-booking'); ?>
            </p>
            <div class="vandel-booking-reference">
                <p><?php _e('Booking Reference:', 'vandel-booking'); ?> <span id="vandel-booking-reference"></span></p>
            </div>
        </div>
    </div>
</div>
<?php
        return ob_get_clean();
    }
    
    /**
     * Render ZIP Code step
     */
    private function renderZipCodeStep() {
        ?>
<div class="vandel-form-section">
    <h3><?php _e('Enter Your Location', 'vandel-booking'); ?></h3>
    <p><?php _e('Please enter your ZIP code to check service availability in your area.', 'vandel-booking'); ?></p>

    <div class="vandel-form-row">
        <div class="vandel-form-group">
            <label for="vandel-zip-code"><?php _e('ZIP Code', 'vandel-booking'); ?> <span
                    class="required">*</span></label>
            <input type="text" id="vandel-zip-code" name="zip_code" required>
        </div>
    </div>

    <div id="vandel-zip-validation-message" class="vandel-validation-message"></div>

    <div id="vandel-location-details" class="vandel-location-details" style="display: none;">
        <div class="vandel-location-info">
            <div class="vandel-location-icon">
                <span class="dashicons dashicons-location"></span>
            </div>
            <div class="vandel-location-text">
                <span id="vandel-city-state"></span>
                <span id="vandel-country"></span>
            </div>
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * Render service selection step
     * 
     * @param int $selected_service_id Pre-selected service ID
     */
    private function renderServiceStep($selected_service_id = 0) {
        ?>
<div class="vandel-form-section">
    <h3><?php _e('Select Service', 'vandel-booking'); ?></h3>
    <p><?php _e('Please choose the service you would like to book.', 'vandel-booking'); ?></p>

    <div class="vandel-services-grid">
        <?php
                if (empty($this->services)) {
                    echo '<p class="vandel-notice">' . __('No services available. Please check back later.', 'vandel-booking') . '</p>';
                } else {
                    foreach ($this->services as $service) {
                        $is_selected = (int)$selected_service_id === (int)$service['id'];
                        $is_popular = isset($service['is_popular']) && $service['is_popular'] === 'yes';
                        
                        $service_classes = 'vandel-service-card';
                        if ($is_selected) {
                            $service_classes .= ' selected';
                        }
                        ?>
        <div class="<?php echo esc_attr($service_classes); ?>"
            data-service-id="<?php echo esc_attr($service['id']); ?>">
            <?php if ($is_popular): ?>
            <span class="vandel-popular-badge"><?php _e('Popular', 'vandel-booking'); ?></span>
            <?php endif; ?>

            <div class="vandel-service-icon">
                <?php if (!empty($service['icon'])): ?>
                <img src="<?php echo esc_url($service['icon']); ?>" alt="<?php echo esc_attr($service['title']); ?>">
                <?php else: ?>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php endif; ?>
            </div>

            <div class="vandel-service-info">
                <h4 class="vandel-service-title"><?php echo esc_html($service['title']); ?></h4>
                <?php if (!empty($service['subtitle'])): ?>
                <p class="vandel-service-subtitle"><?php echo esc_html($service['subtitle']); ?></p>
                <?php endif; ?>

                <div class="vandel-service-price">
                    <?php echo \VandelBooking\Helpers::formatPrice($service['price']); ?>
                </div>
            </div>
        </div>
        <?php
                    }
                }
                ?>
    </div>

    <div id="vandel-service-options" class="vandel-service-options" style="display: none;">
        <h4><?php _e('Service Options', 'vandel-booking'); ?></h4>
        <div id="vandel-options-container" class="vandel-options-container">
            <!-- Options will be loaded here via AJAX -->
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * Render customer details step
     */
    private function renderDetailsStep() {
        ?>
<div class="vandel-form-section">
    <h3><?php _e('Your Details', 'vandel-booking'); ?></h3>
    <p><?php _e('Please provide your contact information and preferred date.', 'vandel-booking'); ?></p>

    <div class="vandel-form-row">
        <div class="vandel-form-group">
            <label for="vandel-name"><?php _e('Name', 'vandel-booking'); ?> <span class="required">*</span></label>
            <input type="text" id="vandel-name" name="name" required>
        </div>

        <div class="vandel-form-group">
            <label for="vandel-email"><?php _e('Email', 'vandel-booking'); ?> <span class="required">*</span></label>
            <input type="email" id="vandel-email" name="email" required>
        </div>
    </div>

    <div class="vandel-form-row">
        <div class="vandel-form-group">
            <label for="vandel-phone"><?php _e('Phone', 'vandel-booking'); ?> <span class="required">*</span></label>
            <input type="tel" id="vandel-phone" name="phone" required>
        </div>

        <div class="vandel-form-group">
            <label for="vandel-date"><?php _e('Preferred Date', 'vandel-booking'); ?> <span
                    class="required">*</span></label>
            <input type="date" id="vandel-date" name="date" required min="<?php echo date('Y-m-d'); ?>">
        </div>
    </div>

    <div class="vandel-form-row">
        <div class="vandel-form-group full-width">
            <label for="vandel-time"><?php _e('Preferred Time', 'vandel-booking'); ?> <span
                    class="required">*</span></label>
            <select id="vandel-time" name="time" required>
                <option value=""><?php _e('Select a time', 'vandel-booking'); ?></option>
                <?php
                        // Get business hours from settings
                        $start_hour = get_option('vandel_business_hours_start', '09:00');
                        $end_hour = get_option('vandel_business_hours_end', '17:00');
                        $slot_interval = intval(get_option('vandel_booking_slots_interval', 30));
                        
                        // Parse start and end times
                        $start = strtotime($start_hour);
                        $end = strtotime($end_hour);
                        
                        // Generate time slots
                        for ($time = $start; $time <= $end; $time += $slot_interval * 60) {
                            echo '<option value="' . date('H:i', $time) . '">' . date('g:i A', $time) . '</option>';
                        }
                        ?>
            </select>
        </div>
    </div>

    <div class="vandel-form-row">
        <div class="vandel-form-group full-width">
            <label for="vandel-comments"><?php _e('Special Instructions', 'vandel-booking'); ?></label>
            <textarea id="vandel-comments" name="comments" rows="3"
                placeholder="<?php _e('Please provide any additional information or special requests', 'vandel-booking'); ?>"></textarea>
        </div>
    </div>

    <div class="vandel-form-row">
        <div class="vandel-form-group full-width">
            <label class="vandel-checkbox-label">
                <input type="checkbox" id="vandel-terms" name="terms" required>
                <span class="vandel-checkbox-text">
                    <?php 
                            printf(
                                __('I agree to the %sTerms and Conditions%s', 'vandel-booking'),
                                '<a href="#" target="_blank">',
                                '</a>'
                            ); 
                            ?>
                </span>
            </label>
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * Render confirmation step
     */
    private function renderConfirmationStep() {
        ?>
<div class="vandel-form-section">
    <h3><?php _e('Confirm Your Booking', 'vandel-booking'); ?></h3>
    <p><?php _e('Please review your booking details before submitting.', 'vandel-booking'); ?></p>

    <div class="vandel-booking-summary">
        <div class="vandel-summary-section">
            <h4><?php _e('Service Details', 'vandel-booking'); ?></h4>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Service:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-service">--</span>
            </div>
            <div class="vandel-summary-item" id="summary-options-container" style="display: none;">
                <span class="vandel-summary-label"><?php _e('Options:', 'vandel-booking'); ?></span>
                <div class="vandel-summary-value" id="summary-options">
                    <!-- Options will be displayed here -->
                </div>
            </div>
        </div>

        <div class="vandel-summary-section">
            <h4><?php _e('Contact Information', 'vandel-booking'); ?></h4>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Name:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-name">--</span>
            </div>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Email:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-email">--</span>
            </div>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Phone:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-phone">--</span>
            </div>
        </div>

        <div class="vandel-summary-section">
            <h4><?php _e('Booking Details', 'vandel-booking'); ?></h4>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Date:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-date">--</span>
            </div>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Time:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-time">--</span>
            </div>
            <?php if ($this->zip_code_feature_enabled): ?>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Location:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-location">--</span>
            </div>
            <?php endif; ?>
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Special Instructions:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-comments">--</span>
            </div>
        </div>

        <div class="vandel-summary-section vandel-price-summary">
            <div class="vandel-summary-item">
                <span class="vandel-summary-label"><?php _e('Service Price:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-base-price">--</span>
            </div>
            <div class="vandel-summary-item" id="summary-options-price-container" style="display: none;">
                <span class="vandel-summary-label"><?php _e('Options:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-options-price">--</span>
            </div>
            <?php if ($this->zip_code_feature_enabled): ?>
            <div class="vandel-summary-item" id="summary-adjustment-container" style="display: none;">
                <span class="vandel-summary-label"><?php _e('Location Adjustment:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-adjustment">--</span>
            </div>
            <div class="vandel-summary-item" id="summary-service-fee-container" style="display: none;">
                <span class="vandel-summary-label"><?php _e('Service Fee:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-service-fee">--</span>
            </div>
            <?php endif; ?>
            <div class="vandel-summary-total">
                <span class="vandel-summary-label"><?php _e('Total:', 'vandel-booking'); ?></span>
                <span class="vandel-summary-value" id="summary-total">--</span>
            </div>
        </div>
    </div>
</div>
<?php
    }
    
    /**
     * AJAX handler to validate ZIP code
     */
    public function ajaxValidateZipCode() {
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        $zip_code = sanitize_text_field($_POST['zip_code']);
        
        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP Code cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Check if zip code model exists
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $zip_code_model = new \VandelBooking\Location\ZipCodeModel();
            $zip_details = $zip_code_model->get($zip_code);
            
            if (!$zip_details) {
                wp_send_json_error(['message' => __('ZIP Code not found in our service area', 'vandel-booking')]);
                return;
            }
            
            if ($zip_details->is_serviceable !== 'yes') {
                wp_send_json_error(['message' => __('Sorry, we do not serve this area yet', 'vandel-booking')]);
                return;
            }
            
            wp_send_json_success([
                'zip_code' => $zip_details->zip_code,
                'city' => $zip_details->city,
                'state' => $zip_details->state,
                'country' => $zip_details->country,
                'price_adjustment' => floatval($zip_details->price_adjustment),
                'service_fee' => floatval($zip_details->service_fee),
                'location_string' => sprintf('%s, %s', $zip_details->city, $zip_details->state)
            ]);
        } else {
            // If ZIP code model doesn't exist, simulate success for demo purposes
            wp_send_json_success([
                'zip_code' => $zip_code,
                'city' => 'Demo City',
                'state' => 'DS',
                'country' => 'Demo Country',
                'price_adjustment' => 0,
                'service_fee' => 0,
                'location_string' => 'Demo City, DS'
            ]);
        }
    }
    
/**
 * AJAX handler to get service details
 */
public function ajaxGetServiceDetails() {
    // Remove all the current content of this method and replace with:
    
    // This method is no longer needed as we've moved AJAX handling to the AjaxHandler class
    // We keep this method for backward compatibility but just pass on to the new handler
    if (class_exists('VandelBooking\\Ajax\\AjaxHandler')) {
        $ajax_handler = new \VandelBooking\Ajax\AjaxHandler();
        $ajax_handler->getServiceDetails();
    } else {
        wp_send_json_error(['message' => 'Service details handler not available']);
    }
}


// Helper function to render sub-services
public function renderSubServices($sub_services) {
    ob_start();
    
    foreach ($sub_services as $sub_service) {
        $id = $sub_service->ID;
        $type = get_post_meta($id, '_vandel_sub_service_type', true) ?: 'checkbox';
        $price = floatval(get_post_meta($id, '_vandel_sub_service_price', true));
        $required = get_post_meta($id, '_vandel_sub_service_required', true) === 'yes';
        
        echo '<div class="vandel-option-item" data-option-id="' . esc_attr($id) . '" data-option-type="' . esc_attr($type) . '">';
        echo '<div class="vandel-option-header">';
        echo '<h4 class="vandel-option-title">' . esc_html($sub_service->post_title) . ($required ? ' <span class="required">*</span>' : '') . '</h4>';
        
        if ($price > 0) {
            echo '<span class="vandel-option-price">' . \VandelBooking\Helpers::formatPrice($price) . '</span>';
        }
        
        echo '</div>';
        
        $subtitle = get_post_meta($id, '_vandel_sub_service_subtitle', true);
        if (!empty($subtitle)) {
            echo '<p class="vandel-option-subtitle">' . esc_html($subtitle) . '</p>';
        }
        
        echo '<div class="vandel-option-input">';
        
        // Render different input types
        switch ($type) {
            case 'checkbox':
                echo '<label class="vandel-checkbox-label">';
                echo '<input type="checkbox" name="options[' . esc_attr($id) . ']" value="yes" data-price="' . esc_attr($price) . '" ' . ($required ? 'required' : '') . '>';
                echo '<span class="vandel-checkbox-text">' . __('Yes, add this service', 'vandel-booking') . '</span>';
                echo '</label>';
                break;
                
            case 'text':
                echo '<input type="text" name="options[' . esc_attr($id) . ']" ' . ($required ? 'required' : '') . '>';
                break;
                
            // Add other input types as needed
        }
        
        echo '</div>';
        echo '</div>';
    }
    
    return ob_get_clean();
}

    /**
     * AJAX handler to submit booking
     */
    public function ajaxSubmitBooking() {
        check_ajax_referer('vandel_booking_nonce', 'nonce');
        
        try {
            // Validate form data
            $required_fields = ['service_id', 'name', 'email', 'phone', 'date', 'time', 'terms'];
            
            $data = [];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new \Exception(sprintf(__('Missing required field: %s', 'vandel-booking'), $field));
                }
                $data[$field] = sanitize_text_field($_POST[$field]);
            }
            
            // Validate email
            if (!is_email($data['email'])) {
                throw new \Exception(__('Invalid email address', 'vandel-booking'));
            }
            
            // Validate service
            $service_id = intval($data['service_id']);
            $service = get_post($service_id);
            if (!$service || $service->post_type !== 'vandel_service') {
                throw new \Exception(__('Invalid service selection', 'vandel-booking'));
            }
            
            // Combine date and time
            $booking_date = date('Y-m-d H:i:s', strtotime($data['date'] . ' ' . $data['time']));
            
            // Collect selected options
            $selected_options = [];
            if (!empty($_POST['options']) && is_array($_POST['options'])) {
                foreach ($_POST['options'] as $option_id => $option_value) {
                    $selected_options[$option_id] = sanitize_text_field($option_value);
                }
            }
            
            // Get ZIP code data if available
            $zip_code_data = [];
            if (!empty($_POST['zip_code_data'])) {
                $zip_code_data = json_decode(stripslashes($_POST['zip_code_data']), true);
            }
            
            // Calculate total price
            $base_price = floatval(get_post_meta($service_id, '_vandel_service_base_price', true));
            $options_price = $this->calculateOptionsPrice($selected_options);
            $price_adjustment = isset($zip_code_data['price_adjustment']) ? floatval($zip_code_data['price_adjustment']) : 0;
            $service_fee = isset($zip_code_data['service_fee']) ? floatval($zip_code_data['service_fee']) : 0;
            
            $total_price = $base_price + $options_price + $price_adjustment + $service_fee;
            
            // Prepare booking data
            $booking_data = [
                'service' => $service_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'date' => $booking_date,
                'options' => $selected_options,
                'total' => $total_price,
                'comments' => isset($_POST['comments']) ? sanitize_textarea_field($_POST['comments']) : '',
                'access_info' => isset($zip_code_data['zip_code']) ? sanitize_text_field($zip_code_data['zip_code']) : '',
                'access_type' => 'zip_code'
            ];
            
            // Create booking
            if (class_exists('\\VandelBooking\\Booking\\BookingManager')) {
                $booking_manager = new \VandelBooking\Booking\BookingManager();
                $booking_id = $booking_manager->createBooking($booking_data);
                
                if (is_wp_error($booking_id)) {
                    throw new \Exception($booking_id->get_error_message());
                }
                
                wp_send_json_success([
                    'booking_id' => $booking_id,
                    'message' => __('Booking created successfully', 'vandel-booking')
                ]);
            } else {
                // For demo/debug purposes when BookingManager doesn't exist
                wp_send_json_success([
                    'booking_id' => rand(1000, 9999),
                    'message' => __('Booking created successfully (demo mode)', 'vandel-booking'),
                    'debug_data' => $booking_data
                ]);
            }
        } catch (\Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Calculate the total price for selected options
     * 
     * @param array $selected_options Selected options
     * @return float Total price for options
     */
    private function calculateOptionsPrice($selected_options) {
        $total = 0;
        
        foreach ($selected_options as $option_id => $option_value) {
            // Skip if empty
            if (empty($option_value)) {
                continue;
            }
            
            $sub_service = get_post($option_id);
            if (!$sub_service || $sub_service->post_type !== 'vandel_sub_service') {
                continue;
            }
            
            $type = get_post_meta($option_id, '_vandel_sub_service_type', true) ?: 'checkbox';
            $base_price = floatval(get_post_meta($option_id, '_vandel_sub_service_price', true));
            
            switch ($type) {
                case 'checkbox':
                    // Value is either 'yes' or empty
                    if ($option_value === 'yes') {
                        $total += $base_price;
                    }
                    break;
                    
                case 'radio':
                case 'dropdown':
                    // Value is option ID, get price from options
                    $options = json_decode(get_post_meta($option_id, '_vandel_sub_service_options', true), true) ?: [];
                    foreach ($options as $option) {
                        if (isset($option['name']) && $option['name'] === $option_value) {
                            $total += isset($option['price']) ? floatval($option['price']) : 0;
                            break;
                        }
                    }
                    break;
                    
                case 'number':
                    // Value is quantity
                    $quantity = intval($option_value);
                    if ($quantity > 0) {
                        $total += $base_price * $quantity;
                    }
                    break;
                    
                default:
                    // Text inputs might have fixed price
                    if (!empty($option_value) && $base_price > 0) {
                        $total += $base_price;
                    }
                    break;
            }
        }
        
        return $total;
    }
    
    /**
     * Render HTML for service options
     * 
     * @param array $options Service options
     * @return string HTML for options
     */
    private function renderOptionsHtml($options) {
        if (empty($options)) {
            return '';
        }
        
        ob_start();
        
        foreach ($options as $option) {
            $option_id = $option['id'];
            $required = $option['required'] ? 'required' : '';
            $required_mark = $option['required'] ? '<span class="required">*</span>' : '';
            $formatted_price = \VandelBooking\Helpers::formatPrice($option['price']);
            
            echo '<div class="vandel-option-item" data-option-id="' . esc_attr($option_id) . '" data-option-type="' . esc_attr($option['type']) . '">';
            echo '<div class="vandel-option-header">';
            echo '<h4 class="vandel-option-title">' . esc_html($option['title']) . ' ' . $required_mark . '</h4>';
            
            if ($option['price'] > 0) {
                echo '<span class="vandel-option-price">' . esc_html($formatted_price) . '</span>';
            }
            
            echo '</div>';
            
            if (!empty($option['subtitle'])) {
                echo '<p class="vandel-option-subtitle">' . esc_html($option['subtitle']) . '</p>';
            }
            
            echo '<div class="vandel-option-input">';
            
            switch ($option['type']) {
                case 'checkbox':
                    echo '<label class="vandel-checkbox-label">';
                    echo '<input type="checkbox" name="options[' . esc_attr($option_id) . ']" value="yes" data-price="' . esc_attr($option['price']) . '" ' . $required . '>';
                    echo '<span class="vandel-checkbox-text">' . __('Yes, add this service', 'vandel-booking') . '</span>';
                    echo '</label>';
                    break;
                    
                case 'radio':
                    if (!empty($option['options'])) {
                        foreach ($option['options'] as $choice) {
                            $choice_price = isset($choice['price']) ? floatval($choice['price']) : 0;
                            $formatted_choice_price = \VandelBooking\Helpers::formatPrice($choice_price);
                            
                            echo '<label class="vandel-radio-label">';
                            echo '<input type="radio" name="options[' . esc_attr($option_id) . ']" value="' . esc_attr($choice['name']) . '" data-price="' . esc_attr($choice_price) . '" ' . $required . '>';
                            echo '<span class="vandel-radio-text">' . esc_html($choice['name']);
                            
                            if ($choice_price > 0) {
                                echo ' <span class="vandel-radio-price">(' . esc_html($formatted_choice_price) . ')</span>';
                            }
                            
                            echo '</span>';
                            echo '</label>';
                        }
                    }
                    break;
                    
                case 'dropdown':
                    echo '<select name="options[' . esc_attr($option_id) . ']" ' . $required . '>';
                    echo '<option value="">' . __('Select an option', 'vandel-booking') . '</option>';
                    
                    if (!empty($option['options'])) {
                        foreach ($option['options'] as $choice) {
                            $choice_price = isset($choice['price']) ? floatval($choice['price']) : 0;
                            $formatted_choice_price = \VandelBooking\Helpers::formatPrice($choice_price);
                            
                            echo '<option value="' . esc_attr($choice['name']) . '" data-price="' . esc_attr($choice_price) . '">';
                            echo esc_html($choice['name']);
                            
                            if ($choice_price > 0) {
                                echo ' (' . esc_html($formatted_choice_price) . ')';
                            }
                            
                            echo '</option>';
                        }
                    }
                    
                    echo '</select>';
                    break;
                    
                case 'number':
                    $min = isset($option['min']) ? intval($option['min']) : 0;
                    $max = isset($option['max']) ? intval($option['max']) : 999;
                    $default = isset($option['default']) ? intval($option['default']) : 0;
                    
                    echo '<input type="number" name="options[' . esc_attr($option_id) . ']" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" value="' . esc_attr($default) . '" data-price="' . esc_attr($option['price']) . '" ' . $required . '>';
                    
                    if ($option['price'] > 0) {
                        echo '<span class="vandel-price-per-unit">' . esc_html($formatted_price) . ' ' . __('per unit', 'vandel-booking') . '</span>';
                    }
                    break;
                    
                case 'text':
                    echo '<input type="text" name="options[' . esc_attr($option_id) . ']" placeholder="' . esc_attr(get_post_meta($option_id, '_vandel_sub_service_placeholder', true)) . '" ' . $required . '>';
                    break;
                    
                case 'textarea':
                    echo '<textarea name="options[' . esc_attr($option_id) . ']" rows="3" placeholder="' . esc_attr(get_post_meta($option_id, '_vandel_sub_service_placeholder', true)) . '" ' . $required . '></textarea>';
                    break;
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get all available services
     * 
     * @return array List of services
     */
    private function getServices() {
        $services = [];
        
        $service_posts = get_posts([
            'post_type' => 'vandel_service',
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'meta_query' => [
                [
                    'key' => '_vandel_service_active',
                    'value' => 'no',
                    'compare' => '!='
                ]
            ]
        ]);
        
        foreach ($service_posts as $service) {
            $services[] = [
                'id' => $service->ID,
                'title' => $service->post_title,
                'subtitle' => get_post_meta($service->ID, '_vandel_service_subtitle', true),
                'description' => get_post_meta($service->ID, '_vandel_service_description', true),
                'price' => floatval(get_post_meta($service->ID, '_vandel_service_base_price', true)),
                'duration' => get_post_meta($service->ID, '_vandel_service_duration', true),
                'is_popular' => get_post_meta($service->ID, '_vandel_service_is_popular', true),
                'icon' => get_the_post_thumbnail_url($service->ID, 'thumbnail')
            ];
        }
        
        return $services;
    }



/**
     * Enqueue booking form scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'vandel-booking-form',
            VANDEL_PLUGIN_URL . 'assets/css/booking-form.css',
            [],
            VANDEL_VERSION
        );
        
        wp_enqueue_script(
            'vandel-booking-form',
            VANDEL_PLUGIN_URL . 'assets/js/booking-form.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Enqueue location selection script
        wp_enqueue_script(
            'vandel-location-select',
            VANDEL_PLUGIN_URL . 'assets/js/location-select.js',
            ['jquery', 'vandel-booking-form'],
            VANDEL_VERSION,
            true
        );
        
        // Get currency symbol for price formatting
        $currency_symbol = $this->get_currency_symbol();
        
        wp_localize_script(
            'vandel-booking-form',
            'vandelBooking',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('vandel_booking_form'),
                'currencySymbol' => $currency_symbol,
                'strings' => [
                    'errorRequired'     => __('This field is required.', 'vandel-booking'),
                    'errorEmail'        => __('Please enter a valid email address.', 'vandel-booking'),
                    'errorPhone'        => __('Please enter a valid phone number.', 'vandel-booking'),
                    'errorDate'         => __('Please select a valid date.', 'vandel-booking'),
                    'errorTime'         => __('Please select a valid time.', 'vandel-booking'),
                    'errorZipCode'      => __('Please enter a valid ZIP code.', 'vandel-booking'),
                    'errorLocationValidation' => __('Failed to validate location.', 'vandel-booking'),
                    'errorForm'         => __('Please check the form and fix all errors.', 'vandel-booking'),
                    'successBooking'    => __('Your booking has been submitted successfully!', 'vandel-booking'),
                    'errorBooking'      => __('There was an error submitting your booking. Please try again.', 'vandel-booking'),
                    'loadingText'       => __('Loading...', 'vandel-booking'),
                    'submittingText'    => __('Submitting...', 'vandel-booking'),
                    'validatingZipCode' => __('Validating ZIP code...', 'vandel-booking'),
                    'selectCountry'     => __('Select Country', 'vandel-booking'),
                    'selectCity'        => __('Select City', 'vandel-booking'),
                    'selectArea'        => __('Select Area', 'vandel-booking'),
                    'loadingCities'     => __('Loading cities', 'vandel-booking'),
                    'loadingAreas'      => __('Loading areas', 'vandel-booking'),
                    'selectedArea'      => __('Selected Area', 'vandel-booking'),
                    'priceAdjustment'   => __('Price Adjustment', 'vandel-booking'),
                    'serviceFee'        => __('Service Fee', 'vandel-booking'),
                ]
            ]
        );
    }
    
    /**
     * Get currency symbol based on settings
     * 
     * @return string Currency symbol
     */
    private function get_currency_symbol() {
        $currency = get_option('vandel_currency', 'EUR');
        
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SEK' => 'kr',
            'NOK' => 'kr',
            'DKK' => 'kr'
        ];
        
        return isset($symbols[$currency]) ? $symbols[$currency] : $currency;
    }
}