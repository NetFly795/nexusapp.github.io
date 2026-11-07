/**
 * luna chat proper. conversations, streaming, image quota drama, the works
 */
(function () {
  'use strict';

  var API = '../connectedcenternew/api/';
  var ACTIVE_CONV_KEY = 'nexus_luna_active_conv';
  var messages = [];
  var conversations = [];
  var currentConversationId = null;
  var busy = false;
  var streamAbort = null;
  var streamingEl = null;

  var page, list, input, sendBtn, quotaEl, signinEl, chatEl, statusEl;
  var emptyState, aurora, composer;
  var convDrawer, convBackdrop, convList, historyBtn;
  var _animRafId = null;

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function apiJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'include' }, options || {}))
      .then(function (r) { return r.json(); });
  }

  function setQuota(q) {
    if (!quotaEl || !q) return;
    quotaEl.textContent = q.remaining + ' of ' + q.limit + ' images left today';
  }

  function formatWhen(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    var now = new Date();
    if (d.toDateString() === now.toDateString()) {
      return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  function updateEmptyState() {
    if (!emptyState || !list) return;
    var hasMessages = messages.length > 0;
    var wasHidden = emptyState.classList.contains('hidden');
    emptyState.classList.toggle('hidden', hasMessages);
    list.classList.toggle('has-msgs', hasMessages);
    if (!hasMessages && wasHidden) animateEmptyHero();
  }

  function animateEmptyHero() {
    if (!emptyState || emptyState.classList.contains('hidden')) return;
    var parts = emptyState.querySelectorAll('.luna-icon-hero, .luna-empty-title, .luna-empty-caption');
    parts.forEach(function (el) {
      el.classList.remove('luna-empty-part-animate');
      el.style.animation = 'none';
      el.style.opacity = '0';
    });
    void emptyState.offsetHeight;
    requestAnimationFrame(function () {
      parts.forEach(function (el, i) {
        el.style.setProperty('--empty-i', i);
        el.style.animation = '';
        el.style.opacity = '';
        el.classList.add('luna-empty-part-animate');
      });
    });
  }

  function isVisibleEl(el) {
    return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
  }

  function fireLunaAnimation(originX, originY) {
    if (_animRafId) {
      cancelAnimationFrame(_animRafId);
      _animRafId = null;
    }

    var items = Array.from(document.querySelectorAll('.rise')).filter(isVisibleEl);
    items.forEach(function (el) {
      el.style.animation = 'none';
      el.style.opacity = '0';
    });
    void document.body.offsetHeight;

    var ox = (originX != null) ? originX : window.innerWidth / 2;
    var oy = (originY != null) ? originY : window.innerHeight / 2;
    var isDock = (originX != null);

    _animRafId = requestAnimationFrame(function () {
      _animRafId = requestAnimationFrame(function () {
        _animRafId = null;
        items.forEach(function (el, i) {
          var r = el.getBoundingClientRect();
          var dx = ox - (r.left + r.width / 2);
          var dy = oy - (r.top + r.height / 2);
          el.style.setProperty('--dx', dx + 'px');
          el.style.setProperty('--dy', dy + 'px');
          var dur = isDock ? 0.52 : 0.60;
          var stagger = isDock ? 0.042 : 0.058;
          var delay = 0.02 + i * stagger;
          el.style.animation = 'lunaDockBurst ' + dur + 's cubic-bezier(0.22,1,0.36,1) ' + delay + 's both';
        });
        if (emptyState && !emptyState.classList.contains('hidden')) {
          setTimeout(animateEmptyHero, isDock ? 280 : 320);
        }
      });
    });
  }

  function setStatusText(text) {
    if (!statusEl) return;
    statusEl.textContent = text || '';
    statusEl.classList.toggle('luna-status-visible', !!text);
  }

  function animateMessageElements(els, startIndex) {
    if (!els || !els.length) return;
    requestAnimationFrame(function () {
      Array.prototype.forEach.call(els, function (el, i) {
        el.style.setProperty('--i', (startIndex || 0) + i);
        el.classList.add('luna-msg-enter');
      });
    });
  }

  function animateConvItems() {
    if (!convList) return;
    requestAnimationFrame(function () {
      convList.querySelectorAll('.luna-conv-item').forEach(function (el, i) {
        el.style.setProperty('--i', i);
        el.classList.add('luna-conv-enter');
      });
    });
  }

  function buildAssistantImagesHtml(images) {
    if (!images || !images.length) return '';
    return images.map(function (img) {
      var src = esc(img.data_url || img.url || '');
      return '<div class="luna-image-wrap"><img src="' + src + '" alt="Generated image" loading="lazy" /></div>';
    }).join('');
  }

  function buildMessageHtml(m, streaming) {
    if (m.role === 'user') {
      return '<div class="luna-msg luna-msg-user"><div class="luna-bubble">' + esc(m.content) + '</div></div>';
    }
    var cls = 'luna-msg luna-msg-assistant' + (streaming ? ' streaming' : '');
    var body = typeof lunaRenderMarkdown === 'function'
      ? lunaRenderMarkdown(m.content || '', !!streaming)
      : esc(m.content || '');
    return '<div class="' + cls + '"><div class="luna-bubble luna-md">' + body
      + buildAssistantImagesHtml(m.images) + '</div></div>';
  }

  function renderMessages(stagger) {
    if (!list) return;
    list.innerHTML = messages.map(function (m) {
      return buildMessageHtml(m, false);
    }).join('');
    updateEmptyState();
    if (stagger) animateMessageElements(list.querySelectorAll('.luna-msg'));
    if (list.scrollHeight) list.scrollTop = list.scrollHeight;
  }

  function appendStreamingAssistant() {
    if (!list) return null;
    updateEmptyState();
    var el = document.createElement('div');
    el.className = 'luna-msg luna-msg-assistant streaming luna-msg-enter';
    el.style.setProperty('--i', 0);
    el.innerHTML = '<div class="luna-bubble luna-md"></div>';
    list.appendChild(el);
    list.scrollTop = list.scrollHeight;
    return el;
  }

  function updateStreamingBubble(text, images) {
    if (!streamingEl) return;
    var bubble = streamingEl.querySelector('.luna-bubble');
    if (!bubble) return;
    bubble.classList.add('luna-md');
    var html = typeof lunaRenderMarkdown === 'function'
      ? lunaRenderMarkdown(text, true)
      : esc(text);
    bubble.innerHTML = html + buildAssistantImagesHtml(images);
    if (list) list.scrollTop = list.scrollHeight;
  }

  function setAurora(on) {
    if (aurora) aurora.classList.toggle('active', !!on);
    if (composer) composer.classList.toggle('busy', !!on);
  }

  function setBusy(on) {
    busy = on;
    if (sendBtn) sendBtn.disabled = on;
    if (input) input.disabled = on;
    setAurora(on);
    if (!on) setStatusText('');
  }

  function showSignedOut() {
    if (page) page.classList.add('signed-out');
    closeConvDrawer();
  }

  function showSignedIn() {
    if (page) page.classList.remove('signed-out');
  }

  function rememberActiveConversation(id) {
    try {
      if (id) sessionStorage.setItem(ACTIVE_CONV_KEY, String(id));
      else sessionStorage.removeItem(ACTIVE_CONV_KEY);
    } catch (e) {}
  }

  function renderConvList() {
    if (!convList) return;
    if (!conversations.length) {
      convList.innerHTML = '<div class="luna-conv-empty">No saved chats yet.<br>Start one with Luna when you\'re ready.</div>';
      return;
    }
    convList.innerHTML = conversations.map(function (c) {
      var active = currentConversationId === c.id ? ' active' : '';
      return '<div class="luna-conv-item' + active + '" data-id="' + c.id + '">'
        + '<div class="luna-conv-item-body">'
        + '<div class="luna-conv-item-title">' + esc(c.title || 'New chat') + '</div>'
        + '<div class="luna-conv-item-meta">' + esc(formatWhen(c.updated_at)) + '</div>'
        + '</div>'
        + '<button class="luna-conv-delete" type="button" data-id="' + c.id + '" title="Delete chat">&times;</button>'
        + '</div>';
    }).join('');

    convList.querySelectorAll('.luna-conv-item').forEach(function (el) {
      el.addEventListener('click', function (e) {
        if (e.target.closest('.luna-conv-delete')) return;
        selectConversation(parseInt(el.getAttribute('data-id'), 10));
      });
    });
    convList.querySelectorAll('.luna-conv-delete').forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        deleteConversation(parseInt(btn.getAttribute('data-id'), 10));
      });
    });
  }

  function fetchConversations() {
    return apiJson(API + 'luna_conversations.php')
      .then(function (d) {
        if (!d.success) return;
        conversations = d.conversations || [];
        renderConvList();
      })
      .catch(function () {});
  }

  function loadConversation(id) {
    if (busy) return Promise.resolve();
    return apiJson(API + 'luna_conversation.php?id=' + encodeURIComponent(id))
      .then(function (d) {
        if (!d.success) throw new Error('Could not load chat');
        currentConversationId = d.conversation.id;
        rememberActiveConversation(currentConversationId);
        messages = (d.messages || []).map(function (m) {
          return { role: m.role, content: m.content, images: m.images || [] };
        });
        renderMessages(true);
        renderConvList();
        closeConvDrawer();
      });
  }

  function selectConversation(id) {
    if (!id || id === currentConversationId) {
      closeConvDrawer();
      return;
    }
    loadConversation(id).catch(function () {
      setStatusText('Could not load that chat.');
    });
  }

  function deleteConversation(id) {
    if (busy || !id) return;
    apiJson(API + 'luna_conversation.php?id=' + encodeURIComponent(id), { method: 'DELETE' })
      .then(function (d) {
        if (!d.success) throw new Error('Delete failed');
        conversations = conversations.filter(function (c) { return c.id !== id; });
        if (currentConversationId === id) newChat(false);
        renderConvList();
      })
      .catch(function () {
        setStatusText('Could not delete that chat.');
      });
  }

  function openConvDrawer() {
    if (!convDrawer || !convBackdrop) return;
    fetchConversations().then(function () {
      convDrawer.classList.add('open');
      convBackdrop.classList.add('show');
      if (historyBtn) historyBtn.classList.add('active');
      animateConvItems();
    });
  }

  function closeConvDrawer() {
    if (!convDrawer || !convBackdrop) return;
    convDrawer.classList.remove('open');
    convBackdrop.classList.remove('show');
    if (historyBtn) historyBtn.classList.remove('active');
  }

  function refreshStatus() {
    return fetch(API + 'luna_status.php', { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.logged_in) {
          showSignedOut();
          return false;
        }
        showSignedIn();
        setQuota(d.quota);
        return true;
      })
      .catch(function () {
        showSignedOut();
        return false;
      });
  }

  function boot() {
    refreshStatus().then(function (ok) {
      if (!ok) return;
      fetchConversations().then(function () {
        var remembered = null;
        try { remembered = parseInt(sessionStorage.getItem(ACTIVE_CONV_KEY) || '', 10); } catch (e) {}
        if (remembered && conversations.some(function (c) { return c.id === remembered; })) {
          return loadConversation(remembered);
        }
        renderMessages();
      });
    });
    setTimeout(function () { if (input) input.focus(); }, 200);
  }

  function openSignIn() {
    try {
      window.parent.postMessage({ type: 'nexus-open-connected-auth', mode: 'login' }, '*');
    } catch (e) {}
  }

  function newChat(focusInput) {
    if (busy) return;
    if (streamAbort) {
      streamAbort.abort();
      streamAbort = null;
    }
    currentConversationId = null;
    rememberActiveConversation(null);
    messages = [];
    streamingEl = null;
    renderMessages();
    renderConvList();
    closeConvDrawer();
    if (focusInput !== false && input) {
      input.value = '';
      input.style.height = 'auto';
      input.focus();
    }
  }

  function upsertConversationMeta(id, title, updatedAt) {
    var found = false;
    conversations = conversations.map(function (c) {
      if (c.id === id) {
        found = true;
        return Object.assign({}, c, {
          title: title || c.title,
          updated_at: updatedAt || c.updated_at
        });
      }
      return c;
    });
    if (!found) {
      conversations.unshift({
        id: id,
        title: title || 'New chat',
        updated_at: updatedAt || new Date().toISOString(),
        preview: ''
      });
    }
    conversations.sort(function (a, b) {
      return new Date(b.updated_at).getTime() - new Date(a.updated_at).getTime();
    });
    renderConvList();
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

  function sendMessage() {
    if (busy || !input) return;
    var text = input.value.trim();
    if (!text) return;

    refreshStatus().then(function (ok) {
      if (!ok) {
        setStatusText('Sign in to Connected Center to chat with Luna.');
        return;
      }

      messages.push({ role: 'user', content: text });
      input.value = '';
      input.style.height = 'auto';
      renderMessages();
      var userMsgs = list ? list.querySelectorAll('.luna-msg-user') : [];
      if (userMsgs.length) {
        animateMessageElements([userMsgs[userMsgs.length - 1]], 0);
      }

      var assistantMsg = { role: 'assistant', content: '', images: [] };
      messages.push(assistantMsg);
      streamingEl = appendStreamingAssistant();
      setBusy(true);

      var payload = {
        conversation_id: currentConversationId || 0,
        messages: messages.slice(0, -1).map(function (m) {
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
            return response.json().catch(function () {
              return { error: 'Luna could not respond right now.' };
            }).then(function (d) {
              throw new Error(d.error || 'Request failed');
            });
          }
          return consumeSseStream(response, function (evt) {
            if (evt.type === 'conversation') {
              currentConversationId = evt.id;
              rememberActiveConversation(currentConversationId);
              upsertConversationMeta(evt.id, evt.title || 'New chat');
            } else if (evt.type === 'delta') {
              assistantMsg.content += evt.text || '';
              updateStreamingBubble(assistantMsg.content, assistantMsg.images);
            } else if (evt.type === 'status') {
              setStatusText(evt.text || '');
            } else if (evt.type === 'image' && evt.image) {
              assistantMsg.images.push({
                url: evt.image.url || '',
                data_url: evt.image.data_url || '',
                prompt: evt.image.prompt || ''
              });
              updateStreamingBubble(assistantMsg.content, assistantMsg.images);
            } else if (evt.type === 'done') {
              assistantMsg.content = evt.reply || assistantMsg.content || '…';
              assistantMsg.images = (evt.images || []).map(function (img) {
                return {
                  url: img.url || '',
                  data_url: img.data_url || '',
                  prompt: img.prompt || ''
                };
              });
              if (evt.quota) setQuota(evt.quota);
              if (evt.conversation_id) {
                currentConversationId = evt.conversation_id;
                rememberActiveConversation(currentConversationId);
                upsertConversationMeta(evt.conversation_id, evt.title, new Date().toISOString());
              }
            } else if (evt.type === 'error') {
              throw new Error(evt.error || 'Stream error');
            }
          });
        })
        .then(function () {
          setBusy(false);
          streamAbort = null;
          if (streamingEl) {
            streamingEl.classList.remove('streaming');
            streamingEl = null;
          }
          renderMessages();
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          setBusy(false);
          streamAbort = null;
          messages.pop();
          if (messages.length && messages[messages.length - 1].role === 'user') {
            messages.pop();
            if (input) input.value = text;
          }
          streamingEl = null;
          renderMessages();
          if (statusEl) {
            setStatusText((err && err.message) ? err.message : 'Could not reach Luna. Try again in a moment.');
          }
        });
    });
  }

  function init() {
    if (window.frameElement && window.frameElement.id === 'lunaFrame') {
      document.body.classList.add('luna-dock-embed');
    }

    page = document.getElementById('lunaPage');
    list = document.getElementById('lunaAiMessages');
    input = document.getElementById('lunaAiInput');
    sendBtn = document.getElementById('lunaAiSend');
    quotaEl = document.getElementById('lunaAiQuota');
    signinEl = document.getElementById('lunaAiSignin');
    chatEl = document.getElementById('lunaAiChat');
    statusEl = document.getElementById('lunaAiStatus');
    emptyState = document.getElementById('lunaEmptyState');
    aurora = document.getElementById('lunaAurora');
    composer = document.getElementById('lunaComposer');
    convDrawer = document.getElementById('lunaConvDrawer');
    convBackdrop = document.getElementById('lunaConvBackdrop');
    convList = document.getElementById('lunaConvList');
    historyBtn = document.getElementById('lunaHistoryBtn');

    var newChatBtn = document.getElementById('lunaNewChat');
    if (newChatBtn) newChatBtn.addEventListener('click', function () { newChat(true); });
    var newChatAlt = document.getElementById('lunaNewChatAlt');
    if (newChatAlt) newChatAlt.addEventListener('click', function () { newChat(true); });

    if (historyBtn) historyBtn.addEventListener('click', function () {
      if (convDrawer && convDrawer.classList.contains('open')) closeConvDrawer();
      else openConvDrawer();
    });
    if (convBackdrop) convBackdrop.addEventListener('click', closeConvDrawer);
    var convClose = document.getElementById('lunaConvDrawerClose');
    if (convClose) convClose.addEventListener('click', closeConvDrawer);

    if (sendBtn) sendBtn.addEventListener('click', sendMessage);

    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          sendMessage();
        }
      });
      input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });
    }

    var signinBtn = document.getElementById('lunaAiSigninBtn');
    if (signinBtn) signinBtn.addEventListener('click', openSignIn);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && convDrawer && convDrawer.classList.contains('open')) {
        closeConvDrawer();
      }
    });

    window.addEventListener('message', function (e) {
      if (e.data && e.data.type === 'nexus-dock-origin') {
        fireLunaAnimation(e.data.x, e.data.y);
      }
      if (e.data && e.data.type === 'luna-dock-reopen') {
        if (input) input.focus();
      }
      if (e.data && e.data.type === 'cc-auth-success') {
        refreshStatus().then(function (ok) {
          if (ok) fetchConversations().then(function () { renderMessages(); });
        });
      }
    });

    window.addEventListener('storage', function (e) {
      if (e.key === 'nexus_theme' || e.key === 'nexus_dark_mode' || e.key === 'nexus_dark_theme') {
        if (window.NexusApplyTheme) {
          window.NexusApplyTheme(localStorage.getItem('nexus_theme') || 'default');
        }
      }
    });

    updateEmptyState();
    boot();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
