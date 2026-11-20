/**
 * Go Live form (#upload-videos-live) — the "Go live" popup and the Go Live
 * page template share this markup.
 *
 * Only the interaction lives here: counters, the schedule reveal,
 * the thumbnail drop zone and validation. Sending the form is still the
 * generic `form.form-videos` handler in upload_videos.js; the submit handler
 * below is bound on the form itself, so it runs before that delegated one and
 * can stop it when something is missing.
 */
(function ($) {
    'use strict';

    $(function () {

        var $forms = $('.golive-form');

        if (!$forms.length) {
            return;
        }

        /* Local time in the format datetime-local wants: YYYY-MM-DDTHH:MM. */
        function localNow() {
            var d = new Date();
            d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
            return d.toISOString().slice(0, 16);
        }

        function setError($form, id, message) {
            var $error = $form.find('#' + id);
            $error.text(message || '');
            $error.closest('.golive-field, .golive-drop').toggleClass('has-error', !!message);
        }

        $forms.each(function () {

            var $form = $(this);
            var $messages = $form.find('.golive-error--form');

            /* Character counters */
            $form.find('[data-counter]').on('input', function () {
                var max = this.getAttribute('maxlength');
                $form.find(this.getAttribute('data-counter')).text(this.value.length + '/' + max);
            });

            $form.find('#golive-name').on('input', function () {
                if ($.trim(this.value)) {
                    setError($form, 'golive-name-error', '');
                }
            });

            /*
             * The Schedule option reveals its input. The hidden input is
             * disabled rather than cleared, so the browser leaves it out of the
             * request and start_stream() never sees an empty date.
             */
            $form.find('[data-toggle-target]').on('change', function () {

                var $target = $form.find(this.getAttribute('data-toggle-target'));
                var show = this.getAttribute('data-toggle-show') === '1';

                $target.prop('hidden', !show);
                $target.find('input').prop('disabled', !show);

                if (show) {
                    var $input = $target.find('input');

                    if ($input.attr('type') === 'datetime-local') {
                        $input.attr('min', localNow());
                    }

                    $input.trigger('focus');
                } else {
                    $target.find('.golive-error').text('');
                    $target.removeClass('has-error');
                }
            });

            /* Thumbnail drop zone */
            var $drop = $form.find('.golive-drop');
            var input = $drop.find('.golive-drop__input').get(0);
            var maxSize = parseInt($drop.data('max-size'), 10) || 0;
            var $preview = $drop.find('.golive-drop__preview');
            var $zone = $drop.find('.golive-drop__zone');
            var $thumbError = $drop.find('.golive-error');
            var objectUrl = null;

            function clearThumb() {
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                    objectUrl = null;
                }
                input.value = '';
                $preview.prop('hidden', true).find('img').attr('src', '');
                $zone.prop('hidden', false);
            }

            function showThumb(file) {

                setError($form, 'golive-thumbnail-error', '');

                if (!file) {
                    clearThumb();
                    return;
                }

                if (!/^image\//.test(file.type)) {
                    clearThumb();
                    setError($form, 'golive-thumbnail-error', $thumbError.data('type'));
                    return;
                }

                if (maxSize && file.size > maxSize) {
                    clearThumb();
                    setError($form, 'golive-thumbnail-error', $thumbError.data('size'));
                    return;
                }

                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }

                objectUrl = URL.createObjectURL(file);
                $preview.find('img').attr('src', objectUrl);
                $preview.prop('hidden', false);
                $zone.prop('hidden', true);
            }

            $(input).on('change', function () {
                showThumb(this.files && this.files[0]);
            });

            $drop.find('.golive-drop__remove').on('click', function () {
                clearThumb();
            });

            $drop.on('dragenter dragover', function (e) {
                e.preventDefault();
                $drop.addClass('is-dragging');
            }).on('dragleave dragend', function (e) {
                if (!e.relatedTarget || !$drop.get(0).contains(e.relatedTarget)) {
                    $drop.removeClass('is-dragging');
                }
            }).on('drop', function (e) {

                e.preventDefault();
                $drop.removeClass('is-dragging');

                var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;

                if (!files || !files.length) {
                    return;
                }

                /* Put the dropped file on the real input, so FormData sends it
                   under video_thumbnail exactly like a picked one. */
                try {
                    var transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    input.files = transfer.files;
                } catch (err) {
                    return;
                }

                showThumb(files[0]);
            });

            /* Validation — runs before the generic AJAX submit */
            $form.on('submit', function (e) {

                var $first = null;

                var $name = $form.find('#golive-name');

                if (!$.trim($name.val())) {
                    setError($form, 'golive-name-error', $messages.data('required'));
                    $first = $first || $name;
                }

                var $date = $form.find('#golive-start-date');

                if (!$date.prop('disabled')) {
                    if (!$date.val() || $date.val() < localNow()) {
                        setError($form, 'golive-start-date-error', $messages.data('date'));
                        $first = $first || $date;
                    } else {
                        setError($form, 'golive-start-date-error', '');
                    }
                }

                if ($first) {
                    e.preventDefault();
                    e.stopPropagation();
                    $first.trigger('focus');
                    return false;
                }
            });

            /* Popup only */
            $form.find('.golive-cancel').on('click', function () {
                if ($.magnificPopup) {
                    $.magnificPopup.close();
                }
            });
        });
    });

})(jQuery);
