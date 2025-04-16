<?php
namespace VandelBooking\Admin\Dashboard\Settings;

use VandelBooking\Location\LocationModel;
use VandelBooking\Helpers;

/**
 * Location Settings
 * Handles the location management in settings
 */
class Location_Settings {
    /**
     * @var LocationModel Location Model instance
     */
    private $location_model;

    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('\\VandelBooking\\Location\\LocationModel')) {
            $this->location_model = new LocationModel();
        }

        // Hook to admin_enqueue_scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Process actions for locations
     */
    public function process_actions() {
        // Check if we're on the correct page and have necessary permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Debug log
        error_log('Location settings process_actions called. POST data: ' . print_r($_POST, true));

        // Check for Location add action
        if (isset($_POST['vandel_add_location']) && 
            isset($_POST['vandel_location_nonce']) && 
            wp_verify_nonce($_POST['vandel_location_nonce'], 'vandel_add_location')) {
            
            // Log the form submission
            error_log('Processing location form submission with nonce verification passed');
            
            $this->add_location($_POST);
        }

        // Check for Location delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete_location' && 
            isset($_GET['location_id']) && isset($_GET['_wpnonce'])) {
            
            $location_id = intval($_GET['location_id']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_location_' . $location_id)) {
                $this->delete_location($location_id);
            }
        }

        // Check for Location update action
        if (isset($_POST['vandel_update_location']) && 
            isset($_POST['vandel_location_update_nonce']) && 
            wp_verify_nonce($_POST['vandel_location_update_nonce'], 'vandel_update_location')) {
            
            $this->update_location($_POST);
        }

        // Check for Initialize Sweden action
        if (isset($_POST['vandel_initialize_sweden']) && 
            isset($_POST['vandel_initialize_sweden_nonce']) && 
            wp_verify_nonce($_POST['vandel_initialize_sweden_nonce'], 'vandel_initialize_sweden')) {
            
            $this->initialize_sweden();
        }
    }

    /**
     * Enqueue Location admin scripts and styles
     */
    public function enqueue_assets() {
        // Only load on the Location settings page
        if (!is_admin() || 
            !isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'settings' ||
            !isset($_GET['section']) || $_GET['section'] !== 'locations') {
            return;
        }
        
        // Enqueue the Location admin script
        wp_enqueue_script(
            'vandel-location-admin',
            VANDEL_PLUGIN_URL . 'assets/js/admin/location-admin.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Localize the script with necessary data
        wp_localize_script(
            'vandel-location-admin',
            'vandelLocationAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_location_nonce'),
                'confirmDelete' => __('Are you sure you want to delete this location?', 'vandel-booking'),
                'strings' => [
                    'importSuccess' => __('Locations imported successfully.', 'vandel-booking'),
                    'importError' => __('Error importing locations.', 'vandel-booking'),
                    'selectFile' => __('Please select a file to import.', 'vandel-booking'),
                    'selectCountry' => __('Please select a country', 'vandel-booking'),
                    'selectCity' => __('Please select a city', 'vandel-booking'),
                    'loadingAreas' => __('Loading areas...', 'vandel-booking')
                ]
            ]
        );
    }

    /**
     * Add new Location
     * 
     * @param array $data Form data
     */
    private function add_location($data) {
        if (!$this->location_model) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_model_missing', 
                __('Location functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }

        $location_data = [
            'country' => sanitize_text_field($data['country']),
            'city' => sanitize_text_field($data['city']),
            'area_name' => sanitize_text_field($data['area_name']),
            'zip_code' => sanitize_text_field($data['zip_code']),
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee' => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_active' => isset($data['is_active']) ? 'yes' : 'no'
        ];

        $result = $this->location_model->add($location_data);

        if ($result) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_added', 
                __('Location added successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_error', 
                __('Failed to add location. It may already exist.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Delete Location
     * 
     * @param int $location_id Location ID to delete
     */
    private function delete_location($location_id) {
        if (!$this->location_model) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_model_missing', 
                __('Location functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }

        $result = $this->location_model->delete($location_id);

        if ($result) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_deleted', 
                __('Location deleted successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_delete_error', 
                __('Failed to delete location.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Update existing Location
     * 
     * @param array $data Location data
     */
    private function update_location($data) {
        if (!$this->location_model) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_model_missing', 
                __('Location functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }
        
        $location_id = intval($data['location_id']);
        $location_data = [
            'country' => sanitize_text_field($data['country']),
            'city' => sanitize_text_field($data['city']),
            'area_name' => sanitize_text_field($data['area_name']),
            'zip_code' => sanitize_text_field($data['zip_code']),
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee' => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_active' => isset($data['is_active']) ? 'yes' : 'no'
        ];
        
        $result = $this->location_model->update($location_id, $location_data);
        
        if ($result) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_updated', 
                __('Location updated successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_error', 
                __('Failed to update location.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Initialize Sweden locations
     */
    private function initialize_sweden() {
        if (!$this->location_model) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_location_model_missing', 
                __('Location functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }

        $result = $this->location_model->initializeSweden();

        if ($result) {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_sweden_initialized', 
                __('Sweden locations initialized successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_location_messages', 
                'vandel_sweden_init_error', 
                __('Failed to initialize Sweden locations or they already exist.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Render settings page
     */
    public function render() {
        // Display settings errors
        settings_errors('vandel_location_messages');
        
        // Get current page
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 15;
        $offset = ($page - 1) * $per_page;
        
        // Get countries for dropdown
        $countries = ['Sweden' => 'Sweden']; // For now, just Sweden
        $cities = [];
        
        // Get existing records for the table
        global $wpdb;
        $table_name = $wpdb->prefix . 'vandel_locations';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        $locations = [];
        $total_locations = 0;
        $total_pages = 1;
        
        if ($table_exists) {
            $locations = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY country, city, area_name ASC LIMIT %d OFFSET %d",
                $per_page, $offset
            ));
            
            $total_locations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $total_pages = ceil($total_locations / $per_page);
            
            // Get cities for selected country (assuming Sweden for now)
            if ($this->location_model) {
                $cities_array = $this->location_model->getCities('Sweden');
                foreach ($cities_array as $city) {
                    $cities[$city] = $city;
                }
            }
        }
        
        ?>
<div class="vandel-settings-section">
    <h2><?php _e('Location Management', 'vandel-booking'); ?></h2>

    <div class="vandel-settings-intro">
        <p><?php _e('Manage your service locations with area-based pricing. Set up countries, cities, and specific areas with ZIP codes.', 'vandel-booking'); ?>
        </p>
    </div>

    <div class="vandel-grid-row">
        <div class="vandel-grid-col">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Add New Location', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <form method="post" action="">
                        <?php wp_nonce_field('vandel_add_location', 'vandel_location_nonce'); ?>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="country"><?php _e('Country', 'vandel-booking'); ?> <span
                                        class="required">*</span></label>
                                <select name="country" id="country" required class="widefat">
                                    <?php _e('Select Country', 'vandel-booking'); ?></option>
                                    <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="vandel-col">
                                <label for="city"><?php _e('City', 'vandel-booking'); ?> <span
                                        class="required">*</span></label>
                                <select name="city" id="city" required class="widefat">
                                    <option value=""><?php _e('Select City', 'vandel-booking'); ?></option>
                                    <?php foreach ($cities as $code => $name): ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="area_name"><?php _e('Area Name', 'vandel-booking'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" name="area_name" id="area_name" required class="widefat">
                            </div>
                            <div class="vandel-col">
                                <label for="zip_code"><?php _e('ZIP Code', 'vandel-booking'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" name="zip_code" id="zip_code" required class="widefat">
                            </div>
                        </div>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label for="price_adjustment"><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span
                                        class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="price_adjustment" id="price_adjustment" step="0.01"
                                        min="-100" max="100" class="widefat" value="0">
                                </div>
                                <p class="description">
                                    <?php _e('Amount to add or subtract from base price for this area', 'vandel-booking'); ?>
                                </p>
                            </div>
                            <div class="vandel-col">
                                <label for="service_fee"><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span
                                        class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="service_fee" id="service_fee" step="0.01" min="0"
                                        class="widefat" value="0">
                                </div>
                                <p class="description">
                                    <?php _e('Additional fee for servicing this area', 'vandel-booking'); ?></p>
                            </div>
                        </div>
                        <div class="vandel-toggle-controls">
                            <div class="vandel-toggle-field">
                                <label class="vandel-toggle">
                                    <input type="checkbox" name="is_active" value="yes" checked>
                                    <span class="vandel-toggle-slider"></span>
                                </label>
                                <span class="vandel-toggle-label"><?php _e('Active Area', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        <button type="submit" name="vandel_add_location" class="button button-primary">
                            <?php _e('Add Location', 'vandel-booking'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Initialize Section -->
        <div class="vandel-grid-col">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Quick Initialize', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <p><?php _e('Initialize the database with predefined locations for Sweden.', 'vandel-booking'); ?>
                    </p>

                    <form method="post" action="">
                        <?php wp_nonce_field('vandel_initialize_sweden', 'vandel_initialize_sweden_nonce'); ?>
                        <button type="submit" name="vandel_initialize_sweden" class="button button-secondary">
                            <span class="dashicons dashicons-database-add"></span>
                            <?php _e('Initialize Sweden Locations', 'vandel-booking'); ?>
                        </button>
                    </form>

                    <div class="vandel-divider"></div>

                    <div class="vandel-import-section">
                        <h4><?php _e('Import Locations', 'vandel-booking'); ?></h4>
                        <p><?php _e('Upload a CSV file with location data to bulk import.', 'vandel-booking'); ?></p>
                        <form method="post" enctype="multipart/form-data">
                            <div class="vandel-form-row">
                                <div class="vandel-col">
                                    <input type="file" name="locations_file" id="vandel-locations-file"
                                        accept=".csv,.xlsx,.xls">
                                </div>
                                <div class="vandel-col">
                                    <button type="button" id="vandel-import-locations" class="button button-secondary">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php _e('Import', 'vandel-booking'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <p class="description">
                            <?php _e('CSV format: Country, City, Area Name, ZIP Code, Price Adjustment, Service Fee, Active (yes/no)', 'vandel-booking'); ?>
                        </p>
                    </div>

                    <div class="vandel-export-section">
                        <h4><?php _e('Export Locations', 'vandel-booking'); ?></h4>
                        <p><?php _e('Download all your locations as a CSV file.', 'vandel-booking'); ?></p>
                        <button type="button" id="vandel-export-locations" class="button button-secondary">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e('Export CSV', 'vandel-booking'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="vandel-card">
        <div class="vandel-card-header vandel-flex-header">
            <h3><?php _e('Your Locations', 'vandel-booking'); ?></h3>
            <div class="vandel-filter-controls">
                <input type="text" id="vandel-location-search"
                    placeholder="<?php _e('Search locations...', 'vandel-booking'); ?>" class="regular-text">
            </div>
        </div>
        <div class="vandel-card-body">
            <table class="wp-list-table widefat fixed striped vandel-data-table">
                <thead>
                    <tr>
                        <th><?php _e('Country', 'vandel-booking'); ?></th>
                        <th><?php _e('City', 'vandel-booking'); ?></th>
                        <th><?php _e('Area Name', 'vandel-booking'); ?></th>
                        <th><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                        <th><?php _e('Price Adjustment', 'vandel-booking'); ?></th>
                        <th><?php _e('Service Fee', 'vandel-booking'); ?></th>
                        <th><?php _e('Status', 'vandel-booking'); ?></th>
                        <th><?php _e('Actions', 'vandel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <?php _e('No locations found. Add your first location above or use Quick Initialize.', 'vandel-booking'); ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($locations as $location): ?>
                    <tr>
                        <td><?php echo esc_html($location->country); ?></td>
                        <td><?php echo esc_html($location->city); ?></td>
                        <td><?php echo esc_html($location->area_name); ?></td>
                        <td><?php echo esc_html($location->zip_code); ?></td>
                        <td>
                            <?php 
                                            $price_adj = floatval($location->price_adjustment);
                                            echo $price_adj > 0 ? '+' : '';
                                            echo Helpers::formatPrice($price_adj);
                                            ?>
                        </td>
                        <td><?php echo Helpers::formatPrice($location->service_fee); ?></td>
                        <td>
                            <?php if ($location->is_active === 'yes'): ?>
                            <span
                                class="vandel-badge vandel-badge-success"><?php _e('Active', 'vandel-booking'); ?></span>
                            <?php else: ?>
                            <span
                                class="vandel-badge vandel-badge-danger"><?php _e('Inactive', 'vandel-booking'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="vandel-row-actions">
                                <a href="#" class="vandel-edit-location"
                                    data-id="<?php echo esc_attr($location->id); ?>"
                                    data-country="<?php echo esc_attr($location->country); ?>"
                                    data-city="<?php echo esc_attr($location->city); ?>"
                                    data-area-name="<?php echo esc_attr($location->area_name); ?>"
                                    data-zip-code="<?php echo esc_attr($location->zip_code); ?>"
                                    data-price-adjustment="<?php echo esc_attr($location->price_adjustment); ?>"
                                    data-service-fee="<?php echo esc_attr($location->service_fee); ?>"
                                    data-is-active="<?php echo esc_attr($location->is_active); ?>">
                                    <?php _e('Edit', 'vandel-booking'); ?>
                                </a>
                                <a href="<?php echo wp_nonce_url(
                                                    admin_url('admin.php?page=vandel-dashboard&tab=settings&section=locations&action=delete_location&location_id=' . urlencode($location->id)),
                                                    'delete_location_' . $location->id
                                                ); ?>" class="vandel-delete-location">
                                    <?php _e('Delete', 'vandel-booking'); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                                $page_links = paginate_links([
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;', 'vandel-booking'),
                                    'next_text' => __('&raquo;', 'vandel-booking'),
                                    'total' => $total_pages,
                                    'current' => $page,
                                    'type' => 'array'
                                ]);

                                if ($page_links) {
                                    echo '<span class="pagination-links">';
                                    echo implode(' ', $page_links);
                                    echo '</span>';
                                }
                                ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Location Modal -->
    <div id="vandel-edit-location-modal" class="vandel-modal" style="display:none;">
        <div class="vandel-modal-content">
            <span class="vandel-modal-close">&times;</span>
            <h3><?php _e('Edit Location', 'vandel-booking'); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('vandel_update_location', 'vandel_location_update_nonce'); ?>
                <input type="hidden" name="location_id" id="edit-location-id">

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="edit-country"><?php _e('Country', 'vandel-booking'); ?></label>
                        <select name="country" id="edit-country" required class="widefat">
                            <?php foreach ($countries as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="vandel-col">
                        <label for="edit-city"><?php _e('City', 'vandel-booking'); ?></label>
                        <select name="city" id="edit-city" required class="widefat">
                            <?php foreach ($cities as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="edit-area-name"><?php _e('Area Name', 'vandel-booking'); ?></label>
                        <input type="text" name="area_name" id="edit-area-name" required class="widefat">
                    </div>
                    <div class="vandel-col">
                        <label for="edit-zip-code"><?php _e('ZIP Code', 'vandel-booking'); ?></label>
                        <input type="text" name="zip_code" id="edit-zip-code" required class="widefat">
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label for="edit-price-adjustment"><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span
                                class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                            <input type="number" name="price_adjustment" id="edit-price-adjustment" step="0.01"
                                min="-100" max="100" class="widefat">
                        </div>
                    </div>
                    <div class="vandel-col">
                        <label for="edit-service-fee"><?php _e('Service Fee', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span
                                class="vandel-input-prefix"><?php echo \VandelBooking\Helpers::getCurrencySymbol(); ?></span>
                            <input type="number" name="service_fee" id="edit-service-fee" step="0.01" min="0"
                                class="widefat">
                        </div>
                    </div>
                </div>

                <div class="vandel-toggle-controls">
                    <div class="vandel-toggle-field">
                        <label class="vandel-toggle">
                            <input type="checkbox" name="is_active" id="edit-is-active" value="yes">
                            <span class="vandel-toggle-slider"></span>
                        </label>
                        <span class="vandel-toggle-label"><?php _e('Active Area', 'vandel-booking'); ?></span>
                    </div>
                </div>

                <button type="submit" name="vandel_update_location" class="button button-primary">
                    <?php _e('Update Location', 'vandel-booking'); ?>
                </button>
            </form>
        </div>
    </div>

    <style>
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
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 60%;
        max-width: 800px;
        position: relative;
        border-radius: 4px;
    }

    .vandel-modal-close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        position: absolute;
        right: 15px;
        top: 10px;
    }

    .vandel-modal-close:hover,
    .vandel-modal-close:focus {
        color: black;
        text-decoration: none;
    }

    .vandel-divider {
        margin: 20px 0;
        border-top: 1px solid #ddd;
    }
    </style>
</div>
<?php
    }
}