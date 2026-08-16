<?php
/**
 * RatTube tools page and actions.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages tool installation, diagnostics, and conversion testing.
 */
class RATTube_Tools {

    /**
     * Registers hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'admin_menu', array( $this, 'register_tools_page' ) );
        add_action( 'admin_post_rattube_install_yt_dlp', array( $this, 'handle_install_yt_dlp' ) );
        add_action( 'admin_post_rattube_install_ffmpeg', array( $this, 'handle_install_ffmpeg' ) );
        add_action( 'admin_post_rattube_run_conversion_test', array( $this, 'handle_conversion_test' ) );
        add_action( 'admin_post_rattube_save_tool_paths', array( $this, 'handle_save_tool_paths' ) );
    }

    /**
     * Adds tools page under Rat Media.
     *
     * @return void
     */
    public function register_tools_page(): void {
        add_submenu_page(
            'edit.php?post_type=rat_media',
            __( 'RatTube Tools', 'rattube' ),
            __( 'RatTube Tools', 'rattube' ),
            'manage_options',
            'rattube-tools',
            array( $this, 'render_tools_page' )
        );
    }

    /**
     * Renders tools page.
     *
     * @return void
     */
    public function render_tools_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $notice_code = isset( $_GET['rattube_tools_notice'] ) ? sanitize_key( wp_unslash( $_GET['rattube_tools_notice'] ) ) : '';
        $notice_msg  = isset( $_GET['rattube_tools_message'] ) ? sanitize_text_field( rawurldecode( (string) wp_unslash( $_GET['rattube_tools_message'] ) ) ) : '';
        $diagnostics = $this->collect_diagnostics();
        $overrides   = rattube_get_tool_path_overrides();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'RatTube Tools', 'rattube' ); ?></h1>

            <?php if ( '' !== $notice_code && '' !== $notice_msg ) : ?>
                <div class="notice <?php echo 'error' === $notice_code ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                    <p><?php echo esc_html( $notice_msg ); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Install / Update Tools', 'rattube' ); ?></h2>
            <p><?php esc_html_e( 'Install executables into uploads/rattube-tools/bin for RatTube worker use.', 'rattube' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 8px;">
                <input type="hidden" name="action" value="rattube_install_yt_dlp" />
                <?php wp_nonce_field( 'rattube_install_yt_dlp', 'rattube_nonce' ); ?>
                <?php submit_button( __( 'Install / Update yt-dlp', 'rattube' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 8px;">
                <input type="hidden" name="action" value="rattube_install_ffmpeg" />
                <?php wp_nonce_field( 'rattube_install_ffmpeg', 'rattube_nonce' ); ?>
                <?php submit_button( __( 'Install / Update ffmpeg', 'rattube' ), 'secondary', 'submit', false ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
                <input type="hidden" name="action" value="rattube_run_conversion_test" />
                <?php wp_nonce_field( 'rattube_run_conversion_test', 'rattube_nonce' ); ?>
                <?php submit_button( __( 'One-Click Conversion Test', 'rattube' ), 'primary', 'submit', false ); ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Manual Tool Paths', 'rattube' ); ?></h2>
            <p><?php esc_html_e( 'Optional overrides. If set and executable, RatTube uses these first, then plugin-installed binaries, then system binaries.', 'rattube' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 980px;">
                <input type="hidden" name="action" value="rattube_save_tool_paths" />
                <?php wp_nonce_field( 'rattube_save_tool_paths', 'rattube_nonce' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="rattube_yt_dlp_path"><?php esc_html_e( 'yt-dlp binary path', 'rattube' ); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="rattube_yt_dlp_path" name="rattube_yt_dlp_path" value="<?php echo esc_attr( $overrides['yt_dlp_path'] ?? '' ); ?>" placeholder="/usr/local/bin/yt-dlp" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="rattube_ffmpeg_path"><?php esc_html_e( 'ffmpeg binary path or directory', 'rattube' ); ?></label></th>
                        <td>
                            <input type="text" class="regular-text code" id="rattube_ffmpeg_path" name="rattube_ffmpeg_path" value="<?php echo esc_attr( $overrides['ffmpeg_path'] ?? '' ); ?>" placeholder="/usr/local/bin/ffmpeg or /usr/local/bin" />
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Save Tool Paths', 'rattube' ), 'secondary', 'submit', false ); ?>
            </form>

            <hr />
            <h2><?php esc_html_e( 'Diagnostics', 'rattube' ); ?></h2>
            <table class="widefat striped" style="max-width: 980px;">
                <tbody>
                    <?php foreach ( $diagnostics as $row ) : ?>
                        <tr>
                            <th style="width: 320px;"><?php echo esc_html( $row['label'] ); ?></th>
                            <td><?php echo esc_html( $row['value'] ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handles yt-dlp installation.
     *
     * @return void
     */
    public function handle_install_yt_dlp(): void {
        $this->authorize_action( 'rattube_install_yt_dlp' );

        $bin_dir = rattube_get_tools_bin_dir();
        if ( '' === $bin_dir || ! wp_mkdir_p( $bin_dir ) ) {
            $this->redirect_with_tools_notice( 'error', __( 'Could not create tools bin directory.', 'rattube' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        $downloaded_file = download_url( 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp', 60 );
        if ( is_wp_error( $downloaded_file ) ) {
            $this->redirect_with_tools_notice( 'error', sprintf( __( 'yt-dlp download failed: %s', 'rattube' ), $downloaded_file->get_error_message() ) );
        }

        $target_path = rattube_get_local_tool_path( 'yt-dlp' );
        if ( ! @copy( $downloaded_file, $target_path ) ) {
            @unlink( $downloaded_file );
            $this->redirect_with_tools_notice( 'error', __( 'Could not copy yt-dlp binary into tools directory.', 'rattube' ) );
        }

        @unlink( $downloaded_file );
        @chmod( $target_path, 0755 );

        $this->redirect_with_tools_notice( 'success', __( 'yt-dlp installed/updated successfully.', 'rattube' ) );
    }

    /**
     * Handles ffmpeg installation.
     *
     * @return void
     */
    public function handle_install_ffmpeg(): void {
        $this->authorize_action( 'rattube_install_ffmpeg' );

        $bin_dir = rattube_get_tools_bin_dir();
        if ( '' === $bin_dir || ! wp_mkdir_p( $bin_dir ) ) {
            $this->redirect_with_tools_notice( 'error', __( 'Could not create tools bin directory.', 'rattube' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( stripos( PHP_OS_FAMILY, 'Linux' ) !== 0 ) {
            $this->redirect_with_tools_notice( 'error', __( 'Automated ffmpeg install currently supports Linux only.', 'rattube' ) );
        }

        $downloaded_file = download_url( 'https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz', 120 );
        if ( is_wp_error( $downloaded_file ) ) {
            $this->redirect_with_tools_notice( 'error', sprintf( __( 'ffmpeg download failed: %s', 'rattube' ), $downloaded_file->get_error_message() ) );
        }

        $tar_path = rattube_find_executable( array( 'tar' ) );
        if ( '' === $tar_path ) {
            @unlink( $downloaded_file );
            $this->redirect_with_tools_notice( 'error', __( 'ffmpeg extraction failed: tar is unavailable in this PHP environment. Install ffmpeg manually for Local, or expose tar to PHP PATH.', 'rattube' ) );
        }

        $xz_path = rattube_find_executable( array( 'xz' ) );
        if ( '' === $xz_path ) {
            @unlink( $downloaded_file );
            $this->redirect_with_tools_notice( 'error', __( 'ffmpeg extraction failed: xz is unavailable in this PHP environment. Set manual ffmpeg path on this page, or install xz for automated extraction.', 'rattube' ) );
        }

        $tmp_extract_dir = trailingslashit( rattube_get_tools_base_dir() ) . 'tmp-extract';
        if ( ! wp_mkdir_p( $tmp_extract_dir ) ) {
            @unlink( $downloaded_file );
            $this->redirect_with_tools_notice( 'error', __( 'Could not create temporary extract directory.', 'rattube' ) );
        }

        $extract_command = sprintf(
            '%3$s -xJf %1$s -C %2$s',
            escapeshellarg( $downloaded_file ),
            escapeshellarg( $tmp_extract_dir ),
            escapeshellarg( $tar_path )
        );
        $extract_result = $this->run_command( $extract_command );
        @unlink( $downloaded_file );

        if ( 0 !== $extract_result['exit_code'] ) {
            $this->cleanup_dir( $tmp_extract_dir );
            $this->redirect_with_tools_notice( 'error', sprintf( __( 'ffmpeg extraction failed: %s', 'rattube' ), $extract_result['output'] ) );
        }

        $ffmpeg_matches = glob( $tmp_extract_dir . '/ffmpeg-*-amd64-static/ffmpeg' );
        $ffprobe_matches = glob( $tmp_extract_dir . '/ffmpeg-*-amd64-static/ffprobe' );

        if ( ! is_array( $ffmpeg_matches ) || empty( $ffmpeg_matches ) ) {
            $this->cleanup_dir( $tmp_extract_dir );
            $this->redirect_with_tools_notice( 'error', __( 'ffmpeg binary not found in extracted archive.', 'rattube' ) );
        }

        $ffmpeg_source = (string) array_pop( $ffmpeg_matches );
        $ffprobe_source = is_array( $ffprobe_matches ) && ! empty( $ffprobe_matches ) ? (string) array_pop( $ffprobe_matches ) : '';

        $ffmpeg_target = rattube_get_local_tool_path( 'ffmpeg' );
        if ( ! @copy( $ffmpeg_source, $ffmpeg_target ) ) {
            $this->cleanup_dir( $tmp_extract_dir );
            $this->redirect_with_tools_notice( 'error', __( 'Could not copy ffmpeg binary into tools directory.', 'rattube' ) );
        }

        @chmod( $ffmpeg_target, 0755 );

        if ( '' !== $ffprobe_source ) {
            $ffprobe_target = rattube_get_local_tool_path( 'ffprobe' );
            @copy( $ffprobe_source, $ffprobe_target );
            @chmod( $ffprobe_target, 0755 );
        }

        $this->cleanup_dir( $tmp_extract_dir );

        $this->redirect_with_tools_notice( 'success', __( 'ffmpeg installed/updated successfully.', 'rattube' ) );
    }

    /**
     * Handles one-click conversion test.
     *
     * @return void
     */
    public function handle_conversion_test(): void {
        $this->authorize_action( 'rattube_run_conversion_test' );

        $test_post = get_posts(
            array(
                'post_type'      => 'rat_media',
                'posts_per_page' => 1,
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'orderby'        => 'date',
                'order'          => 'DESC',
                'meta_query'     => array(
                    array(
                        'key'   => '_rattube_output_format',
                        'value' => 'mp3',
                    ),
                ),
            )
        );

        if ( empty( $test_post ) || ! $test_post[0] instanceof WP_Post ) {
            $this->redirect_with_tools_notice( 'error', __( 'No MP3 Rat Media submission found for testing.', 'rattube' ) );
        }

        $post_id = (int) $test_post[0]->ID;

        $worker = new RATTube_Converter_Worker();
        $worker->process_submission( $post_id );

        $status = (string) get_post_meta( $post_id, '_rattube_status', true );

        if ( 'completed' === $status ) {
            $this->redirect_with_tools_notice( 'success', sprintf( __( 'Conversion test completed successfully for post ID %d.', 'rattube' ), $post_id ) );
        }

        $message = (string) get_post_meta( $post_id, '_rattube_worker_message', true );
        if ( '' === $message ) {
            $message = __( 'Conversion test did not complete successfully.', 'rattube' );
        }

        $this->redirect_with_tools_notice( 'error', sprintf( __( 'Conversion test failed for post ID %1$d: %2$s', 'rattube' ), $post_id, $message ) );
    }

    /**
     * Saves manual tool path overrides.
     *
     * @return void
     */
    public function handle_save_tool_paths(): void {
        $this->authorize_action( 'rattube_save_tool_paths' );

        $yt_dlp_path = isset( $_POST['rattube_yt_dlp_path'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rattube_yt_dlp_path'] ) ) ) : '';
        $ffmpeg_path = isset( $_POST['rattube_ffmpeg_path'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['rattube_ffmpeg_path'] ) ) ) : '';

        if ( '' !== $yt_dlp_path && ( ! is_file( $yt_dlp_path ) || ! is_executable( $yt_dlp_path ) ) ) {
            $this->redirect_with_tools_notice( 'error', __( 'yt-dlp override path is not an executable file.', 'rattube' ) );
        }

        if ( '' !== $ffmpeg_path ) {
            $ffmpeg_valid = false;
            if ( is_file( $ffmpeg_path ) && is_executable( $ffmpeg_path ) ) {
                $ffmpeg_valid = true;
            }

            if ( is_dir( $ffmpeg_path ) ) {
                $candidate = trailingslashit( $ffmpeg_path ) . 'ffmpeg';
                if ( is_file( $candidate ) && is_executable( $candidate ) ) {
                    $ffmpeg_valid = true;
                }
            }

            if ( ! $ffmpeg_valid ) {
                $this->redirect_with_tools_notice( 'error', __( 'ffmpeg override must be an executable file or a directory containing ffmpeg.', 'rattube' ) );
            }
        }

        update_option(
            'rattube_tool_overrides',
            array(
                'yt_dlp_path' => $yt_dlp_path,
                'ffmpeg_path' => $ffmpeg_path,
            ),
            false
        );

        $this->redirect_with_tools_notice( 'success', __( 'Tool paths saved.', 'rattube' ) );
    }

    /**
     * Collects diagnostic information for display.
     *
     * @return array<int, array{label:string,value:string}>
     */
    private function collect_diagnostics(): array {
        $proc_open_available = function_exists( 'proc_open' ) ? __( 'Yes', 'rattube' ) : __( 'No', 'rattube' );
        $disabled_functions  = (string) ini_get( 'disable_functions' );
        $bin_dir             = rattube_get_tools_bin_dir();
        $local_yt_dlp        = rattube_get_local_tool_path( 'yt-dlp' );
        $local_ffmpeg        = rattube_get_local_tool_path( 'ffmpeg' );
        $override_yt_dlp     = rattube_get_tool_path_override( 'yt_dlp_path' );
        $override_ffmpeg     = rattube_get_tool_path_override( 'ffmpeg_path' );
        $effective_yt_dlp    = $this->resolve_effective_yt_dlp();
        $effective_ffmpeg    = $this->resolve_effective_ffmpeg();
        $yt_dlp_version      = $this->read_binary_version( $local_yt_dlp, 'yt-dlp --version' );
        $ffmpeg_version      = $this->read_binary_version( $local_ffmpeg, 'ffmpeg -version | head -n 1' );
        $last_log            = '';

        $logs = rattube_get_admin_logs();
        if ( ! empty( $logs ) ) {
            $entry = (array) array_pop( $logs );
            $last_log = (string) ( $entry['time'] ?? '' ) . ' ' . (string) ( $entry['message'] ?? '' );
        }

        return array(
            array(
                'label' => __( 'proc_open available', 'rattube' ),
                'value' => $proc_open_available,
            ),
            array(
                'label' => __( 'Disabled functions', 'rattube' ),
                'value' => '' !== $disabled_functions ? $disabled_functions : __( 'None listed', 'rattube' ),
            ),
            array(
                'label' => __( 'Tools bin directory', 'rattube' ),
                'value' => '' !== $bin_dir ? $bin_dir : __( 'Unavailable', 'rattube' ),
            ),
            array(
                'label' => __( 'Local yt-dlp binary', 'rattube' ),
                'value' => ( '' !== $local_yt_dlp && is_file( $local_yt_dlp ) ) ? $local_yt_dlp : __( 'Not installed', 'rattube' ),
            ),
            array(
                'label' => __( 'Manual yt-dlp override', 'rattube' ),
                'value' => '' !== $override_yt_dlp ? $override_yt_dlp : __( 'Not set', 'rattube' ),
            ),
            array(
                'label' => __( 'Local ffmpeg binary', 'rattube' ),
                'value' => ( '' !== $local_ffmpeg && is_file( $local_ffmpeg ) ) ? $local_ffmpeg : __( 'Not installed', 'rattube' ),
            ),
            array(
                'label' => __( 'Manual ffmpeg override', 'rattube' ),
                'value' => '' !== $override_ffmpeg ? $override_ffmpeg : __( 'Not set', 'rattube' ),
            ),
            array(
                'label' => __( 'Effective yt-dlp path', 'rattube' ),
                'value' => '' !== $effective_yt_dlp ? $effective_yt_dlp : __( 'Not resolved', 'rattube' ),
            ),
            array(
                'label' => __( 'Effective ffmpeg path', 'rattube' ),
                'value' => '' !== $effective_ffmpeg ? $effective_ffmpeg : __( 'Not resolved', 'rattube' ),
            ),
            array(
                'label' => __( 'yt-dlp version', 'rattube' ),
                'value' => $yt_dlp_version,
            ),
            array(
                'label' => __( 'ffmpeg version', 'rattube' ),
                'value' => $ffmpeg_version,
            ),
            array(
                'label' => __( 'Last RatTube log entry', 'rattube' ),
                'value' => '' !== trim( $last_log ) ? $last_log : __( 'No logs yet', 'rattube' ),
            ),
        );
    }

    /**
     * Authorizes a tools admin action.
     *
     * @param string $action_nonce Action nonce key.
     *
     * @return void
     */
    private function authorize_action( string $action_nonce ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'rattube' ), '', array( 'response' => 403 ) );
        }

        if ( ! isset( $_POST['rattube_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rattube_nonce'] ) ), $action_nonce ) ) {
            wp_die( esc_html__( 'Invalid security token.', 'rattube' ), '', array( 'response' => 403 ) );
        }
    }

    /**
     * Redirects to tools page with a notice.
     *
     * @param string $type    success|error.
     * @param string $message Notice message.
     *
     * @return void
     */
    private function redirect_with_tools_notice( string $type, string $message ): void {
        $url = add_query_arg(
            array(
                'post_type'             => 'rat_media',
                'page'                  => 'rattube-tools',
                'rattube_tools_notice'  => sanitize_key( $type ),
                'rattube_tools_message' => $message,
            ),
            admin_url( 'edit.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Reads version output for a binary.
     *
     * @param string $local_binary Local binary path.
     * @param string $system_cmd   System fallback command.
     *
     * @return string
     */
    private function read_binary_version( string $local_binary, string $system_cmd ): string {
        if ( '' !== $local_binary && is_file( $local_binary ) && is_executable( $local_binary ) ) {
            $cmd = escapeshellarg( $local_binary ) . ' --version';
            if ( $this->is_python_script( $local_binary ) ) {
                $python = rattube_find_executable( array( 'python3', 'python' ) );
                if ( '' !== $python ) {
                    $cmd = escapeshellarg( $python ) . ' ' . escapeshellarg( $local_binary ) . ' --version';
                }
            }

            $out = $this->run_command( $cmd );
            if ( 0 === $out['exit_code'] ) {
                return strtok( trim( $out['output'] ), "\n" ) ?: __( 'Detected', 'rattube' );
            }
        }

        $out = $this->run_command( $system_cmd );
        if ( 0 === $out['exit_code'] && '' !== trim( $out['output'] ) ) {
            return strtok( trim( $out['output'] ), "\n" ) ?: __( 'Detected', 'rattube' );
        }

        return __( 'Not detected', 'rattube' );
    }

    /**
     * Resolves effective yt-dlp path in worker order.
     *
     * @return string
     */
    private function resolve_effective_yt_dlp(): string {
        $override = rattube_get_tool_path_override( 'yt_dlp_path' );
        if ( '' !== $override && is_file( $override ) && is_executable( $override ) ) {
            return $override;
        }

        $local = rattube_get_local_tool_path( 'yt-dlp' );
        if ( '' !== $local && is_file( $local ) && is_executable( $local ) ) {
            return $local;
        }

        return rattube_find_executable( array( 'yt-dlp', 'youtube-dl' ) );
    }

    /**
     * Resolves effective ffmpeg path in worker order.
     *
     * @return string
     */
    private function resolve_effective_ffmpeg(): string {
        $override = rattube_get_tool_path_override( 'ffmpeg_path' );
        if ( '' !== $override ) {
            if ( is_file( $override ) && is_executable( $override ) ) {
                return $override;
            }

            if ( is_dir( $override ) ) {
                $candidate = trailingslashit( $override ) . 'ffmpeg';
                if ( is_file( $candidate ) && is_executable( $candidate ) ) {
                    return $candidate;
                }
            }
        }

        $local = rattube_get_local_tool_path( 'ffmpeg' );
        if ( '' !== $local && is_file( $local ) && is_executable( $local ) ) {
            return $local;
        }

        return rattube_find_executable( array( 'ffmpeg' ) );
    }

    /**
     * Runs shell command.
     *
     * @param string $command Shell command.
     *
     * @return array{exit_code:int,output:string}
     */
    private function run_command( string $command ): array {
        if ( ! function_exists( 'proc_open' ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'proc_open unavailable', 'rattube' ),
            );
        }

        $disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
        if ( in_array( 'proc_open', $disabled, true ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'proc_open disabled', 'rattube' ),
            );
        }

        $spec = array(
            0 => array( 'pipe', 'r' ),
            1 => array( 'pipe', 'w' ),
            2 => array( 'pipe', 'w' ),
        );

        $process = proc_open( array( '/bin/sh', '-c', $command ), $spec, $pipes );
        if ( ! is_resource( $process ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'Failed to start process', 'rattube' ),
            );
        }

        fclose( $pipes[0] );
        $stdout = stream_get_contents( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );

        $exit_code = proc_close( $process );

        return array(
            'exit_code' => (int) $exit_code,
            'output'    => trim( (string) $stdout . "\n" . (string) $stderr ),
        );
    }

    /**
     * Removes temporary extraction directory recursively.
     *
     * @param string $dir Directory path.
     *
     * @return void
     */
    private function cleanup_dir( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $entries = glob( trailingslashit( $dir ) . '*' );
        if ( is_array( $entries ) ) {
            foreach ( $entries as $entry ) {
                if ( is_dir( $entry ) ) {
                    $this->cleanup_dir( $entry );
                } elseif ( is_file( $entry ) ) {
                    @unlink( $entry );
                }
            }
        }

        @rmdir( $dir );
    }

    /**
     * Determines whether a file is a Python script.
     *
     * @param string $file_path Absolute file path.
     *
     * @return bool
     */
    private function is_python_script( string $file_path ): bool {
        if ( ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
            return false;
        }

        $handle = @fopen( $file_path, 'rb' );
        if ( false === $handle ) {
            return false;
        }

        $first_line = fgets( $handle );
        fclose( $handle );

        if ( false === $first_line ) {
            return false;
        }

        return false !== stripos( $first_line, 'python' );
    }
}
