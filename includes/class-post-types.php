<?php
/**
 * Custom post type registration.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers Rat Media CPT and meta.
 */
class RATTube_Post_Types {

    /**
     * Registers hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'init', array( $this, 'register' ) );
    }

    /**
     * Registers post type and meta.
     *
     * @return void
     */
    public function register(): void {
        $labels = array(
            'name'                  => __( 'Rat Media', 'rattube' ),
            'singular_name'         => __( 'Rat Media Item', 'rattube' ),
            'menu_name'             => __( 'Rat Media', 'rattube' ),
            'name_admin_bar'        => __( 'Rat Media Item', 'rattube' ),
            'add_new'               => __( 'Add New', 'rattube' ),
            'add_new_item'          => __( 'Add New Rat Media Item', 'rattube' ),
            'new_item'              => __( 'New Rat Media Item', 'rattube' ),
            'edit_item'             => __( 'Edit Rat Media Item', 'rattube' ),
            'view_item'             => __( 'View Rat Media Item', 'rattube' ),
            'all_items'             => __( 'Rat Media', 'rattube' ),
            'search_items'          => __( 'Search Rat Media', 'rattube' ),
            'not_found'             => __( 'No Rat Media found.', 'rattube' ),
            'not_found_in_trash'    => __( 'No Rat Media found in Trash.', 'rattube' ),
            'featured_image'        => __( 'Cover Image', 'rattube' ),
            'set_featured_image'    => __( 'Set cover image', 'rattube' ),
            'remove_featured_image' => __( 'Remove cover image', 'rattube' ),
            'use_featured_image'    => __( 'Use as cover image', 'rattube' ),
            'archives'              => __( 'Rat Media Archives', 'rattube' ),
        );

        register_post_type(
            'rat_media',
            array(
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'show_in_rest'       => true,
                'query_var'          => true,
                'rewrite'            => array( 'slug' => 'rat-media' ),
                'capability_type'    => array( 'rat_media', 'rat_media_items' ),
                'map_meta_cap'       => true,
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 26,
                'menu_icon'          => 'dashicons-format-video',
                'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
            )
        );

        $this->register_meta();
    }

    /**
     * Registers post meta fields for future processing.
     *
     * @return void
     */
    private function register_meta(): void {
        register_post_meta(
            'rat_media',
            '_rattube_source_url',
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => array( $this, 'sanitize_source_url' ),
                'auth_callback'     => array( $this, 'meta_auth_callback' ),
            )
        );

        register_post_meta(
            'rat_media',
            '_rattube_output_format',
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => array( $this, 'sanitize_output_format' ),
                'auth_callback'     => array( $this, 'meta_auth_callback' ),
            )
        );

        register_post_meta(
            'rat_media',
            '_rattube_status',
            array(
                'type'              => 'string',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => array( $this, 'sanitize_status' ),
                'auth_callback'     => array( $this, 'meta_auth_callback' ),
            )
        );

        register_post_meta(
            'rat_media',
            '_rattube_file_attachment_id',
            array(
                'type'              => 'integer',
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => array( $this, 'sanitize_attachment_id' ),
                'auth_callback'     => array( $this, 'meta_auth_callback' ),
            )
        );
    }

    /**
     * Sanitizes source URL.
     *
     * @param mixed $value Meta value.
     *
     * @return string
     */
    public function sanitize_source_url( $value ): string {
        $url = esc_url_raw( (string) $value );

        return wp_http_validate_url( $url ) ? $url : '';
    }

    /**
     * Sanitizes output format.
     *
     * @param mixed $value Meta value.
     *
     * @return string
     */
    public function sanitize_output_format( $value ): string {
        return rattube_sanitize_output_format( $value );
    }

    /**
     * Sanitizes status.
     *
     * @param mixed $value Meta value.
     *
     * @return string
     */
    public function sanitize_status( $value ): string {
        $status  = sanitize_key( (string) $value );
        $allowed = array( 'submitted', 'queued', 'processing', 'completed', 'failed' );

        return in_array( $status, $allowed, true ) ? $status : 'submitted';
    }

    /**
     * Sanitizes attachment ID.
     *
     * @param mixed $value Meta value.
     *
     * @return int
     */
    public function sanitize_attachment_id( $value ): int {
        return absint( $value );
    }

    /**
     * Restricts meta access to users who can edit the post.
     *
     * @param bool   $allowed  Current permission.
     * @param string $meta_key Meta key.
     * @param int    $post_id  Post ID.
     * @param int    $user_id  User ID.
     *
     * @return bool
     */
    public function meta_auth_callback( bool $allowed, string $meta_key, int $post_id, int $user_id ): bool {
        unset( $allowed, $meta_key );

        return user_can( $user_id, 'edit_post', $post_id );
    }
}
