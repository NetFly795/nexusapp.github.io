var lastGamesUrl = './projects.html'; // fallback when games url gets lost in the sauce

// mobile detection: narrow viewport only. ipads in landscape can stay fancy
function detectMobile() {
  const aspectRatio = window.innerWidth / window.innerHeight;
  const isMobile = aspectRatio < 0.8 || window.innerWidth < 768;

  if (isMobile) {
    document.body.classList.add('mobile');
    document.documentElement.style.setProperty('--is-mobile', '1');
  } else {
    document.body.classList.remove('mobile');
    document.documentElement.style.setProperty('--is-mobile', '0');
  }
  updateContentFrameHeightForMobile();
  
  // liquid glass resize if it exists (it might not. we're all improvising)
  if (window.liquidGlassResize) {
    window.liquidGlassResize();
  }
  
  // force reflow. webkit needs the emotional support
  document.body.offsetHeight;
}

// Run detection on load and resize
window.addEventListener('load', detectMobile);
window.addEventListener('resize', detectMobile);
window.addEventListener('orientationchange', detectMobile);
if (window.visualViewport) {
  window.visualViewport.addEventListener('resize', detectMobile);
}



function _mobileNavHeight() {
  if (typeof bottomNav !== 'undefined' && bottomNav && bottomNav.style.display === 'none') return 0;
  var screenWidth = window.innerWidth;
  if (screenWidth <= 360) return 50;
  if (screenWidth <= 480) return 55;
  return 60;
}

function _mobileFrameHeightPx() {
  var vv = window.visualViewport;
  var h = vv ? vv.height : window.innerHeight;
  return Math.max(200, h - _mobileNavHeight());
}

// Helper to update iframe heights on mobile / tablet
function updateContentFrameHeightForMobile() {
  var h = _mobileFrameHeightPx() + 'px';
  var content = document.getElementById('content-frame');
  var dash = document.getElementById('dash-preload-frame');
  if (document.body.classList.contains('mobile')) {
    if (content) content.style.height = h;
    if (dash) dash.style.height = h;
  } else {
    if (content) content.style.height = '';
    if (dash) dash.style.height = '';
  }
}

// Hide/show nav logic
var dockWrap = document.getElementById('dockWrap');
var dockCluster = document.getElementById('dockCluster');
var bottomNav = document.getElementById('bottomNav');
function getNavButtonRow() {
    return bottomNav ? (bottomNav.querySelector('.bottom-nav-row') || bottomNav) : null;
}
var hideNavBtn = document.getElementById('hideNavBtn');
var showNavBtn = document.getElementById('showNavBtn');
var contentFrame = document.getElementById('content-frame');
var backBtn = document.getElementById('backBtn');
var gamesBtn = document.querySelector('.nav-btn[data-glow="orange"]');

/* ── Dash preload helpers ──────────────────────────── */
var _pageTransitionTimer = null; // tracks the 350ms changePage timeout so we can cancel it
var _dashInitialAnimSent = false;

function sendDashDockOrigin(x, y, force) {
    const dashFrame = document.getElementById('dash-preload-frame');
    if (!dashFrame?.contentWindow) return;
    try {
        dashFrame.contentWindow.postMessage({
            type: 'nexus-dock-origin',
            x: (x != null) ? x : null,
            y: (y != null) ? y : null,
            force: !!force
        }, '*');
    } catch (_) {}
}

function sendInitialDashAnimation(force) {
    if (!force && _dashInitialAnimSent) return;
    _dashInitialAnimSent = true;
    sendDashDockOrigin(null, null, force);
}

function _sendDockOrigin(dashFrame, btn) {
    if (!btn) return;
    try {
        const r = btn.getBoundingClientRect();
        sendDashDockOrigin(r.left + r.width / 2, r.top + r.height / 2, true);
    } catch (_) {}
}

var PRELOADED_PAGES = {
    'dash/index.html': { frameId: 'dash-preload-frame', hiddenClass: 'dash-hidden' },
};

function _hideAllPreloads() {
    Object.values(PRELOADED_PAGES).forEach(cfg => {
        document.getElementById(cfg.frameId)?.classList.add(cfg.hiddenClass);
    });
}

function isHomeVisible() {
    const dash = document.getElementById('dash-preload-frame');
    return dash && !dash.classList.contains('dash-hidden');
}

function _shouldUnloadHomeOnLeave() {
    return window.NexusPerf && NexusPerf.isLite && NexusPerf.isLite();
}

function _suspendHomeFrame() {
    var dash = document.getElementById('dash-preload-frame');
    if (!dash || !_shouldUnloadHomeOnLeave()) return;
    var src = dash.getAttribute('src') || '';
    if (src && src !== 'about:blank') {
        dash.dataset.savedSrc = src;
        dash.src = 'about:blank';
    }
}

function _resumeHomeFrame(done) {
    var dash = document.getElementById('dash-preload-frame');
    if (!dash) { if (done) done(); return; }
    var saved = dash.dataset.savedSrc || 'dash/index.html';
    var cur = dash.getAttribute('src') || '';
    if (cur && cur !== 'about:blank') { if (done) done(); return; }
    var finished = false;
    function finish() {
        if (finished) return;
        finished = true;
        if (done) done();
    }
    dash.onload = finish;
    dash.src = saved;
    setTimeout(finish, 4000);
}

function _suspendContentFrame() {
    var frame = document.getElementById('content-frame');
    if (!frame || !_shouldUnloadHomeOnLeave()) return;
    var src = frame.getAttribute('src') || '';
    if (src && src !== 'about:blank') {
        frame.dataset.savedSrc = src;
        frame.src = 'about:blank';
    }
    frame.classList.remove('page-transition-in', 'page-transition-out');
    frame.style.opacity = '0';
}

function _syncFramePause() {
    if (!window.NexusPerf) return;
    var dash = document.getElementById('dash-preload-frame');
    var content = document.getElementById('content-frame');
    var homeVisible = isHomeVisible();
    NexusPerf.pauseFrame(dash, !homeVisible);
    NexusPerf.pauseFrame(content, homeVisible);
}

function fadeOutHomeThen(done) {
    const dash = document.getElementById('dash-preload-frame');
    const frame = document.getElementById('content-frame');
    if (!isHomeVisible()) {
        _hideAllPreloads();
        _syncFramePause();
        if (done) done();
        return;
    }
    dash.classList.remove('page-transition-in');
    dash.classList.add('page-transition-out');
    setTimeout(function() {
        dash.classList.add('dash-hidden');
        dash.classList.remove('page-transition-out');
        if (frame) {
            frame.style.opacity = '';
            frame.style.transition = '';
        }
        _suspendHomeFrame();
        _syncFramePause();
        if (done) done();
    }, 350);
}

function revealContentFrame() {
    const frame = document.getElementById('content-frame');
    if (!frame) return;
    frame.classList.remove('page-transition-out');
    frame.classList.add('page-transition-in');
}

function changePage(page, btn) {
    const preloadCfg  = PRELOADED_PAGES[page];
    const isPreloaded = !!preloadCfg;
    const lunaBtn = document.getElementById('lunaAiBtn');

    // ─── Already on this exact page → do nothing ───────────────────────
    if (isPreloaded && btn && btn.classList.contains('active')) return;

    if (_lunaDockOpen) closeLunaDock();

    // ─── Close settings / connected sidebar if open ────────────────────────
    if (document.getElementById('settingsPanel').classList.contains('open')) {
        hideSettingsPanel();
    }
    if (_connectedSidebarOpen) {
        hideConnectedSidebar();
    }

    // Cancel any in-flight transition timer
    if (_pageTransitionTimer) { clearTimeout(_pageTransitionTimer); _pageTransitionTimer = null; }

    // ─── Update nav immediately ─────────────────────────────────────────
    document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
    if (lunaBtn) lunaBtn.classList.remove('luna-open');
    if (btn) btn.classList.add('active');
    backBtn.style.display = 'none';

    // ─── Preloaded page (Home, Settings, …) ────────────────────────────
    if (isPreloaded) {
        const preloadEl = document.getElementById(preloadCfg.frameId);

        // Hide every other preload frame
        Object.entries(PRELOADED_PAGES).forEach(([p, cfg]) => {
            if (p !== page) document.getElementById(cfg.frameId)?.classList.add(cfg.hiddenClass);
        });

        // Cross-fade the content-frame out
        const frameEl = document.getElementById('content-frame');
        frameEl.style.transition = 'opacity 0.2s ease';
        frameEl.style.opacity    = '0';
        frameEl.classList.remove('page-transition-in');

        // Home gets a dock-origin card animation; other preloaded pages just reveal
        if (page === 'dash/index.html') {
            _suspendContentFrame();
            var revealHome = function() {
                updateContentFrameHeightForMobile();
                _sendDockOrigin(preloadEl, btn);
                try {
                    preloadEl.contentWindow.postMessage({ type: 'nexus-home-refresh' }, '*');
                } catch (_) {}
                preloadEl.classList.remove(preloadCfg.hiddenClass);
                preloadEl.classList.remove('page-transition-out');
                preloadEl.classList.add('page-transition-in');
                _syncFramePause();
            };
            if ((preloadEl.getAttribute('src') || '') === 'about:blank') {
                _resumeHomeFrame(revealHome);
            } else {
                revealHome();
            }
            return;
        }

        preloadEl?.classList.remove(preloadCfg.hiddenClass);
        preloadEl.classList.remove('page-transition-out');
        preloadEl.classList.add('page-transition-in');
        _syncFramePause();
        return;
    }

    // ─── Non-preloaded page ─────────────────────────────────────────────
    const loadContentPage = function() {
    const frame      = document.getElementById('content-frame');
    const loadingBar = document.getElementById('loadingBar');

    frame.style.transition = '';
    frame.style.opacity    = '';
    loadingBar.classList.add('loading');
    loadingBar.classList.remove('complete');
    frame.classList.add('page-transition-out');
    frame.classList.remove('page-transition-in');

    _pageTransitionTimer = setTimeout(() => {
        _pageTransitionTimer = null;
        frame.onload = () => {
            frame.classList.remove('page-transition-out');
            frame.classList.add('page-transition-in');
            loadingBar.classList.remove('loading');
            loadingBar.classList.add('complete');
            _syncFramePause();
            updateContentFrameHeightForMobile();
            if (window.NexusPerf) NexusPerf.propagate(window.NexusPerf.isLite());
            setTimeout(() => {
                loadingBar.classList.remove('complete');
                loadingBar.style.width = '0%';
            }, 700);
        };
        frame.src = page;
    }, _shouldUnloadHomeOnLeave() ? 80 : 350);
    };

    if (isHomeVisible()) {
        fadeOutHomeThen(loadContentPage);
    } else {
        _hideAllPreloads();
        loadContentPage();
        _syncFramePause();
    }
}

function showConnectedTooltip(btn, event) {
    if (event) event.stopPropagation();
    const connectedBtn = document.querySelector('.nav-btn[data-glow="purple"]');
    changePage('/connectedcenternew/dashboard.php', connectedBtn);
}

hideNavBtn && hideNavBtn.addEventListener('click', function(e) {
  e.preventDefault();
  hideDock();
});

showNavBtn && showNavBtn.addEventListener('click', function(e) {
  e.preventDefault();
  showDock();
});

// Fresh hide dock function
function hideDock() {
  if (_lunaDockOpen) closeLunaDock();
  dockCluster.classList.remove('show-anim');
  dockCluster.classList.add('hide-anim');
  setTimeout(() => {
    dockCluster.style.display = 'none';
    showNavBtn.style.display = 'flex';
    showNavBtn.classList.remove('drift-out');
    void showNavBtn.offsetWidth;
    showNavBtn.classList.add('drift-in');
    showNavBtn.style.visibility = 'visible';
    showNavBtn.style.opacity = '1';
    showNavBtn.style.pointerEvents = 'auto';
    updateContentFrameHeightForMobile();
  }, 250);
}

// Fresh show dock function
function showDock() {
  showNavBtn.classList.remove('drift-in');
  showNavBtn.classList.add('drift-out');
  showNavBtn.style.pointerEvents = 'none';
  setTimeout(() => {
    dockCluster.style.display = 'flex';
    void dockCluster.offsetWidth;
    dockCluster.classList.remove('hide-anim');
    dockCluster.classList.add('show-anim');
    showNavBtn.style.display = 'none';
    showNavBtn.style.visibility = 'hidden';
    showNavBtn.classList.remove('drift-out');
    showNavBtn.style.opacity = '0';
    updateContentFrameHeightForMobile();
  }, 320);
}

var _nexusBlocksEditorOpen = false;

function suppressDockForNexusBlocks() {
  if (_nexusBlocksEditorOpen) return;
  _nexusBlocksEditorOpen = true;
  document.body.classList.add('nexus-blocks-editor-active');
  dockCluster.style.display = 'none';
  showNavBtn.style.display = 'none';
  showNavBtn.style.visibility = 'hidden';
  showNavBtn.style.opacity = '0';
  showNavBtn.style.pointerEvents = 'none';
  dockCluster.classList.remove('show-anim');
  dockCluster.classList.add('hide-anim');
  const frame = document.getElementById('content-frame');
  if (frame) frame.style.height = '100vh';
  updateContentFrameHeightForMobile();
}

function restoreDockAfterNexusBlocks() {
  if (!_nexusBlocksEditorOpen) return;
  _nexusBlocksEditorOpen = false;
  document.body.classList.remove('nexus-blocks-editor-active');
  dockCluster.style.display = 'flex';
  showNavBtn.style.display = 'none';
  showNavBtn.style.visibility = 'hidden';
  showNavBtn.style.opacity = '0';
  showNavBtn.style.pointerEvents = 'none';
  void dockCluster.offsetWidth;
  dockCluster.classList.remove('hide-anim');
  dockCluster.classList.add('show-anim');
  const frame = document.getElementById('content-frame');
  if (frame) frame.style.height = '';
  updateContentFrameHeightForMobile();
}

// Show/hide back button when iframe loads
contentFrame.addEventListener('load', function() {
  const src = contentFrame.getAttribute('src');
});

backBtn.addEventListener('click', function() {
  contentFrame.src = lastGamesUrl;
  backBtn.style.display = 'none';
  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));
  gamesBtn.classList.add('active');
});

document.getElementById('gamesBackBtn').addEventListener('click', function() {
  const frame = document.getElementById('content-frame');
  
  // Start transition out
  frame.classList.add('page-transition-out');
  frame.classList.remove('page-transition-in');
  
  // Hide back button with animation
  this.classList.remove('button-enter');
  this.classList.add('button-exit');
  
  // After animation out completes
  setTimeout(() => {
    frame.src = lastGamesUrl;
    
    // Start transition in when page loads
    frame.onload = () => {
      frame.classList.remove('page-transition-out');
      frame.classList.add('page-transition-in');
      
      // Hide back button after animation
      setTimeout(() => {
        this.style.display = 'none';
      }, 300);
    };
  }, 350);
});

// Theme handling
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  const logo = document.querySelector('.bottom-nav img[alt="Nexus Logo"]');
  if (!logo) return;

  const isDark = localStorage.getItem('nexus_dark_mode') === 'true';
  if (isDark) {
    const darkTheme = localStorage.getItem('nexus_dark_theme') || 'nord';
    const darkLogos = {
      nord: 'submenus/images/deepblue.png',
      amber: 'submenus/images/cherryred.png',
      synthwave: 'submenus/images/purple.png'
    };
    logo.src = darkLogos[darkTheme] || 'nexuslogoapple.png';
    return;
  }

  if (theme === 'default' || !theme) {
    logo.src = 'nexuslogoapple.png';
  } else {
    logo.src = `submenus/images/${theme}.png`;
  }
}

function refreshDockLogo() {
  applyTheme(localStorage.getItem('nexus_theme') || 'default');
}

// Listen for theme changes
window.addEventListener('storage', function(e) {
  if (e.key === 'nexus_theme' || e.key === 'nexus_dark_mode' || e.key === 'nexus_dark_theme') {
    refreshDockLogo();
  }
});

/* expose shared refs for split scripts */
window.lastGamesUrl = typeof lastGamesUrl !== 'undefined' ? lastGamesUrl : './projects.html';
window.dockWrap = dockWrap;
window.dockCluster = dockCluster;
window.bottomNav = bottomNav;
window.hideNavBtn = hideNavBtn;
window.showNavBtn = showNavBtn;
window.contentFrame = contentFrame;
window.backBtn = backBtn;
window.gamesBtn = gamesBtn;
window.getNavButtonRow = getNavButtonRow;
window.detectMobile = detectMobile;
window.updateContentFrameHeightForMobile = updateContentFrameHeightForMobile;
window.changePage = changePage;
window.hideDock = hideDock;
window.showDock = showDock;
window.isHomeVisible = isHomeVisible;
window.fadeOutHomeThen = fadeOutHomeThen;
window.revealContentFrame = revealContentFrame;
window.suppressDockForNexusBlocks = suppressDockForNexusBlocks;
window.restoreDockAfterNexusBlocks = restoreDockAfterNexusBlocks;
window.applyTheme = applyTheme;
window.refreshDockLogo = refreshDockLogo;
window.sendInitialDashAnimation = sendInitialDashAnimation;
window._syncFramePause = _syncFramePause;

