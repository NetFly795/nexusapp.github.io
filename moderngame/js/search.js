// search.js aka "find the game among 400 tiles or perish"
// chatgpt wrote the original comment block. i replaced it with truth.

function startSearch() {
  var input, filter, searchResults, gameLinks, i, gameTitle, gameIcon, gameHref, gameCategory;
  input = document.getElementById("mySearch");
  filter = input.value.toUpperCase();
  searchResults = document.getElementById("searchResults");
  
  // wipe the dropdown. fresh slate. no evidence of prior searches
  searchResults.innerHTML = "";
  
  // If search is empty, hide dropdown
  if (filter.length === 0) {
    searchResults.style.display = "none";
    return;
  }
  
  // Get all game links
  gameLinks = document.querySelectorAll('.game-link');
  var hasResults = false;
  
  // Search through games
  for (i = 0; i < gameLinks.length; i++) {
    gameTitle = gameLinks[i].getElementsByTagName("h1")[0];
    gameIcon = gameLinks[i].getElementsByTagName("img")[0];
    gameHref = gameLinks[i].getAttribute("href");
    gameCategory = gameLinks[i].getAttribute("data-category");
    
    if (gameTitle.innerHTML.toUpperCase().indexOf(filter) > -1) {
      hasResults = true;
      
      // Create icon
      var icon = document.createElement("img");
      icon.className = "search-result-icon";
      icon.src = gameIcon.src;
      icon.alt = "Game icon";
      
      // Create dropdown item
      var resultItem = document.createElement("div");
      resultItem.className = "search-result-item";
      resultItem.onclick = function(href, title, iconElement) {
        return function() {
          // Close search dropdown
          searchResults.style.display = 'none';
          
          // Store the icon element for animation
          window.lastSearchIcon = iconElement;
          window.lastOpenedFromSearch = true;
          
          // Open game with animation from search icon
          window.NexusOverlayOpenFromElement(iconElement, href, title, iconElement.src);
        };
      }(gameHref, gameTitle.textContent.trim(), icon);
      
      // Create title
      var title = document.createElement("div");
      title.className = "search-result-title";
      title.textContent = gameTitle.textContent.trim();
      
      // Create category badge
      var categoryBadge = document.createElement("span");
      categoryBadge.className = "search-result-category";
      categoryBadge.textContent = gameCategory;
      
      // Assemble the result item
      resultItem.appendChild(icon);
      resultItem.appendChild(title);
      resultItem.appendChild(categoryBadge);
      
      searchResults.appendChild(resultItem);
    }
  }
  
  // Show or hide dropdown based on results
  if (hasResults) {
    searchResults.style.display = "block";
  } else {
    searchResults.style.display = "none";
  }
}

// Category filtering function
function filterByCategory(category) {
  // Update active button
  var buttons = document.querySelectorAll('.category-btn');
  buttons.forEach(function(btn) {
    btn.classList.remove('active');
  });
  event.target.classList.add('active');
  
  // Get all game links (exclude Luna recommendation cards)
  var gameLinks = document.querySelectorAll('.game-link:not([data-luna-rec-card])');
  
  // Filter games based on category
  gameLinks.forEach(function(gameLink) {
    var gameCategory = gameLink.getAttribute('data-category');
    
    if (category === 'all' || gameCategory === category) {
      gameLink.style.display = '';
    } else {
      gameLink.style.display = 'none';
    }
  });

  document.querySelectorAll('[data-luna-rec]').forEach(function(strip) {
    var cards = strip.querySelectorAll('.game-link[data-luna-rec-card]');
    var visibleCards = 0;
    cards.forEach(function(card) {
      var gameCategory = card.getAttribute('data-category');
      if (category === 'all' || gameCategory === category) {
        card.style.display = '';
        visibleCards++;
      } else {
        card.style.display = 'none';
      }
    });
    strip.style.display = visibleCards ? '' : 'none';
  });
}

// Toggle user menu
function toggleUserMenu() {
  console.log('toggleUserMenu called');
  
  // Always show gameembed.php
  showGameEmbedPopup();
}

// Show Connected Center popup
function showConnectedPopup() {
  console.log('showConnectedPopup called');
  var popup = document.getElementById('connectedPopup');
  var content = document.getElementById('connectedContent');
  var backdrop = document.getElementById('popupBackdrop');
  
  console.log('Popup element:', popup);
  console.log('Content element:', content);
  
  if (!popup || !content) {
    console.error('Popup or content element not found');
    return;
  }
  
  // Check if we're on the oceanos domain
  var isOceanosDomain = window.location.hostname === 'www.getoceanos.org' && window.location.pathname.includes('/newbankpage/');
  
  if (isOceanosDomain) {
    // Load the oceanos embed content
    content.innerHTML = '<iframe id="connectedEmbedFrame" src="https://www.getoceanos.org/newbankpage/embed.php" width="100%" height="100%" frameborder="0" style="border-radius: 15px;"></iframe>';
  } else {
    // Load the local embed content
    content.innerHTML = '<iframe id="connectedEmbedFrame" src="../newbankpage/embed.php" width="100%" height="100%" frameborder="0" style="border-radius: 15px;"></iframe>';
  }
  
  if (backdrop) {
    backdrop.style.display = 'block';
    // Force reflow
    backdrop.offsetHeight;
    backdrop.classList.add('active');
  }
  
  popup.style.display = 'block';
  // Force reflow
  popup.offsetHeight;
  popup.classList.add('active');
  console.log('Popup should be visible now');
  
  // Add iframe load listener to handle redirects
  var iframe = document.getElementById('connectedEmbedFrame');
  iframe.onload = function() {
    try {
      var iframeUrl = iframe.contentWindow.location.href;
      if (iframeUrl.includes('getoceanos.org/newbankpage/done.html')) {
        // Redirect to gameembed.php
        iframe.src = isOceanosDomain ? 'https://www.getoceanos.org/newbankpage/gameembed.php' : 'https://www.getoceanos.org/newbankpage/gameembed.php';
      }
    } catch (e) {
      // Cross-origin error, ignore
    }
  };
}

// Show Game Embed popup
function showGameEmbedPopup() {
  console.log('showGameEmbedPopup called');
  var popup = document.getElementById('connectedPopup');
  var content = document.getElementById('connectedContent');
  var backdrop = document.getElementById('popupBackdrop');
  
  console.log('Popup element:', popup);
  console.log('Content element:', content);
  
  if (!popup || !content) {
    console.error('Popup or content element not found');
    return;
  }
  
  // Check if we're on the oceanos domain
  var isOceanosDomain = window.location.hostname === 'www.getoceanos.org' && window.location.pathname.includes('/newbankpage/');
  
  if (isOceanosDomain) {
    // Load the oceanos gameembed content
    content.innerHTML = '<iframe id="gameEmbedFrame" src="https://www.getoceanos.org/newbankpage/gameembed.php" width="100%" height="100%" frameborder="0" style="border-radius: 28px;"></iframe>';
  } else {
    // Load the local gameembed content
    content.innerHTML = '<iframe id="gameEmbedFrame" src="https://www.getoceanos.org/newbankpage/gameembed.php" width="100%" height="100%" frameborder="0" style="border-radius: 28px;"></iframe>';
  }
  
  if (backdrop) {
    backdrop.style.display = 'block';
    // Force reflow
    backdrop.offsetHeight;
    backdrop.classList.add('active');
  }
  
  popup.style.display = 'block';
  // Force reflow
  popup.offsetHeight;
  popup.classList.add('active');
  console.log('Popup should be visible now');
  
  // Add iframe load listener to handle redirects
  var iframe = document.getElementById('gameEmbedFrame');
  iframe.onload = function() {
    try {
      var iframeUrl = iframe.contentWindow.location.href;
      if (iframeUrl.includes('getoceanos.org/newbankpage/done.html')) {
        // Redirect to gameembed.php
        iframe.src = isOceanosDomain ? 'https://www.getoceanos.org/newbankpage/gameembed.php' : 'https://www.getoceanos.org/newbankpage/gameembed.php';
      }
    } catch (e) {
      // Cross-origin error, ignore
    }
  };
}

// Update user display name
function updateUserDisplayName(username) {
  var userNameElement = document.getElementById('userName');
  if (userNameElement) {
    userNameElement.textContent = username;
  }
}

// Close Connected Center popup
function closeConnectedPopup() {
  var popup = document.getElementById('connectedPopup');
  var backdrop = document.getElementById('popupBackdrop');
  
  if (popup) {
    popup.classList.remove('active');
    setTimeout(function() {
      popup.style.display = 'none';
    }, 400);
  }
  
  if (backdrop) {
    backdrop.classList.remove('active');
    setTimeout(function() {
      backdrop.style.display = 'none';
    }, 400);
  }
}

// Close Connected Center modal
function closeConnectedModal() {
  var modal = document.getElementById('connectedModal');
  modal.style.display = 'none';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  var searchContainer = document.querySelector('.search-container');
  var searchResults = document.getElementById('searchResults');
  var userAccount = document.querySelector('.user-account');
  var userMenu = document.getElementById('userMenu');
  var connectedModal = document.getElementById('connectedModal');
  var connectedPopup = document.getElementById('connectedPopup');
  
  if (!searchContainer.contains(event.target)) {
    searchResults.style.display = 'none';
  }
  
  if (!userAccount.contains(event.target) && (!connectedPopup || !connectedPopup.contains(event.target))) {
    userMenu.style.display = 'none';
    if (connectedPopup && connectedPopup.classList.contains('active')) {
      closeConnectedPopup();
    }
  }
  
  // Close modal when clicking outside
  if (event.target === connectedModal) {
    closeConnectedModal();
  }
});

// Function to save recent game to browser storage
function saveRecentGame(gameName, gameImage, gameCategory) {
  var recentGames = JSON.parse(localStorage.getItem('recentOnlineMultiplayerGames') || '[]');
  
  // Remove if already exists
  recentGames = recentGames.filter(game => game.name !== gameName);
  
  // Add to beginning
  recentGames.unshift({
    name: gameName,
    image: gameImage,
    category: gameCategory,
    playTime: 'Just now'
  });
  
  // Keep only last 3
  recentGames = recentGames.slice(0, 3);
  
  // Update play times
  recentGames.forEach((game, index) => {
    if (index === 0) {
      game.playTime = 'Just now';
    } else if (index === 1) {
      game.playTime = '1 hour ago';
    } else if (index === 2) {
      game.playTime = '2 hours ago';
    }
  });
  
  localStorage.setItem('recentOnlineMultiplayerGames', JSON.stringify(recentGames));
}

// Spotlight Search functions
function openSpotlight() {
  console.log('openSpotlight called');
  var backdrop = document.getElementById('spotlightBackdrop');
  var card = document.getElementById('spotlightCard');
  var input = document.getElementById('spotlightSearchInput');
  
  if (backdrop && card) {
    backdrop.style.display = 'block';
    card.style.display = 'block';
    
    // Force reflow
    backdrop.offsetHeight;
    card.offsetHeight;
    
    backdrop.classList.add('active');
    card.classList.add('active');
    
    if (input) {
      input.value = "";
      // Initialize with suggestions
      startSpotlightSearch();
      setTimeout(function() {
        input.focus();
      }, 100);
    }
  }
}

function closeSpotlight() {
  var backdrop = document.getElementById('spotlightBackdrop');
  var card = document.getElementById('spotlightCard');
  
  if (backdrop && card) {
    backdrop.classList.remove('active');
    card.classList.remove('active');
    
    setTimeout(function() {
      backdrop.style.display = 'none';
      card.style.display = 'none';
    }, 300);
  }
}

function startSpotlightSearch() {
  var input = document.getElementById("spotlightSearchInput");
  var filter = input.value.toUpperCase();
  var searchResults = document.getElementById("spotlightResults");
  
  searchResults.innerHTML = "";
  
  // If empty, show suggestions
  if (filter.length === 0) {
    var suggestionHeader = document.createElement("div");
    suggestionHeader.className = "spotlight-section-header";
    suggestionHeader.textContent = "Suggested Games";
    searchResults.appendChild(suggestionHeader);
    
    var gameLinks = document.querySelectorAll('.game-link');
    var count = 0;
    for (var i = 0; i < gameLinks.length && count < 3; i++) {
      addSpotlightItem(gameLinks[i]);
      count++;
    }
    return;
  }
  
  var gameLinks = document.querySelectorAll('.game-link');
  var hasResults = false;
  
  for (var i = 0; i < gameLinks.length; i++) {
    var gameTitle = gameLinks[i].getElementsByTagName("h1")[0];
    var gameCategory = gameLinks[i].getAttribute("data-category");
    
    if (gameTitle.innerHTML.toUpperCase().indexOf(filter) > -1 || gameCategory.toUpperCase().indexOf(filter) > -1) {
      hasResults = true;
      addSpotlightItem(gameLinks[i]);
    }
  }
  
  if (!hasResults) {
    var noResults = document.createElement("div");
    noResults.className = "spotlight-no-results";
    noResults.textContent = "No games found matching '" + input.value + "'";
    searchResults.appendChild(noResults);
  }
  
  function addSpotlightItem(gameLink) {
    var titleText = gameLink.getElementsByTagName("h1")[0].textContent.trim();
    var imgSrc = gameLink.getElementsByTagName("img")[0].src;
    var href = gameLink.getAttribute("href");
    var category = gameLink.getAttribute("data-category");
    
    var icon = document.createElement("img");
    icon.className = "search-result-icon";
    icon.src = imgSrc;
    icon.alt = "Game icon";
    
    var resultItem = document.createElement("div");
    resultItem.className = "search-result-item";
    resultItem.onclick = function() {
      closeSpotlight();
      window.lastSearchIcon = icon;
      window.lastOpenedFromSearch = true;
      window.NexusOverlayOpenFromElement(icon, href, titleText, imgSrc);
    };
    
    var title = document.createElement("div");
    title.className = "search-result-title";
    title.textContent = titleText;
    
    var categoryBadge = document.createElement("span");
    categoryBadge.className = "search-result-category";
    categoryBadge.textContent = category;
    
    resultItem.appendChild(icon);
    resultItem.appendChild(title);
    resultItem.appendChild(categoryBadge);
    
    searchResults.appendChild(resultItem);
  }
}

// Global key down listener for Escape key to close Spotlight
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeSpotlight();
  }
});
