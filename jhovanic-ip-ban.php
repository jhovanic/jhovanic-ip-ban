<?php
/*
Plugin Name: Jhovanic IP Ban
Plugin URI:
Description: This plugin enables IP ban for your site.
Version:     20161224
Author:      jhovanic
Author URI:  https://profiles.wordpress.org/jhovanic
License:     MIT
License URI: https://opensource.org/licenses/MIT
*/

defined('ABSPATH') or die('No script kiddies please!');

global $jhovanic_ip_ban_db_version;
$jhovanic_ip_ban_db_version = '1.0';

/**
 * Install setup
 *
 */
function jhovanic_ip_ban_install() {
  global $wpdb;
  global $jhovanic_ip_ban_db_version;

  $table_name = $wpdb->prefix . 'jhovanic_ban_list';
  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table_name (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    last_visited datetime DEFAULT NOW() NOT NULL,
    ip varchar(45) DEFAULT '' NOT NULL,
    user_agent varchar(200) DEFAULT '' NOT NULL,
    PRIMARY KEY  (id)
  ) $charset_collate;";

  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);

  // Options
  add_option('jhovanic_ip_ban_db_version', $jhovanic_ip_ban_db_version);
  add_option('jhovanic_ip_ban_whitelist', '');
  add_option('jhovanic_ip_ban_redirect', 'https://www.google.com/');
  add_option('jhovanic_ip_ban_treshold', '30 days');
  add_option('jhovanic_ip_ban_time_allowed', '1 hour');
}

/**
 * Update setup
 *
 */
function jhovanic_ip_ban_update() {
  global $jhovanic_ip_ban_db_version;
  if (get_site_option('jhovanic_ip_ban_db_version') != $jhovanic_ip_ban_db_version) {
    jhovanic_ip_ban_install();
  }
}


/**
 * Main logic for redirecting
 *
 */
function jhovanic_ip_ban() {

  // Do nothing for admin user
  if (is_user_logged_in() && is_admin()) {
    return '';
  }

  $remote_ip = $_SERVER['REMOTE_ADDR'];
  $remote_ua = $_SERVER['HTTP_USER_AGENT'];

  if (jhovanic_check_ip_address($remote_ip, $remote_ua)) {
    $redirect_url = get_option('jhovanic_ip_ban_redirect');
    if (jhovanic_should_redirect($redirect_url)) {
      wp_redirect($redirect_url);
      exit;
    }
  }
}

/**
 * Check for the given IP address in the ban list
 * @param  string $ip remote IP to check
 * @param  string $ua remote user agent
 * @return bool       true if banned, false otherwise
 */
function jhovanic_check_ip_address($ip, $ua) {
  // Check whitelist
  $ip_whitelist = get_option('jhovanic_ip_ban_whitelist');
  $ip_whitelist = explode("\r\n", $ip_whitelist);
  if (!in_array($ip, $ip_whitelist)) {
    // Fallback to DB lookup if not in whitelist
    global $wpdb;
    $table_name = $wpdb->prefix . 'jhovanic_ban_list';
    $row = $wpdb->get_row("SELECT * FROM $table_name WHERE ip = '$ip'", ARRAY_A);
    if ($row) {
      // Get the time allowed for a visitor before ban
      $time_allowed = get_option('jhovanic_ip_ban_time_allowed');
      $time_allowed_date = strtotime("+$time_allowed", strtotime($row['last_visited']));
      // Get the ban treshold
      $treshold = get_option('jhovanic_ip_ban_treshold');
      $treshold_date = strtotime("+$treshold", strtotime($row['last_visited']));
      $treshold_date = strtotime("+$time_allowed", $treshold_date);
      if (time() - $time_allowed_date > 0) {  // if we're beyond the time allowed then do checkup
        if ((time() - $treshold_date) < 0 ) {
          return true;
        }
        else {
          // If we're beyond the treshold allow visit
          // but update the entry for future visits
          $wpdb->update($table_name, array('user_agent' => $ua, 'last_visited' => date('Y-m-d H:i:s')),
                        array('id' => $row['id']));
        }
      }
    }
    else {
      // Write the ip and user agent to the database
      $wpdb->insert($table_name, array('ip' => $ip, 'user_agent' => $ua), array('%s', '%s'));
    }
  }
  return false;
}

/**
 * Get the current page URL
 * @return string current page URL
 */
function jhovanic_get_current_url() {
  $pageURL = (@$_SERVER["HTTPS"] == "on") ? "https://" : "http://";
  if ($_SERVER["SERVER_PORT"] != "80") {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
  }
  else {
      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
  }
  return $pageURL;
}

/**
 * Validate if redirection should happen
 * @param  string $redirect_url
 * @return bool
 */
function jhovanic_should_redirect($redirect_url) {
  return !(jhovanic_get_current_url() == $redirect_url);
}

/**
 * Register submenu page for settings
 *
 */
function register_jhovanic_ip_ban_submenu_page() {
  add_submenu_page(
    'options-general.php', __('Jhovanic IP Ban'), __('Jhovanic IP Ban'),
    'manage_options',
    'jhovanic-ip-ban',
    'jhovanic_ip_ban_callback');
}

/**
 * Admin settings page
 *
 */
function jhovanic_ip_ban_callback() {

    // form submit  and save values
    if (isset($_POST['_wpprotect']) && wp_verify_nonce($_POST['_wpprotect'], 'jhovanic_ip_ban')) {
        $ip_whitelist = wp_kses($_POST['ip_whitelist'], array());
        $redirect_url = sanitize_text_field($_POST['redirect_url']);
        $ban_treshold = sanitize_text_field($_POST['ban_treshold']);
        $time_allowed = sanitize_text_field($_POST['time_allowed']);

        update_option('jhovanic_ip_ban_whitelist', $ip_whitelist);
        update_option('jhovanic_ip_ban_redirect', $redirect_url);
        update_option('jhovanic_ip_ban_treshold', $ban_treshold);
        update_option('jhovanic_ip_ban_time_allowed', $time_allowed);
    }

    // read values from option table

    $ip_whitelist = get_option('jhovanic_ip_ban_whitelist');
    $redirect_url = get_option('jhovanic_ip_ban_redirect');
    $ban_treshold = get_option('jhovanic_ip_ban_treshold');
    $time_allowed = get_option('jhovanic_ip_ban_time_allowed');

?>

<div class="wrap" id='jhovanic-ip-ban-list'>
  <div class="icon32" id="icon-options-general"><br></div><h2><?php _e('Jhovanic IP Ban'); ?></h2>

  <p>
    <?php _e('Add IP address in the textarea for whitelisting. Add only 1 item per line.
             e.g.:  82.11.22.100') ?>
    <br/>
    <?php _e('You may specify a redirect url; when a user from a banned ip access your site,
             he/she will be redirected to the specified URL.') ?>
  </p>

  <form action="" method="post">
    <p>
      <fieldset></fieldset>
      <label for='ip-whitelist'><?php _e('IP Whitelist'); ?></label> <br/>
      <textarea name='ip_whitelist' id='ip-whitelist'><?php echo $ip_whitelist ?></textarea>
      <span><?php _e('Just 1 IP per line here.') ?></span>
    </p>

    <p>
      <label for='ban-treshold'><?php _e('IP ban time allowed') ?></label> <br/>
      <input type='text' name='time_allowed' id='time-allowed'
             value='<?php echo $time_allowed ?>' />
      <span><?php _e('We use plain english to set the allowed time (e.g. 1 day, 2 hours, 1 week).') ?></span>
    </p>

    <p>
      <label for='ban-treshold'><?php _e('IP ban time') ?></label> <br/>
      <input type='text' name='ban_treshold' id='ban-treshold'
             value='<?php echo $ban_treshold ?>' />
      <span><?php _e('We use plain english to set the ban time (e.g. 1 day, 2 hours, 1 week).') ?></span>
    </p>

    <p>
      <label for='redirect-url'><?php _e('Redirect URL'); ?></label> <br/>
      <input  type='url' name='redirect_url' id='redirect-url'
              value='<?php echo $redirect_url; ?>'
              placeholder='<?php _e('Enter a valid URL') ?>' />
      <span><?php _e('Banned IPs redirect to this URL.') ?></span>
    </p>

    <?php wp_nonce_field('jhovanic_ip_ban', '_wpprotect') ?>

    <p>
      <input type='submit' name='submit' value='<?php _e('Save') ?>' />
    </p>
  </form>
</div>

<?php
}

// Register hooks
register_activation_hook(__FILE__, 'jhovanic_ip_ban_install');

// Add actions
add_action('plugins_loaded', 'jhovanic_ip_ban_update');
add_action('plugins_loaded', 'jhovanic_ip_ban');
add_action('admin_menu', 'register_jhovanic_ip_ban_submenu_page');
