/**
 * quick play strip. stars go brr. empty state is intentionally judgmental
 */
(function (global) {
  var QUICK_PLAY_KEY = 'nexus_quick_play_pins';
  var STAR_SVG =
    '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
    '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 6.91-1.01L12 2z"/>' +
    '</svg>';

  function getAI() {
    if (global.NexusAI) return global.NexusAI;
    try {
      if (global.parent && global.parent !== global && global.parent.NexusAI) {
        return global.parent.NexusAI;
      }
    } catch (e) {}
    return null;
  }

  function readPins() {
    var pins = null;
    var ai = getAI();
    if (ai && typeof ai.getQuickPlay === 'function') {
      try {
        pins = ai.getQuickPlay();
      } catch (e) {}
    }
    if (!Array.isArray(pins)) {
      try {
        pins = JSON.parse(global.localStorage.getItem(QUICK_PLAY_KEY) || '[]');
      } catch (e2) {
        pins = [];
      }
    }
    return Array.isArray(pins) ? pins : [];
  }

  function entryFromAnchor(anchor) {
    if (!anchor) return null;
    var href = anchor.getAttribute('href');
    if (!href) return null;
    var titleEl = anchor.querySelector('.game-title');
    var imgEl = anchor.querySelector('.game-icon');
    return {
      name: titleEl ? titleEl.textContent.replace(/\s+/g, ' ').trim() : 'Game',
      href: href,
      image: imgEl ? imgEl.getAttribute('src') || '' : '',
      category: anchor.getAttribute('data-category') || ''
    };
  }

  function resolveImage(src) {
    if (!src) return '';
    try {
      return new URL(src, global.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function openEntry(entry, originEl) {
    if (global.NexusGameLaunchSheet) {
      global.NexusGameLaunchSheet.openFromEntry(entry, originEl);
      return;
    }
    if (typeof global.openInOverlayHref === 'function') {
      global.openInOverlayHref(entry.href, entry.name || 'Game');
    }
  }

  function buildTile(entry) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'qp-tile-btn';
    btn.title = 'Play ' + (entry.name || 'Game');

    var tile = document.createElement('div');
    tile.className = 'qp-tile';

    if (entry.image) {
      var img = document.createElement('img');
      img.className = 'qp-tile-icon';
      img.src = resolveImage(entry.image);
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      tile.appendChild(img);
    } else {
      var ph = document.createElement('div');
      ph.className = 'qp-tile-icon placeholder';
      ph.textContent = (entry.name || 'G').charAt(0).toUpperCase();
      tile.appendChild(ph);
    }

    var name = document.createElement('span');
    name.className = 'qp-tile-name';
    name.textContent = entry.name || 'Game';
    tile.appendChild(name);

    btn.appendChild(tile);
    btn.addEventListener('click', function () {
      openEntry(entry, btn);
    });
    return btn;
  }

  function renderQuickPlaySection() {
    var strip = document.getElementById('quickPlayStrip');
    if (!strip) return;

    try {
      strip.innerHTML = '';

      var pins = readPins();
      if (!pins.length) {
        var empty = document.createElement('p');
        empty.className = 'qp-empty';
        empty.textContent = 'Nothing pinned yet. Tap ★ on any game below to add it here.';
        strip.appendChild(empty);
        return;
      }

      pins.forEach(function (entry) {
        if (entry && entry.href) strip.appendChild(buildTile(entry));
      });
    } catch (err) {
      strip.innerHTML = '<p class="qp-empty">Could not load Quick Play.</p>';
    }
  }

  function isPinned(href) {
    var ai = getAI();
    if (ai && typeof ai.isQuickPlay === 'function') return ai.isQuickPlay(href);
    var key = href || '';
    return readPins().some(function (item) {
      return item.href === key;
    });
  }

  function updatePinButton(btn, href) {
    var pinned = isPinned(href);
    btn.classList.toggle('pinned', pinned);
    btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
    btn.title = pinned ? 'Remove from Quick Play' : 'Add to Quick Play';
  }

  function togglePin(entry) {
    var ai = getAI();
    if (ai && typeof ai.toggleQuickPlay === 'function') {
      ai.toggleQuickPlay(entry);
      return;
    }
    if (!entry || !entry.href) return;
    var list = readPins().filter(function (item) { return item.href !== entry.href; });
    if (!isPinned(entry.href)) {
      list.unshift({
        name: entry.name || 'Game',
        href: entry.href,
        image: entry.image || '',
        category: entry.category || '',
        pinnedAt: Date.now()
      });
    }
    try {
      global.localStorage.setItem(QUICK_PLAY_KEY, JSON.stringify(list.slice(0, 12)));
    } catch (e) {}
    try {
      global.dispatchEvent(new CustomEvent('nexus-quick-play-changed'));
    } catch (e2) {}
  }

  function ensurePinButton(anchor) {
    var tile = anchor.querySelector('.game-tile');
    if (!tile || tile.querySelector('.qp-pin-btn')) return;

    var href = anchor.getAttribute('href');
    if (!href) return;

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'qp-pin-btn';
    btn.innerHTML = STAR_SVG;
    btn.setAttribute('aria-label', 'Add to Quick Play');
    updatePinButton(btn, href);

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var entry = entryFromAnchor(anchor);
      if (!entry) return;
      togglePin(entry);
      document.querySelectorAll('#search a.game-link .qp-pin-btn').forEach(function (pin) {
        var a = pin.closest('a.game-link');
        if (a) updatePinButton(pin, a.getAttribute('href'));
      });
      renderQuickPlaySection();
    });

    tile.appendChild(btn);
  }

  function decorateGameTiles() {
    document.querySelectorAll('#search a.game-link').forEach(ensurePinButton);
  }

  function refresh() {
    decorateGameTiles();
    renderQuickPlaySection();
  }

  global.NexusQuickPlay = { refresh: refresh };

  var booted = false;
  function init() {
    refresh();
    if (booted) return;
    booted = true;

    global.addEventListener('nexus-quick-play-changed', renderQuickPlaySection);
    global.addEventListener('nexus-games-grid-ready', refresh);
    global.addEventListener('load', refresh);
    global.addEventListener('storage', function (e) {
      if (e.key === QUICK_PLAY_KEY) renderQuickPlaySection();
    });
    global.addEventListener('message', function (e) {
      if (e.data && e.data.type === 'nexus-quick-play-changed') renderQuickPlaySection();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
