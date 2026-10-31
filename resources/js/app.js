import './bootstrap';

import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';

window.Chart = Chart;
window.Alpine = Alpine;

// Desktop sidebar collapse, remembered across page loads.
const sidebarCollapseKey = 'sidebar-collapsed';

function syncSidebarToggles() {
    const collapsed = document.documentElement.classList.contains('sidebar-collapsed');

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? 'Show sidebar' : 'Hide sidebar');
        button.setAttribute('title', collapsed ? 'Show sidebar' : 'Hide sidebar');
    });
}

window.toggleSidebar = () => {
    const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');

    try {
        localStorage.setItem(sidebarCollapseKey, collapsed ? '1' : '0');
    } catch (e) {}

    syncSidebarToggles();
};

syncSidebarToggles();
document.addEventListener('DOMContentLoaded', syncSidebarToggles);

Alpine.start();
