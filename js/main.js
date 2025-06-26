// js/main.js

// Namespace to avoid polluting the global scope
var CSITJobBoard = CSITJobBoard || {};

// Function to load external HTML into a placeholder
CSITJobBoard.loadHTML = function(url, elementId, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) { // DONE
            if (xhr.status === 200) {
                document.getElementById(elementId).innerHTML = xhr.responseText;
                if (typeof callback === 'function') {
                    callback();
                }
            } else {
                console.error('Could not load ' + url + ': ' + xhr.statusText);
            }
        }
    };
    xhr.send();
};

// Initialize all components
CSITJobBoard.init = function() {
    // Load Navbar
    CSITJobBoard.loadHTML('./navbar.html', 'navbar-placeholder', function() {
        // Initialize Navbar after loading
        if (CSITJobBoard.Navbar && typeof CSITJobBoard.Navbar.init === 'function') {
            CSITJobBoard.Navbar.init();
        }
    });

    // Load Filter Sidebar
    CSITJobBoard.loadHTML('./filter.html', 'filter-placeholder', function() {
        // Initialize Filter after loading
        if (CSITJobBoard.Filter && typeof CSITJobBoard.Filter.init === 'function') {
            CSITJobBoard.Filter.init();
        }
    });
};

// Initialize when DOM is fully loaded
document.addEventListener("DOMContentLoaded", CSITJobBoard.init);
