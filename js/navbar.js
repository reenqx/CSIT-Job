// Ensure the namespace exists
var CSITJobBoard = CSITJobBoard || {};

// Navbar Module
CSITJobBoard.Navbar = (function () {
    function toggleExpanded(event) {
        event.preventDefault();
        var expandedNav = document.getElementById('expandedNav');
        
        if (expandedNav) {
            // Toggle class for showing/hiding the expanded menu
            expandedNav.classList.toggle('show');

            // ตรวจสอบว่าเมนูถูกเปิดหรือปิด
            if (expandedNav.classList.contains('show')) {
                expandedNav.style.display = "grid";  // แสดงเมนู
            } else {
                expandedNav.style.display = "none"; // ซ่อนเมนู
            }
        }
    }

    return {
        init: function () {
            var otherNavItem = document.getElementById('otherNavItem');
            if (otherNavItem) {
                otherNavItem.addEventListener('click', toggleExpanded);
            }
        }
    };
})();

// Initialize the Navbar script
document.addEventListener("DOMContentLoaded", function () {
    CSITJobBoard.Navbar.init();
});
