/**
 * luna picks games based on what you played. it's vibes-based ML. don't ask.
 */
(function () {
  'use strict';

  var ICON = '../luna/lunaicon.png';

  function randInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function directGameLinks(grid) {
    return Array.prototype.filter.call(grid.children, function (el) {
      return el.nodeType === 1
        && el.tagName === 'A'
        && el.classList.contains('game-link')
        && !el.hasAttribute('data-luna-rec-card');
    });
  }

  function buildCatalog(grid) {
    if (window.NexusGamesCatalog && typeof NexusGamesCatalog.getGames === 'function') {
      var fromJson = NexusGamesCatalog.getGames();
      if (fromJson && fromJson.length) {
        return fromJson.map(function (g) {
          return {
            href: g.href || '',
            category: g.category || '',
            title: g.title || 'Game',
            icon: g.icon || ''
          };
        }).filter(function (g) { return g.href; });
      }
    }
    return directGameLinks(grid).map(function (a) {
      var titleEl = a.querySelector('.game-title');
      var iconEl = a.querySelector('.game-icon');
      return {
        href: a.getAttribute('href') || '',
        category: a.getAttribute('data-category') || '',
        title: titleEl ? titleEl.textContent.replace(/\s+/g, ' ').trim() : 'Game',
        icon: iconEl ? (iconEl.getAttribute('src') || '') : ''
      };
    }).filter(function (g) { return g.href; });
  }

  function getLastUniqueCategories(history, limit) {
    var seen = {};
    var out = [];
    for (var i = 0; i < history.length; i++) {
      var cat = history[i].category;
      if (!cat || seen[cat]) continue;
      seen[cat] = true;
      out.push(cat);
      if (out.length >= limit) break;
    }
    return out;
  }

  function pickRandomFromCategory(catalog, category, used) {
    var pool = catalog.filter(function (g) {
      return g.category === category && !used[g.href];
    });
    if (!pool.length) return null;
    return pool[randInt(0, pool.length - 1)];
  }

  function buildRecSet(catalog, history, count) {
    var used = {};
    var picks = [];
    var cats = getLastUniqueCategories(history, 3);

    cats.forEach(function (cat) {
      var game = pickRandomFromCategory(catalog, cat, used);
      if (game) {
        picks.push(game);
        used[game.href] = true;
      }
    });

    while (picks.length < count) {
      var pool = catalog.filter(function (g) { return !used[g.href]; });
      if (!pool.length) break;
      var g = pool[randInt(0, pool.length - 1)];
      picks.push(g);
      used[g.href] = true;
    }

    return picks;
  }

  function createRecStrip(games) {
    var strip = document.createElement('div');
    strip.className = 'luna-rec-strip';
    strip.setAttribute('data-luna-rec', '1');

    var cluster = document.createElement('div');
    cluster.className = 'luna-rec-cluster';

    var head = document.createElement('div');
    head.className = 'luna-rec-head';
    head.innerHTML = '<img src="' + ICON + '" alt="" class="luna-rec-logo" decoding="async">'
      + '<span class="luna-rec-label">Recommended by Luna</span>';
    cluster.appendChild(head);

    var box = document.createElement('div');
    box.className = 'luna-rec-box';

    var row = document.createElement('div');
    row.className = 'luna-rec-row';

    games.forEach(function (g) {
      var card = document.createElement('a');
      card.className = 'game-link container';
      card.setAttribute('href', g.href);
      card.setAttribute('data-category', g.category || '');
      card.setAttribute('data-luna-rec-card', '1');
      card.innerHTML = '<div class="game-tile">'
        + (g.icon ? '<img class="game-icon" src="' + g.icon + '" alt="icon" loading="lazy">' : '')
        + '<h1 class="game-title">' + escapeHtml(g.title) + '</h1>'
        + '</div>';
      if (document.getElementById('search') && document.getElementById('search').hasAttribute('data-intro-pending')) {
        var tileEl = card.querySelector('.game-tile');
        if (tileEl) {
          tileEl.style.opacity = '0';
          tileEl.style.transform = 'scale(0.84) translateY(42px)';
        }
      }
      row.appendChild(card);
    });

    box.appendChild(row);
    cluster.appendChild(box);
    strip.appendChild(cluster);
    return strip;
  }

  function escapeHtml(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function clearRecommendations(grid) {
    if (!grid) return;
    grid.querySelectorAll('[data-luna-rec]').forEach(function (el) { el.remove(); });
    delete grid.dataset.lunaRecsDone;
  }

  function injectRecommendations() {
    if (!window.NexusAI || !NexusAI.isEnabled('lunaGameRecommendations')) return false;

    var grid = document.getElementById('search');
    if (!grid) return false;
    if (grid.dataset.lunaRecsDone === '1') return true;

    var catalog = buildCatalog(grid);
    if (catalog.length < 3) return false;

    grid.dataset.lunaRecsDone = '1';

    var history = NexusAI.getPlayHistory();

    var top = buildRecSet(catalog, history, 3);
    if (top.length) {
      grid.insertBefore(createRecStrip(top), grid.firstChild);
    }

    var links = directGameLinks(grid);
    var count = 0;
    var nextAt = randInt(9, 15);
    var anchors = [];

    links.forEach(function (link) {
      count++;
      if (count >= nextAt) {
        anchors.push(link);
        count = 0;
        nextAt = randInt(9, 15);
      }
    });

    anchors.reverse().forEach(function (afterEl) {
      var recs = buildRecSet(catalog, history, 3);
      if (!recs.length) return;
      var strip = createRecStrip(recs);
      if (afterEl.nextSibling) {
        grid.insertBefore(strip, afterEl.nextSibling);
      } else {
        grid.appendChild(strip);
      }
    });

    return true;
  }

  function notifyGridReady() {
    try {
      window.dispatchEvent(new CustomEvent('nexus-games-grid-ready'));
    } catch (e) {}
  }

  function boot() {
    var gridReady = false;
    function readyOnce() {
      if (gridReady) return;
      gridReady = true;
      setTimeout(notifyGridReady, 0);
    }

    function runInject() {
      if (injectRecommendations()) {
        readyOnce();
      } else if (!window.NexusAI || !NexusAI.isEnabled('lunaGameRecommendations')) {
        readyOnce();
      } else {
        injectRecommendations();
        readyOnce();
      }
    }

    function whenCatalogReady(fn) {
      var done = false;
      function once() {
        if (done) return;
        done = true;
        fn();
      }
      if (window.NexusGamesCatalog && NexusGamesCatalog.isReady && NexusGamesCatalog.isReady()) {
        once();
        return;
      }
      window.addEventListener('nexus-games-catalog-ready', function onReady() {
        window.removeEventListener('nexus-games-catalog-ready', onReady);
        once();
      });
      if (window.NexusGamesCatalog && typeof NexusGamesCatalog.whenReady === 'function') {
        NexusGamesCatalog.whenReady().then(once).catch(once);
      }
    }

    whenCatalogReady(runInject);

    window.addEventListener('nexus-ai-settings-changed', function () {
      var grid = document.getElementById('search');
      if (!grid) return;
      clearRecommendations(grid);
      injectRecommendations();
    });

    window.addEventListener('storage', function (e) {
      if (e.key === NexusAI.STORAGE_KEY) {
        var grid = document.getElementById('search');
        if (!grid) return;
        clearRecommendations(grid);
        injectRecommendations();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
