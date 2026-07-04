<?php
/**
 * MP3 conversion worker.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;

/**
 * Processes Rat Media conversion jobs.
 */
class RATTube_Converter_Worker {

    /**
     * Cron hook name.
     */
    private const CRON_HOOK = 'rattube_process_submission_event';

    /**
     * Registers worker hooks.
     *
     * @return void
     */
    public function register_hooks(): void {
        add_action( 'rattube_after_submission_prepared', array( $this, 'schedule_submission' ), 10, 2 );
        add_action( self::CRON_HOOK, array( $this, 'process_submission' ), 10, 1 );
    }

    /**
     * Schedules conversion processing for a submitted Rat Media item.
     *
     * @param int   $post_id Rat Media post ID.
     * @param array $payload Submission payload.
     *
     * @return void
     */
    public function schedule_submission( int $post_id, array $payload ): void {
        unset( $payload );

        if ( $post_id <= 0 || 'rat_media' !== get_post_type( $post_id ) ) {
            return;
        }

        if ( wp_next_scheduled( self::CRON_HOOK, array( $post_id ) ) ) {
            return;
        }

        update_post_meta( $post_id, '_rattube_queue_state', 'queued' );
        update_post_meta( $post_id, '_rattube_worker_message', '' );

        wp_schedule_single_event( time() + 5, self::CRON_HOOK, array( $post_id ) );

        if ( function_exists( 'spawn_cron' ) ) {
            spawn_cron();
        }
    }

    /**
     * Processes one Rat Media submission.
     *
     * @param int $post_id Rat Media post ID.
     *
     * @return void
     */
    public function process_submission( int $post_id ): void {
        if ( $post_id <= 0 || 'rat_media' !== get_post_type( $post_id ) ) {
            return;
        }

        $source_url = (string) get_post_meta( $post_id, '_rattube_source_url', true );
        $format     = (string) get_post_meta( $post_id, '_rattube_output_format', true );
        $basename   = (string) get_post_meta( $post_id, '_rattube_output_basename', true );
        $title      = (string) get_post_meta( $post_id, '_rattube_resolved_name', true );

        if ( empty( $source_url ) ) {
            $this->mark_failed( $post_id, __( 'Source URL is missing.', 'rattube' ) );
            return;
        }

        if ( 'mp3' !== $format ) {
            $this->mark_failed( $post_id, __( 'Only MP3 conversion is supported right now.', 'rattube' ) );
            return;
        }

        if ( empty( $basename ) ) {
            $basename = sanitize_file_name( $title );
            $basename = (string) pathinfo( $basename, PATHINFO_FILENAME );
        }

        if ( empty( $basename ) ) {
            $basename = 'rat-media-' . $post_id;
        }

        update_post_meta( $post_id, '_rattube_status', 'processing' );
        update_post_meta( $post_id, '_rattube_queue_state', 'processing' );
        update_post_meta( $post_id, '_rattube_worker_message', '' );

        $yt_dlp_binary = $this->detect_yt_dlp_binary();
        if ( empty( $yt_dlp_binary ) ) {
            $this->mark_failed( $post_id, __( 'yt-dlp was not found on the server.', 'rattube' ) );
            return;
        }

        $ffmpeg_location = $this->detect_ffmpeg_location();
        if ( empty( $ffmpeg_location ) ) {
            $this->mark_failed( $post_id, __( 'ffmpeg was not found on the server.', 'rattube' ) );
            return;
        }

        $uploads = wp_upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            $this->mark_failed( $post_id, sprintf( __( 'Upload path error: %s', 'rattube' ), (string) $uploads['error'] ) );
            return;
        }

        $temp_dir = trailingslashit( $uploads['basedir'] ) . 'rattube-temp/' . $post_id;
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            $this->mark_failed( $post_id, __( 'Could not create temporary conversion directory.', 'rattube' ) );
            return;
        }

        $output_template = $temp_dir . '/' . $basename . '.%(ext)s';
        $command_parts   = array(
            escapeshellarg( $yt_dlp_binary ),
            '--no-playlist',
            '--extract-audio',
            '--audio-format',
            'mp3',
            '--audio-quality',
            '0',
            '--ffmpeg-location',
            escapeshellarg( $ffmpeg_location ),
            '-o',
            escapeshellarg( $output_template ),
            escapeshellarg( $source_url ),
        );

        $command = implode( ' ', $command_parts );

        $result = $this->run_command( $command );
        if ( 0 !== $result['exit_code'] ) {
            $error = trim( $result['output'] );
            if ( '' === $error ) {
                $error = __( 'Unknown conversion error.', 'rattube' );
            }

            $this->cleanup_dir( $temp_dir );
            $this->mark_failed( $post_id, sprintf( __( 'MP3 conversion failed: %s', 'rattube' ), $error ) );
            return;
        }

        $files = glob( $temp_dir . '/*.mp3' );
        if ( ! is_array( $files ) || empty( $files ) ) {
            $this->cleanup_dir( $temp_dir );
            $this->mark_failed( $post_id, __( 'MP3 output file was not created.', 'rattube' ) );
            return;
        }

        $source_file = (string) array_pop( $files );
        $output_dir  = trailingslashit( $uploads['basedir'] ) . 'rattube-outputs';

        if ( ! wp_mkdir_p( $output_dir ) ) {
            $this->cleanup_dir( $temp_dir );
            $this->mark_failed( $post_id, __( 'Could not create output directory.', 'rattube' ) );
            return;
        }

        $final_filename = wp_unique_filename( $output_dir, $basename . '.mp3' );
        $final_path     = $output_dir . '/' . $final_filename;

        if ( ! rename( $source_file, $final_path ) ) {
            $this->cleanup_dir( $temp_dir );
            $this->mark_failed( $post_id, __( 'Could not move MP3 file into uploads.', 'rattube' ) );
            return;
        }

        $attachment_id = $this->create_attachment( $post_id, $final_path, $title );
        $this->cleanup_dir( $temp_dir );

        if ( $attachment_id <= 0 ) {
            $this->mark_failed( $post_id, __( 'MP3 file created but failed to attach in WordPress.', 'rattube' ) );
            return;
        }

        update_post_meta( $post_id, '_rattube_file_attachment_id', $attachment_id );
        update_post_meta( $post_id, '_rattube_status', 'completed' );
        update_post_meta( $post_id, '_rattube_queue_state', 'done' );
        update_post_meta( $post_id, '_rattube_worker_message', '' );
    }

    /**
     * Detects available yt-dlp command.
     *
     * @return string
     */
    private function detect_yt_dlp_binary(): string {
        $local_path = rattube_get_local_tool_path( 'yt-dlp' );
        if ( '' !== $local_path && is_file( $local_path ) && is_executable( $local_path ) ) {
            return $local_path;
        }

        foreach ( array( 'yt-dlp', 'youtube-dl' ) as $candidate ) {
            $check = $this->run_command( 'command -v ' . escapeshellarg( $candidate ) );
            if ( 0 === $check['exit_code'] && '' !== trim( $check['output'] ) ) {
                return trim( $check['output'] );
            }
        }

        return '';
    }

    /**
     * Detects ffmpeg location path.
     *
     * @return string
     */
    private function detect_ffmpeg_location(): string {
        $local_path = rattube_get_local_tool_path( 'ffmpeg' );
        if ( '' !== $local_path && is_file( $local_path ) && is_executable( $local_path ) ) {
            return dirname( $local_path );
        }

        $check = $this->run_command( 'command -v ffmpeg' );
        if ( 0 === $check['exit_code'] && '' !== trim( $check['output'] ) ) {
            return dirname( trim( $check['output'] ) );
        }

        return '';
    }

    /**
     * Runs a shell command and returns output and exit code.
     *
     * @param string $command Shell command.
     *
     * @return array{exit_code:int,output:string}
     */
    private function run_command( string $command ): array {
        if ( ! function_exists( 'proc_open' ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'proc_open is unavailable in this PHP environment.', 'rattube' ),
            );
        }

        $disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
        if ( in_array( 'proc_open', $disabled, true ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'proc_open is disabled by PHP configuration.', 'rattube' ),
            );
        }

        $descriptor_spec = array(
            0 => array( 'pipe', 'r' ),
            1 => array( 'pipe', 'w' ),
            2 => array( 'pipe', 'w' ),
        );

        $process = proc_open( array( '/bin/sh', '-c', $command ), $descriptor_spec, $pipes );
        if ( ! is_resource( $process ) ) {
            return array(
                'exit_code' => 127,
                'output'    => __( 'Could not start conversion process.', 'rattube' ),
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
     * Creates an attachment for the generated MP3 file.
     *
     * @param int    $post_id    Rat Media post ID.
     * @param string $file_path  Absolute file path.
     * @param string $post_title Desired title.
     *
     * @return int
     */
    private function create_attachment( int $post_id, string $file_path, string $post_title ): int {
        $filetype = wp_check_filetype( basename( $file_path ), null );

        $attachment = array(
            'post_mime_type' => $filetype['type'] ?: 'audio/mpeg',
            'post_title'     => '' !== $post_title ? sanitize_text_field( $post_title ) : sanitize_text_field( (string) pathinfo( $file_path, PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );

        $attachment_id = wp_insert_attachment( $attachment, $file_path, $post_id, true );
        if ( is_wp_error( $attachment_id ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
        if ( is_array( $metadata ) ) {
            wp_update_attachment_metadata( $attachment_id, $metadata );
        }

        return (int) $attachment_id;
    }

    /**
     * Marks a conversion as failed and stores error details.
     *
     * @param int    $post_id  Rat Media post ID.
     * @param string $message  Error message.
     *
     * @return void
     */
    private function mark_failed( int $post_id, string $message ): void {
        update_post_meta( $post_id, '_rattube_status', 'failed' );
        update_post_meta( $post_id, '_rattube_queue_state', 'failed' );
        update_post_meta( $post_id, '_rattube_worker_message', sanitize_text_field( $message ) );

        rattube_add_admin_log(
            sprintf(
                /* translators: 1: post ID, 2: error message. */
                __( 'RatTube conversion failed for post %1$d: %2$s', 'rattube' ),
                $post_id,
                $message
            ),
            'error'
        );
    }

    /**
     * Cleans temporary conversion directory.
     *
     * @param string $dir Directory path.
     *
     * @return void
     */
    private function cleanup_dir( string $dir ): void {
        if ( ! is_dir( $dir ) ) {
            return;
        }

        $files = glob( trailingslashit( $dir ) . '*' );
        if ( is_array( $files ) ) {
            foreach ( $files as $file ) {
                if ( is_file( $file ) ) {
                    wp_delete_file( $file );
                }
            }
        }

        @rmdir( $dir );
    }
}
