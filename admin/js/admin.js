/**
 * RendanIT SEO Admin JS
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Character counter for title inputs
        $(document).on('input', '.rseo-title-input', function() {
            var len = $(this).val().length;
            var counter = $(this).closest('td, p').find('.rseo-char-count');
            counter.find('.rseo-count').text(len);

            counter.removeClass('rseo-over rseo-optimal');
            if (len > 60) {
                counter.addClass('rseo-over');
            } else if (len >= 30 && len <= 60) {
                counter.addClass('rseo-optimal');
            }

            // Update preview
            var preview = $(this).closest('td, p').find('.rseo-preview-title');
            if (preview.length) {
                preview.text($(this).val() || 'Oldal címe');
            }
        });

        // Character counter for description inputs
        $(document).on('input', '.rseo-desc-input', function() {
            var len = $(this).val().length;
            var counter = $(this).closest('td, p').find('.rseo-char-count');
            counter.find('.rseo-count').text(len);

            counter.removeClass('rseo-over rseo-optimal');
            if (len > 155) {
                counter.addClass('rseo-over');
            } else if (len >= 50 && len <= 155) {
                counter.addClass('rseo-optimal');
            }

            // Update preview
            var preview = $(this).closest('td, p').find('.rseo-preview-desc');
            if (preview.length) {
                preview.text($(this).val() || 'Meta leírás helye...');
            }
        });

        // Initialize counters on load
        $('.rseo-title-input').trigger('input');
        $('.rseo-desc-input').trigger('input');

        // Media uploader for image fields
        $(document).on('click', '.rseo-upload-image', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var frame = wp.media({
                title: 'Kép kiválasztása',
                button: { text: 'Kiválasztás' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $(target).val(attachment.url);
            });

            frame.open();
        });
    });

})(jQuery);
