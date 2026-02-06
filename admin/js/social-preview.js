/**
 * RendanIT SEO - Social Preview JavaScript
 */
(function($) {
    'use strict';

    var RSEO_Social = {

        init: function() {
            this.bindEvents();
            this.initMediaUploader();
        },

        bindEvents: function() {
            // Update previews on input change
            $('#rseo_og_title, #rseo_og_description, #rseo_og_image, #rseo_title, #rseo_description').on('input change', this.updatePreviews.bind(this));

            // Twitter card type change
            $('#rseo_twitter_card').on('change', this.updateTwitterCardType.bind(this));

            // Twitter image change
            $('#rseo_twitter_image').on('change', this.updateTwitterImage.bind(this));
        },

        /**
         * Initialize WordPress media uploader
         */
        initMediaUploader: function() {
            var self = this;

            $(document).on('click', '.rseo-upload-image', function(e) {
                e.preventDefault();

                var $button = $(this);
                var targetSelector = $button.data('target');
                var $target = $(targetSelector);

                var frame = wp.media({
                    title: 'Kép kiválasztása',
                    button: { text: 'Kép használata' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $target.val(attachment.url).trigger('change');
                    self.updatePreviews();
                });

                frame.open();
            });
        },

        /**
         * Update all previews
         */
        updatePreviews: function() {
            var ogTitle = $('#rseo_og_title').val() || $('#rseo_title').val() || $('#title').val() || '';
            var ogDesc = $('#rseo_og_description').val() || $('#rseo_description').val() || '';
            var ogImage = $('#rseo_og_image').val() || this.getFeaturedImage() || rseoSocial.defaultOg || '';

            // Facebook preview
            $('#rseo-fb-title').text(ogTitle || 'Bejegyzés címe');
            $('#rseo-fb-desc').text(ogDesc ? ogDesc.substring(0, 150) : 'A bejegyzés leírása itt jelenik meg...');
            $('#rseo-fb-url').text(rseoSocial.siteUrl);

            if (ogImage) {
                $('#rseo-fb-image').css('background-image', 'url(' + ogImage + ')').html('');
            } else {
                $('#rseo-fb-image').css('background-image', 'none').html('<span class="rseo-no-image">Nincs kép</span>');
            }

            // Twitter preview
            var twImage = $('#rseo_twitter_image').val() || ogImage;

            $('#rseo-tw-title').text(ogTitle || 'Bejegyzés címe');
            $('#rseo-tw-desc').text(ogDesc ? ogDesc.substring(0, 120) : 'A bejegyzés leírása...');
            $('#rseo-tw-url').html('🔗 ' + rseoSocial.siteUrl);

            if (twImage) {
                $('#rseo-tw-image').css('background-image', 'url(' + twImage + ')').html('');
            } else {
                $('#rseo-tw-image').css('background-image', 'none').html('<span class="rseo-no-image">Nincs kép</span>');
            }
        },

        /**
         * Update Twitter card type
         */
        updateTwitterCardType: function() {
            var type = $('#rseo_twitter_card').val();
            var $card = $('.rseo-tw-card');

            $card.removeClass('rseo-tw-summary rseo-tw-summary_large_image');
            $card.addClass('rseo-tw-' + type);
        },

        /**
         * Update Twitter image
         */
        updateTwitterImage: function() {
            var twImage = $('#rseo_twitter_image').val();
            var ogImage = $('#rseo_og_image').val() || this.getFeaturedImage() || rseoSocial.defaultOg || '';
            var image = twImage || ogImage;

            if (image) {
                $('#rseo-tw-image').css('background-image', 'url(' + image + ')').html('');
            } else {
                $('#rseo-tw-image').css('background-image', 'none').html('<span class="rseo-no-image">Nincs kép</span>');
            }
        },

        /**
         * Get featured image URL
         */
        getFeaturedImage: function() {
            var $thumbnail = $('#postimagediv img');
            if ($thumbnail.length) {
                var src = $thumbnail.attr('src');
                // Convert thumbnail to larger size
                return src ? src.replace(/-\d+x\d+\./, '.') : '';
            }
            return '';
        }
    };

    $(document).ready(function() {
        if ($('.rseo-social-preview-wrap').length) {
            RSEO_Social.init();
        }
    });

})(jQuery);
