(function( $ ) {
	'use strict';

  $(document).ready(function() {
    
    

        function form_upload_videos() {

               var attachment_id = 0;               
                
               
                $(document).on('change' , '#video-file' , function(e) { 
                        e.preventDefault();
                        var form = $(this).parents('form');
                        var formData = new FormData();
                        formData.append('videos_file', e.target.files[0]);
                        formData.append('action', 'add_media_videos');
                    
                        var jqxhr = $.ajax({
                            
                            url: jws_script.ajax_url,
                            data: formData,
                            method: 'POST',
                            processData: false,
                            contentType: false,
                            xhr: function () {
                            var xhr = new XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function (event) {
                                if (event.lengthComputable) {
                                    var percentComplete = Math.round(event.loaded / event.total * 100);
                                    form.find('.upload-progress span').css('width',percentComplete + '%').attr('data-number',percentComplete + '%');
                                }
                            }, false);
                            return xhr;
                        },beforeSend: function( jqXHR ) {
                            if(form.find('.upload-progress').length < 1) {
                              form.find('#video-file').after( "<div class='upload-progress'><span data-number=''></span></div>" );  
                            }  
                            if(!form.find('.loader').length) {
                                 form.find('[type="submit"]').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                            }
                            form.addClass('loading');
            			}
                       })
                      .done(function( response, textStatus, jqXHR) {
                            if(response.success) {
                              jwsThemeModule.show_notification(response.data.message,'success');
                              attachment_id = response.data.attachment_id; 
                              form.find('#video-file').attr('data-video',attachment_id);
                            
                            }else {
                              jwsThemeModule.show_notification(response.data[0].message,'error');
                            }
                              form.removeClass('loading');
                      })
                      .fail(function() {
                           console.log('error');
                      })
                      .always(function() {
                            
                      });

                    
              }) 
                
                
               $(document).on('change','#video-type',function(e){
                 var video_type = $(this).val(); 
                 var form =  $(this).parents('form'); 
                 form.find('#video-url , #video-file').parent('.field-item').addClass('hidden');
                 form.find('#video-'+video_type+'').parent('.field-item').removeClass('hidden');
               });
               
               $( document ).on( 'change', '.video-thumbnail', function(e){
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
                    if(files.length < 1) {
                        $(this).prev( '.file-thumbnail' ).html('');
                    }
                });
               
               
                
                
               $(document).on('submit' , 'form.form-videos' , function(e) { 
                
                        e.preventDefault();
                        
                        var form = $(this);
           
                        var formData = new FormData(this);
                
                        var video_type = formData.get('videos_type');
                        form.addClass('loading');
                        if(!form.find('.loader').length) {
                             form.find('[type="submit"]').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                        }
                      
                        formData.delete('videos_file');
                     
                        if(video_type == 'file') {
                             formData.delete('videos_url');
                            attachment_id = $(this).find('#video-file').data('video'); 
                            if(!attachment_id) {
                                 form.removeClass('loading');   
                                 jwsThemeModule.show_notification('Video file has not been uploaded yet','error');
                                 return false;
                            }else {
                               formData.append('attachment_id', attachment_id); 
                            } 
                        } else if(video_type != 'url') {
                            formData.delete('videos_url');
                        }
          
                   
                        $.ajax({
                            url: jws_script.ajax_url,
                            data: formData,
                            method: 'POST',
                            contentType: false,
        			        processData: false,
                            success: function(response) {
                                form.removeClass('loading');
                                if(response.success) {
                                   jwsThemeModule.show_notification(response.data.message,'success');
                                   if(response.data.output) {
        								if(window.location.href == response.data.output) {
        										location.reload();
        								} else {
        										window.location.href = response.data.output;
        								}
    								} else {
        								location.reload();
                    			   }
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
              
              $(document).on('click' , '.edit-video' , function(e) {  
                  var btn = $(this);
                  var id = btn.data('id');
                  var data = {}; 
                  var container = $('#edit-videos .form-body');
                  data.action = 'video_editor';
                  data.id = id; 
                  if(!btn.hasClass('choosed')) {
                      container.empty();  
                      container.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>').addClass('loading');    
                      $.ajax({
                            url: jws_script.ajax_url,
                            data:data,
                            type: 'POST',
                            dataType: 'json',
                            success: function(response) {
                                $('.edit-video').removeClass('choosed');
                                btn.addClass('choosed');
                                container.html(response.data).removeClass('loading');
                                container.find('select').select2({
                					dropdownAutoWidth: true,
                                    minimumResultsForSearch: 10
                				});
                                if(response.success) {
                                  jwsThemeModule.show_notification(response.data.message,'success');
                                }else {
                                  jwsThemeModule.show_notification(response.data[0].message,'error');
                                }
                            },
                            error: function($err) {
                                console.log($err);
                            },
                            complete: function() {},
                        });
                    
                    }
                
              });
              
              $(document).on('click' , '.delete-videos' , function(e) {
                
                        e.preventDefault();
                        
                        var btn = $(this);
                        var form = btn.parents('form');
           
                        var formData = {};
                        formData.action = 'delete_videos';
                        formData.id = form.find('[name="post_id"]').val();
                
                        form.addClass('loading');
                        if(!btn.find('.loader').length) {
                             btn.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');    
                        }
                    
                        $.ajax({
                            url: jws_script.ajax_url,
                            data: formData,
                            method: 'POST',
                            success: function(response) {
                               
                              
                                if(response.success) {
                                  jwsThemeModule.show_notification(response.data.message,'success');
                                    location.reload();
                                }else {
                                  jwsThemeModule.show_notification(response.data[0].message,'error');
                                }
                            },
                            error: function() {
                                console.log('error');
                            },
                            complete: function() { form.removeClass('loading');},
                        });
                    
                    
              });
 
        } 
        
         
        form_upload_videos(); 

  

	 });

})( jQuery );
