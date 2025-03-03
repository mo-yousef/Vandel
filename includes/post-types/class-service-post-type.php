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
            'show_in_menu' => false,
            'rewrite' => ['slug' => 'vandel-services'],
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-buddicons-community'
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
    }
    
    /**
     * Render meta box
     * 
     * @param WP_Post $post Current post object
     */
    public function renderMetaBox($post) {
        wp_nonce_field('vandel_service_details', 'vandel_service_details_nonce');
        
        $base_price = get_post_meta($post->ID, '_vandel_service_base_price', true);
        $subtitle = get_post_meta($post->ID, '_vandel_service_subtitle', true);
        $description = get_post_meta($post->ID, '_vandel_service_description', true);
        $selected_sub_services = get_post_meta($post->ID, '_vandel_assigned_sub_services', true) ?: [];
        
        // Include meta box template
        include VANDEL_PLUGIN_DIR . 'includes/admin/views/meta-box-service.php';
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
        
        // Save meta data
        $fields = [
            'vandel_service_subtitle' => '_vandel_service_subtitle',
            'vandel_service_description' => '_vandel_service_description',
            'vandel_service_base_price' => '_vandel_service_base_price'
        ];
        
        foreach ($fields as $post_key => $meta_key) {
            if (isset($_POST[$post_key])) {
                $value = ($post_key === 'vandel_service_description') ? 
                    sanitize_textarea_field($_POST[$post_key]) : 
                    sanitize_text_field($_POST[$post_key]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
        
        // Handle assigned sub-services
        $assigned_sub_services = isset($_POST['vandel_assigned_sub_services']) ? 
            array_map('intval', $_POST['vandel_assigned_sub_services']) : [];
        update_post_meta($post_id, '_vandel_assigned_sub_services', $assigned_sub_services);
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
            return 'Save';
        } elseif ($text === 'Update') {
            return 'Update';
        }
        
        return $translation;
    }
}