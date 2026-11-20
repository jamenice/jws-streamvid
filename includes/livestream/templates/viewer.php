<?php
/**
 * The viewer: player on the left, chat on the right.
 *
 * livekit.js: #join-livestream, #remote-video-container, .video-viewer-holder,
 * .view-count span, #chat-participants-list. chat.js: #chat-messages,
 * #chat-message, #send-message, #send-emoji, #jws-hide-chat.
 *
 * @var int $post_id
 * @var int $author_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$host     = Jws_Livestream::user_profile( $author_id );
$messages = Jws_Livestream_Chat::get_messages( $post_id, 20 );
$profiles = array();
$poster   = get_the_post_thumbnail_url( $post_id, 'full' );
$cats     = function_exists( 'jws_get_cat_list' ) ? jws_get_cat_list( 'videos_cat', ' ', $post_id ) : '';
?>
<div class="livestream-container" id="live">
	<div class="row">

		<div class="col-xl-9 col-12 player-content">

			<div class="live-player">

				<div class="live-join">
					<span class="live-join__badge"><span class="live-join__dot"></span><?php esc_html_e( 'Live', 'jws_streamvid' ); ?></span>
					<h5 class="live-join__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h5>
					<button id="join-livestream" class="live-join__btn" data-id="<?php echo esc_attr( $post_id ); ?>" type="button">
						<span class="live-join__icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5.14v13.72a1 1 0 0 0 1.5.86l11.04-6.86a1 1 0 0 0 0-1.72L9.5 4.28A1 1 0 0 0 8 5.14z"/></svg></span>
						<span><?php esc_html_e( 'Watch live', 'jws_streamvid' ); ?></span>
					</button>
					<p class="live-join__hint" role="status"><?php esc_html_e( 'Join to watch and chat with the host.', 'jws_streamvid' ); ?></p>
				</div>

				<div id="remote-video-container">
					<?php // vjs-fluid: live.css sizes the poster by it (16:9, 80vh on phones). ?>
					<div class="video-viewer-holder vjs-fluid"<?php if ( $poster ) : ?> style="background-image: url('<?php echo esc_url( $poster ); ?>');"<?php endif; ?>>
						<div class="vjs-loading-spinner" style="display: none;"></div>
					</div>
				</div>

				<div class="live-video-content-top">
					<div class="live-content">
						<div class="live-meta">
							<div class="author-avatar">
								<img src="<?php echo esc_url( $host['user_avatar'] ); ?>" alt="" width="90" height="90" />
							</div>
							<div class="author-info">
								<h5 class="author-name"><?php echo esc_html( $host['user_name'] ); ?></h5>
								<h6 class="title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h6>
							</div>
						</div>
					</div>
					<div class="live-view">
						<span class="live"><?php esc_html_e( 'Live', 'jws_streamvid' ); ?></span>
						<div class="view-count">
							<i class="jws-icon-eye"></i>
							<span>0</span>
						</div>
					</div>
				</div>

			</div>

			<div class="live-content-wrapper">
				<div class="live-content desktop">
					<div class="live-meta">
						<a class="author-avatar" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>">
							<img src="<?php echo esc_url( $host['user_avatar'] ); ?>" alt="" width="90" height="90" />
							<span class="live"><?php esc_html_e( 'Live', 'jws_streamvid' ); ?></span>
						</a>
						<div class="author-info">
							<h4 class="title live-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h4>
							<div class="live-host">
								<a class="author-name" href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>"><?php echo esc_html( $host['user_name'] ); ?></a>
							</div>
							<?php if ( $cats ) : ?>
								<div class="video-cat"><?php echo wp_kses_post( $cats ); ?></div>
							<?php endif; ?>
						</div>
					</div>
					<div class="live-action">
						<div class="action-top">
							<div class="view-count live-watching" title="<?php esc_attr_e( 'Watching now', 'jws_streamvid' ); ?>">
								<i class="jws-icon-eye"></i>
								<span>0</span>
								<?php esc_html_e( 'watching', 'jws_streamvid' ); ?>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		<div class="col-xl-3 col-12 chat-sidebar">
			<div class="chat-sidebar-inner jws_sticky_move">

				<div class="chat-locked" role="status">
					<i class="jws-icon-lock-key-fill"></i>
					<span><?php esc_html_e( 'Join the live stream to chat', 'jws_streamvid' ); ?></span>
				</div>

				<div class="live-chat">

					<div class="chat-header" role="tablist">
						<button type="button" class="active" data-tab="chat"><?php esc_html_e( 'Chat', 'jws_streamvid' ); ?></button>
						<button type="button" data-tab="participants"><?php esc_html_e( 'Participants', 'jws_streamvid' ); ?></button>
					</div>

					<div class="chat-content">

						<div class="chat-empty">
							<i class="jws-icon-chat-dots"></i>
							<strong><?php esc_html_e( 'No messages yet', 'jws_streamvid' ); ?></strong>
							<small><?php esc_html_e( 'Say hi and start the conversation.', 'jws_streamvid' ); ?></small>
						</div>

						<button type="button" class="chat-hidden-note">
							<i class="jws-icon-eye"></i>
							<span><?php esc_html_e( 'Chat is hidden', 'jws_streamvid' ); ?></span>
							<small><?php esc_html_e( 'Click to show it again', 'jws_streamvid' ); ?></small>
						</button>

						<div id="chat-messages" class="chat-messages">
							<?php
							foreach ( $messages as $row ) :
								$sender = (int) $row->user_id;

								// One lookup per sender, not per message.
								if ( ! isset( $profiles[ $sender ] ) ) {
									$profiles[ $sender ] = Jws_Livestream::user_profile( $sender );
								}
								?>
								<div<?php if ( $sender && $sender === $author_id ) echo ' class="host-message"'; ?>>
									<img src="<?php echo esc_url( $profiles[ $sender ]['user_avatar'] ); ?>" alt="" />
									<div>
										<strong><?php echo esc_html( $profiles[ $sender ]['user_name'] ); ?>:</strong>
										<?php echo esc_html( $row->message ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<div id="chat-actions">

							<div class="chat-input">
								<input type="text" id="chat-message" placeholder="<?php esc_attr_e( 'Say something', 'jws_streamvid' ); ?>" maxlength="<?php echo esc_attr( Jws_Livestream_Chat::MAX_LENGTH ); ?>" autocomplete="off" />
								<div id="send-emoji" class="button"></div>
							</div>

							<div class="chat-controls">

								<div class="controls-left">
									<div class="jws-chat-setting">
										<h6><?php esc_html_e( 'Chat settings', 'jws_streamvid' ); ?></h6>
										<button type="button" id="jws-hide-chat" class="hide-chat" aria-pressed="false">
											<i class="jws-icon-eye"></i>
											<span class="hide-chat__hide"><?php esc_html_e( 'Hide chat', 'jws_streamvid' ); ?></span>
											<span class="hide-chat__show"><?php esc_html_e( 'Show chat', 'jws_streamvid' ); ?></span>
										</button>
									</div>
									<button class="settings-btn reset-button" type="button" title="<?php esc_attr_e( 'Chat settings', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Chat settings', 'jws_streamvid' ); ?>">
										<i class="jws-icon-gear"></i>
									</button>
								</div>

								<div>
									<button id="send-message" type="button" title="<?php esc_attr_e( 'Send', 'jws_streamvid' ); ?>" aria-label="<?php esc_attr_e( 'Send message', 'jws_streamvid' ); ?>">
										<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
									</button>
								</div>

							</div>
						</div>

					</div>

					<div class="chat-participants">
						<div id="chat-participants-list">
							<ul class="reset_ul_ol"></ul>
						</div>
					</div>

				</div>
			</div>
		</div>

	</div>
</div>
