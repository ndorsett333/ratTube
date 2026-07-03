<?php
/**
 * Plugin activation handler.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation actions.
 */
class RATTube_Activator {

    /**
     * Runs activation logic.
     *
     * @return void
     */
    public static function activate(): void {
        $post_types = new RATTube_Post_Types();
        $post_types->register();

        rattube_grant_rat_media_capabilities();

        RATTube_Routes::ensure_converter_page();

        flush_rewrite_rules();
        update_option( 'rattube_version', RATTUBE_VERSION, false );
    }
}
