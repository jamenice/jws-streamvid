(function ($) {
    'use strict';

    $(document).ready(function () {



        function video_filter() {
            $(document).on('change', '.post-select-filter select', function () {
                var $form = $(this).parents('form');
                var $select = $(this);

                if ($select.hasClass('cat_change')) {
                    var archiveUrl = $select.find(':selected').data('url');
                    var taxonomy = $select.attr('name') || $select.data('taxonomy') || 'genres';
                    var slug = $select.val();

                    if (!slug) {
                        $form.attr('action', archiveUrl);
                        $form.find('input[name="' + taxonomy + '"]').remove();
                    } else {
                        $form.attr('action', archiveUrl);
                        $form.find('input[name="' + taxonomy + '"]').remove();
                        $('<input>').attr({
                            type: 'hidden',
                            name: taxonomy,
                            value: slug
                        }).appendTo($form);
                    }
                }

                $form.submit();
            });
        }

        video_filter();


        function check_live_stream_status() {

            if ($('[data-live-uid]').length) {
                var id = $('[data-live-uid]').data('live-uid');
                var live_status = 'not_live';
                var message = '';
                setInterval(function () {
                    $.ajax({
                        url: jws_script.ajax_url,
                        data: {
                            action: 'check_live_stream_status',
                            id: id,
                        },
                        dataType: 'json',
                        method: 'POST',
                        success: function (response) {

                            if (response.success) {



                                if (response.data.status == 'ready' && live_status != 'live') {

                                    live_status = 'live_2';

                                }


                                if (response.data.status == 'initializing') {

                                    live_status = 'live';

                                }


                                message = response.data.message;

                                if (live_status == 'live_2') {

                                    if (response.data.status == 'disconnected') {
                                        $('.player-overlay').fadeIn("fast");
                                        $('.player-overlay .message').html(message);
                                        window.location.reload();

                                    }

                                }


                                if (live_status == 'live') {

                                    $('.player-overlay').fadeIn("fast");
                                    $('.player-overlay .message').html(message);

                                    if (response.data.status != 'initializing') {

                                        window.location.reload();

                                    }

                                }
                            } else {

                            }


                        },
                        error: function () {
                            console.log('We cant remove product wishlist. Something wrong with AJAX response. Probably some PHP conflict.');
                        },
                        complete: function () {

                        },
                    });

                }, 3500);

            }

        }

        check_live_stream_status();



        $(document).on('click', '[data-modal-jws]', function (e) {
            e.preventDefault();

            var popupId = $(this).data('modal-jws');

            var popupClass = popupId.replace('#', 'mfp-');


            $.magnificPopup.open({
                items: {
                    src: popupId,
                    type: 'inline'
                },
                removalDelay: 360,
                tClose: 'close',
                callbacks: {
                    beforeOpen: function () {
                        this.st.mainClass = 'user-popup animation-popup ' + popupClass;
                    }
                }
            });
        });

        $('.cancel-modal').on('click', function (e) {

            $.magnificPopup.close();

        });



    });

})(jQuery);
