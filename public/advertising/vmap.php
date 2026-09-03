<?php 
header('Content-Type: application/xml; charset=utf-8');

extract( $args );

ob_start();



?>
<?php printf(
    '<vmap:VMAP xmlns:vmap="%s" version="%s">',
    esc_url( 'http://www.iab.net/videosuite/vmap' ),
    esc_attr( '1.0' )
)?>

<?php


if($preroll) {

  foreach($preroll['ads_tag'] as $key => $value) {
    
    ?>
     
    <vmap:AdBreak timeOffset="start" breakType="linear" breakId="preroll_<?php echo esc_attr( $key ); ?>">
    <vmap:AdSource id="preroll-ad-<?php echo esc_attr( $key ); ?>" allowMultipleAds="false" followRedirects="true">
    <vmap:AdTagURI templateType="vast3">
    <![CDATA[ <?php echo esc_url_raw( $value ); ?> ]]>
    </vmap:AdTagURI>
    </vmap:AdSource>
    </vmap:AdBreak>
    
    
    <?php
    
  }
    
}

if($midroll) {

  foreach($midroll['ads_tag'] as $key => $value) {
    
    $time_offset  =  isset($midroll['ads_time_offset'][$key]) && !empty($midroll['ads_time_offset'][$key]) ? $midroll['ads_time_offset'][$key] : '00:00:00';
    
    ?>
     
    <vmap:AdBreak timeOffset="<?php echo esc_attr( $time_offset ); ?>" breakType="linear" breakId="midroll_<?php echo esc_attr( $key ); ?>">
    <vmap:AdSource id="preroll-ad-<?php echo esc_attr( $key ); ?>" allowMultipleAds="false" followRedirects="true">
    <vmap:AdTagURI templateType="vast3">
    <![CDATA[ <?php echo esc_url_raw( $value ); ?> ]]>
    </vmap:AdTagURI>
    </vmap:AdSource>
    </vmap:AdBreak>
    
    
    <?php
    
  }
    
}

if($postroll) {

  foreach($postroll['ads_tag'] as $key => $value) {
    

    ?>
     
    <vmap:AdBreak timeOffset="end" breakType="linear" breakId="postroll_<?php echo esc_attr( $key ); ?>">
    <vmap:AdSource id="postroll-ad-<?php echo esc_attr( $key ); ?>" allowMultipleAds="false" followRedirects="true">
    <vmap:AdTagURI templateType="vast3">
    <![CDATA[ <?php echo esc_url_raw( $value ); ?> ]]>
    </vmap:AdTagURI>
    </vmap:AdSource>
    </vmap:AdBreak>
    
    
    <?php
    
  }
    
}

?>


</vmap:VMAP>
<?php

$output = ob_get_clean();

echo $output;