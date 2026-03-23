import '../bootstrap';
import focus from '@alpinejs/focus';
import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(focus);
});

window.twitchTaskPomoLastInteractiveElement = null;

const trackLastInteractiveElement = (target) => {
    if (!(target instanceof Element)) {
        return;
    }

    const candidate = target.closest(
        'button, a, input, select, textarea, [role="button"], [data-modal-return-focus], [wire\\:click]',
    );

    if (candidate instanceof HTMLElement) {
        window.twitchTaskPomoLastInteractiveElement = candidate;
    }
};

window.twitchTaskPomoModal = ({ show, dismissible, initialFocus, initialFocusMethod }) => ({
    show,
    dismissible,
    initialFocus,
    initialFocusMethod,
    lastActiveElement: null,
    lastActiveSelector: null,
    init() {
        this.$watch('show', (value) => (value ? this.handleOpen() : this.handleClose()));

        if (this.show) {
            this.handleOpen();
            return;
        }

        this.syncBodyState(false);
    },
    close() {
        if (!this.dismissible) {
            return;
        }

        this.show = false;
    },
    escapeSelectorValue(value) {
        return JSON.stringify(value).slice(1, -1);
    },
    resolveFocusReturnSelector(element) {
        if (!(element instanceof HTMLElement)) {
            return null;
        }

        if (element.id) {
            return `#${CSS.escape(element.id)}`;
        }

        const namedTarget = element.getAttribute('name');

        if (namedTarget) {
            return `[name="${this.escapeSelectorValue(namedTarget)}"]`;
        }

        const wireClick = element.getAttribute('wire:click');

        if (wireClick) {
            return `[wire\\:click="${this.escapeSelectorValue(wireClick)}"]`;
        }

        return element.getAttribute('data-modal-return-focus');
    },
    syncBodyState(isOpen) {
        document.body.classList.toggle('modal-open', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    },
    findInitialFocusTarget() {
        if (!this.initialFocus) {
            return this.$refs.dialog;
        }

        const refTarget = this.$refs[this.initialFocus];

        if (refTarget instanceof HTMLElement) {
            return refTarget;
        }

        return this.$refs.dialog?.querySelector?.(this.initialFocus) ?? this.$refs.dialog;
    },
    focusInitialTarget() {
        const target = this.findInitialFocusTarget();

        if (!(target instanceof HTMLElement)) {
            return;
        }

        target.focus();

        if (this.initialFocusMethod === 'select' && typeof target.select === 'function') {
            target.select();
        }
    },
    handleOpen() {
        const activeElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        const lastInteractiveElement =
            window.twitchTaskPomoLastInteractiveElement instanceof HTMLElement
                ? window.twitchTaskPomoLastInteractiveElement
                : null;

        this.lastActiveElement =
            activeElement && activeElement !== document.body ? activeElement : lastInteractiveElement;
        this.lastActiveSelector = this.resolveFocusReturnSelector(this.lastActiveElement);
        this.syncBodyState(true);
        this.$nextTick(() => this.focusInitialTarget());
    },
    handleClose() {
        this.syncBodyState(false);

        const lastActiveElement = this.lastActiveElement;
        const lastActiveSelector = this.lastActiveSelector;

        this.$nextTick(() => {
            if (lastActiveElement instanceof HTMLElement && document.contains(lastActiveElement)) {
                lastActiveElement.focus();
                return;
            }

            const fallbackTarget = lastActiveSelector ? document.querySelector(lastActiveSelector) : null;

            if (fallbackTarget instanceof HTMLElement) {
                fallbackTarget.focus();
            }
        });
    },
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
    trackLastInteractiveElement(event.target);

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

document.addEventListener('focusin', (event) => {
    trackLastInteractiveElement(event.target);
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
