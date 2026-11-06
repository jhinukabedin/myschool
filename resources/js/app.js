import './bootstrap';

import * as Turbo from '@hotwired/turbo';
import Chart from 'chart.js/auto';
import Alpine from 'alpinejs';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Turbo = window.Turbo || Turbo;
window.Chart = Chart;
window.Alpine = Alpine;
window.Swal = Swal;

/*
 * Turbo swaps <body> without ever firing DOMContentLoaded again, while this
 * application still has page scripts that register their initialisers on that
 * event. Turn those registrations into immediate callbacks once the document
 * is already past the parsing stage, so every Turbo visit behaves like a fresh
 * page load without changing the existing Blade templates.
 */
(() => {
    const nativeAdd = Document.prototype.addEventListener;

    const runNow = (listener) => {
        const event = new Event('DOMContentLoaded');
        queueMicrotask(() => {
            if (typeof listener === 'function') {
                listener.call(document, event);
            } else if (listener && typeof listener.handleEvent === 'function') {
                listener.handleEvent(event);
            }
        });
    };

    Document.prototype.addEventListener = function (type, listener, options) {
        if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
            runNow(listener);
            return;
        }

        return nativeAdd.call(this, type, listener, options);
    };
})();

const t = (key, fallback) => (window.__i18n && window.__i18n[key]) || fallback || key;

// Replace native confirm() prompts on form submissions with one polished,
// accessible confirmation dialog. This keeps existing per-action messages.
const pendingConfirmations = new WeakSet();

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;

    const originalHandler = form.getAttribute('onsubmit');
    if (!originalHandler || !/\bconfirm\s*\(/.test(originalHandler)) return;

    event.preventDefault();
    if (pendingConfirmations.has(form)) return;
    pendingConfirmations.add(form);
    form.removeAttribute('onsubmit');

    const method = (form.querySelector('input[name="_method"]')?.value || form.method).toUpperCase();
    const isDelete = method === 'DELETE';
    const messageMatch = originalHandler.match(/confirm\s*\(\s*(['"])(.*?)\1\s*\)/);
    const message = messageMatch?.[2]
        ? new DOMParser().parseFromString(messageMatch[2], 'text/html').documentElement.textContent
        : (isDelete ? t('confirmDeleteBody', 'This action may permanently remove data.') : t('confirmBody', 'Please review this action before continuing.'));

    Swal.fire({
        icon: 'warning',
        title: isDelete ? t('confirmDeleteTitle', 'Are you sure you want to delete?') : t('confirmTitle', 'Please confirm this action'),
        text: message,
        showCancelButton: true,
        confirmButtonText: isDelete ? t('confirmDelete', 'Yes, delete') : t('confirmContinue', 'Continue'),
        cancelButtonText: t('cancel', 'Cancel'),
        reverseButtons: true,
        focusCancel: true,
        allowOutsideClick: false,
        buttonsStyling: false,
        customClass: {
            popup: 'swal-pro-popup',
            title: 'swal-pro-title',
            htmlContainer: 'swal-pro-message',
            actions: 'swal-pro-actions',
            confirmButton: isDelete ? 'swal-pro-delete' : 'swal-pro-confirm',
            cancelButton: 'swal-pro-cancel',
        },
    }).then((result) => {
        pendingConfirmations.delete(form);

        if (result.isConfirmed) {
            form.requestSubmit(event.submitter || undefined);
        } else {
            form.setAttribute('onsubmit', originalHandler);
        }
    });
}, true);

// Desktop sidebar collapse, remembered across page loads.
const sidebarCollapseKey = 'sidebar-collapsed';

function syncSidebarToggles() {
    const collapsed = document.documentElement.classList.contains('sidebar-collapsed');

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-expanded', String(!collapsed));
        button.setAttribute('aria-label', collapsed ? t('showSidebar', 'Show sidebar') : t('hideSidebar', 'Hide sidebar'));
        button.setAttribute('title', collapsed ? t('showSidebar', 'Show sidebar') : t('hideSidebar', 'Hide sidebar'));
    });
}

window.toggleSidebar = () => {
    const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');

    try {
        localStorage.setItem(sidebarCollapseKey, collapsed ? '1' : '0');
    } catch (e) {}

    syncSidebarToggles();
};

/*
 * Theme: dark is the product default. A saved light preference is applied by
 * the inline head script before first paint; this toggle just flips it live.
 */
window.toggleTheme = () => {
    const root = document.documentElement;
    const next = root.getAttribute('data-theme') === 'light' ? 'dark' : 'light';

    root.setAttribute('data-theme', next);

    try {
        localStorage.setItem('theme', next);
    } catch (e) {}

    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', next === 'light' ? '#f4f7fb' : '#0b0716');

    window.dispatchEvent(new CustomEvent('theme:changed', { detail: { theme: next } }));
};

window.currentTheme = () => (document.documentElement.getAttribute('data-theme') === 'light' ? 'light' : 'dark');

function syncThemeToggles() {
    const light = window.currentTheme() === 'light';
    const label = light ? t('switchToDark', 'Switch to dark theme') : t('switchToLight', 'Switch to light theme');

    document.querySelectorAll('.theme-toggle').forEach((button) => {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.dataset.theme = light ? 'light' : 'dark';
    });
}

window.addEventListener('theme:changed', syncThemeToggles);

/*
 * Command palette (Ctrl/Cmd+K). Items are read from the live sidebar and the
 * current page, so every module gets searchable navigation without having to
 * register anything on the server.
 */
const commandPalette = {
    root: null,
    input: null,
    results: null,
    items: [],
    filtered: [],
    active: 0,
    open: false,
};

function collectCommandItems() {
    const items = [];
    const seen = new Set();

    document.querySelectorAll('#app-sidebar a.sidebar-link').forEach((link) => {
        const label = (link.textContent || '').replace(/\s+/g, ' ').trim();
        const url = link.href;
        if (!label || !url || seen.has(url)) return;

        seen.add(url);
        items.push({
            label,
            url,
            group: (link.closest('.nav-section')?.querySelector('.nav-label span')?.textContent || '').trim(),
            icon: link.querySelector('svg')?.outerHTML || '',
        });
    });

    document.querySelectorAll('.quick-item, .page-actions a.btn').forEach((link) => {
        const label = (link.querySelector('.quick-title')?.textContent || link.textContent || '').replace(/\s+/g, ' ').trim();
        const url = link.href;
        if (!label || !url || seen.has(url)) return;

        seen.add(url);
        items.push({
            label,
            url,
            group: items.some((item) => item.group) ? (window.__i18n?.paletteActions || 'Quick actions') : '',
            icon: link.querySelector('svg')?.outerHTML || '',
        });
    });

    return items;
}

function createCommandItem(item, index) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'command-item';
    button.dataset.index = String(index);
    button.setAttribute('role', 'option');

    const icon = item.icon || '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';

    button.innerHTML = `<span class="command-item-icon">${icon}</span><span class="command-item-body"><span class="command-item-label"></span>${item.group ? '<span class="command-item-group"></span>' : ''}</span><span class="command-item-arrow">↵</span>`;
    button.querySelector('.command-item-label').textContent = item.label;
    if (item.group) button.querySelector('.command-item-group').textContent = item.group;

    button.addEventListener('click', () => {
        closeCommandPalette();
        if (window.Turbo) {
            window.Turbo.visit(item.url);
        } else {
            window.location.href = item.url;
        }
    });

    return button;
}

function renderCommandResults() {
    if (!commandPalette.results) return;

    const query = (commandPalette.input?.value || '').trim().toLowerCase();
    commandPalette.filtered = commandPalette.items.filter((item) => {
        if (!query) return true;
        return `${item.label} ${item.group}`.toLowerCase().includes(query);
    });

    if (commandPalette.active >= commandPalette.filtered.length) {
        commandPalette.active = Math.max(0, commandPalette.filtered.length - 1);
    }

    commandPalette.results.innerHTML = '';

    if (commandPalette.filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'command-empty';
        empty.textContent = t('paletteEmpty', 'No matching pages');
        commandPalette.results.appendChild(empty);
        return;
    }

    let lastGroup = null;

    commandPalette.filtered.forEach((item, index) => {
        if (item.group && item.group !== lastGroup) {
            lastGroup = item.group;
            const heading = document.createElement('div');
            heading.className = 'command-group-label';
            heading.textContent = item.group;
            commandPalette.results.appendChild(heading);
        }

        const element = createCommandItem(item, index);
        if (index === commandPalette.active) element.classList.add('active');
        commandPalette.results.appendChild(element);
    });

    commandPalette.results.querySelector('.command-item.active')?.scrollIntoView({ block: 'nearest' });
}

function moveCommandSelection(delta) {
    if (!commandPalette.filtered.length) return;

    commandPalette.active = (commandPalette.active + delta + commandPalette.filtered.length) % commandPalette.filtered.length;

    commandPalette.results.querySelectorAll('.command-item').forEach((element) => {
        element.classList.toggle('active', Number(element.dataset.index) === commandPalette.active);
    });

    commandPalette.results.querySelector('.command-item.active')?.scrollIntoView({ block: 'nearest' });
}

function openCommandPalette() {
    if (!commandPalette.root) return;

    commandPalette.root.hidden = false;
    commandPalette.open = true;
    commandPalette.items = collectCommandItems();
    commandPalette.active = 0;
    commandPalette.input.value = '';
    document.body.classList.add('command-open');
    renderCommandResults();
    requestAnimationFrame(() => commandPalette.input.focus());
}

function closeCommandPalette() {
    if (!commandPalette.root) return;

    commandPalette.root.hidden = true;
    commandPalette.open = false;
    commandPalette.input.value = '';
    document.body.classList.remove('command-open');
}

function bootCommandPalette() {
    commandPalette.root = document.getElementById('command-palette');
    commandPalette.input = document.getElementById('command-input');
    commandPalette.results = document.getElementById('command-results');

    if (!commandPalette.root || !commandPalette.input) return;

    if (commandPalette.input.dataset.bound !== '1') {
        commandPalette.input.dataset.bound = '1';
        commandPalette.input.addEventListener('input', renderCommandResults);
    }

    if (window.__commandDelegatesBound) return;
    window.__commandDelegatesBound = true;

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-command-open]')) {
            event.preventDefault();
            openCommandPalette();
        }

        if (event.target.closest('[data-command-close]')) {
            closeCommandPalette();
        }
    });

    document.addEventListener('keydown', (event) => {
        const isShortcut = (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k';

        if (isShortcut) {
            event.preventDefault();
            commandPalette.open ? closeCommandPalette() : openCommandPalette();
            return;
        }

        if (!commandPalette.open) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeCommandPalette();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveCommandSelection(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveCommandSelection(-1);
        } else if (event.key === 'Enter') {
            const item = commandPalette.filtered[commandPalette.active];
            if (item) {
                event.preventDefault();
                closeCommandPalette();
                window.Turbo ? window.Turbo.visit(item.url) : (window.location.href = item.url);
            }
        }
    });
}

bootCommandPalette();

syncSidebarToggles();
syncThemeToggles();

Alpine.start();

// Re-initialise Alpine directives inside the freshly swapped body, and refresh
// UI that depends on the current document (sidebar labels, theme icons).
document.addEventListener('turbo:load', () => {
    syncSidebarToggles();
    syncThemeToggles();
    bootCommandPalette();

    try {
        Alpine.initTree(document.body);
    } catch (e) {
        console.warn('Alpine re-initialisation failed', e);
    }
});

// Turbo fires this after the new body is in place but before scripts run.
document.addEventListener('turbo:render', () => {
    try {
        Alpine.initTree(document.body);
    } catch (e) {}
});
