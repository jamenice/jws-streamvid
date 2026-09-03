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
                <Creative sequence="1">
                    <NonLinearAds>

                        <?php 
                        
                          $image_id = isset($ads_banner_image['ads_banner_image']) ? $ads_banner_image['ads_banner_image'] : false;
                          
                        
                           $image_ads = wp_get_attachment_image_src( $image_id, 'full' );
                            
                            $ads_image_params['image'] = array(
                                'url'       =>  $image_ads[0],
                                'width'     =>  $image_ads[1],
                                'height'     =>  $image_ads[2],
                                'position'  =>  $ads_banner_image['ads_banner_position']
                            );  
                           
                        
                        
                        
                        printf(
                            '<NonLinear apiFramework="VPAID" width="%s" height="%s" id="overlay-1">',
                            '0',
                            '0'
                        );?>

                            <AdParameters>
                                <![CDATA[ <?php echo wp_json_encode( $ads_image_params ); ?> ]]>
                            </AdParameters>

                            <StaticResource creativeType="application/javascript">
                                <![CDATA[ <?php echo esc_url( $scripts_url ); ?> ]]>
                            </StaticResource>

                            <?php if( $ads_target_url ): ?>
                                <NonLinearClickThrough>
                                    <![CDATA[ <?php echo esc_url( $ads_target_url ); ?> ]]>
                                </NonLinearClickThrough>
                            <?php endif;?>

                        </NonLinear>
                    </NonLinearAds>
                </Creative>
            </Creatives>
        </InLine>
    </Ad>
</VAST>
