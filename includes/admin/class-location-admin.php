<?php
namespace VandelBooking\Admin;

/**
 * Location Admin
 * Provides admin interface for managing locations
 */
class LocationAdmin {
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
        
        // Add admin hooks
        add_action('admin_menu', [$this, 'addSubmenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Add submenu page
     */
    public function addSubmenuPage() {
        add_submenu_page(
            'vandel-dashboard',
            __('Location Management', 'vandel-booking'),
            __('Locations', 'vandel-booking'),
            'manage_options',
            'vandel-locations',
            [$this, 'renderPage']
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueueScripts($hook) {
        if ($hook !== 'vandel-dashboard_page_vandel-locations') {
            return;
        }
        
        wp_enqueue_style(
            'vandel-location-admin',
            VANDEL_PLUGIN_URL . 'assets/css/location-admin.css',
            [],
            VANDEL_VERSION
        );
        
        wp_enqueue_script(
            'vandel-location-admin',
            VANDEL_PLUGIN_URL . 'assets/js/location-admin.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        wp_localize_script(
            'vandel-location-admin',
            'vandelLocationAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_location_admin'),
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to delete this location?', 'vandel-booking'),
                    'saved' => __('Location saved successfully', 'vandel-booking'),
                    'deleted' => __('Location deleted successfully', 'vandel-booking'),
                    'error' => __('An error occurred', 'vandel-booking'),
                    'addLocation' => __('Add New Location', 'vandel-booking'),
                    'editLocation' => __('Edit Location', 'vandel-booking')
                ]
            ]
        );
    }
    
    /**
     * Render admin page
     */
    public function renderPage() {
        // Check if location model is available
        if (!$this->location_model) {
            echo '<div class="notice notice-error"><p>' . __('Location model not available', 'vandel-booking') . '</p></div>';
            return;
        }
        
        // Get countries
        $countries = $this->location_model->getCountries();
        
        // If no countries, add Sweden as default
        if (empty($countries)) {
            $this->location_model->initializeSweden();
            $countries = $this->location_model->getCountries();
        }
        
        // Get current filters
        $current_country = isset($_GET['country']) ? sanitize_text_field($_GET['country']) : '';
        $current_city = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
        $current_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Get cities for selected country
        $cities = [];
        if ($current_country) {
            $cities = $this->location_model->getCities($current_country);
        }
        
        // Get locations based on filters
        $locations = $this->location_model->getAll([
            'country' => $current_country,
            'city' => $current_city,
            'is_active' => $current_active,
            'search' => $search
        ]);
        
        ?>
        <div class="wrap vandel-locations-admin">
            <h1 class="wp-heading-inline"><?php _e('Location Management', 'vandel-booking'); ?></h1>
            <a href="#" class="page-title-action add-new-location"><?php _e('Add New Location', 'vandel-booking'); ?></a>
            <a href="#" class="page-title-action import-locations"><?php _e('Import Locations', 'vandel-booking'); ?></a>
            
            <!-- Filters -->
            <div class="vandel-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="vandel-locations">
                    
                    <select name="country" id="filter-country">
                        <option value=""><?php _e('All Countries', 'vandel-booking'); ?></option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($current_country, $country); ?>><?php echo esc_html($country); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="city" id="filter-city" <?php echo empty($current_country) ? 'disabled' : ''; ?>>
                        <option value=""><?php _e('All Cities', 'vandel-booking'); ?></option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?php echo esc_attr($city); ?>" <?php selected($current_city, $city); ?>><?php echo esc_html($city); ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="is_active">
                        <option value=""><?php _e('All Status', 'vandel-booking'); ?></option>
                        <option value="yes" <?php selected($current_active, 'yes'); ?>><?php _e('Active', 'vandel-booking'); ?></option>
                        <option value="no" <?php selected($current_active, 'no'); ?>><?php _e('Inactive', 'vandel-booking'); ?></option>
                    </select>
                    
                    <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search locations...', 'vandel-booking'); ?>">
                    
                    <button type="submit" class="button"><?php _e('Filter', 'vandel-booking'); ?></button>
                </form>
            </div>
            
            <!-- Locations Table -->
            <table class="wp-list-table widefat fixed striped vandel-locations-table">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Country', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('City', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('Area', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('Price Adjustment', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('Service Fee', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('Status', 'vandel-booking'); ?></th>
                        <th scope="col"><?php _e('Actions', 'vandel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr>
                            <td colspan="8"><?php _e('No locations found.', 'vandel-booking'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($locations as $location): ?>
                            <tr>
                                <td><?php echo esc_html($location->country); ?></td>
                                <td><?php echo esc_html($location->city); ?></td>
                                <td><?php echo esc_html($location->area_name); ?></td>
                                <td><?php echo esc_html($location->zip_code); ?></td>
                                <td><?php echo \VandelBooking\Helpers::formatPrice($location->price_adjustment); ?></td>
                                <td><?php echo \VandelBooking\Helpers::formatPrice($location->service_fee); ?></td>
                                <td>
                                    <span class="vandel-status-badge <?php echo $location->is_active === 'yes' ? 'active' : 'inactive'; ?>">
                                        <?php echo $location->is_active === 'yes' ? __('Active', 'vandel-booking') : __('Inactive', 'vandel-booking'); ?>
                                    </span>
                                </td>
                                <td class="actions">
                                    <a href="#" class="edit-location" 
                                       data-id="<?php echo esc_attr($location->id); ?>"
                                       data-country="<?php echo esc_attr($location->country); ?>"
                                       data-city="<?php echo esc_attr($location->city); ?>"
                                       data-area="<?php echo esc_attr($location->area_name); ?>"
                                       data-zip="<?php echo esc_attr($location->zip_code); ?>"
                                       data-price="<?php echo esc_attr($location->price_adjustment); ?>"
                                       data-fee="<?php echo esc_attr($location->service_fee); ?>"
                                       data-active="<?php echo esc_attr($location->is_active); ?>">
                                        <?php _e('Edit', 'vandel-booking'); ?>
                                    </a> | 
                                    <a href="#" class="delete-location" data-id="<?php echo esc_attr($location->id); ?>">
                                        <?php _e('Delete', 'vandel-booking'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Add/Edit Location Modal -->
            <div id="location-modal" class="vandel-modal">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <h2 id="modal-title"><?php _e('Add New Location', 'vandel-booking'); ?></h2>
                    
                    <form id="location-form">
                        <input type="hidden" id="location-id" name="id" value="0">
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="country"><?php _e('Country', 'vandel-booking'); ?></label>
                                <input type="text" id="country" name="country" required>
                            </div>
                            
                            <div class="vandel-form-group">
                                <label for="city"><?php _e('City', 'vandel-booking'); ?></label>
                                <input type="text" id="city" name="city" required>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="area_name"><?php _e('Area Name', 'vandel-booking'); ?></label>
                                <input type="text" id="area_name" name="area_name" required>
                            </div>
                            
                            <div class="vandel-form-group">
                                <label for="zip_code"><?php _e('ZIP Code', 'vandel-booking'); ?></label>
                                <input type="text" id="zip_code" name="zip_code" required>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="price_adjustment"><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                <input type="number" id="price_adjustment" name="price_adjustment" step="0.01" value="0">
                                <p class="description"><?php _e('Adjustment amount added to the base price (can be negative)', 'vandel-booking'); ?></p>
                            </div>
                            
                            <div class="vandel-form-group">
                                <label for="service_fee"><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                <input type="number" id="service_fee" name="service_fee" step="0.01" min="0" value="0">
                                <p class="description"><?php _e('Additional service fee for this location', 'vandel-booking'); ?></p>
                            </div>
                        </div>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label class="vandel-checkbox-label">
                                    <input type="checkbox" id="is_active" name="is_active" value="yes" checked>
                                    <span class="vandel-checkbox-text"><?php _e('Active', 'vandel-booking'); ?></span>
                                </label>
                                <p class="description"><?php _e('Inactive locations will not be available for booking', 'vandel-booking'); ?></p>
                            </div>
                        </div>
                        
                        <div class="vandel-form-actions">
                            <button type="button" class="button vandel-modal-cancel"><?php _e('Cancel', 'vandel-booking'); ?></button>
                            <button type="submit" class="button button-primary"><?php _e('Save Location', 'vandel-booking'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Import Locations Modal -->
            <div id="import-modal" class="vandel-modal">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <h2><?php _e('Import Locations', 'vandel-booking'); ?></h2>
                    
                    <form id="import-form" enctype="multipart/form-data">
                        <p><?php _e('Upload a CSV file with your locations. The file should have the following columns: country, city, area_name, zip_code, price_adjustment, service_fee, is_active.', 'vandel-booking'); ?></p>
                        
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <label for="import-file"><?php _e('CSV File', 'vandel-booking'); ?></label>
                                <input type="file" id="import-file" name="import_file" accept=".csv" required>
                            </div>
                        </div>
                        
                        <div class="vandel-form-actions">
                            <button type="button" class="button vandel-modal-cancel"><?php _e('Cancel', 'vandel-booking'); ?></button>
                            <button type="submit" class="button button-primary"><?php _e('Import', 'vandel-booking'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}