import './bootstrap';
import 'leaflet/dist/leaflet.css';
import 'leaflet';

// Fix Leaflet default icon paths
import L from 'leaflet';
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

// Global UI interactions
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for mobile
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        if (sidebar) {
            sidebar.classList.remove('-translate-x-full');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('hidden');
        }
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }
    }

    function closeSidebar() {
        if (sidebar) {
            sidebar.classList.add('-translate-x-full');
        }
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('hidden');
        }
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', openSidebar);
    }
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // User menu dropdown
    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userMenu = document.getElementById('user-menu');
    const userMenuContainer = document.getElementById('user-menu-container');

    if (userMenuToggle && userMenu) {
        userMenuToggle.addEventListener('click', function() {
            const isOpen = !userMenu.classList.contains('hidden');
            userMenu.classList.toggle('hidden');
            userMenuToggle.setAttribute('aria-expanded', !isOpen);
        });

        // Close on click outside
        document.addEventListener('click', function(e) {
            if (userMenuContainer && !userMenuContainer.contains(e.target)) {
                userMenu.classList.add('hidden');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });

        // Close on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !userMenu.classList.contains('hidden')) {
                userMenu.classList.add('hidden');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
});