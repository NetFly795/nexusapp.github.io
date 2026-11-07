/**
 * Nexus 7 era theme — light themes only via data-theme + CSS variables.
 * No theme-sync.js / dark mode stack.
 */
(function (global) {
  'use strict';

  function applyTheme(themeName) {
    var name = themeName || 'default';
    try {
      global.localStorage.setItem('nexus_theme', name);
    } catch (e) {}
    global.document.documentElement.setAttribute('data-theme', name);

    var logo = global.document.querySelector('[data-n7-logo]');
    if (logo) {
      logo.src = (name === 'default' || !name)
        ? '../nexuslogoapple.png'
        : '../submenus/images/' + name + '.png';
    }

    try {
      var iframes = global.document.getElementsByTagName('iframe');
      for (var i = 0; i < iframes.length; i++) {
        try {
          if (iframes[i].contentWindow && iframes[i].contentWindow.N7ApplyTheme) {
            iframes[i].contentWindow.N7ApplyTheme(name);
          }
        } catch (err) {}
      }
    } catch (e2) {}

    if (global.parent && global.parent !== global) {
      try {
        if (global.parent.N7ApplyTheme) global.parent.N7ApplyTheme(name);
      } catch (e3) {}
    }
  }

  global.N7ApplyTheme = applyTheme;

  global.addEventListener('storage', function (e) {
    if (e.key === 'nexus_theme') applyTheme(e.newValue || 'default');
  });

  applyTheme(global.localStorage.getItem('nexus_theme') || 'default');
})(window);
