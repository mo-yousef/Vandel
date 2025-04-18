<?php
/**
 * Location Management Settings Tab Integration
 * 
 * This file integrates location management directly into the settings tab
 * of the Vandel Booking plugin dashboard.
 */

namespace VandelBooking\Admin;

/**
 * Location Settings Tab Handler
 */
class LocationSettingsTab {
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
        
        // Register settings tab section
        add_filter('vandel_settings_sections', [$this, 'addSettingsSection']);
        
        // Register AJAX handlers for area and location management
        add_action('wp_ajax_vandel_save_area', [$this, 'ajaxSaveArea']);
        add_action('wp_ajax_vandel_delete_area', [$this, 'ajaxDeleteArea']);
        add_action('wp_ajax_vandel_save_location', [$this, 'ajaxSaveLocation']);
        add_action('wp_ajax_vandel_delete_location', [$this, 'ajaxDeleteLocation']);
        add_action('wp_ajax_vandel_get_area_locations', [$this, 'ajaxGetAreaLocations']);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }
    
    /**
     * Add settings section for locations
     * 
     * @param array $sections Existing sections
     * @return array Modified sections
     */
    public function addSettingsSection($sections) {
        $sections['locations'] = __('Locations', 'vandel-booking');
        return $sections;
    }
    
    /**
     * Enqueue scripts for the settings page
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueueScripts($hook) {
        // Only load on our plugin pages
        if ($hook !== 'toplevel_page_vandel-dashboard' && strpos($hook, 'page_vandel-dashboard') === false) {
            return;
        }
        
        // Only load on the settings tab with locations section
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'settings' || 
            !isset($_GET['section']) || $_GET['section'] !== 'locations') {
            return;
        }
        
        wp_enqueue_style(
            'vandel-location-settings',
            VANDEL_PLUGIN_URL . 'assets/css/location-settings.css',
            [],
            VANDEL_VERSION
        );
        
        wp_enqueue_script(
            'vandel-location-settings',
            VANDEL_PLUGIN_URL . 'assets/js/admin/location-settings.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        wp_localize_script(
            'vandel-location-settings',
            'vandelLocations',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_location_settings'),
                'strings' => [
                    'confirmDeleteArea' => __('Are you sure you want to delete this area? All associated locations will also be removed.', 'vandel-booking'),
                    'confirmDeleteLocation' => __('Are you sure you want to remove this location?', 'vandel-booking'),
                    'saved' => __('Successfully saved!', 'vandel-booking'),
                    'deleted' => __('Successfully deleted!', 'vandel-booking'),
                    'error' => __('An error occurred. Please try again.', 'vandel-booking'),
                    'addLocation' => __('Add location', 'vandel-booking'),
                    'removeLocation' => __('Remove', 'vandel-booking'),
                    'backToSettings' => __('Back to settings', 'vandel-booking'),
                    'save' => __('Save', 'vandel-booking')
                ]
            ]
        );
    }
    
    /**
     * Render settings section content
     */
    public function renderSettingsSection() {
        // Check if location model is available
        if (!$this->location_model) {
            echo '<div class="notice notice-error"><p>' . __('Location model not available', 'vandel-booking') . '</p></div>';
            return;
        }
        
        // Check if an area is being edited
        if (isset($_GET['edit_area'])) {
            $this->renderAreaEditPage(sanitize_text_field($_GET['edit_area']));
            return;
        }
        
        // Get all areas
        $areas = $this->location_model->getAreas();
        ?>
        <div class="vandel-settings-section locations-section">
            <h2 class="vandel-settings-title"><?php _e('Service Areas', 'vandel-booking'); ?></h2>
            <p class="vandel-settings-description"><?php _e('Manage service areas and locations for your booking system.', 'vandel-booking'); ?></p>
            
            <div class="vandel-add-area">
                <h3><?php _e('Add New Area', 'vandel-booking'); ?></h3>
                <form id="add-area-form" class="vandel-inline-form">
                    <div class="vandel-form-row">
                        <div class="vandel-form-group">
                            <label for="area-name"><?php _e('Area Name', 'vandel-booking'); ?></label>
                            <input type="text" id="area-name" name="area_name" required>
                        </div>
                        <div class="vandel-form-group">
                            <label for="area-country"><?php _e('Country', 'vandel-booking'); ?></label>
                            <input type="text" id="area-country" name="country" value="Sweden" required>
                        </div>
                        <div class="vandel-form-submit">
                            <button type="submit" class="button button-primary"><?php _e('Add Area', 'vandel-booking'); ?></button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="vandel-areas-list">
                <h3><?php _e('Existing Areas', 'vandel-booking'); ?></h3>
                
                <?php if (empty($areas)): ?>
                    <p class="vandel-empty-state"><?php _e('No areas have been created yet.', 'vandel-booking'); ?></p>
                <?php else: ?>
                    <div class="vandel-areas-table-container">
                        <table class="wp-list-table widefat fixed striped vandel-areas-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?php _e('Area Name', 'vandel-booking'); ?></th>
                                    <th scope="col"><?php _e('Country', 'vandel-booking'); ?></th>
                                    <th scope="col"><?php _e('Locations', 'vandel-booking'); ?></th>
                                    <th scope="col"><?php _e('Actions', 'vandel-booking'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($areas as $area): ?>
                                    <?php 
                                        $location_count = $this->location_model->countLocationsByArea($area->id);
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($area->name); ?></td>
                                        <td><?php echo esc_html($area->country); ?></td>
                                        <td><?php echo esc_html($location_count); ?></td>
                                        <td class="actions">
                                            <a href="<?php echo esc_url(add_query_arg(['edit_area' => $area->id])); ?>" class="button button-small">
                                                <span class="dashicons dashicons-edit"></span> <?php _e('Edit', 'vandel-booking'); ?>
                                            </a>
                                            <a href="#" class="button button-small button-link-delete delete-area" data-id="<?php echo esc_attr($area->id); ?>">
                                                <span class="dashicons dashicons-trash"></span> <?php _e('Delete', 'vandel-booking'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Add new area form submission
                $('#add-area-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const areaName = $('#area-name').val();
                    const country = $('#area-country').val();
                    
                    if (!areaName || !country) {
                        alert('<?php echo esc_js(__('Please fill in all required fields', 'vandel-booking')); ?>');
                        return;
                    }
                    
                    $.ajax({
                        url: vandelLocations.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'vandel_save_area',
                            nonce: vandelLocations.nonce,
                            area_name: areaName,
                            country: country
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || vandelLocations.strings.error);
                            }
                        },
                        error: function() {
                            alert(vandelLocations.strings.error);
                        }
                    });
                });
                
                // Delete area
                $('.delete-area').on('click', function(e) {
                    e.preventDefault();
                    
                    const areaId = $(this).data('id');
                    
                    if (confirm(vandelLocations.strings.confirmDeleteArea)) {
                        $.ajax({
                            url: vandelLocations.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'vandel_delete_area',
                                nonce: vandelLocations.nonce,
                                area_id: areaId
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.data.message || vandelLocations.strings.error);
                                }
                            },
                            error: function() {
                                alert(vandelLocations.strings.error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
     * Render area edit page
     * 
     * @param int $area_id Area ID
     */
    private function renderAreaEditPage($area_id) {
        // Get area data
        $area = $this->location_model->getAreaById($area_id);
        
        if (!$area) {
            echo '<div class="notice notice-error"><p>' . __('Area not found', 'vandel-booking') . '</p></div>';
            return;
        }
        
        // Get locations for this area
        $locations = $this->location_model->getLocationsByArea($area_id);
        ?>
        <div class="vandel-area-edit-page">
            <h2><?php printf(__('Edit area - %s', 'vandel-booking'), esc_html($area->name)); ?></h2>
            
            <div class="vandel-area-edit-form">
                <form id="edit-area-form">
                    <input type="hidden" id="area-id" value="<?php echo esc_attr($area_id); ?>">
                    
                    <div class="vandel-form-row">
                        <div class="vandel-form-group">
                            <label for="edit-area-name"><?php _e('Name', 'vandel-booking'); ?></label>
                            <input type="text" id="edit-area-name" name="area_name" value="<?php echo esc_attr($area->name); ?>" required>
                        </div>
                    </div>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-form-group">
                            <label for="edit-area-admin-area"><?php _e('Area', 'vandel-booking'); ?> <span class="description"><?php _e('(If you want to add locations from another admin area, create your new area and select the relevant admin area.)', 'vandel-booking'); ?></span></label>
                            <input type="text" id="edit-area-admin-area" name="admin_area" value="<?php echo esc_attr($area->name); ?>" required>
                        </div>
                    </div>
                    
                    <h3><?php _e('Locations', 'vandel-booking'); ?></h3>
                    <div class="vandel-locations-container" id="locations-container">
                        <?php if (empty($locations)): ?>
                            <p class="vandel-empty-state"><?php _e('No locations have been added to this area.', 'vandel-booking'); ?></p>
                        <?php else: ?>
                            <div class="vandel-locations-list">
                                <?php foreach ($locations as $location): ?>
                                    <span class="vandel-location-badge">
                                        <?php echo esc_html($location->name); ?>
                                        <button type="button" class="vandel-remove-location" data-id="<?php echo esc_attr($location->id); ?>">×</button>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vandel-add-location-form">
                        <div class="vandel-form-row">
                            <div class="vandel-form-group">
                                <input type="text" id="new-location-name" placeholder="<?php esc_attr_e('Add new location...', 'vandel-booking'); ?>">
                                <button type="button" id="add-location-btn" class="button"><?php _e('Add', 'vandel-booking'); ?></button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="vandel-form-actions">
                        <a href="<?php echo esc_url(remove_query_arg('edit_area')); ?>" class="button button-secondary"><?php _e('Back to provider settings', 'vandel-booking'); ?></a>
                        <button type="submit" id="save-area-btn" class="button button-primary"><?php _e('Save', 'vandel-booking'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Initialize locations
                let locations = <?php echo json_encode($locations); ?>;
                
                // Add new location
                $('#add-location-btn').on('click', function() {
                    const locationName = $('#new-location-name').val().trim();
                    
                    if (!locationName) {
                        return;
                    }
                    
                    // Add location to UI first
                    const tempId = 'new_' + Date.now();
                    addLocationToUI({
                        id: tempId,
                        name: locationName,
                        area_id: $('#area-id').val(),
                        is_new: true
                    });
                    
                    // Clear input
                    $('#new-location-name').val('').focus();
                });
                
                // Enter key in location input
                $('#new-location-name').on('keypress', function(e) {
                    if (e.which === 13) {
                        e.preventDefault();
                        $('#add-location-btn').click();
                    }
                });
                
                // Remove location
                $(document).on('click', '.vandel-remove-location', function() {
                    const $badge = $(this).closest('.vandel-location-badge');
                    const locationId = $(this).data('id');
                    
                    // If this is a new location that hasn't been saved yet
                    if (locationId.toString().startsWith('new_')) {
                        $badge.remove();
                        return;
                    }
                    
                    if (confirm(vandelLocations.strings.confirmDeleteLocation)) {
                        $.ajax({
                            url: vandelLocations.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'vandel_delete_location',
                                nonce: vandelLocations.nonce,
                                location_id: locationId
                            },
                            success: function(response) {
                                if (response.success) {
                                    $badge.remove();
                                } else {
                                    alert(response.data.message || vandelLocations.strings.error);
                                }
                            },
                            error: function() {
                                alert(vandelLocations.strings.error);
                            }
                        });
                    }
                });
                
                // Save area and locations
                $('#edit-area-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    const areaId = $('#area-id').val();
                    const areaName = $('#edit-area-name').val();
                    const adminArea = $('#edit-area-admin-area').val();
                    
                    if (!areaName || !adminArea) {
                        alert('<?php echo esc_js(__('Please fill in all required fields', 'vandel-booking')); ?>');
                        return;
                    }
                    
                    // Save area first
                    $.ajax({
                        url: vandelLocations.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'vandel_save_area',
                            nonce: vandelLocations.nonce,
                            area_id: areaId,
                            area_name: areaName,
                            admin_area: adminArea
                        },
                        success: function(response) {
                            if (response.success) {
                                // Now save any new locations
                                saveNewLocations(areaId, function() {
                                    // Redirect back to locations list
                                    window.location.href = '<?php echo esc_url(remove_query_arg('edit_area')); ?>';
                                });
                            } else {
                                alert(response.data.message || vandelLocations.strings.error);
                            }
                        },
                        error: function() {
                            alert(vandelLocations.strings.error);
                        }
                    });
                });
                
                // Helper function to add location to UI
                function addLocationToUI(location) {
                    const $container = $('#locations-container');
                    let $locationsList = $container.find('.vandel-locations-list');
                    
                    // Create locations list if it doesn't exist
                    if ($locationsList.length === 0) {
                        $container.empty();
                        $locationsList = $('<div class="vandel-locations-list"></div>').appendTo($container);
                    }
                    
                    // Add new location badge
                    const $badge = $(`
                        <span class="vandel-location-badge">
                            ${location.name}
                            <button type="button" class="vandel-remove-location" data-id="${location.id}">×</button>
                        </span>
                    `);
                    
                    $locationsList.append($badge);
                    
                    // Store new location
                    if (location.is_new) {
                        locations.push(location);
                    }
                }
                
                // Helper function to save new locations
                function saveNewLocations(areaId, callback) {
                    const newLocations = locations.filter(loc => loc.is_new);
                    
                    if (newLocations.length === 0) {
                        if (typeof callback === 'function') {
                            callback();
                        }
                        return;
                    }
                    
                    let savedCount = 0;
                    
                    newLocations.forEach(function(location) {
                        $.ajax({
                            url: vandelLocations.ajaxUrl,
                            type: 'POST',
                            data: {
                                action: 'vandel_save_location',
                                nonce: vandelLocations.nonce,
                                location_name: location.name,
                                area_id: areaId
                            },
                            success: function() {
                                savedCount++;
                                if (savedCount === newLocations.length && typeof callback === 'function') {
                                    callback();
                                }
                            },
                            error: function() {
                                savedCount++;
                                if (savedCount === newLocations.length && typeof callback === 'function') {
                                    callback();
                                }
                            }
                        });
                    });
                }
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for saving area
     */
    public function ajaxSaveArea() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get form data
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        $area_name = isset($_POST['area_name']) ? sanitize_text_field($_POST['area_name']) : '';
        $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
        $admin_area = isset($_POST['admin_area']) ? sanitize_text_field($_POST['admin_area']) : '';
        
        // Make sure admin_area is set to area_name if not provided
        if (empty($admin_area) && !empty($area_name)) {
            $admin_area = $area_name;
        }
        
        // For update, we need at least area_id and area_name
        if ($area_id > 0 && empty($area_name)) {
            wp_send_json_error(['message' => __('Area name is required', 'vandel-booking')]);
            return;
        }
        
        // For new area, we need area_name and country
        if ($area_id === 0 && (empty($area_name) || empty($country))) {
            wp_send_json_error(['message' => __('Area name and country are required', 'vandel-booking')]);
            return;
        }
        
        // Update or add area
        if ($area_id > 0) {
            $result = $this->location_model->updateArea($area_id, [
                'name' => $area_name,
                'admin_area' => $admin_area
            ]);
            $message = __('Area updated successfully', 'vandel-booking');
        } else {
            $result = $this->location_model->addArea([
                'name' => $area_name,
                'country' => $country,
                'admin_area' => $admin_area ?: $area_name
            ]);
            $message = __('Area added successfully', 'vandel-booking');
        }
        
        if ($result) {
            wp_send_json_success(['message' => $message]);
        } else {
            wp_send_json_error(['message' => __('Failed to save area', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for deleting area
     */
    public function ajaxDeleteArea() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get area ID
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        if ($area_id <= 0) {
            wp_send_json_error(['message' => __('Invalid area ID', 'vandel-booking')]);
            return;
        }
        
        // Delete area and all associated locations
        $result = $this->location_model->deleteArea($area_id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Area and associated locations deleted successfully', 'vandel-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete area', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for saving location
     */
    public function ajaxSaveLocation() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get form data
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        $location_name = isset($_POST['location_name']) ? sanitize_text_field($_POST['location_name']) : '';
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        // Validate required fields
        if (empty($location_name) || $area_id <= 0) {
            wp_send_json_error(['message' => __('Location name and area ID are required', 'vandel-booking')]);
            return;
        }
        
        // Update or add location
        if ($location_id > 0) {
            $result = $this->location_model->updateLocation($location_id, [
                'name' => $location_name,
                'area_id' => $area_id
            ]);
            $message = __('Location updated successfully', 'vandel-booking');
        } else {
            $result = $this->location_model->addLocation([
                'name' => $location_name,
                'area_id' => $area_id
            ]);
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
        if (!check_ajax_referer('vandel_location_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get location ID
        $location_id = isset($_POST['location_id']) ? intval($_POST['location_id']) : 0;
        
        if ($location_id <= 0) {
            wp_send_json_error(['message' => __('Invalid location ID', 'vandel-booking')]);
            return;
        }
        
        // Delete location
        $result = $this->location_model->deleteLocation($location_id);
        
        if ($result) {
            wp_send_json_success(['message' => __('Location deleted successfully', 'vandel-booking')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete location', 'vandel-booking')]);
        }
    }
    
    /**
     * AJAX handler for getting locations by area
     */
    public function ajaxGetAreaLocations() {
        // Check nonce
        if (!check_ajax_referer('vandel_location_settings', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'vandel-booking')]);
            return;
        }
        
        // Check if location model is available
        if (!$this->location_model) {
            wp_send_json_error(['message' => __('Location model not available', 'vandel-booking')]);
            return;
        }
        
        // Get area ID
        $area_id = isset($_POST['area_id']) ? intval($_POST['area_id']) : 0;
        
        if ($area_id <= 0) {
            wp_send_json_error(['message' => __('Invalid area ID', 'vandel-booking')]);
            return;
        }
        
        // Get locations for this area
        $locations = $this->location_model->getLocationsByArea($area_id);
        
        wp_send_json_success(['locations' => $locations]);
    }
}

// Initialize the class
add_action('plugins_loaded', function() {
    new LocationSettingsTab();
});