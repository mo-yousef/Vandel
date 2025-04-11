<?php
namespace VandelBooking\Admin;

use VandelBooking\Location\ZipCodeModel;
use VandelBooking\Helpers;

/**
 * Settings Page with ZIP Code Management
 */
class SettingsPage {
    /**
     * @var ZipCodeModel
     */
    private $zip_code_model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->zip_code_model = new ZipCodeModel();
        $this->initHooks();
    }

    /**
     * Initialize hooks
     */
    private function initHooks() {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_init', [$this, 'handleZipCodeActions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Enqueue assets for settings page
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        if ($hook !== 'toplevel_page_vandel-dashboard') {
            return;
        }

        wp_enqueue_script(
            'vandel-zip-code-admin', 
            VANDEL_PLUGIN_URL . 'assets/js/admin/zip-code-admin.js', 
            ['jquery'], 
            VANDEL_VERSION, 
            true
        );

        wp_localize_script('vandel-zip-code-admin', 'vandelZipCodeAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vandel_zip_code_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this ZIP code?', 'vandel-booking'),
        ]);
    }

    /**
     * Handle ZIP Code CRUD actions
     */
    public function handleZipCodeActions() {
    // Add this debug line
    error_log('handleZipCodeActions method called. POST data: ' . print_r($_POST, true));
        // Check if we're on the correct page and have necessary permissions
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check for ZIP Code add action
        if (isset($_POST['vandel_add_zip_code']) && 
            wp_verify_nonce($_POST['vandel_zip_code_nonce'], 'vandel_add_zip_code')) {
            $this->addZipCode($_POST);
        }

        // Check for ZIP Code update action
        if (isset($_POST['vandel_update_zip_code']) && 
            wp_verify_nonce($_POST['vandel_zip_code_update_nonce'], 'vandel_update_zip_code')) {
            $this->updateZipCode($_POST);
        }

        // Check for ZIP Code delete action
        if (isset($_GET['action'], $_GET['zip_code'], $_GET['_wpnonce']) && 
            $_GET['action'] === 'delete_zip_code') {
            if (wp_verify_nonce($_GET['_wpnonce'], 'delete_zip_code_' . $_GET['zip_code'])) {
                $this->deleteZipCode($_GET['zip_code']);
            }
        }
    }

    /**
     * Add new ZIP Code
     * 
     * @param array $data ZIP Code data
     */
    private function addZipCode($data) {
    // Add this debug line
    error_log('addZipCode method called with data: ' . print_r($data, true));

        $zip_code = sanitize_text_field($data['zip_code']);
        $city = sanitize_text_field($data['city']);
        $state = sanitize_text_field($data['state']);
        $country = sanitize_text_field($data['country']);
        $price_adjustment = floatval($data['price_adjustment'] ?? 0);
        $service_fee = floatval($data['service_fee'] ?? 0);
        $is_serviceable = isset($data['is_serviceable']) ? 'yes' : 'no';

        $result = $this->zip_code_model->add([
            'zip_code' => $zip_code,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'price_adjustment' => $price_adjustment,
            'service_fee' => $service_fee,
            'is_serviceable' => $is_serviceable
        ]);

        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_added', 
                __('ZIP Code added successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_error', 
                __('Failed to add ZIP Code. It may already exist.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Update existing ZIP Code
     * 
     * @param array $data ZIP Code data
     */
    private function updateZipCode($data) {
        $original_zip_code = sanitize_text_field($data['original_zip_code']);
        $zip_code = sanitize_text_field($data['zip_code']);
        $city = sanitize_text_field($data['city']);
        $state = sanitize_text_field($data['state']);
        $country = sanitize_text_field($data['country']);
        $price_adjustment = floatval($data['price_adjustment'] ?? 0);
        $service_fee = floatval($data['service_fee'] ?? 0);
        $is_serviceable = isset($data['is_serviceable']) ? 'yes' : 'no';

        $update_data = [
            'zip_code' => $zip_code,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'price_adjustment' => $price_adjustment,
            'service_fee' => $service_fee,
            'is_serviceable' => $is_serviceable
        ];

        $result = $this->zip_code_model->update($original_zip_code, $update_data);

        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_updated', 
                __('ZIP Code updated successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_update_error', 
                __('Failed to update ZIP Code.', 'vandel-booking'), 
                'error'
            );
        }
    }

    /**
     * Delete ZIP Code
     * 
     * @param string $zip_code ZIP Code to delete
     */
    private function deleteZipCode($zip_code) {
        $result = $this->zip_code_model->delete($zip_code);

        if ($result) {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_deleted', 
                __('ZIP Code deleted successfully.', 'vandel-booking'), 
                'success'
            );
        } else {
            add_settings_error(
                'vandel_zip_code_messages', 
                'vandel_zip_code_delete_error', 
                __('Failed to delete ZIP Code.', 'vandel-booking'), 
                'error'
            );
        }

        // Redirect to prevent form resubmission
        wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes'));
        exit;
    }

    /**
     * Register settings
     */
    public function registerSettings() {
        // ZIP Code feature toggle
        register_setting('vandel_settings_group', 'vandel_enable_zip_code_feature');
    }

    /**
     * Render settings page
     */
    public function render() {
        $active_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'general';
        
        ?>
        <div class="wrap vandel-settings-page">
            <h1><?php _e('Vandel Booking Settings', 'vandel-booking'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=general')); ?>" 
                   class="nav-tab <?php echo $active_section === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'vandel-booking'); ?>
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes')); ?>" 
                   class="nav-tab <?php echo $active_section === 'zip-codes' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('ZIP Codes', 'vandel-booking'); ?>
                </a>
            </h2>
            
            <?php 
            // Display any settings errors
            settings_errors('vandel_zip_code_messages');
            
            if ($active_section === 'general') {
                $this->renderGeneralSettings();
            } elseif ($active_section === 'zip-codes') {
                $this->renderZipCodeSettings();
            } 
            ?>
        </div>
        <?php
    }

    /**
     * Render general settings
     */
    private function renderGeneralSettings() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('vandel_settings_group');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable ZIP Code Feature', 'vandel-booking'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="vandel_enable_zip_code_feature" 
                                   value="yes" 
                                   <?php checked(get_option('vandel_enable_zip_code_feature', 'no'), 'yes'); ?>>
                            <?php _e('Enable location-based pricing and service restrictions', 'vandel-booking'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render ZIP Code settings
     */
    private function renderZipCodeSettings() {
        // Pagination
        $page = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 10;
        $offset = ($page - 1) * $per_page;

        // Fetch ZIP Codes
        $zip_codes = $this->zip_code_model->getServiceableZipCodes([
            'limit' => $per_page,
            'offset' => $offset
        ]);
        $total_zip_codes = count($this->zip_code_model->getServiceableZipCodes());
        $total_pages = ceil($total_zip_codes / $per_page);

        ?>
        <div class="vandel-zip-code-management">
            <!-- Add ZIP Code Form -->
            <div class="vandel-zip-code-form">
                <h3><?php _e('Add New ZIP Code', 'vandel-booking'); ?></h3>
                <form method="post" action="">
    <?php wp_nonce_field('vandel_add_zip_code', 'vandel_zip_code_nonce'); ?>
                    <div class="vandel-row">
                        <div class="vandel-col">
                            <label><?php _e('ZIP Code', 'vandel-booking'); ?></label>
                            <input type="text" name="zip_code" required class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label><?php _e('City', 'vandel-booking'); ?></label>
                            <input type="text" name="city" required class="widefat">
                        </div>
                    </div>
                    <div class="vandel-row">
                        <div class="vandel-col">
                            <label><?php _e('State/Province', 'vandel-booking'); ?></label>
                            <input type="text" name="state" class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label><?php _e('Country', 'vandel-booking'); ?></label>
                            <input type="text" name="country" required class="widefat">
                        </div>
                    </div>
                    <div class="vandel-row">
                        <div class="vandel-col">
                            <label><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                            <div class="vandel-input-group">
                                <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                <input type="number" name="price_adjustment" step="0.01" min="-100" max="100" class="widefat">
                            </div>
                        </div>
                        <div class="vandel-col">
                            <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                            <div class="vandel-input-group">
                                <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                <input type="number" name="service_fee" step="0.01" min="0" class="widefat">
                            </div>
                        </div>
                    </div>
                    <div class="vandel-toggle-controls">
                        <div class="vandel-toggle-field">
                            <label class="vandel-toggle">
                                <input type="checkbox" name="is_serviceable" value="yes" checked>
                                <span class="vandel-toggle-slider"></span>
                            </label>
                            <span class="vandel-toggle-label"><?php _e('Serviceable Area', 'vandel-booking'); ?></span>
                        </div>
                    </div>
    <button type="submit" name="vandel_add_zip_code" class="button button-primary">
        <?php _e('Add ZIP Code', 'vandel-booking'); ?>
    </button>
                </form>
            </div>

            <!-- ZIP Code List -->
            <div class="vandel-zip-code-list">
                <h3><?php _e('Existing ZIP Codes', 'vandel-booking'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('ZIP Code', 'vandel-booking'); ?></th>
                            <th><?php _e('City', 'vandel-booking'); ?></th>
                            <th><?php _e('State', 'vandel-booking'); ?></th>
                            <th><?php _e('Country', 'vandel-booking'); ?></th>
                            <th><?php _e('Price Adj.', 'vandel-booking'); ?></th>
                            <th><?php _e('Service Fee', 'vandel-booking'); ?></th>
                            <th><?php _e('Serviceable', 'vandel-booking'); ?></th>
                            <th><?php _e('Actions', 'vandel-booking'); ?></th>



</tr>
                    </thead>
                    <tbody>
                        <?php if (empty($zip_codes)): ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <?php _e('No ZIP Codes found. Add your first ZIP Code above.', 'vandel-booking'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($zip_codes as $zip_code): ?>
                                <tr>
                                    <td><?php echo esc_html($zip_code->zip_code); ?></td>
                                    <td><?php echo esc_html($zip_code->city); ?></td>
                                    <td><?php echo esc_html($zip_code->state ?: 'N/A'); ?></td>
                                    <td><?php echo esc_html($zip_code->country); ?></td>
                                    <td>
                                        <?php 
                                        $price_adj = floatval($zip_code->price_adjustment);
                                        echo $price_adj > 0 ? '+' : '';
                                        echo Helpers::formatPrice($price_adj);
                                        ?>
                                    </td>
                                    <td><?php echo Helpers::formatPrice($zip_code->service_fee); ?></td>
                                    <td>
                                        <?php if ($zip_code->is_serviceable === 'yes'): ?>
                                            <span class="vandel-badge vandel-badge-success"><?php _e('Yes', 'vandel-booking'); ?></span>
                                        <?php else: ?>
                                            <span class="vandel-badge vandel-badge-danger"><?php _e('No', 'vandel-booking'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="vandel-row-actions">
                                            <a href="#" 
                                               class="vandel-edit-zip-code button button-small"
                                               data-zip-code="<?php echo esc_attr($zip_code->zip_code); ?>"
                                               data-city="<?php echo esc_attr($zip_code->city); ?>"
                                               data-state="<?php echo esc_attr($zip_code->state); ?>"
                                               data-country="<?php echo esc_attr($zip_code->country); ?>"
                                               data-price-adjustment="<?php echo esc_attr($zip_code->price_adjustment); ?>"
                                               data-service-fee="<?php echo esc_attr($zip_code->service_fee); ?>"
                                               data-is-serviceable="<?php echo esc_attr($zip_code->is_serviceable); ?>">
                                                <?php _e('Edit', 'vandel-booking'); ?>
                                            </a>
                                            <a href="<?php echo wp_nonce_url(
                                                admin_url('admin.php?page=vandel-dashboard&tab=settings&section=zip-codes&action=delete_zip_code&zip_code=' . urlencode($zip_code->zip_code)),
                                                'delete_zip_code_' . $zip_code->zip_code
                                            ); ?>" 
                                               class="vandel-delete-zip-code button button-small button-link-delete">
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

            <!-- Edit ZIP Code Modal -->
            <div id="vandel-edit-zip-code-modal" class="vandel-modal" style="display:none;">
                <div class="vandel-modal-content">
                    <span class="vandel-modal-close">&times;</span>
                    <h3><?php _e('Edit ZIP Code', 'vandel-booking'); ?></h3>
                    <form method="post" action="">
                        <?php wp_nonce_field('vandel_update_zip_code', 'vandel_zip_code_update_nonce'); ?>
                        <input type="hidden" name="original_zip_code" id="edit-original-zip-code">
                        
                        <div class="vandel-row">
                            <div class="vandel-col">
                                <label><?php _e('ZIP Code', 'vandel-booking'); ?></label>
                                <input type="text" name="zip_code" id="edit-zip-code" required class="widefat">
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('City', 'vandel-booking'); ?></label>
                                <input type="text" name="city" id="edit-city" required class="widefat">
                            </div>
                        </div>
                        
                        <div class="vandel-row">
                            <div class="vandel-col">
                                <label><?php _e('State/Province', 'vandel-booking'); ?></label>
                                <input type="text" name="state" id="edit-state" class="widefat">
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('Country', 'vandel-booking'); ?></label>
                                <input type="text" name="country" id="edit-country" required class="widefat">
                            </div>
                        </div>
                        
                        <div class="vandel-row">
                            <div class="vandel-col">
                                <label><?php _e('Price Adjustment', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="price_adjustment" id="edit-price-adjustment" 
                                           step="0.01" min="-100" max="100" class="widefat">
                                </div>
                            </div>
                            <div class="vandel-col">
                                <label><?php _e('Service Fee', 'vandel-booking'); ?></label>
                                <div class="vandel-input-group">
                                    <span class="vandel-input-prefix"><?php echo Helpers::getCurrencySymbol(); ?></span>
                                    <input type="number" name="service_fee" id="edit-service-fee" 
                                           step="0.01" min="0" class="widefat">
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
                            <?php _e('Update ZIP Code', 'vandel-booking'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- JavaScript for ZIP Code Management -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit ZIP Code Modal Handling
            const editButtons = document.querySelectorAll('.vandel-edit-zip-code');
            const modal = document.getElementById('vandel-edit-zip-code-modal');
            const modalClose = document.querySelector('.vandel-modal-close');

            editButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Populate modal fields
                    document.getElementById('edit-original-zip-code').value = this.dataset.zipCode;
                    document.getElementById('edit-zip-code').value = this.dataset.zipCode;
                    document.getElementById('edit-city').value = this.dataset.city;
                    document.getElementById('edit-state').value = this.dataset.state;
                    document.getElementById('edit-country').value = this.dataset.country;
                    document.getElementById('edit-price-adjustment').value = this.dataset.priceAdjustment;
                    document.getElementById('edit-service-fee').value = this.dataset.serviceFee;
                    
                    // Set checkbox state
                    const isServiceableCheckbox = document.getElementById('edit-is-serviceable');
                    isServiceableCheckbox.checked = this.dataset.isServiceable === 'yes';

                    // Show modal
                    modal.style.display = 'block';
                });
            });

            // Close modal
            modalClose.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Confirm delete
            const deleteButtons = document.querySelectorAll('.vandel-delete-zip-code');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('<?php _e('Are you sure you want to delete this ZIP code?', 'vandel-booking'); ?>')) {
                        e.preventDefault();
                    }
                });
            });
        });
        </script>
        <?php
    }
}