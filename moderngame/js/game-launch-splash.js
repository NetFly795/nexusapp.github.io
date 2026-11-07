/**
 * full-screen splash when you launch a game. shine effect included bc we can
 */
(function (global) {
  'use strict';

  function resolveImage(src) {
    if (!src) return '';
    try {
      return new URL(src, global.location.href).href;
    } catch (e) {
      return src;
    }
  }

  function randDuration() {
    return 500 + Math.floor(Math.random() * 1501);
  }

  function getSplashEls() {
    return {
      root: document.getElementById('gameLaunchSplash'),
      bg: document.getElementById('gameLaunchSplashBg'),
      icon: document.getElementById('gameLaunchSplashIcon'),
      iconImg: document.getElementById('gameLaunchSplashIconImg'),
      iconLetter: document.getElementById('gameLaunchSplashIconLetter'),
      title: document.getElementById('gameLaunchSplashTitle'),
      shine: document.getElementById('gameLaunchSplashShine')
    };
  }

  function setIconContent(els, entry) {
    if (!els.icon || !entry) return;
    if (entry.image) {
      els.iconImg.src = resolveImage(entry.image);
      els.iconImg.style.display = 'block';
      if (els.iconLetter) els.iconLetter.style.display = 'none';
      els.bg.style.backgroundImage = 'url("' + resolveImage(entry.image) + '")';
      els.bg.classList.remove('placeholder');
    } else {
      els.iconImg.style.display = 'none';
      if (els.iconLetter) {
        els.iconLetter.textContent = (entry.name || 'G').charAt(0).toUpperCase();
        els.iconLetter.style.display = 'flex';
      }
      els.bg.style.backgroundImage = '';
      els.bg.classList.add('placeholder');
    }
    if (els.title) els.title.textContent = entry.name || 'Game';
  }

  function placeIconAtRect(icon, rect) {
    var size = Math.max(56, Math.min(rect.width * 0.55, rect.height * 0.45, 120));
    icon.style.width = size + 'px';
    icon.style.height = size + 'px';
    icon.style.top = (rect.top + rect.height / 2 - size / 2) + 'px';
    icon.style.left = (rect.left + rect.width / 2 - size / 2) + 'px';
    icon.style.borderRadius = '16px';
    icon.style.transform = 'none';
  }

  function expandIconToCenter(icon, titleEl) {
    var target = Math.min(128, global.innerWidth * 0.28);
    var top = global.innerHeight / 2 - target / 2 - 28;
    var left = global.innerWidth / 2 - target / 2;
    icon.style.top = top + 'px';
    icon.style.left = left + 'px';
    icon.style.width = target + 'px';
    icon.style.height = target + 'px';
    icon.style.borderRadius = '22px';
    if (titleEl) {
      titleEl.style.top = (top + target + 20) + 'px';
    }
  }

  function resetSplash(els) {
    if (!els.root) return;
    els.root.classList.remove('active', 'expanded', 'shining', 'exit');
    if (els.icon) {
      els.icon.style.top = '';
      els.icon.style.left = '';
      els.icon.style.width = '';
      els.icon.style.height = '';
      els.icon.style.transform = '';
    }
    if (els.title) {
      els.title.style.top = '';
      els.title.style.opacity = '';
      els.title.style.transform = '';
    }
  }

  global.animateGameLaunchSplash = function (entry, originRect, done) {
    var els = getSplashEls();
    if (!els.root || !els.icon || !entry) {
      if (typeof done === 'function') done();
      return;
    }

    resetSplash(els);
    setIconContent(els, entry);

    var rect = originRect || {
      top: global.innerHeight / 2 - 60,
      left: global.innerWidth / 2 - 60,
      width: 120,
      height: 120
    };

    placeIconAtRect(els.icon, rect);
    els.root.classList.add('active');

    global.requestAnimationFrame(function () {
      global.requestAnimationFrame(function () {
        els.root.classList.add('expanded', 'shining');
        expandIconToCenter(els.icon, els.title);
      });
    });

    var holdMs = randDuration();
    global.setTimeout(function () {
      els.root.classList.add('exit');
      global.setTimeout(function () {
        resetSplash(els);
        if (typeof done === 'function') done();
      }, 340);
    }, holdMs);
  };
})(window);
