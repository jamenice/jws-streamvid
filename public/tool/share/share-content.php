<?php
/**
 * The inside of the share popup.
 *
 * Fetched over AJAX rather than printed with the page — see share.php for the
 * shell and why. On that request nothing has set up a post, so the id arrives in
 * $args and every value below is read from it.
 *
 * Every string and every row here comes from Theme Options → Media → Tool, so a
 * site can retitle the popup, drop the link or the embed box, resize the embed
 * iframe and pick which networks to show. Anything never saved reads back as an
 * empty string, so each option keeps the shipped default until it is set — an
 * existing site sees no change until someone edits it.
 *
 * @var int $share_id
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/** Saved value, or the shipped default when the option has never been saved. */
$share_text = function ( $key, $fallback ) {
	$value = jws_streamvid_options( $key );

	return ( '' === $value || null === $value ) ? $fallback : $value;
};

/** Same, for switches: never saved means on, an explicit 0 means off. */
$share_on = function ( $key ) {
	$value = jws_streamvid_options( $key );

	return ( '' === $value || null === $value ) ? true : (bool) $value;
};

$embed_width  = (int) $share_text( 'share_embed_width', 560 );
$embed_height = (int) $share_text( 'share_embed_height', 315 );

$share_id      = isset( $args['share_id'] ) ? (int) $args['share_id'] : get_queried_object_id();

if ( ! $share_id ) {
	return;
}

$share_thumb   = get_the_post_thumbnail_url( $share_id, 'medium' );
$share_excerpt = wp_trim_words( get_the_excerpt( $share_id ), 25 );
$share_link    = get_the_permalink( $share_id );
?>
<div class="form-head">

    <h5 class="title">
        <?php echo esc_html( $share_text( 'share_popup_title', __( 'Share', 'jws_streamvid' ) ) ); ?>
    </h5>

</div>
<?php if ( $share_on( 'share_show_preview' ) ) : ?>
<div class="share-preview">
    <?php if ( $share_thumb ) : ?>
        <div class="share-preview-media">
            <img src="<?php echo esc_url( $share_thumb ); ?>" alt="" loading="lazy" />
        </div>
    <?php endif; ?>
    <div class="share-preview-content">
        <h6 class="share-preview-title"><?php echo esc_html( get_the_title( $share_id ) ); ?></h6>
        <?php if ( $share_excerpt ) : ?>
            <p class="share-preview-excerpt fs-small"><?php echo esc_html( $share_excerpt ); ?></p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<?php if ( $share_on( 'share_show_link' ) ) : ?>
<p>
<label><?php echo esc_html( $share_text( 'share_link_label', __( 'Link', 'jws_streamvid' ) ) ); ?></label>
<input type="text" value="<?php echo esc_url( wp_get_shortlink( $share_id ) ); ?>" />
</p>
<?php endif; ?>
<?php if ( $share_on( 'share_show_embed' ) ) : ?>
<p>
<label><?php echo esc_html( $share_text( 'share_embed_label', __( 'Embed', 'jws_streamvid' ) ) ); ?></label>
<textarea>
<iframe width="<?php echo esc_attr( $embed_width ); ?>" height="<?php echo esc_attr( $embed_height ); ?>"  src="<?php echo esc_url( $share_link . 'embed' ); ?>" frameborder="0" allowfullscreen></iframe>
</textarea>
</p>
<?php endif; ?>
<?php

jws_share_buttons( $share_id );

?>
