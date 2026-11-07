/* Force CCSS onto Ruffle. swfobject's Flash detection never creates an embed
   in modern browsers, so polyfills never run. Replace showGame() instead. */
(function () {
  function log() {
    if (window.console && console.log) {
      console.log.apply(console, ["[soda-ruffle]"].concat([].slice.call(arguments)));
    }
  }

  function hideFlashFallback() {
    var failed = document.getElementById("gameLoadFailed");
    if (failed && failed.parentNode) {
      failed.parentNode.removeChild(failed);
    }
  }

  function buildQuery(flashvars) {
    if (!flashvars) return "";
    var parts = [];
    // Put resourcesMap last — even with encoding, keep critical keys first.
    var keys = Object.keys(flashvars).filter(function (k) {
      return k !== "resourcesMap";
    });
    if (Object.prototype.hasOwnProperty.call(flashvars, "resourcesMap")) {
      keys.push("resourcesMap");
    }
    keys.forEach(function (key) {
      var val = flashvars[key];
      if (val == null) return;
      // Strip cache-buster query junk from map URLs so '=' never appears unencoded
      // in intermediate tooling, and always percent-encode the flashvar value.
      if (key === "resourcesMap") {
        val = String(val).replace(/\?_v=[^,]*/g, "");
      }
      parts.push(encodeURIComponent(key) + "=" + encodeURIComponent(String(val)));
    });
    return parts.join("&");
  }

  function sodaBase() {
    if (window.__SODA_BASE__) return String(window.__SODA_BASE__).replace(/\/$/, "");
    try {
      var b = document.querySelector("base");
      if (b && b.href) return String(new URL(".", b.href).href).replace(/\/$/, "");
    } catch (e) {}
    return String(new URL(".", window.location.href).href).replace(/\/$/, "");
  }
  function rewriteToOrigin(value) {
    if (typeof value !== "string" || !value) return value;
    var base = sodaBase();
    return value
      .replace(/https?:\/\/candycrushsoda\.dev\.midasplayer\.com/gi, base)
      .replace(/https?:\/\/soda1\.midasplayer\.com/gi, base)
      .replace(/__SODA_BASE__/g, base);
  }

  function normalizeFlashvars(flashvars) {
    var out = Object.assign({}, flashvars || {});
    Object.keys(out).forEach(function (key) {
      out[key] = rewriteToOrigin(out[key]);
    });
    var base = sodaBase();
    out.CDN = rewriteToOrigin(out.CDN) || base;
    out.serverHost = window.location.host;
    out.remoteRpcServiceUrl =
      rewriteToOrigin(out.remoteRpcServiceUrl) ||
      base + "/rpc/ClientApi.php";
    out.resourceBase = out.resourceBase || (base.replace(window.location.origin, "") + "/resources/");
    if (out.resourceBase.charAt(0) !== "/") {
      try {
        out.resourceBase = new URL(out.resourceBase, window.location.href).pathname;
        if (out.resourceBase.slice(-1) !== "/") out.resourceBase += "/";
      } catch (e) {}
    }
    if (out.clientSwf) out.clientSwf = rewriteToOrigin(out.clientSwf);
    if (window.__SODA_FLASHVARS__) {
      Object.keys(window.__SODA_FLASHVARS__).forEach(function (k) {
        if (out[k] == null || out[k] === "") out[k] = window.__SODA_FLASHVARS__[k];
      });
    }
    if (out.resourcesMap) {
      out.resourcesMap = rewriteToOrigin(out.resourcesMap).replace(/\?_v=[^,]*/g, "");
    } else if (window.__SODA_RESOURCES_MAP__) {
      out.resourcesMap = rewriteToOrigin(String(window.__SODA_RESOURCES_MAP__));
    }
    if (!out.abTestCases) {
      out.abTestCases = "high_popular_goldbar_pkg;1,illegal_switch_enabled;1,hard_level_pin_enabled;1,illegal_switch_prices;0,booster_bundling_mode;2,new_goldbar_shop;1,striped_lollipop_enabled;1,goldbar_shop_special_offer;0,levels;0,hard_levels_labeling;3";
    }
    if (out.clientVersionUrl) out.clientVersionUrl = rewriteToOrigin(out.clientVersionUrl);
    // Prefer GPU for a usable preloader; ?software=1 selects the CPU Stage3D path.
    if (/(?:^|[?&])software=1(?:&|$)/.test(window.location.search)) {
      out.forceSoftware = "1";
    } else {
      out.forceSoftware = "";
    }
    return out;
  }

  /** Call an AS3 ExternalInterface.addCallback handler through Ruffle. */
  function callFlashCallback(player, name, args) {
    args = args || [];
    // Prefer the property Ruffle installs when addCallback succeeds.
    try {
      var direct = player[name];
      if (typeof direct === "function" && !direct.__sodaBridge) {
        return direct.apply(player, args);
      }
    } catch (e) {
      log("direct callback failed", name, e);
    }
    // Public ruffle() wrapper → callExternalInterface / ExternalInterface.
    try {
      var api = player.ruffle && player.ruffle();
      if (api) {
        if (typeof api.ExternalInterface === "function") {
          return api.ExternalInterface.apply(api, [name].concat(args));
        }
        if (typeof api.callExternalInterface === "function") {
          return api.callExternalInterface(name, args);
        }
      }
    } catch (e) {
      log("ruffle ExternalInterface failed", name, e);
    }
    throw new Error("No Ruffle path to Flash callback " + name);
  }

  /**
   * kingdom.js replies with flashObject.externalInterfaceRpcReceive(response).
   * Ruffle only defines that property after AS addCallback — and replies before
   * then (or if defineProperty fails) are dropped, so resource loads never start.
   */
  function wirePlayer(player) {
    window.game = player;
    try {
      document.game = player;
    } catch (e) {}

    function rpcReceive(response) {
      log("RPC → Flash", response && response.id, response && (response.result !== undefined ? "ok" : "err"));
      return callFlashCallback(player, "externalInterfaceRpcReceive", [response]);
    }
    rpcReceive.__sodaBridge = true;

    try {
      Object.defineProperty(player, "externalInterfaceRpcReceive", {
        configurable: true,
        enumerable: true,
        writable: true,
        value: rpcReceive,
      });
    } catch (e) {
      player.externalInterfaceRpcReceive = rpcReceive;
    }

    if (typeof player.getRenderMode !== "function") {
      player.getRenderMode = function () {
        return "Software";
      };
    }
    if (typeof player.PercentLoaded !== "function") {
      player.PercentLoaded = function () {
        return 100;
      };
    }

    // Keep ExternalInterfaceRpc pointed at this player even if kingdom overwrote it.
    function bindRpc() {
      if (!window.ExternalInterfaceRpc || typeof ExternalInterfaceRpc.init !== "function") {
        return;
      }
      try {
        ExternalInterfaceRpc.init(player);
      } catch (e) {
        log("ExternalInterfaceRpc.init failed", e);
      }
    }
    bindRpc();

    // Log JS API traffic from Flash (proves EI Flash→JS is alive).
    wrapResourceApi();
    wrapFetchFile();
  }

  function wrapResourceApi() {
    var api = window.ResourceLoaderJsApi;
    if (!api || api.__sodaWrapped) return;
    ["onInit", "loadBase64", "supported", "validate", "init"].forEach(function (method) {
      if (typeof api[method] !== "function") return;
      var orig = api[method];
      api[method] = function () {
        var args = Array.prototype.slice.call(arguments);
        log("ResourceLoaderJsApi." + method, args[0] && String(args[0]).slice(0, 120));
        try {
          return orig.apply(this, args);
        } catch (e) {
          log("ResourceLoaderJsApi." + method + " threw", e);
          var errCb = args[args.length - 1];
          if (typeof errCb === "function") {
            try {
              errCb(String(e));
            } catch (e2) {}
          }
          throw e;
        }
      };
    });
    api.__sodaWrapped = true;
  }

  function wrapFetchFile() {
    if (!window.king || !king.util || typeof king.util.fetchFile !== "function" || king.util.fetchFile.__sodaWrapped) {
      return;
    }
    var orig = king.util.fetchFile;
    king.util.fetchFile = function (url, callback) {
      log("fetchFile", url);
      var xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.responseType = "arraybuffer";
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) {
          log("fetchFile ok", url, xhr.response && xhr.response.byteLength);
          callback(xhr.response);
        } else {
          log("fetchFile HTTP", xhr.status, url);
          callback(null);
        }
      };
      xhr.onerror = function () {
        log("fetchFile network error", url);
        callback(null);
      };
      xhr.send();
    };
    king.util.fetchFile.__sodaWrapped = true;
  }

  /** Log every Flash → JS ExternalInterface target the page exposes. */
  function interceptFlashToJs() {
    if (window.__sodaEiHooked) return;
    window.__sodaEiHooked = true;
    window.__sodaEiLog = window.__sodaEiLog || [];

    function wrapFn(label, fn) {
      if (typeof fn !== "function" || fn.__sodaEi) return fn;
      var wrapped = function () {
        var args = Array.prototype.slice.call(arguments);
        var preview = args
          .map(function (a) {
            try {
              return typeof a === "string" ? a.slice(0, 160) : JSON.stringify(a).slice(0, 160);
            } catch (e) {
              return String(a).slice(0, 80);
            }
          })
          .join(" | ");
        window.__sodaEiLog.push({ t: Date.now(), label: label, preview: preview });
        log("EI←Flash", label, preview.slice(0, 200));
        return fn.apply(this, args);
      };
      wrapped.__sodaEi = true;
      return wrapped;
    }

    if (window.ExternalInterfaceRpc && typeof ExternalInterfaceRpc.receive === "function") {
      ExternalInterfaceRpc.receive = wrapFn("ExternalInterfaceRpc.receive", ExternalInterfaceRpc.receive.bind(ExternalInterfaceRpc));
    }

    var api = window.ResourceLoaderJsApi;
    if (api) {
      ["init", "onInit", "supported", "validate", "loadBase64"].forEach(function (m) {
        if (typeof api[m] === "function") api[m] = wrapFn("ResourceLoaderJsApi." + m, api[m].bind(api));
      });
    }

    if (window.king && typeof king.available === "function") {
      king.available = wrapFn("king.available", king.available.bind(king));
    }
  }

  function loadWithRuffle(flashvars, params) {
    hideFlashFallback();

    var container = document.getElementById("game");
    if (!container) {
      log("missing #game container");
      return;
    }

    if (!window.RufflePlayer) {
      log("RufflePlayer global missing");
      container.innerHTML =
        '<p style="color:#c00;font:16px sans-serif;padding:1rem">Ruffle failed to load. Check __ruffle__/ruffle.js</p>';
      return;
    }

    container.innerHTML = "";
    var ruffle = window.RufflePlayer.newest();
    var player = ruffle.createPlayer();
    player.id = "game";
    player.name = "game";
    player.style.width = "800px";
    player.style.height = "600px";
    player.style.display = "block";
    container.appendChild(player);

    flashvars = normalizeFlashvars(flashvars);
    var swfUrl = (flashvars && flashvars.clientSwf) || (sodaBase() + "/Client.swf");
    var parameters = Object.assign({}, flashvars || {});
    delete parameters.clientSwf;

    wirePlayer(player);
    log("loading", swfUrl, {
      forceSoftware: parameters.forceSoftware,
      CDN: parameters.CDN,
      mapLen: parameters.resourcesMap ? parameters.resourcesMap.length : 0,
    });

    // Keep the SWF URL clean. GitHub Pages rejects multi‑KB query strings, and if
    // the URL already has short flashvars Ruffle may ignore the parameters object
    // (so resourcesMap never arrives and the game dies after the King logo).
    var encodedParams = buildQuery(parameters);
    log("flashvars bytes", encodedParams.length, "ab", !!(parameters && parameters.abTestCases), "map", !!(parameters && parameters.resourcesMap));

    // Leave traceObserver unset. The game traces a number every frame, and a
    // JS observer turns that into a host call on the same thread as rendering.

    // Intercept Flash → JS before the first frame runs.
    interceptFlashToJs();

    var loadPromise = player.ruffle
      ? player.ruffle().load({
          url: swfUrl,
          // Encoded string form matches the working local PHP path.
          parameters: encodedParams,
          allowScriptAccess: true,
          autoplay: "on",
          unmuteOverlay: "hidden",
          letterbox: "on",
          // Prefer WebGPU; fall back is handled by Ruffle. Stage3D/Context3D
          // only works on the wgpu family — never force canvas/webgl.
          // webgpu wedges the tab on this client. Stage3D still runs on wgpu-webgl.
          preferredRenderer: (/(?:^|[?&])renderer=([^&]*)/.exec(window.location.search) || [])[1] || "wgpu-webgl",
          logLevel: (/(?:^|[?&])log=([^&]*)/.exec(window.location.search) || [])[1] || "warn",
          deviceFontRenderer: "canvas",
          maxExecutionDuration: Number((/(?:^|[?&])budget=(\d+)/.exec(window.location.search) || [])[1] || 120),
          // Original embed used wmode=direct (required for Stage3D in real FP).
          wmode: "direct",
        })
      : player.load(swfUrl);

    Promise.resolve(loadPromise)
      .then(function () {
        log("load ok");
        interceptFlashToJs();
        wirePlayer(player);
        if (window.king && king.eventBus && king.Event && king.EventType) {
          try {
            king.eventBus.post(
              new king.Event(king.EventType.CLIENT_LOADED, {
                success: true,
                ref: player,
                id: "game",
              })
            );
          } catch (e) {
            log("CLIENT_LOADED post failed", e);
          }
        }
        if (typeof window.__sodaRuffleEmbedCb === "function") {
          try {
            window.__sodaRuffleEmbedCb({
              success: true,
              ref: player,
              id: "game",
            });
          } catch (e) {
            log("embed callback failed", e);
          }
        }
        // Re-bind periodically — Ruffle may redefine callback properties after AS addCallback.
        var n = 0;
        var t = setInterval(function () {
          n += 1;
          wirePlayer(player);
          if (n > 40) clearInterval(t);
        }, 500);
      })
      .catch(function (err) {
        log("load failed", err);
        container.innerHTML =
          '<p style="color:#c00;font:16px sans-serif;padding:1rem">Ruffle could not load Client.swf. See console for details.</p>';
      });
  }

  function install() {
    var previous = window.showGame;
    window.showGame = function () {
      var flashvars = null;
      var params = {
        allowScriptAccess: "always",
        wmode: "opaque",
      };

      if (typeof previous === "function") {
        var restored = false;
        var originalEmbed = window.swfobject && window.swfobject.embedSWF;
        if (window.swfobject) {
          window.swfobject.hasFlashPlayerVersion = function () {
            return true;
          };
          window.swfobject.getFlashPlayerVersion = function () {
            return { major: 32, minor: 0, release: 0 };
          };
          window.swfobject.embedSWF = function (
            swfUrl,
            id,
            w,
            h,
            ver,
            xi,
            fv,
            p,
            attrs,
            cb
          ) {
            flashvars = Object.assign({ clientSwf: swfUrl }, fv || {});
            params = p || params;
            if (typeof cb === "function") {
              window.__sodaRuffleEmbedCb = cb;
            }
            restored = true;
          };
        }

        try {
          previous();
        } catch (e) {
          log("original showGame error (continuing with Ruffle)", e);
        }

        if (window.swfobject && originalEmbed) {
          window.swfobject.embedSWF = originalEmbed;
        }
        if (!restored) {
          log("original showGame did not call embedSWF; using fallback flashvars");
        }
      }

      if (!flashvars) {
        flashvars = Object.assign({
          clientSwf: sodaBase() + "/Client.swf",
          remoteRpcServiceUrl: sodaBase() + "/rpc/ClientApi.php",
          sessionKey: "izWQJpv7IwenmdYyqSk1eA.2",
          serverHost: window.location.host,
          CDN: sodaBase(),
          resourceBase: new URL("resources/", sodaBase() + "/").pathname,
          clientVersionUrl: sodaBase() + "/version.txt",
          abTestCases: "high_popular_goldbar_pkg;1,illegal_switch_enabled;1,hard_level_pin_enabled;1,illegal_switch_prices;0,booster_bundling_mode;2,new_goldbar_shop;1,striped_lollipop_enabled;1,goldbar_shop_special_offer;0,levels;0,hard_levels_labeling;3",
          fName: "OldLoad",
          fId: "1481077882135",
          firstSignInTime: "1",
          forceSoftware: ""
        }, window.__SODA_FLASHVARS__ || {});
      }
      flashvars = normalizeFlashvars(flashvars);

      function start() {
        loadWithRuffle(flashvars, params);
      }

      if (window.RufflePlayer && window.RufflePlayer.newest) {
        start();
      } else {
        var tries = 0;
        var timer = setInterval(function () {
          tries += 1;
          if (window.RufflePlayer && window.RufflePlayer.newest) {
            clearInterval(timer);
            start();
          } else if (tries > 50) {
            clearInterval(timer);
            log("timed out waiting for Ruffle");
            start();
          }
        }, 100);
      }
    };

    log("showGame hooked");
  }

  install();
})();
