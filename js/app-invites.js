/* ── Game Invite Notification System ─────────────────────── */
(function() {
  var _inviteNotif    = null;
  var _inviteTimer    = null;  // auto-hide timer
  var _pollInterval   = null;
  var _shownInviteIds = new Set();
  var _activeInvite   = null;

  function initInviteNotifications() {
    _inviteNotif = document.getElementById('gameInviteNotif');
    document.getElementById('inviteNotifPlay').addEventListener('click', function() {
      if (!_activeInvite) return;
      dismissInvite(_activeInvite.id, 'accepted');
      hideInviteNotif();
      if (_activeInvite.game_path) {
        playGameFromShell(_activeInvite.game_path, _activeInvite.game_name || 'Game');
      }
    });
    document.getElementById('inviteNotifIgnore').addEventListener('click', function() {
      if (!_activeInvite) return;
      dismissInvite(_activeInvite.id, 'ignored');
      hideInviteNotif();
    });

    // Check if user is logged in; if so, start polling
    fetch('connectedcenternew/api/check_status.php')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.status === 'active') startInvitePolling();
      })
      .catch(function() {});
  }

  function startInvitePolling() {
    _pollInterval = setInterval(pollInvites, 4000);
  }

  function pollInvites() {
    fetch('connectedcenternew/api/get_game_invites.php')
      .then(function(r) { return r.json(); })
      .then(function(invites) {
        if (!Array.isArray(invites) || !invites.length) return;
        var invite = invites[0];
        if (_shownInviteIds.has(invite.id)) return;
        _shownInviteIds.add(invite.id);
        showInviteNotif(invite);
      })
      .catch(function() {});
  }

  function showInviteNotif(invite) {
    _activeInvite = invite;
    document.getElementById('inviteNotifSender').textContent = invite.from_name || 'Someone';
    document.getElementById('inviteNotifGame').textContent   = invite.game_name || 'a game';
    // Icon: try to derive from game_path
    var iconEl = document.getElementById('inviteNotifIcon');
    iconEl.textContent = '🎮';
    _inviteNotif.classList.add('show');
    clearTimeout(_inviteTimer);
    _inviteTimer = setTimeout(function() {
      hideInviteNotif();
      if (_activeInvite && _activeInvite.id === invite.id) {
        dismissInvite(invite.id, 'ignored');
        _activeInvite = null;
      }
    }, 30000);
  }

  function hideInviteNotif() {
    clearTimeout(_inviteTimer);
    _inviteNotif.classList.remove('show');
  }

  function dismissInvite(inviteId, action) {
    fetch('connectedcenternew/api/dismiss_game_invite.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ invite_id: inviteId, action: action })
    }).catch(function() {});
  }

  document.addEventListener('DOMContentLoaded', initInviteNotifications);
})();

/* ── Connected Sidebar DM notifications ──────────────────── */
(function() {
  var _dmPoll = null;
  var _lastTooltip = '';

  function updateDmBadge(data) {
    var btn = document.getElementById('connectedSidebarBtn');
    var badge = document.getElementById('connectedSidebarBadge');
    if (!btn || !badge) return;
    var count = (data && data.count) ? parseInt(data.count, 10) : 0;
    if (count > 0) {
      btn.classList.add('has-unread');
      badge.textContent = count > 99 ? '99+' : String(count);
      var tip = '';
      if (data.latest && data.latest.name) {
        tip = data.latest.name + ' sent you a message';
        if (data.count > 1) tip += ' (+' + (count - 1) + ' more)';
      } else {
        tip = count + ' unread message' + (count === 1 ? '' : 's');
      }
      if (tip !== _lastTooltip) {
        _lastTooltip = tip;
        btn.setAttribute('title', tip);
      }
    } else {
      btn.classList.remove('has-unread');
      badge.textContent = '';
      _lastTooltip = '';
      btn.setAttribute('title', 'Connected Messages');
    }
  }

  function pollDmUnread() {
    fetch('connectedcenternew/api/get_unread_dm_count.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(updateDmBadge)
      .catch(function() {});
  }

  function startDmPolling() {
    if (_dmPoll) return;
    pollDmUnread();
    _dmPoll = setInterval(pollDmUnread, 5000);
  }

  function initDmNotifications() {
    fetch('connectedcenternew/api/check_status.php', { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.status === 'active') startDmPolling();
      })
      .catch(function() {});
  }

  window.refreshConnectedDmBadge = pollDmUnread;

  window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'cc-dm-read-changed') {
      pollDmUnread();
    }
    if (e.data && e.data.type === 'cc-auth-success') {
      startDmPolling();
    }
  });

  document.addEventListener('DOMContentLoaded', initDmNotifications);
})();
