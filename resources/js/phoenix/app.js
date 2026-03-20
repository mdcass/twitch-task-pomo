import '../bootstrap';
import focus from '@alpinejs/focus';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(focus);
});

const getPreferredTheme = () => {
    const storedTheme = localStorage.getItem('phoenixTheme') ?? 'light';

    if (storedTheme === 'auto') {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    return storedTheme;
};

const applyTheme = (theme) => {
    document.documentElement.setAttribute('data-bs-theme', theme);
};

const largeScreen = window.matchMedia('(min-width: 992px)');
const darkPreference = window.matchMedia('(prefers-color-scheme: dark)');
const collapsedStorageKey = 'phoenixIsNavbarVerticalCollapsed';

const applyNavbarVerticalCollapsed = (collapsed) => {
    document.documentElement.classList.toggle('navbar-vertical-collapsed', collapsed);
};

const syncThemeControls = () => {
    const storedTheme = localStorage.getItem('phoenixTheme') ?? 'light';
    const checked = storedTheme === 'dark';

    document.querySelectorAll('[data-theme-control="phoenixTheme"]').forEach((input) => {
        if (input instanceof HTMLInputElement) {
            input.checked = checked;
        }
    });
};

const setDocumentMinHeight = () => {
    const navbarVertical = document.querySelector('.navbar-vertical');

    if (!navbarVertical) {
        document.documentElement.style.removeProperty('min-height');
        return;
    }

    const bodyHeight = document.body.offsetHeight;
    const navbarVerticalHeight = navbarVertical.offsetHeight;

    if (
        document.documentElement.classList.contains('navbar-vertical-collapsed') &&
        bodyHeight < navbarVerticalHeight
    ) {
        document.documentElement.style.minHeight = `${navbarVerticalHeight}px`;
        return;
    }

    document.documentElement.style.removeProperty('min-height');
};

const syncComboNavigation = () => {
    const comboNavbar = document.querySelector('[data-navbar-top="combo"]');
    const moveContainer = document.querySelector('[data-move-container]');

    if (!comboNavbar) {
        moveContainer?.remove();
        return;
    }

    const topCollapse = comboNavbar.querySelector('.collapse');
    const targetSelector = comboNavbar.getAttribute('data-move-target');
    const target = targetSelector ? document.querySelector(targetSelector) : null;

    if (!topCollapse || !target) {
        moveContainer?.remove();
        return;
    }

    if (window.innerWidth < 992) {
        if (moveContainer) {
            return;
        }

        const topContent = topCollapse.innerHTML.trim();

        if (topContent === '') {
            return;
        }

        topCollapse.innerHTML = '';
        target.insertAdjacentHTML(
            'afterend',
            `
                <div data-move-container class="move-container">
                    <div class="navbar-vertical-divider">
                        <hr class="navbar-vertical-hr" />
                    </div>
                    ${topContent}
                </div>
            `,
        );

        document.querySelector('[data-move-container] .navbar-nav')?.classList.add('flex-column');

        return;
    }

    if (!moveContainer) {
        return;
    }

    const navbarNav = moveContainer.querySelector('.navbar-nav');

    navbarNav?.classList.remove('flex-column');

    const divider = moveContainer.querySelector('.navbar-vertical-divider');
    divider?.remove();

    topCollapse.innerHTML = moveContainer.innerHTML;
    moveContainer.remove();
};

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(getPreferredTheme());
    syncThemeControls();
    applyNavbarVerticalCollapsed(localStorage.getItem(collapsedStorageKey) === 'true');
    setDocumentMinHeight();
    syncComboNavigation();
});

document.addEventListener('click', (event) => {
    const navbarToggle = event.target.closest('.navbar-vertical-toggle');

    if (navbarToggle) {
        event.preventDefault();

        const collapsed = document.documentElement.classList.contains('navbar-vertical-collapsed');
        const next = !collapsed;

        navbarToggle.blur();
        localStorage.setItem(collapsedStorageKey, next ? 'true' : 'false');
        applyNavbarVerticalCollapsed(next);
        setDocumentMinHeight();
    }
});

document.addEventListener('change', (event) => {
    const themeControl = event.target.closest('[data-theme-control="phoenixTheme"]');

    if (!themeControl || !(themeControl instanceof HTMLInputElement)) {
        return;
    }

    localStorage.setItem('phoenixTheme', themeControl.checked ? 'dark' : 'light');
    applyTheme(getPreferredTheme());
    syncThemeControls();
});

darkPreference.addEventListener('change', () => {
    if ((localStorage.getItem('phoenixTheme') ?? 'light') === 'auto') {
        applyTheme(getPreferredTheme());
        syncThemeControls();
    }
});

largeScreen.addEventListener('change', (event) => {
    if (event.matches) {
        applyNavbarVerticalCollapsed(localStorage.getItem(collapsedStorageKey) === 'true');
    }

    setDocumentMinHeight();
    syncComboNavigation();
});
