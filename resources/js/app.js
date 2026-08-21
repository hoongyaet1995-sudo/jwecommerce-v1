import './bootstrap';
import {
    ArrowLeft,
    ArrowRight,
    Award,
    Briefcase,
    Check,
    CircleCheck,
    CircleAlert,
    ChevronDown,
    Copy,
    Compass,
    CreditCard,
    FileText,
    Globe,
    Glasses,
    Grid,
    Layers,
    Lamp,
    Laptop,
    List,
    LockKeyhole,
    Lock,
    LogIn,
    LogOut,
    Mail,
    Menu,
    MapPin,
    KeyRound,
    Minus,
    Moon,
    Plus,
    Package,
    Search,
    Headphones,
    Heart,
    ShoppingBag,
    SlidersHorizontal,
    Sparkles,
    Star,
    ShieldCheck,
    Sun,
    Tag,
    Trash2,
    Truck,
    RotateCcw,
    Watch,
    X,
    Zap,
    User,
    UserPlus,
    createIcons,
} from 'lucide';

const renderIcons = () => {
    createIcons({
        icons: {
            ArrowLeft,
            ArrowRight,
            Award,
            Briefcase,
            Check,
            CircleCheck,
            CircleAlert,
            ChevronDown,
            Copy,
            Compass,
            CreditCard,
            FileText,
            Globe,
            Glasses,
            Grid,
            Layers,
            Lamp,
            Laptop,
            List,
            LockKeyhole,
            Lock,
            LogIn,
            LogOut,
            Mail,
            Menu,
            MapPin,
            KeyRound,
            Minus,
            Moon,
            Plus,
            Package,
            Search,
            Headphones,
            Heart,
            ShoppingBag,
            SlidersHorizontal,
            Sparkles,
            Star,
            ShieldCheck,
            Sun,
            Tag,
            Trash2,
            Truck,
            RotateCcw,
            Watch,
            X,
            Zap,
            User,
            UserPlus,
        },
        attrs: {
            'aria-hidden': 'true',
            'stroke-width': 1.75,
        },
    });
};

const refreshCartDrawer = async (drawer) => {
    const response = await fetch('/cart/drawer', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Unable to refresh the cart drawer.');
    }

    const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
    const nextContent = documentFragment.querySelector('#cart-drawer-content');
    const currentContent = drawer.querySelector('#cart-drawer-content');

    if (!nextContent || !currentContent) {
        throw new Error('Cart drawer response is incomplete.');
    }

    currentContent.replaceWith(nextContent);
    const count = Number(nextContent.dataset.cartCount || 0);
    document.querySelectorAll('[data-cart-drawer-open]').forEach((trigger) => {
        const badge = trigger.querySelector('[data-cart-count-badge]');

        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('hidden', count === 0);
        }
    });
    renderIcons();
};

const openCartDrawer = (drawer, opener = null) => {
    drawer.__atelierCartOpener = opener;
    drawer.classList.remove('pointer-events-none');
    drawer.setAttribute('aria-hidden', 'false');
    drawer.querySelector('[data-cart-drawer-panel]')?.classList.remove('translate-x-full');
    drawer.querySelector('[data-cart-drawer-backdrop]')?.classList.remove('opacity-0');
    document.body.classList.add('overflow-hidden');
    window.setTimeout(() => drawer.querySelector('[data-cart-drawer-close]')?.focus(), 0);
};

const installCartDrawer = () => {
    const drawer = document.getElementById('cart-drawer');

    if (!drawer) {
        return;
    }

    let closeTimer = null;
    const panel = () => drawer.querySelector('[data-cart-drawer-panel]');
    const backdrop = () => drawer.querySelector('[data-cart-drawer-backdrop]');

    const open = (trigger) => {
        if (closeTimer) {
            window.clearTimeout(closeTimer);
        }
        openCartDrawer(drawer, trigger);
    };

    const close = () => {
        panel()?.classList.add('translate-x-full');
        backdrop()?.classList.add('opacity-0');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        closeTimer = window.setTimeout(() => {
            if (drawer.getAttribute('aria-hidden') === 'true') {
                drawer.classList.add('pointer-events-none');
            }
        }, 300);
        drawer.__atelierCartOpener?.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-cart-drawer-open]');

        if (trigger) {
            event.preventDefault();
            open(trigger);
            return;
        }

        if (event.target.closest('[data-cart-drawer-close]') || event.target.closest('[data-cart-drawer-backdrop]')) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && drawer.getAttribute('aria-hidden') === 'false') {
            close();
        }
    });

    const submitDrawerForm = async (form, submitter) => {
        submitter?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to update the cart.');
            }

            await refreshCartDrawer(drawer);
            openCartDrawer(drawer, submitter);
        } catch (error) {
            window.location.assign(form.action);
        } finally {
            submitter?.removeAttribute('disabled');
        }
    };

    document.addEventListener('click', (event) => {
        const submitter = event.target.closest('form[data-cart-drawer-form] button[type="submit"], form[data-cart-drawer-form] button:not([type])');
        const form = submitter?.closest('form[data-cart-drawer-form]');

        if (!form) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        submitDrawerForm(form, submitter);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-cart-drawer-form]');

        if (!form) {
            return;
        }

        event.preventDefault();
        submitDrawerForm(form, event.submitter);
    });
};

const installStorefrontCartAdds = () => {
    const drawer = document.getElementById('cart-drawer');

    if (!drawer) {
        return;
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[action$="/cart/items"]');

        if (!form || form.matches('[data-cart-drawer-form]') || form.querySelector('input[name="checkout"]') || window.location.pathname === '/cart') {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;
        submitter?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new Error('Unable to add the item to the cart.');
            }

            await refreshCartDrawer(drawer);
            openCartDrawer(drawer, submitter);
        } catch (error) {
            form.submit();
        } finally {
            submitter?.removeAttribute('disabled');
        }
    });
};

const installCatalogSearch = () => {
    document.querySelectorAll('[data-catalog-search]').forEach((container) => {
        const open = container.querySelector('[data-catalog-search-open]');
        const form = container.querySelector('[data-catalog-search-form]');
        const input = form?.querySelector('input[name="search"]');
        const close = container.querySelector('[data-catalog-search-close]');

        open?.addEventListener('click', (event) => {
            if (window.location.pathname !== '/shop') {
                return;
            }

            event.preventDefault();
            open.classList.add('hidden');
            form?.classList.remove('hidden');
            input?.focus();
        });

        close?.addEventListener('click', () => {
            if (window.location.pathname !== '/shop') {
                window.location.assign('/shop');
                return;
            }

            input.value = '';
            form?.classList.add('hidden');
            open?.classList.remove('hidden');
            window.history.replaceState({}, '', '/shop');
            open?.focus();
        });
    });
};

const installLocalAssetInputs = () => {
    document.querySelectorAll('form[action*="/images"] input[name="path"]').forEach((input) => {
        input.type = 'text';
        input.placeholder = '/images/catalog/aether-1.jpg';
        input.setAttribute('inputmode', 'url');
        input.setAttribute('title', 'Use a managed image path from /images/catalog or /images/source.');
    });
};

const installAtelierTheme = () => {
    const root = document.documentElement;

    if (!root) {
        return;
    }

    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        root.classList.toggle('dark', isDark);
        root.dataset.atelierTheme = isDark ? 'dark' : 'light';
        document.querySelectorAll('[data-atelier-theme-toggle]').forEach((toggle) => {
            toggle.setAttribute('title', isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            toggle.setAttribute('aria-label', isDark ? 'Toggle Dark or Light Mode' : 'Toggle Dark or Light Mode');
            toggle.querySelector('[data-atelier-theme-label]')?.replaceChildren(isDark ? 'Light Mode' : 'Dark Mode');
        });
        window.localStorage.setItem('atelier_theme', theme);
        renderIcons();
    };

    const savedTheme = window.localStorage.getItem('atelier_theme');
    applyTheme(savedTheme === 'light' ? 'light' : 'dark');
    document.querySelectorAll('[data-atelier-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => applyTheme(root.dataset.atelierTheme === 'dark' ? 'light' : 'dark'));
    });
};

const installAtelierCurrency = () => {
    const savedCurrency = window.localStorage.getItem('atelier_currency');

    document.querySelectorAll('[data-atelier-currency]').forEach((select) => {
        if (savedCurrency && [...select.options].some((option) => option.value === savedCurrency)) {
            select.value = savedCurrency;
        }

        select.addEventListener('change', () => window.localStorage.setItem('atelier_currency', select.value));
    });
};

const installMegaNavigation = () => {
    document.querySelectorAll('[data-mega-nav]').forEach((nav) => {
        const items = [...nav.querySelectorAll('[data-mega-nav-item]')];
        const close = () => items.forEach((item) => {
            item.querySelector('[data-mega-nav-panel]')?.classList.add('invisible', 'opacity-0');
            item.querySelector('[data-mega-nav-button]')?.setAttribute('aria-expanded', 'false');
        });
        items.forEach((item) => {
            const panel = item.querySelector('[data-mega-nav-panel]');
            const button = item.querySelector('[data-mega-nav-button]');
            if (!panel || !button) return;
            item.addEventListener('mouseenter', () => {
                close(); panel.classList.remove('invisible', 'opacity-0'); button.setAttribute('aria-expanded', 'true');
            });
            item.addEventListener('mouseleave', () => close());
            button.addEventListener('click', (event) => {
                event.preventDefault();
                const opening = panel.classList.contains('invisible'); close();
                if (opening) { panel.classList.remove('invisible', 'opacity-0'); button.setAttribute('aria-expanded', 'true'); }
            });
        });
        document.addEventListener('click', (event) => { if (!nav.contains(event.target)) close(); });
        const mobilePanel = nav.querySelector('[data-mobile-mega-panel]');
        nav.querySelector('[data-mobile-mega-open]')?.addEventListener('click', () => { mobilePanel?.classList.remove('hidden'); mobilePanel?.setAttribute('aria-hidden', 'false'); document.body.classList.add('overflow-hidden'); });
        nav.querySelector('[data-mobile-mega-close]')?.addEventListener('click', () => { mobilePanel?.classList.add('hidden'); mobilePanel?.setAttribute('aria-hidden', 'true'); document.body.classList.remove('overflow-hidden'); });
    });
};

const installDeliveryMatrix = () => {
    document.querySelectorAll('[data-delivery-matrix]').forEach((matrix) => {
        const form = matrix.closest('form');
        const subtotal = Number(matrix.dataset.subtotal || 0);
        const discount = Number(matrix.dataset.discount || 0);
        const taxRate = Number(matrix.dataset.taxRate || 0);
        const money = (amount) => `$${(amount / 100).toFixed(2)}`;

        const refresh = (radio) => {
            const option = radio.closest('[data-delivery-option]');
            const shipping = Number(option?.dataset.deliveryShipping || 0);
            const tax = Math.floor(Math.max(0, subtotal - discount + shipping) * taxRate / 100);
            const total = Math.max(0, subtotal - discount + shipping + tax);
            const definitionFor = (label) => [...(form?.querySelectorAll('dt') || [])].find((term) => term.textContent.trim() === label)?.nextElementSibling;
            const shippingDefinition = definitionFor('Shipping');
            const taxDefinition = definitionFor('Estimated Tax');
            const totalDefinition = definitionFor('Grand Total');

            if (shippingDefinition) shippingDefinition.textContent = shipping ? money(shipping) : 'FREE';
            if (taxDefinition) taxDefinition.textContent = money(tax);
            if (totalDefinition) totalDefinition.textContent = money(total);
            const checkoutSubmit = form?.querySelector('[data-checkout-submit]')
                || form?.querySelector('aside button:not([type="button"])');
            checkoutSubmit?.replaceChildren(`Authorize & Place Order (${money(total)})`);
            matrix.querySelectorAll('[data-delivery-option]').forEach((element) => {
                element.classList.toggle('border-amber-400', element === option);
                element.classList.toggle('bg-amber-400/10', element === option);
            });
        };

        matrix.querySelectorAll('input[name="delivery"]').forEach((radio) => {
            radio.addEventListener('change', () => refresh(radio));
        });
    });
};

const installAdminExperience = () => {
    const shell = document.querySelector('[data-admin-shell]');

    if (!shell) return;

    const themeKey = 'jw_admin_theme';
    const sidebarKey = 'jw_admin_sidebar_collapsed';
    const root = document.documentElement;
    const sidebar = shell.querySelector('[data-admin-sidebar]');
    const main = shell.querySelector('[data-admin-main]');
    const themeToggle = shell.querySelector('[data-admin-theme-toggle]');
    const sidebarToggle = shell.querySelector('[data-admin-sidebar-collapse-toggle]');
    const sidebarLabel = shell.querySelector('[data-admin-sidebar-collapse-label]');
    const desktopMedia = window.matchMedia('(min-width: 1024px)');
    const commandDialog = shell.querySelector('[data-admin-command-dialog]');
    const commandInput = shell.querySelector('[data-admin-command-input]');
    const notificationDrawer = shell.querySelector('[data-admin-notification-drawer]');
    const notificationToggle = shell.querySelector('[data-admin-notification-toggle]');
    const profileMenu = shell.querySelector('[data-admin-profile-menu]');
    const builderWorkspace = shell.querySelector('[data-admin-builder-workspace]');
    const builderPreviewDialog = shell.querySelector('[data-admin-builder-preview-dialog]');
    const builderPreviewOpener = builderWorkspace?.querySelector('[data-admin-builder-preview-enlarge]');
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), iframe, summary, [contenteditable="true"], [tabindex]:not([tabindex="-1"])';
    const backgroundState = new Map();
    const overlayOpeners = new Map();
    let activeOverlay = null;

    const setTheme = (theme) => {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        root.dataset.jwAdminTheme = nextTheme;
        root.classList.toggle('dark', nextTheme === 'dark');
        themeToggle?.setAttribute('aria-pressed', String(nextTheme === 'light'));
    };

    const setSidebarCollapsed = () => {
        const collapsed = window.localStorage.getItem(sidebarKey) === 'true';
        const applyDesktopState = desktopMedia.matches && collapsed;
        sidebar?.classList.toggle('hidden', applyDesktopState);
        main?.classList.toggle('lg:pl-0', applyDesktopState);
        sidebarToggle?.setAttribute('aria-pressed', String(collapsed));
        sidebarLabel?.replaceChildren(collapsed ? 'Expand nav' : 'Collapse nav');
    };

    const syncScrollLock = () => {
        const mobileSidebarOpen = !desktopMedia.matches && sidebar?.getAttribute('aria-hidden') === 'false';

        document.body.classList.toggle('overflow-hidden', Boolean(activeOverlay) || mobileSidebarOpen);
    };

    const focusableIn = (surface) => [...surface.querySelectorAll(focusableSelector)]
        .filter((control) => !control.hasAttribute('disabled') && control.getAttribute('tabindex') !== '-1');

    const disableBackground = (activeSurface) => {
        [...shell.children].filter((child) => child !== activeSurface).forEach((child) => {
            const controls = new Map();

            [child, ...child.querySelectorAll(focusableSelector)].filter((control) => control.matches(focusableSelector)).forEach((control) => {
                controls.set(control, control.getAttribute('tabindex'));
                control.setAttribute('tabindex', '-1');
            });
            backgroundState.set(child, { inert: child.hasAttribute('inert'), controls });
            child.setAttribute('inert', '');
        });
    };

    const restoreBackground = () => {
        backgroundState.forEach((state, child) => {
            child.toggleAttribute('inert', state.inert);
            state.controls.forEach((tabIndex, control) => {
                if (tabIndex === null) {
                    control.removeAttribute('tabindex');
                    return;
                }

                control.setAttribute('tabindex', tabIndex);
            });
        });
        backgroundState.clear();
    };

    const closeOverlay = (surface, { restoreFocus = true } = {}) => {
        if (!surface || surface.getAttribute('aria-hidden') === 'true') return;

        const opener = overlayOpeners.get(surface);
        surface.classList.add('hidden');
        surface.setAttribute('aria-hidden', 'true');
        restoreBackground();
        activeOverlay = null;
        shell.removeAttribute('data-admin-overlay-active');
        syncScrollLock();
        if (surface === notificationDrawer) notificationToggle?.setAttribute('aria-expanded', 'false');
        if (opener?.hasAttribute('aria-expanded')) opener.setAttribute('aria-expanded', 'false');
        if (restoreFocus) opener?.focus();
    };

    const openOverlay = (surface, opener, focusTarget) => {
        if (!surface) return;
        if (activeOverlay === surface) return;
        if (activeOverlay && activeOverlay !== surface) closeOverlay(activeOverlay, { restoreFocus: false });

        overlayOpeners.set(surface, opener);
        activeOverlay = surface;
        shell.setAttribute('data-admin-overlay-active', surface.id);
        disableBackground(surface);
        surface.classList.remove('hidden');
        surface.setAttribute('aria-hidden', 'false');
        if (surface === notificationDrawer) notificationToggle?.setAttribute('aria-expanded', 'true');
        if (opener?.hasAttribute('aria-expanded')) opener.setAttribute('aria-expanded', 'true');
        syncScrollLock();
        window.setTimeout(() => (focusTarget || focusableIn(surface)[0] || surface.querySelector('[data-admin-overlay-content]'))?.focus(), 0);
    };

    if (builderWorkspace) {
        const configurePanel = ({ panel, content, heading, toggle, label, labelText, storageKey, panelName }) => {
            if (!panel || !content || !toggle) return;

            const setCollapsed = (collapsed) => {
                panel.dataset.collapsed = String(collapsed);
                content.classList.toggle('hidden', collapsed);
                heading?.classList.toggle('hidden', collapsed);
                toggle.setAttribute('aria-expanded', String(!collapsed));
                toggle.setAttribute('aria-label', `${collapsed ? 'Expand' : 'Collapse'} ${panelName}`);
                label?.replaceChildren(collapsed ? '→' : '←');
                labelText?.replaceChildren(`${collapsed ? 'Expand' : 'Collapse'} ${panelName}`);
            };

            setCollapsed(window.localStorage.getItem(storageKey) === 'true');
            toggle.addEventListener('click', () => {
                const collapsed = panel.dataset.collapsed !== 'true';
                window.localStorage.setItem(storageKey, String(collapsed));
                setCollapsed(collapsed);
            });
        };

        configurePanel({
            panel: builderWorkspace.querySelector('[data-admin-builder-outline]'),
            content: builderWorkspace.querySelector('[data-admin-builder-outline-content]'),
            heading: builderWorkspace.querySelector('[data-admin-builder-outline-heading]'),
            toggle: builderWorkspace.querySelector('[data-admin-builder-outline-toggle]'),
            label: builderWorkspace.querySelector('[data-admin-builder-outline-label]'),
            labelText: builderWorkspace.querySelector('[data-admin-builder-outline-label-text]'),
            storageKey: 'jw_admin_builder_outline_collapsed',
            panelName: 'section outline',
        });
        configurePanel({
            panel: builderWorkspace.querySelector('[data-admin-builder-inspector]'),
            content: builderWorkspace.querySelector('[data-admin-builder-inspector-content]'),
            heading: builderWorkspace.querySelector('[data-admin-builder-inspector-heading]'),
            toggle: builderWorkspace.querySelector('[data-admin-builder-inspector-toggle]'),
            label: builderWorkspace.querySelector('[data-admin-builder-inspector-label]'),
            labelText: builderWorkspace.querySelector('[data-admin-builder-inspector-label-text]'),
            storageKey: 'jw_admin_builder_inspector_collapsed',
            panelName: 'section inspector',
        });

        builderPreviewOpener?.addEventListener('click', () => openOverlay(builderPreviewDialog, builderPreviewOpener));
        builderPreviewDialog?.querySelectorAll('[data-admin-builder-preview-close]').forEach((button) => {
            button.addEventListener('click', () => closeOverlay(builderPreviewDialog));
        });
        shell.querySelectorAll('[data-admin-builder-preview-frame]').forEach((frame) => {
            const finishLoading = () => frame.parentElement?.querySelector('[data-admin-builder-preview-loading]')?.classList.add('hidden');
            const connectDialogKeyboard = () => {
                if (!builderPreviewDialog?.contains(frame)) return;

                const frameDocument = frame.contentDocument;
                if (!frameDocument || frameDocument.documentElement.dataset.adminBuilderKeyboard === 'true') return;

                frameDocument.documentElement.dataset.adminBuilderKeyboard = 'true';
                frameDocument.addEventListener('click', (event) => {
                    if (event.target.closest?.('a[href]')) event.preventDefault();
                });
                frameDocument.addEventListener('keydown', (event) => {
                    if (activeOverlay !== builderPreviewDialog) return;

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeOverlay(builderPreviewDialog);
                        return;
                    }

                    if (event.key !== 'Tab') return;

                    const controls = [...frameDocument.querySelectorAll(focusableSelector)]
                        .filter((control) => !control.hasAttribute('disabled') && control.getAttribute('tabindex') !== '-1' && control.getClientRects().length);
                    const first = controls[0];
                    const last = controls.at(-1);

                    if (!controls.length || (event.shiftKey && frameDocument.activeElement === first) || (!event.shiftKey && frameDocument.activeElement === last)) {
                        event.preventDefault();
                        builderPreviewDialog.querySelector('[data-admin-builder-preview-close]:not([tabindex="-1"])')?.focus();
                    }
                });
            };

            frame.addEventListener('load', () => {
                finishLoading();
                connectDialogKeyboard();
            });
            connectDialogKeyboard();
            window.setTimeout(finishLoading, 1500);
        });
    }

    setTheme(window.localStorage.getItem(themeKey));
    setSidebarCollapsed();
    themeToggle?.addEventListener('click', () => {
        const nextTheme = root.dataset.jwAdminTheme === 'dark' ? 'light' : 'dark';
        window.localStorage.setItem(themeKey, nextTheme);
        setTheme(nextTheme);
    });
    sidebarToggle?.addEventListener('click', () => {
        window.localStorage.setItem(sidebarKey, String(window.localStorage.getItem(sidebarKey) !== 'true'));
        setSidebarCollapsed();
    });
    desktopMedia.addEventListener('change', () => {
        setSidebarCollapsed();
        if (!activeOverlay) return;

        const surface = activeOverlay;
        restoreBackground();
        queueMicrotask(() => {
            if (activeOverlay !== surface) return;

            disableBackground(surface);
            syncScrollLock();
        });
    });

    shell.querySelectorAll('[data-admin-command-open], [data-admin-command-search]').forEach((opener) => {
        opener.addEventListener('click', (event) => {
            event.preventDefault();
            openOverlay(commandDialog, opener, commandInput);
        });
    });
    shell.querySelectorAll('[data-admin-command-close]').forEach((button) => button.addEventListener('click', () => closeOverlay(commandDialog)));
    commandDialog?.addEventListener('click', (event) => {
        if (event.target === commandDialog) closeOverlay(commandDialog);
    });
    notificationToggle?.addEventListener('click', () => openOverlay(notificationDrawer, notificationToggle));
    shell.querySelectorAll('[data-admin-notification-close]').forEach((button) => button.addEventListener('click', () => closeOverlay(notificationDrawer)));

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            const commandOpener = activeOverlay === notificationDrawer
                ? overlayOpeners.get(notificationDrawer)
                : document.activeElement;
            openOverlay(commandDialog, commandOpener, commandInput);
        }

        if (event.key === 'Escape') {
            closeOverlay(activeOverlay);
            profileMenu?.removeAttribute('open');
        }

        if (event.key !== 'Tab' || !activeOverlay) return;

        const controls = focusableIn(activeOverlay);
        const first = controls[0];
        const last = controls.at(-1);

        if (!first || !last) {
            event.preventDefault();
            activeOverlay.querySelector('[data-admin-overlay-content]')?.focus();
            return;
        }

        if (event.shiftKey && (document.activeElement === first || !activeOverlay.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !activeOverlay.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });
};

const installAdminSidebarDrawer = () => {
    const shell = document.querySelector('[data-admin-shell]');
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const toggle = document.querySelector('[data-admin-sidebar-toggle]');
    const backdrop = document.querySelector('[data-admin-sidebar-backdrop]');
    const closeButton = document.querySelector('[data-admin-sidebar-close]');

    if (!sidebar || !toggle || !backdrop || !closeButton) {
        return;
    }

    const desktopMedia = window.matchMedia('(min-width: 1024px)');
    let opener = null;
    const disabledTabOrder = new Map();
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]';

    const focusableControls = () => [...sidebar.querySelectorAll(focusableSelector)]
        .filter((control) => control.getAttribute('tabindex') !== '-1' && !control.hasAttribute('disabled'));

    const setSidebarInteractive = (interactive) => {
        sidebar.toggleAttribute('inert', !interactive);

        if (interactive) {
            disabledTabOrder.forEach((tabIndex, control) => {
                if (tabIndex === null) {
                    control.removeAttribute('tabindex');
                    return;
                }

                control.setAttribute('tabindex', tabIndex);
            });
            disabledTabOrder.clear();
            return;
        }

        sidebar.querySelectorAll(focusableSelector).forEach((control) => {
            if (!disabledTabOrder.has(control)) {
                disabledTabOrder.set(control, control.getAttribute('tabindex'));
            }

            control.setAttribute('tabindex', '-1');
        });
    };

    const close = ({ restoreFocus = true } = {}) => {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        sidebar.setAttribute('aria-hidden', 'true');
        setSidebarInteractive(false);
        backdrop.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        if (!shell?.hasAttribute('data-admin-overlay-active')) {
            document.body.classList.remove('overflow-hidden');
        }

        if (restoreFocus) {
            opener?.focus();
        }
    };

    const open = (trigger) => {
        if (desktopMedia.matches) {
            return;
        }

        opener = trigger;
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        sidebar.setAttribute('aria-hidden', 'false');
        setSidebarInteractive(true);
        backdrop.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('overflow-hidden');
        window.setTimeout(() => closeButton.focus(), 0);
    };

    const syncForViewport = () => {
        if (desktopMedia.matches) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            sidebar.setAttribute('aria-hidden', 'false');
            setSidebarInteractive(true);
            backdrop.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
            if (!shell?.hasAttribute('data-admin-overlay-active')) {
                document.body.classList.remove('overflow-hidden');
            }
            return;
        }

        close({ restoreFocus: false });
    };

    toggle.addEventListener('click', () => {
        if (toggle.getAttribute('aria-expanded') === 'true') {
            close();
            return;
        }

        open(toggle);
    });
    closeButton.addEventListener('click', () => close());
    backdrop.addEventListener('click', () => close());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            close();
            return;
        }

        if (event.key !== 'Tab' || toggle.getAttribute('aria-expanded') !== 'true') {
            return;
        }

        const controls = focusableControls();

        if (!controls.length) {
            event.preventDefault();
            return;
        }

        const first = controls[0];
        const last = controls.at(-1);

        if (event.shiftKey && (document.activeElement === first || !sidebar.contains(document.activeElement))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (document.activeElement === last || !sidebar.contains(document.activeElement))) {
            event.preventDefault();
            first.focus();
        }
    });
    desktopMedia.addEventListener('change', syncForViewport);
    syncForViewport();
};

const installSuccessReceipt = () => {
    const dialog = document.querySelector('[data-success-receipt-dialog]');

    if (!dialog) {
        return;
    }

    document.querySelectorAll('[data-success-receipt-open]').forEach((button) => button.addEventListener('click', () => dialog.showModal()));
    dialog.querySelectorAll('[data-success-receipt-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.querySelector('[data-success-receipt-print]')?.addEventListener('click', () => window.print());

    document.querySelectorAll('[data-success-tracking-copy]').forEach((button) => button.addEventListener('click', async () => {
        const number = button.dataset.trackingNumber;
        if (!number) return;

        try {
            await navigator.clipboard.writeText(number);
            button.textContent = 'Copied';
            window.setTimeout(() => { button.textContent = 'Copy tracking'; }, 1800);
        } catch {
            button.textContent = number;
        }
    }));
};

const installPdpWishlist = () => {
    document.querySelectorAll('[data-pdp-wishlist]').forEach((button) => {
        button.addEventListener('click', () => {
            const selected = button.getAttribute('aria-pressed') !== 'true';
            const icon = button.querySelector('svg');

            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('bg-red-500', selected);
            button.classList.toggle('text-white', selected);
            button.classList.toggle('border-red-400', selected);
            button.classList.toggle('bg-zinc-950/70', !selected);
            button.classList.toggle('text-zinc-300', !selected);
            button.classList.toggle('border-zinc-800', !selected);
            icon?.classList.toggle('fill-current', selected);
        });
    });
};

const installCustomerAccountTabs = () => {
    document.querySelectorAll('[data-account]').forEach((account) => {
        const tabs = [...account.querySelectorAll('[data-account-tab]')];
        const panels = [...account.querySelectorAll('[data-account-panel]')];

        tabs.forEach((tab) => tab.addEventListener('click', () => {
            const active = tab.dataset.accountTab;
            tabs.forEach((item) => {
                const selected = item === tab;
                item.classList.toggle('bg-zinc-900', selected);
                item.classList.toggle('font-bold', selected);
                item.classList.toggle('text-white', selected);
                item.classList.toggle('shadow-sm', selected);
                item.classList.toggle('dark:bg-zinc-100', selected);
                item.classList.toggle('dark:text-zinc-950', selected);
                item.classList.toggle('text-zinc-600', !selected);
                item.classList.toggle('dark:text-zinc-400', !selected);
            });
            panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.accountPanel !== active));
        }));
    });
};

document.addEventListener('DOMContentLoaded', () => {
    renderIcons();
    installCartDrawer();
    installStorefrontCartAdds();
    installCatalogSearch();
    installLocalAssetInputs();
    if (document.documentElement.matches('[data-atelier-theme-root]')) {
        installAtelierTheme();
    }
installAtelierCurrency();
installMegaNavigation();
    installDeliveryMatrix();
    installAdminExperience();
    installAdminSidebarDrawer();
    installSuccessReceipt();
    installPdpWishlist();
    installCustomerAccountTabs();
});
