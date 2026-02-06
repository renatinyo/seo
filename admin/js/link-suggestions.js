/**
 * RendanIT SEO - Link Suggestions JavaScript
 */
(function($) {
    'use strict';

    var RSEO_Links = {
        searchTimeout: null,

        init: function() {
            this.bindEvents();
            this.loadSuggestions();
        },

        bindEvents: function() {
            // Refresh suggestions
            $('#rseo-refresh-suggestions').on('click', this.loadSuggestions.bind(this));

            // Copy link
            $(document).on('click', '.rseo-copy-link', this.copyLink.bind(this));

            // View link
            $(document).on('click', '.rseo-view-link', this.viewLink.bind(this));

            // Search
            $('#rseo-link-search').on('input', this.handleSearch.bind(this));
        },

        /**
         * Load suggestions via AJAX
         */
        loadSuggestions: function() {
            var self = this;
            var $list = $('#rseo-link-suggestions-list');

            $list.html('<p class="rseo-loading">' + rseoLinks.strings.loading + '</p>');

            $.post(rseoLinks.ajaxUrl, {
                action: 'rseo_get_link_suggestions',
                nonce: rseoLinks.nonce,
                post_id: rseoLinks.postId
            }, function(response) {
                if (response.success && response.data.length) {
                    self.renderSuggestions($list, response.data);
                } else {
                    $list.html('<p class="rseo-no-results">' + rseoLinks.strings.noResults + '</p>');
                }
            }).fail(function() {
                $list.html('<p class="rseo-no-results">Hiba történt</p>');
            });
        },

        /**
         * Render suggestions list
         */
        renderSuggestions: function($container, items) {
            var html = '';

            items.forEach(function(item) {
                var linkedClass = item.is_linked ? ' rseo-already-linked' : '';
                var linkedBadge = item.is_linked ? '<span class="rseo-linked-badge">✓ belinkelve</span>' : '';
                var matchInfo = item.match_term ? '<span class="rseo-match">„' + item.match_term + '"</span>' : '';

                html += '<div class="rseo-link-item' + linkedClass + '" data-url="' + item.url + '">' +
                    '<div class="rseo-link-info">' +
                    '<span class="rseo-link-title" title="' + item.title + '">' + item.title + '</span>' +
                    '<div class="rseo-link-meta">' + item.post_type + ' ' + matchInfo + ' ' + linkedBadge + '</div>' +
                    '</div>' +
                    '<div class="rseo-link-actions">' +
                    '<button type="button" class="button button-small rseo-copy-link" title="Link másolása">📋</button>' +
                    '<button type="button" class="button button-small rseo-view-link" title="Megnyitás">↗</button>' +
                    '</div>' +
                    '</div>';
            });

            $container.html(html);
        },

        /**
         * Handle search input
         */
        handleSearch: function(e) {
            var self = this;
            var query = $(e.target).val();
            var $results = $('#rseo-link-search-results');

            clearTimeout(this.searchTimeout);

            if (query.length < 2) {
                $results.hide().empty();
                return;
            }

            this.searchTimeout = setTimeout(function() {
                self.searchPosts(query);
            }, 300);
        },

        /**
         * Search posts via AJAX
         */
        searchPosts: function(query) {
            var self = this;
            var $results = $('#rseo-link-search-results');

            $results.show().html('<p class="rseo-loading">Keresés...</p>');

            $.post(rseoLinks.ajaxUrl, {
                action: 'rseo_search_posts_for_link',
                nonce: rseoLinks.nonce,
                search: query,
                post_id: rseoLinks.postId
            }, function(response) {
                if (response.success && response.data.length) {
                    self.renderSuggestions($results, response.data);
                } else {
                    $results.html('<p class="rseo-no-results">Nincs találat</p>');
                }
            }).fail(function() {
                $results.html('<p class="rseo-no-results">Hiba történt</p>');
            });
        },

        /**
         * Copy link to clipboard
         */
        copyLink: function(e) {
            e.preventDefault();

            var $item = $(e.target).closest('.rseo-link-item');
            var url = $item.data('url');
            var title = $item.find('.rseo-link-title').text();

            // Create HTML link
            var linkHtml = '<a href="' + url + '">' + title + '</a>';

            // Copy to clipboard
            if (navigator.clipboard) {
                navigator.clipboard.writeText(linkHtml).then(function() {
                    this.showNotice(rseoLinks.strings.copied);
                }.bind(this));
            } else {
                // Fallback
                var $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(linkHtml).select();
                document.execCommand('copy');
                $temp.remove();
                this.showNotice(rseoLinks.strings.copied);
            }
        },

        /**
         * View link in new tab
         */
        viewLink: function(e) {
            e.preventDefault();

            var $item = $(e.target).closest('.rseo-link-item');
            var url = $item.data('url');
            window.open(url, '_blank');
        },

        /**
         * Show temporary notice
         */
        showNotice: function(message) {
            var $notice = $('<div class="rseo-copied-notice">' + message + '</div>');
            $('body').append($notice);

            setTimeout(function() {
                $notice.remove();
            }, 2000);
        }
    };

    $(document).ready(function() {
        if ($('#rseo-link-suggestions-list').length) {
            RSEO_Links.init();
        }
    });

})(jQuery);
