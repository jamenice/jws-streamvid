var ArchiveGlobal;
(function ($) {
    'use strict';
    ArchiveGlobal = (function () {

        return {

            filter_ajax: function () {

                var _isAjaxLoading = false;
                var _cbDebounceTimer = null;

                $(document).on('click', '.jws-post-category-filter a , .page-numbers a', function (e) {

                    e.preventDefault();
                    var url = $(this).attr('href');

                    $(document.body).trigger('archive_videos_filter_ajax', [url, $(this)]);

                });

                /**
                 * Helper: parse query string of window.location into a params object. 
                 */
                function parseCurrentParams() {
                    var url = window.location.href.split('#')[0];
                    var urlParts = url.split('?');
                    var baseUrl = urlParts[0];
                    var qs = urlParts.length > 1 ? urlParts[1] : '';
                    var params = {};
                    if (qs) {
                        qs.split('&').forEach(function (part) {
                            var kv = part.split('=');
                            if (kv[0]) {
                                params[decodeURIComponent(kv[0])] = kv.length > 1 ? decodeURIComponent(kv[1]) : '';
                            }
                        });
                    }
                    return { baseUrl: baseUrl, params: params };
                }

                /**
                 * Helper: build URL from baseUrl + params object.
                 */
                function buildUrl(baseUrl, params) {
                    var qs = [];
                    $.each(params, function (k, v) {
                        if (k && v !== '') qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
                    });
                    return baseUrl + (qs.length ? '?' + qs.join('&') : '');
                }

                /**
                 * Years filter — multi-select toggle built dynamically from window.location
                 * (PHP-rendered href is stale after AJAX so we cannot rely on it)
                 */
                $(document).on('click', '.jws-post-years-filter a', function (e) {
                    e.preventDefault();
                    if (_isAjaxLoading) return;

                    var clicked = $.trim($(this).text());
                    var parsed = parseCurrentParams();
                    var params = parsed.params;

                    var years = params['years'] ? params['years'].split(',') : [];
                    var idx = years.indexOf(clicked);
                    if (idx !== -1) { years.splice(idx, 1); } else { years.push(clicked); }

                    delete params['years'];
                    delete params['paged'];
                    delete params['page'];
                    if (years.length > 0) { params['years'] = years.join(','); }

                    $(document.body).trigger('archive_videos_filter_ajax', [buildUrl(parsed.baseUrl, params), $(this)]);
                });

                /**
                 * Letter filter — multi-select toggle built dynamically from window.location.
                 * Also preserves all active taxonomy/year params so they aren't lost.
                 */
                $(document).on('click', '.jws-post-letter-filter a', function (e) {
                    e.preventDefault();
                    if (_isAjaxLoading) return;

                    var $a = $(this);
                    // PHP sets data-letter with the sanitized value (e.g. "a", "b", "number")
                    var clicked = $a.data('letter');
                    if (!clicked) return;

                    var parsed = parseCurrentParams();
                    var params = parsed.params;

                    var letters = params['starts_with'] ? params['starts_with'].split(',') : [];
                    var idx = letters.indexOf(clicked);
                    if (idx !== -1) { letters.splice(idx, 1); } else { letters.push(clicked); }

                    delete params['starts_with'];
                    delete params['paged'];
                    delete params['page'];
                    if (letters.length > 0) { params['starts_with'] = letters.join(','); }

                    $(document.body).trigger('archive_videos_filter_ajax', [buildUrl(parsed.baseUrl, params), $a]);
                });




                $(document).on('submit', '.post-select-filter', function (e) {
                    e.preventDefault();
                    var parts = [];
                    $(this).serializeArray().forEach(function (item) {
                        if (item.value !== '') {
                            parts.push(encodeURIComponent(item.name) + '=' + encodeURIComponent(item.value));
                        }
                    });
                    var url = $(this).attr('action') + (parts.length ? '?' + parts.join('&') : '');

                    $(document.body).trigger('archive_videos_filter_ajax', [url, $(this)]);

                });


                /**
                 * Taxonomy checkbox filter (jws-taxonomy-checkbox-filter)
                 * Collects all checked values per taxonomy, updates URL param
                 * with comma-separated slugs, then fires archive_videos_filter_ajax.
                 */
                $(document).on('change', '.jws-taxonomy-checkbox-filter input.jws-tax-checkbox', function () {
                    if (_isAjaxLoading) return;
                    var $checkbox = $(this);
                    var $container = $checkbox.closest('.jws-taxonomy-checkbox-filter');
                    var taxonomy = $container.data('taxonomy');

                    // Toggle active class on the label
                    $checkbox.closest('.jws-taxonomy-checkbox-filter__label').toggleClass('active', $checkbox.is(':checked'));

                    // Collect all checked slugs within this container
                    var checked = [];
                    $container.find('input.jws-tax-checkbox:checked').each(function () {
                        checked.push($(this).val());
                    });

                    // Build updated URL using current location
                    var url = window.location.href.split('#')[0];

                    // Remove existing taxonomy param and paged param from URL
                    url = url.replace(new RegExp('[?&]' + taxonomy + '=[^&]*', 'g'), '');
                    url = url.replace(/[?&]paged=[^&]*/g, '');
                    url = url.replace(/[?&]page=[^&]*/g, '');

                    // Clean up any dangling ? or & at the end
                    url = url.replace(/[?&]+$/, '');

                    // If ? was removed (was first param) but & remains, replace first & with ?
                    if (url.indexOf('?') === -1 && url.indexOf('&') !== -1) {
                        url = url.replace('&', '?');
                    }

                    if (checked.length > 0) {
                        var separator = url.indexOf('?') !== -1 ? '&' : '?';
                        url = url + separator + taxonomy + '=' + checked.join(',');
                    }

                    clearTimeout(_cbDebounceTimer);
                    _cbDebounceTimer = setTimeout(function () {
                        $(document.body).trigger('archive_videos_filter_ajax', [url, $checkbox]);
                    }, 350);
                });





                $(document.body).on('archive_videos_filter_ajax', function (e, url, element) {

                    $('html,body').animate({
                        scrollTop: $(".content-area").offset().top - 120
                    }, 600);

                    $('.jws-filter-modal').removeClass('open').hide();
                    $('.show_filter_shop').removeClass('active');

                    if ('?' === url.slice(-1)) {
                        url = url.slice(0, -1);
                    }

                    url = url.replace(/%2C/g, ',');

                    window.history.pushState(null, "", url);
                    $(window).off('popstate.jwsFilter').on('popstate.jwsFilter', function () {
                        window.location = location.href;
                    });

                    // Parse query params from the updated URL
                    var urlParts = url.split('?');
                    var baseUrl = urlParts[0];
                    var qs = urlParts.length > 1 ? urlParts[1] : '';
                    var params = {};
                    if (qs) {
                        qs.split('&').forEach(function (part) {
                            var kv = part.split('=');
                            if (kv[0]) {
                                params[decodeURIComponent(kv[0])] = kv.length > 1 ? decodeURIComponent(kv[1]) : '';
                            }
                        });
                    }
                    var paged = params.paged ? parseInt(params.paged, 10) : 1;

                    // Block duplicate calls while loading
                    if (_isAjaxLoading) return;
                    _isAjaxLoading = true;

                    // Show loading overlay on the posts wrap
                    var $wrap = $('.jws-archive-posts-wrap');
                    $wrap.addClass('loading');
                    $('.post_content').addClass('jws-animated-post');

                    if ($('.profile-v2').length) {
                        jwsArchiveFilter.post_type = 'videos';
                    }

                    // Build POST data 
                    var postData = $.extend({}, params, {
                        action: 'jws_archive_filter_ajax',
                        post_type: jwsArchiveFilter.post_type,
                        base_url: baseUrl,
                        paged: paged
                    });

                    $.ajax({
                        type: 'POST',
                        url: jwsArchiveFilter.ajaxurl,
                        data: postData,
                        dataType: 'json',
                        success: function (res) {
                            _isAjaxLoading = false;
                            $wrap.removeClass('loading');
                            if (!res || !res.success) return;

                            var data = res.data;

                            // Replace posts grid content only
                            $('.jws-posts-grid').html(data.html);

                            // Update post-result count
                            if (data.post_result_html) {
                                if ($('.post-result').length) {
                                    $('.post-result').replaceWith(data.post_result_html);
                                }
                            }

                            // Replace or inject pagination
                            if ($('.jws-pagination-number').length) {
                                $('.jws-pagination-number').replaceWith(data.pagination || '');
                            } else if (data.pagination) {
                                $wrap.append(data.pagination);
                            }

                            // Sync checkbox active states from current URL params
                            $('.jws-taxonomy-checkbox-filter').each(function () {
                                var $container = $(this);
                                var tax = $container.data('taxonomy');
                                var activeValues = params[tax] ? params[tax].split(',') : [];
                                $container.find('input.jws-tax-checkbox').each(function () {
                                    var $cb = $(this);
                                    var checked = activeValues.indexOf($cb.val()) !== -1;
                                    $cb.prop('checked', checked);
                                    $cb.closest('.jws-taxonomy-checkbox-filter__label').toggleClass('active', checked);
                                });
                            });

                            // Sync years filter active state
                            var activeYears = params['years'] ? params['years'].split(',') : [];
                            $('.jws-post-years-filter a').each(function () {
                                $(this).toggleClass('current', activeYears.indexOf($.trim($(this).text())) !== -1);
                            });

                            // Sync letter filter active state (use data-letter set by PHP)
                            var activeLetters = params['starts_with'] ? params['starts_with'].split(',') : [];
                            $('.jws-post-letter-filter a').each(function () {
                                var letter = $(this).data('letter') || '';
                                $(this).toggleClass('current', letter !== '' && activeLetters.indexOf(letter) !== -1);
                            });

                            // Sync category filter active state (active class on <li>)
                            var catKey = jwsArchiveFilter.post_type + '_cat';
                            var activeCat = params[catKey] || '';
                            $('.jws-post-category-filter .cat-item').each(function () {
                                var href = $(this).children('a').attr('href') || '';
                                var match = href.match(new RegExp('[?&]' + catKey + '=([^&]*)'));
                                var slug = match ? decodeURIComponent(match[1]) : '';
                                $(this).toggleClass('current', slug !== '' && slug === activeCat);
                            });

                            // Update title bar: single term → term name; default → archive/page title
                            if (typeof data.title !== 'undefined' && data.title !== '') {
                                $('.jws-title-bar-wrap .title, .jws-title-bar-wrap h1.jws-text-ellipsis').text(data.title);
                            }

                            // Update breadcrumbs: keep Home + first separator, replace the rest
                            if (typeof data.breadcrumb_items !== 'undefined') {
                                var $bc = $('.jws-breadcrumbs');
                                if ($bc.length) {
                                    $bc.children('li').slice(2).remove();
                                    $bc.append(data.breadcrumb_items);
                                }
                            }

                            if (typeof jwsThemeModule !== 'undefined' && jwsThemeModule.movies_offset) {
                                jwsThemeModule.movies_offset();
                            }

                            // Sync cat_change: reset to default when multiple taxonomy values selected
                            $('.cat_change').each(function () {
                                var $sel = $(this);
                                var tax = $sel.data('taxonomy') || $sel.attr('name') || 'genres';
                                var taxVal = params[tax] || '';
                                $sel.val(taxVal.indexOf(',') !== -1 ? '' : taxVal);
                            });

                            $('select').select2({
                                dropdownAutoWidth: true,
                                minimumResultsForSearch: 10
                            });

                            // Trigger animated-post effect then staggered item animation
                            var $grid = $('.jws-posts-grid');

                            var iter = 0;
                            var animID = setInterval(function () {
                                var $item = $grid.find('.jws-post-item').eq(iter);
                                if ($item.length === 0) { clearInterval(animID); return; }
                                $item.addClass('jws-animated');
                                iter++;
                            }, 80);
                        },
                        error: function () {
                            _isAjaxLoading = false;
                            $wrap.removeClass('loading');
                        }
                    });

                });

            },

        }



    }());
    jQuery(document).ready(function ($) {

        ArchiveGlobal.filter_ajax();


    });

})(jQuery);
