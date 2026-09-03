<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit( 'Direct script access denied.' );
}

/**
 * JWS StreamVid - YouTube Import Class
 *
 * Handles importing videos from YouTube channels or playlists
 * into WordPress post types: movies, videos, tv_shows (with episodes).
 */
class Jws_Streamvid_Youtube_Import {

    /**
     * YouTube Data API v3 base URL
     */
    const YT_API_BASE = 'https://www.googleapis.com/youtube/v3/';

    /**
     * Register admin menu and AJAX hooks
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_submenu' ), 20 );
        add_action( 'wp_ajax_jws_yt_fetch_preview',   array( $this, 'ajax_fetch_preview' ) );
        add_action( 'wp_ajax_jws_yt_run_import',      array( $this, 'ajax_run_import' ) );
        add_action( 'wp_ajax_jws_yt_get_import_log',  array( $this, 'ajax_get_import_log' ) );
        add_action( 'wp_ajax_jws_yt_save_settings',   array( $this, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_jws_yt_test_api',        array( $this, 'ajax_test_api' ) );
    }

    /**
     * Register submenu under Jws Settings
     */
    public function register_submenu() {
        add_submenu_page(
            'jws_settings',
            __( 'Import YouTube', 'streamvid' ),
            __( 'Import YouTube', 'streamvid' ),
            'manage_options',
            'jws_youtube_import',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the import admin page
     */
    public function render_page() {
        $saved   = $this->get_saved_settings();
        $partial = plugin_dir_path( __FILE__ ) . '../admin/partials/jws-youtube-import-page.php';
        if ( file_exists( $partial ) ) {
            include $partial;
        }
    }

    // =========================================================
    //  AJAX: Save settings to wp_options
    // =========================================================

    public function ajax_save_settings() {
        check_ajax_referer( 'jws_yt_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'streamvid' ) );
        }

        $settings = array(
            'api_key'           => sanitize_text_field( $_POST['api_key'] ?? '' ),
            'source'            => sanitize_key( $_POST['source'] ?? 'channel_id' ),
            'source_value'      => sanitize_textarea_field( $_POST['source_value'] ?? '' ),
            'search_channel_id' => sanitize_text_field( $_POST['search_channel_id'] ?? '' ),
            'max_results'       => absint( $_POST['max_results'] ?? 20 ),
            'post_type'         => sanitize_key( $_POST['post_type'] ?? 'videos' ),
            'playlist_mode'     => ( ( $_POST['playlist_mode'] ?? '0' ) === '1' ) ? '1' : '0',
            'playlist_name'     => sanitize_text_field( $_POST['playlist_name'] ?? '' ),
            'post_status'       => sanitize_key( $_POST['post_status'] ?? 'draft' ),
            'import_thumbnail'  => ( ( $_POST['import_thumbnail'] ?? '1' ) === '1' ) ? '1' : '0',
            'import_category'   => ( ( $_POST['import_category'] ?? '0' ) === '1' ) ? '1' : '0',
        );

        update_option( 'jws_yt_import_settings', $settings );

        wp_send_json_success( __( 'Settings saved.', 'streamvid' ) );
    }

    /**
     * Get saved settings with defaults.
     */
    private function get_saved_settings() {
        $defaults = array(
            'api_key'           => '',
            'source'            => 'channel_id',
            'source_value'      => '',
            'search_channel_id' => '',
            'max_results'       => 20,
            'post_type'         => 'videos',
            'playlist_mode'     => '0',
            'playlist_name'     => '',
            'post_status'       => 'draft',
            'import_thumbnail'  => '1',
            'import_category'   => '0',
        );

        $saved = get_option( 'jws_yt_import_settings', array() );

        return wp_parse_args( $saved, $defaults );
    }

    // =========================================================
    //  AJAX: Fetch preview (list videos before actual import)
    // =========================================================

    public function ajax_fetch_preview() {
        check_ajax_referer( 'jws_yt_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'streamvid' ) );
        }

        $api_key           = sanitize_text_field( $_POST['api_key'] ?? '' );
        $source            = sanitize_text_field( $_POST['source'] ?? '' );
        $source_val        = sanitize_textarea_field( $_POST['source_value'] ?? '' );
        $search_channel_id = sanitize_text_field( $_POST['search_channel_id'] ?? '' );
        $max               = absint( $_POST['max_results'] ?? 20 );
        $max               = min( $max, 50 );

        if ( empty( $api_key ) || empty( $source_val ) ) {
            wp_send_json_error( __( 'API Key and source value are required.', 'streamvid' ) );
        }

        if ( $source === 'video_url' ) {
            $items = $this->fetch_by_video_ids( $api_key, $source_val );
        } else {
            $items = $this->fetch_youtube_videos( $api_key, $source, $source_val, $max );
        }

        if ( is_wp_error( $items ) ) {
            wp_send_json_error( $items->get_error_message() );
        }

        // Determine post types to check against for duplicate detection
        $post_type     = sanitize_key( $_POST['post_type'] ?? 'videos' );
        $playlist_mode = ( ( $_POST['playlist_mode'] ?? '0' ) === '1' );
        $check_type    = ( $post_type === 'tv_shows' && $playlist_mode ) ? 'episodes' : $post_type;

        foreach ( $items as &$item ) {
            $existing = $this->find_post_by_youtube_id( $item['video_id'], $check_type );
            $item['already_imported'] = (bool) $existing;
            $item['existing_post_id'] = $existing ? (int) $existing : null;
        }
        unset( $item );

        wp_send_json_success( $items );
    }

    // =========================================================
    //  AJAX: Run actual import
    // =========================================================

    public function ajax_run_import() {
        check_ajax_referer( 'jws_yt_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'streamvid' ) );
        }

        $api_key           = sanitize_text_field( $_POST['api_key'] ?? '' );
        $source            = sanitize_text_field( $_POST['source'] ?? '' );
        $source_val        = sanitize_textarea_field( $_POST['source_value'] ?? '' );
        $search_channel_id = sanitize_text_field( $_POST['search_channel_id'] ?? '' );
        $post_type         = sanitize_key( $_POST['post_type'] ?? 'videos' );
        $playlist_mode     = ( $_POST['playlist_mode'] ?? '0' ) === '1';
        $playlist_name     = sanitize_text_field( $_POST['playlist_name'] ?? '' );
        $max               = absint( $_POST['max_results'] ?? 20 );
        $max               = min( $max, 50 );
        $import_thumb      = ( $_POST['import_thumbnail'] ?? '1' ) === '1';
        $import_category   = ( $_POST['import_category'] ?? '0' ) === '1';
        $post_status       = sanitize_key( $_POST['post_status'] ?? 'draft' );

        if ( ! in_array( $post_type, array( 'movies', 'videos', 'tv_shows' ), true ) ) {
            $post_type = 'videos';
        }

        if ( $source === 'video_url' ) {
            $items = $this->fetch_by_video_ids( $api_key, $source_val );
        } else {
            $items = $this->fetch_youtube_videos( $api_key, $source, $source_val, $max );
        }

        if ( is_wp_error( $items ) ) {
            wp_send_json_error( $items->get_error_message() );
        }

        $log = array();

        // Filter to selected IDs if provided
        $selected_ids = isset( $_POST['selected_ids'] ) && is_array( $_POST['selected_ids'] )
            ? array_map( 'sanitize_text_field', $_POST['selected_ids'] )
            : array();

        if ( ! empty( $selected_ids ) ) {
            $items = array_values( array_filter( $items, function( $item ) use ( $selected_ids ) {
                return in_array( $item['video_id'], $selected_ids, true );
            } ) );
        }

        // === Playlist mode: create one tv_show and add episodes ===
        if ( $playlist_mode && $post_type === 'tv_shows' ) {
            $result = $this->import_as_playlist( $items, $playlist_name, $post_status, $import_thumb, $import_category, $source_val );
            wp_send_json_success( $result );
        }

        // === Normal mode: one post per video ===
        foreach ( $items as $item ) {
            $result = $this->import_single_video( $item, $post_type, $post_status, $import_thumb, $import_category );
            $log[]  = $result;
        }

        wp_send_json_success( array(
            'log'   => $log,
            'total' => count( $log ),
        ) );
    }

    // =========================================================
    //  AJAX: Get import log transient
    // =========================================================

    public function ajax_get_import_log() {
        check_ajax_referer( 'jws_yt_import_nonce', 'nonce' );
        $log = get_transient( 'jws_yt_import_log' );
        wp_send_json_success( $log ?: array() );
    }

    // =========================================================
    //  AJAX: Test API Key validity
    // =========================================================

    public function ajax_test_api() {
        check_ajax_referer( 'jws_yt_import_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'streamvid' ) );
        }

        $api_key = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( __( 'API Key is empty.', 'streamvid' ) );
        }

        // Minimal test: list 1 item from a known public playlist
        $url = self::YT_API_BASE . 'videos?' . http_build_query( array(
            'part'       => 'id',
            'chart'      => 'mostPopular',
            'maxResults' => 1,
            'key'        => $api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $code = wp_remote_retrieve_response_code( $response );

        if ( isset( $body['error'] ) ) {
            $msg    = $body['error']['message'] ?? 'Unknown error';
            $reason = $body['error']['errors'][0]['reason'] ?? '';
            wp_send_json_error( sprintf( '[%d] %s (reason: %s)', $code, $msg, $reason ) );
        }

        wp_send_json_success( __( 'API Key is valid and working!', 'streamvid' ) );
    }

    // =========================================================
    //  Core: Fetch videos from YouTube API
    // =========================================================

    /**
     * Fetch video list from a YouTube channel or playlist.
     *
     * @param string $api_key
     * @param string $source       'channel_id' | 'playlist_id' | 'channel_username'
     * @param string $source_value
     * @param int    $max
     * @return array|WP_Error
     */
    private function fetch_youtube_videos( $api_key, $source, $source_value, $max = 20 ) {

        // Resolve channel to uploads playlist
        if ( $source === 'channel_id' ) {
            $playlist_id = $this->get_uploads_playlist( $api_key, $source_value, 'id' );
        } elseif ( $source === 'channel_username' ) {
            // Support both @handle format and legacy username
            $handle = ltrim( $source_value, '@' );
            // Try forHandle first (new API), fall back to forUsername
            $playlist_id = $this->get_uploads_playlist( $api_key, $handle, 'forHandle' );
            if ( is_wp_error( $playlist_id ) ) {
                $playlist_id = $this->get_uploads_playlist( $api_key, $source_value, 'forUsername' );
            }
        } else {
            $playlist_id = $source_value; // direct playlist ID
        }

        if ( is_wp_error( $playlist_id ) ) {
            return $playlist_id;
        }

        // Fetch playlist items — use http_build_query to avoid encoding issues
        $url = self::YT_API_BASE . 'playlistItems?' . http_build_query( array(
            'part'       => 'snippet',
            'playlistId' => $playlist_id,
            'maxResults' => $max,
            'key'        => $api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 20 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            $msg    = $body['error']['message'] ?? __( 'YouTube API error', 'streamvid' );
            $reason = $body['error']['errors'][0]['reason'] ?? '';
            return new WP_Error( 'yt_api_error', sprintf( '[HTTP %d] %s%s', $http_code, $msg, $reason ? " (reason: $reason)" : '' ) );
        }

        $videos = array();

        if ( ! empty( $body['items'] ) ) {
            // Collect video IDs to batch-fetch details (duration, etc.)
            $video_ids = array();
            foreach ( $body['items'] as $item ) {
                $vid_id = $item['snippet']['resourceId']['videoId'] ?? '';
                if ( $vid_id ) {
                    $video_ids[] = $vid_id;
                }
            }

            $details = $this->fetch_video_details( $api_key, $video_ids );

            foreach ( $body['items'] as $item ) {
                $snippet = $item['snippet'];
                $vid_id  = $snippet['resourceId']['videoId'] ?? '';

                if ( empty( $vid_id ) ) continue;

                $detail   = $details[ $vid_id ] ?? array();
                $duration = $this->parse_iso8601_duration( $detail['duration'] ?? '' );

                $videos[] = array(
                    'video_id'    => $vid_id,
                    'title'       => $snippet['title'] ?? '',
                    'description' => $snippet['description'] ?? '',
                    'published'   => $snippet['publishedAt'] ?? '',
                    'thumbnail'   => $this->best_thumbnail( $snippet['thumbnails'] ?? array() ),
                    'url'         => 'https://www.youtube.com/watch?v=' . $vid_id,
                    'duration'    => $duration,
                    'tags'        => $detail['tags'] ?? array(),
                    'category_id' => $detail['category_id'] ?? '',
                );
            }
        }

        return $videos;
    }

    /**
     * Fetch videos directly by URL(s) or video ID(s).
     * Input can be newline/comma-separated list of YouTube URLs or bare 11-char video IDs.
     *
     * @param string $api_key
     * @param string $input   Raw textarea content
     * @return array|WP_Error
     */
    private function fetch_by_video_ids( $api_key, $input ) {
        $raw       = preg_split( '/[\r\n,]+/', $input );
        $video_ids = array();

        foreach ( $raw as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            // Extract video ID from various YouTube URL formats
            if ( preg_match( '/(?:v=|youtu\.be\/|embed\/|shorts\/)([a-zA-Z0-9_-]{11})/', $line, $m ) ) {
                $video_ids[] = $m[1];
            } elseif ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $line ) ) {
                $video_ids[] = $line;
            }
        }

        $video_ids = array_unique( $video_ids );

        if ( empty( $video_ids ) ) {
            return new WP_Error( 'no_ids', __( 'No valid YouTube video IDs found in input. Paste YouTube URLs or 11-character video IDs.', 'streamvid' ) );
        }

        $url = self::YT_API_BASE . 'videos?' . http_build_query( array(
            'part' => 'snippet,contentDetails',
            'id'   => implode( ',', $video_ids ),
            'key'  => $api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) return $response;

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            $msg    = $body['error']['message'] ?? 'YouTube API error';
            $reason = $body['error']['errors'][0]['reason'] ?? '';
            return new WP_Error( 'yt_api_error', sprintf( '[HTTP %d] %s%s', $http_code, $msg, $reason ? " (reason: $reason)" : '' ) );
        }

        $videos = array();
        foreach ( $body['items'] ?? array() as $item ) {
            $vid_id   = $item['id'];
            $snippet  = $item['snippet'] ?? array();
            $duration = $this->parse_iso8601_duration( $item['contentDetails']['duration'] ?? '' );
            $videos[] = array(
                'video_id'    => $vid_id,
                'title'       => $snippet['title'] ?? '',
                'description' => $snippet['description'] ?? '',
                'published'   => $snippet['publishedAt'] ?? '',
                'thumbnail'   => $this->best_thumbnail( $snippet['thumbnails'] ?? array() ),
                'url'         => 'https://www.youtube.com/watch?v=' . $vid_id,
                'duration'    => $duration,
                'tags'        => $snippet['tags'] ?? array(),
                'category_id' => $snippet['categoryId'] ?? '',
            );
        }

        return $videos;
    }

    /**
     * Search YouTube videos by keyword (optionally within a channel).
     *
     * @param string $api_key
    /**
     * Get the uploads playlist ID for a channel.
     */
    private function get_uploads_playlist( $api_key, $channel_value, $param_name ) {
        $url = self::YT_API_BASE . 'channels?' . http_build_query( array(
            'part'      => 'contentDetails',
            $param_name => $channel_value,
            'key'       => $api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $body['error'] ) ) {
            $msg    = $body['error']['message'] ?? 'YouTube API error';
            $reason = $body['error']['errors'][0]['reason'] ?? '';
            return new WP_Error( 'yt_api_error', sprintf( '[HTTP %d] %s%s', $http_code, $msg, $reason ? " (reason: $reason)" : '' ) );
        }

        $playlist_id = $body['items'][0]['contentDetails']['relatedPlaylists']['uploads'] ?? '';

        if ( empty( $playlist_id ) ) {
            return new WP_Error( 'yt_no_channel', __( 'Channel not found or has no uploads playlist.', 'streamvid' ) );
        }

        return $playlist_id;
    }

    /**
     * Batch-fetch video details (duration, tags).
     *
     * @param string $api_key
     * @param array  $video_ids
     * @return array keyed by video_id
     */
    private function fetch_video_details( $api_key, $video_ids ) {
        if ( empty( $video_ids ) ) return array();

        $url = self::YT_API_BASE . 'videos?' . http_build_query( array(
            'part' => 'contentDetails,snippet',
            'id'   => implode( ',', $video_ids ),
            'key'  => $api_key,
        ) );

        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );

        if ( is_wp_error( $response ) ) return array();

        $body    = json_decode( wp_remote_retrieve_body( $response ), true );
        $details = array();

        foreach ( $body['items'] ?? array() as $item ) {
            $details[ $item['id'] ] = array(
                'duration'    => $item['contentDetails']['duration'] ?? '',
                'tags'        => $item['snippet']['tags'] ?? array(),
                'category_id' => $item['snippet']['categoryId'] ?? '',
            );
        }

        return $details;
    }

    /**
     * Parse ISO 8601 duration (PT1H2M3S) to seconds.
     */
    private function parse_iso8601_duration( $duration ) {
        if ( empty( $duration ) ) return 0;
        preg_match( '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $m );
        return (int) ( (int) ( $m[1] ?? 0 ) * 3600 + (int) ( $m[2] ?? 0 ) * 60 + (int) ( $m[3] ?? 0 ) );
    }

    /**
     * Get best quality thumbnail URL.
     */
    private function best_thumbnail( $thumbnails ) {
        foreach ( array( 'maxres', 'standard', 'high', 'medium', 'default' ) as $size ) {
            if ( ! empty( $thumbnails[ $size ]['url'] ) ) {
                return $thumbnails[ $size ]['url'];
            }
        }
        return '';
    }

    // =========================================================
    //  Import: single video → one post
    // =========================================================

    /**
     * Import a single YouTube video as a post.
     *
     * @param array  $item
     * @param string $post_type
     * @param string $post_status
     * @param bool   $import_thumb
     * @return array
     */
    private function import_single_video( $item, $post_type, $post_status, $import_thumb, $import_category = false ) {

        // Skip duplicate by YouTube video ID
        $existing = $this->find_post_by_youtube_id( $item['video_id'], $post_type );

        if ( $existing ) {
            return array(
                'status'   => 'skipped',
                'title'    => $item['title'],
                'video_id' => $item['video_id'],
                'post_id'  => $existing,
                'message'  => __( 'Already exists – skipped.', 'streamvid' ),
            );
        }

        $post_date = ! empty( $item['published'] ) ? date( 'Y-m-d H:i:s', strtotime( $item['published'] ) ) : '';

        $post_id = wp_insert_post( array(
            'post_title'   => sanitize_text_field( $item['title'] ),
            'post_content' => wp_kses_post( $item['description'] ),
            'post_type'    => $post_type,
            'post_status'  => $post_status,
            'post_date'    => $post_date,
        ) );

        if ( is_wp_error( $post_id ) ) {
            return array(
                'status'   => 'error',
                'title'    => $item['title'],
                'video_id' => $item['video_id'],
                'message'  => $post_id->get_error_message(),
            );
        }

        $this->save_video_meta( $post_id, $item, $post_type, $import_category );

        if ( $import_thumb && ! empty( $item['thumbnail'] ) ) {
            $this->set_post_thumbnail_from_url( $post_id, $item['thumbnail'], $item['title'] );
        }

        return array(
            'status'   => 'imported',
            'title'    => $item['title'],
            'video_id' => $item['video_id'],
            'post_id'  => $post_id,
            'edit_url' => get_edit_post_link( $post_id, 'raw' ),
            'message'  => __( 'Imported successfully.', 'streamvid' ),
        );
    }

    // =========================================================
    //  Import: playlist mode → tv_show + episodes
    // =========================================================

    /**
     * Import all videos as a single tv_show (Season 1) with episodes.
     *
     * @param array  $items
     * @param string $playlist_name  Title for the tv_show post
     * @param string $post_status
     * @param bool   $import_thumb
     * @param string $source_val     Used as playlist_source meta
     * @return array
     */
    private function import_as_playlist( $items, $playlist_name, $post_status, $import_thumb, $import_category = false, $source_val = '' ) {

        if ( empty( $playlist_name ) ) {
            $playlist_name = __( 'Imported YouTube Playlist', 'streamvid' );
        }

        // Create or find the tv_show
        $show_id = $this->find_or_create_tvshow( $playlist_name, $post_status, $source_val );

        if ( is_wp_error( $show_id ) ) {
            return array( 'error' => $show_id->get_error_message() );
        }

        // Set thumbnail from first item if not already set
        if ( $import_thumb && ! has_post_thumbnail( $show_id ) && ! empty( $items[0]['thumbnail'] ) ) {
            $this->set_post_thumbnail_from_url( $show_id, $items[0]['thumbnail'], $playlist_name );
        }

        // Load existing Season 1 episodes to merge (avoid losing previously imported)
        $existing_season_episodes = array();
        if ( function_exists( 'get_field' ) ) {
            $existing_seasons = get_field( 'tv_shows_seasons', $show_id );
            if ( ! empty( $existing_seasons[0]['episodes'] ) && is_array( $existing_seasons[0]['episodes'] ) ) {
                $existing_season_episodes = array_map( 'intval', $existing_seasons[0]['episodes'] );
            }
        } else {
            $raw = get_post_meta( $show_id, 'tv_shows_seasons_0_episodes', true );
            if ( is_array( $raw ) ) {
                $existing_season_episodes = array_map( 'intval', $raw );
            }
        }

        // episode_number starts after existing episodes
        $ep_number   = count( $existing_season_episodes ) + 1;
        $episode_ids = $existing_season_episodes; // start with existing
        $log         = array();

        foreach ( $items as $item ) {

            $existing = $this->find_post_by_youtube_id( $item['video_id'], 'episodes' );

            if ( $existing ) {
                // Already imported – make sure it's in our list
                if ( ! in_array( (int) $existing, $episode_ids, true ) ) {
                    $episode_ids[] = (int) $existing;
                }
                $log[] = array(
                    'status'   => 'skipped',
                    'title'    => $item['title'],
                    'video_id' => $item['video_id'],
                    'post_id'  => $existing,
                    'message'  => __( 'Episode already exists – skipped.', 'streamvid' ),
                );
                continue;
            }

            $post_date = ! empty( $item['published'] ) ? date( 'Y-m-d H:i:s', strtotime( $item['published'] ) ) : '';

            $ep_id = wp_insert_post( array(
                'post_title'   => sanitize_text_field( $item['title'] ),
                'post_content' => wp_kses_post( $item['description'] ),
                'post_type'    => 'episodes',
                'post_status'  => $post_status,
                'post_date'    => $post_date,
            ) );

            if ( is_wp_error( $ep_id ) ) {
                $log[] = array(
                    'status'  => 'error',
                    'title'   => $item['title'],
                    'message' => $ep_id->get_error_message(),
                );
                continue;
            }

            $this->save_video_meta( $ep_id, $item, 'episodes', $import_category );

            update_post_meta( $ep_id, 'tv_show_id',     $show_id );
            update_post_meta( $ep_id, 'season_number',  1 );
            update_post_meta( $ep_id, 'episode_number', $ep_number );

            if ( $import_thumb && ! empty( $item['thumbnail'] ) ) {
                $this->set_post_thumbnail_from_url( $ep_id, $item['thumbnail'], $item['title'] );
            }

            $episode_ids[] = (int) $ep_id;

            $log[] = array(
                'status'   => 'imported',
                'title'    => $item['title'],
                'video_id' => $item['video_id'],
                'post_id'  => $ep_id,
                'edit_url' => get_edit_post_link( $ep_id, 'raw' ),
                'message'  => __( 'Episode imported.', 'streamvid' ),
            );

            $ep_number++;
        }

        // Save full season structure (merged existing + new)
        $this->save_tv_show_seasons( $show_id, array_unique( $episode_ids ) );

        return array(
            'show_id'  => $show_id,
            'show_url' => get_edit_post_link( $show_id, 'raw' ),
            'log'      => $log,
            'total'    => count( $log ),
        );
    }

    /**
     * Find existing tv_show by playlist source meta or create it.
     */
    private function find_or_create_tvshow( $title, $post_status, $source_val ) {
        $existing_query = new WP_Query( array(
            'post_type'      => 'tv_shows',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array( array(
                'key'   => '_yt_playlist_source',
                'value' => $source_val,
            ) ),
        ) );

        if ( $existing_query->have_posts() ) {
            return $existing_query->posts[0]->ID;
        }

        $show_id = wp_insert_post( array(
            'post_title'  => sanitize_text_field( $title ),
            'post_type'   => 'tv_shows',
            'post_status' => $post_status,
        ) );

        if ( ! is_wp_error( $show_id ) ) {
            update_post_meta( $show_id, '_yt_playlist_source', $source_val );
        }

        return $show_id;
    }

    /**
     * Save the tv_shows_seasons ACF repeater structure.
     *
     * Real ACF field keys (from tv_shows.php, $key_slug = 'field_tvs_'):
     *   Repeater : field_tvs_tv_shows_seasons
     *   season_thumbnail : field_tvs_season_thumbnail
     *   season_name      : field_tvs_season_name
     *   episodes (relationship, return_format='id') : field_tvs_episodes
     *
     * ACF repeater postmeta pattern:
     *   tv_shows_seasons              = 1          (number of rows)
     *   _tv_shows_seasons             = field_tvs_tv_shows_seasons
     *   tv_shows_seasons_0_season_name = "Season 1"
     *   _tv_shows_seasons_0_season_name = field_tvs_season_name
     *   tv_shows_seasons_0_episodes    = [id1, id2, ...]  (serialized array — relationship field)
     *   _tv_shows_seasons_0_episodes   = field_tvs_episodes
     */
    private function save_tv_show_seasons( $show_id, $episode_ids ) {

        // Use update_field() if ACF is available — most reliable
        if ( function_exists( 'update_field' ) ) {
            $seasons_value = array(
                array(
                    'season_thumbnail' => '',
                    'season_name'      => __( 'Season 1', 'streamvid' ),
                    'episodes'         => array_map( 'intval', $episode_ids ),
                ),
            );
            update_field( 'tv_shows_seasons', $seasons_value, $show_id );
            return;
        }

        // Fallback: write directly to postmeta with correct ACF keys
        update_post_meta( $show_id, 'tv_shows_seasons',   1 );
        update_post_meta( $show_id, '_tv_shows_seasons',  'field_tvs_tv_shows_seasons' );

        update_post_meta( $show_id, 'tv_shows_seasons_0_season_thumbnail', '' );
        update_post_meta( $show_id, '_tv_shows_seasons_0_season_thumbnail', 'field_tvs_season_thumbnail' );

        update_post_meta( $show_id, 'tv_shows_seasons_0_season_name', __( 'Season 1', 'streamvid' ) );
        update_post_meta( $show_id, '_tv_shows_seasons_0_season_name', 'field_tvs_season_name' );

        // Relationship field: stored as serialized array of IDs
        update_post_meta( $show_id, 'tv_shows_seasons_0_episodes',  array_map( 'intval', $episode_ids ) );
        update_post_meta( $show_id, '_tv_shows_seasons_0_episodes', 'field_tvs_episodes' );
    }

    // =========================================================
    //  Helpers
    // =========================================================

    /**
     * Save video-specific meta for the given post type.
     */
    /**
     * YouTube category ID → name mapping (API v3).
     */
    private function get_youtube_category_name( $category_id ) {
        $map = array(
            '1'  => 'Film & Animation',
            '2'  => 'Autos & Vehicles',
            '10' => 'Music',
            '15' => 'Pets & Animals',
            '17' => 'Sports',
            '18' => 'Short Movies',
            '19' => 'Travel & Events',
            '20' => 'Gaming',
            '21' => 'Videoblogging',
            '22' => 'People & Blogs',
            '23' => 'Comedy',
            '24' => 'Entertainment',
            '25' => 'News & Politics',
            '26' => 'Howto & Style',
            '27' => 'Education',
            '28' => 'Science & Technology',
            '29' => 'Nonprofits & Activism',
            '30' => 'Movies',
            '31' => 'Anime/Animation',
            '32' => 'Action/Adventure',
            '33' => 'Classics',
            '34' => 'Comedy',
            '35' => 'Documentary',
            '36' => 'Drama',
            '37' => 'Family',
            '38' => 'Foreign',
            '39' => 'Horror',
            '40' => 'Sci-Fi/Fantasy',
            '41' => 'Thriller',
            '42' => 'Shorts',
            '43' => 'Shows',
            '44' => 'Trailers',
        );
        return $map[ (string) $category_id ] ?? '';
    }

    private function save_video_meta( $post_id, $item, $post_type, $import_category = false ) {
        // YouTube URL meta (used by the player)
        update_post_meta( $post_id, 'videos_type', 'url' );
        update_post_meta( $post_id, 'videos_url',  $item['url'] );

        // Duration (in seconds)
        if ( ! empty( $item['duration'] ) ) {
            update_post_meta( $post_id, '_duration', (int) $item['duration'] );
        }

        // YouTube video ID (for deduplication)
        update_post_meta( $post_id, '_yt_video_id', sanitize_text_field( $item['video_id'] ) );

        // Category taxonomy map (movies & tv_shows → genres; others → their own cat)
        $cat_tax_map = array(
            'movies'   => 'genres',
            'videos'   => 'videos_cat',
            'tv_shows' => 'genres',
            'episodes' => 'genres',
        );

        // Import YouTube category as WordPress category term
        if ( $import_category && ! empty( $item['category_id'] ) ) {
            $cat_name = $this->get_youtube_category_name( $item['category_id'] );
            $taxonomy = $cat_tax_map[ $post_type ] ?? '';
            if ( $cat_name && $taxonomy ) {
                wp_set_object_terms( $post_id, sanitize_text_field( $cat_name ), $taxonomy, false );
            }
        }

        // Store tags as WordPress tags where supported
        if ( ! empty( $item['tags'] ) && is_array( $item['tags'] ) ) {
            $tax_map = array(
                'movies'   => 'movies_tag',
                'videos'   => 'videos_tag',
                'tv_shows' => 'tv_shows_tag',
                'episodes' => 'movies_tag',
            );
            $taxonomy = $tax_map[ $post_type ] ?? '';
            if ( $taxonomy ) {
                wp_set_object_terms( $post_id, array_map( 'sanitize_text_field', $item['tags'] ), $taxonomy );
            }
        }
    }

    /**
     * Find a post by YouTube video ID meta.
     */
    private function find_post_by_youtube_id( $yt_id, $post_type ) {
        $q = new WP_Query( array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_query'     => array( array(
                'key'   => '_yt_video_id',
                'value' => $yt_id,
            ) ),
            'fields' => 'ids',
        ) );

        return $q->have_posts() ? $q->posts[0] : false;
    }

    /**
     * Download a remote image and set it as post thumbnail.
     */
    private function set_post_thumbnail_from_url( $post_id, $url, $title = '' ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $url, $post_id, sanitize_text_field( $title ), 'id' );

        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }
}
