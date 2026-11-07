/**
 * pre-baked aurora videos. yes we ffmpeg'd our feelings into webm at 3am.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_video_bg';
  var videoEl = null;
  var manifest = null;

  var FALLBACK_LIGHT = 'default';
  var FALLBACK_DARK = 'nord';

  function readPref() {
    try { return global.localStorage.getItem(STORAGE_KEY) || 'auto'; }
    catch (e) { return 'auto'; }
  }

  function shouldEnable() {
    var pref = readPref();
    if (pref === 'off') return false;
    if (pref === 'on') return true;
    return true;
  }

  function scriptBase() {
    var scripts = global.document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].getAttribute('src') || '';
      if (src.indexOf('nexus-video-bg.js') !== -1) {
        return src.split('nexus-video-bg.js')[0];
      }
    }
    return '';
  }

  function assetUrl(relative) {
    return scriptBase() + relative;
  }

  function isDark() {
    return global.document.documentElement.getAttribute('data-dark-mode') === 'true';
  }

  function lightThemeKey() {
    var key = global.document.documentElement.getAttribute('data-theme');
    if (!key && global.document.body) {
      key = global.document.body.getAttribute('data-nexus-theme');
    }
    if (!key) {
      try { key = global.localStorage.getItem('nexus_theme'); } catch (e) {}
    }
    return key || FALLBACK_LIGHT;
  }

  function darkThemeKey() {
    try {
      return global.localStorage.getItem('nexus_dark_theme') || FALLBACK_DARK;
    } catch (e) {
      return FALLBACK_DARK;
    }
  }

  function themeKey() {
    return isDark() ? darkThemeKey() : lightThemeKey();
  }

  function videoBasename() {
    return 'aurora-' + (isDark() ? 'dark' : 'light') + '-' + themeKey();
  }

  function pickSource() {
    var probe = global.document.createElement('video');
    var ext = (probe.canPlayType('video/webm; codecs="vp9"') || probe.canPlayType('video/webm'))
      ? '.webm' : '.mp4';
    var mode = isDark() ? 'dark' : 'light';
    var key = themeKey();
    var fallback = isDark() ? FALLBACK_DARK : FALLBACK_LIGHT;
    var names = [
      'aurora-' + mode + '-' + key,
      'aurora-' + mode + '-' + fallback,
      'aurora-light-default'
    ];
    var i;
    for (i = 0; i < names.length; i++) {
      if (i > 0 && names[i] === names[0]) continue;
      if (i > 1 && names[i] === names[1]) continue;
    }
    return assetUrl('../assets/aurora/' + names[0] + ext);
  }

  var fallbackIndex = 0;

  function applyMode() {
    if (!videoEl) return;
    fallbackIndex = 0;
    setVideoSrc(pickSource());
  }

  function setVideoSrc(src) {
    if (!videoEl) return;
    if (videoEl.dataset.currentSrc === src) return;
    videoEl.dataset.currentSrc = src;
    videoEl.onerror = function () {
      var probe = global.document.createElement('video');
      var ext = (probe.canPlayType('video/webm; codecs="vp9"') || probe.canPlayType('video/webm'))
        ? '.webm' : '.mp4';
      var mode = isDark() ? 'dark' : 'light';
      var fallback = isDark() ? FALLBACK_DARK : FALLBACK_LIGHT;
      var names = [
        'aurora-' + mode + '-' + themeKey(),
        'aurora-' + mode + '-' + fallback,
        'aurora-light-default'
      ];
      fallbackIndex += 1;
      if (fallbackIndex < names.length) {
        var next = assetUrl('../assets/aurora/' + names[fallbackIndex] + ext);
        if (next !== videoEl.dataset.currentSrc) setVideoSrc(next);
      }
    };
    videoEl.src = src;
    videoEl.load();
    var play = videoEl.play();
    if (play && play.catch) play.catch(function () {});
  }

  function pause() {
    if (!videoEl) return;
    videoEl.pause();
    global.document.documentElement.classList.add('nexus-video-bg-paused');
  }

  function resume() {
    if (!videoEl) return;
    global.document.documentElement.classList.remove('nexus-video-bg-paused');
    if (!global.document.hidden &&
        !global.document.documentElement.classList.contains('nexus-perf-paused')) {
      var play = videoEl.play();
      if (play && play.catch) play.catch(function () {});
    }
  }

  function destroy() {
    pause();
    if (videoEl && videoEl.parentNode) videoEl.parentNode.removeChild(videoEl);
    videoEl = null;
    global.document.documentElement.classList.remove('nexus-video-bg-active', 'nexus-video-bg-paused');
  }

  function bindEvents() {
    global.document.addEventListener('visibilitychange', function () {
      if (global.document.hidden) pause();
      else resume();
    });

    global.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'nexus-perf-pause') {
        if (e.data.paused) pause(); else resume();
      }
    });

    global.addEventListener('storage', function (e) {
      if (!videoEl) return;
      if (e.key === 'nexus_theme' || e.key === 'nexus_dark_theme' || e.key === 'nexus_dark_mode') {
        applyMode();
      }
    });

    if (global.MutationObserver && global.document.documentElement) {
      var obs = new global.MutationObserver(function () {
        applyMode();
      });
      obs.observe(global.document.documentElement, {
        attributes: true,
        attributeFilter: ['data-dark-mode', 'data-theme', 'data-nexus-theme']
      });
      if (global.document.body) {
        obs.observe(global.document.body, {
          attributes: true,
          attributeFilter: ['data-nexus-theme', 'data-dark-mode']
        });
      }
    }
  }

  function init() {
    if (!shouldEnable()) return false;
    if (videoEl) return true;

    videoEl = global.document.createElement('video');
    videoEl.id = 'nexus-video-bg';
    videoEl.setAttribute('aria-hidden', 'true');
    videoEl.muted = true;
    videoEl.loop = true;
    videoEl.playsInline = true;
    videoEl.autoplay = true;
    videoEl.preload = 'auto';
    videoEl.setAttribute('disablePictureInPicture', '');
    videoEl.setAttribute('disableRemotePlayback', '');

    var anchor = global.document.getElementById('nexus-webgl-bg') ||
      global.document.querySelector('.blurred-blue-ball') ||
      global.document.body.firstChild;
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(videoEl, anchor);
    } else {
      global.document.body.insertBefore(videoEl, global.document.body.firstChild);
    }

    global.document.documentElement.classList.add('nexus-video-bg-active');
    bindEvents();
    applyMode();

    if (global.document.documentElement.classList.contains('nexus-perf-paused') ||
        global.document.hidden) {
      pause();
    }

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

  function usesBackground() {
    return shouldEnable() && !!videoEl;
  }

  global.NexusVideoBg = {
    boot: boot,
    init: init,
    destroy: destroy,
    pause: pause,
    resume: resume,
    setEnabled: setEnabled,
    usesBackground: usesBackground,
    isActive: function () { return !!videoEl; },
    refresh: applyMode
  };

  boot();
})(window);
