<?php
namespace VandelBooking\Location;

/**
 * LocationService - Comprehensive location management for Vandel Booking
 * 
 * This single class handles all location-related functionality including:
 * - Country management
 * - City discovery
 * - ZIP code import and management
 * - Location data for booking forms
 * - Admin dashboard controls for locations
 */
class LocationService {
    /**
     * @var string Database table name for locations
     */
    private $table;
    
    /**
     * @var array Cache for countries
     */
    private $countries_cache = [];
    
    /**
     * @var array Cache for cities
     */
    private $cities_cache = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'vandel_locations';
        
        // Ensure table exists
        $this->create_table();
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Register all necessary hooks
     */
    public function register_hooks() {
        // Admin hooks
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'add_menu_page'], 20);
        
        // AJAX hooks
        add_action('wp_ajax_vandel_get_cities', [$this, 'ajax_get_cities']);
        add_action('wp_ajax_nopriv_vandel_get_cities', [$this, 'ajax_get_cities']);
        add_action('wp_ajax_vandel_get_zipcodes', [$this, 'ajax_get_zipcodes']);
        add_action('wp_ajax_nopriv_vandel_get_zipcodes', [$this, 'ajax_get_zipcodes']);
        add_action('wp_ajax_vandel_validate_location', [$this, 'ajax_validate_location']);
        add_action('wp_ajax_nopriv_vandel_validate_location', [$this, 'ajax_validate_location']);
        
        // Admin AJAX handlers
        add_action('wp_ajax_vandel_add_country', [$this, 'ajax_add_country']);
        add_action('wp_ajax_vandel_add_city', [$this, 'ajax_add_city']);
        add_action('wp_ajax_vandel_toggle_location', [$this, 'ajax_toggle_location']);
        add_action('wp_ajax_vandel_import_zipcodes', [$this, 'ajax_import_zipcodes']);
        
        // Frontend hooks for booking form
        add_filter('vandel_booking_form_data', [$this, 'add_locations_to_booking_form']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }
    
    /**
     * Create the locations table if it doesn't exist
     */
    private function create_table() {
        global $wpdb;
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") === $this->table;
        
        if (!$table_exists) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE {$this->table} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                country VARCHAR(100) NOT NULL,
                city VARCHAR(100) NOT NULL,
                area_name VARCHAR(255) NOT NULL,
                zip_code VARCHAR(20) NOT NULL,
                price_adjustment DECIMAL(10, 2) DEFAULT 0,
                service_fee DECIMAL(10, 2) DEFAULT 0,
                is_active ENUM('yes', 'no') DEFAULT 'yes',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY location_zip (zip_code),
                KEY country_city (country, city)
            ) $charset_collate;";
            
            dbDelta($sql);
        }
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('vandel_location_settings', 'vandel_enabled_countries', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitize_countries']
        ]);
        
        register_setting('vandel_location_settings', 'vandel_enabled_cities', [
            'type' => 'array',
            'default' => [],
            'sanitize_callback' => [$this, 'sanitize_cities']
        ]);
    }
    
    /**
     * Sanitize countries array
     * 
     * @param array $countries List of countries
     * @return array Sanitized countries
     */
    public function sanitize_countries($countries) {
        if (!is_array($countries)) {
            return [];
        }
        
        return array_map('sanitize_text_field', $countries);
    }
    
    /**
     * Sanitize cities array
     * 
     * @param array $cities List of cities
     * @return array Sanitized cities
     */
    public function sanitize_cities($cities) {
        if (!is_array($cities)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($cities as $country => $city_list) {
            $country = sanitize_text_field($country);
            $sanitized[$country] = array_map('sanitize_text_field', $city_list);
        }
        
        return $sanitized;
    }
    
    /**
     * Add menu page for location management
     */
    public function add_menu_page() {
        add_submenu_page(
            'vandel-dashboard',
            __('Location Management', 'vandel-booking'),
            __('Locations', 'vandel-booking'),
            'manage_options',
            'vandel-locations',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     * 
     * @param string $hook Current admin page
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'vandel-dashboard_page_vandel-locations' && 
            !($hook === 'toplevel_page_vandel-dashboard' && 
            isset($_GET['tab']) && $_GET['tab'] === 'settings' && 
            isset($_GET['section']) && $_GET['section'] === 'locations')) {
            return;
        }
        
        // Add some basic styles for the settings page context
        $is_settings_page = isset($_GET['tab']) && $_GET['tab'] === 'settings';
        
        if ($is_settings_page) {
            wp_add_inline_style('vandel-admin', '
                .vandel-locations-container {
                    margin-top: 20px;
                }
                .vandel-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                    margin-bottom: 20px;
                }
                .vandel-card-header {
                    border-bottom: 1px solid #eee;
                    padding: 10px 15px;
                    background: #f9f9f9;
                }
                .vandel-card-body {
                    padding: 15px;
                }
                .vandel-form-row {
                    display: flex;
                    flex-wrap: wrap;
                    margin-bottom: 15px;
                }
                .vandel-form-group {
                    flex: 1;
                    min-width: 200px;
                    margin-right: 15px;
                }
                .vandel-form-group label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 500;
                }
                .vandel-empty-state {
                    color: #777;
                    font-style: italic;
                }
/* Add to your existing inline styles */
.vandel-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.4);
}

.vandel-modal-content {
    position: relative;
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
    border-radius: 4px;
    box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
}

.vandel-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    position: absolute;
    top: 10px;
    right: 15px;
}

.vandel-modal-close:hover,
.vandel-modal-close:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: #2196F3;
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}
            ');
        }
        
        // Enqueue the minimal JS needed
        wp_enqueue_script(
            'vandel-location-service',
            VANDEL_PLUGIN_URL . 'assets/js/admin/location-service.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Localize with necessary data
        wp_localize_script(
            'vandel-location-service',
            'vandelLocationService',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_location_service'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'vandel-booking'),
                    'processing' => __('Processing...', 'vandel-booking'),
                    'success' => __('Success!', 'vandel-booking'),
                    'error' => __('Error occurred. Please try again.', 'vandel-booking'),
                    'fetchingCities' => __('Fetching cities...', 'vandel-booking'),
                    'importingZipCodes' => __('Importing ZIP codes...', 'vandel-booking'),
                    'noResults' => __('No results found', 'vandel-booking')
                ]
            ]
        );
    }
    
    /**
     * Render admin page for location management
     */
    public function render_admin_page() {
        // Get available countries
        $countries = $this->get_countries();
        
        // Get enabled countries and cities
        $enabled_countries = get_option('vandel_enabled_countries', []);
        $enabled_cities = get_option('vandel_enabled_cities', []);
        
        ?>
        <div class="wrap vandel-locations-page">
            <h1><?php _e('Location Management', 'vandel-booking'); ?></h1>
            
            <div class="vandel-locations-container">
                <!-- Countries Section -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h2><?php _e('Countries', 'vandel-booking'); ?></h2>
                    </div>
                    <div class="vandel-card-body">
                        <!-- Add Country Form -->
                        <form id="add-country-form" class="vandel-inline-form">
                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="country-name"><?php _e('Country Name', 'vandel-booking'); ?></label>
                                    <input type="text" id="country-name" placeholder="<?php esc_attr_e('e.g. Sweden', 'vandel-booking'); ?>" required>
                                </div>
                                <div class="vandel-form-submit">
                                    <button type="submit" class="button button-primary"><?php _e('Add Country', 'vandel-booking'); ?></button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Countries List -->
                        <div class="vandel-countries-list">
                            <h3><?php _e('Available Countries', 'vandel-booking'); ?></h3>
                            
                            <?php if (empty($countries)): ?>
                                <p class="vandel-empty-state"><?php _e('No countries available. Add a country to get started.', 'vandel-booking'); ?></p>
                            <?php else: ?>
                                <ul class="vandel-checkbox-list">
                                    <?php foreach ($countries as $country): ?>
                                        <li>
                                            <label>
                                                <input type="checkbox" class="country-toggle" 
                                                       data-country="<?php echo esc_attr($country); ?>" 
                                                       <?php checked(in_array($country, $enabled_countries)); ?>>
                                                <?php echo esc_html($country); ?>
                                            </label>
                                            
                                            <a href="#" class="show-cities-btn" data-country="<?php echo esc_attr($country); ?>">
                                                <?php _e('Show Cities', 'vandel-booking'); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Cities Section -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h2><?php _e('Cities', 'vandel-booking'); ?></h2>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-country-select">
                            <label for="country-select"><?php _e('Select Country', 'vandel-booking'); ?></label>
                            <select id="country-select">
                                <option value=""><?php _e('-- Select Country --', 'vandel-booking'); ?></option>
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="cities-container" class="vandel-cities-container">
                            <p class="vandel-empty-state"><?php _e('Select a country to manage its cities', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- ZIP Codes Section -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h2><?php _e('ZIP Codes', 'vandel-booking'); ?></h2>
                    </div>
                    <div class="vandel-card-body">
                        <div class="vandel-zipcode-filters">
                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="zipcode-country"><?php _e('Country', 'vandel-booking'); ?></label>
                                    <select id="zipcode-country">
                                        <option value=""><?php _e('-- Select Country --', 'vandel-booking'); ?></option>
                                        <?php foreach ($enabled_countries as $country): ?>
                                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="vandel-form-group">
                                    <label for="zipcode-city"><?php _e('City', 'vandel-booking'); ?></label>
                                    <select id="zipcode-city" disabled>
                                        <option value=""><?php _e('-- Select City --', 'vandel-booking'); ?></option>
                                    </select>
                                </div>
                                <div class="vandel-form-submit">
                                    <button type="button" id="load-zipcodes-btn" class="button" disabled>
                                        <?php _e('Load ZIP Codes', 'vandel-booking'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="zipcodes-container" class="vandel-zipcodes-container">
                            <p class="vandel-empty-state"><?php _e('Select a country and city to manage ZIP codes', 'vandel-booking'); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Import ZIP Codes Section -->
                <div class="vandel-card">
                    <div class="vandel-card-header">
                        <h2><?php _e('Import ZIP Codes', 'vandel-booking'); ?></h2>
                    </div>
                    <div class="vandel-card-body">
                        <form id="import-zipcodes-form" enctype="multipart/form-data">
                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="import-country"><?php _e('Country', 'vandel-booking'); ?></label>
                                    <select id="import-country" required>
                                        <option value=""><?php _e('-- Select Country --', 'vandel-booking'); ?></option>
                                        <?php foreach ($countries as $country): ?>
                                            <option value="<?php echo esc_attr($country); ?>"><?php echo esc_html($country); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="vandel-form-group">
                                    <label for="import-city"><?php _e('City', 'vandel-booking'); ?></label>
                                    <select id="import-city" disabled required>
                                        <option value=""><?php _e('-- Select City --', 'vandel-booking'); ?></option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="vandel-form-row">
                                <div class="vandel-form-group">
                                    <label for="zipcode-file"><?php _e('CSV File', 'vandel-booking'); ?></label>
                                    <input type="file" id="zipcode-file" name="zipcode_file" accept=".csv,.txt" required>
                                    <p class="description"><?php _e('CSV format: ZIP Code,Area Name,Price Adjustment,Service Fee', 'vandel-booking'); ?></p>
                                </div>
                            </div>
                            
                            <div class="vandel-form-submit">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Import ZIP Codes', 'vandel-booking'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <!-- Sample Files -->
                        <div class="vandel-sample-files">
                            <h3><?php _e('Sample Files', 'vandel-booking'); ?></h3>
                            <p><?php _e('Download sample ZIP code files for common countries:', 'vandel-booking'); ?></p>
                            
                            <ul class="vandel-sample-list">
                                <li>
                                    <a href="#" class="generate-sample" data-country="Sweden">
                                        <?php _e('Sweden ZIP Codes Sample', 'vandel-booking'); ?>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="generate-sample" data-country="USA">
                                        <?php _e('USA ZIP Codes Sample', 'vandel-booking'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for adding a new country
     */
    public function ajax_add_country() {
        // Verify nonce
        check_ajax_referer('vandel_location_service', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'vandel-booking')]);
            return;
        }
        
        // Get country name
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(['message' => __('Country name cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Add country to database
        $result = $this->add_country($country);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('Country added successfully', 'vandel-booking'),
            'country' => $country
        ]);
    }
    
    /**
     * AJAX handler for adding a new city
     */
    public function ajax_add_city() {
        // Verify nonce
        check_ajax_referer('vandel_location_service', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'vandel-booking')]);
            return;
        }
        
        // Get country and city
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        
        if (empty($country) || empty($city)) {
            wp_send_json_error(['message' => __('Country and city cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Add city to database
        $result = $this->add_city($country, $city);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        wp_send_json_success([
            'message' => __('City added successfully', 'vandel-booking'),
            'country' => $country,
            'city' => $city
        ]);
    }
    
    /**
     * AJAX handler for toggling location status
     */
    public function ajax_toggle_location() {
        // Verify nonce
        check_ajax_referer('vandel_location_service', 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'vandel-booking')]);
            return;
        }
        
        // Get parameters
        $type = isset($_POST['type']) ? sanitize_key($_POST['type']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';
        $active = isset($_POST['active']) ? (bool)$_POST['active'] : false;
        
        if (empty($type) || empty($value)) {
            wp_send_json_error(['message' => __('Missing required parameters', 'vandel-booking')]);
            return;
        }
        
        // Handle different toggle types
        switch ($type) {
            case 'country':
                $this->toggle_country($value, $active);
                break;
                
            case 'city':
                $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
                if (empty($country)) {
                    wp_send_json_error(['message' => __('Country is required for city toggle', 'vandel-booking')]);
                    return;
                }
                $this->toggle_city($country, $value, $active);
                break;
                
            case 'zipcode':
                $this->toggle_zipcode($value, $active);
                break;
                
            default:
                wp_send_json_error(['message' => __('Invalid toggle type', 'vandel-booking')]);
                return;
        }
        
        wp_send_json_success([
            'message' => __('Status updated successfully', 'vandel-booking')
        ]);
    }
    
/**
 * AJAX handler for importing ZIP codes
 */
public function ajax_import_zipcodes() {
    // Verify nonce
    check_ajax_referer('vandel_location_service', 'nonce');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'vandel-booking')]);
        return;
    }
    
    // Get parameters
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
    
    if (empty($country) || empty($city)) {
        wp_send_json_error(['message' => __('Country and city are required', 'vandel-booking')]);
        return;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['zipcode_file']) || empty($_FILES['zipcode_file']['tmp_name'])) {
        wp_send_json_error(['message' => __('No file uploaded', 'vandel-booking')]);
        return;
    }
    
    // Process the uploaded file
    $file = $_FILES['zipcode_file']['tmp_name'];
    
    // Read file content
    $content = file_get_contents($file);
    if (!$content) {
        wp_send_json_error(['message' => __('Failed to read file', 'vandel-booking')]);
        return;
    }
    
    // Parse CSV data
    $rows = array_map('str_getcsv', explode("\n", $content));
    
    // Remove empty rows
    $rows = array_filter($rows, function($row) {
        return !empty($row[0]) && $row[0] !== '';
    });
    
    // Check if first row is a header
    $has_header = false;
    if (count($rows) > 0) {
        $first_row = $rows[0];
        if (count($first_row) >= 1 && !is_numeric($first_row[0])) {
            $has_header = true;
            array_shift($rows);
        }
    }
    
    // Import ZIP codes
    $stats = [
        'total' => count($rows),
        'imported' => 0,
        'updated' => 0,
        'failed' => 0
    ];
    
    // Begin transaction
    global $wpdb;
    $wpdb->query('START TRANSACTION');
    
    try {
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0])) {
                continue;
            }
            
            // Extract data
            $zip_code = trim($row[0]);
            $area_name = isset($row[1]) && !empty($row[1]) ? trim($row[1]) : $city;
            $price_adjustment = isset($row[2]) && is_numeric($row[2]) ? floatval($row[2]) : 0;
            $service_fee = isset($row[3]) && is_numeric($row[3]) ? floatval($row[3]) : 0;
            
            // Check if ZIP code already exists
            $existing = $this->get_location_by_zipcode($zip_code);
            
            if ($existing) {
                // Update existing record
                $update = $this->update_location($existing->id, [
                    'country' => $country,
                    'city' => $city,
                    'area_name' => $area_name,
                    'price_adjustment' => $price_adjustment,
                    'service_fee' => $service_fee
                ]);
                
                if ($update) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            } else {
                // Add new record
                $result = $this->add_location([
                    'country' => $country,
                    'city' => $city,
                    'area_name' => $area_name,
                    'zip_code' => $zip_code,
                    'price_adjustment' => $price_adjustment,
                    'service_fee' => $service_fee
                ]);
                
                if ($result) {
                    $stats['imported']++;
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        // Commit transaction if everything is successful
        $wpdb->query('COMMIT');
        
        wp_send_json_success([
            'message' => sprintf(
                __('Successfully imported %d ZIP codes for %s, %s', 'vandel-booking'),
                $stats['imported'] + $stats['updated'],
                $city,
                $country
            ),
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        // Rollback transaction if an error occurred
        $wpdb->query('ROLLBACK');
        
        wp_send_json_error([
            'message' => __('Error importing ZIP codes: ', 'vandel-booking') . $e->getMessage()
        ]);
    }
}
    

/**
 * AJAX handler for updating a ZIP code
 */
public function ajax_update_zipcode() {
    // Verify nonce
    check_ajax_referer('vandel_location_service', 'nonce');
    
    // Check permissions
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Permission denied', 'vandel-booking')]);
        return;
    }
    
    // Get parameters
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $area_name = isset($_POST['area_name']) ? sanitize_text_field($_POST['area_name']) : '';
    $price_adjustment = isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0;
    $service_fee = isset($_POST['service_fee']) ? floatval($_POST['service_fee']) : 0;
    $is_active = isset($_POST['is_active']) ? ($_POST['is_active'] ? 'yes' : 'no') : 'yes';
    
    if ($id <= 0) {
        wp_send_json_error(['message' => __('Invalid ZIP code ID', 'vandel-booking')]);
        return;
    }
    
    // Update ZIP code
    $update_data = [];
    
    if (!empty($area_name)) {
        $update_data['area_name'] = $area_name;
    }
    
    $update_data['price_adjustment'] = $price_adjustment;
    $update_data['service_fee'] = $service_fee;
    $update_data['is_active'] = $is_active;
    
    $result = $this->update_location($id, $update_data);
    
    if ($result) {
        wp_send_json_success([
            'message' => __('ZIP code updated successfully', 'vandel-booking')
        ]);
    } else {
        wp_send_json_error([
            'message' => __('Failed to update ZIP code', 'vandel-booking')
        ]);
    }
}

    /**
     * AJAX handler for getting cities for a country
     */
    public function ajax_get_cities() {
        // Verify nonce
        if (!check_ajax_referer('vandel_location_service', 'nonce', false) && 
            !check_ajax_referer('vandel_booking_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Get country
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        
        if (empty($country)) {
            wp_send_json_error(['message' => __('Country cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Get cities
        $cities = $this->get_cities($country);
        
        wp_send_json_success($cities);
    }
    
    /**
     * AJAX handler for getting ZIP codes for a city
     */
    public function ajax_get_zipcodes() {
        // Verify nonce
        if (!check_ajax_referer('vandel_location_service', 'nonce', false) && 
            !check_ajax_referer('vandel_booking_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Get country and city
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $city = isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '';
        
        if (empty($country) || empty($city)) {
            wp_send_json_error(['message' => __('Country and city cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Get ZIP codes
        $zipcodes = $this->get_zipcodes($country, $city);
        
        wp_send_json_success($zipcodes);
    }
    
    /**
     * AJAX handler for validating a location (ZIP code lookup)
     */
    public function ajax_validate_location() {
        // Verify nonce
        if (!check_ajax_referer('vandel_location_service', 'nonce', false) && 
            !check_ajax_referer('vandel_booking_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Get ZIP code
        $zip_code = isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '';
        
        if (empty($zip_code)) {
            wp_send_json_error(['message' => __('ZIP code cannot be empty', 'vandel-booking')]);
            return;
        }
        
        // Validate location
        $location = $this->get_location_by_zipcode($zip_code);
        
        if (!$location) {
            wp_send_json_error(['message' => __('ZIP code not found or not available', 'vandel-booking')]);
            return;
        }
        
        // Check if location is active
        if ($location->is_active !== 'yes') {
            wp_send_json_error(['message' => __('This location is not available for service', 'vandel-booking')]);
            return;
        }
        
        // Return location details
        wp_send_json_success([
            'id' => $location->id,
            'country' => $location->country,
            'city' => $location->city,
            'area_name' => $location->area_name,
            'zip_code' => $location->zip_code,
            'price_adjustment' => floatval($location->price_adjustment),
            'service_fee' => floatval($location->service_fee)
        ]);
    }
    
    /**
     * Get all countries from the database
     * 
     * @return array List of countries
     */
    public function get_countries() {
        if (!empty($this->countries_cache)) {
            return $this->countries_cache;
        }
        
        global $wpdb;
        
        $countries = $wpdb->get_col(
            "SELECT DISTINCT country FROM {$this->table} ORDER BY country ASC"
        );
        
        $this->countries_cache = $countries ?: [];
        return $this->countries_cache;
    }
    
    /**
     * Get cities for a country
     * 
     * @param string $country Country name
     * @return array List of cities
     */
    public function get_cities($country) {
        if (isset($this->cities_cache[$country])) {
            return $this->cities_cache[$country];
        }
        
        global $wpdb;
        
        $cities = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT city FROM {$this->table} WHERE country = %s ORDER BY city ASC",
            $country
        ));
        
        $this->cities_cache[$country] = $cities ?: [];
        return $this->cities_cache[$country];
    }
    
    /**
     * Get ZIP codes for a city
     * 
     * @param string $country Country name
     * @param string $city City name
     * @return array List of ZIP codes with details
     */
    public function get_zipcodes($country, $city) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, zip_code, area_name, price_adjustment, service_fee, is_active 
             FROM {$this->table} 
             WHERE country = %s AND city = %s 
             ORDER BY zip_code ASC",
            $country,
            $city
        ));
    }
    
/**
     * Get a location by ZIP code
     * 
     * @param string $zip_code ZIP code to look up
     * @return object|false Location object or false if not found
     */
    public function get_location_by_zipcode($zip_code) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE zip_code = %s",
            $zip_code
        ));
    }
    
    /**
     * Add a new country
     * 
     * @param string $country Country name
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function add_country($country) {
        // Check if country already exists
        $countries = $this->get_countries();
        if (in_array($country, $countries)) {
            return new \WP_Error('country_exists', __('Country already exists', 'vandel-booking'));
        }
        
        // Add a placeholder city and ZIP code to establish the country
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table,
            [
                'country' => $country,
                'city' => 'Sample City',
                'area_name' => 'Sample Area',
                'zip_code' => 'SAMPLE-' . strtoupper(substr(md5($country), 0, 6)),
                'price_adjustment' => 0,
                'service_fee' => 0,
                'is_active' => 'yes',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to add country', 'vandel-booking'));
        }
        
        // Clear cache
        $this->countries_cache = [];
        
        return true;
    }
    
    /**
     * Add a new city to a country
     * 
     * @param string $country Country name
     * @param string $city City name
     * @return bool|WP_Error True on success or WP_Error on failure
     */
    public function add_city($country, $city) {
        // Check if country exists
        $countries = $this->get_countries();
        if (!in_array($country, $countries)) {
            return new \WP_Error('country_not_found', __('Country not found', 'vandel-booking'));
        }
        
        // Check if city already exists in this country
        $cities = $this->get_cities($country);
        if (in_array($city, $cities)) {
            return new \WP_Error('city_exists', __('City already exists for this country', 'vandel-booking'));
        }
        
        // Add a placeholder ZIP code for this city
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table,
            [
                'country' => $country,
                'city' => $city,
                'area_name' => 'Sample Area',
                'zip_code' => 'SAMPLE-' . strtoupper(substr(md5($country . $city), 0, 6)),
                'price_adjustment' => 0,
                'service_fee' => 0,
                'is_active' => 'yes',
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            return new \WP_Error('db_error', __('Failed to add city', 'vandel-booking'));
        }
        
        // Clear cities cache
        unset($this->cities_cache[$country]);
        
        return true;
    }
    
    /**
     * Add a new location (ZIP code)
     * 
     * @param array $location Location data
     * @return int|false Location ID or false on failure
     */
    public function add_location($location) {
        // Set defaults
        $defaults = [
            'price_adjustment' => 0,
            'service_fee' => 0,
            'is_active' => 'yes',
            'created_at' => current_time('mysql')
        ];
        
        $location = wp_parse_args($location, $defaults);
        
        // Ensure required fields
        if (empty($location['country']) || empty($location['city']) || 
            empty($location['area_name']) || empty($location['zip_code'])) {
            return false;
        }
        
        // Check if ZIP code already exists
        $existing = $this->get_location_by_zipcode($location['zip_code']);
        if ($existing) {
            // Update existing record
            return $this->update_location($existing->id, $location);
        }
        
        // Insert new record
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->table,
            [
                'country' => $location['country'],
                'city' => $location['city'],
                'area_name' => $location['area_name'],
                'zip_code' => $location['zip_code'],
                'price_adjustment' => floatval($location['price_adjustment']),
                'service_fee' => floatval($location['service_fee']),
                'is_active' => $location['is_active'],
                'created_at' => $location['created_at']
            ],
            ['%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s']
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update an existing location
     * 
     * @param int $id Location ID
     * @param array $location Location data
     * @return bool Whether the update was successful
     */
    public function update_location($id, $location) {
        global $wpdb;
        
        // Prepare update data
        $update_data = [];
        $format = [];
        
        if (isset($location['country'])) {
            $update_data['country'] = $location['country'];
            $format[] = '%s';
        }
        
        if (isset($location['city'])) {
            $update_data['city'] = $location['city'];
            $format[] = '%s';
        }
        
        if (isset($location['area_name'])) {
            $update_data['area_name'] = $location['area_name'];
            $format[] = '%s';
        }
        
        if (isset($location['zip_code'])) {
            $update_data['zip_code'] = $location['zip_code'];
            $format[] = '%s';
        }
        
        if (isset($location['price_adjustment'])) {
            $update_data['price_adjustment'] = floatval($location['price_adjustment']);
            $format[] = '%f';
        }
        
        if (isset($location['service_fee'])) {
            $update_data['service_fee'] = floatval($location['service_fee']);
            $format[] = '%f';
        }
        
        if (isset($location['is_active'])) {
            $update_data['is_active'] = $location['is_active'];
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        // Update record
        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a location
     * 
     * @param int $id Location ID
     * @return bool Whether the deletion was successful
     */
    public function delete_location($id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Toggle country status
     * 
     * @param string $country Country name
     * @param bool $active Whether the country should be active
     * @return bool Success status
     */
    public function toggle_country($country, $active) {
        // Get enabled countries
        $enabled_countries = get_option('vandel_enabled_countries', []);
        
        if ($active) {
            // Add country if not already enabled
            if (!in_array($country, $enabled_countries)) {
                $enabled_countries[] = $country;
            }
        } else {
            // Remove country if enabled
            $enabled_countries = array_diff($enabled_countries, [$country]);
            
            // Also remove cities for this country
            $enabled_cities = get_option('vandel_enabled_cities', []);
            if (isset($enabled_cities[$country])) {
                unset($enabled_cities[$country]);
                update_option('vandel_enabled_cities', $enabled_cities);
            }
        }
        
        // Update option
        return update_option('vandel_enabled_countries', $enabled_countries);
    }
    
    /**
     * Toggle city status
     * 
     * @param string $country Country name
     * @param string $city City name
     * @param bool $active Whether the city should be active
     * @return bool Success status
     */
    public function toggle_city($country, $city, $active) {
        // Get enabled cities
        $enabled_cities = get_option('vandel_enabled_cities', []);
        
        if (!isset($enabled_cities[$country])) {
            $enabled_cities[$country] = [];
        }
        
        if ($active) {
            // Add city if not already enabled
            if (!in_array($city, $enabled_cities[$country])) {
                $enabled_cities[$country][] = $city;
            }
        } else {
            // Remove city if enabled
            $enabled_cities[$country] = array_diff($enabled_cities[$country], [$city]);
            
            // Remove empty country array
            if (empty($enabled_cities[$country])) {
                unset($enabled_cities[$country]);
            }
        }
        
        // Update option
        return update_option('vandel_enabled_cities', $enabled_cities);
    }
    
    /**
     * Toggle ZIP code status
     * 
     * @param string $zip_code ZIP code
     * @param bool $active Whether the ZIP code should be active
     * @return bool Success status
     */
    public function toggle_zipcode($zip_code, $active) {
        // Get location
        $location = $this->get_location_by_zipcode($zip_code);
        
        if (!$location) {
            return false;
        }
        
        // Update active status
        return $this->update_location($location->id, [
            'is_active' => $active ? 'yes' : 'no'
        ]);
    }
    
    /**
     * Import ZIP codes from a file
     * 
     * @param string $country Country name
     * @param string $city City name
     * @param string $file Path to the file
     * @return array|WP_Error Import statistics or error
     */
    public function import_zipcodes_from_file($country, $city, $file) {
        if (!file_exists($file)) {
            return new \WP_Error('file_not_found', __('File not found', 'vandel-booking'));
        }
        
        // Read file content
        $content = file_get_contents($file);
        if (!$content) {
            return new \WP_Error('file_read_error', __('Failed to read file', 'vandel-booking'));
        }
        
        // Parse CSV data
        $rows = array_map('str_getcsv', explode("\n", $content));
        
        // Remove empty rows
        $rows = array_filter($rows, function($row) {
            return !empty($row[0]) && $row[0] !== '';
        });
        
        // Check if first row is a header
        $has_header = false;
        if (count($rows) > 0) {
            $first_row = $rows[0];
            if (count($first_row) >= 1 && !is_numeric($first_row[0])) {
                $has_header = true;
                array_shift($rows);
            }
        }
        
        // Import ZIP codes
        $stats = [
            'total' => count($rows),
            'imported' => 0,
            'updated' => 0,
            'failed' => 0
        ];
        
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty($row[0])) {
                continue;
            }
            
            // Extract data
            $zip_code = trim($row[0]);
            $area_name = isset($row[1]) && !empty($row[1]) ? trim($row[1]) : $city;
            $price_adjustment = isset($row[2]) && is_numeric($row[2]) ? floatval($row[2]) : 0;
            $service_fee = isset($row[3]) && is_numeric($row[3]) ? floatval($row[3]) : 0;
            
            // Check if ZIP code already exists
            $existing = $this->get_location_by_zipcode($zip_code);
            
            if ($existing) {
                // Update existing record
                $update = $this->update_location($existing->id, [
                    'country' => $country,
                    'city' => $city,
                    'area_name' => $area_name,
                    'price_adjustment' => $price_adjustment,
                    'service_fee' => $service_fee
                ]);
                
                if ($update) {
                    $stats['updated']++;
                } else {
                    $stats['failed']++;
                }
            } else {
                // Add new record
                $result = $this->add_location([
                    'country' => $country,
                    'city' => $city,
                    'area_name' => $area_name,
                    'zip_code' => $zip_code,
                    'price_adjustment' => $price_adjustment,
                    'service_fee' => $service_fee
                ]);
                
                if ($result) {
                    $stats['imported']++;
                } else {
                    $stats['failed']++;
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Generate sample ZIP codes file for download
     * 
     * @param string $country Country name
     * @return string|WP_Error Path to the generated file or error
     */
    public function generate_sample_file($country) {
        // Define sample data based on country
        $samples = [];
        
        switch ($country) {
            case 'Sweden':
                $samples = [
                    ['11152', 'Norrmalm', '10', '5'],
                    ['11346', 'Östermalm', '15', '5'],
                    ['11553', 'Södermalm', '5', '5'],
                    ['16440', 'Kista', '0', '10'],
                    ['41115', 'Centrum', '10', '5']
                ];
                break;
                
            case 'USA':
                $samples = [
                    ['10001', 'Midtown', '15', '10'],
                    ['10002', 'Lower East Side', '10', '10'],
                    ['10003', 'East Village', '12', '10'],
                    ['10004', 'Financial District', '20', '10'],
                    ['10005', 'Wall Street', '25', '10']
                ];
                break;
                
            default:
                $samples = [
                    ['12345', 'Area 1', '5', '5'],
                    ['23456', 'Area 2', '10', '5'],
                    ['34567', 'Area 3', '15', '5'],
                    ['45678', 'Area 4', '0', '10'],
                    ['56789', 'Area 5', '-5', '5']
                ];
        }
        
        // Create temporary file
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/vandel_' . sanitize_file_name($country) . '_sample.csv';
        
        // Write CSV content
        $file = fopen($file_path, 'w');
        
        if (!$file) {
            return new \WP_Error('file_create_error', __('Failed to create sample file', 'vandel-booking'));
        }
        
        // Add header
        fputcsv($file, ['ZIP Code', 'Area Name', 'Price Adjustment', 'Service Fee']);
        
        // Add sample data
        foreach ($samples as $sample) {
            fputcsv($file, $sample);
        }
        
        fclose($file);
        
        return $file_path;
    }
    
    /**
     * Add locations data to booking form
     * 
     * @param array $data Booking form data
     * @return array Modified data with locations
     */
    public function add_locations_to_booking_form($data) {
        // Get enabled countries and cities
        $enabled_countries = get_option('vandel_enabled_countries', []);
        $enabled_cities = get_option('vandel_enabled_cities', []);
        
        // Add location data
        $data['location'] = [
            'countries' => $enabled_countries,
            'cities' => $enabled_cities,
            'strings' => [
                'selectCountry' => __('Select Country', 'vandel-booking'),
                'selectCity' => __('Select City', 'vandel-booking'),
                'enterZip' => __('Enter ZIP Code', 'vandel-booking'),
                'loadingCities' => __('Loading cities...', 'vandel-booking'),
                'noLocations' => __('No locations available', 'vandel-booking')
            ]
        ];
        
        return $data;
    }
}