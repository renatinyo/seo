/**
 * RendanIT SEO Metabox JS
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
            var sep = rseoMetabox.separator || '|';
            var site = rseoMetabox.siteName || '';
            var postTitle = $('#title').val() || '';

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

        // SEO Analysis
        $('#rseo-run-analysis').on('click', function() {
            runAnalysis();
        });

        function runAnalysis() {
            var results = [];
            var title = $('#rseo_title').val();
            var desc = $('#rseo_description').val();
            var focus = $('#rseo_focus_keyword').val();
            var postTitle = $('#title').val();
            var content = '';

            // Try to get content from editor
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                content = tinymce.get('content').getContent({ format: 'text' });
            } else {
                content = $('#content').val() || '';
            }

            var sep = rseoMetabox.separator || '|';
            var site = rseoMetabox.siteName || '';
            var effectiveTitle = title || (postTitle + ' ' + sep + ' ' + site);

            // Title checks
            if (!title) {
                results.push({ type: 'warning', text: '⚠️ Egyedi SEO title nincs megadva (automatikus generálás aktív)' });
            } else if (title.length > 60) {
                results.push({ type: 'error', text: '❌ SEO title túl hosszú (' + title.length + '/60 karakter)' });
            } else if (title.length < 20) {
                results.push({ type: 'warning', text: '⚠️ SEO title túl rövid (' + title.length + '/60 karakter)' });
            } else {
                results.push({ type: 'good', text: '✅ SEO title optimális hosszúságú (' + title.length + '/60)' });
            }

            // Description checks
            if (!desc) {
                results.push({ type: 'error', text: '❌ Meta description hiányzik!' });
            } else if (desc.length > 155) {
                results.push({ type: 'warning', text: '⚠️ Meta description túl hosszú (' + desc.length + '/155)' });
            } else if (desc.length < 50) {
                results.push({ type: 'warning', text: '⚠️ Meta description túl rövid (' + desc.length + '/155)' });
            } else {
                results.push({ type: 'good', text: '✅ Meta description optimális (' + desc.length + '/155)' });
            }

            // Focus keyword checks
            if (!focus) {
                results.push({ type: 'warning', text: '⚠️ Nincs fókusz kulcsszó megadva' });
            } else {
                var focusLower = focus.toLowerCase();

                // In title
                if (effectiveTitle.toLowerCase().indexOf(focusLower) !== -1) {
                    results.push({ type: 'good', text: '✅ Fókusz kulcsszó szerepel a title-ben' });
                } else {
                    results.push({ type: 'error', text: '❌ Fókusz kulcsszó NEM szerepel a title-ben' });
                }

                // In description
                if (desc && desc.toLowerCase().indexOf(focusLower) !== -1) {
                    results.push({ type: 'good', text: '✅ Fókusz kulcsszó szerepel a meta descriptionben' });
                } else {
                    results.push({ type: 'warning', text: '⚠️ Fókusz kulcsszó nem szerepel a meta descriptionben' });
                }

                // In content
                if (content) {
                    var contentLower = content.toLowerCase();
                    var kwCount = (contentLower.split(focusLower).length - 1);
                    var wordCount = content.split(/\s+/).length;
                    var density = wordCount > 0 ? ((kwCount / wordCount) * 100).toFixed(1) : 0;

                    if (kwCount === 0) {
                        results.push({ type: 'error', text: '❌ Fókusz kulcsszó nem szerepel a tartalomban!' });
                    } else {
                        results.push({ type: 'good', text: '✅ Fókusz kulcsszó ' + kwCount + 'x szerepel a tartalomban (sűrűség: ' + density + '%)' });
                    }
                }

                // In URL
                var permalink = $('#sample-permalink a').text() || '';
                var slug = permalink.split('/').filter(Boolean).pop() || '';
                if (slug.toLowerCase().indexOf(focusLower.replace(/\s+/g, '-')) !== -1) {
                    results.push({ type: 'good', text: '✅ Fókusz kulcsszó szerepel az URL-ben' });
                } else {
                    results.push({ type: 'warning', text: '⚠️ Fókusz kulcsszó nem szerepel az URL slug-ban' });
                }
            }

            // Content length
            if (content) {
                var wordCount = content.split(/\s+/).length;
                if (wordCount < 100) {
                    results.push({ type: 'error', text: '❌ Nagyon kevés tartalom (' + wordCount + ' szó, ajánlott min. 300)' });
                } else if (wordCount < 300) {
                    results.push({ type: 'warning', text: '⚠️ Kevés tartalom (' + wordCount + ' szó, ajánlott min. 300)' });
                } else {
                    results.push({ type: 'good', text: '✅ Tartalom hossz megfelelő (' + wordCount + ' szó)' });
                }
            }

            // H1 check (check if post title is set)
            if (!postTitle) {
                results.push({ type: 'error', text: '❌ Nincs cím (H1) megadva!' });
            } else {
                results.push({ type: 'good', text: '✅ Cím (H1) megvan' });
            }

            // Render results
            var html = '<h4>SEO Elemzés Eredménye</h4>';
            var score = 0;
            var total = results.length;

            results.forEach(function(r) {
                var cls = r.type === 'good' ? 'rseo-analysis-good' : (r.type === 'error' ? 'rseo-analysis-error' : 'rseo-analysis-warning');
                html += '<div class="' + cls + '" style="padding:6px 10px;margin:4px 0;border-radius:4px;';
                if (r.type === 'good') html += 'background:#d4edda;color:#155724';
                else if (r.type === 'error') html += 'background:#f8d7da;color:#721c24';
                else html += 'background:#fff3cd;color:#856404';
                html += '">' + r.text + '</div>';

                if (r.type === 'good') score += 100;
                else if (r.type === 'warning') score += 50;
            });

            var percentage = total > 0 ? Math.round(score / total) : 0;
            var scoreColor = percentage >= 70 ? '#00a32a' : (percentage >= 40 ? '#dba617' : '#d63638');
            html = '<div style="text-align:center;margin-bottom:15px;">' +
                '<span style="font-size:48px;font-weight:bold;color:' + scoreColor + '">' + percentage + '%</span>' +
                '<br><span style="color:#666">SEO Pontszám</span></div>' + html;

            $('#rseo-analysis-results').html(html);
        }

    });

})(jQuery);
