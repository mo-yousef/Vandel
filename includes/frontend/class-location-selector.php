<?php
namespace VandelBooking\Frontend;

/**
 * Location Selector Component
 * 
 * Handles the location selection step in the booking form
 */
class LocationSelector {
    /**
     * @var \VandelBooking\Location\LocationModel
     */
    private $location_model;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize location model
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new \VandelBooking\Location\LocationModel();
        }
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue necessary scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Add location selection step to booking form
        add_filter('vandel_booking_form_steps', [$this, 'add_location_step'], 20, 1);
        
        // Render location selection step content
        add_action('vandel_booking_form_step_location', [$this, 'render_location_step']);
        
        // Add location data to booking submission
        add_filter('vandel_booking_submission_data', [$this, 'add_location_data'], 10, 2);
        
        // Validate location selection
        add_filter('vandel_validate_booking_step_location', [$this, 'validate_location_step'], 10, 2);
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Only enqueue on pages with the booking form
        if (!is_page() || !has_shortcode(get_post()->post_content, 'vandel_booking_form')) {
            return;
        }
        
        wp_enqueue_script(
            'vandel-location-selector',
            VANDEL_PLUGIN_URL . 'assets/js/location-selector.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        wp_localize_script(
            'vandel-location-selector',
            'vandelLocation',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_location_nonce'),
                'strings' => [
                    'selectCountry' => __('Select Country', 'vandel-booking'),
                    'selectCity' => __('Select City', 'vandel-booking'),
                    'selectArea' => __('Select Area', 'vandel-booking'),
                    'loadingCities' => __('Loading cities...', 'vandel-booking'),
                    'loadingAreas' => __('Loading areas...', 'vandel-booking'),
                    'noLocations' => __('No locations available', 'vandel-booking'),
                    'validatingLocation' => __('Validating location...', 'vandel-booking'),
                    'validatingZipCode' => __('Validating ZIP code...', 'vandel-booking'),
                    'errorLocation' => __('Error validating location. Please try again.', 'vandel-booking'),
                    'selectedArea' => __('Selected Area', 'vandel-booking'),
                    'priceAdjustment' => __('Price Adjustment', 'vandel-booking'),
                    'serviceFee' => __('Service Fee', 'vandel-booking')
                ]
            ]
        );
        
        wp_enqueue_style(
            'vandel-location-selector',
            VANDEL_PLUGIN_URL . 'assets/css/location-selector.css',
            [],
            VANDEL_VERSION
        );
    }
    
    /**
     * Add location step to booking form steps
     * 
     * @param array $steps Current form steps
     * @return array Modified form steps
     */
    public function add_location_step($steps) {
        // Only add if location model is available
        if (!$this->location_model) {
            return $steps;
        }
        
        // Check if location system is enabled in settings
        $location_enabled = get_option('vandel_enable_location_system', 'yes') === 'yes';
        if (!$location_enabled) {
            return $steps;
        }
        
        // Add location step after service selection
        $new_steps = [];
        foreach ($steps as $key => $step) {
            $new_steps[$key] = $step;
            
            if ($key === 'service') {
                $new_steps['location'] = [
                    'title' => __('Location', 'vandel-booking'),
                    'icon' => 'location',
                    'description' => __('Select your location', 'vandel-booking')
                ];
            }
        }
        
        return $new_steps;
    }
    
    /**
     * Render location step content
     */
    public function render_location_step() {
        // Only render if location model is available
        if (!$this->location_model) {
            echo '<div class="vandel-notice vandel-error">' . __('Location service is not available', 'vandel-booking') . '</div>';
            return;
        }
        
        // Get countries
        $countries = $this->location_model->getCountries();
        
        // If no countries, show error
        if (empty($countries)) {
            echo '<div class="vandel-notice vandel-error">' . __('No locations available. Please contact us directly to book a service.', 'vandel-booking') . '</div>';
            return;
        }
        ?>
        <div class="vandel-form-section vandel-location-section">
            <h3><?php _e('Your Location', 'vandel-booking'); ?></h3>
            <p class="vandel-section-description"><?php _e('Please provide your location to check service availability and pricing.', 'vandel-booking'); ?></p>
            
            <div class="vandel-form-row">
                <div class="vandel-form-group">
                    <label for="vandel-country"><?php _e('Country', 'vandel-booking'); ?> <span class="required">*</span></label>
                    <select id="vandel-country" name="country" required>
                        <option value=""><?php _e('Select Country', 'vandel-booking'); ?></option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="vandel-form-group">
                    <label for="vandel-city"><?php _e('City', 'vandel-booking'); ?> <span class="required">*</span></label>
                    <select id="vandel-city" name="city" required disabled>
                        <option value=""><?php _e('Select City', 'vandel-booking'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="vandel-form-row">
                <div class="vandel-form-group vandel-full-width">
                    <label for="vandel-area"><?php _e('Area', 'vandel-booking'); ?> <span class="required">*</span></label>
                    <select id="vandel-area" name="area" required disabled>
                        <option value=""><?php _e('Select Area', 'vandel-booking'); ?></option>
                    </select>
                </div>
            </div>
            
            <div class="vandel-form-row">
                <div class="vandel-form-group">
                    <label for="vandel-zip-code"><?php _e('ZIP Code', 'vandel-booking'); ?> <span class="required">*</span></label>
                    <input type="text" id="vandel-zip-code" name="zip_code" required>
                </div>
            </div>
            
            <div id="vandel-location-message" class="vandel-validation-message"></div>
            
            <!-- Location details display -->
            <div id="vandel-location-details" class="vandel-location-details" style="display: none;">
                <div class="vandel-location-info">
                    <div class="vandel-location-icon">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <div class="vandel-location-text">
                        <div class="vandel-location-area">
                            <span id="vandel-location-area"></span>
                        </div>
                        <div class="vandel-location-city">
                            <span id="vandel-location-city"></span>
                        </div>
                    </div>
                </div>
                
                <div id="vandel-price-info" class="vandel-price-info" style="display: none;"></div>
            </div>
            
            <!-- Hidden field to store complete location data -->
            <input type="hidden" id="vandel-location-data" name="location_data" value="">
        </div>
        <?php
    }
    
    /**
     * Add location data to booking submission
     * 
     * @param array $submission_data Current submission data
     * @param array $form_data Form data
     * @return array Modified submission data
     */
    public function add_location_data($submission_data, $form_data) {
        // Only process if location model is available
        if (!$this->location_model) {
            return $submission_data;
        }
        
        // Get location data from form
        if (!empty($form_data['location_data'])) {
            $location_data = json_decode(stripslashes($form_data['location_data']), true);
            
            if (is_array($location_data)) {
                // Add location fields to submission data
                $submission_data['country'] = $location_data['country'];
                $submission_data['city'] = $location_data['city'];
                $submission_data['area_name'] = $location_data['area_name'];
                $submission_data['zip_code'] = $location_data['zip_code'];
                
                // Add location price adjustments
                if (isset($location_data['price_adjustment'])) {
                    $submission_data['price_adjustment'] = floatval($location_data['price_adjustment']);
                }
                
                if (isset($location_data['service_fee'])) {
                    $submission_data['service_fee'] = floatval($location_data['service_fee']);
                }
                
                // Store full location data for reference
                $submission_data['location_data'] = $form_data['location_data'];
            }
        } elseif (!empty($form_data['zip_code'])) {
            // If only ZIP code is provided, try to get location data
            $zip_code = sanitize_text_field($form_data['zip_code']);
            $location = $this->location_model->getByZipCode($zip_code);
            
            if ($location) {
                $submission_data['country'] = $location->country;
                $submission_data['city'] = $location->city;
                $submission_data['area_name'] = $location->area_name;
                $submission_data['zip_code'] = $location->zip_code;
                $submission_data['price_adjustment'] = floatval($location->price_adjustment);
                $submission_data['service_fee'] = floatval($location->service_fee);
            } else {
                $submission_data['zip_code'] = $zip_code;
            }
        }
        
        return $submission_data;
    }
    
    /**
     * Validate location step
     * 
     * @param bool $valid Current validation status
     * @param array $form_data Form data
     * @return bool|string True if valid, error message if invalid
     */
    public function validate_location_step($valid, $form_data) {
        // Only validate if location model is available
        if (!$this->location_model) {
            return $valid;
        }
        
        // Check if we have location data
        if (empty($form_data['location_data']) && empty($form_data['zip_code'])) {
            return __('Please select your location or enter a ZIP code', 'vandel-booking');
        }
        
        // If location data is provided, it's validated by the frontend script
        if (!empty($form_data['location_data'])) {
            return true;
        }
        
        // If only ZIP code is provided, validate it here
        if (!empty($form_data['zip_code'])) {
            $zip_code = sanitize_text_field($form_data['zip_code']);
            $location = $this->location_model->getByZipCode($zip_code);
            
            if (!$location) {
                return __('The provided ZIP code is not in our service area', 'vandel-booking');
            }
            
            if ($location->is_active !== 'yes') {
                return __('We currently do not provide service in your area', 'vandel-booking');
            }
        }
        
        return true;
    }
}