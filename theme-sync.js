// welcome to the ultimate way of duct taping visuals together!!
(function() {
  var themes = {
    default: {
      gradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      accent: '#6366f1',
      accentHover: '#4f46e5',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(127, 140, 255, 0.45) 0%, rgba(79, 95, 255, 0.15) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(255, 184, 108, 0.35) 0%, rgba(255, 152, 0, 0.1) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 60% 60%, rgba(110, 231, 183, 0.35) 0%, rgba(0, 200, 150, 0.08) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 40% 40%, rgba(255, 107, 129, 0.35) 0%, rgba(255, 23, 68, 0.08) 60%, transparent 100%)',
      glassTint: 'rgba(255, 255, 255, 0.07)',
      glassBorder: 'rgba(255, 255, 255, 0.12)',
      textAccent: '#1565c0',
      textAccentBg: '#e3eafc',
      bg: '#f2f5f9',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a6a'
    },
    deepblue: {
      gradient: 'linear-gradient(135deg, #1e3c72 0%, #2a5298 100%)',
      accent: '#1e3c72',
      accentHover: '#2a5298',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(30, 144, 255, 0.5) 0%, rgba(0, 0, 139, 0.2) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(0, 191, 255, 0.4) 0%, rgba(30, 144, 255, 0.15) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 60% 60%, rgba(70, 130, 180, 0.35) 0%, rgba(70, 130, 180, 0.1) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 40% 40%, rgba(0, 206, 209, 0.35) 0%, rgba(0, 139, 139, 0.1) 60%, transparent 100%)',
      glassTint: 'rgba(30, 144, 255, 0.04)',
      glassBorder: 'rgba(30, 144, 255, 0.15)',
      textAccent: '#1976d2',
      textAccentBg: '#e3f2fd',
      bg: '#f2f5f9',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a6a'
    },
    cherryred: {
      gradient: 'linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%)',
      accent: '#ff416c',
      accentHover: '#ff4b2b',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(255, 65, 108, 0.5) 0%, rgba(139, 0, 0, 0.2) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(255, 75, 43, 0.4) 0%, rgba(220, 20, 60, 0.15) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 60% 60%, rgba(255, 105, 180, 0.35) 0%, rgba(199, 21, 133, 0.1) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 40% 40%, rgba(255, 99, 71, 0.35) 0%, rgba(255, 69, 0, 0.1) 60%, transparent 100%)',
      glassTint: 'rgba(255, 65, 108, 0.04)',
      glassBorder: 'rgba(255, 65, 108, 0.15)',
      textAccent: '#d32f2f',
      textAccentBg: '#ffebee',
      bg: '#f2f5f9',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a6a'
    },
    gold: {
      gradient: 'linear-gradient(135deg, #f7971e 0%, #ffd200 100%)',
      accent: '#f7971e',
      accentHover: '#ffd200',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(247, 151, 30, 0.5) 0%, rgba(218, 165, 32, 0.2) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(255, 215, 0, 0.4) 0%, rgba(255, 223, 0, 0.15) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 60% 60%, rgba(255, 140, 0, 0.35) 0%, rgba(255, 165, 0, 0.1) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 40% 40%, rgba(244, 164, 96, 0.35) 0%, rgba(205, 133, 63, 0.1) 60%, transparent 100%)',
      glassTint: 'rgba(247, 151, 30, 0.04)',
      glassBorder: 'rgba(247, 151, 30, 0.15)',
      textAccent: '#f57c00',
      textAccentBg: '#fff3e0',
      bg: '#f2f5f9',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a6a'
    },
    purple: {
      gradient: 'linear-gradient(135deg, #8e2de2 0%, #4a00e0 100%)',
      accent: '#8e2de2',
      accentHover: '#4a00e0',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(142, 45, 226, 0.5) 0%, rgba(75, 0, 130, 0.2) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(74, 0, 224, 0.4) 0%, rgba(106, 90, 205, 0.15) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 60% 60%, rgba(186, 85, 211, 0.35) 0%, rgba(147, 112, 219, 0.1) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 40% 40%, rgba(216, 191, 216, 0.35) 0%, rgba(230, 230, 250, 0.1) 60%, transparent 100%)',
      glassTint: 'rgba(142, 45, 226, 0.04)',
      glassBorder: 'rgba(142, 45, 226, 0.15)',
      textAccent: '#7b1fa2',
      textAccentBg: '#f3e5f5',
      bg: '#f2f5f9',
      textPrimary: '#1a1a2e',
      textSecondary: '#4a4a6a'
    }
  };

  // dark themes. we have opinions about cyan at 2am
  var darkThemes = {
    nord: {
      name: 'Nord Frost',
      gradient: 'linear-gradient(135deg, #2dd4bf 0%, #0d9488 100%)',
      accent: '#2dd4bf',
      accentHover: '#0d9488',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(45, 212, 191, 0.14) 0%, rgba(45, 212, 191, 0.03) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(56, 189, 248, 0.12) 0%, rgba(56, 189, 248, 0.02) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 65% 65%, rgba(99, 240, 213, 0.09) 0%, rgba(99, 240, 213, 0.02) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 35% 35%, rgba(14, 165, 233, 0.09) 0%, rgba(14, 165, 233, 0.02) 60%, transparent 100%)',
      glassTint: 'rgba(14, 22, 32, 0.75)',
      glassBorder: 'rgba(255, 255, 255, 0.08)',
      textAccent: '#5eead4',
      textAccentBg: 'rgba(45, 212, 191, 0.12)',
      bg: '#0d131a',
      textPrimary: '#f1f5f9',
      textSecondary: '#94a3b8',
      bodyGlow1: 'rgba(45, 212, 191, 0.08)',
      bodyGlow2: 'rgba(56, 189, 248, 0.06)'
    },
    amber: {
      name: 'Obsidian Amber',
      gradient: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
      accent: '#f59e0b',
      accentHover: '#d97706',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(245, 158, 11, 0.14) 0%, rgba(245, 158, 11, 0.03) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(239, 68, 68, 0.12) 0%, rgba(239, 68, 68, 0.02) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 65% 65%, rgba(251, 191, 36, 0.09) 0%, rgba(251, 191, 36, 0.02) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 35% 35%, rgba(217, 119, 6, 0.09) 0%, rgba(217, 119, 6, 0.02) 60%, transparent 100%)',
      glassTint: 'rgba(18, 14, 8, 0.78)',
      glassBorder: 'rgba(255, 255, 255, 0.08)',
      textAccent: '#fbbf24',
      textAccentBg: 'rgba(245, 158, 11, 0.12)',
      bg: '#0a0908',
      textPrimary: '#f5f0e8',
      textSecondary: '#a8a099',
      bodyGlow1: 'rgba(245, 158, 11, 0.08)',
      bodyGlow2: 'rgba(239, 68, 68, 0.05)'
    },
    synthwave: {
      name: 'Synthwave',
      gradient: 'linear-gradient(135deg, #ec4899 0%, #a855f7 100%)',
      accent: '#ec4899',
      accentHover: '#a855f7',
      ball1: 'radial-gradient(circle at 60% 40%, rgba(236, 72, 153, 0.14) 0%, rgba(236, 72, 153, 0.03) 60%, transparent 100%)',
      ball2: 'radial-gradient(circle at 40% 60%, rgba(168, 85, 247, 0.14) 0%, rgba(168, 85, 247, 0.03) 60%, transparent 100%)',
      ball3: 'radial-gradient(circle at 65% 65%, rgba(6, 182, 212, 0.09) 0%, rgba(6, 182, 212, 0.02) 60%, transparent 100%)',
      ball4: 'radial-gradient(circle at 35% 35%, rgba(244, 63, 94, 0.09) 0%, rgba(244, 63, 94, 0.02) 60%, transparent 100%)',
      glassTint: 'rgba(20, 10, 30, 0.76)',
      glassBorder: 'rgba(255, 255, 255, 0.08)',
      textAccent: '#f472b6',
      textAccentBg: 'rgba(236, 72, 153, 0.12)',
      bg: '#0c0714',
      textPrimary: '#f3e8ff',
      textSecondary: '#c084fc',
      bodyGlow1: 'rgba(236, 72, 153, 0.09)',
      bodyGlow2: 'rgba(168, 85, 247, 0.07)'
    }
  };

  function getActiveDarkTheme() {
    var key = localStorage.getItem('nexus_dark_theme') || 'nord';
    return darkThemes[key] || darkThemes.nord;
  }

  var styleElement = null;

  function gameTileFrostCss() {
    return '\n/* ONE frost plate. do NOT stack blur per tile. i will know */\n' +
      '.game-tile {\n' +
      '  backdrop-filter: none !important;\n' +
      '  -webkit-backdrop-filter: none !important;\n' +
      '}\n';
  }

  function NexusGetAssetPath(relativePath) {
    var scripts = document.getElementsByTagName('script');
    var basePath = '';
    for (var i = 0; i < scripts.length; i++) {
      var src = scripts[i].getAttribute('src') || '';
      if (src.indexOf('theme-sync.js') !== -1) {
        basePath = src.split('theme-sync.js')[0];
        break;
      }
    }
    return basePath + relativePath;
  }

  function applyTheme(themeName) {
    var isDark = localStorage.getItem('nexus_dark_mode') === 'true';
    var theme = isDark ? getActiveDarkTheme() : (themes[themeName] || themes.default);
    var galaxyUrl = NexusGetAssetPath('icons/galaxy_backdrop.png');

    var cssText = ':root {\n' +
      '  --theme-gradient: ' + theme.gradient + ';\n' +
      '  --theme-accent: ' + theme.accent + ';\n' +
      '  --theme-accent-hover: ' + theme.accentHover + ';\n' +
      '  --theme-ball-1: ' + theme.ball1 + ';\n' +
      '  --theme-ball-2: ' + theme.ball2 + ';\n' +
      '  --theme-ball-3: ' + theme.ball3 + ';\n' +
      '  --theme-ball-4: ' + theme.ball4 + ';\n' +
      '  --theme-glass-tint: ' + theme.glassTint + ';\n' +
      '  --theme-glass-border: ' + theme.glassBorder + ';\n' +
      '  --theme-text-accent: ' + theme.textAccent + ';\n' +
      '  --theme-text-accent-bg: ' + theme.textAccentBg + ';\n' +
      '  --theme-bg: ' + theme.bg + ';\n' +
      '  --theme-text-primary: ' + theme.textPrimary + ';\n' +
      '  --theme-text-secondary: ' + theme.textSecondary + ';\n' +
      '}\n\n' +
      '/* dark mode injector. material flat + glass. google would be proud-ish */\n' +
      '[data-dark-mode="true"] body {\n' +
      '  background-color: ' + theme.bg + ' !important;\n' +
      '  background-image: \n' +
      '    radial-gradient(ellipse at 80% 10%, ' + (theme.bodyGlow1 || 'rgba(99,102,241,0.10)') + ', transparent 55%),\n' +
      '    radial-gradient(ellipse at 10% 90%, ' + (theme.bodyGlow2 || 'rgba(168,85,247,0.08)') + ', transparent 55%) !important;\n' +
      '  background-size: cover !important;\n' +
      '  background-attachment: fixed !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .settings-item-title,\n' +
      '[data-dark-mode="true"] .welcome-text,\n' +
      '[data-dark-mode="true"] h1,\n' +
      '[data-dark-mode="true"] h2,\n' +
      '[data-dark-mode="true"] h3,\n' +
      '[data-dark-mode="true"] h4,\n' +
      '[data-dark-mode="true"] th,\n' +
      '[data-dark-mode="true"] td:not(.friend-status),\n' +
      '[data-dark-mode="true"] .bento-title,\n' +
      '[data-dark-mode="true"] .profile-name,\n' +
      '[data-dark-mode="true"] .game-title,\n' +
      '[data-dark-mode="true"] .search-result-title,\n' +
      '[data-dark-mode="true"] .user-name,\n' +
      '[data-dark-mode="true"] .user-details h3,\n' +
      '[data-dark-mode="true"] .recent-games h4,\n' +
      '[data-dark-mode="true"] .friend-name,\n' +
      '[data-dark-mode="true"] .close,\n' +
      '[data-dark-mode="true"] .bold {\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] p,\n' +
      '[data-dark-mode="true"] .settings-item-desc,\n' +
      '[data-dark-mode="true"] .bento-desc,\n' +
      '[data-dark-mode="true"] .user-details p,\n' +
      '[data-dark-mode="true"] .play-time,\n' +
      '[data-dark-mode="true"] .quote-display {\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .notes-textarea {\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] ::placeholder {\n' +
      '  color: rgba(255, 255, 255, 0.35) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .ai-message {\n' +
      '  background-color: var(--theme-glass-tint) !important;\n' +
      '  border-color: var(--theme-glass-border) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .sidenav a {\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .sidenav a:hover {\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .logo-container img,\n' +
      '[data-dark-mode="true"] .settings-logo img {\n' +
      '  filter: none !important;\n' +
      '  opacity: 0.92 !important;\n' +
      '}\n' +
      '/* glass surfaces. elevation lies + backdrop blur */\n' +
      '[data-dark-mode="true"] .glass-container,\n' +
      '[data-dark-mode="true"] .bento-tile,\n' +
      '[data-dark-mode="true"] .tile,\n' +
      '[data-dark-mode="true"] .game-tile,\n' +
      '[data-dark-mode="true"] .quote,\n' +
      '[data-dark-mode="true"] .balance-card,\n' +
      '[data-dark-mode="true"] .card,\n' +
      '[data-dark-mode="true"] .glass-card,\n' +
      '[data-dark-mode="true"] .white-box,\n' +
      '[data-dark-mode="true"] .right-section,\n' +
      '[data-dark-mode="true"] .quick-play-section,\n' +
      '[data-dark-mode="true"] .nexus-box,\n' +
      '[data-dark-mode="true"] .nexus-2025-box,\n' +
      '[data-dark-mode="true"] .settings-item,\n' +
      '[data-dark-mode="true"] .top-bar,\n' +
      '[data-dark-mode="true"] .spotlight-card,\n' +
      '[data-dark-mode="true"] .user-profile,\n' +
      '[data-dark-mode="true"] .user-menu,\n' +
      '[data-dark-mode="true"] .connected-popup,\n' +
      '[data-dark-mode="true"] .recent-game:hover,\n' +
      '[data-dark-mode="true"] .friend-item,\n' +
      '[data-dark-mode="true"] .chat-container {\n' +
      '  background: var(--theme-glass-tint) !important;\n' +
      '  border-color: var(--theme-glass-border) !important;\n' +
      '  backdrop-filter: blur(24px) saturate(120%) !important;\n' +
      '  -webkit-backdrop-filter: blur(24px) saturate(120%) !important;\n' +
      '  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 4px 12px rgba(0, 0, 0, 0.18) !important;\n' +
      '}\n' +
      '/* hover states. tonal ripple. no unhinged neon glow */\n' +
      '[data-dark-mode="true"] .glass-card:hover,\n' +
      '[data-dark-mode="true"] .white-box:hover,\n' +
      '[data-dark-mode="true"] .right-section:hover,\n' +
      '[data-dark-mode="true"] .quick-play-section:hover,\n' +
      '[data-dark-mode="true"] .nexus-box:hover,\n' +
      '[data-dark-mode="true"] .nexus-2025-box:hover,\n' +
      '[data-dark-mode="true"] .bento-tile:hover,\n' +
      '[data-dark-mode="true"] .settings-item:hover {\n' +
      '  background: rgba(255, 255, 255, 0.09) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.14) !important;\n' +
      '  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.28), 0 6px 20px rgba(0, 0, 0, 0.16) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-tile:hover {\n' +
      '  background: rgba(255, 255, 255, 0.09) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.14) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .category-btn {\n' +
      '  background: rgba(255, 255, 255, 0.04) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .category-btn:hover {\n' +
      '  background: rgba(255, 255, 255, 0.08) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .category-btn.active {\n' +
      '  background: var(--theme-accent) !important;\n' +
      '  color: white !important;\n' +
      '  border-color: transparent !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] #mySearch {\n' +
      '  background: rgba(255, 255, 255, 0.06) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .spotlight-search-icon,\n' +
      '[data-dark-mode="true"] .spotlight-input-container span.close {\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .search-result-category {\n' +
      '  background-color: rgba(255, 255, 255, 0.05) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '}\n' +
      '/* nav bar. flat glass. dp2 elevation. we read one material design blog once */\n' +
      '[data-dark-mode="true"] .sidenav,\n' +
      '[data-dark-mode="true"] .taskbar,\n' +
      '[data-dark-mode="true"] .dock,\n' +
      '[data-dark-mode="true"] .bottom-nav {\n' +
      '  background: var(--theme-glass-tint) !important;\n' +
      '  border-color: var(--theme-glass-border) !important;\n' +
      '  border-top-color: rgba(255, 255, 255, 0.06) !important;\n' +
      '  backdrop-filter: blur(28px) saturate(120%) !important;\n' +
      '  -webkit-backdrop-filter: blur(28px) saturate(120%) !important;\n' +
      '  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.35) !important;\n' +
      '}\n' +
      '/* kill nav pseudo reflections. they were giving 2012 */\n' +
      '[data-dark-mode="true"] .bottom-nav::before,\n' +
      '[data-dark-mode="true"] .bottom-nav::after {\n' +
      '  display: none !important;\n' +
      '  background: none !important;\n' +
      '  opacity: 0 !important;\n' +
      '}\n' +
      '/* nav buttons. beveled-out. active state hits different */\n' +
      '[data-dark-mode="true"] .nav-btn,\n' +
      '[data-dark-mode="true"] .mobile .nav-btn {\n' +
      '  background: linear-gradient(180deg, rgba(255,255,255,0.07) 0%, rgba(255,255,255,0.02) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.1),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.32),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.05),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.18),\n' +
      '    0 1px 3px rgba(0,0,0,0.18) !important;\n' +
      '  filter: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn:hover,\n' +
      '[data-dark-mode="true"] .mobile .nav-btn:hover {\n' +
      '  background: linear-gradient(180deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.04) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.14),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.28),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.07),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.15),\n' +
      '    0 2px 5px rgba(0,0,0,0.22) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '  filter: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .connected-sidebar-btn.sidebar-open {\n' +
      '  background: linear-gradient(180deg, rgba(162,89,247,0.28) 0%, rgba(162,89,247,0.14) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '}\n' +
      '/* strip nav button reflections. chrome era is OVER */\n' +
      '[data-dark-mode="true"] .nav-btn::before,\n' +
      '[data-dark-mode="true"] .nav-btn::after,\n' +
      '[data-dark-mode="true"] .mobile .nav-btn::before,\n' +
      '[data-dark-mode="true"] .mobile .nav-btn::after {\n' +
      '  display: none !important;\n' +
      '  opacity: 0 !important;\n' +
      '  background: none !important;\n' +
      '}\n' +
      '/* active nav. tonal fill. accent color. subtle bevel. chef kiss */\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="orange"] {\n' +
      '  background: linear-gradient(180deg, rgba(247,151,30,0.22) 0%, rgba(247,151,30,0.12) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #f7971e !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="purple"] {\n' +
      '  background: linear-gradient(180deg, rgba(162,89,247,0.22) 0%, rgba(162,89,247,0.12) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #a259f7 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="red"] {\n' +
      '  background: linear-gradient(180deg, rgba(255,107,129,0.22) 0%, rgba(255,107,129,0.12) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #ff6b81 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="green"] {\n' +
      '  background: linear-gradient(180deg, rgba(76,175,80,0.22) 0%, rgba(76,175,80,0.12) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #4caf50 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active:not([data-glow]) {\n' +
      '  background: linear-gradient(180deg, rgba(99,102,241,0.22) 0%, rgba(99,102,241,0.12) 100%) !important;\n' +
      '  border: none !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: var(--theme-accent) !important;\n' +
      '}\n' +
      '/* nav icons white in dark mode. contrast or death */\n' +
      '[data-dark-mode="true"] .nav-btn-icon {\n' +
      '  filter: brightness(0) invert(1) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active .nav-btn-icon {\n' +
      '  filter: brightness(0) invert(1) !important;\n' +
      '  opacity: 1 !important;\n' +
      '}\n' +
      '/* beat app.html active bevel on mobile + desktop. specificity war */\n' +
      '[data-dark-mode="true"] .nav-btn.active:not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active:not(:active) {\n' +
      '  transform: translateY(0) !important;\n' +
      '  border: none !important;\n' +
      '  font-weight: 600 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="orange"]:not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active[data-glow="orange"]:not(:active) {\n' +
      '  background: linear-gradient(180deg, rgba(247,151,30,0.22) 0%, rgba(247,151,30,0.12) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #f7971e !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="purple"]:not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active[data-glow="purple"]:not(:active) {\n' +
      '  background: linear-gradient(180deg, rgba(162,89,247,0.22) 0%, rgba(162,89,247,0.12) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #a259f7 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="red"]:not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active[data-glow="red"]:not(:active) {\n' +
      '  background: linear-gradient(180deg, rgba(255,107,129,0.22) 0%, rgba(255,107,129,0.12) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #ff6b81 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active[data-glow="green"]:not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active[data-glow="green"]:not(:active) {\n' +
      '  background: linear-gradient(180deg, rgba(76,175,80,0.22) 0%, rgba(76,175,80,0.12) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: #4caf50 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .nav-btn.active:not([data-glow]):not(:active),\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active:not([data-glow]):not(:active) {\n' +
      '  background: linear-gradient(180deg, rgba(99,102,241,0.22) 0%, rgba(99,102,241,0.12) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.12),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.3),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.06),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.2),\n' +
      '    0 1px 4px rgba(0,0,0,0.2) !important;\n' +
      '  color: var(--theme-accent) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .mobile .nav-btn:hover,\n' +
      '[data-dark-mode="true"] .mobile .nav-btn.active:not(:active):hover {\n' +
      '  transform: translateY(0) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .mobile .nav-btn:active,\n' +
      '[data-dark-mode="true"] .nav-btn:active {\n' +
      '  transform: translateY(1px) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 2px 4px rgba(0,0,0,0.35),\n' +
      '    inset 0 1px 0 rgba(0,0,0,0.2),\n' +
      '    inset 0 -1px 0 rgba(255,255,255,0.04) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .connected-sidebar-btn,\n' +
      '[data-dark-mode="true"] .mobile .connected-sidebar-btn {\n' +
      '  background: linear-gradient(180deg, rgba(255,255,255,0.07) 0%, rgba(255,255,255,0.02) 100%) !important;\n' +
      '  box-shadow:\n' +
      '    inset 0 1px 0 rgba(255,255,255,0.1),\n' +
      '    inset 0 -1px 0 rgba(0,0,0,0.32),\n' +
      '    inset 1px 0 0 rgba(255,255,255,0.05),\n' +
      '    inset -1px 0 0 rgba(0,0,0,0.18),\n' +
      '    0 1px 3px rgba(0,0,0,0.18) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] input[type="text"],\n' +
      '[data-dark-mode="true"] input[type="password"] {\n' +
      '  background: rgba(255, 255, 255, 0.06) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.12) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] input[type="text"]:focus,\n' +
      '[data-dark-mode="true"] input[type="password"]:focus {\n' +
      '  background: rgba(255, 255, 255, 0.08) !important;\n' +
      '  border-color: var(--theme-accent) !important;\n' +
      '  box-shadow: none !important;\n' +
      '  outline: none !important;\n' +
      '}\n' +
      '/* settings popups. flat glass sheet. ios cosplay */\n' +
      '[data-dark-mode="true"] #aboutPopup,\n' +
      '[data-dark-mode="true"] #accountPopup,\n' +
      '[data-dark-mode="true"] #themePopup,\n' +
      '[data-dark-mode="true"] #notificationsPopup,\n' +
      '[data-dark-mode="true"] #privacyPopup {\n' +
      '  background: rgba(18, 18, 22, 0.88) !important;\n' +
      '  border-color: var(--theme-glass-border) !important;\n' +
      '  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5), 0 2px 8px rgba(0, 0, 0, 0.3) !important;\n' +
      '  backdrop-filter: blur(28px) saturate(120%) !important;\n' +
      '  -webkit-backdrop-filter: blur(28px) saturate(120%) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .settings-popup-title {\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '  border-bottom-color: var(--theme-glass-border) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .theme-square-btn {\n' +
      '  background: rgba(255, 255, 255, 0.05) !important;\n' +
      '  color: var(--theme-text-secondary) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .theme-square-btn:hover {\n' +
      '  background: rgba(255, 255, 255, 0.09) !important;\n' +
      '  color: var(--theme-text-primary) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .theme-square-btn.selected {\n' +
      '  background: rgba(99, 102, 241, 0.18) !important;\n' +
      '  border-color: var(--theme-accent) !important;\n' +
      '  color: var(--theme-accent) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .theme-cancel {\n' +
      '  background: rgba(255, 255, 255, 0.06) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .theme-cancel:hover {\n' +
      '  background: rgba(255, 255, 255, 0.10) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .custom-file-label {\n' +
      '  background: rgba(255, 255, 255, 0.06) !important;\n' +
      '  color: var(--theme-accent) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.12) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .custom-file-label:hover {\n' +
      '  background: rgba(99, 102, 241, 0.12) !important;\n' +
      '  border-color: var(--theme-accent) !important;\n' +
      '}\n' +
      '/* toggle switch. flat accent. no glow. we contain ourselves */\n' +
      '[data-dark-mode="true"] .toggle-switch.active {\n' +
      '  background: var(--theme-accent) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .selected-indicator {\n' +
      '  color: var(--theme-accent) !important;\n' +
      '  background: var(--theme-text-accent-bg) !important;\n' +
      '}\n' +
      '/* nav tabs. flat gradient. no shadow. minimalist king */\n' +
      '[data-dark-mode="true"] .nav-tab.active {\n' +
      '  background: var(--theme-gradient) !important;\n' +
      '  box-shadow: none !important;\n' +
      '  color: #ffffff !important;\n' +
      '}\n' +
      '/* scrollbar. thin. flat. barely there */\n' +
      '[data-dark-mode="true"] ::-webkit-scrollbar {\n' +
      '  width: 4px !important;\n' +
      '  height: 4px !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] ::-webkit-scrollbar-track {\n' +
      '  background: transparent !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] ::-webkit-scrollbar-thumb {\n' +
      '  background: rgba(255, 255, 255, 0.18) !important;\n' +
      '  border-radius: 2px !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] ::-webkit-scrollbar-thumb:hover {\n' +
      '  background: rgba(255, 255, 255, 0.28) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .loading-spinner {\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  border-left-color: var(--theme-accent) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .api-key-warning {\n' +
      '  background-color: rgba(239, 68, 68, 0.12) !important;\n' +
      '  color: #fca5a5 !important;\n' +
      '  border: 1px solid rgba(239, 68, 68, 0.22) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '/* color swatches. no neon glow in dark mode. save it for synthwave */\n' +
      '[data-dark-mode="true"] .color-option {\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '/* moderngame portal dark overrides. games page at midnight hits */\n' +
      '[data-dark-mode="true"] .sticky-header .top-bar {\n' +
      '  background: var(--theme-glass-tint) !important;\n' +
      '  box-shadow: 0 1px 0 rgba(255,255,255,0.06), 0 2px 12px rgba(0,0,0,0.35) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .sticky-header .top-bar::after {\n' +
      '  display: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .sticky-header .top-bar::before {\n' +
      '  display: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .account-pill {\n' +
      '  background: var(--theme-accent) !important;\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .account-pill:hover {\n' +
      '  box-shadow: none !important;\n' +
      '  opacity: 0.88 !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-controls-bar {\n' +
      '  background: rgba(15, 15, 20, 0.72) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  box-shadow: 0 2px 12px rgba(0,0,0,0.4) !important;\n' +
      '  backdrop-filter: blur(20px) saturate(110%) !important;\n' +
      '  -webkit-backdrop-filter: blur(20px) saturate(110%) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-control-btn.btn-leave:hover {\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-control-btn.btn-invite:hover {\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-control-btn.btn-settings:hover {\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .game-control-btn {\n' +
      '  box-shadow: none !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .glass-popup {\n' +
      '  background: rgba(14, 18, 26, 0.88) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.08) !important;\n' +
      '  box-shadow: 0 4px 24px rgba(0,0,0,0.45) !important;\n' +
      '  backdrop-filter: blur(28px) saturate(110%) !important;\n' +
      '  -webkit-backdrop-filter: blur(28px) saturate(110%) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] .glass-alert {\n' +
      '  background: rgba(20, 24, 32, 0.88) !important;\n' +
      '  border-color: rgba(255, 255, 255, 0.1) !important;\n' +
      '  box-shadow: 0 4px 16px rgba(0,0,0,0.4) !important;\n' +
      '}\n' +
      '[data-dark-mode="true"] #mySearch:focus {\n' +
      '  box-shadow: none !important;\n' +
      '  border-color: var(--theme-accent) !important;\n' +
      '}\n';

    cssText += gameTileFrostCss();

    if (!styleElement) {
      styleElement = document.createElement('style');
      styleElement.id = 'theme-sync-stylesheet';
      document.head.appendChild(styleElement);
    }
    styleElement.textContent = cssText;
    
    // smear dark mode attrs on html/body. gently but firmly
    document.documentElement.setAttribute('data-theme', themeName);
    document.documentElement.setAttribute('data-dark-mode', isDark ? 'true' : 'false');
    if (document.body) {
      document.body.setAttribute('data-nexus-theme', themeName);
      document.body.setAttribute('data-dark-mode', isDark ? 'true' : 'false');
    } else {
      document.addEventListener('DOMContentLoaded', function () {
        if (document.body) {
          document.body.setAttribute('data-nexus-theme', themeName);
          document.body.setAttribute('data-dark-mode', isDark ? 'true' : 'false');
        }
      });
    }

    // recurse into iframes. theme sync is a family affair
    try {
      var iframes = document.getElementsByTagName('iframe');
      for (var i = 0; i < iframes.length; i++) {
        var iframe = iframes[i];
        if (iframe.contentWindow && iframe.contentWindow.NexusApplyTheme) {
          iframe.contentWindow.NexusApplyTheme(themeName);
        }
      }
    } catch (e) {
      console.log('Error propagating theme to children iframes:', e);
    }

    // tell parent window too. nested iframe vibes
    if (window.parent && window.parent !== window) {
      try {
        if (window.parent.NexusApplyTheme) {
          var parentDark = window.parent.document.documentElement.getAttribute('data-dark-mode');
          var parentTheme = window.parent.document.documentElement.getAttribute('data-theme');
          if (parentDark !== (isDark ? 'true' : 'false') || parentTheme !== themeName) {
            window.parent.NexusApplyTheme(themeName);
          }
        }
      } catch (e) {
        console.log('Error propagating theme to parent:', e);
      }
    }

    if (window.NexusVideoBg && window.NexusVideoBg.refresh) {
      window.NexusVideoBg.refresh();
    }
  }

  // boot themes on load. may your accent color be ever correct
  var activeTheme = localStorage.getItem('nexus_theme') || 'default';
  applyTheme(activeTheme);

  // global export. hello from theme-sync.js. i inject css like a gremlin
  window.NexusApplyTheme = applyTheme;

  // storage events from other frames. sync or perish
  window.addEventListener('storage', function(e) {
    if (e.key === 'nexus_theme' || e.key === 'nexus_dark_mode' || e.key === 'nexus_dark_theme') {
      applyTheme(localStorage.getItem('nexus_theme') || 'default');
    }
    if (e.key === 'nexus_perf_tier') {
      applyTheme(localStorage.getItem('nexus_theme') || 'default');
    }
  });

  window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'nexus-perf-tier') {
      applyTheme(localStorage.getItem('nexus_theme') || 'default');
    }
  });
})();
