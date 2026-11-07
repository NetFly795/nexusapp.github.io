(function () {
  // The stage is 800 CSS pixels. A 2x backing store only scales that image up.
  try {
    Object.defineProperty(window, "devicePixelRatio", { configurable: true, get: function () { return 1; } });
  } catch (e) {}

  // Run our present after every animation callback, so a second copy of the
  // scene cannot land on top of the frame and turn it upside down.
  if (!window.__sodaRAF) {
    window.__sodaRAF = true;
    var origRAF = window.requestAnimationFrame.bind(window);
    var origCAF = window.cancelAnimationFrame.bind(window);
    var rafQueue = [];
    var rafLive = new Set();
    var rafGen = 1;
    var rafScheduled = false;
    window.requestAnimationFrame = function (cb) {
      var id = rafGen++;
      rafQueue.push({ id: id, cb: cb });
      rafLive.add(id);
      if (!rafScheduled) {
        rafScheduled = true;
        origRAF(function (t) {
          rafScheduled = false;
          var batch = rafQueue.splice(0);
          for (var i = 0; i < batch.length; i++) {
            if (!rafLive.has(batch[i].id)) continue;
            rafLive.delete(batch[i].id);
            try { batch[i].cb(t); } catch (err) { setTimeout(function () { throw err; }, 0); }
          }
          if (typeof window.__sodaBlit === "function") window.__sodaBlit();
        });
      }
      return id;
    };
    window.cancelAnimationFrame = function (id) {
      rafLive.delete(id);
      return origCAF(id);
    };
  }

  // Ruffle draws Stage3D into an 800x600 framebuffer and never presents it, and
  // its WebGL backend rebuilds the same handful of programs every frame. Asking
  // the driver if they compiled is what drops the game to a few frames a second.
  var proto = HTMLCanvasElement.prototype;
  var origGet = proto.getContext;
  proto.getContext = function (type, attrs) {
    if (/webgl/i.test(String(type))) {
      attrs = Object.assign({}, attrs || {}, { antialias: false });
    }
    var gl = origGet.call(this, type, attrs);
    if (gl && /webgl/i.test(String(type)) && !gl.__sodaPresent) {
      gl.__sodaPresent = true;
      var stageFb = null;
      var stageW = 800;
      var stageH = 600;
      var drawFb = null;
      var readFb = null;
      var vw = 0;
      var vh = 0;
      var depthOff = false;
      var compiled = new Map();
      var patchedSource = new Map();
      var sourceOf = new WeakMap();
      var originalOf = new WeakMap();
      var alias = new WeakMap();
      var linked = new Map();
      var canonOf = new WeakMap();
      var progShaders = new WeakMap();
      var lastProgram = null;
      var keptShaders = new Set();
      var keptPrograms = new Set();

      function rewriteShader(source) {
        if (patchedSource.has(source)) return patchedSource.get(source);
        var out = source;
        var head = null;
        // The light and tint uniforms are filled in per program. When a program's
        // block is still empty, fall back to the texture so the board cannot go black.
        // Shaders that already multiply by the vertex color are left alone, so fades stay fades.
        if (source.indexOf("_group_0_binding_11_fs") !== -1 && source.indexOf("_group_0_binding_1_fs") !== -1 && source.indexOf("_vs2fs_location4") !== -1) {
          head = "void main() { vec4 _sodaU1 = _group_0_binding_1_fs[1u]; vec4 _sodaU2 = _group_0_binding_1_fs[2u]; vec4 _sodaU5 = _group_0_binding_1_fs[5u]; float _sodaAlive = ((dot(_sodaU1.xyz, _sodaU1.xyz) + dot(_sodaU2.xyz, _sodaU2.xyz)) + abs(_sodaU1.w)) + dot(_sodaU5, _sodaU5); if (_sodaAlive < 0.001) { _fs2p_location0 = texture(_group_0_binding_10_fs, vec2(_vs2fs_location4.xy)); return; } ";
        } else if (source.indexOf("_group_0_binding_11_fs") === -1 && source.indexOf(".wwww") !== -1 && source.indexOf("_group_0_binding_10_fs") !== -1 && source.indexOf("_group_0_binding_1_fs") !== -1 && source.indexOf("_vs2fs_location1") !== -1) {
          head = "void main() { vec4 _sodaU0 = _group_0_binding_1_fs[0u]; if (dot(_sodaU0, _sodaU0) < 0.001) { vec4 _sodaTex = texture(_group_0_binding_10_fs, vec2(_vs2fs_location1.xy)); vec4 _sodaColor = _vs2fs_location0; if (((_sodaColor.r + _sodaColor.g) + _sodaColor.b) < 0.04) { _sodaColor = vec4(1.0); } float _sodaA = _sodaTex.a; _fs2p_location0 = vec4(_sodaColor.rgb * _sodaA, _sodaA); return; } ";
        } else if (source.indexOf("_group_0_binding_11_fs") === -1 && source.indexOf(".xxxx.x") !== -1 && source.indexOf("_group_0_binding_10_fs") !== -1 && source.indexOf("_group_0_binding_1_fs") !== -1 && source.indexOf("_vs2fs_location1") !== -1) {
          head = "void main() { vec4 _sodaU0 = _group_0_binding_1_fs[0u]; if (dot(_sodaU0, _sodaU0) < 0.001) { vec4 _sodaTex = texture(_group_0_binding_10_fs, vec2(_vs2fs_location1.xy)); vec4 _sodaColor = _vs2fs_location0; if (((_sodaColor.r + _sodaColor.g) + _sodaColor.b) < 0.04) { _sodaColor = vec4(1.0); } float _sodaA = _sodaTex.r; _fs2p_location0 = vec4(_sodaColor.rgb * _sodaA, _sodaA); return; } ";
        } else if (source.indexOf("_group_0_binding_1_fs") !== -1 && source.indexOf("_group_0_binding_11_fs") === -1 && source.indexOf("_group_0_binding_10_fs") !== -1 && source.indexOf("_vs2fs_location1") !== -1 && source.indexOf(".wwww") === -1 && source.indexOf(".xxxx.x") === -1) {
          head = "void main() { vec4 _sodaU0 = _group_0_binding_1_fs[0u]; if (dot(_sodaU0, _sodaU0) < 0.001) { _fs2p_location0 = texture(_group_0_binding_10_fs, vec2(_vs2fs_location1.xy)); return; } ";
        }
        if (head) out = out.replace(/void main\(\)\s*\{/, head);
        patchedSource.set(source, out);
        return out;
      }

      var drawCounts = new Map();
      var origBind = gl.bindFramebuffer.bind(gl);
      gl.bindFramebuffer = function (target, fb) {
        if (target === gl.FRAMEBUFFER) {
          drawFb = fb;
          readFb = fb;
        } else if (target === gl.DRAW_FRAMEBUFFER) {
          drawFb = fb;
        } else if (target === gl.READ_FRAMEBUFFER) {
          readFb = fb;
        }
        return origBind(target, fb);
      };
      var origViewport = gl.viewport.bind(gl);
      var origDisable = gl.disable.bind(gl);
      var origEnable = gl.enable.bind(gl);
      gl.viewport = function (x, y, w, h) {
        vw = w;
        vh = h;
        if (w === 800 && h === 600) {
          if (!depthOff) {
            origDisable(gl.DEPTH_TEST);
            depthOff = true;
          }
        } else {
          depthOff = false;
        }
        return origViewport(x, y, w, h);
      };
      // Depth from this backend sits on the far plane, so the test rejects every
      // fragment. Stencil stays enabled so candies stay clipped to the board.
      gl.enable = function (cap) {
        if (cap === gl.DEPTH_TEST && vw === 800 && vh === 600) return;
        return origEnable(cap);
      };

      var origSource = gl.shaderSource.bind(gl);
      var origCompile = gl.compileShader.bind(gl);
      var origAttach = gl.attachShader.bind(gl);
      var origLink = gl.linkProgram.bind(gl);
      var origUse = gl.useProgram.bind(gl);
      var origShaderParam = gl.getShaderParameter.bind(gl);
      var origProgramParam = gl.getProgramParameter.bind(gl);
      var origDeleteShader = gl.deleteShader.bind(gl);
      var origDeleteProgram = gl.deleteProgram.bind(gl);
      var origUniform = gl.getUniformLocation.bind(gl);
      var origAttrib = gl.getAttribLocation.bind(gl);
      var origBlock = gl.getUniformBlockIndex.bind(gl);
      var origBlockBind = gl.uniformBlockBinding ? gl.uniformBlockBinding.bind(gl) : null;
      var blockByProgram = new WeakMap();
      var stageTex = new WeakSet();
      var fbColor = new WeakMap();
      var fbLit = new WeakMap();
      var litPrograms = new WeakSet();
      var activeUnit = gl.TEXTURE0;
      var unitTex = new Map();

      function realShader(shader) {
        return alias.get(shader) || shader;
      }
      function realProgram(program) {
        return canonOf.get(program) || program;
      }

      gl.shaderSource = function (shader, source) {
        if (typeof source === "string") {
          originalOf.set(shader, source);
          source = rewriteShader(source);
        }
        sourceOf.set(shader, source);
        return origSource(shader, source);
      };
      gl.compileShader = function (shader) {
        var source = sourceOf.get(shader);
        var hit = source && compiled.get(source);
        if (hit) {
          alias.set(shader, hit);
          return;
        }
        origCompile(shader);
        if (!(source && origShaderParam(shader, gl.COMPILE_STATUS))) {
          var original = originalOf.get(shader);
          if (original && original !== source) {
            origSource(shader, original);
            origCompile(shader);
            sourceOf.set(shader, original);
            source = original;
          }
        }
        if (source && origShaderParam(shader, gl.COMPILE_STATUS)) {
          compiled.set(source, shader);
          keptShaders.add(shader);
        }
      };
      gl.attachShader = function (program, shader) {
        var real = realShader(shader);
        var list = progShaders.get(program);
        if (!list) progShaders.set(program, list = []);
        list.push(sourceOf.get(real) || sourceOf.get(shader) || "");
        return origAttach(program, real);
      };
      gl.linkProgram = function (program) {
        var key = (progShaders.get(program) || []).join("\n\0\n");
        var hit = key && linked.get(key);
        if (hit) {
          canonOf.set(program, hit);
          return;
        }
        origLink(program);
        if (key && origProgramParam(program, gl.LINK_STATUS)) {
          linked.set(key, program);
          canonOf.set(program, program);
          keptPrograms.add(program);
        }
      };
      function programIsLit(program) {
        var list = progShaders.get(program) || [];
        for (var i = 0; i < list.length; i++) {
          if (list[i].indexOf("_group_0_binding_11_fs") !== -1) return true;
        }
        return false;
      }
      gl.useProgram = function (program) {
        var canon = program ? realProgram(program) : null;
        if (canon && programIsLit(program)) litPrograms.add(canon);
        if (canon === lastProgram) return;
        lastProgram = canon;
        return origUse(canon);
      };
      gl.getShaderParameter = function (shader, pname) {
        if (pname === gl.COMPILE_STATUS) return true;
        if (pname === gl.DELETE_STATUS) return false;
        if (pname === gl.SHADER_TYPE) {
          var real = realShader(shader);
          if (real !== shader) return origShaderParam(real, pname);
        }
        return origShaderParam(shader, pname);
      };
      gl.getShaderInfoLog = function () { return ""; };
      gl.getProgramParameter = function (program, pname) {
        if (pname === gl.LINK_STATUS) return true;
        if (pname === gl.DELETE_STATUS) return false;
        return origProgramParam(realProgram(program), pname);
      };
      gl.getProgramInfoLog = function () { return ""; };
      gl.getUniformLocation = function (program, name) {
        return origUniform(realProgram(program), name);
      };
      gl.getAttribLocation = function (program, name) {
        return origAttrib(realProgram(program), name);
      };
      gl.getUniformBlockIndex = function (program, name) {
        var canon = realProgram(program);
        var map = blockByProgram.get(canon);
        if (!map) blockByProgram.set(canon, map = new Map());
        if (map.has(name)) return map.get(name);
        var index = origBlock(canon, name);
        // A missing block is INVALID_INDEX. wgpu unwraps that and takes the tab down.
        if (index == null || index === 0xffffffff) index = 0;
        map.set(name, index);
        return index;
      };
      if (origBlockBind) {
        gl.uniformBlockBinding = function (program, index, binding) {
          return origBlockBind(realProgram(program), index, binding);
        };
      }
      gl.deleteShader = function (shader) {
        if (keptShaders.has(shader)) return;
        return origDeleteShader(shader);
      };
      gl.deleteProgram = function (program) {
        if (keptPrograms.has(program)) return;
        return origDeleteProgram(program);
      };
      // wgpu flushes after nearly every draw. Each flush waits for the GPU, which
      // holds the game near 18fps on this machine. The browser flushes once when
      // the frame is done, and that is enough for the blit below.
      gl.flush = function () {};

      var origActive = gl.activeTexture.bind(gl);
      gl.activeTexture = function (unit) {
        activeUnit = unit;
        return origActive(unit);
      };
      var origBindTex = gl.bindTexture.bind(gl);
      gl.bindTexture = function (target, tex) {
        if (target === gl.TEXTURE_2D) unitTex.set(activeUnit, tex);
        return origBindTex(target, tex);
      };
      var origFBTex = gl.framebufferTexture2D.bind(gl);
      gl.framebufferTexture2D = function (target, attachment, textarget, texture, level) {
        if (attachment === gl.COLOR_ATTACHMENT0 && drawFb) fbColor.set(drawFb, texture || null);
        return origFBTex(target, attachment, textarget, texture, level);
      };
      function samplesStage() {
        var hit = false;
        unitTex.forEach(function (tex) {
          if (tex && stageTex.has(tex)) hit = true;
        });
        return hit;
      }
      gl.__sodaSkip = 0;
      ["drawArrays", "drawElements", "drawArraysInstanced", "drawElementsInstanced"].forEach(function (name) {
        var orig = gl[name] && gl[name].bind(gl);
        if (!orig) return;
        gl[name] = function () {
          // The scene is already copied onto the canvas below. A later fullscreen
          // draw samples that same image and turns it upside down.
          if (!drawFb && samplesStage()) {
            gl.__sodaSkip++;
            return;
          }
          if (vw === 800 && vh === 600 && drawFb) {
            var attached = fbColor.get(drawFb);
            if (attached) stageTex.add(attached);
            if (lastProgram && litPrograms.has(lastProgram)) fbLit.set(drawFb, true);
            drawCounts.set(drawFb, (drawCounts.get(drawFb) || 0) + 1);
            stageW = 800;
            stageH = 600;
          }
          return orig.apply(gl, arguments);
        };
      });

      // Device text is drawn on a canvas, top row first. GL stores textures bottom
      // row first, which turns those letters upside down. Image uploads are already
      // oriented, so flipping those turns the candies over.
      var unpackFlip = false;
      var origPixelStore = gl.pixelStorei.bind(gl);
      gl.pixelStorei = function (pname, param) {
        if (pname === gl.UNPACK_FLIP_Y_WEBGL) unpackFlip = !!param;
        return origPixelStore(pname, param);
      };
      function canvasSource(src) {
        if (!src || typeof src !== "object" || !src.constructor) return false;
        var n = src.constructor.name;
        return n === "HTMLCanvasElement" || n === "OffscreenCanvas";
      }
      function uploadCanvas(orig, args) {
        var src = args[args.length - 1];
        if (!canvasSource(src) || unpackFlip) return orig.apply(gl, args);
        origPixelStore(gl.UNPACK_FLIP_Y_WEBGL, true);
        try {
          return orig.apply(gl, args);
        } finally {
          origPixelStore(gl.UNPACK_FLIP_Y_WEBGL, false);
        }
      }
      var origTexImage = gl.texImage2D.bind(gl);
      gl.texImage2D = function () { return uploadCanvas(origTexImage, arguments); };
      var origTexSub = gl.texSubImage2D.bind(gl);
      gl.texSubImage2D = function () { return uploadCanvas(origTexSub, arguments); };

      var presenting = false;
      window.__sodaBlit = function () {
        var best = null;
        var bestN = 0;
        drawCounts.forEach(function (n, fb) {
          if (n > bestN) {
            best = fb;
            bestN = n;
          }
        });
        var lit = best && fbLit.get(best);
        drawCounts = new Map();
        fbLit = new WeakMap();
        // Flat screens are already oriented by Ruffle. Copying their offscreen
        // buffer puts that screen upside down. The 3D board is the buffer that
        // has to be copied, and it is right-side up.
        if (!lit || presenting) return;
        stageFb = best;
        presenting = true;
        var prevRead = readFb;
        var prevDraw = drawFb;
        origBind(gl.READ_FRAMEBUFFER, stageFb);
        origBind(gl.DRAW_FRAMEBUFFER, null);
        // The board buffer is stored bottom-up. Copying it straight leaves the
        // whole level upside down, which is the frame that was flickering in.
        gl.blitFramebuffer(0, 0, stageW, stageH, 0, gl.canvas.height, gl.canvas.width, 0, gl.COLOR_BUFFER_BIT, gl.NEAREST);
        origBind(gl.READ_FRAMEBUFFER, prevRead);
        origBind(gl.DRAW_FRAMEBUFFER, prevDraw);
        presenting = false;
      };
    }
    return gl;
  };
  // Disable deprecated HTML5 FileSystem cache path used by kingdom.js.
  try { delete window.requestFileSystem; } catch (e) {}
  try { delete window.webkitRequestFileSystem; } catch (e) {}
  window.requestFileSystem = undefined;
  window.webkitRequestFileSystem = undefined;

  // Report plenty of quota so kingdom.js / the SWF will fetch resource packs.
  var QUOTA = 64 * 1024 * 1024;
  var storageStub = {
    TEMPORARY: 0,
    PERSISTENT: 1,
    queryUsageAndQuota: function (type, ok) { if (typeof ok === "function") ok(0, QUOTA); },
    requestQuota: function (type, bytes, ok) { if (typeof ok === "function") ok(bytes || QUOTA); }
  };
  window.webkitStorageInfo = window.webkitStorageInfo || storageStub;
  window.storageInfo = window.storageInfo || window.webkitStorageInfo;

  if (!navigator.webkitTemporaryStorage) {
    navigator.webkitTemporaryStorage = {
      queryUsageAndQuota: function (ok) { if (typeof ok === "function") ok(0, QUOTA); },
      requestQuota: function (bytes, ok) { if (typeof ok === "function") ok(bytes || QUOTA); }
    };
  }
  if (!navigator.webkitPersistentStorage) {
    navigator.webkitPersistentStorage = {
      queryUsageAndQuota: function (ok) { if (typeof ok === "function") ok(0, QUOTA); },
      requestQuota: function (bytes, ok) { if (typeof ok === "function") ok(bytes || QUOTA); }
    };
  }
})();
window.RufflePlayer = window.RufflePlayer || {};
window.RufflePlayer.config = Object.assign({}, window.RufflePlayer.config || {}, {
    autoplay: "on",
    unmuteOverlay: "hidden",
    letterbox: "on",
    allowScriptAccess: true,
    autoLoad: true,
    polyfills: true,
    preferredRenderer: "wgpu-webgl",
    logLevel: "warn",
    deviceFontRenderer: "canvas",
    // Client.swf startup exceeds the default 15s budget. webgpu wedges the tab.
    maxExecutionDuration: 120,
    wmode: "direct",
    publicPath: (window.__SODA_BASE__ ? window.__SODA_BASE__ + "/__ruffle__/" : "./__ruffle__/")
});
