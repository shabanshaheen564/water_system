import './bootstrap';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarClose = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    const closeSidebar = () => {
        sidebar?.classList.add('translate-x-full');
        sidebarOverlay?.classList.add('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'false');
    };

    const openSidebar = () => {
        sidebar?.classList.remove('translate-x-full');
        sidebarOverlay?.classList.remove('hidden');
        sidebarToggle?.setAttribute('aria-expanded', 'true');
    };

    sidebarToggle?.addEventListener('click', openSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    const userMenuToggle = document.getElementById('user-menu-toggle');
    const userMenu = document.getElementById('user-menu');
    const userMenuContainer = document.getElementById('user-menu-container');

    userMenuToggle?.addEventListener('click', () => {
        const open = userMenu?.classList.toggle('hidden') === false;
        userMenuToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', (event) => {
        if (userMenu && userMenuContainer && !userMenuContainer.contains(event.target)) {
            userMenu.classList.add('hidden');
            userMenuToggle?.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
            userMenu?.classList.add('hidden');
            userMenuToggle?.setAttribute('aria-expanded', 'false');
        }
    });
});

window.initLoginPage = function () {
    const form = document.getElementById('login-form');
    const password = document.getElementById('password');
    const toggle = document.getElementById('toggle-password');
    const openIcon = document.getElementById('eye-open');
    const closedIcon = document.getElementById('eye-closed');
    const button = document.getElementById('login-button');
    const text = document.getElementById('button-text');
    const spinner = document.getElementById('button-spinner');

    if (!form || !password || !toggle || !button) return;

    toggle.addEventListener('click', () => {
        const showing = password.type === 'password';
        password.type = showing ? 'text' : 'password';
        openIcon?.classList.toggle('hidden', showing);
        closedIcon?.classList.toggle('hidden', !showing);
        toggle.setAttribute('aria-pressed', showing ? 'true' : 'false');
        toggle.setAttribute('aria-label', showing ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
    });

    form.addEventListener('submit', () => {
        button.disabled = true;
        text?.classList.add('hidden');
        spinner?.classList.remove('hidden');
    });
};
