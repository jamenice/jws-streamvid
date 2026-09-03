<?php
if( ! defined( 'ABSPATH' ) ){
    exit;
}
$args = wp_parse_args( $args, array(
    'term_id' => 0,
) );

extract( $args );
?>
<div id="save-to-playlist" class="mfp-hide">

    <div class="form-head">
        <h5 class="title"><?php echo esc_html__('Save to playlist','jws_streamvid'); ?></h5>
    </div>

<div class="form-body">

    <div class="form-body-top">
            
         <form class="form form-search-playlist">
            <p class="field-item search-field">
                <input name="search" type="text" class="form-control" placeholder="<?php echo esc_attr__('Search playlist...','jws_streamvid'); ?>" onkeyup="search_playlist()" autocomplete="off">
                <button class="save-modal" type="submit"><i class="jws-icon-magnifying-glass"></i></button> 
            </p>                     
            <input type="hidden" name="term_id" value="0">
            <input type="hidden" name="action" value="save_to_playlist">
        </form>
        
        <div class="search-items jws-scrollbar" data-current-id="<?php echo esc_attr($term_id); ?>">
            <ul></ul>
        </div>
       
    </div>
    
    <div class="form-body-bottom">
        <button class="add-other-playlist add-border" data-modal-get-jws="#create-playlist"><i class="jws-icon-plus"></i><?php echo esc_html__('Create New Playlist','jws_streamvid'); ?></button>
        <div class="other-crelate"></div>
    </div>
    
</div>
<script type="text/javascript">
	function search_playlist() {

	    var filter, ul, text, found = 0;

	    filter 		= jQuery( ".form-search-playlist [name='search']" ).val().toUpperCase();
        var list = jQuery('#save-to-playlist').find('.search-items ul');
	    list.find( 'li h6' ).each(function(i){
	    	text = jQuery( this ).text().toUpperCase().indexOf(filter);
	    	if ( text > -1 ) {
	    		jQuery(this).closest( 'li' ).css( 'display', 'block' );
	    		found++;
	    	}else{
	    		jQuery(this).closest( 'li' ).css( 'display', 'none' );
	    	}
	    });

	    if( found == 0 ){
	    	var notFound = '<li class="not-found p"><?php esc_html_e( 'Not found', 'jws_streamvid' )?></li>';

	    	if( list.find( 'li.not-found' ).length == 0 ){
	    		list.append( notFound );	
	    	}
	    }else{
	    	list.find( 'li.not-found' ).remove();
	    }
	}
</script>
</div>
