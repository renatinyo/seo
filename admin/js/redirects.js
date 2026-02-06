/**
 * RendanIT SEO - Redirect Manager JavaScript
 */
(function($) {
    'use strict';

    var RSEO_Redirects = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Add redirect form
            $('#rseo-add-redirect-form').on('submit', this.addRedirect.bind(this));

            // Delete redirect
            $(document).on('click', '.rseo-delete-redirect', this.deleteRedirect.bind(this));

            // Toggle redirect status
            $(document).on('click', '.rseo-toggle-redirect', this.toggleRedirect.bind(this));

            // Slug change notice - create redirect
            $(document).on('click', '.rseo-create-redirect', this.createFromNotice.bind(this));

            // Slug change notice - dismiss
            $(document).on('click', '.rseo-dismiss-notice', this.dismissNotice.bind(this));
        },

        /**
         * Add new redirect
         */
        addRedirect: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $submit = $form.find('button[type="submit"]');
            var originalText = $submit.text();

            // Get form data
            var data = {
                action: 'rseo_add_redirect',
                nonce: rseoRedirects.nonce,
                source: $form.find('input[name="source"]').val(),
                target: $form.find('input[name="target"]').val(),
                type: $form.find('select[name="type"]').val(),
                is_regex: $form.find('input[name="is_regex"]').is(':checked') ? '1' : '0',
                notes: $form.find('input[name="notes"]').val()
            };

            // Validate
            if (!data.source || !data.target) {
                alert('Kérlek add meg a forrás és cél URL-t!');
                return;
            }

            // Loading state
            $submit.text('Mentés...').prop('disabled', true);
            $form.addClass('rseo-loading');

            $.post(rseoRedirects.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Reload page to show new redirect
                    window.location.reload();
                } else {
                    alert(response.data || rseoRedirects.strings.error);
                }
            }).fail(function() {
                alert(rseoRedirects.strings.error);
            }).always(function() {
                $submit.text(originalText).prop('disabled', false);
                $form.removeClass('rseo-loading');
            });
        },

        /**
         * Delete redirect
         */
        deleteRedirect: function(e) {
            e.preventDefault();

            if (!confirm(rseoRedirects.strings.confirmDelete)) {
                return;
            }

            var $button = $(e.target).closest('.rseo-delete-redirect');
            var $row = $button.closest('tr');
            var id = $row.data('id');

            $row.addClass('rseo-loading');

            $.post(rseoRedirects.ajaxUrl, {
                action: 'rseo_delete_redirect',
                nonce: rseoRedirects.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || rseoRedirects.strings.error);
                    $row.removeClass('rseo-loading');
                }
            }).fail(function() {
                alert(rseoRedirects.strings.error);
                $row.removeClass('rseo-loading');
            });
        },

        /**
         * Toggle redirect active state
         */
        toggleRedirect: function(e) {
            e.preventDefault();

            var $button = $(e.target).closest('.rseo-toggle-redirect');
            var $row = $button.closest('tr');
            var id = $row.data('id');

            $row.addClass('rseo-loading');

            $.post(rseoRedirects.ajaxUrl, {
                action: 'rseo_toggle_redirect',
                nonce: rseoRedirects.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    // Toggle visual state
                    $row.toggleClass('rseo-inactive');

                    var isActive = !$row.hasClass('rseo-inactive');
                    $button.html(isActive ? '✅ Aktív' : '⏸️ Inaktív');

                    $row.addClass('rseo-flash-success');
                    setTimeout(function() {
                        $row.removeClass('rseo-flash-success');
                    }, 1000);
                } else {
                    alert(response.data || rseoRedirects.strings.error);
                }
            }).fail(function() {
                alert(rseoRedirects.strings.error);
            }).always(function() {
                $row.removeClass('rseo-loading');
            });
        },

        /**
         * Create redirect from slug change notice
         */
        createFromNotice: function(e) {
            e.preventDefault();

            var $button = $(e.target);
            var $notice = $button.closest('.rseo-slug-notice');
            var postId = $button.data('post-id');
            var oldUrl = $button.data('old');
            var newUrl = $button.data('new');

            $button.text('Mentés...').prop('disabled', true);

            $.post(rseoRedirects.ajaxUrl, {
                action: 'rseo_create_redirect_from_notice',
                nonce: rseoRedirects.nonce,
                post_id: postId,
                old_url: oldUrl,
                new_url: newUrl
            }, function(response) {
                if (response.success) {
                    $notice.removeClass('notice-warning').addClass('notice-success');
                    $notice.find('p').first().html('<strong>✅ Átirányítás létrehozva!</strong> A régi URL mostantól az új címre irányít.');
                    $notice.find('p').last().remove();

                    setTimeout(function() {
                        $notice.fadeOut(500);
                    }, 3000);
                } else {
                    alert(response.data || rseoRedirects.strings.error);
                    $button.text('✅ 301 átirányítás létrehozása').prop('disabled', false);
                }
            }).fail(function() {
                alert(rseoRedirects.strings.error);
                $button.text('✅ 301 átirányítás létrehozása').prop('disabled', false);
            });
        },

        /**
         * Dismiss slug change notice
         */
        dismissNotice: function(e) {
            e.preventDefault();

            var $button = $(e.target);
            var $notice = $button.closest('.rseo-slug-notice');
            var postId = $button.data('post-id');

            $.post(rseoRedirects.ajaxUrl, {
                action: 'rseo_dismiss_slug_notice',
                nonce: rseoRedirects.nonce,
                post_id: postId
            }, function(response) {
                $notice.fadeOut(300);
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RSEO_Redirects.init();
    });

})(jQuery);
