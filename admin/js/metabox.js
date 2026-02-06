/**
 * RendanIT SEO Metabox JS
 * Version 1.3.2 - Server-side analysis via AJAX
 */
(function($) {
    'use strict';

    $(document).ready(function() {

        // Tab switching
        $(document).on('click', '.rseo-mtab', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');

            $('.rseo-mtab').removeClass('active');
            $(this).addClass('active');

            $('.rseo-mtab-content').removeClass('active');
            $('#rseo-tab-' + tab).addClass('active');
        });

        // Title input - update preview
        $('#rseo_title').on('input', function() {
            var val = $(this).val();
            var sep = (typeof rseoMetabox !== 'undefined' && rseoMetabox.separator) ? rseoMetabox.separator : '|';
            var site = (typeof rseoMetabox !== 'undefined' && rseoMetabox.siteName) ? rseoMetabox.siteName : '';
            var postTitle = getPostTitle();

            var displayTitle = val || (postTitle + ' ' + sep + ' ' + site);
            $('#rseo-preview-title').text(displayTitle);

            // Character count
            var len = val.length;
            $(this).siblings('.rseo-char-count').find('.rseo-count').text(len);
        });

        // Description input - update preview
        $('#rseo_description').on('input', function() {
            var val = $(this).val();
            $('#rseo-preview-desc').text(val || 'Meta leírás...');

            var len = val.length;
            $(this).siblings('.rseo-char-count').find('.rseo-count').text(len);
        });

        // Init counts
        $('#rseo_title').trigger('input');
        $('#rseo_description').trigger('input');

        /**
         * Get post title from various sources (for preview only)
         */
        function getPostTitle() {
            var title = '';

            // 1. Classic Editor - standard #title field
            title = $('#title').val();
            if (title) return title;

            // 2. Gutenberg - post title from wp.data
            if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
                var editor = wp.data.select('core/editor');
                if (editor && editor.getEditedPostAttribute) {
                    title = editor.getEditedPostAttribute('title');
                    if (title) return title;
                }
            }

            // 3. Gutenberg - DOM fallback
            title = $('.editor-post-title__input').val() || $('.editor-post-title__input').text();
            if (title) return title;

            // 4. Post title input name fallback
            title = $('input[name="post_title"]').val();
            if (title) return title;

            return '';
        }

        // SEO Analysis - use event delegation for dynamic button
        $(document).on('click', '#rseo-run-analysis', function(e) {
            e.preventDefault();
            e.stopPropagation();
            runAnalysis();
            return false;
        });

        /**
         * Run analysis via AJAX (server-side)
         * This ensures Elementor content is properly analyzed
         */
        function runAnalysis() {
            var $results = $('#rseo-analysis-results');

            // Show loading state
            $results.html('<div style="text-align:center;padding:40px;"><span class="spinner is-active" style="float:none;"></span><p>Elemzés folyamatban...</p></div>');

            // Check if we have the required data
            if (typeof rseoMetabox === 'undefined' || !rseoMetabox.postId) {
                $results.html('<div style="padding:15px;background:#f8d7da;color:#721c24;border-radius:4px;">Hiba: Hiányzó post ID. Mentsd el először a bejegyzést!</div>');
                return;
            }

            // Make AJAX call to server
            $.ajax({
                url: rseoMetabox.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rseo_run_analysis',
                    nonce: rseoMetabox.nonce,
                    post_id: rseoMetabox.postId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        renderAnalysisResults(response.data);
                    } else {
                        $results.html('<div style="padding:15px;background:#f8d7da;color:#721c24;border-radius:4px;">Hiba történt az elemzés során. Próbáld újra!</div>');
                    }
                },
                error: function() {
                    $results.html('<div style="padding:15px;background:#f8d7da;color:#721c24;border-radius:4px;">Hálózati hiba. Ellenőrizd a kapcsolatot és próbáld újra!</div>');
                }
            });
        }

        /**
         * Render analysis results from server response
         */
        function renderAnalysisResults(data) {
            var score = data.score || 0;
            var grade = data.grade || 'F';
            var checks = data.checks || [];
            var categories = data.categories || {};

            // Score color
            var scoreColor = '#d63638';
            if (score >= 80) scoreColor = '#00a32a';
            else if (score >= 55) scoreColor = '#dba617';
            else if (score >= 30) scoreColor = '#e65100';

            // Build HTML
            var html = '';

            // Score header
            html += '<div style="text-align:center;margin-bottom:20px;padding:20px;background:#f8f9fa;border-radius:8px;">';
            html += '<div style="font-size:56px;font-weight:bold;color:' + scoreColor + '">' + score + '</div>';
            html += '<div style="font-size:24px;font-weight:bold;color:' + scoreColor + ';margin-top:5px;">' + grade + '</div>';
            html += '<div style="color:#666;margin-top:5px;">SEO Pontszám</div>';
            html += '</div>';

            // Category summary
            html += '<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px;">';
            var categoryLabels = {
                'meta': '📝 Meta',
                'content': '📄 Tartalom',
                'keyword': '🔑 Kulcsszó',
                'media': '🖼️ Média',
                'links': '🔗 Linkek',
                'technical': '⚙️ Tech',
                'social': '📱 Social',
                'readability': '📖 Olvashatóság'
            };
            for (var cat in categories) {
                var catData = categories[cat];
                var catPercent = catData.total > 0 ? Math.round((catData.earned / catData.total) * 100) : 0;
                var catColor = catPercent >= 70 ? '#00a32a' : (catPercent >= 40 ? '#dba617' : '#d63638');
                var catLabel = categoryLabels[cat] || cat;
                html += '<div style="flex:1;min-width:80px;text-align:center;padding:8px;background:#fff;border:1px solid #ddd;border-radius:4px;">';
                html += '<div style="font-size:10px;color:#666;">' + catLabel + '</div>';
                html += '<div style="font-size:16px;font-weight:bold;color:' + catColor + '">' + catPercent + '%</div>';
                html += '</div>';
            }
            html += '</div>';

            // Individual checks
            checks.forEach(function(check) {
                var bgColor, textColor, icon;

                if (check.severity === 'good') {
                    bgColor = '#d4edda';
                    textColor = '#155724';
                    icon = '✅';
                } else if (check.severity === 'critical') {
                    bgColor = '#f8d7da';
                    textColor = '#721c24';
                    icon = '❌';
                } else if (check.severity === 'warning') {
                    bgColor = '#fff3cd';
                    textColor = '#856404';
                    icon = '⚠️';
                } else {
                    bgColor = '#cce5ff';
                    textColor = '#004085';
                    icon = 'ℹ️';
                }

                html += '<div style="padding:10px 12px;margin:5px 0;border-radius:4px;background:' + bgColor + ';color:' + textColor + '">';
                html += '<div>' + icon + ' ' + check.message + '</div>';
                if (check.fix && check.severity !== 'good') {
                    html += '<div style="font-size:11px;margin-top:4px;opacity:0.8;">💡 ' + check.fix + '</div>';
                }
                html += '</div>';
            });

            // Re-analyze button
            html += '<div style="text-align:center;margin-top:20px;">';
            html += '<button type="button" class="button button-primary" id="rseo-run-analysis">🔄 Újra elemzés</button>';
            html += '<p style="font-size:11px;color:#666;margin-top:10px;">Az elemzés a mentett tartalmat vizsgálja. Változtatások után mentsd el a bejegyzést az újraelemzéshez!</p>';
            html += '</div>';

            $('#rseo-analysis-results').html(html);
        }

        // Auto-run analysis when switching to analysis tab
        $(document).on('click', '.rseo-mtab[data-tab="analysis"]', function() {
            setTimeout(runAnalysis, 200);
        });

        // Run analysis on page load if analysis tab is active
        if ($('#rseo-tab-analysis').hasClass('active')) {
            setTimeout(runAnalysis, 500);
        }

    });

})(jQuery);
