/**
 * RendanIT SEO - Bulk Editor JavaScript
 */
(function($) {
    'use strict';

    var RSEO_Bulk = {
        currentPage: 1,
        totalPages: 1,
        changes: {},
        filters: {},

        init: function() {
            this.bindEvents();
            this.loadPosts();
        },

        bindEvents: function() {
            // Filters
            $('#rseo-apply-filters').on('click', this.applyFilters.bind(this));
            $('#rseo-reset-filters').on('click', this.resetFilters.bind(this));

            // Pagination
            $('#rseo-prev-page').on('click', this.prevPage.bind(this));
            $('#rseo-next-page').on('click', this.nextPage.bind(this));

            // Track changes
            $(document).on('input', '.rseo-bulk-table input, .rseo-bulk-table textarea', this.trackChange.bind(this));

            // Save all
            $('#rseo-save-all').on('click', this.saveAll.bind(this));

            // Export
            $('#rseo-export-csv').on('click', this.exportCSV.bind(this));

            // Post type change - toggle category filter
            $('#rseo-filter-post-type').on('change', function() {
                var isPost = $(this).val() === 'post';
                $('.rseo-filter-category').toggle(isPost);
            });
        },

        /**
         * Load posts via AJAX
         */
        loadPosts: function() {
            var self = this;
            var $tbody = $('#rseo-bulk-tbody');

            $tbody.html('<tr class="rseo-loading-row"><td colspan="5">Betöltés...</td></tr>');

            $.post(rseoBulk.ajaxUrl, {
                action: 'rseo_bulk_load_posts',
                nonce: rseoBulk.nonce,
                post_type: $('#rseo-filter-post-type').val(),
                page: this.currentPage,
                search: $('#rseo-filter-search').val(),
                category: $('#rseo-filter-category').val(),
                score_filter: $('#rseo-filter-score').val()
            }, function(response) {
                if (response.success) {
                    self.renderPosts(response.data.posts);
                    self.totalPages = response.data.total_pages;
                    self.updatePagination(response.data);
                } else {
                    $tbody.html('<tr><td colspan="5">Hiba a betöltéskor</td></tr>');
                }
            }).fail(function() {
                $tbody.html('<tr><td colspan="5">Hiba a betöltéskor</td></tr>');
            });
        },

        /**
         * Render posts in table
         */
        renderPosts: function(posts) {
            var $tbody = $('#rseo-bulk-tbody');
            $tbody.empty();

            if (!posts.length) {
                $tbody.html('<tr><td colspan="5" style="text-align:center;padding:40px;">Nincs találat</td></tr>');
                return;
            }

            var self = this;

            posts.forEach(function(post) {
                var scoreClass = 'rseo-score-poor';
                if (post.seo_score >= 80) scoreClass = 'rseo-score-excellent';
                else if (post.seo_score >= 60) scoreClass = 'rseo-score-good';
                else if (post.seo_score >= 40) scoreClass = 'rseo-score-fair';

                var $row = $('<tr data-id="' + post.ID + '"></tr>');

                // Title column
                $row.append(
                    '<td class="column-title">' +
                    '<div class="rseo-post-title"><a href="' + post.edit_link + '" target="_blank">' + self.escapeHtml(post.post_title) + '</a></div>' +
                    '<div class="rseo-post-meta">' + post.post_type + '</div>' +
                    '<div class="rseo-post-links">' +
                    '<a href="' + post.permalink + '" target="_blank">Megtekintés ↗</a>' +
                    '</div>' +
                    '</td>'
                );

                // SEO Title
                $row.append(
                    '<td class="column-seo-title">' +
                    '<input type="text" name="seo_title" value="' + self.escapeAttr(post.seo_title) + '" data-original="' + self.escapeAttr(post.seo_title) + '" maxlength="70" placeholder="' + self.escapeAttr(post.post_title) + '">' +
                    '<div class="rseo-char-counter"><span class="rseo-char-count">' + (post.seo_title || '').length + '</span>/60</div>' +
                    '</td>'
                );

                // SEO Description
                $row.append(
                    '<td class="column-seo-desc">' +
                    '<textarea name="seo_desc" data-original="' + self.escapeAttr(post.seo_desc) + '" maxlength="160" placeholder="Meta leírás...">' + self.escapeHtml(post.seo_desc) + '</textarea>' +
                    '<div class="rseo-char-counter"><span class="rseo-char-count">' + (post.seo_desc || '').length + '</span>/155</div>' +
                    '</td>'
                );

                // Focus Keyword
                $row.append(
                    '<td class="column-focus-kw">' +
                    '<input type="text" name="focus_kw" value="' + self.escapeAttr(post.focus_kw) + '" data-original="' + self.escapeAttr(post.focus_kw) + '" placeholder="Kulcsszó...">' +
                    '</td>'
                );

                // Score
                $row.append(
                    '<td class="column-score">' +
                    '<span class="rseo-score-badge ' + scoreClass + '">' + post.seo_score + '</span>' +
                    '<span class="rseo-grade">' + post.seo_grade + '</span>' +
                    '</td>'
                );

                $tbody.append($row);
            });

            // Update char counters
            this.bindCharCounters();
        },

        /**
         * Bind char counters
         */
        bindCharCounters: function() {
            $('.rseo-bulk-table input, .rseo-bulk-table textarea').each(function() {
                var $input = $(this);
                var $counter = $input.closest('td').find('.rseo-char-count');
                if ($counter.length) {
                    $counter.text($input.val().length);
                }
            });
        },

        /**
         * Track changes
         */
        trackChange: function(e) {
            var $input = $(e.target);
            var $row = $input.closest('tr');
            var postId = $row.data('id');
            var field = $input.attr('name');
            var value = $input.val();
            var original = $input.data('original') || '';

            // Update char counter
            var $counter = $input.closest('td').find('.rseo-char-counter');
            if ($counter.length) {
                var len = value.length;
                var max = field === 'seo_title' ? 60 : (field === 'seo_desc' ? 155 : 999);
                $counter.find('.rseo-char-count').text(len);
                $counter.toggleClass('rseo-over', len > max);
            }

            // Track change
            if (value !== original) {
                if (!this.changes[postId]) {
                    this.changes[postId] = {};
                }
                this.changes[postId][field] = value;
                $input.closest('td').addClass('rseo-changed');
            } else {
                if (this.changes[postId]) {
                    delete this.changes[postId][field];
                    if (Object.keys(this.changes[postId]).length === 0) {
                        delete this.changes[postId];
                    }
                }
                $input.closest('td').removeClass('rseo-changed');
            }

            this.updateSaveButton();
        },

        /**
         * Update save button state
         */
        updateSaveButton: function() {
            var count = Object.keys(this.changes).length;
            $('.rseo-change-count').text(count);
            $('#rseo-save-all').prop('disabled', count === 0);
        },

        /**
         * Save all changes
         */
        saveAll: function() {
            var self = this;

            if (Object.keys(this.changes).length === 0) {
                return;
            }

            if (!confirm(rseoBulk.strings.confirm)) {
                return;
            }

            var $button = $('#rseo-save-all');
            $button.prop('disabled', true).text(rseoBulk.strings.saving);

            $.post(rseoBulk.ajaxUrl, {
                action: 'rseo_bulk_save',
                nonce: rseoBulk.nonce,
                changes: JSON.stringify(this.changes)
            }, function(response) {
                if (response.success) {
                    // Clear changes
                    self.changes = {};
                    $('.rseo-changed').removeClass('rseo-changed');

                    // Update originals
                    $('.rseo-bulk-table input, .rseo-bulk-table textarea').each(function() {
                        $(this).data('original', $(this).val());
                    });

                    self.updateSaveButton();
                    $('.rseo-bulk-status').text(rseoBulk.strings.saved).fadeIn();
                    setTimeout(function() {
                        $('.rseo-bulk-status').fadeOut();
                    }, 3000);

                    // Reload to get new scores
                    self.loadPosts();
                } else {
                    alert(response.data || rseoBulk.strings.error);
                }
            }).fail(function() {
                alert(rseoBulk.strings.error);
            }).always(function() {
                $button.text('💾 Változtatások mentése (' + Object.keys(self.changes).length + ')');
            });
        },

        /**
         * Apply filters
         */
        applyFilters: function() {
            this.currentPage = 1;
            this.loadPosts();
        },

        /**
         * Reset filters
         */
        resetFilters: function() {
            $('#rseo-filter-post-type').val('post');
            $('#rseo-filter-category').val('');
            $('#rseo-filter-score').val('');
            $('#rseo-filter-search').val('');
            $('.rseo-filter-category').show();
            this.currentPage = 1;
            this.loadPosts();
        },

        /**
         * Pagination
         */
        prevPage: function() {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadPosts();
            }
        },

        nextPage: function() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
                this.loadPosts();
            }
        },

        updatePagination: function(data) {
            $('#rseo-current-page').text(data.current);
            $('#rseo-total-pages').text(data.total_pages);
            $('#rseo-prev-page').prop('disabled', data.current <= 1);
            $('#rseo-next-page').prop('disabled', data.current >= data.total_pages);
        },

        /**
         * Export CSV
         */
        exportCSV: function() {
            var posts = [];
            $('.rseo-bulk-table tbody tr').each(function() {
                var $row = $(this);
                if ($row.hasClass('rseo-loading-row')) return;

                posts.push({
                    id: $row.data('id'),
                    title: $row.find('.rseo-post-title a').text(),
                    seo_title: $row.find('input[name="seo_title"]').val(),
                    seo_desc: $row.find('textarea[name="seo_desc"]').val(),
                    focus_kw: $row.find('input[name="focus_kw"]').val(),
                    score: $row.find('.rseo-score-badge').text()
                });
            });

            if (!posts.length) {
                alert('Nincs exportálható adat.');
                return;
            }

            // Generate CSV
            var csv = 'ID,Cím,SEO Title,Meta Description,Fókusz kulcsszó,Pontszám\n';
            posts.forEach(function(p) {
                csv += p.id + ',"' + p.title.replace(/"/g, '""') + '","' + p.seo_title.replace(/"/g, '""') + '","' + p.seo_desc.replace(/"/g, '""') + '","' + p.focus_kw.replace(/"/g, '""') + '",' + p.score + '\n';
            });

            // Download
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'seo-export-' + new Date().toISOString().slice(0, 10) + '.csv';
            link.click();
        },

        /**
         * Helpers
         */
        escapeHtml: function(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        escapeAttr: function(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;');
        }
    };

    $(document).ready(function() {
        RSEO_Bulk.init();
    });

})(jQuery);
