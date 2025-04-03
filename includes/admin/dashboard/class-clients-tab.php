<?php
namespace VandelBooking\Admin\Dashboard;

/**
 * Clients Tab
 * Handles the clients listing and management tab
 */
class Clients_Tab implements Tab_Interface {
    /**
     * Register hooks specific to this tab
     */
    public function register_hooks() {
        // Register hooks for any client actions
        add_action('admin_init', [$this, 'handle_client_actions']);
    }
    
    /**
     * Process any actions for this tab
     */
    public function process_actions() {
        // Process client bulk actions
        if (isset($_POST['vandel_bulk_action']) && isset($_POST['client_ids']) && is_array($_POST['client_ids'])) {
            $this->process_bulk_actions();
        }
    }
    
    /**
     * Handle client actions like edit, delete
     */
    public function handle_client_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'vandel-dashboard' || !isset($_GET['tab']) || $_GET['tab'] !== 'clients') {
            return;
        }
        
        // Handle individual client actions
        if (isset($_GET['action']) && isset($_GET['client_id']) && is_numeric($_GET['client_id'])) {
            $client_id = intval($_GET['client_id']);
            $action = sanitize_key($_GET['action']);
            
            // Verify nonce for delete action
            if ($action === 'delete_client') {
                if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'delete_client_' . $client_id)) {
                    wp_die(__('Security check failed', 'vandel-booking'));
                    return;
                }
                
                $this->delete_client($client_id);
                wp_redirect(admin_url('admin.php?page=vandel-dashboard&tab=clients&message=client_deleted'));
                exit;
            }
        }
    }
    
    /**
     * Process bulk actions
     */
    private function process_bulk_actions() {
        // Verify nonce
        if (!isset($_POST['vandel_bulk_nonce']) || !wp_verify_nonce($_POST['vandel_bulk_nonce'], 'vandel_bulk_client_actions')) {
            return;
        }
        
        $client_ids = array_map('intval', $_POST['client_ids']);
        if (empty($client_ids)) {
            return;
        }
        
        // Get action
        $action = '-1';
        if (isset($_POST['bulk_action']) && $_POST['bulk_action'] !== '-1') {
            $action = $_POST['bulk_action'];
        } elseif (isset($_POST['bulk_action_bottom']) && $_POST['bulk_action_bottom'] !== '-1') {
            $action = $_POST['bulk_action_bottom'];
        }
        
        // Process based on action
        switch ($action) {
            case 'delete':
                $this->bulk_delete_clients($client_ids);
                break;
                
            case 'export':
                $this->bulk_export_clients($client_ids);
                break;
        }
    }
    
    /**
     * Bulk delete clients
     * 
     * @param array $client_ids Array of client IDs
     */
    private function bulk_delete_clients($client_ids) {
        if (!class_exists('\\VandelBooking\\Client\\ClientModel')) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard', 
                'tab' => 'clients', 
                'message' => 'bulk_delete_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        $client_model = new \VandelBooking\Client\ClientModel();
        $success_count = 0;
        
        foreach ($client_ids as $client_id) {
            if ($client_model->delete($client_id)) {
                $success_count++;
            }
        }
        
        $message = $success_count > 0 ? 'bulk_deleted' : 'bulk_delete_failed';
        wp_redirect(add_query_arg([
            'page' => 'vandel-dashboard', 
            'tab' => 'clients', 
            'message' => $message,
            'count' => $success_count
        ], admin_url('admin.php')));
        exit;
    }
    
    /**
     * Bulk export clients
     * 
     * @param array $client_ids Array of client IDs
     */
    private function bulk_export_clients($client_ids) {
        if (!class_exists('\\VandelBooking\\Client\\ClientModel')) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard', 
                'tab' => 'clients', 
                'message' => 'export_failed'
            ], admin_url('admin.php')));
            exit;
        }
        
        $client_model = new \VandelBooking\Client\ClientModel();
        $clients = [];
        
        // Get all selected clients
        foreach ($client_ids as $client_id) {
            $client = $client_model->get($client_id);
            if ($client) {
                $clients[] = $client;
            }
        }
        
        if (empty($clients)) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard', 
                'tab' => 'clients', 
                'message' => 'export_empty'
            ], admin_url('admin.php')));
            exit;
        }
        
        // Generate CSV
        $filename = 'vandel-clients-export-' . date('Y-m-d') . '.csv';
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, [
            'ID', 
            'Name', 
            'Email', 
            'Phone', 
            'Total Spent', 
            'Created At'
        ]);
        
        // Add client data
        foreach ($clients as $client) {
            fputcsv($output, [
                $client->id,
                $client->name,
                $client->email,
                isset($client->phone) ? $client->phone : '',
                isset($client->total_spent) ? $client->total_spent : 0,
                $client->created_at
            ]);
        }
        
        // Close output stream
        fclose($output);
        exit;
    }
    
    /**
     * Delete single client
     * 
     * @param int $client_id Client ID
     * @return bool Success
     */
    private function delete_client($client_id) {
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $client_model = new \VandelBooking\Client\ClientModel();
            return $client_model->delete($client_id);
        }
        
        return false;
    }
    
    /**
     * Render tab content
     */
    public function render() {
        // Check if adding new client
        if (isset($_GET['action']) && $_GET['action'] === 'add') {
            $this->render_add_client_form();
            return;
        }
        
        // Check if importing clients
        if (isset($_GET['action']) && $_GET['action'] === 'import') {
            $this->render_client_import_page();
            return;
        }
        
        // Display status messages
        $this->display_status_messages();
        
        global $wpdb;
        
        // Check if ClientModel exists
        $client_model = null;
        if (class_exists('\\VandelBooking\\Client\\ClientModel')) {
            $client_model = new \VandelBooking\Client\ClientModel();
        }
        
        // Pagination parameters
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20; // Clients per page
        $offset = ($page - 1) * $per_page;
        
        // Prepare filter arguments
        $args = [
            'limit' => $per_page,
            'offset' => $offset,
            'orderby' => isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name',
            'order' => isset($_GET['order']) ? sanitize_key($_GET['order']) : 'ASC',
            'search' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '',
        ];
        
        // Get filtered clients
        $clients = [];
        $total_clients = 0;
        
        if ($client_model) {
            $clients = $client_model->getAll($args);
            $total_clients = $client_model->count(['search' => $args['search']]);
        } else {
            // Fallback to direct DB query
            $clients_table = $wpdb->prefix . 'vandel_clients';
            $clients_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$clients_table'") === $clients_table;
            
            if ($clients_table_exists) {
                // Build where clause
                $where = '1=1';
                if (!empty($args['search'])) {
                    $search = '%' . $wpdb->esc_like($args['search']) . '%';
                    $where .= $wpdb->prepare(
                        " AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)",
                        $search, $search, $search
                    );
                }
                
                // Get total count for pagination
                $total_clients = $wpdb->get_var("SELECT COUNT(*) FROM $clients_table WHERE $where");
                
                // Order clause
                $orderby = in_array($args['orderby'], ['name', 'email', 'total_spent', 'created_at']) 
                    ? $args['orderby'] 
                    : 'name';
                    
                $order = $args['order'] === 'DESC' ? 'DESC' : 'ASC';
                
                // Get clients
                $clients = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $clients_table 
                         WHERE $where
                         ORDER BY $orderby $order
                         LIMIT %d OFFSET %d",
                        $args['limit'], $args['offset']
                    )
                );
            }
        }
        
        // Calculate pagination
        $total_pages = ceil($total_clients / $per_page);
        
        // Render the clients list
        $this->render_clients_list(
            $clients,
            $total_clients,
            $total_pages,
            $page,
            isset($_GET['s']) ? $_GET['s'] : '',
            isset($_GET['orderby']) ? $_GET['orderby'] : 'name',
            isset($_GET['order']) ? $_GET['order'] : 'ASC'
        );
    }
    
    /**
     * Display status messages
     */
    private function display_status_messages() {
        if (!isset($_GET['message'])) {
            return;
        }
        
        $message_type = 'success';
        $message = '';
        $count = isset($_GET['count']) ? intval($_GET['count']) : 0;
        
        switch ($_GET['message']) {
            case 'client_created':
                $message = __('Client created successfully.', 'vandel-booking');
                break;
            case 'client_deleted':
                $message = __('Client deleted successfully.', 'vandel-booking');
                break;
            case 'bulk_deleted':
                $message = sprintf(
                    _n('%d client deleted successfully.', '%d clients deleted successfully.', $count, 'vandel-booking'),
                    $count
                );
                break;
            case 'export_empty':
                $message = __('No clients were found to export.', 'vandel-booking');
                $message_type = 'warning';
                break;
            case 'delete_failed':
            case 'bulk_delete_failed':
                $message = __('Failed to delete client(s).', 'vandel-booking');
                $message_type = 'error';
                break;
            case 'export_failed':
                $message = __('Failed to export clients.', 'vandel-booking');
                $message_type = 'error';
                break;
        }
        
        if (!empty($message)) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($message_type),
                esc_html($message)
            );
        }
    }
    
    /**
     * Render clients list
     */
    private function render_clients_list($clients, $total_clients, $total_pages, $current_page, $search_query, $orderby, $order) {
        ?>
        <div id="clients" class="vandel-tab-content">
            <div class="vandel-card">
                <div class="vandel-card-header vandel-flex-header">
                    <h3><?php _e('Client Management', 'vandel-booking'); ?></h3>
                    <div class="vandel-header-actions">
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Client', 'vandel-booking'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=import'); ?>" class="button">
                            <span class="dashicons dashicons-upload"></span> <?php _e('Import Clients', 'vandel-booking'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="vandel-card-body">
                    <!-- Filters -->
                    <div class="vandel-filters-toolbar">
                        <form method="get" action="<?php echo admin_url('admin.php'); ?>" class="vandel-filter-form">
                            <input type="hidden" name="page" value="vandel-dashboard">
                            <input type="hidden" name="tab" value="clients">
                            
                            <div class="vandel-search-field">
                                <input type="text" name="s" placeholder="<?php _e('Search clients...', 'vandel-booking'); ?>" value="<?php echo esc_attr($search_query); ?>">
                                <button type="submit" class="vandel-search-button">
                                    <span class="dashicons dashicons-search"></span>
                                </button>
                            </div>
                            
                            <div class="vandel-filter-actions">
                                <button type="submit" class="button"><?php _e('Search', 'vandel-booking'); ?></button>
                                <?php if (!empty($search_query)): ?>
                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="button vandel-reset-btn"><?php _e('Reset', 'vandel-booking'); ?></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Clients Table -->
                    <form method="post" id="vandel-clients-form">
                        <?php if (empty($clients)): ?>
                            <div class="vandel-empty-state">
                                <span class="dashicons dashicons-groups"></span>
                                <p><?php _e('No clients found.', 'vandel-booking'); ?></p>
                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="button button-primary">
                                    <?php _e('Add New Client', 'vandel-booking'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="tablenav top">
                                <div class="alignleft actions bulkactions">
                                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'vandel-booking'); ?></label>
                                    <select name="bulk_action" id="bulk-action-selector-top">
                                        <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                                        <option value="export"><?php _e('Export Selected', 'vandel-booking'); ?></option>
                                        <option value="delete"><?php _e('Delete Selected', 'vandel-booking'); ?></option>
                                    </select>
                                    <input type="submit" id="doaction" class="button action" name="vandel_bulk_action" value="<?php esc_attr_e('Apply', 'vandel-booking'); ?>">
                                </div>
                                <div class="tablenav-pages">
                                    <span class="displaying-num">
                                        <?php printf(_n('%s item', '%s items', $total_clients, 'vandel-booking'), number_format_i18n($total_clients)); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <table class="wp-list-table widefat fixed striped vandel-data-table">
                                <thead>
                                    <tr>
                                        <td class="manage-column column-cb check-column">
                                            <input type="checkbox" id="cb-select-all-1">
                                        </td>
                                        <th scope="col" class="manage-column column-name">
                                            <a href="<?php echo add_query_arg(['orderby' => 'name', 'order' => ($orderby === 'name' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                                <?php _e('Name', 'vandel-booking'); ?>
                                                <?php if ($orderby === 'name'): ?>
                                                    <span class="sorting-indicator"></span>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th scope="col" class="manage-column column-email">
                                            <a href="<?php echo add_query_arg(['orderby' => 'email', 'order' => ($orderby === 'email' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                                <?php _e('Email', 'vandel-booking'); ?>
                                                <?php if ($orderby === 'email'): ?>
                                                    <span class="sorting-indicator"></span>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th scope="col" class="manage-column column-phone"><?php _e('Phone', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-total-spent">
                                            <a href="<?php echo add_query_arg(['orderby' => 'total_spent', 'order' => ($orderby === 'total_spent' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                                <?php _e('Total Spent', 'vandel-booking'); ?>
                                                <?php if ($orderby === 'total_spent'): ?>
                                                    <span class="sorting-indicator"></span>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th scope="col" class="manage-column column-created-at">
                                            <a href="<?php echo add_query_arg(['orderby' => 'created_at', 'order' => ($orderby === 'created_at' && $order === 'ASC') ? 'DESC' : 'ASC']); ?>">
                                                <?php _e('Created', 'vandel-booking'); ?>
                                                <?php if ($orderby === 'created_at'): ?>
                                                    <span class="sorting-indicator"></span>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'vandel-booking'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clients as $client): ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="client_ids[]" value="<?php echo esc_attr($client->id); ?>" id="cb-select-<?php echo esc_attr($client->id); ?>">
                                            </th>
                                            <td class="column-name">
                                                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id); ?>" class="row-title">
                                                    <?php echo esc_html($client->name); ?>
                                                </a>
                                            </td>
                                            <td class="column-email">
                                                <a href="mailto:<?php echo esc_attr($client->email); ?>">
                                                    <?php echo esc_html($client->email); ?>
                                                </a>
                                            </td>
                                            <td class="column-phone">
                                                <?php 
                                                if (!empty($client->phone)) {
                                                    printf(
                                                        '<a href="tel:%s">%s</a>',
                                                        esc_attr($client->phone),
                                                        esc_html($client->phone)
                                                    );
                                                } else {
                                                    echo '—';
                                                }
                                                ?>
                                            </td>
                                            <td class="column-total-spent">
                                                <?php echo isset($client->total_spent) ? \VandelBooking\Helpers::formatPrice($client->total_spent) : \VandelBooking\Helpers::formatPrice(0); ?>
                                            </td>
                                            <td class="column-created-at">
                                                <?php echo date_i18n(get_option('date_format'), strtotime($client->created_at)); ?>
                                            </td>
                                            <td class="column-actions">
                                                <div class="vandel-row-actions">
                                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id); ?>" class="button button-small button-default" title="<?php esc_attr_e('View Details', 'vandel-booking'); ?>">
                                                        <span class="dashicons dashicons-visibility"></span>
                                                    </a>
                                                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id . '&action=edit'); ?>" class="button button-small" title="<?php esc_attr_e('Edit Client', 'vandel-booking'); ?>">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </a>
                                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=vandel-dashboard&tab=clients&action=delete_client&client_id=' . $client->id), 'delete_client_' . $client->id); ?>" class="button button-small button-link-delete" title="<?php esc_attr_e('Delete Client', 'vandel-booking'); ?>" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this client?', 'vandel-booking'); ?>')">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="manage-column column-cb check-column">
                                            <input type="checkbox" id="cb-select-all-2">
                                        </td>
                                        <th scope="col" class="manage-column column-name"><?php _e('Name', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-email"><?php _e('Email', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-phone"><?php _e('Phone', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-total-spent"><?php _e('Total Spent', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-created-at"><?php _e('Created', 'vandel-booking'); ?></th>
                                        <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'vandel-booking'); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                            
                            <div class="tablenav bottom">
                                <div class="alignleft actions bulkactions">
                                    <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Select bulk action', 'vandel-booking'); ?></label>
                                    <select name="bulk_action_bottom" id="bulk-action-selector-bottom">
                                        <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                                        <option value="export"><?php _e('Export Selected', 'vandel-booking'); ?></option>
                                        <option value="delete"><?php _e('Delete Selected', 'vandel-booking'); ?></option>
                                    </select>
                                    <input type="submit" id="doaction2" class="button action" name="vandel_bulk_action" value="<?php esc_attr_e('Apply', 'vandel-booking'); ?>">
                                </div>
                                
                                <?php if ($total_pages > 1): ?>
                                    <div class="vandel-pagination">
                                        <?php
                                        // Build pagination links
                                        $current_url = add_query_arg(array_filter([
                                            'page' => 'vandel-dashboard',
                                            'tab' => 'clients',
                                            's' => $search_query,
                                            'orderby' => $orderby,
                                            'order' => $order
                                        ]), admin_url('admin.php'));
                                        
                                        // Previous page
                                        if ($current_page > 1) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $current_url)) . '" class="vandel-pagination-btn">&laquo; ' . __('Previous', 'vandel-booking') . '</a>';
                                        }
                                        
                                        // Page numbers
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);
                                        
                                        if ($start_page > 1) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', 1, $current_url)) . '" class="vandel-pagination-btn">1</a>';
                                            if ($start_page > 2) {
                                                echo '<span class="vandel-pagination-ellipsis">...</span>';
                                            }
                                        }
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++) {
                                            if ($i == $current_page) {
                                                echo '<span class="vandel-pagination-btn vandel-pagination-current">' . $i . '</span>';
                                            } else {
                                                echo '<a href="' . esc_url(add_query_arg('paged', $i, $current_url)) . '" class="vandel-pagination-btn">' . $i . '</a>';
                                            }
                                        }
                                        
                                        if ($end_page < $total_pages) {
                                            if ($end_page < $total_pages - 1) {
                                                echo '<span class="vandel-pagination-ellipsis">...</span>';
                                            }
                                            echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $current_url)) . '" class="vandel-pagination-btn">' . $total_pages . '</a>';
                                        }
                                        
                                        // Next page
                                        if ($current_page < $total_pages) {
                                            echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $current_url)) . '" class="vandel-pagination-btn">' . __('Next', 'vandel-booking') . ' &raquo;</a>';
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php wp_nonce_field('vandel_bulk_client_actions', 'vandel_bulk_nonce'); ?>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle "Select All" checkboxes
            const selectAllTop = document.getElementById('cb-select-all-1');
            const selectAllBottom = document.getElementById('cb-select-all-2');
            const checkboxes = document.querySelectorAll('input[name="client_ids[]"]');
            
            if (selectAllTop && selectAllBottom && checkboxes.length) {
                // Top checkbox toggle
                selectAllTop.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                    if (selectAllBottom) selectAllBottom.checked = this.checked;
                });
                
                // Bottom checkbox toggle
                selectAllBottom.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => checkbox.checked = this.checked);
                    if (selectAllTop) selectAllTop.checked = this.checked;
                });
                
                // Individual checkbox toggle
                checkboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const allChecked = Array.from(checkboxes).every(c => c.checked);
                        const someChecked = Array.from(checkboxes).some(c => c.checked);
                        
                        if (selectAllTop) selectAllTop.checked = allChecked;
                        if (selectAllBottom) selectAllBottom.checked = allChecked;
                        
                        // Enable/disable bulk action buttons
                        const bulkButtons = document.querySelectorAll('input[name="vandel_bulk_action"]');
                        bulkButtons.forEach(button => {
                            button.disabled = !someChecked;
                        });
                    });
                });
                
                // Initial check for bulk action buttons
                const someChecked = Array.from(checkboxes).some(c => c.checked);
                const bulkButtons = document.querySelectorAll('input[name="vandel_bulk_action"]');
                bulkButtons.forEach(button => {
                    button.disabled = !someChecked;
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Render add client form
     */
    private function render_add_client_form() {
        // Include the client details class if it exists
        if (class_exists('\\VandelBooking\\Admin\\ClientDetails')) {
            $client_details = new \VandelBooking\Admin\ClientDetails();
            $client_details->render(0); // 0 means new client
            return;
        }
        
        // Fallback form if ClientDetails class is not available
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h2><?php _e('Add New Client', 'vandel-booking'); ?></h2>
            </div>
            <div class="vandel-card-body">
                <form method="post" action="">
                    <?php wp_nonce_field('vandel_update_client', 'vandel_client_nonce'); ?>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_name"><?php _e('Name', 'vandel-booking'); ?> <span class="required">*</span></label>
                            <input type="text" name="client_name" id="client_name" required class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label for="client_email"><?php _e('Email', 'vandel-booking'); ?> <span class="required">*</span></label>
                            <input type="email" name="client_email" id="client_email" required class="widefat">
                        </div>
                    </div>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_phone"><?php _e('Phone', 'vandel-booking'); ?></label>
                            <input type="tel" name="client_phone" id="client_phone" class="widefat">
                        </div>
                        <div class="vandel-col">
                            <label for="client_address"><?php _e('Address', 'vandel-booking'); ?></label>
                            <textarea name="client_address" id="client_address" rows="3" class="widefat"></textarea>
                        </div>
                    </div>
                    
                    <div class="vandel-form-row">
                        <div class="vandel-col">
                            <label for="client_notes"><?php _e('Notes', 'vandel-booking'); ?></label>
                            <textarea name="client_notes" id="client_notes" rows="5" class="widefat"></textarea>
                        </div>
                    </div>
                    
                    <div class="vandel-form-actions">
                        <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="button button-secondary"><?php _e('Cancel', 'vandel-booking'); ?></a>
                        <button type="submit" name="vandel_update_client" class="button button-primary"><?php _e('Create Client', 'vandel-booking'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render client import page
     */
    private function render_client_import_page() {
        // Check if we have results from an import
        $import_results = get_transient('vandel_client_import_results');
        delete_transient('vandel_client_import_results'); // Clean up after reading
        
        ?>
        <div class="vandel-card">
            <div class="vandel-card-header">
                <h2><?php _e('Import Clients', 'vandel-booking'); ?></h2>
            </div>
            <div class="vandel-card-body">
                <?php if ($import_results): ?>
                    <!-- Display import results -->
                    <div class="vandel-import-results">
                        <h3><?php _e('Import Completed', 'vandel-booking'); ?></h3>
                        
                        <div class="vandel-import-stats">
                            <div class="vandel-import-stat-item">
                                <div class="vandel-import-stat-value"><?php echo number_format_i18n($import_results['total']); ?></div>
                                <div class="vandel-import-stat-label"><?php _e('Total Records', 'vandel-booking'); ?></div>
                            </div>
                            
                            <div class="vandel-import-stat-item">
                                <div class="vandel-import-stat-value"><?php echo number_format_i18n($import_results['imported']); ?></div>
                                <div class="vandel-import-stat-label"><?php _e('Imported', 'vandel-booking'); ?></div>
                            </div>
                            
                            <div class="vandel-import-stat-item">
                                <div class="vandel-import-stat-value"><?php echo number_format_i18n($import_results['updated']); ?></div>
                                <div class="vandel-import-stat-label"><?php _e('Updated', 'vandel-booking'); ?></div>
                            </div>
                            
                            <div class="vandel-import-stat-item">
                                <div class="vandel-import-stat-value"><?php echo number_format_i18n($import_results['failed']); ?></div>
                                <div class="vandel-import-stat-label"><?php _e('Failed', 'vandel-booking'); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($import_results['errors'])): ?>
                            <div class="vandel-import-errors">
                                <h4><?php _e('Errors', 'vandel-booking'); ?></h4>
                                <ul class="vandel-error-list">
                                    <?php foreach ($import_results['errors'] as $error): ?>
                                        <li><?php echo esc_html($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <div class="vandel-form-actions">
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="button button-primary"><?php _e('Back to Clients List', 'vandel-booking'); ?></a>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=import'); ?>" class="button"><?php _e('Import More Clients', 'vandel-booking'); ?></a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Display import form -->
                    <div class="vandel-import-instructions">
                        <h4><?php _e('Import Instructions', 'vandel-booking'); ?></h4>
                        <ul>
                            <li><?php _e('Create a CSV file with the following columns: name, email, phone, address, notes.', 'vandel-booking'); ?></li>
                            <li><?php _e('Email and name are required fields.', 'vandel-booking'); ?></li>
                            <li><?php _e('If a client with the same email already exists, their information will be updated.', 'vandel-booking'); ?></li>
                            <li><?php _e('The CSV should use commas (,) as delimiters and be UTF-8 encoded.', 'vandel-booking'); ?></li>
                        </ul>
                    </div>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" class="vandel-import-form">
                        <?php wp_nonce_field('vandel_import_clients', 'vandel_import_nonce'); ?>
                        <input type="hidden" name="action" value="vandel_import_clients">
                        
                        <div class="vandel-drop-zone" id="vandel-drop-zone">
                            <div class="vandel-drop-zone-icon">
                                <span class="dashicons dashicons-upload"></span>
                            </div>
                            <div class="vandel-drop-zone-text">
                                <?php _e('Drop your CSV file here or click to browse', 'vandel-booking'); ?>
                            </div>
                            <button type="button" class="button" id="vandel-browse-file">
                                <?php _e('Browse File', 'vandel-booking'); ?>
                            </button>
                            <input type="file" name="clients_csv" id="vandel-file-input" class="vandel-file-input" accept=".csv">
                        </div>
                        
                        <div class="vandel-selected-file" id="vandel-selected-file">
                            <div class="vandel-selected-file-name" id="vandel-selected-file-name"></div>
                            <div class="vandel-selected-file-remove" id="vandel-remove-file">
                                <span class="dashicons dashicons-dismiss"></span>
                            </div>
                        </div>
                        
                        <div class="vandel-import-submit">
                            <button type="submit" class="button button-primary" id="vandel-import-submit" disabled>
                                <?php _e('Import Clients', 'vandel-booking'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="button">
                                <?php _e('Cancel', 'vandel-booking'); ?>
                            </a>
                        </div>
                    </form>
                    
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const dropZone = document.getElementById('vandel-drop-zone');
                        const fileInput = document.getElementById('vandel-file-input');
                        const browseButton = document.getElementById('vandel-browse-file');
                        const selectedFile = document.getElementById('vandel-selected-file');
                        const selectedFileName = document.getElementById('vandel-selected-file-name');
                        const removeFileButton = document.getElementById('vandel-remove-file');
                        const importButton = document.getElementById('vandel-import-submit');
                        
                        // Handle browse button click
                        browseButton.addEventListener('click', function() {
                            fileInput.click();
                        });
                        
                        // Handle file selection
                        fileInput.addEventListener('change', function() {
                            handleFiles(this.files);
                        });
                        
                        // Handle drag and drop
                        dropZone.addEventListener('dragover', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            this.classList.add('drag-over');
                        });
                        
                        dropZone.addEventListener('dragleave', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            this.classList.remove('drag-over');
                        });
                        
                        dropZone.addEventListener('drop', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            this.classList.remove('drag-over');
                            
                            if (e.dataTransfer.files.length) {
                                handleFiles(e.dataTransfer.files);
                            }
                        });
                        
                        // Handle file remove
                        removeFileButton.addEventListener('click', function() {
                            fileInput.value = '';
                            selectedFile.classList.remove('visible');
                            importButton.disabled = true;
                        });
                        
                        // Helper function to handle selected files
                        function handleFiles(files) {
                            if (files.length) {
                                const file = files[0];
                                
                                // Check file type
                                if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                                    alert('<?php echo esc_js(__('Please select a CSV file.', 'vandel-booking')); ?>');
                                    fileInput.value = '';
                                    return;
                                }
                                
                                selectedFileName.textContent = file.name;
                                selectedFile.classList.add('visible');
                                importButton.disabled = false;
                            }
                        }
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}