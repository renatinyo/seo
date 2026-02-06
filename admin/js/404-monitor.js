/**
 * RendanIT SEO - 404 Monitor JavaScript
 */
(function($) {
    'use strict';

    var RSEO_404 = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Delete single 404
            $(document).on('click', '.rseo-delete-404', this.delete404.bind(this));

            // Delete all 404s
            $(document).on('click', '.rseo-delete-all-404', this.deleteAll404.bind(this));

            // Open redirect modal
            $(document).on('click', '.rseo-create-redirect-404', this.openRedirectModal.bind(this));

            // Close modal
            $(document).on('click', '.rseo-modal-close, .rseo-modal', this.closeModal.bind(this));
            $(document).on('click', '.rseo-modal-content', function(e) {
                e.stopPropagation();
            });

            // Submit redirect form
            $('#rseo-404-redirect-form').on('submit', this.createRedirect.bind(this));
        },

        /**
         * Delete single 404
         */
        delete404: function(e) {
            e.preventDefault();

            if (!confirm(rseo404.strings.confirmDelete)) {
                return;
            }

            var $button = $(e.target).closest('.rseo-delete-404');
            var $row = $button.closest('tr');
            var id = $row.data('id');

            $row.css('opacity', '0.5');

            $.post(rseo404.ajaxUrl, {
                action: 'rseo_delete_404',
                nonce: rseo404.nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data || 'Hiba történt');
                    $row.css('opacity', '1');
                }
            }).fail(function() {
                alert('Hiba történt');
                $row.css('opacity', '1');
            });
        },

        /**
         * Delete all 404s
         */
        deleteAll404: function(e) {
            e.preventDefault();

            if (!confirm(rseo404.strings.confirmDeleteAll)) {
                return;
            }

            var $button = $(e.target);
            $button.prop('disabled', true).text('Törlés...');

            $.post(rseo404.ajaxUrl, {
                action: 'rseo_delete_all_404',
                nonce: rseo404.nonce
            }, function(response) {
                if (response.success) {
                    window.location.reload();
                } else {
                    alert(response.data || 'Hiba történt');
                    $button.prop('disabled', false).text('🗑️ Összes törlése');
                }
            }).fail(function() {
                alert('Hiba történt');
                $button.prop('disabled', false).text('🗑️ Összes törlése');
            });
        },

        /**
         * Open redirect modal
         */
        openRedirectModal: function(e) {
            e.preventDefault();

            var $button = $(e.target).closest('.rseo-create-redirect-404');
            var url = $button.data('url');
            var $row = $button.closest('tr');
            var id = $row.data('id');

            var $modal = $('#rseo-redirect-modal');
            $modal.find('input[name="id"]').val(id);
            $modal.find('input[name="source"]').val(url);
            $modal.find('input[name="target"]').val('').focus();

            $modal.show();
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if ($(e.target).hasClass('rseo-modal') || $(e.target).hasClass('rseo-modal-close')) {
                $('#rseo-redirect-modal').hide();
            }
        },

        /**
         * Create redirect from modal
         */
        createRedirect: function(e) {
            e.preventDefault();

            var $form = $(e.target);
            var $submit = $form.find('button[type="submit"]');
            var data = {
                action: 'rseo_create_redirect_from_404',
                nonce: rseo404.nonce,
                id: $form.find('input[name="id"]').val(),
                source: $form.find('input[name="source"]').val(),
                target: $form.find('input[name="target"]').val()
            };

            if (!data.target) {
                alert('Add meg a cél URL-t!');
                return;
            }

            $submit.prop('disabled', true).text('Létrehozás...');

            $.post(rseo404.ajaxUrl, data, function(response) {
                if (response.success) {
                    // Update row
                    var $row = $('tr[data-id="' + data.id + '"]');
                    $row.addClass('rseo-resolved');
                    $row.find('.column-status').html('<span class="rseo-status rseo-status-resolved">✅ Megoldva</span>');
                    $row.find('.rseo-create-redirect-404').remove();

                    $('#rseo-redirect-modal').hide();
                    $form[0].reset();
                } else {
                    alert(response.data || 'Hiba történt');
                }
            }).fail(function() {
                alert('Hiba történt');
            }).always(function() {
                $submit.prop('disabled', false).text('Létrehozás');
            });
        }
    };

    $(document).ready(function() {
        RSEO_404.init();
    });

})(jQuery);
