/**
 * luna copilot shell. in-game chat panel. streams markdown like it's a personality
 */
(function (global) {
  'use strict';

  var API = '/connectedcenternew/api/';
  var panel, backdrop, messagesEl, inputEl, sendBtn, statusEl, signinEl, subtitleEl, footEl;
  var busy = false;
  var loggedIn = false;
  var gameName = '';
  var chatMessages = [];
  var streamEl = null;
  var streamAbort = null;

  function $(id) { return document.getElementById(id); }

  function setStatus(text) {
    if (statusEl) statusEl.textContent = text || '';
  }

  function setBusy(on) {
    busy = !!on;
    if (sendBtn) sendBtn.disabled = on;
    if (inputEl) inputEl.disabled = on;
    if (panel) panel.classList.toggle('busy', on);
  }

  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  function renderMessages() {
    if (!messagesEl) return;
    messagesEl.innerHTML = chatMessages.map(function (m) {
      return '<div class="luna-copilot-msg ' + m.role + '">' + esc(m.content).replace(/\n/g, '<br>') + '</div>';
    }).join('');
    if (streamEl) messagesEl.appendChild(streamEl);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function appendStreamingAssistant() {
    if (!messagesEl) return null;
    var el = document.createElement('div');
    el.className = 'luna-copilot-msg assistant streaming';
    el.textContent = '';
    messagesEl.appendChild(el);
    streamEl = el;
    return el;
  }

  function updateStream(text) {
    if (streamEl) streamEl.innerHTML = esc(text).replace(/\n/g, '<br>');
    if (messagesEl) messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function consumeSseStream(response, onEvent) {
    if (!response.body || !response.body.getReader) {
      return response.text().then(function (text) {
        text.split('\n\n').forEach(function (block) {
          block.split('\n').forEach(function (line) {
            if (line.indexOf('data: ') === 0) {
              try { onEvent(JSON.parse(line.slice(6))); } catch (e) {}
            }
          });
        });
      });
    }
    var reader = response.body.getReader();
    var decoder = new TextDecoder();
    var buffer = '';
    function pump() {
      return reader.read().then(function (result) {
        if (result.done) return;
        buffer += decoder.decode(result.value, { stream: true });
        var parts = buffer.split('\n\n');
        buffer = parts.pop() || '';
        parts.forEach(function (block) {
          block.split('\n').forEach(function (line) {
            if (line.indexOf('data: ') === 0) {
              try { onEvent(JSON.parse(line.slice(6))); } catch (e) {}
            }
          });
        });
        return pump();
      });
    }
    return pump();
  }

  function refreshStatus() {
    return fetch(API + 'luna_status.php', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        loggedIn = !!d.logged_in;
        if (signinEl) signinEl.style.display = loggedIn ? 'none' : 'block';
        if (messagesEl) messagesEl.style.display = loggedIn ? 'flex' : 'none';
        if (footEl) footEl.style.display = loggedIn ? '' : 'none';
        return loggedIn;
      })
      .catch(function () {
        loggedIn = false;
        return false;
      });
  }

  function openSignIn() {
    try {
      global.parent.postMessage({ type: 'nexus-open-connected-auth', mode: 'login' }, '*');
    } catch (e) {}
  }

  function sendMessage() {
    if (busy || !inputEl || !loggedIn) return;
    var text = inputEl.value.trim();
    if (!text) return;

    chatMessages.push({ role: 'user', content: text });
    inputEl.value = '';
    inputEl.style.height = 'auto';
    renderMessages();

    var assistant = { role: 'assistant', content: '' };
    chatMessages.push(assistant);
    appendStreamingAssistant();
    setBusy(true);
    setStatus('Luna is thinking…');

    var payload = {
      conversation_id: 0,
      copilot_game: gameName || '',
      messages: chatMessages.slice(0, -1).map(function (m) {
        return { role: m.role, content: m.content };
      })
    };

    if (streamAbort) streamAbort.abort();
    streamAbort = typeof AbortController !== 'undefined' ? new AbortController() : null;

    fetch(API + 'luna_chat_stream.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'text/event-stream' },
      body: JSON.stringify(payload),
      signal: streamAbort ? streamAbort.signal : undefined
    })
      .then(function (response) {
        if (!response.ok) {
          return response.json().catch(function () { return { error: 'Luna could not respond.' }; })
            .then(function (d) { throw new Error(d.error || 'Request failed'); });
        }
        return consumeSseStream(response, function (evt) {
          if (evt.type === 'delta') {
            assistant.content += evt.text || '';
            updateStream(assistant.content);
          } else if (evt.type === 'status') {
            setStatus(evt.text || '');
          } else if (evt.type === 'done') {
            assistant.content = evt.reply || assistant.content || '…';
          } else if (evt.type === 'error') {
            throw new Error(evt.error || 'Stream error');
          }
        });
      })
      .then(function () {
        setBusy(false);
        streamEl = null;
        setStatus('');
        renderMessages();
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        setBusy(false);
        chatMessages.pop();
        if (chatMessages.length && chatMessages[chatMessages.length - 1].role === 'user') {
          chatMessages.pop();
          if (inputEl) inputEl.value = text;
        }
        streamEl = null;
        renderMessages();
        setStatus((err && err.message) ? err.message : 'Could not reach Luna.');
      });
  }

  function updateSubtitle() {
    if (!subtitleEl) return;
    if (gameName) {
      subtitleEl.textContent = 'Stuck? Ask Luna about ' + gameName;
    } else {
      subtitleEl.textContent = 'Stuck? Ask Luna about this game';
    }
  }

  function openCopilot(name) {
    if (global.NexusAI && !global.NexusAI.isEnabled('lunaCopilot')) return;
    gameName = name || '';
    chatMessages = [];
    streamEl = null;
    updateSubtitle();
    renderMessages();
    refreshStatus();
    if (backdrop) backdrop.classList.add('show');
    if (panel) panel.classList.add('open');
    setTimeout(function () { if (inputEl) inputEl.focus(); }, 280);
  }

  function closeCopilot() {
    if (backdrop) backdrop.classList.remove('show');
    if (panel) panel.classList.remove('open');
    if (streamAbort) streamAbort.abort();
    setBusy(false);
    streamEl = null;
  }

  function init() {
    panel = $('lunaCopilotPanel');
    backdrop = $('lunaCopilotBackdrop');
    messagesEl = $('lunaCopilotMessages');
    inputEl = $('lunaCopilotInput');
    sendBtn = $('lunaCopilotSend');
    statusEl = $('lunaCopilotStatus');
    signinEl = $('lunaCopilotSignin');
    subtitleEl = $('lunaCopilotSubtitle');
    footEl = document.querySelector('.luna-copilot-foot');

    var closeBtn = $('lunaCopilotClose');
    if (closeBtn) closeBtn.addEventListener('click', closeCopilot);
    if (backdrop) backdrop.addEventListener('click', closeCopilot);
    if (sendBtn) sendBtn.addEventListener('click', sendMessage);

    var signinBtn = $('lunaCopilotSigninBtn');
    if (signinBtn) signinBtn.addEventListener('click', openSignIn);

    if (inputEl) {
      inputEl.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
      inputEl.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 96) + 'px';
      });
    }

    global.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel && panel.classList.contains('open')) closeCopilot();
    });

    global.addEventListener('message', function (e) {
      if (e.data && e.data.type === 'cc-auth-success') refreshStatus();
    });
  }

  global.LunaCopilot = {
    init: init,
    open: openCopilot,
    close: closeCopilot
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window);
