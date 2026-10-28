(function( $ ) {
	'use strict';

    /*
     * Upload / edit video and Go Live forms.
     *
     * One AJAX submit for every form.form-videos (upload, edit, go live). The
     * video file goes up as soon as it is picked (add_media_videos) and its
     * attachment id is stored in input[name="videos_file"][data-video].
     *
     * jwsUpload (wp_localize_script): nonce, i18n.
     */

    var cfg = window.jwsUpload || {};
    var VIDEO_INPUT = 'input[name="videos_file"]';
    var i18n = cfg.i18n || {};
    var LOADER = '<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>';

    /* Server messages arrive escaped (esc_html__) or raw (WP_Error); decode,
       then escape once, so both read right and neither runs as HTML. */
    function notify(message, type) {
        if (message && typeof jwsThemeModule !== 'undefined') {
            jwsThemeModule.show_notification($('<div>').text($('<textarea>').html(message).text()).html(), type);
        }
    }

    /* WP_Error comes back as [ { code, message } ]; our own errors as { message }. */
    function errorText(response) {
        var data = response && response.data;
        if (data && data[0] && data[0].message) return data[0].message;
        if (data && data.message) return data.message;
        return i18n.error || '';
    }

    function addLoader($button) {
        if (!$button.find('.loader').length) {
            $button.append(LOADER);
        }
    }

  $(document).ready(function() {

        function form_upload_videos() {

                $(document).on('change' , VIDEO_INPUT , function(e) {
                        e.preventDefault();

                        var file = e.target.files && e.target.files[0];

                        if (!file) {
                            return;
                        }

                        var form = $(this).parents('form');
                        var $input = $(this);
                        var formData = new FormData();
                        formData.append('videos_file', file);
                        formData.append('action', 'add_media_videos');
                        formData.append('upload_nonce', cfg.nonce);

                        // A new file replaces the previous one only once it is uploaded.
                        $input.attr('data-video', '');

                        $.ajax({
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
                                        form.find('.upload-progress span').css('width', percentComplete + '%').attr('data-number', percentComplete + '%');
                                    }
                                }, false);
                                return xhr;
                            },
                            beforeSend: function () {
                                if (form.find('.upload-progress').length < 1) {
                                    $input.after("<div class='upload-progress'><span data-number=''></span></div>");
                                }
                                form.find('.upload-progress span').css('width', '0%').attr('data-number', '0%');
                                if (form.find('.upload-filename').length < 1) {
                                    form.find('.upload-progress').after("<div class='upload-filename'></div>");
                                }
                                form.find('.upload-filename').text((i18n.uploading || 'Uploading:') + ' ' + file.name);
                                addLoader(form.find('[type="submit"]'));
                                form.addClass('loading');
                            }
                        })
                        .done(function (response) {
                            if (response && response.success) {
                                notify(response.data.message, 'success');
                                $input.attr('data-video', response.data.attachment_id);
                                form.find('.upload-filename').text((i18n.uploaded || 'Uploaded:') + ' ' + file.name);
                            } else {
                                notify(errorText(response), 'error');
                                form.find('.upload-filename').text((i18n.upload_failed || 'Upload failed:') + ' ' + file.name);
                            }
                        })
                        .fail(function () {
                            notify(i18n.error, 'error');
                            form.find('.upload-filename').text((i18n.upload_failed || 'Upload failed:') + ' ' + file.name);
                        })
                        .always(function () {
                            form.removeClass('loading');
                        });
              });

                $(document).on('submit', 'form.form-videos', function (e) {

                    e.preventDefault();

                    var form = $(this);

                    if (form.hasClass('loading')) {
                        return false;
                    }

                    var formData = new FormData(this);

                    var video_type = formData.get('videos_type');

                    formData.delete('videos_file');

                    if (video_type == 'file') {
                        formData.delete('videos_url');
                        var attachment_id = form.find(VIDEO_INPUT).attr('data-video');
                        if (!attachment_id) {
                            notify(form.find('.golive-error--form').data('file') || i18n.no_file, 'error');
                            return false;
                        }
                        formData.append('attachment_id', attachment_id);
                    } else if (video_type != 'url') {
                        formData.delete('videos_url');
                    }

                    form.addClass('loading');
                    addLoader(form.find('[type="submit"]'));

                    $.ajax({
                        url: jws_script.ajax_url,
                        data: formData,
                        method: 'POST',
                        contentType: false,
                        processData: false,
                        success: function (response) {
                            if (response && response.success) {
                                notify(response.data.message, 'success');
                                if (response.data.output && window.location.href !== response.data.output) {
                                    window.location.href = response.data.output;
                                } else {
                                    location.reload();
                                }
                            } else {
                                form.removeClass('loading');
                                notify(errorText(response), 'error');
                            }
                        },
                        error: function () {
                            form.removeClass('loading');
                            notify(i18n.error, 'error');
                        }
                    });
                });

                $(document).on('click', '.edit-video', function (e) {
                    var btn = $(this);
                    var container = $('#edit-videos .form-body');

                    if (btn.hasClass('choosed')) {
                        return;
                    }

                    container.empty().append(LOADER).addClass('loading');

                    $.ajax({
                        url: jws_script.ajax_url,
                        data: { action: 'video_editor', upload_nonce: cfg.nonce, id: btn.data('id') },
                        type: 'POST',
                        dataType: 'json',
                        success: function (response) {
                            container.removeClass('loading');

                            if (!response || !response.success) {
                                container.empty();
                                notify(errorText(response), 'error');
                                return;
                            }

                            $('.edit-video').removeClass('choosed');
                            btn.addClass('choosed');
                            container.html(response.data);
                            if ($.fn.select2) {
                                container.find('select').select2({
                                    dropdownAutoWidth: true,
                                    minimumResultsForSearch: 10
                                });
                            }
                        },
                        error: function () {
                            container.removeClass('loading').empty();
                            notify(i18n.error, 'error');
                        }
                    });
                });

                $(document).on('click', '.delete-videos', function (e) {

                    e.preventDefault();

                    var btn = $(this);
                    var form = btn.parents('form');

                    if (form.hasClass('loading') || (i18n.delete_confirm && !window.confirm(i18n.delete_confirm))) {
                        return;
                    }

                    form.addClass('loading');
                    addLoader(btn);

                    $.ajax({
                        url: jws_script.ajax_url,
                        data: { action: 'delete_videos', upload_nonce: cfg.nonce, id: form.find('[name="post_id"]').val() },
                        method: 'POST',
                        success: function (response) {
                            if (response && response.success) {
                                notify(response.data.message, 'success');
                                location.reload();
                            } else {
                                form.removeClass('loading');
                                notify(errorText(response), 'error');
                            }
                        },
                        error: function () {
                            form.removeClass('loading');
                            notify(i18n.error, 'error');
                        }
                    });
                });

        }

        form_upload_videos();

  });


    /* ---------------------------------------------------------------------- */
    /* Upload / edit video form (.jws-upload-form)                             */
    /*                                                                         */
    /* Delegated: the edit form arrives over AJAX. The upload itself is the    */
    /* video file change handler above; the drop zone only feeds it.           */
    /* ---------------------------------------------------------------------- */

    var FORM = '.jws-upload-form';

    function setFieldError($field, message) {
        $field.find('.golive-error').first().text(message || '');
        $field.toggleClass('has-error', !!message);
    }

    // Character counters
    $(document).on('input', FORM + ' [data-counter]', function () {
        $(this).closest('.golive-field').find('.golive-count').text(this.value.length + '/' + this.getAttribute('maxlength'));
        if (this.value.trim()) {
            setFieldError($(this).closest('.golive-field'), '');
        }
    });

    // Video source: upload a file, or a link / embed
    $(document).on('change', FORM + ' input[name="videos_type"]', function () {
        var source = this.value;
        $(this).closest('form').find('.upload-panel').each(function () {
            var on = this.getAttribute('data-source') === source;
            $(this).prop('hidden', !on);
            $(this).find('textarea').prop('disabled', !on);
        });
    });

    // Drop zones: a click opens the picker by itself (each zone is a <label for>
    // its file input); a dropped file is put on the input here.
    $(document).on('dragenter dragover', FORM + ' .golive-drop', function (e) {
        e.preventDefault();
        $(this).addClass('is-dragging');
    });

    $(document).on('dragleave drop', FORM + ' .golive-drop', function (e) {
        e.preventDefault();
        $(this).removeClass('is-dragging');

        if (e.type !== 'drop') {
            return;
        }

        var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
        var input = $(this).find('.golive-drop__input').get(0);

        if (!files || !files.length || !input) {
            return;
        }

        try {
            var transfer = new DataTransfer();
            transfer.items.add(files[0]);
            input.files = transfer.files;
        } catch (err) {
            return;
        }

        // A native event, so the jQuery handlers (the video upload) run.
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Video file chosen: show its name in the zone while it uploads
    $(document).on('change', FORM + ' ' + VIDEO_INPUT, function () {
        var file = this.files && this.files[0];
        if (file) {
            $(this).closest('.golive-drop').addClass('has-file').find('.upload-drop__title').text(file.name);
        }
    });

    // Thumbnail preview
    $(document).on('change', FORM + ' .upload-drop--thumb .golive-drop__input', function () {
        var $drop = $(this).closest('.golive-drop');
        var $error = $drop.find('.golive-error');
        var file = this.files && this.files[0];
        var maxSize = parseInt($drop.data('max-size'), 10) || 0;

        setFieldError($drop, '');

        if (!file) {
            return;
        }

        if (!/^image\//.test(file.type)) {
            this.value = '';
            setFieldError($drop, $error.data('type'));
            return;
        }

        if (maxSize && file.size > maxSize) {
            this.value = '';
            setFieldError($drop, $error.data('size'));
            return;
        }

        $drop.find('.golive-drop__preview').prop('hidden', false).find('img').attr('src', URL.createObjectURL(file));
        $drop.find('.golive-drop__zone').prop('hidden', true);
    });

    // Cancel (popup)
    $(document).on('click', FORM + ' .upload-cancel', function () {
        if ($.magnificPopup) {
            $.magnificPopup.close();
        }
    });

    /*
     * Validation, in the capture phase: it runs before the AJAX submit handler
     * above (delegated, bubbling) and can stop it.
     */
    document.addEventListener('submit', function (e) {

        var form = e.target;

        if (!form.matches || !form.matches(FORM)) {
            return;
        }

        var $form = $(form);
        var messages = $form.find('.golive-error--form').data() || {};
        var $first = null;

        var $title = $form.find('.upload-title');
        if (!$.trim($title.val())) {
            setFieldError($title.closest('.golive-field'), messages.required);
            $first = $first || $title;
        }

        var source = $form.find('input[name="videos_type"]:checked').val() || $form.find('input[name="videos_type"]').val();

        if (source === 'file' && $form.find(VIDEO_INPUT).length && !$form.find(VIDEO_INPUT).attr('data-video')) {
            notify(messages.file, 'error');
            $first = $first || $form.find(VIDEO_INPUT);
        }

        var $url = $form.find('.upload-url');
        if (source === 'url' && $url.length && !$.trim($url.val())) {
            setFieldError($url.closest('.golive-field'), messages.url);
            $first = $first || $url;
        }

        if ($first) {
            e.preventDefault();
            e.stopPropagation();
            $first.trigger('focus');
        }
    }, true);

})( jQuery );
