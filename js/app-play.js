function playGameFromShell(href, title) {
    if (!href) return;
    href = href.replace(/^\.\.?\/moderngame\//, '').replace(/^\.\//, '');
    window._pendingGamePlay = { href: href, title: title || 'Game' };
    const gamesBtn = document.querySelector('.nav-btn[data-glow="orange"]');
    const frame = document.getElementById('content-frame');
    const gamesPage = './moderngame/index.html';

    function sendPendingPlay() {
        var pending = window._pendingGamePlay;
        if (!pending) return false;
        try {
            if (frame.contentWindow) {
                frame.contentWindow.postMessage({
                    type: 'nexus-play-game',
                    href: pending.href,
                    title: pending.title
                }, '*');
                window._pendingGamePlay = null;
                return true;
            }
        } catch (_) {}
        return false;
    }

    function activateGamesNav() {
        document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
        if (gamesBtn) gamesBtn.classList.add('active');
    }

    function retryPendingPlay() {
        if (!sendPendingPlay()) {
            var attempts = 0;
            var retry = setInterval(function() {
                if (sendPendingPlay() || ++attempts > 25) clearInterval(retry);
            }, 120);
        }
    }

    const currentSrc = frame.getAttribute('src') || '';
    const onGamesFrame = currentSrc.includes('moderngame');
    const homeVisible = isHomeVisible();

    if (onGamesFrame && !homeVisible) {
        activateGamesNav();
        retryPendingPlay();
        return;
    }

    activateGamesNav();

    if (onGamesFrame && homeVisible) {
        fadeOutHomeThen(function() {
            revealContentFrame();
            retryPendingPlay();
        });
        return;
    }

    changePage(gamesPage, gamesBtn);
}

window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'nexus-open-connected-account') {
        openConnectedAccountSettings();
    }
    if (e.data && e.data.type === 'nexus-open-connected-auth') {
        openConnectedAccountSettings(e.data.mode || 'login');
    }
    if (e.data && e.data.type === 'nexus-play-last-game' && e.data.href) {
        playGameFromShell(e.data.href, e.data.title || e.data.name);
    }
    if (e.data && e.data.type === 'nexus-games-ready' && window._pendingGamePlay) {
        playGameFromShell(window._pendingGamePlay.href, window._pendingGamePlay.title);
    }
    if (e.data && e.data.type === 'nexus-open-connected') {
        hideConnectedSidebar();
        changePage('/connectedcenternew/dashboard.php', document.querySelector('.nav-btn[data-glow="purple"]'));
    }
    if (e.data && e.data.type === 'cc-auth-success') {
        try {
            var csFrame = document.getElementById('connectedSidebarFrame');
            if (csFrame && csFrame.contentWindow) {
                csFrame.contentWindow.postMessage({ type: 'cc-sidebar-refresh' }, '*');
            }
        } catch (_) {}
        try {
            var mainFrame = document.getElementById('content-frame');
            if (mainFrame && mainFrame.contentWindow) {
                mainFrame.contentWindow.postMessage({ type: 'cc-auth-success' }, '*');
            }
        } catch (_) {}
        try {
            var lunaFrame = document.getElementById('lunaFrame');
            if (lunaFrame && lunaFrame.contentWindow) {
                lunaFrame.contentWindow.postMessage({ type: 'cc-auth-success' }, '*');
            }
        } catch (_) {}
        if (window.refreshConnectedDmBadge) window.refreshConnectedDmBadge();
    }
    if (e.data && e.data.type === 'nexus-game-active') {
        _activeGameContext = e.data.game || null;
    }
    if (e.data && e.data.type === 'nexus-game-inactive') {
        _activeGameContext = null;
        closeLunaCopilot();
    }
    if (e.data && e.data.type === 'nexus-open-luna-copilot') {
        openLunaCopilot((e.data.game && e.data.game.name) || undefined);
    }
    if (e.data && e.data.type === 'nexus-blocks-editor-open') {
        suppressDockForNexusBlocks();
    }
    if (e.data && e.data.type === 'nexus-blocks-editor-close') {
        restoreDockAfterNexusBlocks();
    }
    if (e.data && e.data.type === 'nexus-debug-log') {
        var d = e.data;
        var lvl = d.level || 'info';
        var logFn = window.debugConsole && window.debugConsole.log;
        if (logFn) {
            if (d.line) {
                logFn(d.line, lvl);
            } else {
                var meta = d.meta ? ' ' + JSON.stringify(d.meta) : '';
                logFn('[' + (d.source || '?') + '] ' + (d.message || '') + meta, lvl);
            }
        }
    }
    if (e.data && e.data.type === 'nexus-dash-ready') {
        sendInitialDashAnimation();
    }
    if (e.data && e.data.type === 'nexus-quick-play-changed') {
        try {
            var dashFrame = document.getElementById('dash-preload-frame');
            if (dashFrame && dashFrame.contentWindow) {
                dashFrame.contentWindow.postMessage({ type: 'nexus-quick-play-changed' }, '*');
            }
        } catch (_) {}
    }
    if (e.data && e.data.type === 'nexus-last-played-changed') {
        try {
            var dashFrame2 = document.getElementById('dash-preload-frame');
            if (dashFrame2 && dashFrame2.contentWindow) {
                dashFrame2.contentWindow.postMessage({ type: 'nexus-home-refresh' }, '*');
            }
        } catch (_) {}
    }
});

window.playGameFromShell = playGameFromShell;

