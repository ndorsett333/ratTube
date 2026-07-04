<?php
/**
 * Frontend routes and shortcode handling.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles converter shortcode and submission flow.
 */
class RATTube_Routes {

    /**
     * Registers route hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'init', array( $this, 'register_shortcodes' ) );
        add_action( 'template_redirect', array( $this, 'protect_converter_page' ) );
        add_action( 'template_redirect', array( $this, 'protect_rat_media_frontend' ) );
        add_action( 'admin_post_rattube_submit_converter', array( $this, 'handle_converter_submission' ) );
        add_action( 'admin_post_nopriv_rattube_submit_converter', array( $this, 'handle_converter_submission' ) );
    }

    /**
     * Registers shortcode.
     *
     * @return void
     */
    public function register_shortcodes(): void {
        add_shortcode( rattube_get_converter_shortcode_tag(), array( $this, 'render_converter_shortcode' ) );
    }

    /**
     * Renders converter shortcode output.
     *
     * @return string
     */
    public function render_converter_shortcode(): string {
        if ( ! $this->current_user_can_access_converter() ) {
            return '';
        }

        $notice  = $this->get_notice_from_request();
        $formats = rattube_get_allowed_output_formats();

        ob_start();
        include RATTUBE_PLUGIN_DIR . 'templates/frontend-converter-page.php';
        return (string) ob_get_clean();
    }

    /**
     * Handles converter form submission.
     *
     * @return void
     */
    public function handle_converter_submission(): void {
        if ( ! $this->current_user_can_access_converter() ) {
            wp_die( esc_html__( 'You do not have permission to submit to the RatTube converter.', 'rattube' ), '', array( 'response' => 403 ) );
        }

        if ( ! isset( $_POST['rattube_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rattube_nonce'] ) ), 'rattube_submit_converter' ) ) {
            rattube_add_admin_log( __( 'Converter submission rejected due to invalid nonce.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_nonce', 'error' );
        }

        $source_url = isset( $_POST['rattube_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['rattube_source_url'] ) ) : '';
        $name_raw   = isset( $_POST['rattube_media_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rattube_media_name'] ) ) : '';
        $format_raw = isset( $_POST['rattube_output_format'] ) ? sanitize_text_field( wp_unslash( $_POST['rattube_output_format'] ) ) : '';
        $name       = trim( $name_raw );
        $format     = rattube_sanitize_output_format( $format_raw );

        if ( empty( $source_url ) || ! wp_http_validate_url( $source_url ) ) {
            rattube_add_admin_log( __( 'Converter submission failed URL validation.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_url', 'error' );
        }

        if ( empty( $format ) ) {
            rattube_add_admin_log( __( 'Converter submission used an invalid format.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_format', 'error' );
        }

        $resolved_name = $this->resolve_submission_name( $source_url, $name );

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'rat_media',
                'post_title'  => sanitize_text_field( $resolved_name ),
                'post_status' => 'publish',
                'post_content'=> '',
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            rattube_add_admin_log( sprintf( __( 'Failed to create Rat Media item: %s', 'rattube' ), $post_id->get_error_message() ), 'error' );
            $this->redirect_with_notice( 'create_failed', 'error' );
        }

        update_post_meta( $post_id, '_rattube_source_url', $source_url );
        update_post_meta( $post_id, '_rattube_output_format', $format );
        update_post_meta( $post_id, '_rattube_status', 'submitted' );
        update_post_meta( $post_id, '_rattube_file_attachment_id', 0 );
        update_post_meta( $post_id, '_rattube_requested_name', $name );
        update_post_meta( $post_id, '_rattube_resolved_name', $resolved_name );

        $output_basename = (string) pathinfo( sanitize_file_name( $resolved_name ), PATHINFO_FILENAME );
        if ( '' === $output_basename ) {
            $output_basename = 'rat-media-' . (int) $post_id;
        }
        update_post_meta( $post_id, '_rattube_output_basename', $output_basename );

        /**
         * Fires when a valid converter submission is created.
         *
         * @param int   $post_id Newly created post ID.
         * @param array $payload Submission payload.
         */
        do_action(
            'rattube_submission_created',
            $post_id,
            array(
                'source_url'    => $source_url,
                'output_format' => $format,
                'resolved_name' => $resolved_name,
                'output_basename' => $output_basename,
            )
        );

        $this->redirect_with_notice(
            'success',
            'success',
            array(
                'rattube_post_id' => (int) $post_id,
            )
        );
    }

    /**
     * Creates or reuses the converter page.
     *
     * @return void
     */
    public static function ensure_converter_page(): void {
        $slug          = rattube_get_converter_slug();
        $shortcode_tag = rattube_get_converter_shortcode_tag();
        $shortcode     = '[' . $shortcode_tag . ']';

        $existing = get_page_by_path( $slug, OBJECT, 'page' );
        if ( $existing instanceof WP_Post ) {
            update_option( 'rattube_converter_page_id', (int) $existing->ID, false );
            return;
        }

        $post_id = wp_insert_post(
            array(
                'post_type'      => 'page',
                'post_title'     => __( 'RatTube Converter', 'rattube' ),
                'post_name'      => $slug,
                'post_content'   => $shortcode,
                'post_status'    => 'publish',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ),
            true
        );

        if ( ! is_wp_error( $post_id ) ) {
            update_option( 'rattube_converter_page_id', (int) $post_id, false );
        }
    }

    /**
     * Protects the converter page from non-admin access.
     *
     * @return void
     */
    public function protect_converter_page(): void {
        $page_id = (int) get_option( 'rattube_converter_page_id', 0 );

        if ( $page_id <= 0 || ! is_page( $page_id ) || $this->current_user_can_access_converter() ) {
            return;
        }

        wp_die( esc_html__( 'You do not have permission to access this page.', 'rattube' ), '', array( 'response' => 404 ) );
    }

    /**
     * Blocks frontend access to Rat Media posts and archives.
     *
     * @return void
     */
    public function protect_rat_media_frontend(): void {
        if ( is_admin() ) {
            return;
        }

        if ( is_singular( 'rat_media' ) || is_post_type_archive( 'rat_media' ) ) {
            wp_die( esc_html__( 'The requested content is not available.', 'rattube' ), '', array( 'response' => 404 ) );
        }
    }

    /**
     * Determines whether the current user can access the converter.
     *
     * @return bool
     */
    private function current_user_can_access_converter(): bool {
        return is_user_logged_in() && current_user_can( 'manage_options' );
    }

    /**
     * Resolves submission title and output name.
     *
     * @param string $source_url Source URL.
     * @param string $name       User-provided name.
     *
     * @return string
     */
    private function resolve_submission_name( string $source_url, string $name ): string {
        if ( '' !== $name ) {
            return $name;
        }

        $source_title = $this->get_source_title( $source_url );
        if ( '' !== $source_title ) {
            return $source_title;
        }

        $host = wp_parse_url( $source_url, PHP_URL_HOST );

        return sprintf(
            /* translators: %s: source host name. */
            __( 'Rat Media submission from %s', 'rattube' ),
            $host ?: __( 'unknown source', 'rattube' )
        );
    }

    /**
     * Attempts to fetch a source title for known providers.
     *
     * @param string $source_url Source URL.
     *
     * @return string
     */
    private function get_source_title( string $source_url ): string {
        $host = (string) wp_parse_url( $source_url, PHP_URL_HOST );
        if ( false === strpos( $host, 'youtube.com' ) && false === strpos( $host, 'youtu.be' ) ) {
            return '';
        }

        $endpoint = add_query_arg(
            array(
                'url'    => $source_url,
                'format' => 'json',
            ),
            'https://www.youtube.com/oembed'
        );

        $response = wp_remote_get(
            $endpoint,
            array(
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status_code ) {
            return '';
        }

        $payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $payload ) || empty( $payload['title'] ) ) {
            return '';
        }

        return sanitize_text_field( (string) $payload['title'] );
    }

    /**
     * Resolves a frontend notice from query args.
     *
     * @return array<string, string>
     */
    private function get_notice_from_request(): array {
        $code = isset( $_GET['rattube_notice'] ) ? sanitize_key( wp_unslash( $_GET['rattube_notice'] ) ) : '';
        $type = isset( $_GET['rattube_type'] ) ? sanitize_key( wp_unslash( $_GET['rattube_type'] ) ) : 'info';

        $messages = array(
            'success'       => __( 'Submission received. A Rat Media entry has been created for processing.', 'rattube' ),
            'invalid_nonce' => __( 'Your session expired. Please try again.', 'rattube' ),
            'invalid_url'   => __( 'Please provide a valid source URL.', 'rattube' ),
            'invalid_format'=> __( 'Please choose a valid output format.', 'rattube' ),
            'create_failed' => __( 'Could not save your submission. Please try again.', 'rattube' ),
        );

        if ( ! array_key_exists( $code, $messages ) ) {
            return array();
        }

        return array(
            'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
            'message' => $messages[ $code ],
        );
    }

    /**
     * Redirects back to converter page with notice query args.
     *
     * @param string $notice_code Notice code.
     * @param string $type        Notice type.
     * @param array  $extra_args  Additional query args.
     *
     * @return void
     */
    private function redirect_with_notice( string $notice_code, string $type = 'info', array $extra_args = array() ): void {
        $redirect_url = wp_get_referer();

        if ( empty( $redirect_url ) ) {
            $page_id = (int) get_option( 'rattube_converter_page_id', 0 );
            if ( $page_id > 0 ) {
                $redirect_url = get_permalink( $page_id );
            }
        }

        if ( empty( $redirect_url ) ) {
            $redirect_url = home_url( '/' . rattube_get_converter_slug() . '/' );
        }

        $args = array_merge(
            array(
                'rattube_notice' => sanitize_key( $notice_code ),
                'rattube_type'   => sanitize_key( $type ),
            ),
            $extra_args
        );

        wp_safe_redirect( add_query_arg( $args, $redirect_url ) );
        exit;
    }
}
