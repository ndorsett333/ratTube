<?php
/**
 * Asset loader.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles admin and frontend assets.
 */
class RATTube_Assets {

    /**
     * Registers hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * Enqueues frontend assets only when converter UI is on the page.
     *
     * @return void
     */
    public function enqueue_frontend_assets(): void {
        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }

        wp_enqueue_style(
            'rattube-frontend',
            RATTUBE_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            RATTUBE_VERSION
        );

        wp_enqueue_script(
            'rattube-frontend',
            RATTUBE_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            RATTUBE_VERSION,
            true
        );
    }

    /**
     * Enqueues admin assets on plugin settings page.
     *
     * @param string $hook_suffix Current admin page.
     *
     * @return void
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        if ( 'settings_page_rattube' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_style(
            'rattube-admin',
            RATTUBE_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RATTUBE_VERSION
        );

        wp_enqueue_script(
            'rattube-admin',
            RATTUBE_PLUGIN_URL . 'assets/js/admin.js',
            array(),
            RATTUBE_VERSION,
            true
        );
    }

    /**
     * Determines whether frontend assets should be loaded.
     *
     * @return bool
     */
    private function should_load_frontend_assets(): bool {
        if ( is_admin() ) {
            return false;
        }

        if ( is_singular() ) {
            $post = get_post();
            if ( $post instanceof WP_Post && has_shortcode( (string) $post->post_content, rattube_get_converter_shortcode_tag() ) ) {
                return true;
            }
        }

        $converter_page_id = (int) get_option( 'rattube_converter_page_id', 0 );
        if ( $converter_page_id > 0 && is_page( $converter_page_id ) ) {
            return true;
        }

        return false;
    }
}
