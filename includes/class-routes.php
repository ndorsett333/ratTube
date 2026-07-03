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
        if ( ! isset( $_POST['rattube_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rattube_nonce'] ) ), 'rattube_submit_converter' ) ) {
            rattube_add_admin_log( __( 'Converter submission rejected due to invalid nonce.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_nonce', 'error' );
        }

        $source_url = isset( $_POST['rattube_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['rattube_source_url'] ) ) : '';
        $format_raw = isset( $_POST['rattube_output_format'] ) ? sanitize_text_field( wp_unslash( $_POST['rattube_output_format'] ) ) : '';
        $format     = rattube_sanitize_output_format( $format_raw );

        if ( empty( $source_url ) || ! wp_http_validate_url( $source_url ) ) {
            rattube_add_admin_log( __( 'Converter submission failed URL validation.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_url', 'error' );
        }

        if ( empty( $format ) ) {
            rattube_add_admin_log( __( 'Converter submission used an invalid format.', 'rattube' ), 'warning' );
            $this->redirect_with_notice( 'invalid_format', 'error' );
        }

        $post_title = sprintf(
            /* translators: %s: source host name. */
            __( 'Rat Media submission from %s', 'rattube' ),
            wp_parse_url( $source_url, PHP_URL_HOST ) ?: __( 'unknown source', 'rattube' )
        );

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'rat_media',
                'post_title'  => sanitize_text_field( $post_title ),
                'post_status' => 'draft',
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
