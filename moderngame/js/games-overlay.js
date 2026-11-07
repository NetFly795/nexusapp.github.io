(function () {
  function $(selector, root) { return (root || document).querySelector(selector); }
  function $all(selector, root) { return Array.prototype.slice.call((root || document).querySelectorAll(selector)); }

  var overlay = $('#gameOverlay');
  var frame = $('#gameFrame');
  var leaveBtn = $('#leaveGameButton');
  var titleEl = $('#gameTitle');
  var lastOpenedAnchor = null;
  var lastOpenSourceEl = null;
  var lastOpenBgUrl = '';

  // Glassmorphic circular HUD controls
  var inviteBtn = $('#inviteButton');
  var settingsBtn = $('#settingsButton');
  var lunaCopilotBtn = $('#lunaCopilotButton');
  var settingsPopup = $('#gameSettingsPopup');
  var inviteModal = $('#gameInviteModal');
  var closeSettings = $('#closeSettingsBtn');
  var closeInvite = $('#closeInviteBtn');
  
  // Settings elements
  var fpsLimiter = $('#fpsLimiter');
  var fpsLimitValue = $('#fpsLimitValue');
  var zoomSlider = $('#zoomSlider');
  var zoomValue = $('#zoomValue');
  var settingsInvitedList = $('#settingsInvitedList');
  var inviteModalPlayersList = $('#inviteModalPlayersList');
  
  // Invite elements
  var copyInviteBtn = $('#copyInviteLinkBtn');
  var inviteShareLink = $('#inviteShareLink');
  var glassAlert = $('#glassAlert');

  // Helpers
  function showGlassAlert(message) {
    if (!glassAlert) return;
    glassAlert.textContent = message;
    glassAlert.classList.add('show');
    setTimeout(function() {
      glassAlert.classList.remove('show');
    }, 3000);
  }

  // ── Game Invite System ────────────────────────────────────────────────
  var _inviteSearchTimer = null;
  var _inviteConnected   = null; // null=unknown, true/false
  var _invitedSession    = [];   // [{id,name,status}]

  function ccAvatarUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//.test(path) || path.indexOf('data:') === 0) return path;
    return '../connectedcenternew/' + path.replace(/^\//, '');
  }

  function appendInvitedPlayerRow(listEl, userName, statusText, statusClass) {
    if (!listEl) return;
    var empty = listEl.querySelector('.no-players-msg');
    if (empty) empty.remove();
    var row = document.createElement('div');
    row.className = 'invited-player-row';
    row.innerHTML = '<div class="invited-player-left"><span>' + _escHtml(userName) + '</span></div>'
      + '<span class="invited-player-status ' + (statusClass || 'pending') + '">' + _escHtml(statusText || 'Pending…') + '</span>';
    listEl.appendChild(row);
  }

  function syncInvitedLists() {
    var html = _invitedSession.length
      ? ''
      : '<div class="no-players-msg">No players invited yet. Tap Add to invite friends!</div>';
    if (settingsInvitedList) {
      settingsInvitedList.innerHTML = html;
      _invitedSession.forEach(function(p) {
        appendInvitedPlayerRow(settingsInvitedList, p.name, p.status === 'sent' ? 'Invite sent' : 'Pending…', p.status === 'sent' ? 'joined' : 'pending');
      });
    }
    if (inviteModalPlayersList) {
      inviteModalPlayersList.innerHTML = _invitedSession.length
        ? ''
        : '<div class="no-players-msg" style="color:rgba(255,255,255,0.45);font-size:12px;">No invites sent yet.</div>';
      _invitedSession.forEach(function(p) {
        appendInvitedPlayerRow(inviteModalPlayersList, p.name, p.status === 'sent' ? 'Sent!' : 'Pending…', p.status === 'sent' ? 'joined' : 'pending');
      });
    }
  }

  window.showGameInviteModal = function(btn, event) {
    if (event) { event.stopPropagation(); event.preventDefault(); }
    if (!inviteModal) return;
    if (settingsPopup) settingsPopup.classList.remove('active');
    var opening = !inviteModal.classList.contains('active');
    inviteModal.classList.toggle('active');
    if (!opening) return;

    var nameSpan = document.getElementById('inviteModalGameName');
    if (nameSpan) nameSpan.textContent = _currentGameName || 'Game';

    var searchInput = document.getElementById('inviteUserSearchInput');
    var results = document.getElementById('inviteUserResults');
    if (searchInput) { searchInput.value = ''; searchInput.disabled = true; }
    if (results) {
      results.innerHTML = '<div class="no-players-msg" style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">Checking Connected Center…</div>';
    }
    syncInvitedLists();

    fetch('../connectedcenternew/api/check_status.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _inviteConnected = (d.status === 'active');
        var notLoggedIn = document.getElementById('inviteNotLoggedIn');
        if (!_inviteConnected) {
          if (notLoggedIn) notLoggedIn.style.display = 'block';
          if (searchInput) searchInput.disabled = true;
          if (results) {
            results.innerHTML = '<div class="no-players-msg" style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">Sign in to search for players.</div>';
          }
        } else {
          if (notLoggedIn) notLoggedIn.style.display = 'none';
          if (searchInput) { searchInput.disabled = false; searchInput.focus(); }
          if (results) {
            results.innerHTML = '<div class="no-players-msg" style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">Type a username to search.</div>';
          }
        }
      })
      .catch(function() {
        _inviteConnected = false;
        var notLoggedIn = document.getElementById('inviteNotLoggedIn');
        if (notLoggedIn) notLoggedIn.style.display = 'block';
        if (results) {
          results.innerHTML = '<div style="color:rgba(255,100,100,0.8);font-size:13px;padding:8px 0;">Could not reach Connected Center.</div>';
        }
      });
  };

  window.searchInviteUsers = function() {
    if (!_inviteConnected) return;
    clearTimeout(_inviteSearchTimer);
    _inviteSearchTimer = setTimeout(function() {
      var q = (document.getElementById('inviteUserSearchInput') || {}).value || '';
      var results = document.getElementById('inviteUserResults');
      if (!results) return;
      if (q.trim().length < 1) {
        results.innerHTML = '<div class="no-players-msg" style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">Type a username to search.</div>';
        return;
      }
      results.innerHTML = '<div style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">Searching...</div>';
      fetch('../connectedcenternew/api/search_users.php?q=' + encodeURIComponent(q.trim()), { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
          if (!results) return;
          if (data.error) {
            results.innerHTML = '<div style="color:rgba(255,100,100,0.8);font-size:13px;padding:8px 0;">' + _escHtml(data.error) + '</div>';
            return;
          }
          var users = data.users || [];
          if (!users.length) {
            results.innerHTML = '<div class="no-players-msg" style="color:rgba(255,255,255,0.5);font-size:13px;padding:8px 0;">No users found.</div>';
            return;
          }
          results.innerHTML = users.map(function(u) {
            var name = u.display_name || u.username;
            var av = ccAvatarUrl(u.profile_picture);
            var avatarHtml = av
              ? '<img src="' + _escHtml(av) + '" class="friend-avatar" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;" />'
              : '<div class="friend-avatar" style="width:36px;height:36px;border-radius:50%;background:#5865f2;display:flex;align-items:center;justify-content:center;font-weight:700;color:#fff;font-size:14px;">' + _escHtml(name.charAt(0).toUpperCase()) + '</div>';
            var already = _invitedSession.some(function(p) { return p.id === u.id; });
            return '<div class="friend-item">'
              + '<div class="friend-info">' + avatarHtml
              + '<div><div class="friend-name">' + _escHtml(name) + '</div>'
              + '<div class="friend-status" style="color:rgba(255,255,255,0.5);font-size:12px;">@' + _escHtml(u.username) + '</div>'
              + '</div></div>'
              + '<button type="button" class="invite-action-btn' + (already ? ' invited' : '') + '" data-userid="' + u.id + '" data-name="' + _escHtml(name) + '"' + (already ? ' disabled' : '') + '>'
              + (already ? 'Sent!' : 'Add') + '</button>'
              + '</div>';
          }).join('');
          results.querySelectorAll('.invite-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
              e.stopPropagation();
              if (btn.classList.contains('invited') || btn.disabled) return;
              var userId = parseInt(btn.getAttribute('data-userid'), 10);
              var userName = btn.getAttribute('data-name');
              btn.textContent = '…';
              btn.disabled = true;
              fetch('../connectedcenternew/api/send_game_invite.php', {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  to_user_id: userId,
                  game_name:  _currentGameName || 'a game',
                  game_path:  _currentGamePath || ''
                })
              }).then(function(r) { return r.json(); })
                .then(function(d) {
                  if (d.error) {
                    btn.textContent = 'Add';
                    btn.disabled = false;
                    showGlassAlert('Error: ' + d.error);
                  } else {
                    btn.classList.add('invited');
                    btn.textContent = 'Sent!';
                    showGlassAlert('Invite sent to ' + userName + '!');
                    _invitedSession.push({ id: userId, name: userName, status: 'sent' });
                    syncInvitedLists();
                  }
                }).catch(function() {
                  btn.textContent = 'Add';
                  btn.disabled = false;
                  showGlassAlert('Could not send invite.');
                });
            });
          });
        })
        .catch(function() {
          results.innerHTML = '<div style="color:rgba(255,100,100,0.8);font-size:13px;padding:8px 0;">Could not reach Connected Center.</div>';
        });
    }, 320);
  };

  function _escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function openConnectedSignIn(mode) {
    var payload = mode === 'login'
      ? { type: 'nexus-open-connected-auth', mode: 'login' }
      : { type: 'nexus-open-connected-account' };
    try {
      if (window.top && window.top !== window) {
        window.top.postMessage(payload, '*');
        if (inviteModal) inviteModal.classList.remove('active');
        return;
      }
    } catch (e) {}
    showConnectedSettingsPopup(payload);
  }

  function openConnectedAccount(event) {
    if (event) { event.stopPropagation(); event.preventDefault(); }
    openConnectedSignIn('account');
  }

  function showConnectedSettingsPopup(payload) {
    var popup = document.getElementById('connectedPopup');
    var content = document.getElementById('connectedContent');
    var backdrop = document.getElementById('popupBackdrop');
    if (!popup || !content) return;
    content.innerHTML = '<iframe id="connectedSettingsFrame" src="../submenus/settings.html" style="width:100%;height:100%;border:none;border-radius:15px;" title="Nexus Settings"></iframe>';
    var iframe = document.getElementById('connectedSettingsFrame');
    if (iframe) {
      iframe.onload = function() {
        try {
          iframe.contentWindow.postMessage(payload || { type: 'open-connected-account' }, '*');
        } catch (e) {}
      };
    }
    if (backdrop) {
      backdrop.style.display = 'block';
      backdrop.offsetHeight;
      backdrop.classList.add('active');
    }
    popup.style.display = 'block';
    popup.offsetHeight;
    popup.classList.add('active');
  }

  window.openConnectedAccount = openConnectedAccount;
  window.openConnectedSignIn = openConnectedSignIn;
  window.showConnectedTooltip = openConnectedAccount;

  // Toggles Settings Popup
  if (settingsBtn && settingsPopup) {
    settingsBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      if (inviteModal) inviteModal.classList.remove('active');
      settingsPopup.classList.toggle('active');
    });
  }

  if (inviteBtn) {
    inviteBtn.addEventListener('click', function(e) {
      showGameInviteModal(inviteBtn, e);
    });
  }

  // Invite modal is now handled by showGameInviteModal (Connected Center integration)

  // Close Popups when close buttons clicked
  if (closeSettings && settingsPopup) {
    closeSettings.addEventListener('click', function(e) {
      e.stopPropagation();
      settingsPopup.classList.remove('active');
    });
  }
  if (closeInvite && inviteModal) {
    closeInvite.addEventListener('click', function(e) {
      e.stopPropagation();
      inviteModal.classList.remove('active');
    });
  }

  // Close Popups on clicking anywhere outside
  document.addEventListener('click', function(e) {
    if (settingsPopup && settingsPopup.classList.contains('active')) {
      if (!settingsPopup.contains(e.target) && e.target !== settingsBtn && !settingsBtn.contains(e.target)) {
        settingsPopup.classList.remove('active');
      }
    }
    if (inviteModal && inviteModal.classList.contains('active')) {
      if (!inviteModal.contains(e.target) && e.target !== inviteBtn && !inviteBtn.contains(e.target)) {
        inviteModal.classList.remove('active');
      }
    }
  });

  // FPS Limiter Slider
  if (fpsLimiter && fpsLimitValue) {
    fpsLimiter.addEventListener('input', function() {
      fpsLimitValue.textContent = fpsLimiter.value + ' FPS';
    });
  }

  // Zoom Slider (Physically scales the game frame!)
  if (zoomSlider && zoomValue && frame) {
    zoomSlider.addEventListener('input', function() {
      var val = zoomSlider.value;
      zoomValue.textContent = val + '%';
      
      // Apply scale and size adjustment to prevent border clipping
      var scale = val / 100;
      frame.style.transform = 'scale(' + scale + ')';
      frame.style.transformOrigin = 'center center';
      frame.style.width = (100 / scale) + '%';
      frame.style.height = (100 / scale) + '%';
    });
  }

  // Copy Invite Link
  if (copyInviteBtn && inviteShareLink) {
    copyInviteBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      inviteShareLink.select();
      inviteShareLink.setSelectionRange(0, 99999);
      try {
        navigator.clipboard.writeText(inviteShareLink.value);
        showGlassAlert('Invite link copied to clipboard!');
      } catch (err) {
        // Fallback
        document.execCommand('copy');
        showGlassAlert('Invite link copied to clipboard!');
      }
    });
  }

  // Invite actions are handled dynamically inside searchInviteUsers() (Connected Center integration)

  function getTileElement(anchor) {
    return anchor.querySelector('.game-tile') || anchor;
  }

  function getTileImageUrl(anchor) {
    var img = anchor.querySelector('.game-icon');
    return img ? img.getAttribute('src') : '';
  }

  // Windows 8-style 3D Flip Launch Animation
  function animateWin8FlipOpen(el, bgUrl, titleText, done) {
    try {
      var rect = el ? el.getBoundingClientRect() : null;
      if (!rect || rect.width === 0 || rect.height === 0) {
        // Fallback: center of screen 300x400 card
        var defaultWidth = 300;
        var defaultHeight = 400;
        rect = {
          top: (window.innerHeight - defaultHeight) / 2,
          left: (window.innerWidth - defaultWidth) / 2,
          width: defaultWidth,
          height: defaultHeight,
          right: (window.innerWidth - defaultWidth) / 2 + defaultWidth,
          bottom: (window.innerHeight - defaultHeight) / 2 + defaultHeight
        };
      }
      
      var topVal = rect.top;
      var leftVal = rect.left;
      var bottomVal = window.innerHeight - rect.bottom;
      var rightVal = window.innerWidth - rect.right;
      
      // Create iOS fluid clip-path container
      var container = document.createElement('div');
      container.className = 'ios-open-container';
      container.style.clipPath = 'inset(' + topVal + 'px ' + rightVal + 'px ' + bottomVal + 'px ' + leftVal + 'px round 24px)';
      
      // Front face: pixel-perfect cloned game tile
      var frontFace = document.createElement('div');
      frontFace.className = 'ios-face ios-face-front';
      if (el) {
        var clone = el.cloneNode(true);
        // Reset transformations, margins, and absolute position inside the front face frame
        clone.style.transform = 'none';
        clone.style.transition = 'none';
        clone.style.boxShadow = 'none';
        clone.style.margin = '0';
        clone.style.position = 'absolute';
        clone.style.top = rect.top + 'px';
        clone.style.left = rect.left + 'px';
        clone.style.width = rect.width + 'px';
        clone.style.height = rect.height + 'px';
        clone.style.borderRadius = '24px';
        clone.style.background = window.getComputedStyle(el).background;
        
        // Ensure we copy the image source correctly
        var cloneImg = clone.querySelector('.game-icon');
        var origImg = el.querySelector('.game-icon');
        if (cloneImg && origImg) {
          cloneImg.src = origImg.src;
        }
        frontFace.appendChild(clone);
      } else {
        frontFace.style.background = 'linear-gradient(135deg, #1e88e5 0%, #1565c0 100%)';
      }
      
      // Back face: iOS splash/loading screen
      var backFace = document.createElement('div');
      backFace.className = 'ios-face ios-face-back';
      
      var loaderDiv = document.createElement('div');
      loaderDiv.className = 'ios-splash-loader';
      
      var spinner = document.createElement('div');
      spinner.className = 'ios-spinner';
      
      var loaderTitle = document.createElement('div');
      loaderTitle.className = 'loader-title';
      loaderTitle.textContent = titleText || 'Loading…';
      
      loaderDiv.appendChild(spinner);
      loaderDiv.appendChild(loaderTitle);
      backFace.appendChild(loaderDiv);
      
      container.appendChild(frontFace);
      container.appendChild(backFace);
      document.body.appendChild(container);
      
      // Force browser reflow to register initial styles
      container.offsetHeight;
      
      // Trigger rapid expansion and cross-fade
      requestAnimationFrame(function() {
        container.style.clipPath = 'inset(0px round 0px)';
        container.classList.add('toFullscreen');
      });
      
      function cleanup() {
        if (container && container.parentNode) {
          container.parentNode.removeChild(container);
        }
      }
      
      // Listen for fluid expansion completion
      container.addEventListener('transitionend', function onEnd(e) {
        if (e.target !== container || e.propertyName !== 'clip-path') return;
        container.removeEventListener('transitionend', onEnd);
        cleanup();
        if (typeof done === 'function') done();
      });
      
      // Safety timeout (matches the 400ms transition time with a buffer)
      setTimeout(function() {
        if (document.body.contains(container)) {
          cleanup();
          if (typeof done === 'function') done();
        }
      }, 600);
      
    } catch (e) {
      if (typeof done === 'function') done();
    }
  }

  // Smooth overlay close (fade + scale — no clip-path jank)
  function animateOverlayClose(done) {
    prepareForCloseAnimation();
    if (!overlay.classList.contains('visible')) {
      if (typeof done === 'function') done();
      return;
    }
    overlay.classList.add('closing');
    overlay.setAttribute('aria-hidden', 'true');
    frame.src = 'about:blank';
    var finished = false;
    function finish() {
      if (finished) return;
      finished = true;
      overlay.classList.remove('closing');
      if (typeof done === 'function') done();
    }
    overlay.addEventListener('transitionend', function onEnd(e) {
      if (e.target !== overlay || e.propertyName !== 'opacity') return;
      overlay.removeEventListener('transitionend', onEnd);
      finish();
    });
    setTimeout(finish, 420);
  }

  function runCloseAnimation(done) {
    animateOverlayClose(done);
  }

  function animateOpenFromElement(el, bgUrl, titleText, done) {
    if (typeof titleText === 'function') {
      done = titleText;
      titleText = 'Game';
    }
    animateWin8FlipOpen(el, bgUrl, titleText, done);
  }

  function saveLastPlayedGame(name, canonicalHref, image, category) {
    if (!canonicalHref) return;
    if (window.NexusAI) {
      NexusAI.recordGamePlay({
        name: name || 'Game',
        href: canonicalHref,
        image: image || '',
        category: category || ''
      });
    }
  }

  function notifyShellGameState(active, name, href) {
    try {
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: active ? 'nexus-game-active' : 'nexus-game-inactive',
          game: active ? { name: name || 'Game', href: href || '' } : null
        }, '*');
      }
    } catch (e) {}
  }

  /* Among Us (Connected Center) — append CC username to URL */
  var _ccUsernameCache = null;
  var _ccUsernamePending = null;

  function isAmongUsCcHref(href) {
    return href && /amongus\.html/i.test(href);
  }

  function fetchCcUsername() {
    if (_ccUsernameCache) return Promise.resolve(_ccUsernameCache);
    if (_ccUsernamePending) return _ccUsernamePending;
    _ccUsernamePending = fetch('../connectedcenternew/api/check_status.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _ccUsernameCache = (d.status === 'active' && d.user && d.user.username) ? d.user.username : '';
        return _ccUsernameCache;
      })
      .catch(function() { return ''; })
      .finally(function() { _ccUsernamePending = null; });
    return _ccUsernamePending;
  }

  function appendCcUsernameParam(href, username) {
    if (!username) return href;
    var sep = href.indexOf('?') >= 0 ? '&' : '?';
    return href + sep + 'username=' + encodeURIComponent(username);
  }

  function resolveGameHref(href, callback) {
    if (!isAmongUsCcHref(href)) {
      callback(href);
      return;
    }
    fetchCcUsername().then(function(username) {
      callback(appendCcUsernameParam(href, username));
    });
  }

  function openInOverlayHref(href, titleText) {
    if (!href) return;
    resolveGameHref(href, function(resolvedHref) {
    titleEl.textContent = titleText || resolvedHref;
    _currentGameName = titleText || resolvedHref;
    _currentGamePath = resolvedHref;

    var iconSrc = '';
    var anchor = $('a.game-link[href="' + href + '"]');
    if (anchor) iconSrc = getTileImageUrl(anchor);
    var gameCategory = anchor ? (anchor.getAttribute('data-category') || '') : '';
    saveLastPlayedGame(titleText || resolvedHref, href, iconSrc, gameCategory);
    
    // Bind to settings popup
    var settingsGameName = $('#settingsGameName');
    var settingsGameIcon = $('#settingsGameIcon');
    if (settingsGameName) {
      settingsGameName.textContent = titleText || 'Game';
    }
    if (settingsGameIcon) {
      var anchor = $('a.game-link[href="' + href + '"]');
      var iconSrc = anchor ? getTileImageUrl(anchor) : '';
      if (iconSrc) {
        settingsGameIcon.src = iconSrc;
        settingsGameIcon.style.display = 'block';
      } else {
        settingsGameIcon.style.display = 'none';
      }
    }

    frame.src = resolvedHref;
    overlay.classList.add('visible');
    overlay.setAttribute('aria-hidden', 'false');
    notifyShellGameState(true, titleText || resolvedHref, resolvedHref);
    overlay.classList.add('contentHidden');
    var sticky = document.querySelector('.sticky-header');
    if (sticky) {
      sticky.classList.remove('merged');
      sticky.style.setProperty('--merge-progress', '0');
    }
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){
        overlay.classList.remove('contentHidden');
        // Activate performance mode once fluid expand and fade are complete
        setTimeout(function() {
          document.body.classList.add('performance-mode');
        }, 400);
      });
    });
    }); /* resolveGameHref */
  }

  function animateOpenFrom(anchor, done) {
    var tileEl = getTileElement(anchor);
    var bgUrl = getTileImageUrl(anchor);
    var titleNode = anchor.querySelector('.game-title');
    var titleText = titleNode ? titleNode.textContent.trim() : 'Game';
    animateWin8FlipOpen(tileEl, bgUrl, titleText, done);
  }

  window.animateOpenFrom = animateOpenFrom;

  // Expose a global helper so other scripts (e.g., details pane) can open the overlay with a custom morph origin
  window.NexusOverlayOpenFromElement = function(originEl, href, title, bgUrl){
    try {
      lastOpenSourceEl = originEl || null;
      lastOpenBgUrl = bgUrl || '';
      animateOpenFromElement(originEl || document.body, lastOpenBgUrl, title, function(){
        openInOverlayHref(href, title);
      });
    } catch (e) {
      openInOverlayHref(href, title);
    }
  };
  
  // Expose openInOverlayHref globally for search functionality
  window.openInOverlayHref = openInOverlayHref;
  window.openInOverlay = openInOverlay;

  // Track current game for invite system
  var _currentGameName = '';
  var _currentGamePath = '';

  function openInOverlay(anchor) {
    var href = anchor.getAttribute('href');
    if (!href) return;
    var titleNode = anchor.querySelector('.game-title');
    var title = titleNode ? titleNode.textContent.trim() : href;
    resolveGameHref(href, function(resolvedHref) {
    titleEl.textContent = title || 'Game';
    _currentGameName = title || 'Game';
    _currentGamePath = resolvedHref;

    var iconSrc = getTileImageUrl(anchor);
    var gameCategory = anchor.getAttribute('data-category') || '';
    saveLastPlayedGame(title || 'Game', href, iconSrc, gameCategory);
    
    // Bind to settings popup
    var settingsGameName = $('#settingsGameName');
    var settingsGameIcon = $('#settingsGameIcon');
    if (settingsGameName) {
      settingsGameName.textContent = title || 'Game';
    }
    if (settingsGameIcon) {
      var iconSrc = getTileImageUrl(anchor);
      if (iconSrc) {
        settingsGameIcon.src = iconSrc;
        settingsGameIcon.style.display = 'block';
      } else {
        settingsGameIcon.style.display = 'none';
      }
    }

    frame.src = resolvedHref;
    overlay.classList.add('visible');
    overlay.setAttribute('aria-hidden', 'false');
    notifyShellGameState(true, title || 'Game', resolvedHref);
    overlay.classList.add('contentHidden');
    var sticky = document.querySelector('.sticky-header');
    if (sticky) {
      sticky.classList.remove('merged');
      sticky.style.setProperty('--merge-progress', '0');
    }
    requestAnimationFrame(function(){
      requestAnimationFrame(function(){
        overlay.classList.remove('contentHidden');
        // Activate performance mode once fluid expand and fade are complete
        setTimeout(function() {
          document.body.classList.add('performance-mode');
        }, 400);
      });
    });
    lastOpenedAnchor = anchor;
    }); /* resolveGameHref */
  }

  function prepareForCloseAnimation() {
    document.body.classList.remove('performance-mode');
  }

  function closeOverlayImmediate() {
    document.body.classList.remove('performance-mode');
    overlay.classList.remove('visible', 'closing', 'contentHidden');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.style.transform = '';
    overlay.style.opacity = '';
    overlay.style.borderRadius = '';
    frame.src = 'about:blank';
    notifyShellGameState(false);
    
    // Close and reset glass popups and sliders
    if (settingsPopup) settingsPopup.classList.remove('active');
    if (inviteModal) inviteModal.classList.remove('active');
    
    _invitedSession = [];
    syncInvitedLists();
    
    var inviteActionBtns = $all('.invite-action-btn');
    inviteActionBtns.forEach(function(btn) {
      btn.classList.remove('invited');
      btn.textContent = 'Add';
      btn.disabled = false;
    });
    
    if (fpsLimiter) {
      fpsLimiter.value = 60;
      fpsLimitValue.textContent = '60 FPS';
    }
    if (zoomSlider) {
      zoomSlider.value = 100;
      zoomValue.textContent = '100%';
    }
    if (frame) {
      frame.style.transform = '';
      frame.style.width = '100%';
      frame.style.height = '100%';
    }

    lastOpenedAnchor = null;
    lastOpenSourceEl = null;
    lastOpenBgUrl = '';
    window.lastSearchIcon = null;
    window.lastOpenedFromSearch = false;
  }
  
  // Use event delegation so dynamically created links open the launch sheet
  document.addEventListener('click', function(e){
    if (e.target && e.target.closest && e.target.closest('.qp-pin-btn')) return;
    var a = e.target && e.target.closest ? e.target.closest('a.game-link') : null;
    if (!a) return;
    if (!document.contains(a)) return;
    e.preventDefault();
    window.lastOpenedFromSearch = false;
    if (window.NexusGameLaunchSheet) {
      window.NexusGameLaunchSheet.openFromAnchor(a);
      return;
    }
    animateOpenFrom(a, function(){ openInOverlay(a); });
  });

  if (leaveBtn) {
    leaveBtn.addEventListener('click', function () {
      runCloseAnimation(function(){
        closeOverlayImmediate();
      });
    });
  }

  if (lunaCopilotBtn) {
    lunaCopilotBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (window.parent && window.parent !== window) {
        window.parent.postMessage({
          type: 'nexus-open-luna-copilot',
          game: { name: _currentGameName, href: _currentGamePath }
        }, '*');
      }
    });
  }

  if (collapseBtn && topBar) {
    collapseBtn.addEventListener('click', function(){
      var collapsed = topBar.classList.toggle('collapsed');
      collapseBtn.textContent = collapsed ? 'Expand' : 'Collapse';
      collapseBtn.setAttribute('aria-label', collapsed ? 'Expand' : 'Collapse');
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (window.NexusGameLaunchSheet && window.NexusGameLaunchSheet.isOpen()) return;
    if (overlay.classList.contains('visible')) {
      runCloseAnimation(function(){
        closeOverlayImmediate();
      });
    }
  });

  window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'nexus-play-game' && e.data.href) {
      openInOverlayHref(e.data.href, e.data.title || e.data.name || 'Game');
    }
  });

  try {
    if (window.parent && window.parent !== window) {
      window.parent.postMessage({ type: 'nexus-games-ready' }, '*');
    }
  } catch (_) {}
})();
