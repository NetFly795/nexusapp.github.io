/**
 * aurora on the gpu bc css blur orbs were personally attacking me.
 * one canvas. fragment shader go brr. do not look at the math sober.
 */
(function (global) {
  'use strict';

  var STORAGE_KEY = 'nexus_webgl_bg';
  var canvas = null;
  var gl = null;
  var program = null;
  var rafId = 0;
  var running = false;
  var startTime = 0;
  var mergeProgress = 0;
  var uniforms = {};
  var orbColors = [];
  var bgColor = [0.949, 0.961, 0.976];
  var dprCap = 2;
  var themeFrame = 0;

  var VERT_SRC =
    'attribute vec2 a_pos;' +
    'void main(){ gl_Position = vec4(a_pos, 0.0, 1.0); }';

  var FRAG_SRC =
    'precision mediump float;' +
    'uniform vec2 u_resolution;' +
    'uniform float u_time;' +
    'uniform float u_merge;' +
    'uniform vec3 u_bg;' +
    'uniform vec3 u_c0; uniform vec3 u_c1; uniform vec3 u_c2;' +
    'uniform float u_a0; uniform float u_a1; uniform float u_a2;' +
    'float orb(vec2 uv, vec2 center, float radius, float alpha) {' +
    '  float d = distance(uv, center) / max(radius, 0.001);' +
    '  float g = exp(-d * d * 3.2);' +
    '  return g * alpha;' +
    '}' +
    'void main() {' +
    '  vec2 uv = gl_FragCoord.xy / u_resolution;' +
    '  uv.y = 1.0 - uv.y;' +
    '  float t = u_time;' +
    '  float m = u_merge;' +
    '  vec2 p0 = vec2(0.82, 0.12) + vec2(sin(t * 0.419) * 0.035, cos(t * 0.357) * 0.042) * (1.0 + m * 0.15);' +
    '  vec2 p1 = vec2(0.14, 0.86) + vec2(cos(t * 0.349) * 0.04, sin(t * 0.302) * 0.038) * (1.0 + m * 0.12);' +
    '  vec2 p2 = vec2(0.38, 0.52) + vec2(sin(t * 0.314) * 0.045, cos(t * 0.271) * 0.04) * (1.0 + m * 0.1);' +
    '  float r0 = 0.34 * (1.0 + m * 0.06);' +
    '  float r1 = 0.36 * (1.0 + m * 0.06);' +
    '  float r2 = 0.28 * (1.0 + m * 0.05);' +
    '  vec3 col = u_bg;' +
    '  float g0 = orb(uv, p0, r0, u_a0);' +
    '  float g1 = orb(uv, p1, r1, u_a1);' +
    '  float g2 = orb(uv, p2, r2, u_a2);' +
    '  col += u_c0 * g0;' +
    '  col += u_c1 * g1;' +
    '  col += u_c2 * g2;' +
    '  col = clamp(col, 0.0, 1.0);' +
    '  gl_FragColor = vec4(col, 1.0);' +
    '}';

  function readPref() {
    try {
      return global.localStorage.getItem(STORAGE_KEY) || 'auto';
    } catch (e) {
      return 'auto';
    }
  }

  function shouldEnable() {
    var pref = readPref();
    if (pref === 'off') return false;
    if (pref === 'on') return true;
    if (global.NexusVideoBg && NexusVideoBg.usesBackground && NexusVideoBg.usesBackground()) {
      return false;
    }
    return false;
  }

  function parseRgba(str) {
    if (!str) return null;
    var m = String(str).match(/rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)(?:\s*,\s*([\d.]+))?\s*\)/i);
    if (!m) return null;
    return [
      parseFloat(m[1]) / 255,
      parseFloat(m[2]) / 255,
      parseFloat(m[3]) / 255,
      m[4] != null ? parseFloat(m[4]) : 1
    ];
  }

  function parseColor(str) {
    str = (str || '').trim();
    if (!str) return null;
    var rgba = parseRgba(str);
    if (rgba) return rgba;
    var hex = str.match(/^#([0-9a-f]{3,8})$/i);
    if (hex) {
      var h = hex[1];
      if (h.length === 3) {
        h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
      }
      return [
        parseInt(h.slice(0, 2), 16) / 255,
        parseInt(h.slice(2, 4), 16) / 255,
        parseInt(h.slice(4, 6), 16) / 255,
        1
      ];
    }
    return null;
  }

  function parseBallGradient(css) {
    css = css || '';
    var matches = css.match(/rgba?\([^)]+\)/gi) || [];
    var center = parseRgba(matches[0]) || [0.5, 0.55, 1, 0.45];
    return {
      rgb: [center[0], center[1], center[2]],
      alpha: center[3]
    };
  }

  function readTheme() {
    var root = global.getComputedStyle(global.document.documentElement);
    var bg = parseColor(root.getPropertyValue('--theme-bg')) ||
      parseColor(root.getPropertyValue('background-color'));
    if (bg) bgColor = bg.slice(0, 3);

    orbColors = [1, 2, 3].map(function (i) {
      return parseBallGradient(root.getPropertyValue('--theme-ball-' + i));
    });
  }

  function compileShader(type, src) {
    var sh = gl.createShader(type);
    gl.shaderSource(sh, src);
    gl.compileShader(sh);
    if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
      console.warn('[NexusWebGLBg]', gl.getShaderInfoLog(sh));
      gl.deleteShader(sh);
      return null;
    }
    return sh;
  }

  function createProgram() {
    var vs = compileShader(gl.VERTEX_SHADER, VERT_SRC);
    var fs = compileShader(gl.FRAGMENT_SHADER, FRAG_SRC);
    if (!vs || !fs) return null;
    var prog = gl.createProgram();
    gl.attachShader(prog, vs);
    gl.attachShader(prog, fs);
    gl.linkProgram(prog);
    if (!gl.getProgramParameter(prog, gl.LINK_STATUS)) {
      console.warn('[NexusWebGLBg]', gl.getProgramInfoLog(prog));
      return null;
    }
    return prog;
  }

  function getDpr() {
    var dpr = global.devicePixelRatio || 1;
    if (global.NexusPerf && NexusPerf.isLite && NexusPerf.isLite()) {
      dprCap = 1.5;
    }
    return Math.min(dpr, dprCap);
  }

  function resize() {
    if (!canvas || !gl) return;
    var dpr = getDpr();
    var w = Math.max(1, Math.floor(canvas.clientWidth * dpr));
    var h = Math.max(1, Math.floor(canvas.clientHeight * dpr));
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
      gl.viewport(0, 0, w, h);
    }
  }

  function draw() {
    if (!gl || !program || !running) return;
    resize();
    if ((themeFrame++ % 45) === 0) readTheme();

    gl.useProgram(program);
    gl.clearColor(bgColor[0], bgColor[1], bgColor[2], 1.0);
    gl.clear(gl.COLOR_BUFFER_BIT);
    gl.uniform2f(uniforms.resolution, canvas.width, canvas.height);
    gl.uniform1f(uniforms.time, (performance.now() - startTime) * 0.001);
    gl.uniform1f(uniforms.merge, mergeProgress);
    gl.uniform3f(uniforms.bg, bgColor[0], bgColor[1], bgColor[2]);

    for (var i = 0; i < 3; i++) {
      var o = orbColors[i] || { rgb: [0.5, 0.55, 1], alpha: 0.4 };
      gl.uniform3f(uniforms['c' + i], o.rgb[0], o.rgb[1], o.rgb[2]);
      gl.uniform1f(uniforms['a' + i], o.alpha);
    }

    gl.drawArrays(gl.TRIANGLES, 0, 6);
  }

  function tick() {
    if (!running) return;
    draw();
    rafId = global.requestAnimationFrame(tick);
  }

  function pollMergeProgress() {
    if (!running) return;
    try {
      var sticky = global.document.querySelector('.sticky-header');
      if (sticky) {
        var raw = global.getComputedStyle(sticky).getPropertyValue('--merge-progress');
        var p = parseFloat(raw);
        if (!isNaN(p)) mergeProgress = p;
      }
    } catch (e) {}
  }

  function startMergePoll() {
    if (!global.document.querySelector('.sticky-header')) return;
    global.setInterval(pollMergeProgress, 100);
  }

  function pause() {
    running = false;
    if (rafId) {
      global.cancelAnimationFrame(rafId);
      rafId = 0;
    }
    global.document.documentElement.classList.add('nexus-webgl-paused');
  }

  function resume() {
    if (!gl || !program) return;
    if (running) return;
    running = true;
    global.document.documentElement.classList.remove('nexus-webgl-paused');
    tick();
  }

  function destroy() {
    pause();
    if (canvas && canvas.parentNode) {
      canvas.parentNode.removeChild(canvas);
    }
    canvas = null;
    gl = null;
    program = null;
    global.document.documentElement.classList.remove('nexus-webgl-active', 'nexus-webgl-paused');
  }

  function watchPerformanceMode() {
    if (!global.document.body) return;
    var obs = new global.MutationObserver(function () {
      if (global.document.body.classList.contains('performance-mode')) {
        pause();
      } else if (!global.document.hidden &&
          !global.document.documentElement.classList.contains('nexus-perf-paused')) {
        resume();
      }
    });
    obs.observe(global.document.body, { attributes: true, attributeFilter: ['class'] });
  }

  function init() {
    if (!shouldEnable()) return false;
    if (canvas) return true;

    canvas = global.document.createElement('canvas');
    canvas.id = 'nexus-webgl-bg';
    canvas.setAttribute('aria-hidden', 'true');
    global.document.body.insertBefore(canvas, global.document.body.firstChild);

    gl = canvas.getContext('webgl', {
      alpha: false,
      antialias: false,
      depth: false,
      stencil: false,
      preserveDrawingBuffer: false,
      powerPreference: 'high-performance'
    });
    if (!gl) {
      destroy();
      return false;
    }

    program = createProgram();
    if (!program) {
      destroy();
      return false;
    }

    var buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(
      gl.ARRAY_BUFFER,
      new Float32Array([-1, -1, 1, -1, -1, 1, -1, 1, 1, -1, 1, 1]),
      gl.STATIC_DRAW
    );
    var loc = gl.getAttribLocation(program, 'a_pos');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    uniforms.resolution = gl.getUniformLocation(program, 'u_resolution');
    uniforms.time = gl.getUniformLocation(program, 'u_time');
    uniforms.merge = gl.getUniformLocation(program, 'u_merge');
    uniforms.bg = gl.getUniformLocation(program, 'u_bg');
    uniforms.c0 = gl.getUniformLocation(program, 'u_c0');
    uniforms.c1 = gl.getUniformLocation(program, 'u_c1');
    uniforms.c2 = gl.getUniformLocation(program, 'u_c2');
    uniforms.a0 = gl.getUniformLocation(program, 'u_a0');
    uniforms.a1 = gl.getUniformLocation(program, 'u_a1');
    uniforms.a2 = gl.getUniformLocation(program, 'u_a2');

    startTime = performance.now();
    global.document.documentElement.classList.add('nexus-webgl-active');
    readTheme();
    resize();
    startMergePoll();
    watchPerformanceMode();

    global.addEventListener('resize', resize);
    global.addEventListener('orientationchange', resize);
    if (global.visualViewport) {
      global.visualViewport.addEventListener('resize', resize);
    }

    global.document.addEventListener('visibilitychange', function () {
      if (global.document.hidden) pause();
      else if (!global.document.documentElement.classList.contains('nexus-perf-paused')) resume();
    });

    global.addEventListener('message', function (e) {
      if (!e.data) return;
      if (e.data.type === 'nexus-perf-pause') {
        if (e.data.paused) pause();
        else if (!global.document.hidden) resume();
      }
    });

    if (global.document.documentElement.classList.contains('nexus-perf-paused') ||
        global.document.hidden) {
      pause();
    } else {
      resume();
    }

    return true;
  }

  function boot() {
    var run = function () {
      if (global.NexusVideoBg && NexusVideoBg.isActive && NexusVideoBg.isActive()) return;
      init();
    };
    if (global.document.body) run();
    else global.document.addEventListener('DOMContentLoaded', run, { once: true });
  }

  function setEnabled(on) {
    try {
      global.localStorage.setItem(STORAGE_KEY, on ? 'on' : 'off');
    } catch (e) {}
    if (on) boot();
    else destroy();
  }

  global.NexusWebGLBg = {
    boot: boot,
    init: init,
    destroy: destroy,
    pause: pause,
    resume: resume,
    setMergeProgress: function (p) { mergeProgress = p; },
    setEnabled: setEnabled,
    isActive: function () { return !!gl && running; }
  };

  boot();
})(window);
