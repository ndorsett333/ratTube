<?php
/**
 * Frontend integration helpers.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides extension points for submission lifecycle.
 */
class RATTube_Frontend {

    /**
     * Registers hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'rattube_submission_created', array( $this, 'prepare_submission_pipeline' ), 10, 2 );
    }

    /**
     * Stores baseline state and exposes hook for future job dispatch.
     *
     * @param int   $post_id Rat Media post ID.
     * @param array $payload Submission payload.
     *
     * @return void
     */
    public function prepare_submission_pipeline( int $post_id, array $payload ): void {
        if ( metadata_exists( 'post', $post_id, '_rattube_queue_state' ) ) {
            return;
        }

        update_post_meta( $post_id, '_rattube_queue_state', 'not_queued' );

        /**
         * Fires after RatTube stores initial queue metadata.
         *
         * @param int   $post_id Rat Media post ID.
         * @param array $payload Sanitized payload.
         */
        do_action( 'rattube_after_submission_prepared', $post_id, $payload );
    }
}
