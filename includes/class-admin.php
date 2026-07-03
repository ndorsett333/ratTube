<?php
/**
 * Admin area behavior.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles settings and admin UX scaffolding.
 */
class RATTube_Admin {

    /**
     * Registers hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_menu', array( $this, 'register_cpt_frontend_link' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Adds a submenu item under Rat Media that redirects to the frontend converter page.
     *
     * @return void
     */
    public function register_cpt_frontend_link(): void {
        add_submenu_page(
            'edit.php?post_type=rat_media',
            __( 'Frontend Converter', 'rattube' ),
            __( 'Frontend Converter', 'rattube' ),
            'edit_rat_media_items',
            'rattube-frontend-converter',
            array( $this, 'redirect_to_converter_page' )
        );
    }

    /**
     * Redirects to the frontend converter page.
     *
     * @return void
     */
    public function redirect_to_converter_page(): void {
        if ( ! current_user_can( 'edit_rat_media_items' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'rattube' ) );
        }

        $page_id = (int) get_option( 'rattube_converter_page_id', 0 );
        $url     = $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' . rattube_get_converter_slug() . '/' );

        if ( empty( $url ) ) {
            wp_die( esc_html__( 'The RatTube converter page could not be found.', 'rattube' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Frontend Converter', 'rattube' ); ?></h1>
            <p><?php esc_html_e( 'Open the frontend converter page in a new tab.', 'rattube' ); ?></p>
            <p>
                <a class="button button-primary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e( 'Open Frontend Converter', 'rattube' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Adds settings page.
     *
     * @return void
     */
    public function register_settings_page(): void {
        add_options_page(
            __( 'RatTube Settings', 'rattube' ),
            __( 'RatTube', 'rattube' ),
            'manage_options',
            'rattube',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Registers settings fields.
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'rattube_settings_group',
            'rattube_settings',
            array(
                'sanitize_callback' => array( $this, 'sanitize_settings' ),
                'default'           => array(),
            )
        );

        add_settings_section(
            'rattube_main',
            __( 'Foundation Settings', 'rattube' ),
            array( $this, 'render_settings_section_intro' ),
            'rattube'
        );

        add_settings_field(
            'default_output_format',
            __( 'Default Output Format', 'rattube' ),
            array( $this, 'render_default_output_format_field' ),
            'rattube',
            'rattube_main'
        );

        add_settings_field(
            'enable_worker_placeholder',
            __( 'Enable Worker Placeholder', 'rattube' ),
            array( $this, 'render_worker_checkbox_field' ),
            'rattube',
            'rattube_main'
        );

        add_settings_field(
            'delete_data_on_uninstall',
            __( 'Delete Data On Uninstall', 'rattube' ),
            array( $this, 'render_delete_data_checkbox_field' ),
            'rattube',
            'rattube_main'
        );
    }

    /**
     * Sanitizes settings payload.
     *
     * @param mixed $input Raw settings input.
     *
     * @return array<string, mixed>
     */
    public function sanitize_settings( $input ): array {
        if ( ! is_array( $input ) ) {
            return array();
        }

        $settings = array();

        $settings['default_output_format'] = isset( $input['default_output_format'] )
            ? rattube_sanitize_output_format( $input['default_output_format'] )
            : '';

        $settings['enable_worker_placeholder'] = ! empty( $input['enable_worker_placeholder'] ) ? 1 : 0;
        $settings['delete_data_on_uninstall']  = ! empty( $input['delete_data_on_uninstall'] ) ? 1 : 0;

        return $settings;
    }

    /**
     * Renders settings section description.
     *
     * @return void
     */
    public function render_settings_section_intro(): void {
        echo '<p>' . esc_html__( 'This scaffold prepares RatTube for future conversion services and workers.', 'rattube' ) . '</p>';
    }

    /**
     * Renders default output format select.
     *
     * @return void
     */
    public function render_default_output_format_field(): void {
        $settings = get_option( 'rattube_settings', array() );
        $value    = isset( $settings['default_output_format'] ) ? (string) $settings['default_output_format'] : '';
        $formats  = rattube_get_allowed_output_formats();

        echo '<select name="rattube_settings[default_output_format]">';
        echo '<option value="">' . esc_html__( 'No default', 'rattube' ) . '</option>';
        foreach ( $formats as $key => $label ) {
            printf(
                '<option value="%1$s" %3$s>%2$s</option>',
                esc_attr( $key ),
                esc_html( $label ),
                selected( $value, $key, false )
            );
        }
        echo '</select>';
    }

    /**
     * Renders worker placeholder checkbox.
     *
     * @return void
     */
    public function render_worker_checkbox_field(): void {
        $settings = get_option( 'rattube_settings', array() );
        $checked  = ! empty( $settings['enable_worker_placeholder'] );

        printf(
            '<label><input type="checkbox" name="rattube_settings[enable_worker_placeholder]" value="1" %1$s /> %2$s</label>',
            checked( $checked, true, false ),
            esc_html__( 'Enable future async worker hooks.', 'rattube' )
        );
    }

    /**
     * Renders delete data checkbox.
     *
     * @return void
     */
    public function render_delete_data_checkbox_field(): void {
        $settings = get_option( 'rattube_settings', array() );
        $checked  = ! empty( $settings['delete_data_on_uninstall'] );

        printf(
            '<label><input type="checkbox" name="rattube_settings[delete_data_on_uninstall]" value="1" %1$s /> %2$s</label>',
            checked( $checked, true, false ),
            esc_html__( 'Also remove Rat Media posts and converter page on uninstall.', 'rattube' )
        );
    }

    /**
     * Renders plugin settings page.
     *
     * @return void
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $logs = rattube_get_admin_logs();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'RatTube Settings', 'rattube' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'rattube_settings_group' );
                do_settings_sections( 'rattube' );
                submit_button();
                ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Submission Log', 'rattube' ); ?></h2>
            <?php if ( empty( $logs ) ) : ?>
                <p><?php esc_html_e( 'No submission warnings or errors logged yet.', 'rattube' ); ?></p>
            <?php else : ?>
                <table class="widefat striped rattube-log-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Time', 'rattube' ); ?></th>
                            <th><?php esc_html_e( 'Level', 'rattube' ); ?></th>
                            <th><?php esc_html_e( 'Message', 'rattube' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_reverse( $logs ) as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry['time'] ?? '' ); ?></td>
                                <td><?php echo esc_html( strtoupper( (string) ( $entry['level'] ?? 'info' ) ) ); ?></td>
                                <td><?php echo esc_html( $entry['message'] ?? '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
