(function( $ ) {
	'use strict';

      jQuery(document).ready(function($) {

         var movies = $('.favorites-page .owl-carousel');
         jwsThemeModule.owl_caousel_init(movies);
      
        
        function delete_playlist() { 
            
            $('form.form-delete-playlist').on('submit' , function(e) { 
                    e.preventDefault();
                    var form = $(this);
                    var formData = new FormData(this);
                    var btn =  form.find('.save-modal');
                   form.addClass('loading');
                    if(!btn.find('.loader').length) {
                        btn.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }
                  
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: formData,
                    method: 'POST',
                    contentType: false,
			        processData: false,
                    success: function(response) {
                        if(response.success) {
                            jwsThemeModule.show_notification(response.data.message,'success');
                           	if( response.data.redirect_url ){
                    			window.location.href = response.data.redirect_url;
                    		}
                        }else {
                           jwsThemeModule.show_notification(alert(response.data[0].message),'error');
                        }
                      
                    },
                    error: function() {
                        console.log('error');
                    },
                    complete: function() {form.removeClass('loading');},
                });
          });
            
        }
        delete_playlist();
        
        
        function create_edit_playlist() {
      
            
                  $(document).on('submit' , 'form.form-set-playlist' , function(e) { 
                            e.preventDefault();
                            var form = $(this);
                            var formData = new FormData(this);
                            var save = $(this).parents('#save-to-playlist').length;
                            var post_id = formData.get("post_id");
                            var file = $(this).find('[type="file"]');
                            var btn =  form.find('.save-modal');
                            form.addClass('loading');
                            if(!btn.find('.loader').length) {
                                btn.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                            }
                            $.ajax({
                            url: jws_script.ajax_url,
                            data: formData,
                            method: 'POST',
                            contentType: false,
					        processData: false,
                            success: function(response) {
                                if(response.success) {
                                   jwsThemeModule.show_notification(response.data.message,'success');
                                   location.reload();
                                   if(save) {
                                      callback_search_playlist(post_id); 
                                      $('.add-other-playlist').click(); 
                                    }
                                }else {
                                   jwsThemeModule.show_notification(response.data[0].message,'error');
                                }
                                
                            },
                            error: function() {
                                console.log('error');
                            },
                            complete: function() {form.removeClass('loading');},
                        });
                  });
                  
                  
              $( document ).on( 'change', '#playlist_image', function(e){
                	var input 				=	$(this);
                    var URL 				=	window.URL || window.webkitURL;
                    var imageURL 			=	'';
                    var files 				=	this.files;
                    var file;
            
                    if (URL) {
              
                        if (files && files.length) {
                            file = files[0];
            
                            if (/^image\/\w+$/.test(file.type)) {
                                imageURL = URL.createObjectURL(file);
            
                                var imgTag = '<img class="wp-post-image" src="'+imageURL+'">';
            
                                $(this).prev( '.file-thumbnail' )
                                .html( imgTag );
            
                            }else{
                            	input.attr( 'value', '' );
                            }
                        }
                    }
                });
                
              $(document).on('click','.playlist-control .set-thumbnail', function(e) { 
                   e.preventDefault();
                   var button = $(this); 
                   var post_id = button.data('post-id');
                   var term_id = button.data('term-id');
                   var data = {};
                   var background = $('.playlist-background');
                    data.action = 'set_image_playlist_from_post';
                    data.post_id = post_id;
                    data.term_id = term_id;
            
                    background.addClass('loading');
                    if(!background.find('.loader').length) {
                         background.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }  
                    
                    
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: data,
                    method: 'POST',
                    success: function(response) {
                        background.removeClass('loading');
                        if(response.success) {
                          if(!background.find('img').length) {
                              background.append('<img src="">');
                          }  
                          background.find('img').attr('src',response.data.thumbnail_url);
                          $('.file-thumbnail img').attr('src',response.data.thumbnail_url);

                          jwsThemeModule.show_notification(response.data.message,'success');
                          
                        }else {
                          jwsThemeModule.show_notification(response.data[0].message,'error');
                        }
                      
                    },
                    error: function() {
                        console.log('error');
                    },
                    complete: function() {},
                });
            }); 
            $(document).on('click','.playlist-control .remove-post-playlist', function(e) { 
                   e.preventDefault();
                   var button = $(this); 
                   var post_id = button.data('post-id');
                   var term_id = button.data('term-id');
                   var data = {};
                    data.action = 'remove_post_in_playlist';
                    data.post_id = post_id;
                    data.term_id = term_id;

                    $.ajax({
                    url: jws_script.ajax_url,
                    data: data,
                    method: 'POST',
                    success: function(response) {

                        if(response.success) {
                          jwsThemeModule.show_notification(response.data.message,'success');
                        }else {
                          jwsThemeModule.show_notification(response.data[0].message,'error'); 
                        }
                      
                    },
                    error: function() {
                        console.log('error');
                    },
                    complete: function() {},
                });
          })
            
        }
        
        create_edit_playlist();
        
        
        
        function add_item_playlist() { 
            
          $('#add-item-playlist form').on('submit' , function(e) { 
                   e.preventDefault();
                   var form = $(this); 
                   var formData = new FormData(this);
                    form.find('.save-modal').addClass('loading');
                    if(!form.find('.loader').length) {
                         form.find('.save-modal').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: formData,
                    method: 'POST',
                    contentType: false,
			        processData: false,
                    success: function(response) {
                        
                        if(response.success) {
                            form.next('.search-items').find('.videos-advanced-content').html(response.data);
                        }else {
                            jwsThemeModule.show_notification(response.data[0].message,'error');
                        }
                      
                    },
                    error: function() {
                        jwsThemeModule.show_notification(jwsThemeModule.show_notification_error(),'error');
                        
                    },
                    complete: function() {form.find('.save-modal').removeClass('loading');},
                });
          }); 
            
           $('[modal="#add-item-playlist"]').on('click' , function(e) {
 
               e.preventDefault();
               var $buttton = $(this).attr('modal');
               var term_id = $(this).data('term-id');
           
                $.magnificPopup.open({
                    items: {
                        src: $buttton,
                        type: 'inline'
                    },
                    tClose: 'close',
                    removalDelay: 360,    
                    callbacks: {
                        beforeOpen: function() {
                            this.st.mainClass = 'user-popup animation-popup';
                            $($buttton).find('[name="term_id"]').val(term_id);
                        },
                        open: function() {
                            
    
                        }
                    },
                });
   
          });
          
          $(document).on('click','.set-item-playlist', function(e) { 
                   e.preventDefault();
                   var button = $(this); 
                   var post_id = button.data('post-id');
                   var term_id = button.data('term-id');
                   var post_type = button.data('post-type');
                   var playlist_type = button.data('playlist-type');
                   var term_current_id = button.parents('[data-current-id]').data('current-id');
                   var data = {};
                    data.action = 'set_item_playlist';
                    data.post_id = post_id;
                    data.term_id = term_id;
                    data.post_type = post_type;
                    data.playlist_type = playlist_type;
                    button.addClass('loading');
                    if(!button.find('.loader').length) {
                         button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: data,
                    method: 'POST',
                    success: function(response) {
                        button.removeClass('loading');

                        if(response.success) {
                           if(term_id == term_current_id || typeof term_current_id === "undefined")  {
                            $('.profile-main .videos-advanced-content').html(response.data.output);
                           }
                           jwsThemeModule.show_notification(response.data.message,'success');
                       
                           if(response.data.type == 'save') {
                              button.addClass('checked');
                           }else {
                              button.removeClass('checked');
                           }
                        }else {
                           jwsThemeModule.show_notification(alert(response.data[0].message),'error');
                        }
                      
                    },
                    error: function() {
                        console.log('error');
                    },
                    complete: function() {},
                });
          }); 
            
        }
        
        add_item_playlist();
        
        function save_to_playlist() {
             var $modal = $('#save-to-playlist');  
          
             $(document).on('click','[data-modal-jws="#save-to-playlist"]', function(e) {  
                        
                      e.preventDefault();
                       var button = $(this); 
                       var post_id = button.data('post-id');
                       var playlist_type = button.data('playlist-type'); 
                       var post_type = button.data('post-type'); 
                        if(!$('.other-crelate form').length) {
                            var form_clone = $('#create-playlist .form-set-playlist').clone();   
                            form_clone.find('[name="post_id"]').val(post_id);
                            $('.other-crelate').html(form_clone);
                            $('.other-crelate').find('.select2').remove();
                            $('.other-crelate').find('select').select2({
            					dropdownAutoWidth: true,
                                minimumResultsForSearch: 10
            				});
             
                        };
                       callback_search_playlist(post_id,playlist_type,post_type);
             });
              
             $(document).on('click','.add-other-playlist', function(e) {  
   
                
                $modal.find('.other-crelate').slideToggle();
                
                $modal.find('.form-body-top').slideToggle();
                
             });
           
            
            
        }  
        save_to_playlist();  
        
        function callback_search_playlist(post_id,playlist_type,post_type) { 
            var form = $('.form-search-playlist');
            var data = {};
            data.action = 'get_save_to_playlist';
            data.post_id = post_id;
            data.playlist_type = playlist_type;
            data.post_type = post_type;
            form.next('.search-items').html('');
            form.addClass('loading');
            if(!form.find('.loader').length) {
                 form.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
            }
            $.ajax({
            url: jws_script.ajax_url,
            data: data,
            method: 'POST',
            success: function(response) {
                form.removeClass('loading');
                if(response.success) {
                   form.next('.search-items').html('<ul>'+response.data+'</ul>');
                }else {
                   jwsThemeModule.show_notification(response.data[0].message,'error');
                }
            },
            error: function() {
                console.log('error');
            },
            complete: function() {},
        });
            
        }

        
        function edit_profile() {  
            
           $('form.form-edit-profile').on('submit' , function(e) { 
                    e.preventDefault();
                    var form = $(this);
                    var formData = new FormData(this);

                    form.addClass('loading');
                    if(!form.find('.loader').length) {
                         form.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: formData,
                    method: 'POST',
                    contentType: false,
			        processData: false,
                    success: function(response) {
                        if(response.success) {
                          jwsThemeModule.show_notification(response.data.message,'success');
                          location.reload();
                        }else {
                           jwsThemeModule.show_notification(response.data[0].message,'error');
                        }
                    },
                    error: function() {
                      jwsThemeModule.show_notification('There is a problem with the internet connection, please try again.','error');
                    },
                    complete: function() {form.removeClass('loading');},
                });
          });
            
        }
        
        edit_profile();
        
        
        
        
        function edit_personal() { 
            
            
            $( document ).on( 'change', '#user_avatar', function(e){
                	var input 				=	$(this);
                    var URL 				=	window.URL || window.webkitURL;
                    var imageURL 			=	'';
                    var files 				=	this.files;
                    var file;
         
                    if (URL) {
              
                        if (files && files.length) {
                            file = files[0];
            
                            if (/^image\/\w+$/.test(file.type)) {
                                imageURL = URL.createObjectURL(file);
            
                                var imgTag = '<img class="wp-post-image" src="'+imageURL+'">';
            
                                $(this).prev( '.user-avatar' )
                                .html( imgTag );
            
                            }else{
                            	input.attr( 'value', '' );
                            }
                        }
                    }
                });
            
           $('form.form-edit-personal').on('submit' , function(e) { 
                    e.preventDefault();
                 
                    var form = $(this);
                    var formData = new FormData(this);

                    form.addClass('loading');
                    if(!form.find('.loader').length) {
                         form.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                    }
                  
                    $.ajax({
                    url: jws_script.ajax_url,
                    data: formData,
                    method: 'POST',
                    contentType: false,
			        processData: false,
                    success: function(response) {
                        if(response.success) {
                          jwsThemeModule.show_notification(response.data.message,'success');
                          location.reload();
                        }else {
                           jwsThemeModule.show_notification(response.data[0].message,'error');
                        }
                    },
                    error: function() {
                       jwsThemeModule.show_notification('There is a problem with the internet connection, please try again.','error');
                    },
                    complete: function() {form.removeClass('loading');},
                });
          });
            
        }
        
        edit_personal();  
 
    });

})( jQuery );
