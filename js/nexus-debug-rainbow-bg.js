/**
 * rainbow debug plate. for when you need to know if the glass is lying to you.
 * (it always is.)
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_debug_rainbow_bg';

  function readEnabled() {
    try { return global.localStorage.getItem(STORAGE_KEY) === 'on'; }
    catch (e) { return false; }
  }

  function rainbowCss() {
    return 'repeating-linear-gradient(90deg,' +
      '#ff0000 0px,#ff0000 32px,' +
      '#ff8800 32px,#ff8800 64px,' +
      '#ffff00 64px,#ffff00 96px,' +
      '#00ff00 96px,#00ff00 128px,' +
      '#00ffff 128px,#00ffff 160px,' +
      '#0088ff 160px,#0088ff 192px,' +
      '#8800ff 192px,#8800ff 224px,' +
      '#ff00ff 224px,#ff00ff 256px' +
      ')';
  }

  function applyToDocument(doc, on) {
    if (!doc || !doc.documentElement) return;
    var root = doc.documentElement;
    root.classList.toggle('nexus-debug-rainbow-bg', on);
    root.style.setProperty('--nexus-debug-rainbow', on ? rainbowCss() : '');

    if (on) {
      var plate = doc.getElementById('nexus-debug-rainbow-plate');
      if (!plate) {
        plate = doc.createElement('div');
        plate.id = 'nexus-debug-rainbow-plate';
        plate.setAttribute('aria-hidden', 'true');
        var anchor = doc.getElementById('nexus-webgl-bg') ||
          doc.querySelector('.blurred-blue-ball') ||
          doc.body.firstChild;
        if (anchor && anchor.parentNode) {
          anchor.parentNode.insertBefore(plate, anchor);
        } else if (doc.body) {
          doc.body.insertBefore(plate, doc.body.firstChild);
        }
      }
      if (plate) plate.style.display = '';
    } else {
      var hidden = doc.getElementById('nexus-debug-rainbow-plate');
      if (hidden) hidden.style.display = 'none';
    }
  }

  function propagate(on) {
    var msg = { type: 'nexus-debug-rainbow-bg', enabled: !!on };
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
    var on = readEnabled();
    applyToDocument(rootDoc || global.document, on);
    if (!rootDoc || rootDoc === global.document) {
      propagate(on);
    }
    return on;
  }

  function setEnabled(on) {
    try {
      global.localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
    } catch (e) {}
    apply();
    return !!on;
  }

  function toggle() {
    return setEnabled(!readEnabled());
  }

  function bindEvents() {
    global.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'nexus-debug-rainbow-bg-request' && e.source) {
        try {
          e.source.postMessage({
            type: 'nexus-debug-rainbow-bg',
            enabled: readEnabled()
          }, '*');
        } catch (err) {}
      }
      if (e.data.type === 'nexus-debug-rainbow-bg') {
        applyToDocument(global.document, !!e.data.enabled);
      }
    });
  }

  function bootChild() {
    applyToDocument(global.document, readEnabled());
    try {
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({ type: 'nexus-debug-rainbow-bg-request' }, '*');
      }
    } catch (e) {}
  }

  global.NexusDebugRainbowBg = {
    isEnabled: readEnabled,
    setEnabled: setEnabled,
    toggle: toggle,
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
