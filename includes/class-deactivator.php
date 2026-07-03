<?php
/**
 * Plugin deactivation handler.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles deactivation actions.
 */
class RATTube_Deactivator {

    /**
     * Runs deactivation logic.
     *
     * @return void
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
