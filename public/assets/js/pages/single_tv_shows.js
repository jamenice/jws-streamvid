(function ($) {
    'use strict';

    jQuery(document).ready(function ($) {


        function change_seasion() {

            $(document).on('click', '.dropdown-toggle', function (e) {
                $(this).toggleClass('open');
                $(this).next('.dropdown-menu').slideToggle(200);
            });

            $(document).on('click', function (event) {
                var $trigger = $('.dropdown');
                if ($trigger !== event.target && !$trigger.has(event.target).length) {
                    $('.select-seasion .dropdown-menu').slideUp(200);
                    $('.select-seasion .dropdown-toggle').removeClass('open');
                }
            });
            $(document).on('click', '.dropdown-item', function (e) {
                e.preventDefault();
                var button = $(this);
                var value = button.data('value');
                var season = button.data('index');
                var dropdown = button.parents('.select-seasion');
                var id = dropdown.data('id');
                var intervalID;
                var wrap = $('.global-episodes .episodes-content');
                button.closest('.dropdown').find('.dropdown-toggle').text(value).addClass('selected');

                $('.select-seasion .dropdown-menu').slideUp(200);
                $('.select-seasion .dropdown-toggle').removeClass('open');

                var display = dropdown.find('.dropdown-toggle').data('display');

                if (button.hasClass('active')) {
                    return false;
                }


                if ($('.jws-view-episodes').length) {
                    var url_view = $('.jws-view-episodes').attr('href');

                    if (url_view.indexOf('season=') === -1) {
                        url_view += '?season=' + (season + 1);
                    }
                    url_view = url_view.replace(/(\?|&)season=([^&]*)/, '$1season=' + (season + 1));


                    $('.jws-view-episodes').attr('href', url_view);
                }


                wrap.addClass('jws-animated-post');
                clearInterval(intervalID);


                var data = {
                    id: id,
                    season: season,
                    action: "jws_load_season",
                };

                data.display = display;


                if (display === 'episodes_version2') {
                    $('.sidebar-list').addClass('loading');
                    if (!$('.sidebar-list').find('.loader').length) {
                        $('.sidebar-list').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
                    }
                } else if (display === 'v3') {
                    $('.global-episodes').addClass('loading');
                    if (!$('.global-episodes').find('.loader').length) {
                        $('.global-episodes').append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
                    }
                } else {
                    if (!wrap.find('.loader').length) {
                        wrap.append('<div class="loader"><svg class="circular" viewBox="25 25 50 50"><circle class="path" cx="50" cy="50" r="20" fill="none" stroke-width="2" stroke-miterlimit="10"/></svg></div>');
                    }
                }


                wrap.addClass('loading');


                $.ajax({
                    url: jws_script.ajax_url,
                    data: data,
                    type: 'POST',
                    dataType: 'json',
                }).success(function (response) {

                    let content = response.data.content;

                    if (response.data.status.display == 'episodes_version2') {

                        $('.sidebar-list').replaceWith(content);
                        jwsSingleGlobal.jws_scroll_to_episodes();

                    } else if (display === 'v3') {

                        var innerHtml = $('<div>' + content + '</div>').find('.global-episodes').html();
                        $('.global-episodes').html(innerHtml);

                    } else {

                        wrap.html(content);
                        jwsSingleGlobal.episodes_carousel();
                        var iter = 0;
                        intervalID = setInterval(function () {
                            wrap.find('.jws-post-item').eq(iter).addClass('jws-animated');
                            iter++;
                        }, 100);

                    }


                }).complete(function () {
                    button.addClass('active').parent().siblings().children().removeClass('active');
                    wrap.removeClass('loading');
                }).error(function (ex) {
                    console.log(ex);
                });



            });

        }

        change_seasion();


        $('#commentform').append('<input type="hidden" name="redirect_to" value="' + window.location.href + '">');


    });

})(jQuery);
