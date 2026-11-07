/**
 * Nexus 7 shell — iframe navigation, setup, dock customize, hide/show.
 */
(function () {
  'use strict';

  var CONFIG = window.N7_CONFIG || {};
  var SETUP_STEPS = CONFIG.setupSteps || 5;
  var PAGES = {
    home: CONFIG.homePage || 'home.html',
    games: '../moderngame/index.html',
    social: CONFIG.socialPage || 'social.html',
    settings: CONFIG.settingsPage || '../../submenus/settings.html'
  };

  var lastGamesUrl = '../moderngame/index.html';
  var currentStep = 1;
  var selectedTheme = 'default';

  var bottomNav = document.getElementById('bottomNav');
  var contentFrame = document.getElementById('content-frame');
  var showNavBtn = document.getElementById('showNavBtn');
  var hideNavBtn = document.getElementById('hideNavBtn');
  var gamesBtn = document.querySelector('.nav-btn[data-page="games"]');
  var backBtn = document.getElementById('backBtn');
  var gamesBackBtn = document.getElementById('gamesBackBtn');
  var fullscreenBtn = document.getElementById('fullscreenBtn');

  function detectMobile() {
    var isMobile = (window.innerWidth / window.innerHeight) < 0.8 || window.innerWidth < 768;
    document.body.classList.toggle('mobile', isMobile);
    updateFrameHeight();
  }

  function navHeight() {
    if (!bottomNav || bottomNav.style.display === 'none') return 0;
    if (window.innerWidth <= 360) return 50;
    if (window.innerWidth <= 480) return 55;
    return 60;
  }

  function updateFrameHeight() {
    if (!contentFrame) return;
    if (document.body.classList.contains('mobile')) {
      var h = Math.max(200, window.innerHeight - navHeight());
      contentFrame.style.height = h + 'px';
    } else {
      contentFrame.style.height = '';
    }
  }

  window.changePage = function (page, btn) {
    var frame = contentFrame;
    var loadingBar = document.getElementById('loadingBar');
    if (!frame || !loadingBar) return;

    loadingBar.classList.add('loading');
    loadingBar.classList.remove('complete');
    frame.classList.add('page-transition-out');
    frame.classList.remove('page-transition-in');

    setTimeout(function () {
      frame.src = page;
      document.querySelectorAll('.nav-btn').forEach(function (b) {
        b.classList.remove('active');
      });
      if (btn) btn.classList.add('active');
      if (backBtn) backBtn.style.display = 'none';

      var pageKey = btn && btn.getAttribute('data-page');
      var isGames = pageKey === 'games';
      if (fullscreenBtn) fullscreenBtn.classList.toggle('active', isGames);
      if (bottomNav) bottomNav.classList.toggle('shift-left', isGames);

      frame.onload = function () {
        frame.classList.remove('page-transition-out');
        frame.classList.add('page-transition-in');
        loadingBar.classList.remove('loading');
        loadingBar.classList.add('complete');
        updateFrameHeight();
        setTimeout(function () {
          loadingBar.classList.remove('complete');
          loadingBar.style.width = '0%';
        }, 700);
      };
    }, 350);
  };

  function wireNav() {
    var map = [
      { sel: '.nav-btn[data-page="home"]', page: PAGES.home },
      { sel: '.nav-btn[data-page="games"]', page: PAGES.games },
      { sel: '.nav-btn[data-page="social"]', page: PAGES.social },
      { sel: '.nav-btn[data-page="settings"]', page: PAGES.settings }
    ];
    map.forEach(function (item) {
      var btn = document.querySelector(item.sel);
      if (!btn) return;
      btn.addEventListener('click', function () {
        changePage(item.page, btn);
      });
    });
  }

  if (hideNavBtn) {
    hideNavBtn.addEventListener('click', function () {
      bottomNav.classList.remove('show-anim');
      bottomNav.classList.add('hide-anim');
      setTimeout(function () {
        bottomNav.style.display = 'none';
        showNavBtn.style.display = 'flex';
        showNavBtn.classList.remove('drift-out');
        void showNavBtn.offsetWidth;
        showNavBtn.classList.add('drift-in');
        showNavBtn.style.visibility = 'visible';
        showNavBtn.style.opacity = '1';
        showNavBtn.style.pointerEvents = 'auto';
        updateFrameHeight();
      }, 250);
    });
  }

  if (showNavBtn) {
    showNavBtn.addEventListener('click', function () {
      showNavBtn.classList.remove('drift-in');
      showNavBtn.classList.add('drift-out');
      showNavBtn.style.pointerEvents = 'none';
      setTimeout(function () {
        bottomNav.style.display = 'flex';
        void bottomNav.offsetWidth;
        bottomNav.classList.remove('hide-anim');
        bottomNav.classList.add('show-anim');
        showNavBtn.style.display = 'none';
        updateFrameHeight();
      }, 320);
    });
  }

  if (backBtn && gamesBtn) {
    backBtn.addEventListener('click', function () {
      contentFrame.src = lastGamesUrl;
      backBtn.style.display = 'none';
      document.querySelectorAll('.nav-btn').forEach(function (b) { b.classList.remove('active'); });
      gamesBtn.classList.add('active');
    });
  }

  if (gamesBackBtn) {
    gamesBackBtn.addEventListener('click', function () {
      contentFrame.classList.add('page-transition-out');
      gamesBackBtn.classList.add('button-exit');
      setTimeout(function () {
        contentFrame.src = lastGamesUrl;
        contentFrame.onload = function () {
          contentFrame.classList.remove('page-transition-out');
          contentFrame.classList.add('page-transition-in');
          setTimeout(function () { gamesBackBtn.style.display = 'none'; }, 300);
        };
      }, 350);
    });
  }

  if (fullscreenBtn) {
    fullscreenBtn.addEventListener('click', function () {
      var target = contentFrame;
      if (document.fullscreenElement) document.exitFullscreen();
      else if (target && target.requestFullscreen) target.requestFullscreen();
    });
  }

  window.toggleCustomizeMode = function () {
    var section = document.querySelector('.customize-section');
    var overlay = document.querySelector('.edit-overlay');
    if (!section) return;
    var open = !section.classList.contains('show');
    if (open) {
      section.classList.add('show');
      overlay.classList.add('show');
      bottomNav.classList.add('popup-mode');
    } else {
      section.classList.remove('show');
      overlay.classList.remove('show');
      bottomNav.classList.remove('popup-mode');
    }
  };

  function navButtonForPage(page) {
    if (page === 'games') return gamesBtn;
    return document.querySelector('.nav-btn[data-page="' + page + '"]');
  }

  window.setDefaultPage = function (page) {
    document.querySelectorAll('.option-choice').forEach(function (btn) {
      btn.classList.remove('selected');
    });
    var selected = document.querySelector('.option-choice[data-page="' + page + '"]');
    if (selected) selected.classList.add('selected');
    localStorage.setItem('nexus_default_page', page);
    var btn = navButtonForPage(page);
    if (btn) changePage(PAGES[page] || PAGES.home, btn);
  };

  function applySavedButtonOrder() {
    var saved = [];
    try { saved = JSON.parse(localStorage.getItem('nexus_button_order') || '[]'); } catch (e) {}
    if (!saved.length || !bottomNav) return;
    var anchor = document.getElementById('navReorderAnchor');
    saved.forEach(function (page) {
      var btn = navButtonForPage(page);
      if (btn && anchor) bottomNav.insertBefore(btn, anchor);
    });
  }

  function showSetup() {
    document.getElementById('setupOverlay').style.display = 'block';
    document.getElementById('setupPopup').style.display = 'block';
    setTimeout(function () {
      document.getElementById('setupOverlay').style.opacity = '1';
      document.getElementById('setupPopup').style.opacity = '1';
      document.getElementById('step1').classList.add('active');
    }, 100);
  }

  window.selectSetupTheme = function (theme) {
    document.querySelectorAll('.theme-preview').forEach(function (p) {
      p.classList.remove('selected');
    });
    if (event && event.currentTarget) event.currentTarget.classList.add('selected');
    selectedTheme = theme;
    if (window.N7ApplyTheme) N7ApplyTheme(theme);
  };

  window.nextSetupStep = function () {
    var username = document.getElementById('setupUsername').value;
    if (currentStep === 1 && !username.trim()) {
      alert('Please enter a username');
      return;
    }
    if (currentStep === SETUP_STEPS) {
      localStorage.setItem('nexus_username', username.trim());
      localStorage.setItem('nexus_theme', selectedTheme);
      if (SETUP_STEPS >= 5) {
        localStorage.setItem('nexus_bio', document.getElementById('setupBio').value || '');
        var preview = document.getElementById('setupProfilePreview');
        if (preview && preview.src && preview.style.display !== 'none') {
          localStorage.setItem('nexus_profilepic', preview.src);
        }
      }
      localStorage.setItem('nexus_setup_complete', 'true');
      document.getElementById('setupOverlay').style.opacity = '0';
      document.getElementById('setupPopup').style.opacity = '0';
      setTimeout(function () {
        document.getElementById('setupOverlay').style.display = 'none';
        document.getElementById('setupPopup').style.display = 'none';
      }, 300);
      if (window.N7ApplyTheme) N7ApplyTheme(selectedTheme);
      return;
    }
    document.querySelector('.setup-step.active').classList.remove('active');
    currentStep++;
    document.getElementById('step' + currentStep).classList.add('active');
    if (currentStep === SETUP_STEPS) {
      document.getElementById('setupNextBtn').textContent = 'Get Started';
    }
  };

  window.acceptCookies = function () {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookiePopup').classList.remove('show');
  };

  window.declineCookies = function () {
    localStorage.setItem('cookie_consent', 'declined');
    document.getElementById('cookiePopup').classList.remove('show');
  };

  function initDragReorder() {
    var list = document.getElementById('buttonList');
    if (!list) return;
    var dragged = null;
    list.querySelectorAll('.button-preview').forEach(function (btn) {
      btn.addEventListener('dragstart', function () {
        dragged = btn;
        btn.classList.add('dragging');
      });
      btn.addEventListener('dragend', function () {
        btn.classList.remove('dragging');
      });
      btn.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (dragged && dragged !== btn) {
          var rect = btn.getBoundingClientRect();
          if (e.clientY < rect.top + rect.height / 2) {
            list.insertBefore(dragged, btn);
          } else {
            list.insertBefore(dragged, btn.nextSibling);
          }
        }
      });
    });
    list.addEventListener('drop', function (e) {
      e.preventDefault();
      var order = Array.prototype.map.call(list.children, function (el) {
        return el.getAttribute('data-page');
      });
      localStorage.setItem('nexus_button_order', JSON.stringify(order));
      applySavedButtonOrder();
    });
  }

  document.addEventListener('mousemove', function (e) {
    document.documentElement.style.setProperty('--mouse-x', e.clientX / window.innerWidth);
    document.documentElement.style.setProperty('--mouse-y', e.clientY / window.innerHeight);
  });

  window.addEventListener('message', function (e) {
    if (e.data && e.data.type === 'n7-open-customize') toggleCustomizeMode();
    if (e.data && e.data.type === 'n7-apply-theme' && window.N7ApplyTheme) {
      N7ApplyTheme(e.data.theme);
    }
  });

  document.addEventListener('DOMContentLoaded', function () {
    detectMobile();
    wireNav();
    initDragReorder();
    applySavedButtonOrder();

    var profileInput = document.getElementById('setupProfilePic');
    if (profileInput) {
      profileInput.addEventListener('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (evt) {
          var preview = document.getElementById('setupProfilePreview');
          preview.src = evt.target.result;
          preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    }

    if (!localStorage.getItem('nexus_setup_complete')) showSetup();

    if (!localStorage.getItem('cookie_consent')) {
      setTimeout(function () {
        document.getElementById('cookiePopup').classList.add('show');
      }, 1000);
    }

    var defaultPage = localStorage.getItem('nexus_default_page');
    if (defaultPage && PAGES[defaultPage]) {
      var btn = navButtonForPage(defaultPage);
      if (btn) changePage(PAGES[defaultPage], btn);
    }

    var dp = localStorage.getItem('nexus_default_page') || 'home';
    var choice = document.querySelector('.option-choice[data-page="' + dp + '"]');
    if (choice) choice.classList.add('selected');
  });

  window.addEventListener('resize', detectMobile);
  window.addEventListener('orientationchange', detectMobile);
})();
