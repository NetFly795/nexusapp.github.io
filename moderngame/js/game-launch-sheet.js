/**
 * tile morph popup. play / pin / friends. ios would call this "delightful".
 * i call it "4 hours of getBoundingClientRect()"
 */
(function (global) {
  'use strict';

  var MORPH_MS = 480;

  var state = {
    entry: null,
    anchor: null,
    originRect: null,
    originEl: null,
    tileIconH: 276,
    open: false,
    closing: false
  };

  var els = {};

  function entryFromAnchor(anchor) {
    if (!anchor) return null;
    var href = anchor.getAttribute('href');
    if (!href) return null;
    var titleEl = anchor.querySelector('.game-title');
    var imgEl = anchor.querySelector('.game-icon');
    return {
      name: titleEl ? titleEl.textContent.replace(/\s+/g, ' ').trim() : 'Game',
      href: href,
      image: imgEl ? (imgEl.getAttribute('src') || '') : '',
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

  function getOriginRect(anchor, originEl) {
    var el = originEl || (anchor && anchor.querySelector('.game-tile')) || anchor;
    if (!el) {
      return {
        top: global.innerHeight / 2 - 200,
        left: global.innerWidth / 2 - 150,
        width: 300,
        height: 400
      };
    }
    return el.getBoundingClientRect();
  }

  function getIconLaunchRect() {
    if (els.iconWrap) {
      return els.iconWrap.getBoundingClientRect();
    }
    if (els.morph) {
      return els.morph.getBoundingClientRect();
    }
    return state.originRect;
  }

  function setIcon(entry, tileIconHeight) {
    var wrap = els.iconWrap;
    var img = els.icon;
    var letter = els.iconLetter;
    if (!wrap) return;

    var h = tileIconHeight || 276;
    wrap.style.width = 'calc(100% - 24px)';
    wrap.style.height = h + 'px';
    wrap.style.borderRadius = '18px';

    if (entry.image) {
      img.src = resolveImage(entry.image);
      img.alt = '';
      img.style.display = 'block';
      if (letter) letter.style.display = 'none';
    } else {
      img.style.display = 'none';
      if (letter) {
        letter.textContent = (entry.name || 'G').charAt(0).toUpperCase();
        letter.style.display = 'flex';
      }
    }
  }

  function resetMorphStyles() {
    if (!els.morph) return;
    els.morph.classList.remove('expanded', 'closing');
    els.morph.style.transition = 'none';
    els.morph.style.top = '';
    els.morph.style.left = '';
    els.morph.style.width = '';
    els.morph.style.height = '';
    els.morph.style.transform = '';
    els.morph.style.opacity = '';
    if (els.iconWrap) {
      els.iconWrap.style.transition = 'none';
      els.iconWrap.style.width = '';
      els.iconWrap.style.height = '';
      els.iconWrap.style.borderRadius = '';
    }
    void els.morph.offsetHeight;
    els.morph.style.transition = '';
    if (els.iconWrap) els.iconWrap.style.transition = '';
  }

  function updatePinButton() {
    if (!els.pinBtn || !state.entry || !global.NexusAI) return;
    var pinned = NexusAI.isQuickPlay(state.entry.href);
    els.pinBtn.classList.toggle('pinned', pinned);
    els.pinBtn.textContent = pinned ? 'Unpin from Quick Play' : 'Pin to Quick Play';
  }

  function openGameAfterSplash(withFriends) {
    var entry = state.entry;
    var anchor = state.anchor;
    var launchRect = getIconLaunchRect();
    if (!launchRect || !launchRect.width) launchRect = state.originRect;

    var afterOpen = withFriends
      ? function () {
          if (typeof global.showGameInviteModal === 'function') {
            global.setTimeout(function () {
              global.showGameInviteModal(null, null);
            }, 420);
          }
        }
      : null;

    hideSheetImmediate();

    var openGame = function () {
      if (anchor && typeof global.openInOverlay === 'function') {
        global.openInOverlay(anchor);
        if (afterOpen) afterOpen();
        return;
      }
      if (typeof global.openInOverlayHref === 'function' && entry) {
        global.openInOverlayHref(entry.href, entry.name || 'Game');
        if (afterOpen) afterOpen();
      }
    };

    if (typeof global.animateGameLaunchSplash === 'function') {
      global.animateGameLaunchSplash(entry, launchRect, openGame);
      return;
    }

    if (anchor && typeof global.animateOpenFrom === 'function') {
      global.animateOpenFrom(anchor, openGame);
      return;
    }
    openGame();
  }

  function playGame(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (!state.open || state.closing) return;
    openGameAfterSplash(false);
  }

  function playWithFriends(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (!state.open || state.closing) return;
    openGameAfterSplash(true);
  }

  function togglePin(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    if (!state.entry || !global.NexusAI) return;
    NexusAI.toggleQuickPlay(state.entry);
    updatePinButton();
    if (global.NexusQuickPlay && typeof global.NexusQuickPlay.refresh === 'function') {
      global.NexusQuickPlay.refresh();
    }
    document.querySelectorAll('#search a.game-link .qp-pin-btn').forEach(function (pin) {
      var a = pin.closest('a.game-link');
      if (!a || !global.NexusAI) return;
      var pinned = NexusAI.isQuickPlay(a.getAttribute('href'));
      pin.classList.toggle('pinned', pinned);
    });
  }

  function expandMorph(originRect, tileIconH) {
    var morph = els.morph;
    if (!morph) return;

    var targetW = Math.min(360, global.innerWidth * 0.92);
    var targetH = Math.min(430, global.innerHeight * 0.78);
    var targetTop = (global.innerHeight - targetH) / 2;
    var targetLeft = (global.innerWidth - targetW) / 2;

    morph.style.transition = 'none';
    if (els.iconWrap) els.iconWrap.style.transition = 'none';

    morph.style.top = originRect.top + 'px';
    morph.style.left = originRect.left + 'px';
    morph.style.width = originRect.width + 'px';
    morph.style.height = originRect.height + 'px';
    morph.style.transform = 'none';
    morph.style.opacity = '1';
    morph.classList.remove('expanded', 'closing');
    setIcon(state.entry, tileIconH);

    void morph.offsetHeight;

    morph.style.transition = '';
    if (els.iconWrap) els.iconWrap.style.transition = '';

    global.requestAnimationFrame(function () {
      global.requestAnimationFrame(function () {
        morph.style.top = targetTop + 'px';
        morph.style.left = targetLeft + 'px';
        morph.style.width = targetW + 'px';
        morph.style.height = targetH + 'px';
        morph.classList.add('expanded');

        if (els.iconWrap) {
          els.iconWrap.style.width = '88px';
          els.iconWrap.style.height = '88px';
          els.iconWrap.style.borderRadius = '16px';
        }
      });
    });
  }

  function collapseMorph(done) {
    var morph = els.morph;
    var origin = state.originRect;
    if (!morph || !origin) {
      if (done) done();
      return;
    }

    state.closing = true;
    morph.classList.remove('expanded');
    morph.classList.add('closing');

    if (state.entry) setIcon(state.entry, state.tileIconH);

    if (els.iconWrap) {
      els.iconWrap.style.width = 'calc(100% - 24px)';
      els.iconWrap.style.height = state.tileIconH + 'px';
      els.iconWrap.style.borderRadius = '18px';
    }
    if (els.backdrop) els.backdrop.classList.remove('visible');
    if (els.closeBtn) els.closeBtn.style.opacity = '0';

    morph.style.top = origin.top + 'px';
    morph.style.left = origin.left + 'px';
    morph.style.width = origin.width + 'px';
    morph.style.height = origin.height + 'px';

    var finished = false;
    function finish() {
      if (finished) return;
      finished = true;
      state.closing = false;
      if (done) done();
    }

    function onEnd(e) {
      if (e.target !== morph) return;
      if (e.propertyName !== 'top' && e.propertyName !== 'width') return;
      morph.removeEventListener('transitionend', onEnd);
      finish();
    }

    morph.addEventListener('transitionend', onEnd);
    global.setTimeout(finish, MORPH_MS + 80);
  }

  function hideSheetImmediate() {
    state.open = false;
    state.closing = false;
    if (els.backdrop) els.backdrop.classList.remove('visible');
    if (els.sheet) {
      els.sheet.classList.remove('open');
      els.sheet.setAttribute('aria-hidden', 'true');
    }
    if (els.closeBtn) els.closeBtn.style.opacity = '';
    resetMorphStyles();
    state.entry = null;
    state.anchor = null;
    state.originRect = null;
    state.originEl = null;
  }

  function closeSheet(done, animateReverse) {
    if (!state.open && !state.closing) {
      if (done) done();
      return;
    }
    if (state.closing) return;

    if (animateReverse && state.originRect && els.morph) {
      collapseMorph(function () {
        hideSheetImmediate();
        if (done) done();
      });
      return;
    }

    hideSheetImmediate();
    if (done) global.setTimeout(done, 0);
  }

  function openSheet(entry, anchor, originEl) {
    if (!entry || !entry.href) return;

    if (state.open || state.closing) {
      closeSheet(function () {
        openSheet(entry, anchor, originEl);
      }, true);
      return;
    }

    if (!els.sheet || !els.morph || !els.backdrop) return;

    var originRect = getOriginRect(anchor, originEl);
    var tileIconH = Math.max(120, Math.min(276, originRect.height - 100));

    state.open = true;
    state.entry = entry;
    state.anchor = anchor || null;
    state.originRect = originRect;
    state.originEl = originEl || null;
    state.tileIconH = tileIconH;

    if (els.title) els.title.textContent = entry.name || 'Game';
    updatePinButton();

    els.backdrop.classList.add('visible');
    els.sheet.classList.add('open');
    els.sheet.setAttribute('aria-hidden', 'false');

    expandMorph(originRect, tileIconH);
  }

  function bindEvents() {
    if (!els.backdrop || !els.sheet) return;

    els.backdrop.addEventListener('click', function () {
      closeSheet(null, true);
    });

    els.sheet.addEventListener('click', function (e) {
      if (e.target === els.sheet) closeSheet(null, true);
    });

    if (els.closeBtn) {
      els.closeBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeSheet(null, true);
      });
    }

    if (els.playBtn) els.playBtn.addEventListener('click', playGame);
    if (els.pinBtn) els.pinBtn.addEventListener('click', togglePin);
    if (els.friendsBtn) els.friendsBtn.addEventListener('click', playWithFriends);

    if (els.morph) {
      els.morph.addEventListener('click', function (e) { e.stopPropagation(); });
    }

    global.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && state.open) closeSheet(null, true);
    });
  }

  function init() {
    els.backdrop = document.getElementById('gameLaunchBackdrop');
    els.sheet = document.getElementById('gameLaunchSheet');
    els.morph = document.getElementById('gameLaunchMorph');
    els.iconWrap = document.getElementById('gameLaunchIconWrap');
    els.icon = document.getElementById('gameLaunchIcon');
    els.iconLetter = document.getElementById('gameLaunchIconLetter');
    els.title = document.getElementById('gameLaunchTitle');
    els.playBtn = document.getElementById('gameLaunchPlay');
    els.pinBtn = document.getElementById('gameLaunchPin');
    els.friendsBtn = document.getElementById('gameLaunchFriends');
    els.closeBtn = document.getElementById('gameLaunchClose');
    if (!els.sheet) return;
    bindEvents();
  }

  global.NexusGameLaunchSheet = {
    openFromAnchor: function (anchor) {
      var entry = entryFromAnchor(anchor);
      openSheet(entry, anchor, anchor && anchor.querySelector('.game-tile'));
    },
    openFromEntry: function (entry, originEl) {
      var anchor = null;
      if (entry && entry.href) {
        document.querySelectorAll('#search a.game-link').forEach(function (a) {
          if (!anchor && a.getAttribute('href') === entry.href) anchor = a;
        });
      }
      openSheet(entry, anchor, originEl);
    },
    close: closeSheet,
    isOpen: function () { return state.open; }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
