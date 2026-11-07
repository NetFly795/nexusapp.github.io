(function () {
  var threshold = 360;
  var sticky = document.querySelector('.sticky-header');
  if (!sticky) return;
  var categoryFilters = sticky.querySelector('.category-filters');
  var ticking = false;

  function getScrollTop() {
    return window.scrollY || window.pageYOffset ||
      document.documentElement.scrollTop || 0;
  }

  function updateProgress() {
    var overlay = document.querySelector('#gameOverlay');
    if (overlay && overlay.classList.contains('visible')) {
      sticky.style.setProperty('--merge-progress', '0');
      sticky.classList.remove('merged');
      if (categoryFilters) categoryFilters.classList.remove('hidden-during-merge');
      ticking = false;
      return;
    }

    var sc = getScrollTop();
    var raw = Math.max(0, Math.min(1, sc / threshold));
    var eased = raw * raw * (3 - 2 * raw);
    sticky.style.setProperty('--merge-progress', eased);

    ticking = false;
  }

  function onScroll() {
    if (!ticking) {
      ticking = true;
      window.requestAnimationFrame(updateProgress);
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  updateProgress();
})();
