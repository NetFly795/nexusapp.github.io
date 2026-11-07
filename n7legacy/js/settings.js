(function () {
  'use strict';

  function markSelected(theme) {
    document.querySelectorAll('.theme-swatch').forEach(function (btn) {
      btn.classList.toggle('selected', btn.getAttribute('data-theme') === theme);
    });
  }

  window.openCustomize = function () {
    try {
      window.parent.postMessage({ type: 'n7-open-customize' }, '*');
    } catch (e) {}
  };

  window.resetSetup = function () {
    localStorage.removeItem('nexus_setup_complete');
    alert('Reload the Nexus 7 shell to see the setup wizard again.');
  };

  document.addEventListener('DOMContentLoaded', function () {
    var name = localStorage.getItem('nexus_username') || 'Guest';
    document.getElementById('profileName').textContent = name;
    var bio = localStorage.getItem('nexus_bio');
    if (bio) document.getElementById('profileBio').textContent = bio;
    var pic = localStorage.getItem('nexus_profilepic');
    if (pic) document.getElementById('profilePic').src = pic;

    var current = localStorage.getItem('nexus_theme') || 'default';
    markSelected(current);

    document.querySelectorAll('.theme-swatch').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var theme = btn.getAttribute('data-theme');
        if (window.N7ApplyTheme) N7ApplyTheme(theme);
        markSelected(theme);
      });
    });
  });
})();
