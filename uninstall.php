<?php
// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

delete_option('jhovanic_ip_ban_db_version');
delete_option('jhovanic_ip_ban_whitelist');
delete_option('jhovanic_ip_ban_redirect');
delete_option('jhovanic_ip_ban_treshold');

// For site option but not sure if needed
delete_site_option('jhovanic_ip_ban_db_version');

// Drop the database table
global $wpdb;
$table_name = $wpdb->prefix . 'jhovanic_ban_list';
$wpdb->query("DROP TABLE IF EXISTS $table_name;");
