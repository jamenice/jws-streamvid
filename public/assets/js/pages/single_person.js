(function( $ ) {
	'use strict';

    jQuery(document).ready(function($) {
       

          
        
          
          
        $('.history-nav a').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var slug = btn.data('slug');
            $('.history-nav a').removeClass('active');
            btn.addClass('active');
            
            if(slug != 'all') {
                $('.history-item').hide();
                $('.'+slug).show(); 
            }else {
                $('.history-item').show();
            }
            
            
            
        });  
           
       
    });

})( jQuery );
