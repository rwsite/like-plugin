(function ($) {
    'use strict';

    $(document).on('click', '.sl-button', function (e) {
        e.preventDefault();
        console.log('like js');

        const button = $(this);
        const post_id = button.attr('data-post-id');
        const security = button.attr('data-nonce');
        const iscomment = button.attr('data-iscomment');
        let allbuttons;

        if (iscomment === '1') { /* Comments can have same id */
            allbuttons = $('.sl-comment-button-' + post_id);
        } else {
            allbuttons = $('.sl-button-' + post_id);
        }

        const loader = allbuttons.next('#sl-loader');
        if (post_id !== '') {
            $.ajax({
                type: 'POST',
                url: simpleLikes.ajaxurl,
                data: {
                    action: 'process_simple_like',
                    post_id: post_id,
                    nonce: security,
                    is_comment: iscomment,
                },
                beforeSend: function () {
                    loader.html('&nbsp;<div class="loader">Loading...</div>');
                },
                success: function (response) {
                    var icon = response.icon;
                    var count = response.count;
                    allbuttons.html(icon + count);
                    if (response.status === 'unliked') {
                        var like_text = simpleLikes.like;
                        allbuttons.prop('title', like_text);
                        allbuttons.removeClass('liked');
                    } else {
                        var unlike_text = simpleLikes.unlike;
                        allbuttons.prop('title', unlike_text);
                        allbuttons.addClass('liked');
                    }
                    loader.empty();
                }
            });

        }

    });
})(jQuery);
