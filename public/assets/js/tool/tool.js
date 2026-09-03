(function ($) {
    'use strict';

    $(document).ready(function () {


        /*
         * The share popup ships as an empty shell (see tool/share/share.php) and
         * is filled on demand: nothing is requested until someone actually opens
         * it, and each post's markup is kept so re-opening the same card costs
         * nothing. [data-modal-jws] does the opening, in jws-streamvid-public.js
         * — this only supplies the contents, so the box is already on screen
         * with its spinner while the request is in flight.
         */
        var share_html = {};

        $(document).on('click', '[data-share-id]', function () {

            var id     = $(this).data('share-id');
            var $shell = $('#share-videos');

            if (!id || !$shell.length || $shell.data('filled-id') === id) {
                return;
            }

            var $body = $shell.data('filled-id', id).addClass('loading').find('.share-body').empty();

            if (share_html[id]) {
                $body.html(share_html[id]);
                $shell.removeClass('loading');
                return;
            }

            $.ajax({
                type: 'POST',
                url: jws_script.ajax_url,
                data: {
                    action: 'share_content',
                    post_id: id
                },
                success: function (response) {

                    if (!response.success || !response.data.html) {
                        return;
                    }

                    share_html[id] = response.data.html;
                    $body.html(response.data.html);
                },
                complete: function () {
                    $shell.removeClass('loading');
                },
                // A failed fill must not be remembered as this post's content.
                error: function () {
                    $shell.removeData('filled-id');
                }
            });
        });

        /*
         * "Copy link" in the share row. The anchor points at the page itself, so
         * with no clipboard API available the click is left alone and the link
         * still goes somewhere sensible.
         */
        $(document).on('click', '.share-copy-link', function (e) {

            var url = $(this).attr('href');

            if (!navigator.clipboard) {
                return;
            }

            e.preventDefault();

            navigator.clipboard.writeText(url).then(function () {
                if (typeof jwsThemeModule !== 'undefined' && jwsThemeModule.show_notification) {
                    jwsThemeModule.show_notification('Link copied', 'success');
                }
            });
        });

        $('.jws-download-videos').on('click', function (e) {

            e.preventDefault();

            var button = $(this);

            button.next('.jws-download-list').slideToggle();

        });

        $('.jws-download-list a').on('click', function (e) {

            e.preventDefault();

            var button = $(this);
            var download_url = button.data('url');



            button.addClass('loading');
            if (!button.find('.loader').length) {
                button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
            }
            console.log(download_url);
            $.ajax({
                type: 'POST',
                url: jws_script.ajax_url,
                data: {
                    action: 'download_post',
                    download_url: download_url,
                },
                success: function (response) {

                    console.log(response);


                    if (response.data.content != "no_file") {

                        var link = document.createElement('a');
                        link.href = response.data.content;
                        link.download = 'video.mp4'; // Set the filename for the downloaded video

                        // Append the link to the document and trigger the click event
                        $(document.body).append(link);
                        link.click();

                        // Remove the link from the document
                        $(link).remove();

                    } else {


                        jwsThemeModule.show_notification('Video not found.', 'error');

                    }

                    button.removeClass('loading');


                }
            });
        });

        $(document).on('click', '.remove-favorite', function (e) {
            e.preventDefault();

            if ($('body').hasClass('user-not-logged-in')) {
                $('.jws-form-login-popup').addClass('open');
                return false;
            }

            var button = $(this);
            var post_id = button.data('id');

            // Get post type from current filter tab
            var current_filter = $('.favorites-filter.active').data('filter') ||
                $('input[name="favorites_filter"]:checked').val() ||
                'movies';


            button.addClass('loading');
            if (!button.find('.loader').length) {
                button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
            }

            $.ajax({
                type: 'POST',
                url: jws_script.ajax_url,
                data: {
                    action: 'like_post',
                    post_id: post_id,
                    post_type: current_filter,
                    type: 'dislike',
                },
                success: function (response) {

                    if (response.success) {
                        // Remove item with fade effect
                        button.parents('.jws-post-item').fadeOut(300, function () {
                            $(this).remove();

                            // Check if no items left
                            if ($('.row .jws-post-item').length === 0) {
                                $('.row').html('<div class="jws-post-item col-12">' +
                                    (jws_script.not_found || 'Not Found') + '</div>');
                            }
                        });

                        jwsThemeModule.show_notification(response.data.message, 'success');
                    } else {
                        jwsThemeModule.show_notification(response.data.message || 'Error occurred', 'error');
                    }
                },
                error: function () {
                    jwsThemeModule.show_notification('Error occurred', 'error');
                },
                complete: function () {
                    button.removeClass('loading');
                }
            });

        });
        $(document).on('click', '.like-button', function (e) {
            e.preventDefault();

            if ($('body').hasClass('user-not-logged-in')) {
                $('.jws-form-login-popup').addClass('open');
                return false;
            }

            var button = $(this);
            var post_id = button.data('post-id');
            var post_type = button.data('type');
            var likes_count = button.find('.likes-count');
            var $type = 'like';
            if (button.hasClass('liked')) {
                $type = 'dislike';
            }
            button.addClass('loading');
            if (!button.find('.loader').length) {
                button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
            }
            $.ajax({
                type: 'POST',
                url: jws_script.ajax_url,
                data: {
                    action: 'like_post',
                    post_id: post_id,
                    post_type: post_type,
                    type: $type,
                },
                success: function (response) {
                    button.removeClass('loading');
                    if (response.data.status == 'good') {
                        likes_count.text(response.data.count);
                        button.addClass('liked');
                    } else {
                        button.removeClass('liked');
                        likes_count.text(response.data.count);
                    }
                    jwsThemeModule.show_notification(response.data.message, 'success');
                }
            });
        });




        /* Code function watch list */

        function edit_watchlist() {
            $(document).on('click', '.remove-watchlist', function (e) {
                e.preventDefault();
                var button = $(this);
                var post_id = button.data('id');
                var $type = 'watchlisted';
                watchlist_item(post_id, $type, button);
            });

            $(document).on('click', '.remove-history', function (e) {
                e.preventDefault();
                var button = $(this);
                var post_id = button.data('id');

                history_delete(post_id, button);
            });

            $(document).on('click', '.watchlist-edit', function (e) {

                e.preventDefault();
                var button = $(this);
                $('.watchlist-button').addClass('editor');
                $('.profile-main').addClass('editor');
            });
            $(document).on('click', '.watchlist-cancel', function (e) {

                e.preventDefault();
                var button = $(this);
                $('.watchlist-button').removeClass('editor');
                $('.profile-main').removeClass('editor');
            });

            $(document).on('click', '.select-all', function (e) {

                e.preventDefault();

                var button = $(this);
                button.toggleClass('active');
                if (button.hasClass('active')) {
                    $('input[name="watchlisted[]"]').prop('checked', true);
                } else {
                    $('input[name="watchlisted[]"]').prop('checked', false);
                }

            });

            $(document).on('click', '.watchlist-delete', function (e) {

                e.preventDefault();
                var button = $(this);
                var selectedValues = [];
                var $type = 'watchlist_many';
                $('input[name="watchlisted[]"]:checked').each(function () {
                    selectedValues.push($(this).val());
                });

                if ($('.profile-watchlist').length) {
                    watchlist_item(selectedValues, $type, button);
                } else {
                    history_delete(selectedValues, button);
                }




            });

            function history_delete($id, button) {
                button.addClass('loading');
                if (!button.find('.loader').length) {
                    button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
                }
                $.ajax({
                    type: 'POST',
                    url: jws_script.ajax_url,
                    data: {
                        action: 'history_delete',
                        post_id: $id,
                    },
                    success: function (response) {
                        button.removeClass('loading');

                        if (response.success) {

                            if (Array.isArray($id)) {
                                $('input[name="watchlisted[]"]:checked').each(function () {
                                    $(this).parents('.jws-post-item').remove();
                                });
                            } else {
                                button.parents('.jws-post-item').fadeOut(300, function () {
                                    $(this).remove();
                                });
                            }

                            jwsThemeModule.show_notification(response.data.message, 'success');

                        } else {
                            jwsThemeModule.show_notification(response.data[0].message, 'error');
                        }
                    }
                });
            }



            function watchlist_item($id, $type, button) {
                button.addClass('loading');
                let button_global;
                if (button.hasClass('watchlist-delete') || button.hasClass('remove-watchlist')) {
                    button_global = button;
                } else {
                    button_global = $(".watchlist-add[data-post-id=" + $id + "]");
                }
                if (!button.find('.loader').length) {
                    button.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
                }
                $.ajax({
                    type: 'POST',
                    url: jws_script.ajax_url,
                    data: {
                        action: 'watchlist_post',
                        post_id: $id,
                        type: $type,
                    },
                    success: function (response) {

                        if (response.success) {
                            if ($type == 'watchlist_many') {
                                $('input[name="watchlisted[]"]:checked').each(function () {
                                    $(this).parents('.jws-post-item').remove();
                                });
                            } else if (button.hasClass('remove-watchlist')) {
                                // Remove single item
                                button.parents('.jws-post-item').fadeOut(300, function () {
                                    $(this).remove();
                                });

                            } else {
                                if (response.data.status == 'good') {
                                    button_global.addClass('watchlisted');
                                } else {
                                    button_global.removeClass('watchlisted');
                                }
                            }
                            jwsThemeModule.show_notification(response.data.message, 'success');

                        } else {
                            jwsThemeModule.show_notification(response.data[0].message, 'error');
                        }
                    },
                    complete: function () {
                        button.removeClass('loading');
                    }
                });
            }

            $(document).on('click', '.watchlist-add', function (e) {
                e.preventDefault();

                if ($('body').hasClass('user-not-logged-in')) {
                    $('.jws-form-login-popup').addClass('open');
                    return false;
                }

                var button = $(this);
                var post_id = button.data('post-id');
                var $type = 'watchlist';
                if (button.hasClass('watchlisted')) {
                    $type = 'watchlisted';
                }
                watchlist_item(post_id, $type, button);
            });


        }
        edit_watchlist();


    });

})(jQuery);
