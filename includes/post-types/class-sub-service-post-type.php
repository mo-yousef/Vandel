<?php
namespace VandelBooking\PostTypes;

/**
 * Sub-Service Post Type
 */
class SubServicePostType {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaData']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('manage_vandel_sub_service_posts_columns', [$this, 'customColumns']);
        add_action('manage_vandel_sub_service_posts_custom_column', [$this, 'populateCustomColumns'], 10, 2);
        add_filter('gettext', [$this, 'modifyPublishButton'], 10, 2);
        add_action('wp_ajax_vandel_fetch_option_template', [$this, 'ajaxFetchOptionTemplate']);
    }
    
    /**
     * Register post type
     */
    public function register() {
        register_post_type('vandel_sub_service', [
            'labels' => [
                'name' => __('Sub-Services', 'vandel-booking'),
                'singular_name' => __('Sub-Service', 'vandel-booking'),
                'add_new' => __('Add New Sub-Service', 'vandel-booking'),
                'add_new_item' => __('Add New Sub-Service', 'vandel-booking'),
                'edit_item' => __('Edit Sub-Service', 'vandel-booking'),
                'new_item' => __('New Sub-Service', 'vandel-booking'),
                'view_item' => __('View Sub-Service', 'vandel-booking'),
                'search_items' => __('Search Sub-Services', 'vandel-booking'),
                'not_found' => __('No sub-services found', 'vandel-booking'),
                'not_found_in_trash' => __('No sub-services found in Trash', 'vandel-booking'),
            ],
            'public' => true,
            'has_archive' => false,
            'show_in_menu' => 'vandel-dashboard',
            'menu_position' => 21,
            'rewrite' => ['slug' => 'vandel-sub-services'],
            'supports' => ['title'],
            'menu_icon' => 'dashicons-buddicons-groups',
            'show_in_rest' => true, // Enable Gutenberg editor
        ]);
    }
    
    /**
     * Add meta boxes
     */
    public function addMetaBoxes() {
        add_meta_box(
            'vandel_sub_service_details',
            __('Sub-Service Details', 'vandel-booking'),
            [$this, 'renderDetailsMetaBox'],
            'vandel_sub_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vandel_sub_service_configuration',
            __('Input Configuration', 'vandel-booking'),
            [$this, 'renderConfigurationMetaBox'],
            'vandel_sub_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vandel_sub_service_options',
            __('Options', 'vandel-booking'),
            [$this, 'renderOptionsMetaBox'],
            'vandel_sub_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vandel_sub_service_parent',
            __('Parent Services', 'vandel-booking'),
            [$this, 'renderParentServicesMetaBox'],
            'vandel_sub_service',
            'side',
            'default'
        );
        
        add_meta_box(
            'vandel_sub_service_preview',
            __('Sub-Service Preview', 'vandel-booking'),
            [$this, 'renderPreviewMetaBox'],
            'vandel_sub_service',
            'side',
            'default'
        );
    }
    
    /**
     * Render details meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderDetailsMetaBox($post) {
        wp_nonce_field('vandel_sub_service_details', 'vandel_sub_service_details_nonce');
        
        $price = get_post_meta($post->ID, '_vandel_sub_service_price', true);
        $subtitle = get_post_meta($post->ID, '_vandel_sub_service_subtitle', true);
        $description = get_post_meta($post->ID, '_vandel_sub_service_description', true);
        $is_required = get_post_meta($post->ID, '_vandel_sub_service_required', true) === 'yes';
        $active = get_post_meta($post->ID, '_vandel_sub_service_active', true) !== 'no'; // Default to active
        
        // Get currency symbol
        $currency_symbol = \VandelBooking\Helpers::getCurrencySymbol();
        ?>
        <div class="vandel-metabox">
            <div class="vandel-field">
                <label for="vandel_sub_service_subtitle"><?php _e('Subtitle', 'vandel-booking'); ?></label>
                <input type="text" id="vandel_sub_service_subtitle" name="vandel_sub_service_subtitle" 
                    value="<?php echo esc_attr($subtitle); ?>" class="widefat" 
                    placeholder="<?php _e('e.g., "Choose the number of bedrooms"', 'vandel-booking'); ?>">
                <p class="description"><?php _e('A brief description that appears below the sub-service title', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-field">
                <label for="vandel_sub_service_description"><?php _e('Description', 'vandel-booking'); ?></label>
                <textarea id="vandel_sub_service_description" name="vandel_sub_service_description" 
                    rows="3" class="widefat" 
                    placeholder="<?php _e('Enter detailed description', 'vandel-booking'); ?>"><?php echo esc_textarea($description); ?></textarea>
                <p class="description"><?php _e('Additional information to help customers understand this option', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-row">
                <div class="vandel-col">
                    <div class="vandel-field">
                        <label for="vandel_sub_service_price"><?php _e('Base Price', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span class="vandel-input-prefix"><?php echo esc_html($currency_symbol); ?></span>
                            <input type="number" id="vandel_sub_service_price" name="vandel_sub_service_price" 
                                value="<?php echo esc_attr($price); ?>" step="0.01" min="0" 
                                placeholder="0.00" class="widefat">
                        </div>
                        <p class="description"><?php _e('Starting price for this option (can be overridden by specific option values)', 'vandel-booking'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="vandel-toggle-controls">
                <div class="vandel-toggle-field">
                    <label class="vandel-toggle">
                        <input type="checkbox" name="vandel_sub_service_required" value="yes" <?php checked($is_required); ?>>
                        <span class="vandel-toggle-slider"></span>
                    </label>
                    <span class="vandel-toggle-label"><?php _e('Required Field', 'vandel-booking'); ?></span>
                    <p class="description"><?php _e('If enabled, customers must fill out this field to complete their booking', 'vandel-booking'); ?></p>
                </div>
                
                <div class="vandel-toggle-field">
                    <label class="vandel-toggle">
                        <input type="checkbox" name="vandel_sub_service_active" value="yes" <?php checked($active); ?>>
                        <span class="vandel-toggle-slider"></span>
                    </label>
                    <span class="vandel-toggle-label"><?php _e('Active', 'vandel-booking'); ?></span>
                    <p class="description"><?php _e('If disabled, this option will not appear on the booking form', 'vandel-booking'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save meta data
     * 
     * @param int $post_id Post ID
     */
    public function saveMetaData($post_id) {
        // Check if we're doing an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check post type
        if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'vandel_sub_service') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['vandel_sub_service_details_nonce']) || 
            !wp_verify_nonce($_POST['vandel_sub_service_details_nonce'], 'vandel_sub_service_details')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save basic fields
        $text_fields = [
            'vandel_sub_service_subtitle' => '_vandel_sub_service_subtitle',
            'vandel_sub_service_price' => '_vandel_sub_service_price',
            'vandel_sub_service_placeholder' => '_vandel_sub_service_placeholder',
            'vandel_sub_service_type' => '_vandel_sub_service_type',
            'vandel_sub_service_min' => '_vandel_sub_service_min',
            'vandel_sub_service_max' => '_vandel_sub_service_max',
            'vandel_sub_service_default' => '_vandel_sub_service_default',
        ];
        
        foreach ($text_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        
        // Save description
        if (isset($_POST['vandel_sub_service_description'])) {
            update_post_meta($post_id, '_vandel_sub_service_description', sanitize_textarea_field($_POST['vandel_sub_service_description']));
        }
        
        // Save checkbox fields
        $checkbox_fields = [
            'vandel_sub_service_required' => '_vandel_sub_service_required',
            'vandel_sub_service_active' => '_vandel_sub_service_active',
        ];
        
        foreach ($checkbox_fields as $post_key => $meta_key) {
            $value = isset($_POST[$post_key]) ? 'yes' : 'no';
            update_post_meta($post_id, $meta_key, $value);
        }
        
        // Save options for dropdown, checkbox, and radio types
        if (isset($_POST['vandel_sub_service_options']) && is_array($_POST['vandel_sub_service_options'])) {
            $options = [];
            
            foreach ($_POST['vandel_sub_service_options'] as $option) {
                // Filter out empty options
                if (!empty($option['name'])) {
                    $options[] = [
                        'name' => sanitize_text_field($option['name']),
                        'price' => (float) sanitize_text_field($option['price'] ?: 0),
                    ];
                }
            }
            
            update_post_meta($post_id, '_vandel_sub_service_options', json_encode($options));
        } else {
            // If no options provided but input type requires them, save an empty array
            $input_type = isset($_POST['vandel_sub_service_type']) ? $_POST['vandel_sub_service_type'] : '';
            if (in_array($input_type, ['dropdown', 'checkbox', 'radio'])) {
                update_post_meta($post_id, '_vandel_sub_service_options', json_encode([]));
            }
        }
    }
    
    /**
     * Enqueue assets for the admin
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        global $post_type, $post;
        
        // Only load on add/edit sub-service screens
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post_type === 'vandel_sub_service') {
            wp_enqueue_style(
                'vandel-sub-service-admin',
                VANDEL_PLUGIN_URL . 'assets/css/admin/sub-service-admin.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-sub-service-admin',
                VANDEL_PLUGIN_URL . 'assets/js/admin/sub-service-admin.js',
                ['jquery', 'jquery-ui-sortable'],
                VANDEL_VERSION,
                true
            );
            
            // Localize script with data
            wp_localize_script('vandel-sub-service-admin', 'vandelSubServiceAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_sub_service_admin_nonce'),
                'postId' => $post ? $post->ID : 0,
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to remove this option?', 'vandel-booking'),
                    'noOptions' => __('No options defined yet. Add some options below.', 'vandel-booking'),
                    'saveSuccess' => __('Changes saved successfully.', 'vandel-booking'),
                    'saveError' => __('Error saving changes.', 'vandel-booking'),
                ]
            ]);
        }
    }
    
    /**
     * Define custom columns for sub-service list
     * 
     * @param array $columns Default columns
     * @return array Modified columns
     */
    public function customColumns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['sub_service_type'] = __('Input Type', 'vandel-booking');
                $new_columns['sub_service_price'] = __('Price', 'vandel-booking');
                $new_columns['sub_service_options'] = __('Options', 'vandel-booking');
                $new_columns['sub_service_parent'] = __('Used In', 'vandel-booking');
            } else if ($key !== 'date') {
                $new_columns[$key] = $value;
            }
        }
        
        $new_columns['date'] = __('Date', 'vandel-booking');
        
        return $new_columns;
    }
    
    /**
     * Populate custom columns with data
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function populateCustomColumns($column, $post_id) {
        switch ($column) {
            case 'sub_service_type':
                $input_type = get_post_meta($post_id, '_vandel_sub_service_type', true) ?: 'text';
                $type_icons = [
                    'text' => 'dashicons-editor-textcolor',
                    'textarea' => 'dashicons-editor-paragraph',
                    'number' => 'dashicons-calculator',
                    'dropdown' => 'dashicons-menu-alt',
                    'checkbox' => 'dashicons-yes-alt',
                    'radio' => 'dashicons-marker',
                ];
                
                $icon_class = isset($type_icons[$input_type]) ? $type_icons[$input_type] : 'dashicons-plus-alt';
                
                echo '<span class="vandel-type-icon">';
                echo '<span class="dashicons ' . esc_attr($icon_class) . '"></span>';
                echo esc_html(ucfirst($input_type));
                echo '</span>';
                break;
                
            case 'sub_service_price':
                $price = get_post_meta($post_id, '_vandel_sub_service_price', true);
                if (!empty($price)) {
                    echo \VandelBooking\Helpers::formatPrice($price);
                } else {
                    echo '—';
                }
                break;
                
            case 'sub_service_options':
                $input_type = get_post_meta($post_id, '_vandel_sub_service_type', true) ?: 'text';
                $options = json_decode(get_post_meta($post_id, '_vandel_sub_service_options', true), true) ?: [];
                
                if (in_array($input_type, ['dropdown', 'checkbox', 'radio'])) {
                    $count = count($options);
                    
                    if ($count > 0) {
                        echo '<span class="vandel-badge">' . $count . '</span>';
                    } else {
                        echo '<span class="vandel-badge vandel-badge-warning">0</span>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'sub_service_parent':
                // Find services that have this sub-service assigned
                $services = get_posts([
                    'post_type' => 'vandel_service',
                    'numberposts' => -1,
                    'fields' => 'ids',
                ]);
                
                $parent_services = [];
                foreach ($services as $service_id) {
                    $assigned_sub_services = get_post_meta($service_id, '_vandel_assigned_sub_services', true) ?: [];
                    if (in_array($post_id, $assigned_sub_services)) {
                        $parent_services[] = $service_id;
                    }
                }
                
                if (!empty($parent_services)) {
                    echo '<span class="vandel-badge">' . count($parent_services) . '</span>';
                } else {
                    echo '<span class="vandel-badge vandel-badge-danger">0</span>';
                }
                break;
        }
    }
    
    /**
     * Modify publish button text
     * 
     * @param string $translation Translated text
     * @param string $text Original text
     * @return string Modified text
     */
    public function modifyPublishButton($translation, $text) {
        global $post_type;
        
        if ($post_type !== 'vandel_sub_service') {
            return $translation;
        }
        
        if ($text === 'Publish') {
            return __('Save Sub-Service', 'vandel-booking');
        } elseif ($text === 'Update') {
            return __('Update Sub-Service', 'vandel-booking');
        }
        
        return $translation;
    }
}
    
    /**
     * Render options meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderOptionsMetaBox($post) {
        $input_type = get_post_meta($post->ID, '_vandel_sub_service_type', true) ?: 'text';
        $options = json_decode(get_post_meta($post->ID, '_vandel_sub_service_options', true), true) ?: [];
        $currency_symbol = \VandelBooking\Helpers::getCurrencySymbol();
        
        // Only show for select, checkbox, and radio types
        $option_based_types = ['dropdown', 'checkbox', 'radio'];
        $display_style = in_array($input_type, $option_based_types) ? '' : 'display:none;';
        ?>
        <div class="vandel-metabox" id="vandel-options-container" style="<?php echo $display_style; ?>">
            <?php if (empty($options)): ?>
                <div class="vandel-notice vandel-notice-info">
                    <p><?php _e('No options defined yet. Add some options below.', 'vandel-booking'); ?></p>
                </div>
            <?php endif; ?>
            
            <div id="vandel-options-list" class="vandel-sortable">
                <?php 
                if (!empty($options)) {
                    foreach ($options as $index => $option) {
                        $this->renderOptionRow($index, $option, $currency_symbol);
                    }
                }
                ?>
            </div>
            
            <div class="vandel-actions">
                <button type="button" id="vandel-add-option" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> 
                    <?php _e('Add Option', 'vandel-booking'); ?>
                </button>
            </div>
            
            <!-- Template for new option row -->
            <script type="text/template" id="vandel-option-template">
                <?php $this->renderOptionRow('{{index}}', ['name' => '', 'price' => ''], $currency_symbol); ?>
            </script>
        </div>
        <?php
    }
    
    /**
     * Render a single option row
     * 
     * @param int|string $index Option index
     * @param array $option Option data
     * @param string $currency_symbol Currency symbol
     */
    private function renderOptionRow($index, $option, $currency_symbol) {
        ?>
        <div class="vandel-option-row" data-index="<?php echo esc_attr($index); ?>">
            <div class="vandel-option-handle">
                <span class="dashicons dashicons-menu"></span>
            </div>
            
            <div class="vandel-option-fields">
                <div class="vandel-field">
                    <input type="text" 
                        name="vandel_sub_service_options[<?php echo esc_attr($index); ?>][name]" 
                        value="<?php echo esc_attr($option['name']); ?>" 
                        placeholder="<?php _e('Option name', 'vandel-booking'); ?>" 
                        class="widefat">
                </div>
                
                <div class="vandel-field vandel-field-narrow">
                    <div class="vandel-input-group">
                        <span class="vandel-input-prefix"><?php echo esc_html($currency_symbol); ?></span>
                        <input type="number" 
                            name="vandel_sub_service_options[<?php echo esc_attr($index); ?>][price]" 
                            value="<?php echo esc_attr($option['price']); ?>" 
                            step="0.01" min="0" 
                            placeholder="0.00" 
                            class="widefat">
                    </div>
                </div>
            </div>
            
            <div class="vandel-option-actions">
                <button type="button" class="vandel-remove-option button">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for fetching option template
     */
    public function ajaxFetchOptionTemplate() {
        check_ajax_referer('vandel_sub_service_admin_nonce', 'nonce');
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : 0;
        $currency_symbol = \VandelBooking\Helpers::getCurrencySymbol();
        
        ob_start();
        $this->renderOptionRow($index, ['name' => '', 'price' => ''], $currency_symbol);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Render parent services meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderParentServicesMetaBox($post) {
        $services = get_posts([
            'post_type' => 'vandel_service',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        
        if (empty($services)) {
            ?>
            <div class="vandel-notice vandel-notice-warning">
                <p><?php _e('No services available. Create a service first.', 'vandel-booking'); ?></p>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=vandel_service')); ?>" class="button button-primary">
                    <?php _e('Create Service', 'vandel-booking'); ?>
                </a>
            </div>
            <?php
            return;
        }
        
        // Get all services that have this sub-service assigned
        $parent_services = [];
        foreach ($services as $service) {
            $assigned_sub_services = get_post_meta($service->ID, '_vandel_assigned_sub_services', true) ?: [];
            if (in_array($post->ID, $assigned_sub_services)) {
                $parent_services[] = $service->ID;
            }
        }
        ?>
        <div class="vandel-parent-services-list">
            <p class="description"><?php _e('This sub-service appears in the following services:', 'vandel-booking'); ?></p>
            
            <?php if (empty($parent_services)): ?>
                <div class="vandel-notice vandel-notice-warning">
                    <p><?php _e('This sub-service is not assigned to any service yet.', 'vandel-booking'); ?></p>
                </div>
            <?php else: ?>
                <ul class="vandel-service-list">
                    <?php foreach ($parent_services as $service_id):
                        $service = get_post($service_id);
                        if (!$service) continue;
                    ?>
                        <li class="vandel-service-item">
                            <a href="<?php echo get_edit_post_link($service_id); ?>">
                                <?php echo esc_html($service->post_title); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <div class="vandel-actions">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=vandel_service')); ?>" class="button">
                    <?php _e('Manage Services', 'vandel-booking'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get icon for input type
     * 
     * @param string $type Input type
     * @return string Dashicon class
     */
    private function getTypeIcon($type) {
        $icons = [
            'text' => 'dashicons-editor-textcolor',
            'textarea' => 'dashicons-editor-paragraph',
            'number' => 'dashicons-calculator',
            'dropdown' => 'dashicons-menu-alt',
            'checkbox' => 'dashicons-yes-alt',
            'radio' => 'dashicons-marker',
        ];
        
        return isset($icons[$type]) ? $icons[$type] : 'dashicons-plus-alt';
    }
    
    /**
     * Render preview meta box
     * 
     * @param \WP_Post $post Current post object
     */
        $input_type = get_post_meta($post->ID, '_vandel_sub_service_type', true) ?: 'text';
        $placeholder = get_post_meta($post->ID, '_vandel_sub_service_placeholder', true);
        $options = json_decode(get_post_meta($post->ID, '_vandel_sub_service_options', true), true) ?: [];
        
        $input_type_labels = [
            'text' => __('Text Input', 'vandel-booking'),
            'textarea' => __('Text Area', 'vandel-booking'),
            'number' => __('Number', 'vandel-booking'),
            'dropdown' => __('Dropdown', 'vandel-booking'),
            'checkbox' => __('Checkboxes', 'vandel-booking'),
            'radio' => __('Radio Buttons', 'vandel-booking'),
        ];
        
        $type_label = $input_type_labels[$input_type] ?? $input_type;
        ?>
        <div class="vandel-preview-meta">
            <div class="vandel-preview-header">
                <span class="vandel-input-type-badge">
                    <span class="dashicons <?php echo $this->getTypeIcon($input_type); ?>"></span>
                    <?php echo esc_html($type_label); ?>
                </span>
            </div>
            
            <div class="vandel-preview-form">
                <div class="vandel-preview-label">
                    <?php echo esc_html($post->post_title); ?>
                </div>
                
                <?php switch ($input_type):
                    case 'text': ?>
                        <input type="text" class="vandel-preview-input" 
                            placeholder="<?php echo esc_attr($placeholder); ?>" disabled>
                        <?php break;
                        
                    case 'textarea': ?>
                        <textarea class="vandel-preview-input" rows="3" 
                            placeholder="<?php echo esc_attr($placeholder); ?>" disabled></textarea>
                        <?php break;
                        
                    case 'number': ?>
                        <input type="number" class="vandel-preview-input" 
                            placeholder="<?php echo esc_attr($placeholder); ?>" disabled>
                        <?php break;
                        
                    case 'dropdown': ?>
                        <select class="vandel-preview-input" disabled>
                            <option value=""><?php echo esc_html($placeholder ?: __('Select an option', 'vandel-booking')); ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value=""><?php echo esc_html($option['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php break;
                        
                    case 'checkbox': ?>
                        <div class="vandel-preview-checkboxes">
                            <?php if (empty($options)): ?>
                                <div class="vandel-preview-placeholder">
                                    <?php _e('No options defined', 'vandel-booking'); ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($options as $index => $option): ?>
                                    <div class="vandel-preview-checkbox">
                                        <input type="checkbox" disabled>
                                        <label><?php echo esc_html($option['name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php break;
                        
                    case 'radio': ?>
                        <div class="vandel-preview-radios">
                            <?php if (empty($options)): ?>
                                <div class="vandel-preview-placeholder">
                                    <?php _e('No options defined', 'vandel-booking'); ?>
                                </div>
                            <?php else: ?>
                                <?php foreach ($options as $index => $option): ?>
                                    <div class="vandel-preview-radio">
                                        <input type="radio" disabled>
                                        <label><?php echo esc_html($option['name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php break;
                        
                    default: ?>
                        <div class="vandel-preview-placeholder">
                            <?php _e('Preview not available for this input type', 'vandel-booking'); ?>
                        </div>
                <?php endswitch; ?>
            </div>
        </div>
        <?php
    }
    ?>