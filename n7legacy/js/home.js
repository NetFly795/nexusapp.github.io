(function () {
  'use strict';

  var quotes = [
    'The only limit to our realization of tomorrow is our doubts of today. — FDR',
    'In the middle of every difficulty lies opportunity. — Einstein',
    'Believe you can and you\'re halfway there. — Theodore Roosevelt',
    'It always seems impossible until it\'s done. — Nelson Mandela',
    'You miss 100% of the shots you don\'t take. — Wayne Gretzky'
  ];

  function showPopup() {
    var overlay = document.getElementById('popupOverlay');
    overlay.style.display = 'block';
    setTimeout(function () { overlay.style.opacity = '1'; }, 10);
    document.getElementById('popup').style.display = 'block';
  }

  function closePopup() {
    var overlay = document.getElementById('popupOverlay');
    overlay.style.opacity = '0';
    setTimeout(function () {
      overlay.style.display = 'none';
      document.getElementById('popup').style.display = 'none';
    }, 300);
  }

  function openGames() {
    try {
      if (window.parent && window.parent.changePage) {
        var btn = window.parent.document.querySelector('.nav-btn[data-glow="orange"]');
        window.parent.changePage('../moderngame/index.html', btn);
      }
    } catch (e) {}
  }

  window.showPopup = showPopup;
  window.closePopup = closePopup;
  window.openGames = openGames;

  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('quoteText').textContent =
      quotes[Math.floor(Math.random() * quotes.length)];

    var username = localStorage.getItem('nexus_username');
    if (username) {
      document.getElementById('welcomeText').textContent = 'Welcome, ' + username + '!';
    }

    var pic = localStorage.getItem('nexus_profilepic');
    var profileImage = document.getElementById('profilePic');
    if (pic) profileImage.src = pic;
    profileImage.onerror = function () {
      this.src = '../icons/user.png';
    };
  });
})();
