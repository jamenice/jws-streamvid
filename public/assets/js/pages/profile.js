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
                           jwsThemeModule.show_notification(response.data[0].message,'error');
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
                           jwsThemeModule.show_notification(response.data[0].message,'error');
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

        
        /* Calling codes shared by several countries, and the one to pick
         * when a typed number only says the code; countries whose leading 0
         * is part of the number. Same as the theme's phone-login.js. */
        var MAIN_COUNTRY_FOR_CODE = {
            '1': 'US', '7': 'RU', '39': 'IT', '44': 'GB', '47': 'NO', '61': 'AU',
            '212': 'MA', '262': 'RE', '290': 'SH', '358': 'FI', '590': 'GP', '599': 'CW'
        };
        var KEEP_LEADING_ZERO = { IT: true, SM: true, VA: true };

        /* The number in E.164, or '' when it cannot be one: typed with "+"
         * or "00" it is already international; otherwise the trunk 0 goes
         * and the calling code is put in front (phone-login.js toE164). */
        function phoneToE164(input, iso, dial) {
            var trimmed = String(input || '').trim();
            var digits = trimmed.replace(/\D/g, '');
            var full;

            if (!digits) {
                return '';
            }
            if (trimmed.charAt(0) === '+') {
                full = digits;
            } else if (trimmed.indexOf('00') === 0) {
                full = digits.slice(2);
            } else {
                if (!KEEP_LEADING_ZERO[iso]) {
                    digits = digits.replace(/^0+/, '');
                }
                full = String(dial || '') + digits;
            }

            if (full.length < 7 || full.length > 15 || full.charAt(0) === '0') {
                return '';
            }
            return '+' + full;
        }

        /* The phone modal's country picker (profile_phone_field() in the
         * dashboard class), set up like the login form's SMS sign-in:
         * searchable with select2, started on the browser's country when the
         * page did not decide it, and moved to match a number typed with its
         * code. value() is the number to send, or null after telling the
         * person it is not one. */
        function profilePhonePicker($form) {
            var select = $form.find('select.jws-profile-phone-country')[0];
            var input = $form.find('#user_phone')[0];

            if (!select || !input) {
                return { value: function (raw) { return String(raw || '').trim(); } };
            }

            function setCountry(iso) {
                if (!iso || !select.querySelector('option[value="' + iso + '"]')) {
                    return false;
                }
                if (select.value !== iso) {
                    select.value = iso;
                    $(select).trigger('change.select2');
                }
                return true;
            }

            if (select.getAttribute('data-detect') === '1') {
                var languages = navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || ''];
                var byLanguage = {};
                try {
                    byLanguage = JSON.parse(select.getAttribute('data-lang-country') || '{}') || {};
                } catch (err) {}
                var found = false;
                for (var i = 0; i < languages.length && !found; i++) {
                    var region = String(languages[i]).split(/[-_]/)[1];
                    found = !!region && /^[A-Za-z]{2}$/.test(region) && setCountry(region.toUpperCase());
                }
                for (var j = 0; j < languages.length && !found; j++) {
                    found = setCountry(byLanguage[String(languages[j]).split(/[-_]/)[0].toLowerCase()]);
                }
            }

            input.addEventListener('input', function () {
                var value = input.value.trim();
                if (value.charAt(0) !== '+' && value.indexOf('00') !== 0) {
                    return;
                }
                var digits = value.replace(/\D/g, '');
                if (value.indexOf('00') === 0) {
                    digits = digits.slice(2);
                }
                var current = select.options[select.selectedIndex];
                var currentDial = current ? current.getAttribute('data-dial') : '';
                if (currentDial && digits.indexOf(currentDial) === 0) {
                    return;
                }
                for (var k = 0; k < select.options.length; k++) {
                    var dial = select.options[k].getAttribute('data-dial');
                    if (dial && digits.indexOf(dial) === 0) {
                        if (!setCountry(MAIN_COUNTRY_FOR_CODE[dial])) {
                            setCountry(select.options[k].value);
                        }
                        return;
                    }
                }
            });

            // The list opens inside the popup, under the picker: Magnific
            // Popup pulls focus back from anything outside it, which would
            // lock the search box.
            if ($.fn.select2) {
                $(select).select2({
                    dropdownParent: $(select).parent(),
                    dropdownAutoWidth: true,
                    width: 'style',
                    language: { noResults: function () { return '\u2014'; } },
                    templateSelection: function (state) {
                        var dial = state.element ? state.element.getAttribute('data-dial') : '';
                        return state.id ? state.id + ' +' + dial : state.text;
                    }
                }).on('select2:open', function () {
                    var search = select.parentNode.querySelector('.select2-search__field');
                    if (search) {
                        search.setAttribute('placeholder', select.getAttribute('data-search') || '');
                    }
                }).on('select2:select', function () {
                    input.focus();
                });
            }

            return {
                value: function (raw) {
                    var option = select.options[select.selectedIndex];
                    var phone = phoneToE164(raw, select.value, option ? option.getAttribute('data-dial') : '');
                    if (!phone) {
                        jwsThemeModule.show_notification(select.getAttribute('data-invalid'), 'error');
                        return null;
                    }
                    return phone;
                }
            };
        }

        function edit_profile() {  

           // The email, phone and password modals (form_profile() in the
           // dashboard class) all post edit_profile. A new email needs a
           // code sent to it, and a new phone needs one texted to it while
           // phone sign-in is on (send_*_change_code).
           // The phone modal's country picker: the number is sent in E.164,
           // with the picked country's calling code in front.
           var phonePicker = profilePhonePicker($('form.form-edit-phone'));

           function codeField($form, inputSel, boxSel, action, field, toValue) {
               var $input = $form.find(inputSel);
               var $box = $form.find(boxSel);
               if (!$input.length || !$box.length) {
                   return;
               }
               var $btn = $box.find('button[type="button"]');
               var $code = $box.find('input');
               var $note = $box.find('.fs-small');

               $btn.on('click', function () {
                   if ($btn.prop('disabled')) {
                       return;
                   }
                   if (!String($input.val() || '').trim()) {
                       $input.trigger('focus');
                       return;
                   }
                   var value = toValue ? toValue($input.val()) : $input.val();
                   if (value === null) {
                       $input.trigger('focus');
                       return;
                   }
                   if (!$btn.data('label')) {
                       $btn.data('label', $btn.text());
                   }
                   $btn.prop('disabled', true);

                   var data = {
                       action: action,
                       edit_profile_nonce: $form.find('[name="edit_profile_nonce"]').val()
                   };
                   data[field] = value;

                   $.post(jws_script.ajax_url, data).done(function (response) {
                       if (response && response.success) {
                           jwsThemeModule.show_notification(response.data.message, 'success');
                           // Test mode sends no SMS: keep its code in view.
                           if (response.data.test_code) {
                               $note.text(response.data.message);
                           }
                           $code.val('').trigger('focus');
                           var left = 60;
                           var timer = setInterval(function () {
                               left--;
                               $btn.text(left > 0 ? left + 's' : $btn.data('label'));
                               if (left <= 0) {
                                   clearInterval(timer);
                                   $btn.prop('disabled', false);
                               }
                           }, 1000);
                           $btn.text(left + 's');
                       } else {
                           var message = response && response.data && response.data[0] ? response.data[0].message : '';
                           jwsThemeModule.show_notification(message || 'The code could not be sent. Please try again.', 'error');
                           $btn.prop('disabled', false);
                       }
                   }).fail(function () {
                       jwsThemeModule.show_notification('There is a problem with the internet connection, please try again.', 'error');
                       $btn.prop('disabled', false);
                   });
               });
           }

           codeField($('form.form-edit-email'), '#user_email', '.jws-email-otp', 'jws_send_email_change_code', 'user_email');
           codeField($('form.form-edit-phone'), '#user_phone', '.jws-phone-otp', 'jws_send_phone_change_code', 'user_phone', phonePicker.value);
            
           $('form.form-edit-profile').on('submit' , function(e) { 
                    e.preventDefault();
                    var form = $(this);
                    var formData = new FormData(this);

                    if (form.hasClass('form-edit-phone')) {
                        var phone = phonePicker.value(formData.get('user_phone'));
                        if (phone === null) {
                            form.find('#user_phone').trigger('focus');
                            return;
                        }
                        formData.set('user_phone', phone);
                        formData.delete('phone_country');
                    }

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
