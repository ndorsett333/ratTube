<?php
/**
 * Plugin bootstrap class.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates plugin components.
 */
class RATTube_Plugin {

    /**
     * Post types component.
     *
     * @var RATTube_Post_Types
     */
    private RATTube_Post_Types $post_types;

    /**
     * Routing component.
     *
     * @var RATTube_Routes
     */
    private RATTube_Routes $routes;

    /**
     * Assets component.
     *
     * @var RATTube_Assets
     */
    private RATTube_Assets $assets;

    /**
     * Admin component.
     *
     * @var RATTube_Admin
     */
    private RATTube_Admin $admin;

    /**
     * Frontend component.
     *
     * @var RATTube_Frontend
     */
    private RATTube_Frontend $frontend;

    /**
     * Converter worker component.
     *
     * @var RATTube_Converter_Worker
     */
    private RATTube_Converter_Worker $converter_worker;

    /**
     * Tools component.
     *
     * @var RATTube_Tools
     */
    private RATTube_Tools $tools;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->post_types = new RATTube_Post_Types();
        $this->routes     = new RATTube_Routes();
        $this->assets     = new RATTube_Assets();
        $this->admin      = new RATTube_Admin();
        $this->frontend   = new RATTube_Frontend();
        $this->converter_worker = new RATTube_Converter_Worker();
        $this->tools      = new RATTube_Tools();
    }

    /**
     * Registers all component hooks.
     *
     * @return void
     */
    public function run(): void {
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        $this->post_types->register_hooks();
        $this->routes->register_hooks();
        $this->assets->register_hooks();
        $this->admin->register_hooks();
        $this->frontend->register_hooks();
        $this->converter_worker->register_hooks();
        $this->tools->register_hooks();

        update_option( 'rattube_version', RATTUBE_VERSION, false );
    }

    /**
     * Loads translation files.
     *
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( 'rattube', false, dirname( plugin_basename( RATTUBE_PLUGIN_FILE ) ) . '/languages' );
    }
}
