<?php
/**
 * Plugin Name: Smart Chat
 * Plugin URI: https://github.com/hoseinmos/smart-chat
 * Description: A smart chat widget that provides intelligent responses using internal site content and external sources.
 * Version: 1.0.0
 * Author: hoseinmos
 * Author URI: https://github.com/hoseinmos
 * Text Domain: smart-chat
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.5
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 *
 * @package SmartChat
 * @version 1.0.0
 * @author hoseinmos
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HMSC_VERSION', '1.0.0');
define('HMSC_PLUGIN_FILE', __FILE__);
define('HMSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HMSC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HMSC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SmartChat\\';
    $base_dir = HMSC_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function hmsc_init() {
    load_plugin_textdomain('smart-chat', false, dirname(HMSC_PLUGIN_BASENAME) . '/languages');
    new \SmartChat\Loader();
}
add_action('plugins_loaded', 'hmsc_init');

// Activation hook
register_activation_hook(__FILE__, 'hmsc_activate');
function hmsc_activate() {
    $default_options = array(
        'enable' => true,
        'position' => 'bottom-right',
        'welcome_text' => __('Hi! I\'m here to help. Ask me anything!', 'smart-chat'),
        'placeholder' => __('Type your question...', 'smart-chat'),
        'theme_color' => '#0073aa',
        'bubble_color' => '#ffffff',
        'mode' => 'hybrid',
        'internal_limit' => 5,
        'external_limit' => 3,
        'mix_weight' => 70,
        'provider' => 'mock',
        'api_key' => '',
        'endpoint' => '',
        'enable_logs' => false,
        'retention_days' => 7,
        'rate_limit' => 10,
        'rate_limit_window' => 60
    );
    
    add_option('hmsc_options', $default_options);
    hmsc_create_logs_table();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'hmsc_deactivate');
function hmsc_deactivate() {
    wp_clear_scheduled_hook('hmsc_cleanup_logs');
}

// Create logs table
function hmsc_create_logs_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'hmsc_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        session_id varchar(32) NOT NULL,
        user_ip varchar(45) NOT NULL,
        user_agent text,
        question text NOT NULL,
        answer text NOT NULL,
        sources longtext,
        provider varchar(50) NOT NULL,
        response_time float NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Helper functions
function hmsc_get_option($key, $default = null) {
    $options = get_option('hmsc_options', array());
    return isset($options[$key]) ? $options[$key] : $default;
}

function hmsc_update_option($key, $value) {
    $options = get_option('hmsc_options', array());
    $options[$key] = $value;
    return update_option('hmsc_options', $options);
}

function hmsc_sanitize_options($input) {
    $sanitized = array();
    
    $sanitized['enable'] = isset($input['enable']) ? true : false;
    $sanitized['position'] = sanitize_text_field($input['position']);
    $sanitized['welcome_text'] = sanitize_textarea_field($input['welcome_text']);
    $sanitized['placeholder'] = sanitize_text_field($input['placeholder']);
    $sanitized['theme_color'] = sanitize_hex_color($input['theme_color']);
    $sanitized['bubble_color'] = sanitize_hex_color($input['bubble_color']);
    $sanitized['mode'] = sanitize_text_field($input['mode']);
    $sanitized['internal_limit'] = absint($input['internal_limit']);
    $sanitized['external_limit'] = absint($input['external_limit']);
    $sanitized['mix_weight'] = min(100, max(0, absint($input['mix_weight'])));
    $sanitized['provider'] = sanitize_text_field($input['provider']);
    $sanitized['api_key'] = sanitize_text_field($input['api_key']);
    $sanitized['endpoint'] = esc_url_raw($input['endpoint']);
    $sanitized['enable_logs'] = isset($input['enable_logs']) ? true : false;
    $sanitized['retention_days'] = absint($input['retention_days']);
    $sanitized['rate_limit'] = absint($input['rate_limit']);
    $sanitized['rate_limit_window'] = absint($input['rate_limit_window']);
    
    return $sanitized;
}

// Add settings link to plugins page
function hmsc_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=smart-chat') . '">' . __('Settings', 'smart-chat') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . HMSC_PLUGIN_BASENAME, 'hmsc_add_settings_link');