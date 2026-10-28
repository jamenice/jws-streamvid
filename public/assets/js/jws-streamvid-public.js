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


        $(document).on('click', '[data-modal-jws]', function (e) {
            e.preventDefault();

            var popupId = $(this).data('modal-jws');

            var popupClass = popupId.replace('#', 'mfp-');

            /* The Fullscreen API only paints the fullscreen element's own
               subtree, and magnificPopup otherwise always builds its wrap as
               a child of document.body — so a popup opened while some other
               element (e.g. the drama short player) is fullscreen would get
               its .open classes toggled correctly yet never actually render.
               Building the wrap inside the fullscreen element keeps it on
               screen; outside fullscreen this is just document.body, the
               same default magnificPopup already uses. */
            var fullscreenEl = document.fullscreenElement || document.webkitFullscreenElement || document.body;

            $.magnificPopup.open({
                items: {
                    src: popupId,
                    type: 'inline'
                },
                removalDelay: 360,
                tClose: 'close',
                prependTo: fullscreenEl,
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
