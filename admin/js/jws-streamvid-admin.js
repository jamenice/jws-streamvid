(function( $ ) {
	'use strict';

  $(document).ready(function() {
  
     $('body').on('click', '.remove-live-stream', function(e) {
        e.preventDefault(); 
        if (confirm('You want to delete all live stream data. This will delete videos on cloudflare.') != true) {
            return false;
        }
        const btn = $(this);
       
        btn.addClass('loading');
    
         $.ajax({
    			url: jws_script.ajax_url,
    			data: {
    				action: 'delete_live_data',
    				id: btn.data('id'),
    			},
    			dataType: 'json',
    			method: 'POST',
    			success: function(response) {
    			   console.log(response);
                   if(response.success) {
                     alert('Deleted a live vide data');
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
    
    $('body').on('click', '.start-live-stream', function(e) {
        e.preventDefault(); 
      
        const btn = $(this);
       
        btn.addClass('loading');
    
         $.ajax({
    			url: jws_script.ajax_url,
    			data: {
    				action: 'start_live_data_admin',
    				id: btn.data('id'),
    			},
    			dataType: 'json',
    			method: 'POST',
    			success: function(response) {
                   if(response.success) {
                     alert('Started a live vide data');
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
    
   function start_player($player_wap,$reload) {
              var $player = $player_wap.find('.jws_player'),  
              option = $player.data( 'player' ),
              player_start = $player.attr( 'id' );  
              
              if($reload) {
                videojs(player_start).dispose();
              }
              
              if($player.length) {
                    
                    var player = videojs(player_start , option);
                    
                    player.ready(function() { 
                     
                       this.hlsQualitySelector({
                           displayCurrentQuality: true,
                        });  
                    });
                    
                    return player;
                    
                   
                
                }
            
     }  
              
     if ( typeof videojs == 'function') {
            
         var player; 
         
        $('.videos_player').each(function(){
            
            var $this = $(this);
            player = start_player($this);
 
            
        });
       
    }
    
    
     function check_live_stream_status() {
            
               if($('[data-live-uid]').length) {
               var id = $('[data-live-uid]').data('live-uid');
               var live_status = 'not_live';
               var message = '';
               setInterval( function(){
                  $.ajax({
    					url: jws_script.ajax_url,
    					data: {
    						action: 'check_live_stream_status',
                            id: id,
    					},
    					dataType: 'json',
    					method: 'POST',
    					success: function(response) {
    			
                            if(response.success) {
                                
                                
                                
                                if(response.data.status == 'ready' && live_status != 'live') {
      
                                   live_status = 'live_2';
        
                                }
                                
                                
                                if(response.data.status == 'initializing') {
      
                                    live_status = 'live';
        
                                }
                                
                              
                                message = response.data.message;
                                
                                if(live_status == 'live_2') {
                                    
                                    if(response.data.status == 'disconnected') {
                                            $('.player-overlay').fadeIn( "fast" );
                                            $('.player-overlay .message').html(message); 
                                            window.location.reload();
                                       
                                    }
                                        
                                }
                              
                                
                                if(live_status == 'live') {
                                    
                                        $('.player-overlay').fadeIn( "fast" );
                                        $('.player-overlay .message').html(message); 
                                         
                                        if(response.data.status != 'initializing') {
                                    
                                            window.location.reload();
                                       
                                        }
                                        
                                } 	
                            }else {
                              
                            }
        
    					     
    					},
    					error: function() {
    						console.log('We cant remove product wishlist. Something wrong with AJAX response. Probably some PHP conflict.');
    					},
    					complete: function() {
    					
    					},
    			});
                    
                }, 3500 );
                
               } 
            
        }

        check_live_stream_status(); 
    


});

})( jQuery );

