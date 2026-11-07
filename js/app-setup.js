document.addEventListener('DOMContentLoaded', function() {
  // Apply theme
  const currentTheme = localStorage.getItem('nexus_theme') || 'default';
  applyTheme(currentTheme);

  // Load default page if saved
  const defaultPage = localStorage.getItem('nexus_default_page');
  const pageNavMap = { home: 'home', games: 'games', social: 'connected', settings: 'settings' };
  if (defaultPage) {
    const navLabel = pageNavMap[defaultPage] || defaultPage;
    const navBtn = Array.from(document.querySelectorAll('.nav-btn')).find(btn => 
        btn.querySelector('.nav-btn-content').textContent.trim().toLowerCase() === navLabel
    );
    if (navBtn) {
      let url = navBtn.getAttribute('onclick').match(/'([^']+)'/)[1];
      changePage(url, navBtn);
    }
  }

  // Apply saved button order
  const savedOrder = JSON.parse(localStorage.getItem('nexus_button_order') || '[]');
  if (savedOrder.length) {
      const nav = getNavButtonRow();
      const buttons = Array.from(document.getElementById('bottomNav').querySelectorAll('.nav-btn'));
      savedOrder.forEach(page => {
          const button = buttons.find(btn => 
              btn.querySelector('.nav-btn-content').textContent.trim().toLowerCase() === page
          );
          if (button && nav) nav.insertBefore(button, nav.querySelector('#navReorderAnchor'));
      });
  }

  _syncFramePause();
  ['dash-preload-frame', 'content-frame'].forEach(function(id) {
    var f = document.getElementById(id);
    if (!f) return;
    f.addEventListener('load', function() {
      _syncFramePause();
      if (window.NexusPerf) NexusPerf.propagate(NexusPerf.isLite());
      if (window.NexusGlassQuality) NexusGlassQuality.propagate();
      if (window.NexusDebugRainbowBg) NexusDebugRainbowBg.propagate();
    });
  });
});

// Setup handling
var currentStep = 1;
var selectedTheme = 'default';

var SETUP_TOTAL_STEPS = 4;

function _updateSetupBtn() {
  const btn = document.querySelector('#setupPopup .setup-btn');
  if (!btn) return;
  btn.textContent = currentStep === 1 ? 'Go'
                  : currentStep === SETUP_TOTAL_STEPS ? 'Finish'
                  : 'Next';
}

function _doStepTransition(fromEl, toEl) {
  if (fromEl) {
    fromEl.classList.add('exiting');
    fromEl.classList.remove('active');
    fromEl.addEventListener('animationend', () => {
      fromEl.classList.remove('exiting');
    }, { once: true });
  }
  // Small stagger so the enter animation starts just as the exit peaks
  setTimeout(() => {
    if (toEl) toEl.classList.add('active');
  }, 70);
}

function showSetup() {
  const popup = document.getElementById('setupPopup');
  popup.style.display = 'flex';
  setTimeout(() => {
    popup.style.opacity = '1';
    document.getElementById('step1').classList.add('active');
    _updateSetupBtn();
  }, 50);
}

function selectSetupTheme(theme) {
  document.querySelectorAll('.theme-preview').forEach(p => p.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  selectedTheme = theme;
}

function handleSetupButton() {
  nextSetupStep();
}

function nextSetupStep() {
  const username = document.getElementById('setupUsername').value;

  if (currentStep === 2 && !username.trim()) {
    document.getElementById('setupUsername').focus();
    document.getElementById('setupUsername').style.borderColor = '#e53935';
    return;
  }

  // Last step: save & dismiss
  if (currentStep === SETUP_TOTAL_STEPS) {
    localStorage.setItem('nexus_username', username.trim());
    localStorage.setItem('nexus_theme', selectedTheme);
    localStorage.setItem('nexus_setup_complete', 'true');

    const popup = document.getElementById('setupPopup');
    popup.style.opacity = '0';
    setTimeout(() => {
      popup.style.display = 'none';
      // Trigger centre-burst on the dash that's been loading in the background
      sendInitialDashAnimation(true);
    }, 380);

    applyTheme(selectedTheme);
    return;
  }

  const fromEl = document.querySelector('.setup-step.active');
  currentStep++;
  const toEl = document.getElementById('step' + currentStep);
  _doStepTransition(fromEl, toEl);
  _updateSetupBtn();
}

// Bank integration removed. RIP bank page. you are missed by nobody

// Check if setup is needed
document.addEventListener('DOMContentLoaded', function() {
  if(!localStorage.getItem('nexus_setup_complete')) {
    showSetup();
  }
});

function setDefaultPage(page) {
    const pageNavMap = { home: 'home', games: 'games', social: 'connected', settings: 'settings' };
    // First remove all selected states
    document.querySelectorAll('.option-choice').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Find and select the correct button
    const selectedBtn = document.querySelector(`.option-choice[onclick*="'${page}'"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('selected');
    }
    
    // Save the preference
    localStorage.setItem('nexus_default_page', page);
    
    // Update the navigation
    const navBtn = Array.from(document.querySelectorAll('.nav-btn')).find(btn => 
        btn.querySelector('.nav-btn-content').textContent.trim().toLowerCase() === (pageNavMap[page] || page)
    );
    
    if (navBtn) {
        const url = navBtn.getAttribute('onclick').match(/'([^']+)'/)[1];
        changePage(url, navBtn);
    }
}

function toggleCustomizeMode() {
    let section = document.querySelector('.customize-section');
    const overlay = document.querySelector('.edit-overlay');
    
    if (!section) {
        // Create customize section if it doesn't exist
        section = document.createElement('div');
        section.className = 'customize-section';
        section.innerHTML = `
            <div class="option-group">
                <span class="option-label">Arrange Navigation Buttons</span>
                <div class="nav-preview" id="buttonList">
                    <div class="button-preview" draggable="true" data-page="home">
                        <img src="icons/home.png" alt="Home">Home
                    </div>
                    <div class="button-preview" draggable="true" data-page="games">
                        <img src="icons/games.png" alt="Games">Games
                    </div>
                    <div class="button-preview" draggable="true" data-page="social">
                        <img src="icons/social.png" alt="Social">Connected
                    </div>
                    <div class="button-preview" draggable="true" data-page="settings">
                        <img src="icons/preferences.png" alt="Settings">Settings
                    </div>
                </div>
            </div>
            <div class="option-group">
                <span class="option-label">Choose Default Page</span>
                <div class="option-choices">
                    <button class="option-choice selected" onclick="setDefaultPage('home')">Home</button>
                    <button class="option-choice" onclick="setDefaultPage('games')">Games</button>
                    <button class="option-choice" onclick="setDefaultPage('social')">Connected</button>
                    <button class="option-choice" onclick="setDefaultPage('settings')">Settings</button>
                </div>
            </div>
            <button class="done-button" onclick="toggleCustomizeMode()">Done</button>
        `;
        getNavButtonRow()?.appendChild(section);
        
        // Setup drag and drop
        const buttonList = section.querySelector('#buttonList');
        let draggedItem = null;
        
        buttonList.querySelectorAll('.button-preview').forEach(button => {
            button.addEventListener('dragstart', function(e) {
                draggedItem = this;
                this.classList.add('dragging');
            });
            
            button.addEventListener('dragend', function() {
                this.classList.remove('dragging');
            });
            
            button.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (this !== draggedItem) {
                    const rect = this.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    if (e.clientY < midY && this.previousElementSibling !== draggedItem) {
                        this.parentNode.insertBefore(draggedItem, this);
                    } else if (e.clientY >= midY && this.nextElementSibling !== draggedItem) {
                        this.parentNode.insertBefore(draggedItem, this.nextElementSibling);
                    }
                }
            });
        });
        
        buttonList.addEventListener('dragover', e => {
            e.preventDefault();
            buttonList.classList.add('drag-over');
        });
        
        buttonList.addEventListener('dragleave', () => {
            buttonList.classList.remove('drag-over');
        });
        
        buttonList.addEventListener('drop', e => {
            e.preventDefault();
            buttonList.classList.remove('drag-over');
            
            // Save new order
            const newOrder = Array.from(buttonList.children).map(btn => btn.dataset.page);
            localStorage.setItem('nexus_button_order', JSON.stringify(newOrder));
            
            // Reorder actual nav buttons
            const nav = getNavButtonRow();
            const buttons = Array.from(document.getElementById('bottomNav').querySelectorAll('.nav-btn'));
            const orderedButtons = newOrder.map(page => 
                buttons.find(btn => btn.querySelector('.nav-btn-content').textContent.trim().toLowerCase() === page)
            );
            
            orderedButtons.forEach(btn => {
                if (btn && nav) nav.insertBefore(btn, nav.querySelector('#navReorderAnchor'));
            });
        });
        
        // Set correct states
        const defaultPage = localStorage.getItem('nexus_default_page') || 'home';
        section.querySelector(`button[onclick="setDefaultPage('${defaultPage}')"]`)?.classList.add('selected');
        
        // Load saved button order
        const savedOrder = JSON.parse(localStorage.getItem('nexus_button_order') || '[]');
        if (savedOrder.length) {
            const list = section.querySelector('#buttonList');
            savedOrder.forEach(page => {
                const button = list.querySelector(`[data-page="${page}"]`);
                if (button) list.appendChild(button);
            });
        }
        
        requestAnimationFrame(() => {
            section.classList.add('show');
            overlay.classList.add('show');
        });
    } else {
        section.classList.remove('show');
        overlay.classList.remove('show');
        setTimeout(() => section.remove(), 300);
    }
}

// Initialize button order on page load
document.addEventListener('DOMContentLoaded', function() {
    // Apply saved button order
    const savedOrder = JSON.parse(localStorage.getItem('nexus_button_order') || '[]');
    if (savedOrder.length) {
        const nav = getNavButtonRow();
        const buttons = Array.from(document.getElementById('bottomNav').querySelectorAll('.nav-btn'));
        savedOrder.forEach(page => {
            const button = buttons.find(btn => 
                btn.querySelector('.nav-btn-content').textContent.trim().toLowerCase() === page
            );
            if (button && nav) nav.insertBefore(button, nav.querySelector('#navReorderAnchor'));
        });
    }
});

// Cookie consent handling
function showCookiePopup() {
    if (!localStorage.getItem('cookie_consent')) {
        setTimeout(() => {
            document.getElementById('cookiePopup').classList.add('show');
        }, 1000);
    }
}

function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookiePopup').classList.remove('show');
}

function declineCookies() {
    localStorage.setItem('cookie_consent', 'declined');
    document.getElementById('cookiePopup').classList.remove('show');
}

// Check cookie consent on load
document.addEventListener('DOMContentLoaded', function() {
    showCookiePopup();
    // ...rest of existing DOMContentLoaded code...
});

// Add mouse tracking for lighting effects (throttled for performance)
var mouseThrottle = false;
document.addEventListener('mousemove', (e) => {
    if (mouseThrottle) return;
    mouseThrottle = true;
    
    requestAnimationFrame(() => {
        const x = e.clientX / window.innerWidth;
        const y = e.clientY / window.innerHeight;
        document.documentElement.style.setProperty('--mouse-x', x);
        document.documentElement.style.setProperty('--mouse-y', y);
        mouseThrottle = false;
    });
});

// Initialize mouse position
document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.style.setProperty('--mouse-x', '0.5');
    document.documentElement.style.setProperty('--mouse-y', '0.5');

    // Fallback if dash-ready was missed (e.g. message sent before listener existed)
    setTimeout(() => sendInitialDashAnimation(), 800);
});

window.showSetup = showSetup;
window.selectSetupTheme = selectSetupTheme;
window.handleSetupButton = handleSetupButton;
window.nextSetupStep = nextSetupStep;
window.setDefaultPage = setDefaultPage;
window.toggleCustomizeMode = toggleCustomizeMode;
window.acceptCookies = acceptCookies;
window.declineCookies = declineCookies;
window.showCookiePopup = showCookiePopup;

