<?php
header('Content-Type: application/xml; charset=utf-8');

extract( $args );

?>
<VAST version="3.0">
    <?php printf( '<Ad id="%s">', esc_attr( $ads_id ) ); ?>
        <InLine>
            <AdSystem> <?php echo esc_html( $ads_system ); ?> </AdSystem>
            <AdTitle> <?php echo esc_html( $ads_title ); ?> </AdTitle>

            <?php if( $ads_description ): ?>
            <Description> <?php echo esc_html( $ads_description ); ?> </Description>
            <?php endif?>
            
            <Creatives>
                <Creative>
                    <?php printf(
                        '<Linear%s>',
                        $ads_skippable ? ' skipoffset="'.esc_attr( $ads_skippable ).'"' : ''
                    );?>

                        <?php if( $ads_duration ): ?>

                        <Duration> <?php echo esc_html( $ads_duration ); ?> </Duration>

                        <?php endif;?>

                        <?php if( $ads_target_url ): ?>
                            <VideoClicks>
                                <ClickThrough>
                                    <![CDATA[ <?php echo esc_url( $ads_target_url ); ?> ]]>
                                </ClickThrough>
                            </VideoClicks>
                        <?php endif;?>
                        
                        <?php
                        $is_video_url = ( $ads_video_source === 'url' && ! empty( $ads_video_url ) );

                        if( $is_video_url ) {
                            $video_id     = 0;
                            $video_src    = $ads_video_url;
                            $video_mime   = 'video/mp4';
                            $video_width  = '';
                            $video_height = '';
                        } elseif( $ads_video ) {
                            if( ! function_exists( 'wp_read_video_metadata' ) ){
                                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                            }
                            $metadata     = wp_read_video_metadata( get_attached_file( $ads_video ) );
                            $video_id     = $ads_video;
                            $video_src    = wp_get_attachment_url( $ads_video );
                            $video_mime   = get_post_mime_type( $ads_video );
                            $video_width  = $metadata['width'];
                            $video_height = $metadata['height'];
                        }
                        ?>

                        <?php if( $is_video_url || $ads_video ): ?>
                            <MediaFiles>

                                <?php printf(
                                    '<MediaFile id="%s" delivery="progressive" type="%s" width="%s" height="%s" bitrate="%s" scalable="true" maintainAspectRatio="true">',
                                    esc_attr( $video_id ),
                                    esc_attr( $video_mime ),
                                    esc_attr( $video_width ),
                                    esc_attr( $video_height ),
                                    esc_attr( '720' )
                                );?>
                                    <![CDATA[ <?php echo esc_url( $video_src ); ?> ]]>
                                </MediaFile>

                            </MediaFiles>
                        <?php endif;?>
                    </Linear>
                </Creative>
            </Creatives>
        </InLine>
    </Ad>
</VAST>