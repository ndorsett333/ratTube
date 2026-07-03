<?php
/**
 * Frontend converter shortcode template.
 *
 * @package RatTube
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="rattube-converter" aria-labelledby="rattube-converter-title">
    <h2 id="rattube-converter-title"><?php esc_html_e( 'RatTube Converter', 'rattube' ); ?></h2>
    <p><?php esc_html_e( 'Submit a media URL for future processing. Conversion is not enabled in this foundation release.', 'rattube' ); ?></p>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="rattube-notice rattube-notice--<?php echo esc_attr( $notice['type'] ); ?>" role="status">
            <?php echo esc_html( $notice['message'] ); ?>
        </div>
    <?php endif; ?>

    <form class="rattube-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="rattube_submit_converter" />
        <?php wp_nonce_field( 'rattube_submit_converter', 'rattube_nonce' ); ?>

        <p>
            <label for="rattube_source_url"><?php esc_html_e( 'Source URL', 'rattube' ); ?></label>
            <input id="rattube_source_url" name="rattube_source_url" type="url" required="required" maxlength="2048" placeholder="https://example.com/media" />
        </p>

        <p>
            <label for="rattube_output_format"><?php esc_html_e( 'Output Format', 'rattube' ); ?></label>
            <select id="rattube_output_format" name="rattube_output_format" required="required">
                <option value=""><?php esc_html_e( 'Select a format', 'rattube' ); ?></option>
                <?php foreach ( $formats as $key => $label ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <button type="submit"><?php esc_html_e( 'Submit', 'rattube' ); ?></button>
        </p>
    </form>
</section>
