/**
 * dock glows on gpu so the buttons look like they're vibrating with power
 * (they are not. it's a lie. a beautiful lie.)
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_webgl_dock';
  var MAX_GLOWS = 10;
  var host = null;
  var canvas = null;
  var gl = null;
  var program = null;
  var rafId = 0;
  var running = false;
  var uniforms = {};
  var glows = [];
  var glowCurrent = [];
  var dprCap = 2;

  var VERT =
    'attribute vec2 a_pos;' +
    'void main(){ gl_Position = vec4(a_pos, 0.0, 1.0); }';

  var FRAG =
    'precision mediump float;' +
    'uniform vec2 u_res;' +
    'uniform vec3 u_glowC[10];' +
    'uniform vec2 u_glowP[10];' +
    'uniform vec2 u_glowScale[10];' +
    'uniform float u_glowS[10];' +
    'void main() {' +
    '  vec2 pix = vec2(gl_FragCoord.x, u_res.y - gl_FragCoord.y);' +
    '  vec3 col = vec3(0.0);' +
    '  float alpha = 0.0;' +
    '  for (int i = 0; i < 10; i++) {' +
    '    float gs = u_glowS[i];' +
    '    if (gs < 0.01) continue;' +
    '    vec2 delta = (pix - u_glowP[i]) / max(u_glowScale[i], vec2(1.0));' +
    '    float d2 = dot(delta, delta);' +
    '    float g = exp(-d2 * 0.38) * gs;' +
    '    col += u_glowC[i] * g;' +
    '    alpha = max(alpha, g * 0.85);' +
    '  }' +
    '  gl_FragColor = vec4(clamp(col, 0.0, 1.0), clamp(alpha, 0.0, 1.0));' +
    '}';

  function readPref() {
    try { return global.localStorage.getItem(STORAGE_KEY) || 'auto'; }
    catch (e) { return 'auto'; }
  }

  function shouldEnable() {
    var pref = readPref();
    return pref !== 'off';
  }

  function parseRgba(str) {
    if (!str) return null;
    var m = String(str).trim().match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
    if (!m) return null;
    return [
      parseFloat(m[1]) / 255,
      parseFloat(m[2]) / 255,
      parseFloat(m[3]) / 255,
      m[4] != null ? parseFloat(m[4]) : 1
    ];
  }

  function glowColorsFor(btn) {
    var style = global.getComputedStyle(btn);
    var idle = parseRgba(style.getPropertyValue('--glow-color'));
    var active = parseRgba(style.getPropertyValue('--glow-active-color'));
    return {
      idle: idle ? [idle[0], idle[1], idle[2]] : [0.2, 0.6, 1],
      active: active ? [active[0], active[1], active[2]] : [0.2, 0.6, 1]
    };
  }

  function getDpr() {
    var dpr = global.devicePixelRatio || 1;
    if (global.NexusPerf && NexusPerf.isLite && NexusPerf.isLite()) dprCap = 1.5;
    return Math.min(dpr, dprCap);
  }

  function compile(type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.warn('[NexusWebGLDock]', gl.getShaderInfoLog(sh));
      return null;
    }
    return sh;
  }

  function createProgram() {
    var vs = compile(gl.VERTEX_SHADER, VERT);
    var fs = compile(gl.FRAGMENT_SHADER, FRAG);
    if (!vs || !fs) return null;
    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
      console.warn('[NexusWebGLDock]', gl.getProgramInfoLog(prog));
      return null;
    }
    return prog;
  }

  function measure() {
    if (!host) return null;
    var dpr = getDpr();
    var hr = host.getBoundingClientRect();
    var row = host.querySelector('.bottom-nav-row');
    var buttons = row ? row.querySelectorAll('.nav-btn:not(.dock-toggle-btn)') : [];

    glows = [];
    for (var i = 0; i < buttons.length && i < MAX_GLOWS; i++) {
      var btn = buttons[i];
      var br = btn.getBoundingClientRect();
      var colors = glowColorsFor(btn);
      var isActive = btn.classList.contains('active');
      var isHover = btn.matches(':hover');
      var target = isActive || isHover ? 1 : 0;
      var bw = br.width * dpr;
      var bh = br.height * dpr;
      glows.push({
        x: (br.left + br.width * 0.5 - hr.left) * dpr,
        y: (br.bottom - hr.top + 14) * dpr,
        scaleX: Math.max(28, bw * 0.36),
        scaleY: Math.max(22, bh * 0.32),
        color: isActive ? colors.active : colors.idle,
        target: target
      });
    }

    return {
      resW: Math.max(1, Math.floor(hr.width * dpr)),
      resH: Math.max(1, Math.floor(hr.height * dpr)),
      glowCount: glows.length
    };
  }

  function resize() {
    if (!canvas || !host) return null;
    var m = measure();
    if (!m) return null;
    canvas.width = m.resW;
    canvas.height = m.resH;
    gl.viewport(0, 0, m.resW, m.resH);
    return m;
  }

  function draw() {
    if (!gl || !program || !running) return;
    var m = resize();
    if (!m) return;

    for (var i = 0; i < glows.length; i++) {
      if (!glowCurrent[i]) glowCurrent[i] = { s: 0 };
      glowCurrent[i].s += (glows[i].target - glowCurrent[i].s) * 0.14;
    }

    gl.useProgram(program);
    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.uniform2f(uniforms.res, canvas.width, canvas.height);

    for (var g = 0; g < MAX_GLOWS; g++) {
      if (g < glows.length) {
        var cur = glowCurrent[g] ? glowCurrent[g].s : 0;
        gl.uniform3f(uniforms.glowC[g], glows[g].color[0], glows[g].color[1], glows[g].color[2]);
        gl.uniform2f(uniforms.glowP[g], glows[g].x, glows[g].y);
        gl.uniform2f(uniforms.glowScale[g], glows[g].scaleX, glows[g].scaleY);
        gl.uniform1f(uniforms.glowS[g], cur);
      } else {
        gl.uniform1f(uniforms.glowS[g], 0);
      }
    }

    gl.drawArrays(gl.TRIANGLES, 0, 6);
  }

  function tick() {
    if (!running) return;
    draw();
    rafId = global.requestAnimationFrame(tick);
  }

  function pause() {
    running = false;
    if (rafId) { global.cancelAnimationFrame(rafId); rafId = 0; }
  }

  function resume() {
    if (!gl) return;
    if (running) return;
    running = true;
    tick();
  }

  function bindEvents() {
    var row = host.querySelector('.bottom-nav-row');
    if (row) {
      row.addEventListener('mouseover', draw, true);
      row.addEventListener('mouseout', draw, true);
      row.addEventListener('click', draw, true);
    }
    global.addEventListener('resize', function () {
      if (isMobileDock()) { destroy(); return; }
      draw();
    });
    global.addEventListener('orientationchange', draw);
    if (global.visualViewport) global.visualViewport.addEventListener('resize', draw);

    if (global.ResizeObserver) {
      var ro = new global.ResizeObserver(function () { draw(); });
      ro.observe(host);
    }

    global.addEventListener('message', function (e) {
      if (e.data && e.data.type === 'nexus-perf-pause') {
        if (e.data.paused) pause(); else resume();
      }
    });
  }

  function destroy() {
    pause();
    if (canvas && canvas.parentNode) canvas.parentNode.removeChild(canvas);
    canvas = null; gl = null; program = null; host = null;
    global.document.documentElement.classList.remove('nexus-webgl-dock-active');
  }

  function isMobileDock() {
    return global.document.body.classList.contains('mobile');
  }

  function init() {
    if (!shouldEnable() || isMobileDock()) return false;
    host = global.document.getElementById('bottomNav');
    if (!host || canvas) return !!canvas;

    if (global.getComputedStyle(host).position === 'static') {
      host.style.position = 'relative';
    }

    canvas = global.document.createElement('canvas');
    canvas.id = 'nexus-webgl-dock';
    canvas.setAttribute('aria-hidden', 'true');
    host.insertBefore(canvas, host.firstChild);

    gl = canvas.getContext('webgl', {
      alpha: true,
      antialias: true,
      depth: false,
      stencil: false,
      premultipliedAlpha: true,
      preserveDrawingBuffer: false,
      powerPreference: 'high-performance'
    });
    if (!gl) { destroy(); return false; }

    program = createProgram();
    if (!program) { destroy(); return false; }

    gl.enable(gl.BLEND);
    gl.blendFunc(gl.SRC_ALPHA, gl.ONE_MINUS_SRC_ALPHA);

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
      -1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1
    ]), gl.STATIC_DRAW);
    var loc = gl.getAttribLocation(program, 'a_pos');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    uniforms.res = gl.getUniformLocation(program, 'u_res');
    uniforms.glowC = [];
    uniforms.glowP = [];
    uniforms.glowScale = [];
    uniforms.glowS = [];
    for (var i = 0; i < MAX_GLOWS; i++) {
      uniforms.glowC[i] = gl.getUniformLocation(program, 'u_glowC[' + i + ']');
      uniforms.glowP[i] = gl.getUniformLocation(program, 'u_glowP[' + i + ']');
      uniforms.glowScale[i] = gl.getUniformLocation(program, 'u_glowScale[' + i + ']');
      uniforms.glowS[i] = gl.getUniformLocation(program, 'u_glowS[' + i + ']');
    }

    global.document.documentElement.classList.add('nexus-webgl-dock-active');
    bindEvents();
    resume();
    return true;
  }

  function boot() {
    if (global.document.getElementById('bottomNav')) init();
    else global.document.addEventListener('DOMContentLoaded', init, { once: true });
  }

  function setEnabled(on) {
    try { global.localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off'); } catch (e) {}
    if (on) boot(); else destroy();
  }

  global.NexusWebGLDock = {
    boot: boot,
    init: init,
    destroy: destroy,
    pause: pause,
    resume: resume,
    setEnabled: setEnabled,
    isActive: function () { return !!gl && running; }
  };

  boot();
})(window);
