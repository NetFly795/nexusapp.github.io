(function (global) {
  'use strict';

  var CATALOG_URL = 'games.json';
  var _games = [];
  var _ready = false;
  var _readyPromise = null;

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function makeId(game, index) {
    if (game.id) return String(game.id);
    var href = game.href || '';
    var m = href.match(/projects\/([^/]+)\//);
    if (m) return m[1];
    m = href.match(/^\.\.\/([^/]+)\//);
    if (m) return m[1];
    return 'game-' + index;
  }

  function renderGame(game, index) {
    var a = document.createElement('a');
    a.href = game.href || '#';
    a.className = 'game-link container';
    a.setAttribute('data-category', game.category || '');
    a.setAttribute('data-game-id', makeId(game, index));
    a.innerHTML =
      '<div class="game-tile">' +
      (game.icon
        ? '<img class="game-icon" src="' + escapeHtml(game.icon) + '" alt="icon" loading="lazy" />'
        : '') +
      '<h1 class="game-title">' + escapeHtml(game.title || 'Game') + '</h1>' +
      '</div>';
    return a;
  }

  function renderGrid(games) {
    var grid = document.getElementById('search');
    if (!grid) return;
    grid.innerHTML = '';
    grid.setAttribute('data-intro-pending', '1');
    var frag = document.createDocumentFragment();
    for (var i = 0; i < games.length; i++) {
      frag.appendChild(renderGame(games[i], i));
    }
    grid.appendChild(frag);
  }

  function normalizePayload(data) {
    if (!data) return [];
    if (Array.isArray(data)) return data;
    if (Array.isArray(data.games)) return data.games;
    return [];
  }

  function markReady() {
    _ready = true;
    try {
      global.dispatchEvent(new CustomEvent('nexus-games-catalog-ready', { detail: { games: _games } }));
    } catch (e) {}
  }

  function load() {
    if (_readyPromise) return _readyPromise;
    _readyPromise = fetch(CATALOG_URL, { cache: 'no-cache' })
      .then(function (r) {
        if (!r.ok) throw new Error('games.json HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        _games = normalizePayload(data);
        renderGrid(_games);
        markReady();
        return _games;
      })
      .catch(function (err) {
        console.error('[NexusGamesCatalog]', err);
        _games = [];
        markReady();
        return _games;
      });
    return _readyPromise;
  }

  global.NexusGamesCatalog = {
    load: load,
    getGames: function () { return _games.slice(); },
    isReady: function () { return _ready; },
    whenReady: function () { return load(); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
})(window);
