(function () {
  'use strict';

  var tabs = [
    { id: 'pay', label: 'Nexus Pay', url: '../../bank/index.html', local: true },
    { id: 'chat', label: 'Nexus Chat', url: 'https://www.buttonstinks.cc/newbankpage/chat/index.php', local: false },
    { id: 'social', label: 'Nexus Social', url: 'https://www.buttonstinks.cc/nexussocial', local: false },
    { id: 'account', label: 'Account Settings', url: '../../submenus/settings.html', local: true }
  ];

  var iframe = document.getElementById('socialFrame');
  var loadingIndicator = document.getElementById('loadingIndicator');
  var tabContainer = document.getElementById('socialTabs');
  var activeUrl = '';

  function tabButton(tab, index) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'nav-tab' + (index === 0 ? ' active' : '');
    btn.innerHTML = '<span>' + tab.label + '</span>';
    btn.addEventListener('click', function () { changeSocialPage(tab.url, btn); });
    return btn;
  }

  function changeSocialPage(url, tabElement) {
    if (activeUrl === url) return;

    document.querySelectorAll('.nav-tab').forEach(function (t) {
      t.classList.remove('active');
    });
    tabElement.classList.add('active');

    iframe.classList.remove('transition-in');
    iframe.classList.add('transition-out');
    loadingIndicator.classList.add('show');

    setTimeout(function () {
      iframe.src = url;
      activeUrl = url;
      iframe.onload = function () {
        setTimeout(function () {
          iframe.classList.remove('transition-out');
          iframe.classList.add('transition-in');
          loadingIndicator.classList.remove('show');
        }, 100);
      };
    }, 250);
  }

  tabs.forEach(function (tab, i) {
    tabContainer.appendChild(tabButton(tab, i));
  });

  if (tabs.length) {
    activeUrl = tabs[0].url;
    iframe.src = tabs[0].url;
  }

  document.addEventListener('keydown', function (e) {
    var tabEls = document.querySelectorAll('.nav-tab');
    var idx = Array.prototype.findIndex.call(tabEls, function (t) {
      return t.classList.contains('active');
    });
    if (e.key === 'ArrowLeft' && idx > 0) tabEls[idx - 1].click();
    if (e.key === 'ArrowRight' && idx < tabEls.length - 1) tabEls[idx + 1].click();
  });
})();
