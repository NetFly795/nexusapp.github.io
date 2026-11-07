/**
 * home dashboard glue: recent game + quick play pins.
 * if this breaks the whole home page looks haunted. no pressure.
 */
(function (global) {
  function resolveImage(src) {
    if (!src) return '';
    try {
      return new URL(src, global.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function playGame(entry) {
    if (!entry || !entry.href) return;
    try {
      if (global.parent && global.parent !== global) {
        global.parent.postMessage({
          type: 'nexus-play-last-game',
          href: entry.href,
          title: entry.name,
          name: entry.name
        }, '*');
      }
    } catch (e) {}
  }

  function buildChip(entry) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'qp-chip';
    btn.title = 'Play ' + (entry.name || 'Game');

    if (entry.image) {
      var img = document.createElement('img');
      img.className = 'qp-chip-icon';
      img.src = resolveImage(entry.image.indexOf('/') === 0 || /^https?:/.test(entry.image)
        ? entry.image
        : '../moderngame/' + entry.image.replace(/^\.\//, ''));
      img.alt = '';
      img.loading = 'lazy';
      img.decoding = 'async';
      btn.appendChild(img);
    } else {
      var ph = document.createElement('div');
      ph.className = 'qp-chip-icon placeholder';
      ph.textContent = (entry.name || 'G').charAt(0).toUpperCase();
      btn.appendChild(ph);
    }

    var text = document.createElement('span');
    text.className = 'qp-chip-text';
    var name = document.createElement('span');
    name.className = 'qp-chip-name';
    name.textContent = entry.name || 'Game';
    text.appendChild(name);
    if (entry.category) {
      var cat = document.createElement('span');
      cat.className = 'qp-chip-cat';
      cat.textContent = entry.category;
      text.appendChild(cat);
    }
    btn.appendChild(text);
    btn.addEventListener('click', function () { playGame(entry); });
    return btn;
  }

  function renderRecent() {
    var section = document.getElementById('recentPlayedSection');
    var nameEl = document.getElementById('recentPlayedName');
    var metaEl = document.getElementById('recentPlayedMeta');
    var iconWrap = document.getElementById('recentPlayedIconWrap');
    var card = document.getElementById('recentPlayedCard');
    var btn = document.getElementById('recentPlayedBtn');
    if (!section || !global.NexusAI) return;

    var recent = NexusAI.getLastPlayed();
    if (!recent || !recent.href) {
      section.hidden = true;
      return;
    }

    section.hidden = false;
    nameEl.textContent = recent.name || 'Game';
    metaEl.textContent = NexusAI.formatRelativeTime(recent.playedAt) +
      (recent.category ? ' · ' + recent.category : '');

    iconWrap.innerHTML = '';
    if (recent.image) {
      var img = document.createElement('img');
      img.className = 'recent-played-icon';
      img.src = resolveImage(recent.image.indexOf('/') === 0 || /^https?:/.test(recent.image)
        ? recent.image
        : '../moderngame/' + recent.image.replace(/^\./, ''));
      img.alt = '';
      iconWrap.appendChild(img);
    } else {
      var ph = document.createElement('div');
      ph.className = 'recent-played-icon placeholder';
      ph.textContent = (recent.name || 'G').charAt(0).toUpperCase();
      iconWrap.appendChild(ph);
    }

    var play = function () { playGame(recent); };
    if (btn) btn.onclick = function (e) { e.stopPropagation(); play(); };
    if (card) {
      card.onclick = play;
      card.onkeydown = function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          play();
        }
      };
    }
  }

  function renderQuickPlay() {
    var strip = document.getElementById('homeQuickPlayStrip');
    var empty = document.getElementById('homeQuickPlayEmpty');
    if (!strip || !global.NexusAI) return;

    var pins = NexusAI.getQuickPlay();
    strip.innerHTML = '';

    if (!pins.length) {
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    pins.forEach(function (entry) {
      strip.appendChild(buildChip(entry));
    });
  }

  function refresh() {
    renderRecent();
    renderQuickPlay();
  }

  global.NexusHomePlay = {
    init: refresh,
    refresh: refresh
  };

  global.addEventListener('nexus-quick-play-changed', function () {
    refresh();
    if (global.NexusWebGLGlass) NexusWebGLGlass.refresh();
  });

  global.addEventListener('load', refresh);
  global.addEventListener('message', function (e) {
    if (e.data && (e.data.type === 'nexus-quick-play-changed' || e.data.type === 'nexus-home-refresh')) {
      refresh();
      if (global.NexusWebGLGlass) NexusWebGLGlass.refresh();
    }
  });
  global.addEventListener('storage', function (e) {
    if (!global.NexusAI) return;
    if (e.key === NexusAI.QUICK_PLAY_KEY || e.key === NexusAI.LAST_PLAYED_KEY || e.key === NexusAI.HISTORY_KEY) {
      refresh();
    }
  });
})(window);
