const APPLET_SCREENSHOT = '../applets/Screenshot 2025-07-02 at 1.11.58\u202fPM.png';

const addons = [
    {
        id: 1,
        name: "Freedom Bank for Rubles",
        description: "A safe place to store your rubles with advanced security features.",
        version: "1.2.0",
        author: "Nexus Team",
        rating: 4.5
    },
    {
        id: 2,
        name: "For the Motherland Instant",
        description: "Instantly watch For the Motherland Socialist Publication with this applet.",
        version: "1.0.0",
        author: "For the Motherland",
    },
    {
        id: 3,
        name: "Nexus Tasks",
        description: "Keep track of your tasks and to-do lists with Nexus Tasks.",
        version: "1.0.0",
        author: "Nexus Team",
        rating: 4.8
    },
    {
        id: 4,
        name: "Nexus Sync2",
        description: "Synchronize Nexus across multiple devices seamlessly.",
        version: "2.0.1",
        author: "Nexus Team",
        rating: 4.8
    },
    {
        id: 5,
        name: "Nexus 3Sync",
        description: "Synchronize Nexus across multiple devices seamlessly.",
        version: "2.0.1",
        author: "Nexus Team",
        rating: 4.8
    },
];

const PINNED_KEY = 'nexus_pinned_applets';

function loadPinnedApplets() {
    try {
        const saved = localStorage.getItem(PINNED_KEY);
        if (saved) {
            const ids = JSON.parse(saved);
            // Only keep valid applets
            return addons.filter(a => ids.includes(a.id));
        }
    } catch {}
    return [addons[0]]; // Default
}

function savePinnedApplets() {
    const ids = pinnedApplets.map(a => a.id);
    localStorage.setItem(PINNED_KEY, JSON.stringify(ids));
}

const pinnedApplets = loadPinnedApplets();

function isPinned(addon) {
    return pinnedApplets.some(a => a.id === addon.id);
}

function pinApplet(addon) {
    if (!isPinned(addon)) {
        pinnedApplets.push(addon);
        savePinnedApplets();
        renderPinned();
    }
}

function unpinApplet(addon) {
    const idx = pinnedApplets.findIndex(a => a.id === addon.id);
    if (idx !== -1) {
        pinnedApplets.splice(idx, 1);
        savePinnedApplets();
        renderPinned();
    }
}

function renderPinned() {
    const pinnedList = document.getElementById('pinned-list');
    if (!pinnedList) return;
    pinnedList.innerHTML = "";
    pinnedApplets.forEach(addon => {
        const li = document.createElement('li');
        li.className = 'pinned-item';
        li.textContent = addon.name;
        li.onclick = () => showAddonDetails(addon);
        pinnedList.appendChild(li);
    });
}

function initializeStore() {
    const addonList = document.getElementById('addon-list');
    const searchInput = document.getElementById('addon-search');

    function renderList(filter = "") {
        addonList.innerHTML = "";
        addons
            .filter(addon => addon.name.toLowerCase().includes(filter.toLowerCase()))
            .forEach(addon => {
                const li = document.createElement('li');
                li.className = 'addon-item';
                li.textContent = addon.name;
                li.onclick = () => showAddonDetails(addon);
                addonList.appendChild(li);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const searchInput = document.getElementById('addon-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                renderList(e.target.value);
            });
            // Render full list on focus if empty
            searchInput.addEventListener('focus', (e) => {
                if (!searchInput.value) renderList('');
            });
        }
        renderList();
        renderPinned();
    });

    renderList();
    renderPinned();

    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            renderList(e.target.value);
        });
    }
}

function showAddonDetails(addon) {
    const detailsContainer = document.getElementById('addon-details');
    document.querySelectorAll('.addon-item').forEach(item => {
        item.classList.remove('selected');
        if(item.textContent === addon.name) {
            item.classList.add('selected');
        }
    });

    detailsContainer.classList.remove('slide-in');
    detailsContainer.classList.add('slide-out');

    setTimeout(() => {
        const pinBtnLabel = isPinned(addon) ? "Unpin" : "Pin";
        detailsContainer.innerHTML = `
            <h1>${addon.name}</h1>
            <p>Version: ${addon.version}</p>
            <p>Author: ${addon.author}</p>
            <p>${addon.description}</p>
            <div class="action-buttons">
                <button class="open-btn" onclick="openApplet()">Open</button>
                <button class="pin-btn" onclick="togglePin(${addon.id})">${pinBtnLabel}</button>
            </div>
            <div class="screenshot-section">
                <div>
                    <img src="${APPLET_SCREENSHOT}" alt="Screenshot of ${addon.name}" class="applet-screenshot"/>
                    <div class="screenshot-controls">
                        <button class="expand-btn" onclick="showScreenshotPopup()">Expand</button>
                    </div>
                </div>
            </div>
        `;
        // Force reflow to ensure the animation triggers
        void detailsContainer.offsetWidth;
        detailsContainer.classList.remove('slide-out');
        detailsContainer.classList.add('slide-in');
    }, 250);
}

// Open applet: collapse sidebar with animation
window.openApplet = function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    sidebar.classList.add('sidebar-collapsing');
    setTimeout(() => {
        sidebar.classList.add('sidebar-collapsed');
        sidebar.classList.remove('sidebar-collapsing');
    }, 350);
};

// Pin/unpin handler for button
window.togglePin = function(addonId) {
    const addon = addons.find(a => a.id === addonId);
    if (!addon) return;
    if (isPinned(addon)) {
        unpinApplet(addon);
    } else {
        pinApplet(addon);
    }
    showAddonDetails(addon);
};

// Pop-up logic
window.showScreenshotPopup = function() {
    let popup = document.createElement('div');
    popup.className = 'screenshot-popup-overlay';
    popup.innerHTML = `
        <div class="screenshot-popup">
            <button class="close-popup-btn" onclick="closeScreenshotPopup()">&times;</button>
            <img src="${APPLET_SCREENSHOT}" alt="Enlarged Screenshot" class="popup-screenshot-img"/>
        </div>
    `;
    document.body.appendChild(popup);
    setTimeout(() => popup.classList.add('visible'), 10);
};

window.closeScreenshotPopup = function() {
    const popup = document.querySelector('.screenshot-popup-overlay');
    if (popup) {
        popup.classList.remove('visible');
        setTimeout(() => popup.remove(), 200);
    }
};

document.addEventListener('DOMContentLoaded', initializeStore);
document.addEventListener('DOMContentLoaded', initializeStore);
document.addEventListener('DOMContentLoaded', initializeStore);
document.addEventListener('DOMContentLoaded', initializeStore);
