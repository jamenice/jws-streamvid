(function( $ ) {
	'use strict';

  $(document).ready(function() {
  
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
    
    
});

})( jQuery );

