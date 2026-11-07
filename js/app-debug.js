// ── Debug Console ──────────────────────────────────────────────
(function () {
  let debugOpen = false;
  let cmdHistory = [];
  let historyIdx = -1;

  function debugLog(text, type) {
    const out = document.getElementById('debugOutput');
    if (!out) return;
    const line = document.createElement('div');
    line.className = 'debug-line ' + (type || 'info');
    line.textContent = text;
    out.appendChild(line);
    out.scrollTop = out.scrollHeight;
  }

  function openDebug() {
    const el = document.getElementById('debugConsole');
    if (!el) return;
    el.classList.add('open');
    debugOpen = true;
    setTimeout(() => document.getElementById('debugInput')?.focus(), 80);
    if (window._debug_syncLiteToggle) window._debug_syncLiteToggle();
    if (window._debug_syncGlassQuality) window._debug_syncGlassQuality();
    if (window._debug_syncRainbowBg) window._debug_syncRainbowBg();
    debugLog('Debug console opened. Press ~ to close.', 'info');
  }

  function closeDebug() {
    const el = document.getElementById('debugConsole');
    if (!el) return;
    el.classList.remove('open');
    debugOpen = false;
  }

  function runDebugInput() {
    const inp = document.getElementById('debugInput');
    if (!inp) return;
    const code = inp.value.trim();
    if (!code) return;
    cmdHistory.unshift(code);
    historyIdx = -1;
    inp.value = '';
    debugLog(code, 'input');
    try {
      // eslint-disable-next-line no-eval
      const result = eval(code);
      const display = result === undefined ? '(undefined)' : JSON.stringify(result, null, 2) ?? String(result);
      debugLog(display, 'result');
    } catch (e) {
      debugLog(e.message, 'error');
    }
  }

  // Tilde key toggles console
  document.addEventListener('keydown', function (e) {
    if (e.key === '~' || (e.key === '`' && e.shiftKey)) {
      e.preventDefault();
      debugOpen ? closeDebug() : openDebug();
      return;
    }
    if (!debugOpen) return;
    if (e.key === 'Escape') { closeDebug(); return; }
    const inp = document.getElementById('debugInput');
    if (document.activeElement !== inp) return;
    if (e.key === 'Enter') { runDebugInput(); return; }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (cmdHistory.length) {
        historyIdx = Math.min(historyIdx + 1, cmdHistory.length - 1);
        inp.value = cmdHistory[historyIdx];
      }
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      historyIdx = Math.max(historyIdx - 1, -1);
      inp.value = historyIdx >= 0 ? cmdHistory[historyIdx] : '';
    }
  });

  // Expose helpers on window so users can call them from the console
  window.debugConsole = { log: debugLog, open: openDebug, close: closeDebug, isOpen: function() { return debugOpen; } };

  // Quick-action handlers
  window._debug_toggleCustomize = function () {
    debugLog('Calling toggleCustomizeMode()…', 'info');
    try { toggleCustomizeMode(); debugLog('Done.', 'result'); }
    catch (e) { debugLog(e.message, 'error'); }
  };

  window._debug_toggleDarkMode = function () {
    const body = document.body;
    const on = body.classList.toggle('dark-mode');
    if (on) {
      body.style.setProperty('--theme-tint', 'rgba(30,30,30,0.92)');
      body.style.setProperty('--theme-shadow', 'rgba(0,0,0,0.6)');
      body.style.setProperty('--theme-hover', '#333');
      body.style.setProperty('--theme-accent', '#90caf9');
      body.style.background = '#121212';
    } else {
      ['--theme-tint','--theme-shadow','--theme-hover','--theme-accent'].forEach(v => body.style.removeProperty(v));
      body.style.background = '';
    }
    debugLog('Dark mode ' + (on ? 'ON' : 'OFF'), 'result');
  };

  window._debug_toggleNexus9Dark = function () {
    const current = localStorage.getItem('nexus_dark_mode') === 'true';
    const next = !current;
    localStorage.setItem('nexus_dark_mode', next ? 'true' : 'false');
    const theme = localStorage.getItem('nexus_theme') || 'default';
    if (window.NexusApplyTheme) {
      window.NexusApplyTheme(theme);
      debugLog('Nexus 9 dark mode ' + (next ? 'ON' : 'OFF') + ' (theme: ' + (next ? (localStorage.getItem('nexus_dark_theme') || 'nord') : theme) + ')', 'result');
    } else {
      debugLog('NexusApplyTheme not found. theme-sync.js ghosted us.', 'error');
    }
  };

  window._debug_clearConsole = function () {
    const out = document.getElementById('debugOutput');
    if (out) out.innerHTML = '';
  };

  window._debug_syncLiteToggle = function () {
    const btn = document.getElementById('debugLiteToggle');
    if (!btn) return;
    const pref = localStorage.getItem('nexus_perf_tier') || 'auto';
    const active = window.NexusPerf ? NexusPerf.isLite() : pref === 'lite';
    btn.classList.toggle('active', active);
    btn.textContent = 'Lite Mode: ' + (active ? 'ON' : 'OFF');
    btn.title = pref === 'lite'
      ? 'Forced lite (debug). Click to restore auto.'
      : 'Click to force lite mode for debugging';
  };

  window._debug_toggleLiteMode = function () {
    const pref = localStorage.getItem('nexus_perf_tier') || 'auto';
    const next = pref === 'lite' ? 'auto' : 'lite';
    if (window.NexusPerf) {
      NexusPerf.setTier(next);
      debugLog('Perf tier → ' + next + (next === 'lite' ? ' (forced)' : ' (auto/device)'), 'result');
    } else {
      try { localStorage.setItem('nexus_perf_tier', next); } catch (e) {}
      debugLog('NexusPerf not loaded. stored pref only: ' + next, 'error');
    }
    window._debug_syncLiteToggle();
  };

  window._debug_glassQualityLabel = function (q) {
    if (q <= 0) return 'Flat fill';
    if (q >= 100) return 'Full glass';
    return 'Mixed';
  };

  window._debug_syncGlassQuality = function () {
    const slider = document.getElementById('debugGlassQuality');
    const valEl = document.getElementById('debugGlassQualityVal');
    const hint = document.getElementById('debugGlassQualityHint');
    if (!slider || !valEl) return;
    const q = window.NexusGlassQuality ? NexusGlassQuality.getQuality() : parseInt(localStorage.getItem('nexus_glass_quality') || '0', 10);
    slider.value = String(q);
    valEl.textContent = q + ' / ' + window._debug_glassQualityLabel(q);
    if (hint) {
      hint.textContent = q <= 0
        ? 'Panels use one logical color (tint × theme bg). No frost/blur.'
        : q >= 100
          ? 'Live frost + translucent panels (current default).'
          : 'Interpolating blur/frost opacity and panel fill.';
    }
  };

  window._debug_setGlassQuality = function (q) {
    q = Math.max(0, Math.min(100, Math.round(Number(q) || 0)));
    if (window.NexusGlassQuality) {
      NexusGlassQuality.setQuality(q);
    } else {
      try { localStorage.setItem('nexus_glass_quality', String(q)); } catch (e) {}
    }
    window._debug_syncGlassQuality();
    debugLog('Glass quality → ' + q + ' (' + window._debug_glassQualityLabel(q) + ')', 'result');
  };

  window._debug_syncRainbowBg = function () {
    const btn = document.getElementById('debugRainbowBgToggle');
    if (!btn) return;
    const on = window.NexusDebugRainbowBg
      ? NexusDebugRainbowBg.isEnabled()
      : localStorage.getItem('nexus_debug_rainbow_bg') === 'on';
    btn.classList.toggle('active', on);
    btn.textContent = 'Rainbow BG: ' + (on ? 'ON' : 'OFF');
    btn.title = on
      ? 'Spectrum stripes behind glass (debug). Click to restore aurora.'
      : 'Click for full-spectrum rainbow background (glass testing)';
  };

  window._debug_toggleRainbowBg = function () {
    let on = false;
    if (window.NexusDebugRainbowBg) {
      on = NexusDebugRainbowBg.toggle();
    } else {
      on = localStorage.getItem('nexus_debug_rainbow_bg') !== 'on';
      try { localStorage.setItem('nexus_debug_rainbow_bg', on ? 'on' : 'off'); } catch (e) {}
    }
    window._debug_syncRainbowBg();
    debugLog('Rainbow background ' + (on ? 'ON' : 'OFF'), 'result');
  };

  document.addEventListener('DOMContentLoaded', function () {
    const runBtn = document.getElementById('debugRunBtn');
    if (runBtn) runBtn.addEventListener('click', runDebugInput);
    const closeBtn = document.getElementById('debugConsoleClose');
    if (closeBtn) closeBtn.addEventListener('click', closeDebug);
    const glassSlider = document.getElementById('debugGlassQuality');
    if (glassSlider) {
      glassSlider.addEventListener('input', function () {
        window._debug_setGlassQuality(glassSlider.value);
      });
      window._debug_syncGlassQuality();
    }
    window._debug_syncRainbowBg();
  });
})();
