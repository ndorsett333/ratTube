<?php
/**
 * Shared helper functions.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns allowed output formats.
 *
 * @return array<string, string>
 */
function rattube_get_allowed_output_formats(): array {
    $formats = array(
        'mp3' => __( 'MP3 Audio', 'rattube' ),
        'mp4' => __( 'MP4 Video', 'rattube' ),
        'wav' => __( 'WAV Audio', 'rattube' ),
    );

    /**
     * Filters the allowed output formats.
     *
     * @param array<string, string> $formats Allowed formats.
     */
    return apply_filters( 'rattube_allowed_output_formats', $formats );
}

/**
 * Sanitizes and validates output format.
 *
 * @param mixed $format Requested format.
 *
 * @return string
 */
function rattube_sanitize_output_format( $format ): string {
    $format  = sanitize_key( (string) $format );
    $formats = rattube_get_allowed_output_formats();

    return array_key_exists( $format, $formats ) ? $format : '';
}

/**
 * Gets converter page slug.
 *
 * @return string
 */
function rattube_get_converter_slug(): string {
    $slug = 'rat-media-convert';

    return sanitize_title( (string) apply_filters( 'rattube_converter_slug', $slug ) );
}

/**
 * Gets converter shortcode tag.
 *
 * @return string
 */
function rattube_get_converter_shortcode_tag(): string {
    return 'rattube_converter';
}

/**
 * Returns Rat Media capability map.
 *
 * @return array<string, string>
 */
function rattube_get_rat_media_capabilities(): array {
    return array(
        'edit_post'              => 'edit_rat_media',
        'read_post'              => 'read_rat_media',
        'delete_post'            => 'delete_rat_media',
        'edit_posts'             => 'edit_rat_media_items',
        'edit_others_posts'     => 'edit_others_rat_media_items',
        'publish_posts'          => 'publish_rat_media_items',
        'read_private_posts'    => 'read_private_rat_media_items',
        'create_posts'          => 'edit_rat_media_items',
    );
}

/**
 * Returns all Rat Media primitive capabilities.
 *
 * @return array<int, string>
 */
function rattube_get_rat_media_capability_names(): array {
    return array_values( rattube_get_rat_media_capabilities() );
}

/**
 * Grants Rat Media capabilities to roles that should see and manage the CPT.
 *
 * @return void
 */
function rattube_grant_rat_media_capabilities(): void {
    $capabilities = rattube_get_rat_media_capability_names();

    $role = get_role( 'administrator' );

    if ( ! $role instanceof WP_Role ) {
        return;
    }

    foreach ( $capabilities as $capability ) {
        $role->add_cap( $capability );
    }
}

/**
 * Appends a simple admin log entry.
 *
 * @param string $message Log message.
 * @param string $level   error|warning|info.
 *
 * @return void
 */
function rattube_add_admin_log( string $message, string $level = 'error' ): void {
    $logs = get_option( 'rattube_admin_logs', array() );

    if ( ! is_array( $logs ) ) {
        $logs = array();
    }

    $logs[] = array(
        'time'    => current_time( 'mysql' ),
        'level'   => sanitize_key( $level ),
        'message' => sanitize_text_field( $message ),
    );

    if ( count( $logs ) > 25 ) {
        $logs = array_slice( $logs, -25 );
    }

    update_option( 'rattube_admin_logs', $logs, false );
}

/**
 * Returns admin logs.
 *
 * @return array<int, array<string, string>>
 */
function rattube_get_admin_logs(): array {
    $logs = get_option( 'rattube_admin_logs', array() );

    return is_array( $logs ) ? $logs : array();
}
