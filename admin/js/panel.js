/**
 * RendanIT SEO Panel
 * AJAX-based score panel - loads on demand only
 */

var rseoLoaded = false;
var rseoPanelOpen = false;

function rseoTogglePanel() {
    var panel = document.getElementById('rseo-panel-overlay');
    if (!panel) return;

    rseoPanelOpen = !rseoPanelOpen;

    if (rseoPanelOpen) {
        panel.classList.remove('rseo-panel-hidden');
        document.body.style.overflow = 'hidden';

        // Load score on first open
        if (!rseoLoaded) {
            rseoLoadScore(false);
        }
    } else {
        panel.classList.add('rseo-panel-hidden');
        document.body.style.overflow = '';
    }
}

function rseoRefreshScore() {
    rseoLoadScore(true);
}

function rseoLoadScore(force) {
    var body = document.getElementById('rseo-panel-body');
    if (!body) return;

    // Show loading
    body.innerHTML = '<div class="rseo-panel-loading"><div class="rseo-spinner"></div><p>Elemzés folyamatban...</p></div>';

    var data = new FormData();
    data.append('nonce', rseoPanel.nonce);

    if (rseoPanel.isHome && !rseoPanel.postId) {
        // Homepage without a page – use homepage audit
        data.append('action', 'rseo_get_homepage_score');
    } else if (rseoPanel.postId) {
        data.append('action', 'rseo_get_score');
        data.append('post_id', rseoPanel.postId);
        if (force) data.append('force', '1');
    } else {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#666;">' +
            '<p style="font-size:48px;margin:0;">🔍</p>' +
            '<p>Ez az oldal nem egy egyedi bejegyzés/oldal, így nincs egyedi SEO pontszáma.</p>' +
            '<p><a href="' + (rseoPanel.ajaxUrl.replace('admin-ajax.php', 'admin.php?page=rendanit-seo-audit')) + '" style="color:#2271b1;">Menj az SEO Audit oldalra →</a></p>' +
            '</div>';
        rseoLoaded = true;
        return;
    }

    fetch(rseoPanel.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(function(res) { return res.json(); })
    .then(function(response) {
        if (response.success) {
            body.innerHTML = response.data.html;
            rseoLoaded = true;

            // Update admin bar score
            var abScore = document.getElementById('rseo-ab-score-value');
            if (abScore) {
                abScore.textContent = response.data.score + '/100';
                abScore.style.color = response.data.color;
            }
        } else {
            body.innerHTML = '<div style="text-align:center;padding:40px;color:#d63638;">' +
                '<p>Hiba történt az elemzés során.</p>' +
                '<p><button class="rseo-btn" onclick="rseoRefreshScore()">🔄 Újrapróbálás</button></p></div>';
        }
    })
    .catch(function(err) {
        body.innerHTML = '<div style="text-align:center;padding:40px;color:#d63638;">' +
            '<p>Hálózati hiba: ' + err.message + '</p>' +
            '<p><button class="rseo-btn" onclick="rseoRefreshScore()">🔄 Újrapróbálás</button></p></div>';
    });
}

// Close on overlay click
document.addEventListener('click', function(e) {
    if (e.target && e.target.id === 'rseo-panel-overlay') {
        rseoTogglePanel();
    }
});

// Close on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && rseoPanelOpen) {
        rseoTogglePanel();
    }
});

// Auto-load score in admin bar on page load (lightweight – only reads cache)
document.addEventListener('DOMContentLoaded', function() {
    if (!rseoPanel.postId && !rseoPanel.isHome) return;

    var data = new FormData();
    data.append('nonce', rseoPanel.nonce);

    if (rseoPanel.isHome && !rseoPanel.postId) {
        data.append('action', 'rseo_get_homepage_score');
    } else {
        data.append('action', 'rseo_get_score');
        data.append('post_id', rseoPanel.postId);
    }

    fetch(rseoPanel.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
    })
    .then(function(res) { return res.json(); })
    .then(function(response) {
        if (response.success) {
            var abScore = document.getElementById('rseo-ab-score-value');
            if (abScore) {
                abScore.textContent = response.data.score + '/100';
                abScore.style.color = response.data.color;
            }
        }
    })
    .catch(function() {});
});
