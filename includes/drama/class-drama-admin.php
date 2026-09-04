<?php

/**
 * Admin screens for short drama.
 *
 * Three things editors actually need:
 *   - to see at a glance how many episodes a drama has and what it costs,
 *   - to create eighty numbered episodes without eighty trips through
 *     "Add New",
 *   - to look at and adjust a viewer's coin balance.
 *
 * @package    Jws_Streamvid
 * @subpackage Jws_Streamvid/includes/drama
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Jws_Drama_Admin {

	const PAGE_EPISODES = 'jws-drama-episodes';

	/**
	 * The Taxonomies tab in class-drama-fields.php replaces these, the same
	 * way the theme hides them for movies/tv_shows in template-tags.php.
	 */
	public function remove_taxonomy_metaboxes() {

		$drama = Jws_Drama_Post_Types::DRAMA;

		remove_meta_box( 'genresdiv', $drama, 'side' );
		remove_meta_box( 'countriesdiv', $drama, 'side' );
		remove_meta_box( 'agesdiv', $drama, 'side' );
		remove_meta_box( 'tagsdiv-' . Jws_Drama_Post_Types::TAX_TAG, $drama, 'side' );
	}

	/* ---------------------------------------------------------------------- */
	/* List tables                                                             */
	/* ---------------------------------------------------------------------- */

	public function drama_columns( $columns ) {

		$new = array();

		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['drama_episodes'] = esc_html__( 'Episodes', 'jws_streamvid' );
				$new['drama_unlock']   = esc_html__( 'Free / Coins', 'jws_streamvid' );
				$new['drama_status']   = esc_html__( 'Status', 'jws_streamvid' );
				$new['drama_tags']     = esc_html__( 'Tag', 'jws_streamvid' );
			}
		}

		return $new;
	}

	public function drama_column( $column, $post_id ) {

		switch ( $column ) {

			case 'drama_episodes':
				$published = Jws_Drama_Post_Types::episode_count( $post_id, true );
				$all       = Jws_Drama_Post_Types::episode_count( $post_id, false );
				$manage    = add_query_arg(
					array( 'post_type' => Jws_Drama_Post_Types::DRAMA, 'page' => self::PAGE_EPISODES, 'drama_id' => $post_id ),
					admin_url( 'edit.php' )
				);

				printf(
					'<a href="%s">%d</a>%s',
					esc_url( $manage ),
					(int) $published,
					$all > $published ? ' <span style="color:#996800">(+' . (int) ( $all - $published ) . ' draft)</span>' : ''
				);
				break;

			case 'drama_unlock':
				printf(
					/* translators: 1: free episode count, 2: coin price */
					esc_html__( '%1$d free, then %2$d coins', 'jws_streamvid' ),
					(int) Jws_Drama_Wallet::free_episodes( $post_id ),
					(int) Jws_Drama_Wallet::coin_price( $post_id )
				);
				break;

			case 'drama_status':
				$status = get_post_meta( $post_id, 'drama_status', true );
				echo esc_html( 'completed' === $status ? esc_html__( 'Completed', 'jws_streamvid' ) : esc_html__( 'Ongoing', 'jws_streamvid' ) );
				break;

			case 'drama_tags':
				$terms = get_the_terms( $post_id, 'drama_tag' );

				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					echo '—';
					break;
				}

				$links = array();

				foreach ( $terms as $term ) {

					$url = add_query_arg(
						array( 'post_type' => Jws_Drama_Post_Types::DRAMA, 'drama_tag' => $term->slug ),
						admin_url( 'edit.php' )
					);

					$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $term->name ) . '</a>';
				}

				echo wp_kses_post( implode( ', ', $links ) );
				break;
		}
	}

	public function episode_columns( $columns ) {

		$new = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['drama_ep_number'] = esc_html__( '#', 'jws_streamvid' );
			}

			$new[ $key ] = $label;

			if ( 'title' === $key ) {
				$new['drama_ep_parent'] = esc_html__( 'Drama', 'jws_streamvid' );
				$new['drama_ep_source'] = esc_html__( 'Source', 'jws_streamvid' );
				$new['drama_ep_access'] = esc_html__( 'Access', 'jws_streamvid' );
			}
		}

		return $new;
	}

	public function episode_column( $column, $post_id ) {

		switch ( $column ) {

			case 'drama_ep_number':
				echo (int) Jws_Drama_Wallet::episode_number( $post_id );
				break;

			case 'drama_ep_parent':
				$drama_id = Jws_Drama_Wallet::drama_id_of( $post_id );

				if ( $drama_id ) {
					printf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $drama_id ) ), esc_html( get_the_title( $drama_id ) ) );
				} else {
					echo '<span style="color:#b32d2e">' . esc_html__( 'Not linked', 'jws_streamvid' ) . '</span>';
				}
				break;

			case 'drama_ep_source':
				$type = get_post_meta( $post_id, 'videos_type', true );
				echo esc_html( $type ? $type : '—' );
				break;

			case 'drama_ep_access':
				$drama_id = Jws_Drama_Wallet::drama_id_of( $post_id );

				if ( ! $drama_id ) {
					echo '—';
					break;
				}

				if ( get_post_meta( $post_id, 'drama_ep_free', true ) ) {
					echo '<span style="color:#008a20">' . esc_html__( 'Free (forced)', 'jws_streamvid' ) . '</span>';
				} elseif ( Jws_Drama_Wallet::episode_number( $post_id ) <= Jws_Drama_Wallet::free_episodes( $drama_id ) ) {
					echo '<span style="color:#008a20">' . esc_html__( 'Free', 'jws_streamvid' ) . '</span>';
				} else {
					printf(
						/* translators: %d: coin price */
						esc_html__( '%d coins', 'jws_streamvid' ),
						(int) Jws_Drama_Wallet::coin_price( $drama_id )
					);
				}
				break;
		}
	}

	/** Lets the episode list be sorted by running order. */
	public function episode_sortable_columns( $columns ) {
		$columns['drama_ep_number'] = 'menu_order';
		return $columns;
	}

	/**
	 * The episode list is only ever useful in series order, and only for one
	 * drama at a time. Default to that instead of newest-first.
	 */
	public function episode_default_order( $query ) {

		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( Jws_Drama_Post_Types::EPISODE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}

		$drama_id = isset( $_GET['drama_id'] ) ? absint( $_GET['drama_id'] ) : 0;

		if ( $drama_id ) {
			$query->set(
				'meta_query',
				array(
					array( 'key' => 'drama_id', 'value' => $drama_id, 'compare' => '=', 'type' => 'NUMERIC' ),
				)
			);
		}
	}

	/** Drama filter above the episode list. */
	public function episode_filter_dropdown( $post_type ) {

		if ( Jws_Drama_Post_Types::EPISODE !== $post_type ) {
			return;
		}

		$selected = isset( $_GET['drama_id'] ) ? absint( $_GET['drama_id'] ) : 0;
		$dramas   = get_posts(
			array(
				'post_type'      => Jws_Drama_Post_Types::DRAMA,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
			)
		);

		echo '<select name="drama_id"><option value="">' . esc_html__( 'All drama', 'jws_streamvid' ) . '</option>';

		foreach ( $dramas as $drama ) {
			printf(
				'<option value="%d"%s>%s</option>',
				(int) $drama->ID,
				selected( $selected, $drama->ID, false ),
				esc_html( $drama->post_title )
			);
		}

		echo '</select>';
	}

	/* ---------------------------------------------------------------------- */
	/* Bulk episode creation                                                   */
	/* ---------------------------------------------------------------------- */

	public function register_pages() {

		add_submenu_page(
			'edit.php?post_type=' . Jws_Drama_Post_Types::DRAMA,
			esc_html__( 'Manage Episodes', 'jws_streamvid' ),
			esc_html__( 'Manage Episodes', 'jws_streamvid' ),
			'edit_posts',
			self::PAGE_EPISODES,
			array( $this, 'render_episodes_page' )
		);
	}

	public function render_episodes_page() {

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$drama_id = isset( $_GET['drama_id'] ) ? absint( $_GET['drama_id'] ) : 0;
		$notice   = $this->handle_bulk_create( $drama_id );
		$dramas   = get_posts(
			array(
				'post_type'      => Jws_Drama_Post_Types::DRAMA,
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => array( 'publish', 'draft', 'pending' ),
			)
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Manage Episodes', 'jws_streamvid' ); ?></h1>

			<?php if ( $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<form method="get" style="margin:16px 0">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( Jws_Drama_Post_Types::DRAMA ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_EPISODES ); ?>" />
				<label for="drama_id"><strong><?php echo esc_html__( 'Drama', 'jws_streamvid' ); ?></strong></label>
				<select name="drama_id" id="drama_id">
					<option value=""><?php echo esc_html__( '— select —', 'jws_streamvid' ); ?></option>
					<?php foreach ( $dramas as $drama ) : ?>
						<option value="<?php echo (int) $drama->ID; ?>" <?php selected( $drama_id, $drama->ID ); ?>><?php echo esc_html( $drama->post_title ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( esc_html__( 'Show', 'jws_streamvid' ), 'secondary', '', false ); ?>
			</form>

			<?php
			if ( ! $drama_id ) {
				echo '<p>' . esc_html__( 'Pick a drama to list its episodes and add more.', 'jws_streamvid' ) . '</p></div>';
				return;
			}

			$episodes = Jws_Drama_Post_Types::episodes_of( $drama_id, 'all' );
			$highest  = 0;

			foreach ( $episodes as $episode ) {
				$highest = max( $highest, Jws_Drama_Wallet::episode_number( $episode->ID ) );
			}
			?>

			<h2><?php echo esc_html( get_the_title( $drama_id ) ); ?></h2>

			<div class="card" style="max-width:none;padding:12px 16px">
				<h3 style="margin-top:0"><?php echo esc_html__( 'Add episodes in bulk', 'jws_streamvid' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'jws_drama_bulk_create', 'jws_drama_nonce' ); ?>
					<input type="hidden" name="drama_id" value="<?php echo (int) $drama_id; ?>" />
					<label><?php echo esc_html__( 'From', 'jws_streamvid' ); ?>
						<input type="number" name="from" min="1" value="<?php echo (int) ( $highest + 1 ); ?>" style="width:90px" required />
					</label>
					<label style="margin-inline-start:12px"><?php echo esc_html__( 'To', 'jws_streamvid' ); ?>
						<input type="number" name="to" min="1" value="<?php echo (int) ( $highest + 10 ); ?>" style="width:90px" required />
					</label>
					<label style="margin-inline-start:12px"><?php echo esc_html__( 'Title pattern', 'jws_streamvid' ); ?>
						<input type="text" name="pattern" value="<?php echo esc_attr__( 'Episode %d', 'jws_streamvid' ); ?>" style="width:200px" />
					</label>
					<label style="margin-inline-start:12px">
						<input type="checkbox" name="publish" value="1" />
						<?php echo esc_html__( 'Publish immediately', 'jws_streamvid' ); ?>
					</label>
					<?php submit_button( esc_html__( 'Create', 'jws_streamvid' ), 'primary', 'jws_drama_create', false ); ?>
					<p class="description">
						<?php echo esc_html__( 'Creates numbered episodes linked to this drama. Numbers that already exist are skipped, so you can run it again after adding more.', 'jws_streamvid' ); ?>
					</p>
				</form>
			</div>

			<table class="wp-list-table widefat fixed striped" style="margin-top:16px">
				<thead>
					<tr>
						<th style="width:60px"><?php echo esc_html__( '#', 'jws_streamvid' ); ?></th>
						<th><?php echo esc_html__( 'Title', 'jws_streamvid' ); ?></th>
						<th style="width:120px"><?php echo esc_html__( 'Status', 'jws_streamvid' ); ?></th>
						<th style="width:140px"><?php echo esc_html__( 'Video', 'jws_streamvid' ); ?></th>
						<th style="width:140px"><?php echo esc_html__( 'Access', 'jws_streamvid' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $episodes ) ) : ?>
					<tr><td colspan="5"><?php echo esc_html__( 'No episodes yet.', 'jws_streamvid' ); ?></td></tr>
				<?php else : ?>
					<?php
					$free_count = Jws_Drama_Wallet::free_episodes( $drama_id );
					$price      = Jws_Drama_Wallet::coin_price( $drama_id );

					foreach ( $episodes as $episode ) :
						$number = Jws_Drama_Wallet::episode_number( $episode->ID );
						$type   = get_post_meta( $episode->ID, 'videos_type', true );
						$has_src = 'file' === $type
							? (bool) get_post_meta( $episode->ID, 'videos_file', true )
							: ( 'url' === $type ? (bool) get_post_meta( $episode->ID, 'videos_url', true ) : (bool) $type );
						?>
						<tr>
							<td><?php echo (int) $number; ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $episode->ID ) ); ?>"><?php echo esc_html( $episode->post_title ); ?></a></td>
							<td><?php echo esc_html( $episode->post_status ); ?></td>
							<td>
								<?php if ( $has_src ) : ?>
									<span style="color:#008a20"><?php echo esc_html( $type ); ?></span>
								<?php else : ?>
									<span style="color:#b32d2e"><?php echo esc_html__( 'missing', 'jws_streamvid' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								if ( get_post_meta( $episode->ID, 'drama_ep_free', true ) || $number <= $free_count ) {
									echo esc_html__( 'Free', 'jws_streamvid' );
								} else {
									/* translators: %d: coin price */
									printf( esc_html__( '%d coins', 'jws_streamvid' ), (int) $price );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * @return array|null { type, message }
	 */
	private function handle_bulk_create( $drama_id ) {

		if ( empty( $_POST['jws_drama_create'] ) ) {
			return null;
		}

		if ( ! isset( $_POST['jws_drama_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_drama_nonce'] ) ), 'jws_drama_bulk_create' ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Security check failed.', 'jws_streamvid' ) );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'You are not allowed to do this.', 'jws_streamvid' ) );
		}

		$drama_id = absint( $_POST['drama_id'] );
		$from     = max( 1, absint( $_POST['from'] ) );
		$to       = max( 1, absint( $_POST['to'] ) );
		$pattern  = isset( $_POST['pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['pattern'] ) ) : 'Episode %d';
		$publish  = ! empty( $_POST['publish'] );

		if ( ! $drama_id || get_post_type( $drama_id ) !== Jws_Drama_Post_Types::DRAMA ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'Pick a drama first.', 'jws_streamvid' ) );
		}

		if ( $to < $from ) {
			return array( 'type' => 'error', 'message' => esc_html__( '"To" must not be smaller than "From".', 'jws_streamvid' ) );
		}

		/* A runaway range would create thousands of posts in one request. */
		if ( ( $to - $from ) >= 200 ) {
			return array( 'type' => 'error', 'message' => esc_html__( 'That is more than 200 episodes at once. Do it in smaller batches.', 'jws_streamvid' ) );
		}

		if ( false === strpos( $pattern, '%d' ) ) {
			$pattern .= ' %d';
		}

		$taken = array();

		foreach ( Jws_Drama_Post_Types::episodes_of( $drama_id ) as $existing_id ) {
			$taken[ Jws_Drama_Wallet::episode_number( $existing_id ) ] = true;
		}

		$created = 0;
		$skipped = 0;

		for ( $number = $from; $number <= $to; $number++ ) {

			if ( isset( $taken[ $number ] ) ) {
				$skipped++;
				continue;
			}

			$episode_id = wp_insert_post(
				array(
					'post_type'   => Jws_Drama_Post_Types::EPISODE,
					'post_title'  => sprintf( $pattern, $number ),
					'post_status' => $publish ? 'publish' : 'draft',
					'menu_order'  => $number,
				),
				true
			);

			if ( is_wp_error( $episode_id ) ) {
				continue;
			}

			update_post_meta( $episode_id, 'drama_id', $drama_id );
			update_post_meta( $episode_id, 'drama_ep_number', $number );

			/* ACF stores a key reference alongside the value; without it the
			   field renders empty on the edit screen even though the meta is
			   there. */
			update_post_meta( $episode_id, '_drama_id', 'field_drama_ep_drama_id' );
			update_post_meta( $episode_id, '_drama_ep_number', 'field_drama_ep_number' );

			$created++;
		}

		return array(
			'type'    => $created ? 'success' : 'warning',
			'message' => sprintf(
				/* translators: 1: created count, 2: skipped count */
				esc_html__( 'Created %1$d episodes, skipped %2$d that already existed.', 'jws_streamvid' ),
				$created,
				$skipped
			),
		);
	}

	/* ---------------------------------------------------------------------- */
	/* Coin wallet on the user screens                                         */
	/* ---------------------------------------------------------------------- */

	public function user_columns( $columns ) {
		$columns['drama_coins'] = esc_html__( 'Coins', 'jws_streamvid' );
		return $columns;
	}

	public function user_column( $value, $column, $user_id ) {

		if ( 'drama_coins' === $column ) {
			return (int) Jws_Drama_Wallet::balance( $user_id );
		}

		return $value;
	}

	public function user_profile_fields( $user ) {

		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$balance = Jws_Drama_Wallet::balance( $user->ID );
		$history = Jws_Drama_Wallet::history( $user->ID, 10 );
		?>
		<h2><?php echo esc_html__( 'Drama Coins', 'jws_streamvid' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php echo esc_html__( 'Balance', 'jws_streamvid' ); ?></th>
				<td>
					<strong style="font-size:16px"><?php echo (int) $balance; ?></strong>
					<?php wp_nonce_field( 'jws_drama_adjust_coins', 'jws_drama_coins_nonce' ); ?>
					<p style="margin-top:10px">
						<select name="jws_drama_coin_action">
							<option value="add"><?php echo esc_html__( '+ Add coins', 'jws_streamvid' ); ?></option>
							<option value="subtract"><?php echo esc_html__( '− Deduct coins', 'jws_streamvid' ); ?></option>
						</select>
						<input type="number" name="jws_drama_coin_amount" min="1" value="" style="width:110px" placeholder="0" />
						<input type="text" name="jws_drama_coin_note" value="" class="regular-text" placeholder="<?php echo esc_attr__( 'Reason (optional)', 'jws_streamvid' ); ?>" />
					</p>
					<p class="description">
						<?php echo esc_html__( 'Saving the profile applies it and writes a wallet entry.', 'jws_streamvid' ); ?>
					</p>
				</td>
			</tr>
			<?php if ( $history ) : ?>
			<tr>
				<th><?php echo esc_html__( 'Recent activity', 'jws_streamvid' ); ?></th>
				<td>
					<table class="widefat striped" style="max-width:620px">
						<thead><tr>
							<th><?php echo esc_html__( 'When', 'jws_streamvid' ); ?></th>
							<th><?php echo esc_html__( 'Change', 'jws_streamvid' ); ?></th>
							<th><?php echo esc_html__( 'Balance', 'jws_streamvid' ); ?></th>
							<th><?php echo esc_html__( 'Type', 'jws_streamvid' ); ?></th>
							<th><?php echo esc_html__( 'Note', 'jws_streamvid' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $history as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->created_at ); ?></td>
								<td style="color:<?php echo $row->delta < 0 ? '#b32d2e' : '#008a20'; ?>">
									<?php echo esc_html( ( $row->delta > 0 ? '+' : '' ) . (int) $row->delta ); ?>
								</td>
								<td><?php echo (int) $row->balance_after; ?></td>
								<td><?php echo esc_html( $row->type ); ?></td>
								<td><?php echo esc_html( $row->note ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	public function save_user_profile_fields( $user_id ) {

		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['jws_drama_coins_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jws_drama_coins_nonce'] ) ), 'jws_drama_adjust_coins' ) ) {
			return;
		}

		$amount = isset( $_POST['jws_drama_coin_amount'] ) ? max( 0, (int) $_POST['jws_drama_coin_amount'] ) : 0;

		if ( 0 === $amount ) {
			return;
		}

		$subtract = isset( $_POST['jws_drama_coin_action'] ) && 'subtract' === $_POST['jws_drama_coin_action'];
		$delta    = $subtract ? -$amount : $amount;

		$note = isset( $_POST['jws_drama_coin_note'] ) ? sanitize_text_field( wp_unslash( $_POST['jws_drama_coin_note'] ) ) : '';
		$note = $note ? $note : sprintf( 'Adjusted by %s', wp_get_current_user()->user_login );

		if ( $delta > 0 ) {
			Jws_Drama_Wallet::credit( $user_id, $delta, 'admin', get_current_user_id(), $note );
		} else {
			Jws_Drama_Wallet::debit( $user_id, abs( $delta ), 'admin', get_current_user_id(), $note );
		}
	}
}
