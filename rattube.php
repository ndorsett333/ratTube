<?php
/**
 * Plugin Name:       RatTube
 * Description:       Foundation plugin for Rat Media intake and conversion workflows.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            RatTube
 * Text Domain:       rattube
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

define( 'RATTUBE_VERSION', '0.1.0' );
define( 'RATTUBE_PLUGIN_FILE', __FILE__ );
define( 'RATTUBE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RATTUBE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RATTUBE_PLUGIN_DIR . 'includes/helpers.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-activator.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-post-types.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-routes.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-assets.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-admin.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-frontend.php';
require_once RATTUBE_PLUGIN_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'RATTube_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RATTube_Deactivator', 'deactivate' ) );

/**
 * Starts plugin execution.
 *
 * @return void
 */
function rattube_run(): void {
    $plugin = new RATTube_Plugin();
    $plugin->run();
}

rattube_run();
