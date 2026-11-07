(function () {
  'use strict';

  var INTRO_COUNT = 15;
  var STAGGER_MS = 65;

  var SPRING = {
    stiffness: 178,
    damping: 22,
    mass: 1
  };

  var START = {
    y: 42,
    scale: 0.84,
    opacity: 0
  };

  function shouldSkip() {
    if (window.NexusPerf && window.NexusPerf.isLite && window.NexusPerf.isLite()) return true;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return true;
    if (document.body.classList.contains('performance-mode')) return true;
    return false;
  }

  function clearTileMotion(tile) {
    if (!tile) return;
    tile.style.opacity = '';
    tile.style.transform = '';
    tile.style.willChange = '';
  }

  function revealTilesFrom(links, startIndex) {
    for (var i = startIndex; i < links.length; i++) {
      clearTileMotion(links[i].querySelector('.game-tile'));
    }
  }

  function springIntegrate(value, target, velocity, stiffness, damping, mass, dt) {
    var acceleration = (-stiffness * (value - target) - damping * velocity) / mass;
    velocity += acceleration * dt;
    value += velocity * dt;
    return { value: value, velocity: velocity };
  }

  function isSettled(value, target, velocity, valueThreshold, velocityThreshold) {
    return Math.abs(value - target) < valueThreshold && Math.abs(velocity) < velocityThreshold;
  }

  function animateTileSpring(tile, delayMs, onComplete) {
    var y = START.y;
    var scale = START.scale;
    var opacity = START.opacity;
    var vy = 0;
    var vs = 0;
    var vo = 0;
    var lastTime = 0;
    var running = false;

    tile.style.willChange = 'transform, opacity';

    setTimeout(function () {
      running = true;

      function frame(now) {
        if (!running) return;

        if (!lastTime) lastTime = now;
        var dt = Math.min(0.028, (now - lastTime) / 1000);
        lastTime = now;

        var ry = springIntegrate(y, 0, vy, SPRING.stiffness, SPRING.damping, SPRING.mass, dt);
        y = ry.value;
        vy = ry.velocity;

        var rs = springIntegrate(scale, 1, vs, SPRING.stiffness, SPRING.damping, SPRING.mass, dt);
        scale = rs.value;
        vs = rs.velocity;

        var ro = springIntegrate(opacity, 1, vo, SPRING.stiffness * 1.15, SPRING.damping * 0.92, SPRING.mass, dt);
        opacity = ro.value;
        vo = ro.velocity;

        if (opacity < 0) opacity = 0;
        if (opacity > 1) opacity = 1;

        tile.style.opacity = String(opacity);
        tile.style.transform = 'scale(' + scale + ') translateY(' + y + 'px)';

        var settled =
          isSettled(y, 0, vy, 0.15, 0.12) &&
          isSettled(scale, 1, vs, 0.0015, 0.008) &&
          isSettled(opacity, 1, vo, 0.008, 0.02);

        if (settled) {
          running = false;
          clearTileMotion(tile);
          if (onComplete) onComplete();
          return;
        }

        requestAnimationFrame(frame);
      }

      requestAnimationFrame(frame);
    }, delayMs);
  }

  function cleanupIntro(links, count) {
    for (var j = 0; j < count; j++) {
      links[j].classList.remove('tile-intro');
      clearTileMotion(links[j].querySelector('.game-tile'));
    }
  }

  function runIntro() {
    var grid = document.getElementById('search');
    if (!grid || grid.dataset.tileIntroDone === '1') return;
    grid.dataset.tileIntroDone = '1';

    var links = grid.querySelectorAll('a.game-link');

    if (shouldSkip()) {
      grid.removeAttribute('data-intro-pending');
      revealTilesFrom(links, 0);
      return;
    }

    var count = Math.min(INTRO_COUNT, links.length);
    if (!count) {
      grid.removeAttribute('data-intro-pending');
      revealTilesFrom(links, 0);
      return;
    }

    var tiles = [];
    for (var i = 0; i < count; i++) {
      var tile = links[i].querySelector('.game-tile');
      if (!tile) continue;
      links[i].classList.add('tile-intro');
      tile.style.opacity = '0';
      tile.style.transform = 'scale(' + START.scale + ') translateY(' + START.y + 'px)';
      tiles.push(tile);
    }

    grid.removeAttribute('data-intro-pending');
    revealTilesFrom(links, count);

    if (!tiles.length) return;

    var remaining = tiles.length;

    function onTileDone() {
      remaining--;
      if (remaining <= 0) {
        cleanupIntro(links, count);
      }
    }

    for (var k = 0; k < tiles.length; k++) {
      animateTileSpring(tiles[k], k * STAGGER_MS, onTileDone);
    }
  }

  function boot() {
    window.addEventListener('nexus-games-grid-ready', runIntro, { once: true });
    window.addEventListener('nexus-games-catalog-ready', function () {
      // Fallback if Luna recs are off / delayed — intro can still run after tiles exist
      setTimeout(runIntro, 0);
    }, { once: true });
    window.addEventListener('load', function onLoad() {
      window.removeEventListener('load', onLoad);
      runIntro();
    }, { once: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
