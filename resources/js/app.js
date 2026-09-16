import './bootstrap';
import 'leaflet/dist/leaflet.css';
import 'leaflet';

import L from 'leaflet';
delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
});

document.addEventListener('DOMContentLoaded', function () {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    function openSidebar() {
        sidebar?.classList.remove('translate-x-full');
        sidebarOverlay?.classList.remove('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar?.classList.add('translate-x-full');
        sidebarOverlay?.classList.add('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    }

    sidebarToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userMenu = document.getElementById('user-menu');
    const userMenuContainer = document.getElementById('user-menu-container');

    if (userMenuToggle && userMenu) {
        userMenuToggle.addEventListener('click', function () {
            const isOpen = !userMenu.classList.contains('hidden');
            userMenu.classList.toggle('hidden');
            userMenuToggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        });

        document.addEventListener('click', function (event) {
            if (userMenuContainer && !userMenuContainer.contains(event.target)) {
                userMenu.classList.add('hidden');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                userMenu.classList.add('hidden');
                userMenuToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
