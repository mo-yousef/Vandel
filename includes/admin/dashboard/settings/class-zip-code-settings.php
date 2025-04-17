<?php
namespace VandelBooking\Admin\Dashboard\Settings;

use VandelBooking\Location\ZipCodeModel;
use VandelBooking\Helpers;

/**
 * ZIP Code Settings
 * Handles the ZIP code management in settings
 */
class ZipCode_Settings {
    /**
     * @var ZipCodeModel ZIP Code Model instance
     */
    private $zip_code_model;

    /**
     * Constructor
     */
    public function __construct() {
        if (class_exists('\\VandelBooking\\Location\\ZipCodeModel')) {
            $this->zip_code_model = new ZipCodeModel();
        }

        // Hook to admin_enqueue_scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Process actions for ZIP codes
     */
    public function process_actions() {
        // Check if we're on the correct page and have necessary permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Debug log
        error_log('ZIP code settings process_actions called. POST data: ' . print_r($_POST, true));

        // Check for ZIP Code add action
        if (isset($_POST['vandel_add_zip_code']) && 
            isset($_POST['vandel_zip_code_nonce']) && 
            wp_verify_nonce($_POST['vandel_zip_code_nonce'], 'vandel_add_zip_code')) {
            
            // Log the form submission
            error_log('Processing ZIP code form submission with nonce verification passed');
            
            $this->add_zip_code($_POST);
        }

        // Check for ZIP Code delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete_zip_code' && 
            isset($_GET['zip_code']) && isset($_GET['_wpnonce'])) {
            
            $zip_code = sanitize_text_field($_GET['zip_code']);
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_zip_code_' . $zip_code)) {
                $this->delete_zip_code($zip_code);
            }
        }

        // Check for ZIP Code update action
        if (isset($_POST['vandel_update_zip_code']) && 
            isset($_POST['vandel_zip_code_update_nonce']) && 
            wp_verify_nonce($_POST['vandel_zip_code_update_nonce'], 'vandel_update_zip_code')) {
            
            $this->update_zip_code($_POST);
        }
    }

    /**
     * Enqueue ZIP Code admin scripts and styles
     */
    public function enqueue_assets() {
        // Only load on the ZIP code settings page
        if (!is_admin() || 
            !isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || 
            !isset($_GET['tab']) || $_GET['tab'] !== 'settings' ||
            !isset($_GET['section']) || $_GET['section'] !== 'zip-codes') {
            return;
        }
        
        // Make sure jQuery is loaded
        wp_enqueue_script('jquery');

        // Enqueue the ZIP code admin script
        wp_enqueue_script(
            'vandel-zip-code-admin',
            VANDEL_PLUGIN_URL . 'assets/js/admin/location-admin.js',
            ['jquery'],
            VANDEL_VERSION,
            true
        );
        
        // Localize the script with necessary data
        wp_localize_script(
            'vandel-zip-code-admin',
            'vandelZipCodeAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_zip_code_nonce'),
                'confirmDelete' => __('Are you sure you want to delete this ZIP code?', 'vandel-booking'),
                'strings' => [
                    'importSuccess' => __('ZIP codes imported successfully.', 'vandel-booking'),
                    'importError' => __('Error importing ZIP codes.', 'vandel-booking'),
                    'selectFile' => __('Please select a file to import.', 'vandel-booking')
                ]
            ]
        );
    }

    /**
     * Add new ZIP Code
     * 
     * @param array $data Form data
     */
    private function add_zip_code($data) {
        if (!$this->zip_code_model) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_model_missing', 
                __('ZIP Code functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }

        $zip_code_data = [
            'zip_code' => sanitize_text_field($data['zip_code']),
            'city' => sanitize_text_field($data['city']),
            'state' => isset($data['state']) ? sanitize_text_field($data['state']) : '',
            'country' => sanitize_text_field($data['country']),
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee' => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_serviceable' => isset($data['is_serviceable']) ? 'yes' : 'no'
        ];

        $result = $this->zip_code_model->add($zip_code_data);

        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_added', 
                __('Service area added successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_error', 
                __('Failed to add service area. It may already exist.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Delete ZIP Code
     * 
     * @param string $zip_code ZIP Code to delete
     */
    private function delete_zip_code($zip_code) {
        if (!$this->zip_code_model) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_model_missing', 
                __('ZIP Code functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }

        $result = $this->zip_code_model->delete($zip_code);

        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_deleted', 
                __('Service area deleted successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_delete_error', 
                __('Failed to delete service area.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Update existing ZIP Code
     * 
     * @param array $data ZIP Code data
     */
    private function update_zip_code($data) {
        if (!$this->zip_code_model) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_model_missing', 
                __('ZIP Code functionality is not available.', 'vandel-booking'), 
                'error'
            );
            return;
        }
        
        $original_zip_code = sanitize_text_field($data['original_zip_code']);
        $zip_code_data = [
            'zip_code' => sanitize_text_field($data['zip_code']),
            'city' => sanitize_text_field($data['city']),
            'state' => isset($data['state']) ? sanitize_text_field($data['state']) : '',
            'country' => sanitize_text_field($data['country']),
            'price_adjustment' => isset($data['price_adjustment']) ? floatval($data['price_adjustment']) : 0,
            'service_fee' => isset($data['service_fee']) ? floatval($data['service_fee']) : 0,
            'is_serviceable' => isset($data['is_serviceable']) ? 'yes' : 'no'
        ];
        
        $result = $this->zip_code_model->update($original_zip_code, $zip_code_data);
        
        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_updated', 
                __('Service area updated successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_error', 
                __('Failed to update service area.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Render ZIP Code settings
     */
    public function render() {
        // Display settings errors
        settings_errors('vandel_zip_code_messages');
        
        // Get current page
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;
        
        // Get ZIP Codes
        $zip_codes = [];
        $total_zip_codes = 0;
        $total_pages = 1;
        
        if ($this->zip_code_model && method_exists($this->zip_code_model, 'getServiceableZipCodes')) {
            $zip_codes = $this->zip_code_model->getServiceableZipCodes([
                'limit' => $per_page,
                'offset' => $offset
            ]);
            
            $count_result = $this->zip_code_model->getServiceableZipCodes();
            $total_zip_codes = is_array($count_result) ? count($count_result) : 0;
            $total_pages = ceil($total_zip_codes / $per_page);
        }
        
        ?>
<div class="vandel-settings-section">
    <h2><?php _e('Service Areas Management', 'vandel-booking'); ?></h2>

    <div class="vandel-settings-intro">
        <p><?php _e('Manage the areas you service with ZIP code-based pricing. Set up service areas, adjust pricing, and manage locations.', 'vandel-booking'); ?>
        </p>
    </div>

    <div class="vandel-grid-row">
        <div class="vandel-grid-col">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Add New Service Area', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <form method="post" action="">
                        <?php wp_nonce_field('vandel_add_zip_code', 'vandel_zip_code_nonce'); ?>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label><?php _e('ZIP Code', 'vandel-booking'); ?> <span
                                        class="required">*</span></label>
                                <input type="text" name="zip_code" required class="widefat">
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('City', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" name="city" required class="widefat">
                            </div>
                        </div>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label><?php _e('State/Province', 'vandel-booking'); ?></label>
                                <input type="text" name="state" class="widefat">
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('Country', 'vandel-booking'); ?> <span class="required">*</span></label>
                                <input type="text" name="country" required class="widefat">
                            </div>
                        </div>
                        <div class="vandel-form-row">
                            <div class="vandel-col">
                                <label><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="price_adjustment" step="0.01" min="-100" max="100"
                                        class="widefat" value="0">
                                </div>
                                <p class="description">
                                    <?php _e('Amount to add or subtract from base price for this area', 'vandel-booking'); ?>
                                </p>
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="service_fee" step="0.01" min="0" class="widefat"
                                        value="0">
                                </div>
                                <p class="description">
                                    <?php _e('Additional fee for servicing this area', 'vandel-booking'); ?></p>
                            </div>
                        </div>
                        <div class="vandel-toggle-controls">
                            <div class="vandel-toggle-field">
                                <label class="vandel-toggle">
                                    <input type="checkbox" name="is_serviceable" value="yes" checked>
                                    <span class="vandel-toggle-slider"></span>
                                </label>
                                <span
                                    class="vandel-toggle-label"><?php _e('Serviceable Area', 'vandel-booking'); ?></span>
                            </div>
                        </div>
                        <button type="submit" name="vandel_add_zip_code" class="button button-primary">
                            <?php _e('Add Service Area', 'vandel-booking'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Import/Export Section -->
        <div class="vandel-grid-col">
            <div class="vandel-card">
                <div class="vandel-card-header">
                    <h3><?php _e('Import/Export', 'vandel-booking'); ?></h3>
                </div>
                <div class="vandel-card-body">
                    <div class="vandel-import-section">
                        <h4><?php _e('Import ZIP Codes', 'vandel-booking'); ?></h4>
                        <p><?php _e('Upload a CSV file with ZIP code data to bulk import.', 'vandel-booking'); ?></p>
                        <form method="post" enctype="multipart/form-data">
                            <div class="vandel-form-row">
                                <div class="vandel-col">
                                    <input type="file" name="zip_codes_file" id="vandel-zip-codes-file"
                                        accept=".csv,.xlsx,.xls">
                                </div>
                                <div class="vandel-col">
                                    <button type="button" id="vandel-import-zip-codes" class="button button-secondary">
                                        <span class="dashicons dashicons-upload"></span>
                                        <?php _e('Import', 'vandel-booking'); ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <p class="description">
                            <?php _e('CSV format: ZIP Code, City, State, Country, Price Adjustment, Service Fee, Serviceable (yes/no)', 'vandel-booking'); ?>
                        </p>
                    </div>
                    <div class="vandel-export-section">
                        <h4><?php _e('Export ZIP Codes', 'vandel-booking'); ?></h4>
                        <p><?php _e('Download all your service areas as a CSV file.', 'vandel-booking'); ?></p>
                        <button type="button" id="vandel-export-zip-codes" class="button button-secondary">
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
            <h3><?php _e('Your Service Areas', 'vandel-booking'); ?></h3>
            <div class="vandel-filter-controls">
                <input type="text" id="vandel-zip-search"
                    placeholder="<?php _e('Search ZIP codes...', 'vandel-booking'); ?>" class="regular-text">
            </div>
        </div>
        <div class="vandel-card-body">
            <table class="wp-list-table widefat fixed striped vandel-data-table">
                <thead>
                    <tr>
                        <th><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                        <th><?php _e('City', 'vandel-booking'); ?></th>
                        <th><?php _e('State', 'vandel-booking'); ?></th>
                        <th><?php _e('Country', 'vandel-booking'); ?></th>
                        <th><?php _e('Price Adjustment', 'vandel-booking'); ?></th>
                        <th><?php _e('Service Fee', 'vandel-booking'); ?></th>
                        <th><?php _e('Status', 'vandel-booking'); ?></th>
                        <th><?php _e('Actions', 'vandel-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($zip_codes)): ?>
                    <tr>
                        <td colspan="8"><?php _e('No service areas found.', 'vandel-booking'); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($zip_codes as $zip): ?>
                    <tr>
                        <td><?php echo esc_html($zip->zip_code); ?></td>
                        <td><?php echo esc_html($zip->city); ?></td>
                        <td><?php echo esc_html($zip->state ?: '—'); ?></td>
                        <td><?php echo esc_html($zip->country); ?></td>
                        <td><?php echo ($zip->price_adjustment >= 0 ? '+' : '') . Helpers::formatPrice($zip->price_adjustment); ?>
                        </td>
                        <td><?php echo Helpers::formatPrice($zip->service_fee); ?></td>
                        <td><?php echo ($zip->is_serviceable === 'yes' ? 
                                            '<span class="vandel-badge vandel-badge-success">' . __('Active', 'vandel-booking') . '</span>' : 
                                            '<span class="vandel-badge vandel-badge-danger">' . __('Inactive', 'vandel-booking') . '</span>'); ?>
                        </td>
                        <td>
                            <div class="vandel-row-actions">
                                <a href="#" class="vandel-edit-zip-code"
                                    data-zip-code="<?php echo esc_attr($zip->zip_code); ?>"
                                    data-city="<?php echo esc_attr($zip->city); ?>"
                                    data-state="<?php echo esc_attr($zip->state); ?>"
                                    data-country="<?php echo esc_attr($zip->country); ?>"
                                    data-price-adjustment="<?php echo esc_attr($zip->price_adjustment); ?>"
                                    data-service-fee="<?php echo esc_attr($zip->service_fee); ?>"
                                    data-is-serviceable="<?php echo esc_attr($zip->is_serviceable); ?>">
                                    <?php _e('Edit', 'vandel-booking'); ?>
                                </a> |
                                <a href="<?php echo wp_nonce_url(
                                                    admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes&action=delete_zip_code&zip_code=' . urlencode($zip->zip_code)),
                                                    'delete_zip_code_' . $zip->zip_code
                                                ); ?>"
                                    class="vandel-delete-zip-code"><?php _e('Delete', 'vandel-booking'); ?></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                                echo paginate_links([
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => __('&laquo;', 'vandel-booking'),
                                    'next_text' => __('&raquo;', 'vandel-booking'),
                                    'total' => $total_pages,
                                    'current' => $page
                                ]);
                                ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit ZIP Code Modal -->
    <div id="vandel-edit-zip-code-modal" class="vandel-modal" style="display:none;">
        <div class="vandel-modal-content">
            <span class="vandel-modal-close">&times;</span>
            <h3><?php _e('Edit Service Area', 'vandel-booking'); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field('vandel_update_zip_code', 'vandel_zip_code_update_nonce'); ?>
                <input type="hidden" name="original_zip_code" id="edit-original-zip-code">

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label><?php _e('ZIP Code', 'vandel-booking'); ?></label>
                        <input type="text" name="zip_code" id="edit-zip-code" required class="widefat">
                    </div>
                    <div class="vandel-col">
                        <label><?php _e('City', 'vandel-booking'); ?></label>
                        <input type="text" name="city" id="edit-city" required class="widefat">
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label><?php _e('State/Province', 'vandel-booking'); ?></label>
                        <input type="text" name="state" id="edit-state" class="widefat">
                    </div>
                    <div class="vandel-col">
                        <label><?php _e('Country', 'vandel-booking'); ?></label>
                        <input type="text" name="country" id="edit-country" required class="widefat">
                    </div>
                </div>

                <div class="vandel-form-row">
                    <div class="vandel-col">
                        <label><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                            <input type="number" name="price_adjustment" id="edit-price-adjustment" step="0.01"
                                min="-100" max="100" class="widefat">
                        </div>
                    </div>
                    <div class="vandel-col">
                        <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                            <input type="number" name="service_fee" id="edit-service-fee" step="0.01" min="0"
                                class="widefat">
                        </div>
                    </div>
                </div>

                <div class="vandel-toggle-controls">
                    <div class="vandel-toggle-field">
                        <label class="vandel-toggle">
                            <input type="checkbox" name="is_serviceable" id="edit-is-serviceable" value="yes">
                            <span class="vandel-toggle-slider"></span>
                        </label>
                        <span class="vandel-toggle-label"><?php _e('Serviceable Area', 'vandel-booking'); ?></span>
                    </div>
                </div>

                <button type="submit" name="vandel_update_zip_code" class="button button-primary">
                    <?php _e('Update Service Area', 'vandel-booking'); ?>
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
        width: 50%;
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
    </style>
</div>
<?php
    }
}