<?php
/**
 * Plugin Name: Bitesize Cúrsaí
 * Plugin URI: https://github.com/bitesizeirish/bitesize-cursai-plugin
 * Description: Custom WordPress plugin for Bitesize Irish Cúrsaí membership site customizations
 * Version: 1.0.3
 * Author: Bitesize Irish
 * Author URI: https://bitesize.irish
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bitesize-cursai
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 * Updated automatically by build script.
 */
define('BITESIZE_CURSAI_VERSION', '1.0.3');

/**
 * Plugin directory path.
 */
define('BITESIZE_CURSAI_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL.
 */
define('BITESIZE_CURSAI_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_bitesize_cursai() {
    // Activation code here
}
register_activation_hook(__FILE__, 'activate_bitesize_cursai');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_bitesize_cursai() {
    // Deactivation code here
}
register_deactivation_hook(__FILE__, 'deactivate_bitesize_cursai');

/**
 * Begin execution of the plugin.
 */
function run_bitesize_cursai() {
    // Plugin initialization code here
}
add_action('plugins_loaded', 'run_bitesize_cursai');

