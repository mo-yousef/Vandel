<?php

add_action('admin_init', function() {
    global $wpdb;
    error_log('Database tables: ' . print_r($wpdb->get_results("SHOW TABLES LIKE '%vandel%'"), true));
});
