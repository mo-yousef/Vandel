<?php
namespace VandelBooking\PostTypes;

/**
 * Service Post Type
 */
class ServicePostType {
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register']);
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaData']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('manage_vandel_service_posts_columns', [$this, 'customColumns']);
        add_action('manage_vandel_service_posts_custom_column', [$this, 'populateCustomColumns'], 10, 2);
        add_filter('gettext', [$this, 'modifyPublishButton'], 10, 2);
    }
    
    /**
     * Register post type
     */
    public function register() {
        register_post_type('vandel_service', [
            'labels' => [
                'name' => __('Services', 'vandel-booking'),
                'singular_name' => __('Service', 'vandel-booking'),
                'add_new' => __('Add New Service', 'vandel-booking'),
                'add_new_item' => __('Add New Service', 'vandel-booking'),
                'edit_item' => __('Edit Service', 'vandel-booking'),
                'new_item' => __('New Service', 'vandel-booking'),
                'view_item' => __('View Service', 'vandel-booking'),
                'search_items' => __('Search Services', 'vandel-booking'),
                'not_found' => __('No services found', 'vandel-booking'),
                'not_found_in_trash' => __('No services found in Trash', 'vandel-booking'),
                'featured_image' => __('Service Icon', 'vandel-booking'),
                'set_featured_image' => __('Set service icon', 'vandel-booking'),
                'remove_featured_image' => __('Remove service icon', 'vandel-booking'),
                'use_featured_image' => __('Use as service icon', 'vandel-booking'),
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_menu' => 'vandel-dashboard',
            'menu_position' => 20,
            'rewrite' => ['slug' => 'vandel-services'],
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-buddicons-community',
            'show_in_rest' => true, // Enable Gutenberg editor
        ]);
    }
    
    /**
     * Add meta boxes
     */
    public function addMetaBoxes() {
        add_meta_box(
            'vandel_service_details',
            __('Service Details', 'vandel-booking'),
            [$this, 'renderMetaBox'],
            'vandel_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vandel_service_sub_services',
            __('Sub-Services', 'vandel-booking'),
            [$this, 'renderSubServicesMetaBox'],
            'vandel_service',
            'normal',
            'high'
        );
        
        add_meta_box(
            'vandel_service_preview',
            __('Service Preview', 'vandel-booking'),
            [$this, 'renderServicePreview'],
            'vandel_service',
            'side',
            'default'
        );
    }
    
    /**
     * Render meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderMetaBox($post) {
        wp_nonce_field('vandel_service_details', 'vandel_service_details_nonce');
        
        $base_price = get_post_meta($post->ID, '_vandel_service_base_price', true);
        $subtitle = get_post_meta($post->ID, '_vandel_service_subtitle', true);
        $description = get_post_meta($post->ID, '_vandel_service_description', true);
        $duration = get_post_meta($post->ID, '_vandel_service_duration', true);
        $is_popular = get_post_meta($post->ID, '_vandel_service_is_popular', true);
        $active = get_post_meta($post->ID, '_vandel_service_active', true) !== 'no'; // Default to active
        
        // Get currency symbol
        $currency_symbol = \VandelBooking\Helpers::getCurrencySymbol();
        ?>
        <div class="vandel-metabox">
            <div class="vandel-field">
                <label for="vandel_service_subtitle"><?php _e('Subtitle', 'vandel-booking'); ?></label>
                <input type="text" id="vandel_service_subtitle" name="vandel_service_subtitle" 
                    value="<?php echo esc_attr($subtitle); ?>" class="widefat" 
                    placeholder="<?php _e('e.g., "Professional home cleaning service"', 'vandel-booking'); ?>">
                <p class="description"><?php _e('A brief description that appears below the service title', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-field">
                <label for="vandel_service_description"><?php _e('Description', 'vandel-booking'); ?></label>
                <textarea id="vandel_service_description" name="vandel_service_description" 
                    rows="4" class="widefat" 
                    placeholder="<?php _e('Enter detailed service description', 'vandel-booking'); ?>"><?php echo esc_textarea($description); ?></textarea>
                <p class="description"><?php _e('Detailed description of the service and what it includes', 'vandel-booking'); ?></p>
            </div>
            
            <div class="vandel-row">
                <div class="vandel-col">
                    <div class="vandel-field">
                        <label for="vandel_service_base_price"><?php _e('Base Price', 'vandel-booking'); ?></label>
                        <div class="vandel-input-group">
                            <span class="vandel-input-prefix"><?php echo esc_html($currency_symbol); ?></span>
                            <input type="number" id="vandel_service_base_price" name="vandel_service_base_price" 
                                value="<?php echo esc_attr($base_price); ?>" step="0.01" min="0" 
                                placeholder="0.00" class="widefat">
                        </div>
                        <p class="description"><?php _e('Starting price for this service', 'vandel-booking'); ?></p>
                    </div>
                </div>
                
                <div class="vandel-col">
                    <div class="vandel-field">
                        <label for="vandel_service_duration"><?php _e('Service Duration (minutes)', 'vandel-booking'); ?></label>
                        <input type="number" id="vandel_service_duration" name="vandel_service_duration" 
                            value="<?php echo esc_attr($duration); ?>" min="0" step="5" class="widefat" 
                            placeholder="<?php _e('e.g., 60', 'vandel-booking'); ?>">
                        <p class="description"><?php _e('Approximate time to complete this service', 'vandel-booking'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="vandel-toggle-controls">
                <div class="vandel-toggle-field">
                    <label class="vandel-toggle">
                        <input type="checkbox" name="vandel_service_is_popular" value="yes" <?php checked($is_popular, 'yes'); ?>>
                        <span class="vandel-toggle-slider"></span>
                    </label>
                    <span class="vandel-toggle-label"><?php _e('Mark as Popular Service', 'vandel-booking'); ?></span>
                </div>
                
                <div class="vandel-toggle-field">
                    <label class="vandel-toggle">
                        <input type="checkbox" name="vandel_service_active" value="yes" <?php checked($active); ?>>
                        <span class="vandel-toggle-slider"></span>
                    </label>
                    <span class="vandel-toggle-label"><?php _e('Service Active', 'vandel-booking'); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render sub-services meta box
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderSubServicesMetaBox($post) {
        $assigned_sub_services = get_post_meta($post->ID, '_vandel_assigned_sub_services', true) ?: [];
        
        $sub_services = get_posts([
            'post_type' => 'vandel_sub_service',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);
        ?>
        <div class="vandel-metabox">
            <?php if (empty($sub_services)): ?>
                <div class="vandel-notice vandel-notice-info">
                    <p><?php _e('No sub-services available. Create some sub-services first.', 'vandel-booking'); ?></p>
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=vandel_sub_service')); ?>" class="button button-primary">
                        <?php _e('Create Sub-Service', 'vandel-booking'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="vandel-sub-services-grid">
                    <?php foreach ($sub_services as $sub_service):
                        $is_assigned = in_array($sub_service->ID, $assigned_sub_services);
                        $sub_type = get_post_meta($sub_service->ID, '_vandel_sub_service_type', true);
                        $sub_price = get_post_meta($sub_service->ID, '_vandel_sub_service_price', true);
                        $formatted_price = \VandelBooking\Helpers::formatPrice($sub_price);
                        $subtitle = get_post_meta($sub_service->ID, '_vandel_sub_service_subtitle', true);
                        $icon_class = $this->getSubServiceIconClass($sub_type);
                    ?>
                        <div class="vandel-sub-service-card <?php echo $is_assigned ? 'assigned' : ''; ?>">
                            <div class="vandel-sub-service-header">
                                <div class="vandel-sub-service-checkbox">
                                    <input type="checkbox" id="sub_service_<?php echo $sub_service->ID; ?>" 
                                        name="vandel_assigned_sub_services[]" 
                                        value="<?php echo $sub_service->ID; ?>" 
                                        <?php checked($is_assigned); ?>>
                                    <label for="sub_service_<?php echo $sub_service->ID; ?>"></label>
                                </div>
                                <div class="vandel-sub-service-type">
                                    <span class="dashicons <?php echo $icon_class; ?>"></span>
                                    <span class="vandel-sub-service-type-label"><?php echo ucfirst($sub_type); ?></span>
                                </div>
                            </div>
                            <div class="vandel-sub-service-body">
                                <h4><?php echo esc_html($sub_service->post_title); ?></h4>
                                <?php if (!empty($subtitle)): ?>
                                    <p class="vandel-sub-service-subtitle"><?php echo esc_html($subtitle); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="vandel-sub-service-footer">
                                <span class="vandel-sub-service-price">
                                    <?php echo esc_html($formatted_price); ?>
                                </span>
                                <a href="<?php echo get_edit_post_link($sub_service->ID); ?>" class="vandel-sub-service-edit">
                                    <span class="dashicons dashicons-edit"></span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="vandel-actions">
                    <a href="<?php echo esc_url(admin_url('post-new.php?post_type=vandel_sub_service')); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-plus-alt"></span> 
                        <?php _e('Add New Sub-Service', 'vandel-booking'); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render service preview
     * 
     * @param \WP_Post $post Current post object
     */
    public function renderServicePreview($post) {
        $subtitle = get_post_meta($post->ID, '_vandel_service_subtitle', true);
        $base_price = get_post_meta($post->ID, '_vandel_service_base_price', true);
        $formatted_price = \VandelBooking\Helpers::formatPrice($base_price);
        $duration = get_post_meta($post->ID, '_vandel_service_duration', true);
        $is_popular = get_post_meta($post->ID, '_vandel_service_is_popular', true);
        
        $featured_image = get_the_post_thumbnail_url($post->ID, 'thumbnail');
        $placeholder_image = VANDEL_PLUGIN_URL . 'assets/images/service-placeholder.jpg';
        $image_url = $featured_image ? $featured_image : $placeholder_image;
        ?>
        <div class="vandel-preview-card">
            <div class="vandel-preview-header">
                <div class="vandel-preview-image" style="background-image: url(<?php echo esc_url($image_url); ?>);">
                    <?php if ($is_popular === 'yes'): ?>
                        <span class="vandel-preview-badge"><?php _e('Popular', 'vandel-booking'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="vandel-preview-body">
                <h4 class="vandel-preview-title"><?php echo esc_html($post->post_title); ?></h4>
                <?php if (!empty($subtitle)): ?>
                    <p class="vandel-preview-subtitle"><?php echo esc_html($subtitle); ?></p>
                <?php endif; ?>
                <div class="vandel-preview-details">
                    <?php if (!empty($base_price)): ?>
                        <div class="vandel-preview-price"><?php echo esc_html($formatted_price); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($duration)): ?>
                        <div class="vandel-preview-duration">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo esc_html($duration); ?> <?php _e('min', 'vandel-booking'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if (empty($featured_image)): ?>
            <div class="vandel-notice vandel-notice-warning">
                <p><?php _e('Add a featured image to make your service more appealing.', 'vandel-booking'); ?></p>
                <a href="#" class="button" onclick="jQuery('#set-post-thumbnail').click(); return false;">
                    <?php _e('Set Featured Image', 'vandel-booking'); ?>
                </a>
            </div>
        <?php endif; ?>
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
        if (!isset($_POST['post_type']) || $_POST['post_type'] !== 'vandel_service') {
            return;
        }
        
        // Verify nonce
        if (!isset($_POST['vandel_service_details_nonce']) || 
            !wp_verify_nonce($_POST['vandel_service_details_nonce'], 'vandel_service_details')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save basic fields
        $text_fields = [
            'vandel_service_subtitle' => '_vandel_service_subtitle',
            'vandel_service_base_price' => '_vandel_service_base_price',
            'vandel_service_duration' => '_vandel_service_duration',
        ];
        
        foreach ($text_fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($_POST[$post_key]));
            }
        }
        
        // Save description
        if (isset($_POST['vandel_service_description'])) {
            update_post_meta($post_id, '_vandel_service_description', sanitize_textarea_field($_POST['vandel_service_description']));
        }
        
        // Save checkbox fields
        $checkbox_fields = [
            'vandel_service_is_popular' => '_vandel_service_is_popular',
            'vandel_service_active' => '_vandel_service_active',
        ];
        
        foreach ($checkbox_fields as $post_key => $meta_key) {
            $value = isset($_POST[$post_key]) ? 'yes' : 'no';
            update_post_meta($post_id, $meta_key, $value);
        }
        
        // Handle assigned sub-services
        $assigned_sub_services = isset($_POST['vandel_assigned_sub_services']) ? 
            array_map('intval', $_POST['vandel_assigned_sub_services']) : [];
        update_post_meta($post_id, '_vandel_assigned_sub_services', $assigned_sub_services);
    }
    
    /**
     * Enqueue assets for the admin
     * 
     * @param string $hook Current admin page
     */
    public function enqueueAssets($hook) {
        global $post_type, $post;
        
        // Only load on add/edit service screens
        if (($hook === 'post-new.php' || $hook === 'post.php') && $post_type === 'vandel_service') {
            wp_enqueue_style(
                'vandel-service-admin',
                VANDEL_PLUGIN_URL . 'assets/css/admin/service-admin.css',
                [],
                VANDEL_VERSION
            );
            
            wp_enqueue_script(
                'vandel-service-admin',
                VANDEL_PLUGIN_URL . 'assets/js/admin/service-admin.js',
                ['jquery', 'jquery-ui-sortable'],
                VANDEL_VERSION,
                true
            );
            
            // Localize script with data
            wp_localize_script('vandel-service-admin', 'vandelServiceAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vandel_service_admin_nonce'),
                'serviceId' => $post ? $post->ID : 0,
                'strings' => [
                    'confirmDelete' => __('Are you sure you want to remove this sub-service?', 'vandel-booking'),
                    'saveSuccess' => __('Changes saved successfully.', 'vandel-booking'),
                    'saveError' => __('Error saving changes.', 'vandel-booking'),
                ]
            ]);
        }
    }
    
    /**
     * Define custom columns for service list
     * 
     * @param array $columns Default columns
     * @return array Modified columns
     */
    public function customColumns($columns) {
        $new_columns = [];
        
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['service_icon'] = __('Icon', 'vandel-booking');
                $new_columns['service_price'] = __('Base Price', 'vandel-booking');
                $new_columns['service_duration'] = __('Duration', 'vandel-booking');
                $new_columns['service_sub_services'] = __('Sub-Services', 'vandel-booking');
                $new_columns['service_status'] = __('Status', 'vandel-booking');
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
            case 'service_icon':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [40, 40]);
                } else {
                    echo '<span class="dashicons dashicons-format-image" style="color:#ccc;font-size:32px;"></span>';
                }
                break;
                
            case 'service_price':
                $base_price = get_post_meta($post_id, '_vandel_service_base_price', true);
                if (!empty($base_price)) {
                    echo \VandelBooking\Helpers::formatPrice($base_price);
                } else {
                    echo '—';
                }
                break;
                
            case 'service_duration':
                $duration = get_post_meta($post_id, '_vandel_service_duration', true);
                if (!empty($duration)) {
                    printf('%d %s', $duration, __('min', 'vandel-booking'));
                } else {
                    echo '—';
                }
                break;
                
            case 'service_sub_services':
                $sub_services = get_post_meta($post_id, '_vandel_assigned_sub_services', true) ?: [];
                $count = count($sub_services);
                
                if ($count > 0) {
                    echo '<span class="vandel-badge">' . $count . '</span>';
                } else {
                    echo '—';
                }
                break;
                
            case 'service_status':
                $is_active = get_post_meta($post_id, '_vandel_service_active', true) !== 'no';
                $is_popular = get_post_meta($post_id, '_vandel_service_is_popular', true) === 'yes';
                
                if ($is_active) {
                    echo '<span class="vandel-status vandel-status-active">' . __('Active', 'vandel-booking') . '</span>';
                } else {
                    echo '<span class="vandel-status vandel-status-inactive">' . __('Inactive', 'vandel-booking') . '</span>';
                }
                
                if ($is_popular) {
                    echo ' <span class="vandel-status vandel-status-featured">' . __('Popular', 'vandel-booking') . '</span>';
                }
                break;
        }
    }
    
    /**
     * Get dashicon class for sub-service type
     * 
     * @param string $type Sub-service type
     * @return string Dashicon class
     */
    private function getSubServiceIconClass($type) {
        $icons = [
            'number' => 'dashicons-calculator',
            'text' => 'dashicons-text',
            'textarea' => 'dashicons-editor-paragraph',
            'dropdown' => 'dashicons-menu-alt',
            'checkbox' => 'dashicons-yes-alt',
            'radio' => 'dashicons-marker',
        ];
        
        return isset($icons[$type]) ? $icons[$type] : 'dashicons-plus-alt';
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
        
        if ($post_type !== 'vandel_service') {
            return $translation;
        }
        
        if ($text === 'Publish') {
            return __('Save Service', 'vandel-booking');
        } elseif ($text === 'Update') {
            return __('Update Service', 'vandel-booking');
        }
        
        return $translation;
    }
}