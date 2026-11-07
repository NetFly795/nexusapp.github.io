(function () {
  var STAGE_W = 800;
  var STAGE_H = 600;

  function viewport() {
    var vv = window.visualViewport;
    if (vv && vv.width && vv.height) return { w: vv.width, h: vv.height };
    return {
      w: window.innerWidth || document.documentElement.clientWidth || STAGE_W,
      h: window.innerHeight || document.documentElement.clientHeight || STAGE_H
    };
  }

  function fit() {
    var stage = document.getElementById("soda-stage");
    if (!stage) return;
    var vp = viewport();
    // Fill as much of the screen as possible while keeping 4:3.
    var scale = Math.min(vp.w / STAGE_W, vp.h / STAGE_H);
    stage.style.transform = "scale(" + scale + ")";
    stage.style.width = STAGE_W + "px";
    stage.style.height = STAGE_H + "px";
  }

  window.addEventListener("resize", fit);
  window.addEventListener("orientationchange", fit);
  if (window.visualViewport) {
    window.visualViewport.addEventListener("resize", fit);
    window.visualViewport.addEventListener("scroll", fit);
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", fit);
  else fit();
  var n = 0;
  var t = setInterval(function () {
    fit();
    if (++n > 20) clearInterval(t);
  }, 500);
})();
