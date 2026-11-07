(function (global) {
  'use strict';

  var MD_TAGS = [
    'p', 'br', 'strong', 'em', 'b', 'i', 'u', 's', 'del', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'sup', 'sub'
  ];

  function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function configureMarked() {
    if (!global.marked || global.marked.__lunaConfigured) return;
    global.marked.setOptions({
      gfm: true,
      breaks: true,
      headerIds: false,
      mangle: false
    });
    global.marked.__lunaConfigured = true;
  }

  function hardenLinks(root) {
    root.querySelectorAll('a[href]').forEach(function (a) {
      a.setAttribute('target', '_blank');
      a.setAttribute('rel', 'noopener noreferrer');
    });
  }

  function renderMarkdown(text, streaming) {
    var source = String(text || '');
    if (!source) return streaming ? '<span class="luna-stream-cursor" aria-hidden="true"></span>' : '';

    if (typeof global.marked === 'undefined' || typeof global.DOMPurify === 'undefined') {
      return esc(source);
    }

    configureMarked();

    var html;
    try {
      html = global.marked.parse(source, { async: false });
    } catch (e) {
      return esc(source);
    }

    var clean = global.DOMPurify.sanitize(html, {
      ALLOWED_TAGS: MD_TAGS,
      ALLOWED_ATTR: ['href', 'title', 'class']
    });

    var root = document.createElement('div');
    root.innerHTML = clean;
    hardenLinks(root);

    if (streaming) {
      root.insertAdjacentHTML('beforeend', '<span class="luna-stream-cursor" aria-hidden="true"></span>');
    }

    return root.innerHTML;
  }

  global.lunaRenderMarkdown = renderMarkdown;
})(typeof window !== 'undefined' ? window : this);
