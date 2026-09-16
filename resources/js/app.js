import './bootstrap';
import 'leaflet/dist/leaflet.css';
import L from 'leaflet';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({ iconRetinaUrl: markerIcon2x, iconUrl: markerIcon, shadowUrl: markerShadow });

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initUserMenu();
    initLoginPage();
    initSpatialDatasetForm();
});

function initSidebar() {
    const toggle = document.getElementById('sidebar-toggle');
    const close = document.getElementById('sidebar-close');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (!sidebar) return;
    const closeSidebar = () => {
        sidebar.classList.add('translate-x-full');
        overlay?.classList.add('hidden');
        toggle?.setAttribute('aria-expanded', 'false');
    };
    const openSidebar = () => {
        sidebar.classList.remove('translate-x-full');
        overlay?.classList.remove('hidden');
        toggle?.setAttribute('aria-expanded', 'true');
    };
    toggle?.addEventListener('click', openSidebar);
    close?.addEventListener('click', closeSidebar);
    overlay?.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeSidebar();
    });
}

function initUserMenu() {
    const toggle = document.getElementById('user-menu-toggle');
    const menu = document.getElementById('user-menu');
    const container = document.getElementById('user-menu-container');
    if (!toggle || !menu || !container) return;
    toggle.addEventListener('click', () => {
        const isOpen = !menu.classList.contains('hidden');
        menu.classList.toggle('hidden', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
    });
    document.addEventListener('click', (event) => {
        if (!container.contains(event.target)) {
            menu.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        }
    });
}

function initLoginPage() {
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
    });
    form.addEventListener('submit', () => {
        button.disabled = true;
        text?.classList.add('hidden');
        spinner?.classList.remove('hidden');
    });
}

function initSpatialDatasetForm() {
    const checkbox = document.querySelector('[data-spatial-toggle]');
    const fields = document.getElementById('spatial-fields');
    const geometry = document.getElementById('geometry_type');
    const srid = document.getElementById('srid');
    if (!checkbox || !fields || !geometry || !srid) return;
    const sync = () => {
        const enabled = checkbox.checked;
        fields.classList.toggle('hidden', !enabled);
        geometry.required = enabled;
        srid.required = enabled;
    };
    checkbox.addEventListener('change', sync);
    sync();
}
