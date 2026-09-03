<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$nonce    = wp_create_nonce( 'jws_yt_import_nonce' );
$ajax_url = admin_url( 'admin-ajax.php' );

$src              = $saved['source'] ?? 'channel_id';
$is_video_url     = ( $src === 'video_url' );
$is_tv            = ( $saved['post_type'] === 'tv_shows' );
$is_playlist_mode = ( $is_tv && $saved['playlist_mode'] === '1' );
?>
<div class="wrap jws-yt-wrap">

    <!-- ── Page header ── -->
    <div class="jws-yt-page-header">
        <span class="jws-yt-logo">▶</span>
        <div>
            <h1><?php esc_html_e( 'YouTube Importer', 'streamvid' ); ?></h1>
            <p><?php esc_html_e( 'Import videos from YouTube channels, playlists, or direct URLs into your site.', 'streamvid' ); ?></p>
        </div>
    </div>

    <div id="jws-yt-notices"></div>

    <!-- ══════════════════════════════════════════
         MAIN LAYOUT  (left settings | right preview)
    ══════════════════════════════════════════ -->
    <div class="jws-yt-layout">

        <!-- ── LEFT PANEL ── -->
        <div class="jws-yt-panel-left">

            <!-- § API Key -->
            <div class="jws-yt-section" id="section-api">
                <div class="jws-yt-section-head">
                    <span class="jws-yt-step">1</span>
                    <h3><?php esc_html_e( 'API Key', 'streamvid' ); ?></h3>
                    <span id="api_key_status_icon"></span>
                </div>
                <div class="jws-yt-section-body">
                    <div class="jws-yt-api-row">
                        <input type="password" id="yt_api_key" class="jws-yt-input" placeholder="AIzaSy…"
                               value="<?php echo esc_attr( $saved['api_key'] ); ?>" />
                        <button type="button" id="jws_yt_test_api_btn" class="jws-yt-btn jws-yt-btn-ghost">
                            <?php esc_html_e( 'Test', 'streamvid' ); ?>
                        </button>
                    </div>
                    <span id="jws_yt_test_result" class="jws-yt-hint"></span>
                    <p class="jws-yt-hint"><?php esc_html_e( 'YouTube Data API v3 key from Google Cloud Console.', 'streamvid' ); ?></p>
                </div>
            </div>

            <!-- § Source -->
            <div class="jws-yt-section" id="section-source">
                <div class="jws-yt-section-head">
                    <span class="jws-yt-step">2</span>
                    <h3><?php esc_html_e( 'Source', 'streamvid' ); ?></h3>
                </div>
                <div class="jws-yt-section-body">
                    <!-- Source type pills -->
                    <div class="jws-yt-pills" id="yt_source_pills">
                        <?php
                        $sources = array(
                            'channel_id'       => __( 'Channel ID', 'streamvid' ),
                            'channel_username' => __( '@Handle', 'streamvid' ),
                            'playlist_id'      => __( 'Playlist', 'streamvid' ),
                            'video_url'        => __( 'Direct URL / ID', 'streamvid' ),
                        );
                        foreach ( $sources as $val => $label ) :
                        ?>
                        <label class="jws-yt-pill <?php echo $src === $val ? 'active' : ''; ?>">
                            <input type="radio" name="source" value="<?php echo esc_attr( $val ); ?>" <?php checked( $src, $val ); ?> hidden>
                            <?php echo esc_html( $label ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Single-line input (channel / playlist) -->
                    <div id="yt_input_single" class="jws-yt-source-input" <?php echo $is_video_url ? 'style="display:none"' : ''; ?>>
                        <input type="text" id="yt_source_value" class="jws-yt-input"
                               placeholder="UCxxxxxxxx  /  PLxxxxxxxx  /  @ChannelName"
                               value="<?php echo esc_attr( $is_video_url ? '' : $saved['source_value'] ); ?>" />
                        <p class="jws-yt-hint" id="yt_source_hint"></p>
                    </div>

                    <!-- Textarea (direct URLs / IDs) -->
                    <div id="yt_input_textarea" class="jws-yt-source-input" <?php echo $is_video_url ? '' : 'style="display:none"'; ?>>
                        <textarea id="yt_source_textarea" class="jws-yt-input jws-yt-textarea" rows="5"
                            placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ&#10;dQw4w9WgXcQ&#10;https://youtu.be/another_id"><?php echo $is_video_url ? esc_textarea( $saved['source_value'] ) : ''; ?></textarea>
                        <p class="jws-yt-hint"><?php esc_html_e( 'One URL or video ID per line. Supports youtube.com, youtu.be and Shorts links.', 'streamvid' ); ?></p>
                    </div>

                    <!-- Max results (hidden for direct URL) -->
                    <div id="yt_max_results_wrap" class="jws-yt-inline-field" <?php echo $is_video_url ? 'style="display:none"' : ''; ?>>
                        <label><?php esc_html_e( 'Max results', 'streamvid' ); ?></label>
                        <input type="number" id="yt_max_results" class="jws-yt-input jws-yt-input-tiny"
                               value="<?php echo esc_attr( $saved['max_results'] ); ?>" min="1" max="50" />
                        <span class="jws-yt-hint"><?php esc_html_e( '(max 50)', 'streamvid' ); ?></span>
                    </div>
                </div>
            </div>

            <!-- § Import Options -->
            <div class="jws-yt-section" id="section-options">
                <div class="jws-yt-section-head">
                    <span class="jws-yt-step">3</span>
                    <h3><?php esc_html_e( 'Import Options', 'streamvid' ); ?></h3>
                </div>
                <div class="jws-yt-section-body">
                    <div class="jws-yt-options-grid">
                        <!-- Post Type -->
                        <div class="jws-yt-field">
                            <label><?php esc_html_e( 'Post type', 'streamvid' ); ?></label>
                            <select id="yt_post_type" class="jws-yt-select">
                                <option value="videos"   <?php selected( $saved['post_type'], 'videos' ); ?>><?php esc_html_e( 'Videos', 'streamvid' ); ?></option>
                                <option value="movies"   <?php selected( $saved['post_type'], 'movies' ); ?>><?php esc_html_e( 'Movies', 'streamvid' ); ?></option>
                                <option value="tv_shows" <?php selected( $saved['post_type'], 'tv_shows' ); ?>><?php esc_html_e( 'TV Shows', 'streamvid' ); ?></option>
                            </select>
                        </div>
                        <!-- Status -->
                        <div class="jws-yt-field">
                            <label><?php esc_html_e( 'Post status', 'streamvid' ); ?></label>
                            <select id="yt_post_status" class="jws-yt-select">
                                <option value="draft"   <?php selected( $saved['post_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'streamvid' ); ?></option>
                                <option value="publish" <?php selected( $saved['post_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'streamvid' ); ?></option>
                                <option value="pending" <?php selected( $saved['post_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'streamvid' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Thumbnail toggle -->
                    <label class="jws-yt-toggle-row">
                        <span class="jws-yt-toggle-switch">
                            <input type="checkbox" id="yt_import_thumbnail" <?php checked( $saved['import_thumbnail'], '1' ); ?> />
                            <span class="jws-yt-toggle-slider"></span>
                        </span>
                        <span><?php esc_html_e( 'Download YouTube thumbnail as featured image', 'streamvid' ); ?></span>
                    </label>

                    <!-- Category toggle -->
                    <label class="jws-yt-toggle-row">
                        <span class="jws-yt-toggle-switch">
                            <input type="checkbox" id="yt_import_category" <?php checked( $saved['import_category'] ?? '0', '1' ); ?> />
                            <span class="jws-yt-toggle-slider"></span>
                        </span>
                        <span><?php esc_html_e( 'Import YouTube category into post category', 'streamvid' ); ?></span>
                    </label>

                    <!-- Hide imported toggle -->
                    <label class="jws-yt-toggle-row">
                        <span class="jws-yt-toggle-switch">
                            <input type="checkbox" id="yt_hide_imported" />
                            <span class="jws-yt-toggle-slider"></span>
                        </span>
                        <span><?php esc_html_e( 'Hide already imported videos from preview list', 'streamvid' ); ?></span>
                    </label>

                    <!-- TV Show / Playlist mode -->
                    <div id="yt_tvshow_box" class="jws-yt-tvshow-box" <?php echo $is_tv ? '' : 'style="display:none"'; ?>>
                        <label class="jws-yt-toggle-row">
                            <span class="jws-yt-toggle-switch">
                                <input type="checkbox" id="yt_playlist_mode" <?php checked( $saved['playlist_mode'], '1' ); ?> />
                                <span class="jws-yt-toggle-slider"></span>
                            </span>
                            <span><?php esc_html_e( 'Playlist mode — import as Episodes inside one TV Show (Season 1)', 'streamvid' ); ?></span>
                        </label>
                        <div id="yt_tvshow_name_row" <?php echo $is_playlist_mode ? '' : 'style="display:none"'; ?>>
                            <input type="text" id="yt_playlist_name" class="jws-yt-input"
                                   placeholder="<?php esc_attr_e( 'TV Show title…', 'streamvid' ); ?>"
                                   value="<?php echo esc_attr( $saved['playlist_name'] ); ?>" />
                            <p class="jws-yt-hint"><?php esc_html_e( 'Leave empty to use the channel / playlist name.', 'streamvid' ); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- § Actions -->
            <div class="jws-yt-section-actions">
                <button type="button" id="jws_yt_preview_btn" class="jws-yt-btn jws-yt-btn-primary">
                    <span class="dashicons dashicons-search"></span>
                    <?php esc_html_e( 'Load Videos', 'streamvid' ); ?>
                </button>
                <span id="jws_yt_spinner" class="spinner" style="float:none;margin-top:0;"></span>
                <button type="button" id="jws_yt_save_btn" class="jws-yt-btn jws-yt-btn-ghost" style="margin-left:auto;">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Settings', 'streamvid' ); ?>
                </button>
                <span id="jws_yt_save_status" class="jws-yt-save-status"></span>
            </div>

        </div><!-- /.jws-yt-panel-left -->

        <!-- ── RIGHT PANEL (preview + log) ── -->
        <div class="jws-yt-panel-right">

            <!-- Empty state -->
            <div class="jws-yt-empty-state" id="jws_yt_empty_state">
                <span class="dashicons dashicons-video-alt3"></span>
                <p><?php esc_html_e( 'Configure your source on the left, then click "Load Videos" to preview before importing.', 'streamvid' ); ?></p>
            </div>

            <!-- Preview section -->
            <div id="jws_yt_preview_section" style="display:none;">
                <div class="jws-yt-preview-header">
                    <div class="jws-yt-preview-title">
                        <h3><?php esc_html_e( 'Videos', 'streamvid' ); ?>
                            <span id="jws_yt_count_badge" class="jws-yt-badge"></span>
                            <span id="jws_yt_selected_badge" class="jws-yt-badge jws-yt-badge-green" style="display:none;"></span>
                        </h3>
                    </div>
                    <div class="jws-yt-preview-toolbar">
                        <input type="text" id="jws_yt_filter_text" class="jws-yt-input jws-yt-input-search"
                               placeholder="<?php esc_attr_e( 'Filter by title…', 'streamvid' ); ?>" />
                        <select id="jws_yt_filter_perpage" class="jws-yt-select jws-yt-select-sm">
                            <option value="10">10</option>
                            <option value="20" selected>20</option>
                            <option value="50">50</option>
                        </select>
                        <button type="button" id="jws_yt_select_all_btn" class="jws-yt-btn jws-yt-btn-ghost jws-yt-btn-sm"><?php esc_html_e( 'All', 'streamvid' ); ?></button>
                        <button type="button" id="jws_yt_deselect_all_btn" class="jws-yt-btn jws-yt-btn-ghost jws-yt-btn-sm"><?php esc_html_e( 'None', 'streamvid' ); ?></button>
                    </div>
                </div>

                <div id="jws_yt_preview_table_wrap"></div>
                <div id="jws_yt_pagination" class="jws-yt-pagination"></div>
            </div>

            <!-- Import Log -->
            <div id="jws_yt_log_section" style="display:none;">
                <div class="jws-yt-preview-header">
                    <h3 style="flex:1"><?php esc_html_e( 'Import Log', 'streamvid' ); ?></h3>
                    <button type="button" id="jws_yt_log_close" class="jws-yt-btn jws-yt-btn-ghost jws-yt-btn-sm">✕</button>
                </div>
                <div id="jws_yt_log_table_wrap"></div>
            </div>

        </div><!-- /.jws-yt-panel-right -->
    </div><!-- /.jws-yt-layout -->

    <!-- ── Sticky Import Bar ── -->
    <div id="jws_yt_sticky_bar" class="jws-yt-sticky-bar" style="display:none;">
        <span id="jws_yt_sticky_count"></span>
        <button type="button" id="jws_yt_import_btn" class="jws-yt-btn jws-yt-btn-danger">
            <span class="dashicons dashicons-download"></span>
            <span id="jws_yt_import_btn_label"><?php esc_html_e( 'Import Selected', 'streamvid' ); ?></span>
        </button>
    </div>

</div><!-- /.jws-yt-wrap -->

<style>
/* ── Layout ── */
.jws-yt-wrap { max-width:1280px; font-size:14px; padding-bottom:60px; }
.jws-yt-page-header { display:flex; align-items:center; gap:14px; margin-bottom:20px; }
.jws-yt-page-header h1 { margin:0; font-size:22px; }
.jws-yt-page-header p { margin:2px 0 0; color:#50575e; font-size:13px; }
.jws-yt-logo { font-size:32px; color:#ff0000; line-height:1; }

.jws-yt-layout { display:grid; grid-template-columns:360px 1fr; gap:20px; align-items:start; }
@media (max-width:960px) { .jws-yt-layout { grid-template-columns:1fr; } }

/* ── Sections ── */
.jws-yt-panel-left { display:flex; flex-direction:column; gap:6px; }
.jws-yt-section { background:#fff; border:1px solid #dde0e4; border-radius:8px; overflow:hidden; }
.jws-yt-section-head { display:flex; align-items:center; gap:10px; padding:11px 16px; background:#f9f9fb; border-bottom:1px solid #dde0e4; }
.jws-yt-section-head h3 { margin:0; font-size:13px; font-weight:600; flex:1; }
.jws-yt-step { display:inline-flex; align-items:center; justify-content:center; width:21px; height:21px;
    background:#2271b1; color:#fff; border-radius:50%; font-size:11px; font-weight:700; flex-shrink:0; }
.jws-yt-section-body { padding:14px 16px; display:flex; flex-direction:column; gap:11px; }

/* ── Source pills ── */
.jws-yt-pills { display:flex; flex-wrap:wrap; gap:6px; }
.jws-yt-pill { display:inline-block; padding:5px 13px; border:1px solid #c3c4c7; border-radius:20px;
    cursor:pointer; font-size:12px; font-weight:500; color:#3c434a; transition:.15s; user-select:none; }
.jws-yt-pill:hover { border-color:#2271b1; color:#2271b1; }
.jws-yt-pill.active { background:#2271b1; border-color:#2271b1; color:#fff; }

/* ── Inputs ── */
.jws-yt-input { width:100%; padding:7px 10px; border:1px solid #c3c4c7; border-radius:5px; font-size:13px;
    background:#fff; box-sizing:border-box; transition:.15s; }
.jws-yt-input:focus { border-color:#2271b1; outline:none; box-shadow:0 0 0 2px rgba(34,113,177,.15); }
.jws-yt-input-tiny { width:70px !important; text-align:center; }
.jws-yt-input-search { max-width:200px; }
.jws-yt-textarea { resize:vertical; min-height:110px; font-family:monospace; font-size:12px; }
.jws-yt-select { padding:7px 28px 7px 10px; border:1px solid #c3c4c7; border-radius:5px; font-size:13px;
    background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23555'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance:none; cursor:pointer; }
.jws-yt-select-sm { width:auto; }

.jws-yt-api-row { display:flex; gap:8px; }
.jws-yt-api-row .jws-yt-input { flex:1; }
.jws-yt-inline-field { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.jws-yt-inline-field label { font-size:12px; color:#3c434a; white-space:nowrap; }
.jws-yt-options-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.jws-yt-field { display:flex; flex-direction:column; gap:4px; }
.jws-yt-field label { font-size:12px; color:#3c434a; font-weight:500; }

/* ── Toggle ── */
.jws-yt-toggle-row { display:flex; align-items:center; gap:10px; cursor:pointer; font-size:13px; }
.jws-yt-toggle-switch { position:relative; width:38px; height:22px; flex-shrink:0; }
.jws-yt-toggle-switch input { opacity:0; width:0; height:0; position:absolute; }
.jws-yt-toggle-slider { position:absolute; inset:0; background:#c3c4c7; border-radius:11px; transition:.2s; cursor:pointer; }
.jws-yt-toggle-slider:before { content:''; position:absolute; width:16px; height:16px; left:3px; top:3px;
    background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.25); }
.jws-yt-toggle-switch input:checked + .jws-yt-toggle-slider { background:#2271b1; }
.jws-yt-toggle-switch input:checked + .jws-yt-toggle-slider:before { transform:translateX(16px); }

/* ── TV Show box ── */
.jws-yt-tvshow-box { background:#f0f6fc; border:1px solid #c3dcf5; border-radius:6px; padding:12px; display:flex; flex-direction:column; gap:10px; }

/* ── Buttons ── */
.jws-yt-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:5px;
    font-size:13px; font-weight:500; cursor:pointer; border:1px solid transparent; transition:.15s; line-height:1.4; }
.jws-yt-btn .dashicons { font-size:15px; width:15px; height:15px; }
.jws-yt-btn-primary { background:#2271b1; color:#fff; border-color:#2271b1; }
.jws-yt-btn-primary:hover { background:#135e96; color:#fff; }
.jws-yt-btn-ghost { background:#fff; color:#3c434a; border-color:#c3c4c7; }
.jws-yt-btn-ghost:hover { border-color:#2271b1; color:#2271b1; }
.jws-yt-btn-danger { background:#d63638; color:#fff; border-color:#d63638; }
.jws-yt-btn-danger:hover { background:#b32d2e; }
.jws-yt-btn-sm { padding:5px 10px; font-size:12px; }
.jws-yt-btn:disabled, .jws-yt-btn[disabled] { opacity:.5; cursor:not-allowed; pointer-events:none; }

.jws-yt-section-actions { display:flex; align-items:center; gap:10px; padding:10px 0 4px; flex-wrap:wrap; }
.jws-yt-save-status { font-size:12px; color:#00a32a; }

/* ── Right panel ── */
.jws-yt-panel-right { background:#fff; border:1px solid #dde0e4; border-radius:8px; overflow:hidden; min-height:260px; }
.jws-yt-empty-state { display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:60px 20px; color:#8c8f94; text-align:center; gap:10px; }
.jws-yt-empty-state .dashicons { font-size:44px; width:44px; height:44px; }
.jws-yt-empty-state p { max-width:320px; font-size:13px; margin:0; line-height:1.6; }

.jws-yt-preview-header { display:flex; align-items:center; gap:10px; padding:11px 16px;
    border-bottom:1px solid #dde0e4; background:#f9f9fb; flex-wrap:wrap; }
.jws-yt-preview-title h3 { margin:0; font-size:14px; font-weight:600; display:flex; align-items:center; gap:6px; flex:1; }
.jws-yt-preview-toolbar { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }

/* ── Badges ── */
.jws-yt-badge { background:#2271b1; color:#fff; border-radius:12px; padding:2px 9px; font-size:11px; font-weight:600; }
.jws-yt-badge-green { background:#00a32a; }

/* ── Table ── */
#jws_yt_preview_table_wrap, #jws_yt_log_table_wrap { overflow-x:auto; }
#jws_yt_preview_table_wrap table, #jws_yt_log_table_wrap table { width:100%; border-collapse:collapse; font-size:13px; }
#jws_yt_preview_table_wrap th, #jws_yt_preview_table_wrap td,
#jws_yt_log_table_wrap th, #jws_yt_log_table_wrap td { padding:9px 12px; border-bottom:1px solid #f0f0f1; vertical-align:middle; }
#jws_yt_preview_table_wrap thead th, #jws_yt_log_table_wrap thead th { background:#f6f7f7; font-weight:600; color:#1d2327; }
#jws_yt_preview_table_wrap tbody tr:last-child td, #jws_yt_log_table_wrap tbody tr:last-child td { border-bottom:0; }
#jws_yt_preview_table_wrap tbody tr:hover td { background:#f8f9fa; }
.jws-yt-thumb { width:112px; height:63px; object-fit:cover; border-radius:4px; display:block; }
.jws-yt-row-checked td { background:#f0f6fc !important; }
.jws-yt-chk-cell { width:36px; text-align:center; }
.jws-yt-video-title { font-weight:500; }
.jws-yt-video-meta { font-size:11px; color:#8c8f94; margin-top:3px; }

/* ── Pagination ── */
.jws-yt-pagination { display:flex; gap:4px; align-items:center; padding:10px 14px; border-top:1px solid #f0f0f1; flex-wrap:wrap; }
.jws-yt-page-btn { min-width:30px; height:30px; padding:0 8px; border:1px solid #c3c4c7; background:#fff;
    border-radius:4px; cursor:pointer; font-size:12px; transition:.15s; }
.jws-yt-page-btn:hover:not([disabled]) { border-color:#2271b1; color:#2271b1; }
.jws-yt-page-btn.active { background:#2271b1; border-color:#2271b1; color:#fff; }
.jws-yt-page-btn[disabled] { opacity:.4; cursor:not-allowed; }
.jws-yt-page-info { font-size:12px; color:#8c8f94; margin-left:4px; }

/* ── Status ── */
.jws-yt-status-imported { color:#00a32a; font-weight:600; }
.jws-yt-status-skipped  { color:#dba617; font-weight:600; }
.jws-yt-status-error    { color:#d63638; font-weight:600; }

/* ── Misc ── */
.jws-yt-hint { font-size:12px; color:#8c8f94; margin:0; display:block; line-height:1.5; }

/* ── Thumbnail badge ── */
.jws-yt-thumb-wrap { position:relative; display:inline-block; }
.jws-yt-thumb-badge { position:absolute; bottom:4px; left:0; right:0; background:rgba(0,163,42,.88);
    color:#fff; font-size:10px; font-weight:700; text-align:center; padding:2px 4px;
    border-radius:0 0 4px 4px; letter-spacing:.3px; }
.jws-yt-row-imported td { opacity:.65; }
.jws-yt-row-imported.jws-yt-row-checked td { opacity:1; }
.jws-yt-badge-imported { background:#00a32a; font-size:10px; }

/* ── Sticky bar ── */
.jws-yt-sticky-bar { position:fixed; bottom:0; left:160px; right:0; background:#1d2327; color:#fff;
    padding:11px 24px; display:flex; align-items:center; justify-content:flex-end; gap:16px;
    z-index:9999; box-shadow:0 -2px 10px rgba(0,0,0,.35); }
.jws-yt-sticky-bar > span { font-size:13px; color:#ccd0d4; }
@media (max-width:782px) { .jws-yt-sticky-bar { left:0; } }
</style>

<script>
(function($) {
    var nonce        = '<?php echo esc_js( $nonce ); ?>';
    var ajaxUrl      = '<?php echo esc_js( $ajax_url ); ?>';
    var previewData  = [];
    var filteredData = [];
    var selectedIds  = {};
    var currentPage  = 1;
    var saveTimer    = null;

    var sourceHints = {
        channel_id:       '<?php echo esc_js( __( 'e.g. UCxxxxxxxxxxxxxxxxxxxxxx', 'streamvid' ) ); ?>',
        channel_username: '<?php echo esc_js( __( 'e.g. @ChannelName', 'streamvid' ) ); ?>',
        playlist_id:      '<?php echo esc_js( __( 'e.g. PLxxxxxxxxxxxxxxxxxxxxxx', 'streamvid' ) ); ?>',
        video_url:        '',
    };

    function currentSource() { return $('input[name="source"]:checked').val(); }

    // Init placeholder
    updateSourceHint( currentSource() );

    // ── Source pill switch ──
    $(document).on('change', 'input[name="source"]', function() {
        var src = $(this).val();
        var isUrl = (src === 'video_url');
        $('.jws-yt-pill').removeClass('active');
        $(this).closest('.jws-yt-pill').addClass('active');
        $('#yt_input_single').toggle(!isUrl);
        $('#yt_input_textarea').toggle(isUrl);
        $('#yt_max_results_wrap').toggle(!isUrl);
        updateSourceHint(src);
        triggerAutoSave();
    });

    function updateSourceHint(src) {
        $('#yt_source_value').attr('placeholder', sourceHints[src] || '');
        $('#yt_source_hint').text(sourceHints[src] || '');
    }

    // ── Post type ──
    $('#yt_post_type').on('change', function() {
        var isTv = $(this).val() === 'tv_shows';
        $('#yt_tvshow_box').toggle(isTv);
        if (!isTv) { $('#yt_playlist_mode').prop('checked', false); $('#yt_tvshow_name_row').hide(); }
        triggerAutoSave();
    });
    $('#yt_playlist_mode').on('change', function() {
        $('#yt_tvshow_name_row').toggle($(this).is(':checked'));
        triggerAutoSave();
    });

    // ── Auto-save ──
    function triggerAutoSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function() { saveSettings(false); }, 1500);
    }
    $('#yt_api_key,#yt_source_value,#yt_source_textarea,#yt_max_results,#yt_playlist_name').on('input', triggerAutoSave);
    $('#yt_post_type,#yt_post_status').on('change', triggerAutoSave);
    $('#yt_import_thumbnail,#yt_playlist_mode,#yt_import_category').on('change', triggerAutoSave);
    $('#yt_hide_imported').on('change', function() { if(previewData.length){ currentPage=1; applyFilter(); } });

    $('#jws_yt_save_btn').on('click', function() { saveSettings(true); });
    function saveSettings(show) {
        $.post(ajaxUrl, $.extend({ action:'jws_yt_save_settings', nonce:nonce }, collectParams()))
            .done(function(r) {
                if (r.success && show) {
                    $('#jws_yt_save_status').text('✓ <?php echo esc_js( __( 'Saved', 'streamvid' ) ); ?>').show();
                    setTimeout(function() { $('#jws_yt_save_status').fadeOut(); }, 2000);
                }
            });
    }

    // ── Test API ──
    $('#jws_yt_test_api_btn').on('click', function() {
        var key = $('#yt_api_key').val().trim();
        if (!key) { $('#jws_yt_test_result').css('color','#d63638').text('✗ <?php echo esc_js( __( 'Enter API Key first.', 'streamvid' ) ); ?>'); return; }
        var $b = $(this).prop('disabled', true).text('…');
        $.post(ajaxUrl, { action:'jws_yt_test_api', nonce:nonce, api_key:key })
            .done(function(r) {
                if (r.success) {
                    $('#jws_yt_test_result').css('color','#00a32a').text('✓ ' + r.data);
                    $('#api_key_status_icon').html('<span style="color:#00a32a;font-size:16px;line-height:1">●</span>');
                } else {
                    $('#jws_yt_test_result').css('color','#d63638').text('✗ ' + r.data);
                    $('#api_key_status_icon').html('<span style="color:#d63638;font-size:16px;line-height:1">●</span>');
                }
            })
            .fail(function() { $('#jws_yt_test_result').css('color','#d63638').text('✗ Request failed'); })
            .always(function() { $b.prop('disabled', false).text('<?php echo esc_js( __( 'Test', 'streamvid' ) ); ?>'); });
    });

    // ── Load Videos ──
    $('#jws_yt_preview_btn').on('click', function() {
        var p = collectParams();
        if (!validate(p)) return;
        setLoading(true);
        $('#jws_yt_preview_section,#jws_yt_log_section').hide();
        $('#jws_yt_empty_state').hide();

        $.post(ajaxUrl, $.extend({ action:'jws_yt_fetch_preview', nonce:nonce }, p))
            .done(function(r) {
                if (r.success && r.data.length) {
                    previewData = r.data;
                    selectedIds = {};
                    $.each(previewData, function(i,v) { selectedIds[v.video_id] = true; });
                    currentPage = 1;
                    applyFilter();
                    $('#jws_yt_preview_section').show();
                    updateBar();
                } else if (r.success) {
                    showNotice('error', '<?php echo esc_js( __( 'No videos found for this source.', 'streamvid' ) ); ?>');
                    $('#jws_yt_empty_state').show();
                } else {
                    showNotice('error', r.data || '<?php echo esc_js( __( 'Unknown error.', 'streamvid' ) ); ?>');
                    $('#jws_yt_empty_state').show();
                }
            })
            .fail(function() { showNotice('error','<?php echo esc_js( __( 'Request failed.', 'streamvid' ) ); ?>'); $('#jws_yt_empty_state').show(); })
            .always(function() { setLoading(false); });
    });

    // ── Filter ──
    $('#jws_yt_filter_text').on('input', function() { currentPage=1; applyFilter(); });
    $('#jws_yt_filter_perpage').on('change', function() { currentPage=1; applyFilter(); });
    $('#jws_yt_select_all_btn').on('click', function() { $.each(filteredData,function(i,v){selectedIds[v.video_id]=true;}); renderPage(); updateBar(); });
    $('#jws_yt_deselect_all_btn').on('click', function() { $.each(filteredData,function(i,v){selectedIds[v.video_id]=false;}); renderPage(); updateBar(); });

    $(document).on('change','.jws-yt-chk',function(){
        selectedIds[$(this).val()]=$(this).is(':checked');
        $(this).closest('tr').toggleClass('jws-yt-row-checked',!!selectedIds[$(this).val()]);
        updateBar();
    });
    $(document).on('change','#jws_yt_chk_all',function(){
        var c=$(this).is(':checked'), pp=parseInt($('#jws_yt_filter_perpage').val())||20;
        $.each(filteredData.slice((currentPage-1)*pp, currentPage*pp),function(i,v){selectedIds[v.video_id]=c;});
        renderPage(); updateBar();
    });

    function applyFilter() {
        var q=$('#jws_yt_filter_text').val().toLowerCase().trim();
        var hideImported=$('#yt_hide_imported').is(':checked');
        filteredData = previewData.slice();
        if(q) filteredData=$.grep(filteredData,function(v){return v.title.toLowerCase().indexOf(q)!==-1;});
        if(hideImported) filteredData=$.grep(filteredData,function(v){return !v.already_imported;});
        var importedCount=$.grep(previewData,function(v){return v.already_imported;}).length;
        renderPage();
        var badge=filteredData.length+'/'+previewData.length;
        if(importedCount>0) badge+=' <span class="jws-yt-badge jws-yt-badge-imported" title="<?php echo esc_js(__('Already imported','streamvid')); ?>">'+importedCount+' ✓</span>';
        $('#jws_yt_count_badge').html(badge);
    }

    function renderPage() {
        var pp=parseInt($('#jws_yt_filter_perpage').val())||20;
        var total=Math.max(1,Math.ceil(filteredData.length/pp));
        if(currentPage>total) currentPage=total;
        var start=(currentPage-1)*pp, items=filteredData.slice(start,start+pp);
        var allC=items.length>0 && $.grep(items,function(v){return !selectedIds[v.video_id];}).length===0;

        var html='<table><thead><tr>'
            +'<th class="jws-yt-chk-cell"><input type="checkbox" id="jws_yt_chk_all"'+(allC?' checked':'')+'></th>'
            +'<th style="width:120px"><?php echo esc_js(__('Thumbnail','streamvid')); ?></th>'
            +'<th><?php echo esc_js(__('Title','streamvid')); ?></th>'
            +'<th style="width:78px"><?php echo esc_js(__('Duration','streamvid')); ?></th>'
            +'<th style="width:90px"><?php echo esc_js(__('Date','streamvid')); ?></th>'
            +'</tr></thead><tbody>';

        if(!items.length) {
            html+='<tr><td colspan="5" style="text-align:center;padding:30px;color:#8c8f94"><?php echo esc_js(__('No videos match.','streamvid')); ?></td></tr>';
        } else {
        $.each(items,function(i,v){
                var chk=!!selectedIds[v.video_id];
                var importedBadge='';
                var editLink='';
                if(v.already_imported){
                    importedBadge='<span class="jws-yt-thumb-badge">✓ <?php echo esc_js(__('Imported','streamvid')); ?></span>';
                    if(v.existing_post_id) editLink=' <a href="post.php?post='+v.existing_post_id+'&action=edit" target="_blank" style="font-size:11px;color:#2271b1"><?php echo esc_js(__('Edit','streamvid')); ?> ↗</a>';
                }
                var thumbHtml='—';
                if(v.thumbnail){
                    thumbHtml='<div class="jws-yt-thumb-wrap"><a href="'+v.url+'" target="_blank"><img src="'+v.thumbnail+'" class="jws-yt-thumb"></a>'+importedBadge+'</div>';
                }
                html+='<tr class="'+(chk?'jws-yt-row-checked':'')+(v.already_imported?' jws-yt-row-imported':'')+'">'
                    +'<td class="jws-yt-chk-cell"><input type="checkbox" class="jws-yt-chk" value="'+v.video_id+'"'+(chk?' checked':'')+'>'
                    +'<td>'+thumbHtml+'</td>'
                    +'<td><div class="jws-yt-video-title">'+escHtml(v.title)+'</div>'
                    +'<div class="jws-yt-video-meta"><a href="'+v.url+'" target="_blank" style="color:#8c8f94">'+v.video_id+' ↗</a>'+editLink+'</div></td>'
                    +'<td>'+secToTime(v.duration)+'</td>'
                    +'<td>'+(v.published?v.published.substring(0,10):'—')+'</td>'
                    +'</tr>';
            });
        }
        html+='</tbody></table>';
        $('#jws_yt_preview_table_wrap').html(html);
        renderPag(total);
    }

    function renderPag(total) {
        if(total<=1){$('#jws_yt_pagination').html('');return;}
        var h='<button class="jws-yt-page-btn" data-page="'+Math.max(1,currentPage-1)+'"'+(currentPage===1?' disabled':'')+'>&lsaquo;</button>';
        var f=Math.max(1,currentPage-2),t=Math.min(total,currentPage+2);
        if(f>1){h+='<button class="jws-yt-page-btn" data-page="1">1</button>';if(f>2)h+='<span style="padding:0 3px">…</span>';}
        for(var p=f;p<=t;p++) h+='<button class="jws-yt-page-btn'+(p===currentPage?' active':'')+'" data-page="'+p+'">'+p+'</button>';
        if(t<total){if(t<total-1)h+='<span style="padding:0 3px">…</span>';h+='<button class="jws-yt-page-btn" data-page="'+total+'">'+total+'</button>';}
        h+='<button class="jws-yt-page-btn" data-page="'+Math.min(total,currentPage+1)+'"'+(currentPage===total?' disabled':'')+'>&rsaquo;</button>';
        h+='<span class="jws-yt-page-info">'+currentPage+' / '+total+'</span>';
        $('#jws_yt_pagination').html(h);
    }
    $(document).on('click','.jws-yt-page-btn',function(){currentPage=parseInt($(this).data('page'));renderPage();});

    function updateBar() {
        var n=0; $.each(selectedIds,function(id,v){if(v)n++;});
        if(n>0){
            $('#jws_yt_sticky_count').text(n+' <?php echo esc_js(__('video(s) selected','streamvid')); ?>');
            $('#jws_yt_import_btn_label').text('<?php echo esc_js(__('Import Selected','streamvid')); ?> ('+n+')');
            $('#jws_yt_selected_badge').text(n+' <?php echo esc_js(__('selected','streamvid')); ?>').show();
            $('#jws_yt_sticky_bar').show();
        } else {
            $('#jws_yt_sticky_bar').hide();
            $('#jws_yt_selected_badge').hide();
        }
    }

    // ── Import ──
    $('#jws_yt_import_btn').on('click',function(){
        var sel=[]; $.each(selectedIds,function(id,v){if(v)sel.push(id);});
        if(!sel.length) return;
        if(!confirm('<?php echo esc_js(__('Import','streamvid')); ?> '+sel.length+' <?php echo esc_js(__('video(s)?','streamvid')); ?>')) return;
        var p=collectParams(); p.selected_ids=sel;
        setLoading(true); $('#jws_yt_log_section').hide(); $(this).prop('disabled',true);
        $.post(ajaxUrl,$.extend({action:'jws_yt_run_import',nonce:nonce},p))
            .done(function(r){
                if(r.success){
                    renderLog(r.data); $('#jws_yt_log_section').show();
                    showNotice('success','<?php echo esc_js(__('Import complete —','streamvid')); ?> '+(r.data.total||0)+' <?php echo esc_js(__('items processed.','streamvid')); ?>');
                    $('html,body').animate({scrollTop:$('#jws_yt_log_section').offset().top-60},400);
                } else { showNotice('error',r.data||'<?php echo esc_js(__('Import failed.','streamvid')); ?>'); }
            })
            .fail(function(){showNotice('error','<?php echo esc_js(__('Request failed.','streamvid')); ?>');})
            .always(function(){setLoading(false);$('#jws_yt_import_btn').prop('disabled',false);});
    });

    $('#jws_yt_log_close').on('click',function(){
        $('#jws_yt_log_section').hide();
        if(previewData.length) $('#jws_yt_preview_section').show();
    });

    // ── Helpers ──
    function collectParams(){
        var src=currentSource();
        return {
            api_key:          $('#yt_api_key').val().trim(),
            source:           src,
            source_value:     src==='video_url'?$('#yt_source_textarea').val().trim():$('#yt_source_value').val().trim(),
            max_results:      $('#yt_max_results').val(),
            post_type:        $('#yt_post_type').val(),
            playlist_mode:    $('#yt_playlist_mode').is(':checked')?'1':'0',
            playlist_name:    $('#yt_playlist_name').val().trim(),
            post_status:      $('#yt_post_status').val(),
            import_thumbnail: $('#yt_import_thumbnail').is(':checked')?'1':'0',
            import_category:  $('#yt_import_category').is(':checked')?'1':'0',
        };
    }
    function validate(p){
        if(!p.api_key){showNotice('error','<?php echo esc_js(__('Please enter your YouTube API Key.','streamvid')); ?>');return false;}
        if(!p.source_value){
            showNotice('error', p.source==='video_url'
                ?'<?php echo esc_js(__('Paste at least one YouTube URL or video ID.','streamvid')); ?>'
                :'<?php echo esc_js(__('Enter the Channel or Playlist ID.','streamvid')); ?>');
            return false;
        }
        return true;
    }
    function setLoading(on){$('#jws_yt_spinner').toggleClass('is-active',on);$('#jws_yt_preview_btn').prop('disabled',on);}
    function showNotice(t,m){
        $('#jws-yt-notices').html('<div class="notice notice-'+(t==='success'?'success':'error')+' is-dismissible"><p>'+m+'</p></div>');
        $('html,body').animate({scrollTop:0},300);
    }
    function secToTime(s){if(!s)return '—';var h=Math.floor(s/3600),m=Math.floor((s%3600)/60),sec=s%60;return h?h+'h '+pad(m)+'m '+pad(sec)+'s':pad(m)+'m '+pad(sec)+'s';}
    function pad(n){return n<10?'0'+n:n;}
    function escHtml(s){return $('<div>').text(s).html();}
    function renderLog(data){
        var rows=data.log||[];
        var ex=data.show_id?'<p style="margin:12px 16px 4px"><strong><?php echo esc_js(__('TV Show:','streamvid')); ?></strong> <a href="'+data.show_url+'" target="_blank">#'+data.show_id+' ↗</a></p>':'';
        var h=ex+'<table><thead><tr><th>#</th><th><?php echo esc_js(__('Title','streamvid')); ?></th><th><?php echo esc_js(__('Status','streamvid')); ?></th><th><?php echo esc_js(__('Post','streamvid')); ?></th><th><?php echo esc_js(__('Note','streamvid')); ?></th></tr></thead><tbody>';
        $.each(rows,function(i,r){
            h+='<tr><td>'+(i+1)+'</td><td>'+escHtml(r.title||'')+'</td>'
                +'<td class="jws-yt-status-'+(r.status||'error')+'">'+escHtml(r.status||'')+'</td>'
                +'<td>'+(r.edit_url?'<a href="'+r.edit_url+'" target="_blank">#'+r.post_id+'</a>':(r.post_id?'#'+r.post_id:'—'))+'</td>'
                +'<td>'+escHtml(r.message||'')+'</td></tr>';
        });
        h+='</tbody></table>';
        $('#jws_yt_log_table_wrap').html(h);
    }
})(jQuery);
</script>
