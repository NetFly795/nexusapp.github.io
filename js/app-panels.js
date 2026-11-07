/* settings panel slide logic. css transforms are my love language */
var _settingsPrevActiveBtn = null;

function _ensureLazyFrame(frame) {
    if (!frame || !frame.dataset.lazySrc) return;
    var src = frame.getAttribute('src') || '';
    if (!src || src === 'about:blank') {
        frame.src = frame.dataset.lazySrc;
    }
}

function showSettingsPanel(btn) {
    if (document.getElementById('settingsPanel').classList.contains('open')) return;
    if (_connectedSidebarOpen) hideConnectedSidebar();
    if (_lunaDockOpen) closeLunaDock();
    _ensureLazyFrame(document.getElementById('settingsPanelFrame'));
    _settingsPrevActiveBtn = document.querySelector('.nav-btn.active') || null;
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    var lunaBtn = document.getElementById('lunaAiBtn');
    if (lunaBtn) lunaBtn.classList.remove('luna-open');
    if (btn) btn.classList.add('active');
    document.getElementById('settingsPanelBackdrop').classList.add('show');
    document.getElementById('settingsPanel').classList.add('open');
}

function openConnectedAccountSettings(openAuth) {
    showSettingsPanel(document.getElementById('settingsBtn'));
    setTimeout(function() {
        var frame = document.getElementById('settingsPanelFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({
                type: 'open-connected-account',
                auth: openAuth || null
            }, '*');
        }
    }, 350);
}

function hideSettingsPanel() {
    document.getElementById('settingsPanelBackdrop').classList.remove('show');
    document.getElementById('settingsPanel').classList.remove('open');
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    if (_settingsPrevActiveBtn) {
        _settingsPrevActiveBtn.classList.add('active');
        _settingsPrevActiveBtn = null;
    }
}

/* connected sidebar panel. slide from the right like a dramatic entrance */
var _connectedSidebarOpen = false;

function showConnectedSidebar(btn) {
    if (document.getElementById('settingsPanel').classList.contains('open')) {
        hideSettingsPanel();
    }
    _ensureLazyFrame(document.getElementById('connectedSidebarFrame'));
    document.getElementById('connectedSidebarBackdrop').classList.add('show');
    document.getElementById('connectedSidebarPanel').classList.add('open');
    _connectedSidebarOpen = true;
    if (btn) btn.classList.add('sidebar-open');
    try {
        var frame = document.getElementById('connectedSidebarFrame');
        if (frame && frame.contentWindow) {
            frame.contentWindow.postMessage({ type: 'cc-sidebar-refresh' }, '*');
        }
    } catch (_) {}
    if (window.refreshConnectedDmBadge) window.refreshConnectedDmBadge();
}

function hideConnectedSidebar() {
    document.getElementById('connectedSidebarBackdrop').classList.remove('show');
    document.getElementById('connectedSidebarPanel').classList.remove('open');
    _connectedSidebarOpen = false;
    var btn = document.getElementById('connectedSidebarBtn');
    if (btn) btn.classList.remove('sidebar-open');
}

function toggleConnectedSidebar(btn) {
    if (_connectedSidebarOpen) {
        hideConnectedSidebar();
        return;
    }
    showConnectedSidebar(btn);
}

/* luna dock morph. the dock grows a chat panel. body horror but make it cute */
var _lunaDockOpen = false;
var _lunaFrameLoaded = false;
var _activeGameContext = null;

function openLunaCopilot(gameName) {
    if (window.NexusAI && !NexusAI.isEnabled('lunaCopilot')) return;
    if (_lunaDockOpen) closeLunaDock();
    if (_connectedSidebarOpen) hideConnectedSidebar();
    if (document.getElementById('settingsPanel').classList.contains('open')) {
        hideSettingsPanel();
    }
    const name = gameName || (_activeGameContext && _activeGameContext.name) || '';
    if (window.LunaCopilot) LunaCopilot.open(name);
}

function closeLunaCopilot() {
    if (window.LunaCopilot) LunaCopilot.close();
}

function _ensureLunaFrameLoaded(onReady) {
    const frame = document.getElementById('lunaFrame');
    if (!frame) return;
    if (_lunaFrameLoaded) {
        if (onReady) onReady(false);
        return;
    }
    const src = frame.getAttribute('src') || '';
    if (src && src !== 'about:blank') {
        _lunaFrameLoaded = true;
        if (onReady) onReady(false);
        return;
    }
    frame.addEventListener('load', function () {
        _lunaFrameLoaded = true;
        if (onReady) onReady(true);
    }, { once: true });
    frame.src = 'luna/index.html';
}

function _postLunaDockMessage(firstOpen) {
    try {
        const frame = document.getElementById('lunaFrame');
        const lunaBtn = document.getElementById('lunaAiBtn');
        if (!frame || !frame.contentWindow || !lunaBtn) return;
        const r = lunaBtn.getBoundingClientRect();
        frame.contentWindow.postMessage({
            type: firstOpen ? 'nexus-dock-origin' : 'luna-dock-reopen',
            x: r.left + r.width / 2,
            y: r.top + r.height / 2
        }, '*');
    } catch (_) {}
}

function openLunaDock() {
    if (_lunaDockOpen) return;
    if (window.NexusAI && !NexusAI.isEnabled('lunaDockChat')) return;
    if (document.getElementById('settingsPanel').classList.contains('open')) {
        hideSettingsPanel();
    }
    if (_connectedSidebarOpen) hideConnectedSidebar();

    const backdrop = document.getElementById('lunaDockBackdrop');
    const panel = document.getElementById('lunaDockPanel');
    const btn = document.getElementById('lunaAiBtn');
    if (backdrop) backdrop.classList.add('show');
    if (panel) panel.setAttribute('aria-hidden', 'false');
    dockWrap.classList.add('luna-open');
    if (btn) {
        btn.classList.add('luna-open');
        btn.setAttribute('aria-expanded', 'true');
        btn.title = 'Close Luna AI';
    }
    _lunaDockOpen = true;

    _ensureLunaFrameLoaded(function (firstOpen) {
        setTimeout(function () { _postLunaDockMessage(firstOpen); }, firstOpen ? 480 : 120);
    });
}

function closeLunaDock() {
    if (!_lunaDockOpen) return;
    const backdrop = document.getElementById('lunaDockBackdrop');
    const panel = document.getElementById('lunaDockPanel');
    const btn = document.getElementById('lunaAiBtn');
    if (backdrop) backdrop.classList.remove('show');
    if (panel) panel.setAttribute('aria-hidden', 'true');
    dockWrap.classList.remove('luna-open');
    if (btn) {
        btn.classList.remove('luna-open');
        btn.setAttribute('aria-expanded', 'false');
        btn.title = 'Luna AI';
    }
    _lunaDockOpen = false;
}

function toggleLunaDock(ev) {
    if (ev) {
        ev.preventDefault();
        ev.stopPropagation();
    }
    if (_activeGameContext && window.NexusAI && NexusAI.isEnabled('lunaCopilot')) {
        const panel = document.getElementById('lunaCopilotPanel');
        if (panel && panel.classList.contains('open')) closeLunaCopilot();
        else openLunaCopilot(_activeGameContext.name);
        return;
    }
    if (window.NexusAI && !NexusAI.isEnabled('lunaDockChat')) return;
    if (_lunaDockOpen) closeLunaDock();
    else openLunaDock();
}

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && _lunaDockOpen) closeLunaDock();
});


window.showSettingsPanel = showSettingsPanel;
window.hideSettingsPanel = hideSettingsPanel;
window.openConnectedAccountSettings = openConnectedAccountSettings;
window.showConnectedSidebar = showConnectedSidebar;
window.hideConnectedSidebar = hideConnectedSidebar;
window.toggleConnectedSidebar = toggleConnectedSidebar;
window.openLunaCopilot = openLunaCopilot;
window.closeLunaCopilot = closeLunaCopilot;
window.openLunaDock = openLunaDock;
window.closeLunaDock = closeLunaDock;
window.toggleLunaDock = toggleLunaDock;

