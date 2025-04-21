<?php
namespace VandelBooking\Admin\Dashboard;

use VandelBooking\Client\ClientModel;
use VandelBooking\Helpers;

/**
 * Enhanced Clients Tab with Modern Design and Advanced Filtering
 */
class Clients_Tab implements Tab_Interface {
    /**
     * @var ClientModel
     */
    private $client_model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->client_model = new ClientModel();
    }

    public function register_hooks() {
        add_action('admin_init', [$this, 'handle_client_actions']);
        add_action('wp_ajax_vandel_client_advanced_filter', [$this, 'ajax_advanced_filter']);
    }

    public function process_actions() {
        if (isset($_POST['vandel_bulk_action']) && isset($_POST['client_ids']) && is_array($_POST['client_ids'])) {
            $this->process_bulk_actions();
        }
    }

public function render() {
    $this->display_status_messages();

    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $filter_args = $this->prepare_filter_arguments();

    $clients = $this->client_model->getAll(array_merge($filter_args, [
        'limit' => $per_page,
        'offset' => $offset
    ]));

    $total_clients = $this->client_model->count($filter_args);
    $total_pages = ceil($total_clients / $per_page);
    ?>

    <div class="vandel-clients-dashboard">
        <div class="vandel-clients-header">
            <div class="vandel-clients-title">
                <h1><?php _e('Client Management', 'vandel-booking'); ?></h1>
                <p><?php printf(__('Total Clients: %s', 'vandel-booking'), number_format_i18n($total_clients)); ?></p>
            </div>
            <div class="vandel-clients-actions">
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Add New Client', 'vandel-booking'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=import'); ?>" class="button">
                    <span class="dashicons dashicons-upload"></span> <?php _e('Import Clients', 'vandel-booking'); ?>
                </a>
            </div>
        </div>

        <div class="vandel-clients-filter-section">
            <form method="get" action="" class="vandel-clients-filter-form">
                <input type="hidden" name="page" value="vandel-dashboard">
                <input type="hidden" name="tab" value="clients">

                <div class="vandel-filter-row">
                    <div class="vandel-filter-group">
                        <label for="vandel-search-clients"><?php _e('Search Clients', 'vandel-booking'); ?></label>
                        <input type="search" id="vandel-search-clients" name="s" placeholder="<?php _e('Search by name, email, or phone', 'vandel-booking'); ?>" value="<?php echo isset($_GET['s']) ? esc_attr($_GET['s']) : ''; ?>">
                    </div>

                    <div class="vandel-filter-group">
                        <label for="vandel-min-spent"><?php _e('Minimum Total Spent', 'vandel-booking'); ?></label>
                        <input type="number" id="vandel-min-spent" name="min_spent" min="0" step="0.01" value="<?php echo isset($_GET['min_spent']) ? esc_attr($_GET['min_spent']) : ''; ?>">
                    </div>

                    <div class="vandel-filter-group">
                        <label for="vandel-min-bookings"><?php _e('Minimum Bookings', 'vandel-booking'); ?></label>
                        <input type="number" id="vandel-min-bookings" name="min_bookings" min="0" value="<?php echo isset($_GET['min_bookings']) ? esc_attr($_GET['min_bookings']) : ''; ?>">
                    </div>
                </div>

                <div class="vandel-filter-actions">
                    <button type="submit" class="button button-secondary"><?php _e('Apply Filters', 'vandel-booking'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients'); ?>" class="button"><?php _e('Reset Filters', 'vandel-booking'); ?></a>
                </div>
            </form>
        </div>

        <?php if (empty($clients)): ?>
            <div class="vandel-empty-state">
                <span class="dashicons dashicons-groups"></span>
                <p><?php _e('No clients found.', 'vandel-booking'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=clients&action=add'); ?>" class="button button-primary">
                    <?php _e('Add Your First Client', 'vandel-booking'); ?>
                </a>
            </div>
        <?php else: ?>
            <form method="post" id="vandel-clients-form">
                <?php wp_nonce_field('vandel_client_bulk_actions', 'vandel_client_nonce'); ?>

                <div class="vandel-clients-table-actions">
                    <div class="vandel-bulk-actions">
                        <select name="bulk_action">
                            <option value="-1"><?php _e('Bulk Actions', 'vandel-booking'); ?></option>
                            <option value="export"><?php _e('Export Selected', 'vandel-booking'); ?></option>
                            <option value="delete"><?php _e('Delete Selected', 'vandel-booking'); ?></option>
                        </select>
                        <button type="submit" name="vandel_bulk_action" class="button"><?php _e('Apply', 'vandel-booking'); ?></button>
                    </div>
                </div>

                <div class="vandel-clients-table-container">
                    <table class="wp-list-table widefat fixed striped vandel-clients-table">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1">
                                </td>
                                <th><?php _e('Name', 'vandel-booking'); ?></th>
                                <th><?php _e('Contact', 'vandel-booking'); ?></th>
                                <th><?php _e('Total Spent', 'vandel-booking'); ?></th>
                                <th><?php _e('Bookings', 'vandel-booking'); ?></th>
                                <th><?php _e('Last Booking', 'vandel-booking'); ?></th>
                                <th><?php _e('Actions', 'vandel-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="client_ids[]" value="<?php echo esc_attr($client->id); ?>">
                                    </th>
                                    <td>
                                        <div class="vandel-client-name-cell">
                                            <div class="vandel-client-avatar"><?php echo esc_html(strtoupper(substr($client->name, 0, 1))); ?></div>
                                            <div class="vandel-client-details">
                                                <strong><?php echo esc_html($client->name); ?></strong>
                                                <span><?php echo esc_html($client->created_at); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="mailto:<?php echo esc_attr($client->email); ?>"><?php echo esc_html($client->email); ?></a>
                                        <?php if (!empty($client->phone)): ?>
                                            <br><a href="tel:<?php echo esc_attr($client->phone); ?>"><?php echo esc_html($client->phone); ?></a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo Helpers::formatPrice(floatval($client->total_spent ?? 0)); ?></td>
                                    <td><?php echo number_format_i18n(intval($client->bookings_count ?? 0)); ?></td>
                                    <td><?php echo esc_html($client->last_booking ? date_i18n(get_option('date_format'), strtotime($client->last_booking)) : '—'); ?></td>
                                    <td>
                                        <div class="vandel-row-actions">
                                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=client-details&client_id=' . $client->id); ?>" class="button button-small"><?php _e('View', 'vandel-booking'); ?></a>
                                            <a href="<?php echo admin_url('admin.php?page=vandel-dashboard&tab=bookings&action=add&client_id=' . $client->id); ?>" class="button button-small"><?php _e('Book', 'vandel-booking'); ?></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php $this->render_pagination($page, $total_pages, $total_clients); ?>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const masterCheckbox = document.getElementById('cb-select-all-1');
            const checkboxes = document.querySelectorAll('input[name="client_ids[]"]');

            if (masterCheckbox) {
                masterCheckbox.addEventListener('change', () => {
                    checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
                });
            }
        });
    </script>

    <?php
}


    private function prepare_filter_arguments() {
        $args = [];

        if (isset($_GET['s']) && !empty($_GET['s'])) {
            $args['search'] = sanitize_text_field($_GET['s']);
        }

        if (isset($_GET['min_spent']) && is_numeric($_GET['min_spent'])) {
            $args['min_spent'] = floatval($_GET['min_spent']);
        }

        if (isset($_GET['min_bookings']) && is_numeric($_GET['min_bookings'])) {
            $args['min_bookings'] = intval($_GET['min_bookings']);
        }

        $args['orderby'] = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'name';
        $args['order'] = isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) 
            ? strtoupper($_GET['order']) 
            : 'ASC';

        return $args;
    }

    private function render_pagination($current_page, $total_pages, $total_items) {
        if ($total_pages <= 1) return;

        $base_url = add_query_arg(
            array_filter([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                's' => isset($_GET['s']) ? sanitize_text_field($_GET['s']) : false,
                'min_spent' => isset($_GET['min_spent']) ? floatval($_GET['min_spent']) : false,
                'min_bookings' => isset($_GET['min_bookings']) ? intval($_GET['min_bookings']) : false,
            ]),
            admin_url('admin.php')
        );

        echo '<div class="vandel-pagination">';
        if ($current_page > 1) {
            echo '<a href="' . esc_url(add_query_arg('paged', $current_page - 1, $base_url)) . '" class="button pagination-previous">' . __('Previous', 'vandel-booking') . '</a>';
        }

        echo '<span class="pagination-pages">';
        if ($current_page > 2) {
            echo '<a href="' . esc_url(add_query_arg('paged', 1, $base_url)) . '" class="button">1</a>';
            if ($current_page > 3) echo '<span class="pagination-ellipsis">...</span>';
        }

        for ($i = max(1, $current_page - 1); $i <= min($total_pages, $current_page + 1); $i++) {
            if ($i === $current_page) {
                echo '<span class="button active">' . $i . '</span>';
            } else {
                echo '<a href="' . esc_url(add_query_arg('paged', $i, $base_url)) . '" class="button">' . $i . '</a>';
            }
        }

        if ($current_page < $total_pages - 1) {
            if ($current_page < $total_pages - 2) echo '<span class="pagination-ellipsis">...</span>';
            echo '<a href="' . esc_url(add_query_arg('paged', $total_pages, $base_url)) . '" class="button">' . $total_pages . '</a>';
        }

        echo '</span>';

        if ($current_page < $total_pages) {
            echo '<a href="' . esc_url(add_query_arg('paged', $current_page + 1, $base_url)) . '" class="button pagination-next">' . __('Next', 'vandel-booking') . '</a>';
        }

        echo '</div>';
    }

    private function process_bulk_actions() {
        if (!isset($_POST['vandel_client_nonce']) || 
            !wp_verify_nonce($_POST['vandel_client_nonce'], 'vandel_client_bulk_actions')) {
            wp_die(__('Security check failed', 'vandel-booking'));
        }

        if (!isset($_POST['client_ids']) || !is_array($_POST['client_ids'])) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                'message' => 'no_clients_selected'
            ], admin_url('admin.php')));
            exit;
        }

        $client_ids = array_filter(array_map('intval', $_POST['client_ids']));
        if (empty($client_ids)) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                'message' => 'no_clients_selected'
            ], admin_url('admin.php')));
            exit;
        }

        $action = isset($_POST['bulk_action']) ? sanitize_key($_POST['bulk_action']) : '';

        switch ($action) {
            case 'export':
                $this->bulk_export_clients($client_ids);
                break;
            case 'delete':
                $this->bulk_delete_clients($client_ids);
                break;
            default:
                wp_redirect(add_query_arg([
                    'page' => 'vandel-dashboard',
                    'tab' => 'clients',
                    'message' => 'invalid_action'
                ], admin_url('admin.php')));
                exit;
        }
    }

    private function bulk_export_clients($client_ids) {
        $exported_clients = [];
        foreach ($client_ids as $client_id) {
            $client = $this->client_model->get($client_id);
            if ($client) $exported_clients[] = $client;
        }

        if (empty($exported_clients)) {
            wp_redirect(add_query_arg([
                'page' => 'vandel-dashboard',
                'tab' => 'clients',
                'message' => 'export_no_clients'
            ], admin_url('admin.php')));
            exit;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=vandel_clients_export_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Total Spent', 'Bookings Count', 'Last Booking', 'Created At']);

        foreach ($exported_clients as $client) {
            fputcsv($output, [
                $client->id,
                $client->name,
                $client->email,
                $client->phone ?? '',
                $client->total_spent ?? 0,
                $client->bookings_count ?? 0,
                $client->last_booking ?? '',
                $client->created_at
            ]);
        }

        fclose($output);
        exit;
    }

    private function bulk_delete_clients($client_ids) {
        $deleted_count = 0;
        $failed_count = 0;

        foreach ($client_ids as $client_id) {
            try {
                if ($this->client_model->delete($client_id)) {
                    $deleted_count++;
                } else {
                    $failed_count++;
                }
            } catch (\Exception $e) {
                $failed_count++;
                error_log('Failed to delete client ID ' . $client_id . ': ' . $e->getMessage());
            }
        }

        wp_redirect(add_query_arg([
            'page' => 'vandel-dashboard',
            'tab' => 'clients',
            'message' => 'bulk_delete',
            'deleted' => $deleted_count,
            'failed' => $failed_count
        ], admin_url('admin.php')));
        exit;
    }

    private function display_status_messages() {
        if (!isset($_GET['message'])) return;

        $message_type = 'success';
        $message = '';

        switch ($_GET['message']) {
            case 'bulk_delete':
                $deleted = intval($_GET['deleted'] ?? 0);
                $failed = intval($_GET['failed'] ?? 0);

                if ($deleted > 0) {
                    $message = sprintf(
                        _n('%d client deleted successfully.', '%d clients deleted successfully.', $deleted, 'vandel-booking'),
                        $deleted
                    );
                    if ($failed > 0) {
                        $message .= ' ' . sprintf(
                            _n('%d client could not be deleted.', '%d clients could not be deleted.', $failed, 'vandel-booking'),
                            $failed
                        );
                        $message_type = 'warning';
                    }
                } else {
                    $message = __('No clients were deleted.', 'vandel-booking');
                    $message_type = 'warning';
                }
                break;

            case 'export_no_clients':
                $message = __('No clients were selected for export.', 'vandel-booking');
                $message_type = 'warning';
                break;

            case 'no_clients_selected':
                $message = __('No clients were selected.', 'vandel-booking');
                $message_type = 'warning';
                break;

            case 'invalid_action':
                $message = __('Invalid bulk action selected.', 'vandel-booking');
                $message_type = 'error';
                break;
        }

        if (!empty($message)) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($message_type), esc_html($message));
        }
    }

    // You can define ajax_advanced_filter and handle_client_actions here if needed
    /**
     * Handle individual client actions (like delete or update from URL params)
     */
    public function handle_client_actions() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_GET['action']) || !isset($_GET['client_id'])) {
            return;
        }

        $action = sanitize_key($_GET['action']);
        $client_id = intval($_GET['client_id']);

        if (!$client_id) {
            return;
        }

        switch ($action) {
            case 'delete':
                check_admin_referer('vandel_delete_client_' . $client_id);

                try {
                    $deleted = $this->client_model->delete($client_id);

                    if ($deleted) {
                        wp_redirect(add_query_arg([
                            'page' => 'vandel-dashboard',
                            'tab' => 'clients',
                            'message' => 'single_delete_success'
                        ], admin_url('admin.php')));
                    } else {
                        wp_redirect(add_query_arg([
                            'page' => 'vandel-dashboard',
                            'tab' => 'clients',
                            'message' => 'single_delete_failed'
                        ], admin_url('admin.php')));
                    }
                    exit;
                } catch (\Exception $e) {
                    error_log('Client delete error: ' . $e->getMessage());
                }
                break;
        }
    }

    /**
     * Handle AJAX filtering request
     */
    public function ajax_advanced_filter() {
        check_ajax_referer('vandel_client_filter_nonce', 'nonce');

        $args = [];

        if (!empty($_POST['search'])) {
            $args['search'] = sanitize_text_field($_POST['search']);
        }

        if (!empty($_POST['min_spent'])) {
            $args['min_spent'] = floatval($_POST['min_spent']);
        }

        if (!empty($_POST['min_bookings'])) {
            $args['min_bookings'] = intval($_POST['min_bookings']);
        }

        $clients = $this->client_model->getAll($args);

        wp_send_json_success([
            'clients' => $clients
        ]);
    }
}
