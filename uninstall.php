<?php
/**
 * Uninstall handler.
 *
 * @package RatTube
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'rattube_settings', array() );

$delete_data_on_uninstall = ! empty( $settings['delete_data_on_uninstall'] );
if ( $delete_data_on_uninstall ) {
    $rat_media_posts = get_posts(
        array(
            'post_type'      => 'rat_media',
            'post_status'    => 'any',
            'numberposts'    => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'suppress_filters' => true,
        )
    );

    foreach ( $rat_media_posts as $post_id ) {
        wp_delete_post( (int) $post_id, true );
    }

    $converter_page_id = (int) get_option( 'rattube_converter_page_id', 0 );
    if ( $converter_page_id > 0 ) {
        wp_delete_post( $converter_page_id, true );
    }
}

delete_option( 'rattube_settings' );
delete_option( 'rattube_admin_logs' );
delete_option( 'rattube_converter_page_id' );
delete_option( 'rattube_version' );
