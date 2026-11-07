/**
 * ipad mode. not a vibe choice. safari made us do it.
 * (still looks good tho. we're so back.)
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_perf_tier';

  function isIPadLike() {
    var ua = global.navigator.userAgent || '';
    if (/iPad/.test(ua)) return true;
    if (global.navigator.maxTouchPoints > 1 && /MacIntel/.test(global.navigator.platform || '')) {
      return true;
    }
    return false;
  }

  function readPref() {
    try {
      return global.localStorage.getItem(STORAGE_KEY) || 'auto';
    } catch (e) {
      return 'auto';
    }
  }

  function shouldUseLite() {
    var pref = readPref();
    if (pref === 'full') return false;
    if (pref === 'lite') return true;
    /* auto: ipads only. desktops get the full cursed blur experience they deserve */
    return isIPadLike();
  }

  function tierName(lite) {
    return lite ? 'lite' : 'full';
  }

  function applyToDocument(doc, lite) {
    if (!doc || !doc.documentElement) return;
    var tier = tierName(lite);
    doc.documentElement.setAttribute('data-nexus-perf', tier);
    if (doc.body) {
      doc.body.classList.toggle('nexus-perf-lite', lite);
    }
    try {
      if (doc.defaultView && doc.defaultView.NexusApplyTheme) {
        doc.defaultView.NexusApplyTheme(
          global.localStorage.getItem('nexus_theme') || 'default'
        );
      }
    } catch (e) {}
  }

  function apply(rootDoc) {
    var lite = shouldUseLite();
    applyToDocument(rootDoc || global.document, lite);
    propagate(lite);
    return lite;
  }

  function propagate(lite) {
    var tier = tierName(lite);
    var msg = { type: 'nexus-perf-tier', tier: tier };
    try {
      var iframes = global.document.getElementsByTagName('iframe');
      for (var i = 0; i < iframes.length; i++) {
        try {
          if (iframes[i].contentWindow) {
            iframes[i].contentWindow.postMessage(msg, '*');
          }
        } catch (e) {}
      }
    } catch (e2) {}
  }

  function pauseFrame(frame, paused) {
    if (!frame) return;
    try {
      if (frame.contentWindow) {
        frame.contentWindow.postMessage({ type: 'nexus-perf-pause', paused: !!paused }, '*');
      }
    } catch (e) {}
  }

  function setTier(tier) {
    try {
      global.localStorage.setItem(STORAGE_KEY, tier);
    } catch (e) {}
    apply();
  }

  function isLite() {
    return global.document.documentElement.getAttribute('data-nexus-perf') === 'lite';
  }

  function isTouchTablet() {
    return isIPadLike() || (global.navigator.maxTouchPoints > 1 && global.innerWidth <= 1366);
  }

  function bootChildFrame() {
    applyToDocument(global.document, shouldUseLite());
    try {
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({ type: 'nexus-perf-request' }, '*');
      }
    } catch (e) {}
  }

  global.NexusPerf = {
    isLite: isLite,
    isIPadLike: isIPadLike,
    isTouchTablet: isTouchTablet,
    shouldUseLite: shouldUseLite,
    apply: apply,
    propagate: propagate,
    pauseFrame: pauseFrame,
    setTier: setTier,
    bootChildFrame: bootChildFrame
  };

  global.addEventListener('message', function (e) {
    if (!e.data) return;
    if (e.data.type === 'nexus-perf-request' && e.source) {
      try {
        e.source.postMessage({
          type: 'nexus-perf-tier',
          tier: tierName(isLite())
        }, '*');
      } catch (err) {}
    }
    if (e.data.type === 'nexus-perf-tier') {
      applyToDocument(global.document, e.data.tier === 'lite');
    }
    if (e.data.type === 'nexus-perf-pause') {
      global.document.documentElement.classList.toggle('nexus-perf-paused', !!e.data.paused);
    }
  });

  if (global.document.documentElement) {
    if (global.parent && global.parent !== global) {
      bootChildFrame();
    } else {
      apply();
    }
  } else {
    global.addEventListener('DOMContentLoaded', function () {
      if (global.parent && global.parent !== global) {
        bootChildFrame();
      } else {
        apply();
      }
    });
  }
})(window);
