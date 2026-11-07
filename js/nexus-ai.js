/**
 * luna flags, play history, quick play pins. all in localStorage like animals.
 * don't @ me i had a deadline and feelings
 */
(function (global) {
  var STORAGE_KEY = 'nexus_ai_settings';
  var HISTORY_KEY = 'nexus_game_play_history';
  var QUICK_PLAY_KEY = 'nexus_quick_play_pins';
  var LAST_PLAYED_KEY = 'nexus_last_played_game';
  var QUICK_PLAY_MAX = 12;

  var DEFAULTS = {
    lunaGameRecommendations: true,
    lunaCopilot: true,
    lunaDockChat: true
  };

  function getSettings() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return Object.assign({}, DEFAULTS);
      return Object.assign({}, DEFAULTS, JSON.parse(raw));
    } catch (e) {
      return Object.assign({}, DEFAULTS);
    }
  }

  function isEnabled(key) {
    var s = getSettings();
    return s[key] !== false;
  }

  function setSetting(key, value) {
    var s = getSettings();
    s[key] = !!value;
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(s));
    } catch (e) {}
    try {
      global.dispatchEvent(new CustomEvent('nexus-ai-settings-changed', { detail: s }));
    } catch (e2) {}
  }

  function normalizeHref(href) {
    if (!href) return '';
    try {
      var base = global.location && global.location.href ? global.location.href : 'https://nexus.local/';
      var u = new URL(href, base);
      return (u.pathname + u.search).replace(/\/+$/, '') || u.pathname;
    } catch (e) {
      return String(href).split('#')[0];
    }
  }

  function dispatchQuickPlayChanged() {
    try {
      global.dispatchEvent(new CustomEvent('nexus-quick-play-changed'));
    } catch (e) {}
    try {
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({ type: 'nexus-quick-play-changed' }, '*');
      }
    } catch (e2) {}
  }

  function recordGamePlay(entry) {
    if (!entry || !entry.href) return;
    var canonical = entry.href;
    try {
      var list = JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
      list = list.filter(function (item) {
        return normalizeHref(item.href) !== normalizeHref(canonical);
      });
      list.unshift({
        name: entry.name || 'Game',
        href: canonical,
        image: entry.image || '',
        category: entry.category || '',
        playedAt: Date.now()
      });
      localStorage.setItem(HISTORY_KEY, JSON.stringify(list.slice(0, 80)));
    } catch (e) {}
    try {
      localStorage.setItem(LAST_PLAYED_KEY, JSON.stringify({
        name: entry.name || 'Game',
        href: canonical,
        image: entry.image || '',
        category: entry.category || '',
        playedAt: Date.now()
      }));
    } catch (e2) {}
    try {
      global.dispatchEvent(new CustomEvent('nexus-last-played-changed'));
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({ type: 'nexus-last-played-changed' }, '*');
      }
    } catch (e3) {}
  }

  function getPlayHistory() {
    try {
      return JSON.parse(localStorage.getItem(HISTORY_KEY) || '[]');
    } catch (e) {
      return [];
    }
  }

  function getLastPlayed() {
    try {
      var raw = localStorage.getItem(LAST_PLAYED_KEY);
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    var hist = getPlayHistory();
    return hist.length ? hist[0] : null;
  }

  function getQuickPlay() {
    try {
      return JSON.parse(localStorage.getItem(QUICK_PLAY_KEY) || '[]');
    } catch (e) {
      return [];
    }
  }

  function isQuickPlay(href) {
    var key = normalizeHref(href);
    if (!key) return false;
    return getQuickPlay().some(function (item) {
      return normalizeHref(item.href) === key;
    });
  }

  function addQuickPlay(entry) {
    if (!entry || !entry.href) return false;
    try {
      var key = normalizeHref(entry.href);
      var list = getQuickPlay().filter(function (item) {
        return normalizeHref(item.href) !== key;
      });
      list.unshift({
        name: entry.name || 'Game',
        href: entry.href,
        image: entry.image || '',
        category: entry.category || '',
        pinnedAt: Date.now()
      });
      localStorage.setItem(QUICK_PLAY_KEY, JSON.stringify(list.slice(0, QUICK_PLAY_MAX)));
      dispatchQuickPlayChanged();
      return true;
    } catch (e) {
      return false;
    }
  }

  function removeQuickPlay(href) {
    if (!href) return false;
    try {
      var key = normalizeHref(href);
      var list = getQuickPlay().filter(function (item) {
        return normalizeHref(item.href) !== key;
      });
      localStorage.setItem(QUICK_PLAY_KEY, JSON.stringify(list));
      dispatchQuickPlayChanged();
      return true;
    } catch (e) {
      return false;
    }
  }

  function toggleQuickPlay(entry) {
    if (!entry || !entry.href) return false;
    if (isQuickPlay(entry.href)) {
      removeQuickPlay(entry.href);
      return false;
    }
    addQuickPlay(entry);
    return true;
  }

  function formatRelativeTime(ts) {
    if (!ts) return '';
    var diff = Date.now() - ts;
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
    if (diff < 86400000) return Math.floor(diff / 3600000) + 'h ago';
    if (diff < 604800000) return Math.floor(diff / 86400000) + 'd ago';
    return new Date(ts).toLocaleDateString();
  }

  global.NexusAI = {
    STORAGE_KEY: STORAGE_KEY,
    HISTORY_KEY: HISTORY_KEY,
    QUICK_PLAY_KEY: QUICK_PLAY_KEY,
    LAST_PLAYED_KEY: LAST_PLAYED_KEY,
    QUICK_PLAY_MAX: QUICK_PLAY_MAX,
    DEFAULTS: DEFAULTS,
    getSettings: getSettings,
    isEnabled: isEnabled,
    setSetting: setSetting,
    normalizeHref: normalizeHref,
    recordGamePlay: recordGamePlay,
    getPlayHistory: getPlayHistory,
    getLastPlayed: getLastPlayed,
    getQuickPlay: getQuickPlay,
    isQuickPlay: isQuickPlay,
    addQuickPlay: addQuickPlay,
    removeQuickPlay: removeQuickPlay,
    toggleQuickPlay: toggleQuickPlay,
    formatRelativeTime: formatRelativeTime
  };
})(typeof window !== 'undefined' ? window : this);
