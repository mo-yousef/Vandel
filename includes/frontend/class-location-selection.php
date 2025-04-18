<?php
namespace VandelBooking\Frontend;

/**
 * Location Selection Component
 */
class LocationSelection {
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
        
        // Add hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_filter('vandel_booking_form_location_step', [$this, 'renderLocationStep'], 10, 2);
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueueScripts() {
        wp_enqueue_script(
            'vandel-location-selection',
            VANDEL_PLUGIN_URL . 'assets/js/location-selection.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        wp_localize_script(
            'vandel-location-selection',
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
                    'noLocations' => __('No locations available', 'vandel-booking')
                ]
            ]
        );
    }
    
    /**
     * Render location selection step
     * 
     * @param string $html Current HTML
     * @param array $atts Shortcode attributes
     * @return string Updated HTML
     */
    public function renderLocationStep($html, $atts) {
        // Only proceed if location model is available
        if (!$this->location_model) {
            return $html;
        }
        
        // Get countries
        $countries = $this->location_model->getCountries();
        if (empty($countries)) {
            return $html;
        }
        
        ob_start();
        ?>
        <div class="vandel-form-section">
            <h3><?php _e('Your Location', 'vandel-booking'); ?></h3>
            <p><?php _e('Please select your location to check service availability.', 'vandel-booking'); ?></p>
            
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
                <div class="vandel-form-group full-width">
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
            
            <!-- Location Details Display -->
            <div id="vandel-location-details" class="vandel-location-details" style="display: none;">
                <div class="vandel-location-info">
                    <div class="vandel-location-icon">
                        <span class="dashicons dashicons-location"></span>
                    </div>
                    <div class="vandel-location-text">
                        <div class="vandel-location-name">
                            <span id="vandel-location-area"></span>
                        </div>
                        <div class="vandel-location-city">
                            <span id="vandel-location-city"></span>
                        </div>
                    </div>
                </div>
                <div class="vandel-price-info" id="vandel-price-info" style="display: none;">
                    <!-- Price adjustments will be displayed here -->
                </div>
            </div>
        </div>
        
        <input type="hidden" id="vandel-location-data" name="location_data" value="">
        <?php
        
        return ob_get_clean();
    }
}