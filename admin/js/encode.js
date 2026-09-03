(function( $ ) {
	'use strict';

$(document).ready(function() {
		
        $('body').on('click', '.jws-media-encode', function(e) {
            e.preventDefault(); 
            
            const btn = $(this);
            let status = '';
    
            if(btn.hasClass('encoding')) return false;
            
            
            if(btn.hasClass('encoded')) {
                status = 'encoded';
                btn.text('Encode');
            }else {
               btn.text('Encoding');
            }
            
        
             $.ajax({
					url: jws_script.ajax_url,
					data: {
						action: 'encode_media_video',
						id: btn.data('id'),
                        status:status,
					},
					dataType: 'json',
					method: 'POST',
					success: function(response) {
					   if(status != 'encoded') {
    					    btn.addClass('encoding');
                            encode_items(btn); 
					   }else {
					        btn.removeClass('encoded'); 
                            btn.next('.percentage').remove(); 
                            btn.prev('.bag_encoded').remove(); 
                             
					   }     
					},
					error: function() {
						console.log('We cant remove product wishlist. Something wrong with AJAX response. Probably some PHP conflict.');
					},
					complete: function() {
					
					},
			});  
            
 
        });
        
       function automatic_encode() {
        
             $('.jws-media-encode').each(function(e){
               const btn = $(this);
               if(btn.hasClass('encoding')) {
                    
                    encode_items(btn); 
                    
               }
      
            });
            
       };
       
       automatic_encode();
       
       function encode_items(btn) {
            
           let status = '';
            
           btn.addClass('loading');
    
            if(!btn.hasClass('encoding')) {
                return false;      
            }
            
           status = 'encoding';
            
           btn.parent().append('<div class="percentage"></div>'); 
            
           setInterval(function () {
        
                 $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'encode_media_video',
    						id: btn.data('id'),
                            status:status,
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    			
    					   if(response.data.percentage == 100) {
    					      btn.parent().find('.percentage').html('<div class="success">Success</div>');
                              btn.text('Decode').removeClass('encoding').addClass('encoded');
    					   }else{
    					       btn.parent().find('.percentage').html('<div class="running" style="width:'+response.data.percentage+'%">'+response.data.percentage+'</div>');
    					   }  
    					   
    					     
    					},
    					error: function() {
    						console.log('We cant remove product wishlist. Something wrong with AJAX response. Probably some PHP conflict.');
    					},
    					complete: function() {
    						btn.removeClass('loading');
    					},
    			});
                
                
            }, 2000);   
        
       }
       
       function bunny_cdn() {
            $('body').on('click', '.jws-media-bunny', function(e) {
                e.preventDefault(); 
                
                const btn = $(this);
               
                btn.addClass('loading');
            
                 $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'bunny_media_video',
    						id: btn.data('id'),
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    					   console.log(response);
                           if(response.data.log.success) {
                             alert('Added a video to Bunny');
                             location.reload();
                           }    
    					},
    					error: function() {
    						console.log('error');
    					},
    					complete: function() {
    					   btn.removeClass('loading');
    					},
    			});  
                
     
            });
            
            $('body').on('click', '.jws-remove-bunny', function(e) {
                e.preventDefault(); 
                
                const btn = $(this);
               
                btn.addClass('loading');
            
                 $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'bunny_media_video_delete',
    						id: btn.data('id'),
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    					   console.log(response);    
                           if(response.data.log.success) {
                             alert('Video removed from Bunny');
                             location.reload();
                           }
    					},
    					error: function() {
    						console.log('error');
    					},
    					complete: function() {
    					   btn.removeClass('loading');
    					},
    			});  
                
     
            });
            
       } 
       
      bunny_cdn();
      
      
        function cloudflare_cdn() {
            $('body').on('click', '.jws-media-cloudflare', function(e) {
                e.preventDefault(); 
                
                const btn = $(this);
               
                btn.addClass('loading');
            
                 $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'cloudflare_upload_media_video',
    						id: btn.data('id'),
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    					   console.log(response);
                           if(response.success) {
                             alert('Added a video to Cloudflare');
                             location.reload();
                           } else {
                             alert(response.data[0].message);
                 
                           }    
    					},
    					error: function() {
    						console.log('error');
    					},
    					complete: function() {
    					   btn.removeClass('loading');
    					},
    			});  
                
     
            });
            
             $('body').on('click', '.jws-remove-cloudflare', function(e) {
                e.preventDefault(); 
                
                const btn = $(this);
               
                btn.addClass('loading');
            
                 $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'cloudflare_delete_media_video',
    						id: btn.data('id'),
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    					   console.log(response);
                           if(response.success) {
                             alert('Deleted a video on Cloudflare');
                            location.reload();
                           } else {
                             alert(response.data[0].message);
                 
                           }    
    					},
    					error: function() {
    						console.log('error');
    					},
    					complete: function() {
    					   btn.removeClass('loading');
    					},
    			});  
                
     
            });

            
       } 
       
      cloudflare_cdn();
        
        
});

})( jQuery );
