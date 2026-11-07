/**
 * ONE blur layer to rule them all. we tried N× backdrop-filter per tile once.
 * safari sent us a cease and desist emotionally. never again.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_webgl_glass';
  var frostEl = null;
  var pageKind = null;

  function readPref() {
    try { return global.localStorage.getItem(STORAGE_KEY) || 'auto'; }
    catch (e) { return 'auto'; }
  }

  function shouldEnable() {
    return readPref() !== 'off';
  }

  function detectPage() {
    if (global.document.querySelector('.page')) return 'home';
    if (global.document.querySelector('.game-link') || global.document.querySelector('#mySearch')) {
      return 'games';
    }
    return null;
  }

  function bindEvents() {
    global.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'nexus-perf-pause' && frostEl) {
        frostEl.style.visibility = e.data.paused ? 'hidden' : '';
      }
    });

    global.document.addEventListener('visibilitychange', function () {
      if (!frostEl) return;
      var paused = global.document.hidden ||
        global.document.documentElement.classList.contains('nexus-perf-paused');
      frostEl.style.visibility = paused ? 'hidden' : '';
    });
  }

  function destroy() {
    if (frostEl) {
      if (pageKind === 'home') {
        if (frostEl.parentNode) frostEl.parentNode.removeChild(frostEl);
      } else {
        frostEl.removeAttribute('id');
        frostEl.classList.remove('nexus-glass-frost');
      }
    }
    frostEl = null;
    pageKind = null;
    global.document.documentElement.classList.remove('nexus-webgl-glass-active');
    global.document.documentElement.removeAttribute('data-nexus-glass-page');
  }

  function init() {
    if (!shouldEnable()) return false;
    if (frostEl) return true;

    pageKind = detectPage();
    if (!pageKind) return false;

    if (pageKind === 'home') {
      frostEl = global.document.createElement('div');
      frostEl.id = 'nexus-home-frost';
      frostEl.className = 'nexus-glass-frost';
      var pageEl = global.document.querySelector('.page');
      if (pageEl && pageEl.parentNode) {
        pageEl.parentNode.insertBefore(frostEl, pageEl);
      } else {
        global.document.body.appendChild(frostEl);
      }
    } else {
      /* games page: no frost plate. ios iframe scroll dies if you breathe on it wrong */
      frostEl = null;
    }

    if (frostEl) {
      frostEl.setAttribute('aria-hidden', 'true');
    }
    global.document.documentElement.classList.add('nexus-webgl-glass-active');
    global.document.documentElement.setAttribute('data-nexus-glass-page', pageKind);
    bindEvents();
    if (global.NexusGlassQuality) global.NexusGlassQuality.apply();
    return true;
  }

  function boot() {
    if (global.document.body) init();
    else global.document.addEventListener('DOMContentLoaded', init, { once: true });
  }

  function setEnabled(on) {
    try { global.localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off'); } catch (e) {}
    if (on) boot(); else destroy();
  }

  global.NexusWebGLGlass = {
    boot: boot,
    init: init,
    destroy: destroy,
    pause: function () { if (frostEl) frostEl.style.visibility = 'hidden'; },
    resume: function () {
      if (frostEl && !global.document.hidden &&
          !global.document.documentElement.classList.contains('nexus-perf-paused')) {
        frostEl.style.visibility = '';
      }
    },
    refresh: function () {},
    setEnabled: setEnabled,
    isActive: function () { return !!frostEl; }
  };

  boot();
})(window);
