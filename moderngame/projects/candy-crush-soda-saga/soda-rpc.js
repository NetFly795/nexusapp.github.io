(function () {
  var STORE_KEY = "ccss-soda-save-v1";
  var LEVEL_COUNT = 1375;
  var MAX_LIVES = 5;
  var REGEN_SECONDS = 30 * 60;
  var USER_ID = 168025967;
  var packagesCache = null;

  function defaultBoosters() {
    return [
      { type: "StritzLollipopHammer", typeId: 12002, amount: 3, availability: 2, unlocked: true },
      { type: "StritzStripedLollipopHammer", typeId: 12003, amount: 3, availability: 2, unlocked: true },
      { type: "StritzIllegalSwitch", typeId: 12005, amount: 3, availability: 2, unlocked: true },
      { type: "StritzColorbombPregameBooster", typeId: 12020, amount: 3, availability: 2, unlocked: true },
      { type: "StritzColoringCandyPregameBooster", typeId: 12021, amount: 3, availability: 2, unlocked: true },
      { type: "StritzDoubleDelishFishPregameBooster", typeId: 12022, amount: 3, availability: 2, unlocked: true },
      { type: "StritzStripedCandyPregameBooster", typeId: 12026, amount: 3, availability: 2, unlocked: true },
      { type: "StritzWrappedCandyPregameBooster", typeId: 12027, amount: 3, availability: 2, unlocked: true }
    ];
  }

  function defaultLevels() {
    var levels = [];
    for (var i = 1; i <= LEVEL_COUNT; i++) {
      levels.push({
        id: i,
        score: 0,
        stars: 0,
        locked: i > 1,
        unlockTime: i === 1 ? Date.now() : 0,
        completedTime: 0
      });
    }
    return levels;
  }

  function defaultState() {
    return {
      levels: defaultLevels(),
      boosters: defaultBoosters(),
      lives: { lives: MAX_LIVES, timeToNextRegeneration: -1, immortal: false, lastUpdate: Date.now() },
      currency: { softCurrency: 0, hardCurrency: 500 },
      userData: { values: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0] },
      messages: []
    };
  }

  function loadState() {
    try {
      var raw = localStorage.getItem(STORE_KEY);
      if (!raw) return defaultState();
      var parsed = JSON.parse(raw);
      var base = defaultState();
      if (!parsed || typeof parsed !== "object") return base;
      if (Array.isArray(parsed.levels) && parsed.levels.length) {
        var byId = {};
        parsed.levels.forEach(function (lv) {
          if (lv && lv.id) byId[lv.id] = lv;
        });
        base.levels = base.levels.map(function (lv) {
          return byId[lv.id] ? Object.assign({}, lv, byId[lv.id]) : lv;
        });
      }
      if (Array.isArray(parsed.boosters) && parsed.boosters.length) base.boosters = parsed.boosters;
      if (parsed.lives) base.lives = Object.assign(base.lives, parsed.lives);
      if (parsed.currency) base.currency = Object.assign(base.currency, parsed.currency);
      if (parsed.userData && Array.isArray(parsed.userData.values)) base.userData = parsed.userData;
      if (Array.isArray(parsed.messages)) base.messages = parsed.messages;
      return base;
    } catch (e) {
      return defaultState();
    }
  }

  function saveState(state) {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify(state));
    } catch (e) {
      console.warn("[soda-rpc] localStorage write failed", e);
    }
  }

  function regenLives(state) {
    var life = state.lives;
    if (!life || life.immortal) return;
    if (life.lives >= MAX_LIVES) {
      life.lives = MAX_LIVES;
      life.timeToNextRegeneration = -1;
      life.lastUpdate = Date.now();
      return;
    }
    var now = Date.now();
    var last = life.lastUpdate || now;
    var elapsed = Math.max(0, now - last);
    if (life.timeToNextRegeneration < 0) {
      life.timeToNextRegeneration = REGEN_SECONDS * 1000;
    }
    var remaining = life.timeToNextRegeneration;
    while (life.lives < MAX_LIVES && elapsed >= remaining) {
      elapsed -= remaining;
      life.lives += 1;
      remaining = REGEN_SECONDS * 1000;
    }
    if (life.lives >= MAX_LIVES) {
      life.lives = MAX_LIVES;
      life.timeToNextRegeneration = -1;
    } else {
      life.timeToNextRegeneration = remaining - elapsed;
    }
    life.lastUpdate = now;
  }

  function syncLevels(state, incoming) {
    if (!Array.isArray(incoming)) return;
    var byId = {};
    state.levels.forEach(function (lv) {
      byId[lv.id] = lv;
    });
    incoming.forEach(function (lv) {
      if (!lv || !lv.id) return;
      var cur = byId[lv.id] || {
        id: lv.id,
        score: 0,
        stars: 0,
        locked: true,
        unlockTime: 0,
        completedTime: 0
      };
      if (typeof lv.score === "number" && lv.score > cur.score) cur.score = lv.score;
      if (typeof lv.stars === "number" && lv.stars > cur.stars) cur.stars = lv.stars;
      if (lv.locked === false) cur.locked = false;
      if (lv.unlockTime) cur.unlockTime = lv.unlockTime;
      if (lv.completedTime) cur.completedTime = lv.completedTime;
      // Completing a level unlocks the next one.
      if (cur.stars > 0 || cur.completedTime || (lv.score && lv.score > 0)) {
        cur.locked = false;
        if (!cur.completedTime) cur.completedTime = Date.now();
        var next = byId[cur.id + 1];
        if (next) {
          next.locked = false;
          if (!next.unlockTime) next.unlockTime = Date.now();
        }
      }
      byId[cur.id] = cur;
    });
    state.levels = [];
    for (var i = 1; i <= LEVEL_COUNT; i++) {
      state.levels.push(
        byId[i] || {
          id: i,
          score: 0,
          stars: 0,
          locked: i > 1,
          unlockTime: 0,
          completedTime: 0
        }
      );
    }
  }

  function findBooster(state, typeId) {
    for (var i = 0; i < state.boosters.length; i++) {
      if (state.boosters[i].typeId === typeId || state.boosters[i].typeId === Number(typeId)) {
        return state.boosters[i];
      }
    }
    return null;
  }

  function useBoosters(state, amounts) {
    if (!Array.isArray(amounts)) return state.boosters;
    amounts.forEach(function (item) {
      if (!item) return;
      var id = item.typeId != null ? item.typeId : item.itemTypeId;
      var amount = item.amount != null ? item.amount : 1;
      var b = findBooster(state, id);
      if (b) b.amount = Math.max(0, (b.amount || 0) - amount);
    });
    return state.boosters;
  }

  function addBooster(state, typeId, count) {
    var b = findBooster(state, typeId);
    if (b) {
      b.amount = (b.amount || 0) + (count || 0);
      b.unlocked = true;
    } else {
      state.boosters.push({
        type: "Item" + typeId,
        typeId: Number(typeId),
        amount: count || 0,
        availability: 2,
        unlocked: true
      });
    }
    return state.boosters;
  }

  function loadPackages(cb) {
    if (packagesCache) {
      cb(packagesCache);
      return;
    }
    var url = (window.__SODA_BASE__ || ".") + "/product-packages.json";
    fetch(url)
      .then(function (r) {
        return r.json();
      })
      .then(function (j) {
        packagesCache = j;
        cb(j);
      })
      .catch(function () {
        packagesCache = [];
        cb([]);
      });
  }

  function handle(method, params, state) {
    params = params || [];
    switch (method) {
      case "GuiTracking.CustomFunnel":
      case "ClientHealthTracking.clientStartupStage2":
      case "ClientHealthTracking.clientLoadProgress2":
      case "ClientHealthTracking.clientInformation2":
      case "ClientHealthTracking.clientException2":
      case "StritzApi.reportBrowserPlatform":
      case "StritzApi.reportFramerate":
      case "StritzApi.reportInGameCounters":
      case "KingLevelApi.setActionAmount":
      case "KingdomAchievementApi.startAchievement":
      case "KingdomAchievementApi.increaseAchievementDataBalance":
      case "CollaborationApi.synchronizeCollaborationContainers":
        return [];

      case "StritzApi.getUserData":
        return state.userData;
      case "StritzApi.setUserDataValue": {
        var key = params[0] | 0;
        var value = params[1] | 0;
        while (state.userData.values.length <= key) state.userData.values.push(0);
        state.userData.values[key] = value;
        return null;
      }
      case "StritzApi.isPaymentConversionUser":
        return true;
      case "StritzApi.getActiveSales":
        return [];
      case "StritzApi.getUserSegment":
        return 0;

      case "StarLevelApi.getLevels":
        return state.levels;
      case "StarLevelApi.synchronizeLevels":
        syncLevels(state, params[0]);
        return null;
      case "StarLevelApi.getUserProgressions":
      case "StarLevelApi.getUserProgressions2":
      case "StarLevelApi.getMetaGraphUserProgressions":
        return { entries: [] };
      case "StarLevelApi.getLevelToplist":
      case "StarLevelApi.getLevelToplist2":
      case "StarLevelApi.getMetaGraphLevelToplist": {
        var my = state.levels[(params[0] | 0) - 1];
        var myScore = my ? my.score || 0 : 0;
        return {
          entries: [
            { userId: 1, value: Math.max(6942069, myScore) },
            { userId: USER_ID, value: myScore }
          ]
        };
      }

      case "BoosterApi.getBoosters":
        return state.boosters;
      case "BoosterApi.useBoosters":
        return useBoosters(state, params[0]);
      case "BoosterApi.addBooster":
        return addBooster(state, params[0], params[1]);
      case "BoosterApi.acceptBoosterGift":
        if (params[0]) addBooster(state, params[0].typeId || params[0].itemTypeId, params[0].amount || 1);
        return state.boosters;
      case "BoosterApi.unlockBooster": {
        var ub = findBooster(state, params[0]);
        if (ub) {
          ub.unlocked = true;
          return ub;
        }
        return {
          type: "StritzLollipopHammer",
          typeId: params[0] || 12002,
          amount: 0,
          availability: 2,
          unlocked: true
        };
      }

      case "ProductApi.getUserCurrency":
        return "USD";
      case "ProductApi.getAllProductPackages":
        return packagesCache || [];
      case "ProductApi.purchase":
        return { status: "ok", isPurchaseForAnotherUser: false };

      case "VirtualCurrencyApi.getBalance":
      case "VirtualCurrencyApi.payoutSoftCurrency":
        return state.currency;

      case "LifeApi.getLife":
        regenLives(state);
        return {
          lives: state.lives.lives,
          timeToNextRegeneration: state.lives.timeToNextRegeneration,
          immortal: !!state.lives.immortal
        };
      case "LifeApi.addLives":
        regenLives(state);
        state.lives.lives = Math.min(MAX_LIVES, state.lives.lives + (params[0] || 1));
        if (state.lives.lives >= MAX_LIVES) state.lives.timeToNextRegeneration = -1;
        return {
          lives: state.lives.lives,
          timeToNextRegeneration: state.lives.timeToNextRegeneration,
          immortal: !!state.lives.immortal
        };
      case "LifeApi.removeLives":
        regenLives(state);
        state.lives.lives = Math.max(0, state.lives.lives - (params[0] || 1));
        if (state.lives.lives < MAX_LIVES && state.lives.timeToNextRegeneration < 0) {
          state.lives.timeToNextRegeneration = REGEN_SECONDS * 1000;
          state.lives.lastUpdate = Date.now();
        }
        return {
          lives: state.lives.lives,
          timeToNextRegeneration: state.lives.timeToNextRegeneration,
          immortal: !!state.lives.immortal
        };
      case "LifeApi.getMaxLives":
        return MAX_LIVES;
      case "LifeApi.getLifeRegenerationTimeInSeconds":
        return REGEN_SECONDS;

      case "UnlimitedLivesApi.incrementDefaultUnlimitedLivesExpireTime":
      case "UnlimitedLivesApi.incrementUnlimitedLivesExpireTime":
      case "UnlimitedLivesApi.getUnlimitedLivesExpireTime":
        return { coreUserId: String(USER_ID), expireTimestamp: "0" };

      case "CollaborationApi.getCollaborationContainers":
        return [];

      case "ProfileCardApi.isProfileCardEnabled":
        return true;
      case "ProfileCardApi.getActiveKingAppIds":
        return { kingAppIds: ["33"] };
      case "ProfileCardApi.getActiveKingApps":
        return { id: "33", carouselImage: null };

      case "KingLevelApi.getUserKingLevel":
      case "KingLevelApi.getUserKingLevelByUser":
        return {
          kingLevel: 1,
          percentOfCurrentLevel: 0,
          kingLevelActionDtos: [{ kingAppId: 33, kingLevelActionTypeId: 1, amount: 1 }]
        };

      case "KingdomAchievementApi.getAchievementsByKingApp":
      case "KingdomAchievementApi.getAchievementsByKingAppAndAchievementType":
        return [];
      case "KingdomAchievementApi.getAchievementDataList":
      case "KingdomAchievementApi.getAchievementDataListByKingApp":
      case "KingdomAchievementApi.getAchievementDataListByKingAppAndAchievementType":
        return { coreUserId: String(USER_ID), achievementDataDtos: [] };

      case "SocialUserApi.getCurrentUser":
        return {
          userId: USER_ID,
          langCode: "en",
          country: "US",
          externalUserId: "LOCAL-SODA-USER",
          firstName: "Player",
          friendType: "NONE",
          lastSignInTime: Math.floor(Date.now() / 1000),
          name: "Player",
          pic: "",
          pic100: ""
        };
      case "SocialUserApi.getAppFriends":
        return [];

      case "MessageApi.fetchAndDeleteMessages": {
        var msgs = state.messages || [];
        state.messages = [];
        return msgs;
      }

      case "SignInDataApi.getCurrentUserAppSignIn":
        return { num: USER_ID };

      case "TimeApi.getUserTime":
      case "TimeApi.getServerTime":
        return Date.now();

      case "ApplicationSettingsApi.getSettings":
        return [
          { name: "canvas.synergy.enabled", value: true },
          { name: "synergy.start.time", value: 0 },
          { name: "released.episode", value: 81 },
          { name: "sidekicks.enabled", value: true }
        ];
      case "ApplicationSettingsApi.getSettingsWithPrefix":
        return [];

      case "MissionApi.getAccomplishedMissions":
      case "MissionApi.getAvailableMissions":
        return [];

      case "BlobStoreTranslationsApi.getTranslationsUrls":
        return { error: "no translations!" };
      case "UserMetricsApi.getUserMetrics":
        return { error: "userMetrics2 not supported" };

      case "KingdomApi.sendMessage":
        return null;

      default:
        return null;
    }
  }

  function processBatch(requests) {
    var state = loadState();
    regenLives(state);
    var responses = requests.map(function (req) {
      var result;
      try {
        result = handle(req.method, req.params, state);
      } catch (e) {
        console.warn("[soda-rpc]", req.method, e);
        result = null;
      }
      var response = { jsonrpc: "2.0", id: req.id };
      if (result !== null && result !== undefined) response.result = result;
      return response;
    });
    // Product packages may still be loading — fill after.
    saveState(state);
    return responses;
  }

  function isRpcUrl(url) {
    if (!url) return false;
    return /\/rpc\/ClientApi\.php/i.test(String(url));
  }

  function respondXhr(xhr, body) {
    var text = typeof body === "string" ? body : JSON.stringify(body);
    Object.defineProperty(xhr, "readyState", { configurable: true, get: function () { return 4; } });
    Object.defineProperty(xhr, "status", { configurable: true, get: function () { return 200; } });
    Object.defineProperty(xhr, "statusText", { configurable: true, get: function () { return "OK"; } });
    Object.defineProperty(xhr, "responseText", { configurable: true, get: function () { return text; } });
    Object.defineProperty(xhr, "response", {
      configurable: true,
      get: function () {
        return xhr.responseType === "json" ? JSON.parse(text) : text;
      }
    });
    if (typeof xhr.onreadystatechange === "function") xhr.onreadystatechange();
    if (typeof xhr.onload === "function") xhr.onload();
  }

  // Prefetch packages so ProductApi is ready.
  loadPackages(function () {});

  var XO = XMLHttpRequest;
  function WrappedXHR() {
    var xhr = new XO();
    var _url = "";
    var _async = true;
    var _method = "GET";
    var origOpen = xhr.open;
    var origSend = xhr.send;
    xhr.open = function (method, url, async) {
      _method = method;
      _url = url;
      _async = async !== false;
      return origOpen.apply(xhr, arguments);
    };
    xhr.send = function (body) {
      if (!isRpcUrl(_url) || String(_method).toUpperCase() !== "POST") {
        return origSend.apply(xhr, arguments);
      }
      var requests;
      try {
        requests = typeof body === "string" ? JSON.parse(body) : body;
      } catch (e) {
        requests = [];
      }
      if (!Array.isArray(requests)) requests = [requests];

      function finish() {
        var responses = processBatch(requests);
        // Ensure packages are embedded for getAllProductPackages.
        responses.forEach(function (resp, i) {
          if (requests[i] && requests[i].method === "ProductApi.getAllProductPackages") {
            resp.result = packagesCache || [];
          }
        });
        respondXhr(xhr, responses);
      }

      if (requests.some(function (r) { return r && r.method === "ProductApi.getAllProductPackages"; }) && !packagesCache) {
        loadPackages(finish);
      } else if (_async) {
        setTimeout(finish, 0);
      } else {
        finish();
      }
    };
    return xhr;
  }
  WrappedXHR.prototype = XO.prototype;
  window.XMLHttpRequest = WrappedXHR;

  function readBody(input, init) {
    if (init && init.body != null) {
      if (typeof init.body === "string") return Promise.resolve(init.body);
      if (init.body instanceof ArrayBuffer) return Promise.resolve(new TextDecoder().decode(init.body));
      if (typeof Blob !== "undefined" && init.body instanceof Blob) return init.body.text();
      if (typeof Uint8Array !== "undefined" && init.body instanceof Uint8Array) {
        return Promise.resolve(new TextDecoder().decode(init.body));
      }
      try {
        return Promise.resolve(String(init.body));
      } catch (e) {
        return Promise.resolve("");
      }
    }
    if (input && typeof input === "object" && typeof input.text === "function") {
      try {
        return input.clone().text();
      } catch (e) {
        return input.text();
      }
    }
    return Promise.resolve("");
  }

  if (window.fetch) {
    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      var url = typeof input === "string" ? input : input && input.url;
      var method = (init && init.method) || (input && input.method) || "GET";
      if (!isRpcUrl(url) || String(method).toUpperCase() !== "POST") {
        return origFetch(input, init);
      }
      return readBody(input, init)
        .then(function (bodyText) {
          var requests;
          try {
            requests = bodyText ? JSON.parse(bodyText) : [];
          } catch (e) {
            console.warn("[soda-rpc] bad RPC body", e, String(bodyText).slice(0, 200));
            requests = [];
          }
          if (!Array.isArray(requests)) requests = [requests];
          return new Promise(function (resolve) {
            function done() {
              try {
                var responses = processBatch(requests);
                responses.forEach(function (resp, i) {
                  if (requests[i] && requests[i].method === "ProductApi.getAllProductPackages") {
                    resp.result = packagesCache || [];
                  }
                });
                resolve(
                  new Response(JSON.stringify(responses), {
                    status: 200,
                    headers: { "Content-Type": "application/json" }
                  })
                );
              } catch (err) {
                console.warn("[soda-rpc] handler failed", err);
                resolve(
                  new Response("[]", {
                    status: 200,
                    headers: { "Content-Type": "application/json" }
                  })
                );
              }
            }
            if (requests.some(function (r) { return r && r.method === "ProductApi.getAllProductPackages"; }) && !packagesCache) {
              loadPackages(done);
            } else {
              done();
            }
          });
        })
        .catch(function (err) {
          console.warn("[soda-rpc] fetch intercept failed", err);
          return new Response("[]", {
            status: 200,
            headers: { "Content-Type": "application/json" }
          });
        });
    };
  }

  console.log("[soda-rpc] localStorage save active (" + STORE_KEY + ")");
})();
