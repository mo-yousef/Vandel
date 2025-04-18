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
        
        // Register AJAX handlers
        add_action('wp_ajax_vandel_save_location', [$this, 'ajaxSaveLocation']);
        add_action('wp_ajax_vandel_delete_location', [$this, 'ajaxDeleteLocation']);
        add_action('wp_ajax_vandel_import_locations', [$this, 'ajaxImportLocations']);
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
        
        <script>
            jQuery(document).ready(function($) {
                // Filter country change
                $('#filter-country').on('change', function() {
                    if ($(this).val()) {
                        $('#filter-city').prop('disabled', false);
                    } else {
                        $('#filter-city').prop('disabled', true).val('');
                    }
                });
                
                // Open add location modal
                $('.add-new-location').on('click', function(e) {
                    e.preventDefault();
                    resetLocationForm();
                    $('#modal-title').text(vandelLocationAdmin.strings.addLocation);
                    $('#location-modal').show();
                });
                
                // Open import modal
                $('.import-locations').on('click', function(e) {
                    e.preventDefault();
                    $('#import-modal').show();
                });
                
                // Close modals
                $('.vandel-modal-close, .vandel-modal-cancel').on('click', function() {
                    $('.vandel-modal').hide();
                });
                
                // Close modal when clicking outside
                $(window).on('click', function(e) {
                    if ($(e.target).hasClass('vandel-modal')) {
                        $('.vandel-modal').hide();
                    }
                });
                
                // Edit location
                $('.edit-location').on('click', function(e) {
                    e.preventDefault();
                    
                    const id = $(this).data('id');
                    const country = $(this).data('country');
                    const city = $(this).data('city');
                    const area = $(this).data('area');
                    const zip = $(this).data('zip');
                    const price = $(this).data('price');
                    const fee = $(this).data('fee');
                    const active = $(this).data('active');
                    
                    $('#location-id').val(id);
                    $('#country').val(country);
                    $('#city').val(city);
                    $('#area_name').val(area);
                    $('#zip_code').val(zip);
                    $('#price_adjustment').val(price);
                    $('#service_fee').val(fee);
                    $('#is_active').prop('checked', active === 'yes');
                    
                    $('#modal-title').text(vandelLocationAdmin.strings.editLocation);
                    $('#location-modal').show();
                });
                
                // Delete location
                $('.delete-location').on('click', function(e) {
                    e.preventDefault();
                    
                    const id = $(this).data('id');
                    
                    if (confirm(vandelLocationAdmin.strings.confirmDelete)) {
                        $.ajax({
                            url: vandelLocationAdmin.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'vandel_delete_location',
                                id: id,
                                nonce: vandelLocationAdmin.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.location.reload();
                                } else {
                                    alert(response.data.message || vandelLocationAdmin.strings.error);
                                }
                            },
                            error: function() {
                                alert(vandelLocationAdmin.strings.error);
                            }
                        });
                    }
                });
                
                // Submit location form
                $('#location-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = {
                        action: 'vandel_save_location',
                        nonce: vandelLocationAdmin.nonce,
                        id: $('#location-id').val(),
                        country: $('#country').val(),
                        city: $('#city').val(),
                        area_name: $('#area_name').val(),
                        zip_code: $('#zip_code').val(),
                        price_adjustment: $('#price_adjustment').val(),
                        service_fee: $('#service_fee').val(),
                        is_active: $('#is_active').is(':checked') ? 'yes' : 'no'
                    };
                    
                    $.ajax({
                        url: vandelLocationAdmin.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.data.message || vandelLocationAdmin.strings.error);
                            }
                        },
                        error: function() {
                            alert(vandelLocationAdmin.strings.error);
                        }
                    });
                });
                
                // Submit import form
                $('#import-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'vandel_import_locations');
                    formData.append('nonce', vandelLocationAdmin.nonce);
                    
                    $.ajax({
                        url: vandelLocationAdmin.ajaxUrl,
                        type: 'POST',
                        data: formData,
                        contentType: false,
                        processData: false,
                        success: function(response) {
                            if (response.success) {
                                window.location.reload();
                            } else {
                                alert(response.data.message || vandelLocationAdmin.strings.error);
                            }
                        },
                        error: function() {
                            alert(vandelLocationAdmin.strings.error);
                        }
                    });
                });
                
                // Reset location form
                function resetLocationForm() {
                    $('#location-id').val('0');
                    $('#country').val('');
                    $('#city').val('');
                    $('#area_name').val('');
                    $('#zip_code').val('');
                    $('#price_adjustment').val('0');
                    $('#service_fee').val('0');
                    $('#is_active').prop('checked', true);
                }
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for saving location
     */
    public function ajaxSaveLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get form data
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $location_data = [
            'country' => isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '',
            'city' => isset($_POST['city']) ? sanitize_text_field($_POST['city']) : '',
            'area_name' => isset($_POST['area_name']) ? sanitize_text_field($_POST['area_name']) : '',
            'zip_code' => isset($_POST['zip_code']) ? sanitize_text_field($_POST['zip_code']) : '',
            'price_adjustment' => isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0,
            'service_fee' => isset($_POST['service_fee']) ? floatval($_POST['service_fee']) : 0,
            'is_active' => isset($_POST['is_active']) && $_POST['is_active'] === 'yes' ? 'yes' : 'no'
        ];
        
        // Validate required fields
        if (empty($location_data['country']) || empty($location_data['city']) || 
            empty($location_data['area_name']) || empty($location_data['zip_code'])) {
            wp_send_json_error(['message' => __('Please fill all required fields', 'vandel-booking')]);
            return;
        }
        
        // Update or add location
        if ($id > 0) {
            $result = $this->location_model->update($id, $location_data);
            $message = __('Location updated successfully', 'vandel-booking');
        } else {
            $result = $this->location_model->add($location_data);
            $message = __('Location added successfully', 'vandel-booking');
        }
        
        if ($result) {
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to save location', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for deleting location
     */
    public function ajaxDeleteLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get location ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if ($id <= 0) {
            wp_send_json_error(['message' => __('Invalid location ID', 'vandel-booking')]);
            return;
        }
        
        // Delete location
        $result = $this->location_model->delete($id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Location deleted successfully', 'vandel-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete location', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for importing locations
     */
    public function ajaxImportLocations() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_admin', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['import_file']) || !is_uploaded_file($_FILES['import_file']['tmp_name'])) {
            wp_send_json_error(['message' => __('No file uploaded', 'vandel-booking')]);
            return;
        }
        
        // Read CSV file
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        if (!$file) {
            wp_send_json_error(['message' => __('Unable to open file', 'vandel-booking')]);
            return;
        }
        
        // Get headers
        $headers = fgetcsv($file);
        
        // Check required headers
        $required_headers = ['country', 'city', 'area_name', 'zip_code'];
        $missing_headers = array_diff($required_headers, $headers);
        
        if (!empty($missing_headers)) {
            fclose($file);
            wp_send_json_error([
                'message' => sprintf(
                    __('Missing required headers: %s', 'vandel-booking'),
                    implode(', ', $missing_headers)
                )
            ]);
            return;
        }
        
        // Parse locations
        $locations = [];
        while (($row = fgetcsv($file)) !== false) {
            if (count($row) >= count($headers)) {
                $location = array_combine($headers, $row);
                $locations[] = $location;
            }
        }
        
        fclose($file);
        
        if (empty($locations)) {
            wp_send_json_error(['message' => __('No locations found in file', 'vandel-booking')]);
            return;
        }
        
        // Import locations
        $result = $this->location_model->bulkImport($locations);
        
        wp_send_json_success([
            'message' => sprintf(
                __('Import completed. %d locations imported, %d updated, %d failed.', 'vandel-booking'),
                $result['imported'],
                $result['updated'],
                $result['failed']
            )
        ]);
    }
}