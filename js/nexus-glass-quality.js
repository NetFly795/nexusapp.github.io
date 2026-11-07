/**
 * debug slider: 0 = flat panels (coward mode), 100 = full frost (based).
 * i leave it at weird numbers on purpose sometimes. for science.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_glass_quality';
  var DEFAULT_QUALITY = 0;

  function readStored() {
    try {
      var raw = global.localStorage.getItem(STORAGE_KEY);
      if (raw == null || raw === '') return DEFAULT_QUALITY;
      var n = parseInt(raw, 10);
      if (isNaN(n)) return DEFAULT_QUALITY;
      return Math.max(0, Math.min(100, n));
    } catch (e) {
      return DEFAULT_QUALITY;
    }
  }

  function parseRgba(str) {
    if (!str) return null;
    var m = String(str).trim().match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
    if (!m) return null;
    return [
      parseFloat(m[1]) / 255,
      parseFloat(m[2]) / 255,
      parseFloat(m[3]) / 255,
      m[4] != null ? parseFloat(m[4]) : 1
    ];
  }

  function parseHexColor(str) {
    str = (str || '').trim();
    var m = str.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
    if (!m) return null;
    var h = m[1];
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    return [
      parseInt(h.slice(0, 2), 16) / 255,
      parseInt(h.slice(2, 4), 16) / 255,
      parseInt(h.slice(4, 6), 16) / 255,
      1
    ];
  }

  function parseColor(str) {
    return parseRgba(str) || parseHexColor(str);
  }

  function readCssColor(doc, varName, fallback) {
    var root = doc && doc.documentElement ? doc.documentElement : global.document.documentElement;
    var val = global.getComputedStyle(root).getPropertyValue(varName).trim();
    return parseColor(val) || fallback;
  }

  function compositeFlat(tint, bg) {
    var a = tint[3];
    var ia = 1 - a;
    return [
      tint[0] * a + bg[0] * ia,
      tint[1] * a + bg[1] * ia,
      tint[2] * a + bg[2] * ia,
      1
    ];
  }

  function lerp(a, b, t) {
    return a + (b - a) * t;
  }

  function rgbaString(c) {
    var r = Math.round(Math.max(0, Math.min(255, c[0] * 255)));
    var g = Math.round(Math.max(0, Math.min(255, c[1] * 255)));
    var b = Math.round(Math.max(0, Math.min(255, c[2] * 255)));
    var a = Math.max(0, Math.min(1, c[3]));
    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + Math.round(a * 1000) / 1000 + ')';
  }

  function mixPanelFill(tint, flat, t) {
    return [
      lerp(flat[0], tint[0], t),
      lerp(flat[1], tint[1], t),
      lerp(flat[2], tint[2], t),
      lerp(1, tint[3], t)
    ];
  }

  function brightenFill(c, amount) {
    return [
      Math.min(1, c[0] + amount),
      Math.min(1, c[1] + amount),
      Math.min(1, c[2] + amount),
      Math.min(1, c[3] + amount * 0.35)
    ];
  }

  function applyToDocument(doc, quality) {
    if (!doc || !doc.documentElement) return;

    var t = quality / 100;
    var root = doc.documentElement;
    var tint = readCssColor(doc, '--theme-glass-tint', [1, 1, 1, 0.4]);
    var bg = readCssColor(doc, '--theme-bg', [0.949, 0.961, 0.976, 1]);
    var flat = compositeFlat(tint, bg);
    var fill = mixPanelFill(tint, flat, t);
    var fillHover = brightenFill(fill, (1 - t) * 0.04 + t * 0.08);

    root.style.setProperty('--nexus-glass-quality', String(t));
    root.style.setProperty('--nexus-glass-bg-blur', (28 * t).toFixed(2) + 'px');
    root.style.setProperty('--nexus-glass-frost-opacity', String(t));
    root.style.setProperty('--nexus-glass-bg-saturate', (100 + 80 * t).toFixed(0) + '%');
    root.style.setProperty('--nexus-glass-panel-fill', rgbaString(fill));
    root.style.setProperty('--nexus-glass-panel-fill-hover', rgbaString(fillHover));
    root.style.setProperty('--nexus-glass-flat-fill', rgbaString(flat));

    root.setAttribute('data-nexus-glass-quality', String(quality));
    root.classList.toggle('nexus-glass-quality-active', true);
    root.classList.toggle('nexus-glass-quality-flat', quality <= 0);
    root.classList.toggle('nexus-glass-quality-full', quality >= 100);
  }

  function propagate(quality) {
    var msg = { type: 'nexus-glass-quality', quality: quality };
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

  function apply(rootDoc) {
    var quality = readStored();
    applyToDocument(rootDoc || global.document, quality);
    if (!rootDoc || rootDoc === global.document) {
      propagate(quality);
    }
    return quality;
  }

  function setQuality(quality) {
    quality = Math.max(0, Math.min(100, Math.round(quality)));
    try {
      global.localStorage.setItem(STORAGE_KEY, String(quality));
    } catch (e) {}
    apply();
    return quality;
  }

  function bindEvents() {
    global.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'nexus-glass-quality-request' && e.source) {
        try {
          e.source.postMessage({
            type: 'nexus-glass-quality',
            quality: readStored()
          }, '*');
        } catch (err) {}
      }
      if (e.data.type === 'nexus-glass-quality') {
        applyToDocument(global.document, e.data.quality);
      }
    });

    if (global.MutationObserver && global.document.documentElement) {
      var themeObs = new global.MutationObserver(function () {
        apply();
      });
      themeObs.observe(global.document.documentElement, {
        attributes: true,
        attributeFilter: ['data-dark-mode', 'data-nexus-theme']
      });
    }
  }

  function bootChild() {
    applyToDocument(global.document, readStored());
    try {
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({ type: 'nexus-glass-quality-request' }, '*');
      }
    } catch (e) {}
  }

  global.NexusGlassQuality = {
    getQuality: readStored,
    setQuality: setQuality,
    apply: apply,
    propagate: propagate
  };

  bindEvents();

  if (global.document.documentElement) {
    if (global.parent && global.parent !== global) {
      bootChild();
    } else {
      apply();
    }
  } else {
    global.addEventListener('DOMContentLoaded', function () {
      if (global.parent && global.parent !== global) {
        bootChild();
      } else {
        apply();
      }
    });
  }
})(window);
