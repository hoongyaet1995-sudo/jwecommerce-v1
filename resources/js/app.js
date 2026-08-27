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
    HelpCircle,
    Inbox,
    Info,
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
    MessageSquare,
    KeyRound,
    Minus,
    Moon,
    Plus,
    Package,
    Phone,
    Search,
    Send,
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
    UserCheck,
    Clock,
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
            HelpCircle,
            Inbox,
            Info,
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
            MessageSquare,
            KeyRound,
            Minus,
            Moon,
            Plus,
            Package,
            Phone,
            Search,
            Send,
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
            UserCheck,
            Clock,
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

        if (!form || form.matches('[data-cart-drawer-form], [data-quick-add-form]') || form.querySelector('input[name="checkout"]') || window.location.pathname === '/cart') {
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
        document.querySelectorAll('[data-atelier-currency]').forEach((select) => {
            select.style.colorScheme = isDark ? 'dark' : 'light';
        });
        document.querySelectorAll('[data-atelier-theme-toggle]').forEach((toggle) => {
            toggle.setAttribute('title', isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode');
            toggle.setAttribute('aria-label', isDark ? 'Toggle Dark or Light Mode' : 'Toggle Dark or Light Mode');
            toggle.querySelector('[data-atelier-theme-label]')?.replaceChildren(isDark ? 'Light Mode' : 'Dark Mode');
            const icon = toggle.querySelector('[data-atelier-theme-icon]');

            if (icon) {
                icon.outerHTML = `<i data-atelier-theme-icon data-lucide="${isDark ? 'sun' : 'moon'}" class="h-4 w-4 ${isDark ? 'text-amber-400 hover:rotate-45' : 'text-zinc-700 hover:-rotate-12'} transition-transform duration-300"></i>`;
            }
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

const installAtelierMemberMenu = () => {
    document.querySelectorAll('[data-atelier-member-menu]').forEach((menu) => {
        const toggle = menu.querySelector('[data-atelier-member-menu-toggle]');
        const panel = menu.querySelector('[data-atelier-member-menu-panel]');
        const close = () => {
            panel?.classList.add('invisible', 'translate-y-[-0.5rem]', 'opacity-0');
            panel?.setAttribute('aria-hidden', 'true');
            toggle?.setAttribute('aria-expanded', 'false');
            toggle?.querySelector('[data-lucide="chevron-down"]')?.classList.remove('rotate-180');
        };
        const open = () => {
            panel?.classList.remove('invisible', 'translate-y-[-0.5rem]', 'opacity-0');
            panel?.setAttribute('aria-hidden', 'false');
            toggle?.setAttribute('aria-expanded', 'true');
            toggle?.querySelector('[data-lucide="chevron-down"]')?.classList.add('rotate-180');
        };

        toggle?.addEventListener('click', (event) => {
            event.stopPropagation();

            if (toggle.getAttribute('aria-expanded') === 'true') {
                close();
            } else {
                document.querySelectorAll('[data-atelier-member-menu]').forEach((otherMenu) => {
                    if (otherMenu !== menu) {
                        otherMenu.querySelector('[data-atelier-member-menu-panel]')?.classList.add('invisible', 'translate-y-[-0.5rem]', 'opacity-0');
                        otherMenu.querySelector('[data-atelier-member-menu-panel]')?.setAttribute('aria-hidden', 'true');
                        otherMenu.querySelector('[data-atelier-member-menu-toggle]')?.setAttribute('aria-expanded', 'false');
                    }
                });
                open();
            }
        });

        document.addEventListener('click', (event) => {
            if (!menu.contains(event.target)) {
                close();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close();
            }
        });
    });
};

const installAtelierCurrency = () => {
    const optionsTemplate = document.querySelector('[data-atelier-currency-options]');
    const endpoint = optionsTemplate?.dataset.atelierCurrencyEndpoint;
    const selectedCurrency = optionsTemplate?.dataset.atelierCurrencyDefault || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    document.querySelectorAll('[data-atelier-currency]').forEach((select) => {
        if (optionsTemplate instanceof HTMLTemplateElement) {
            select.replaceChildren(optionsTemplate.content.cloneNode(true));
            select.dataset.atelierCurrencyDefault = selectedCurrency;
            select.style.colorScheme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
        }

        if (select.dataset.atelierCurrencyDefault) {
            select.value = select.dataset.atelierCurrencyDefault;
        }

        select.addEventListener('change', async () => {
            const previous = selectedCurrency;

            if (!endpoint || !csrfToken) {
                select.value = previous;
                return;
            }

            select.disabled = true;

            try {
                const response = await window.fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ currency: select.value }),
                });

                if (!response.ok) {
                    select.value = previous;
                    return;
                }

                window.location.reload();
            } catch (_) {
                select.value = previous;
            } finally {
                select.disabled = false;
            }
        });
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
        if (matrix.dataset.deliveryMatrixBound === 'true') return;
        matrix.dataset.deliveryMatrixBound = 'true';
        const form = matrix.closest('form');
        const subtotal = Number(matrix.dataset.subtotal || 0);
        const discount = Number(matrix.dataset.discount || 0);
        const quotedTax = Number(matrix.dataset.tax || 0);
        let taxQuote = {};
        try { taxQuote = JSON.parse(matrix.dataset.taxQuote || '{}'); } catch (_) { taxQuote = {}; }
        const money = (amount) => storefrontMoney(amount);

        const refresh = (radio) => {
            const option = radio.closest('[data-delivery-option]');
            const shipping = Number(option?.dataset.deliveryShipping || 0);
            // Tax is resolved server-side by TaxQuoteService; this browser only
            // displays the returned store-wide Tax Class quote.
            const tax = quotedTax;
            const total = Math.max(0, subtotal - discount + shipping + tax);
            const definitionFor = (label) => [...(form?.querySelectorAll('dt') || [])].find((term) => term.textContent.trim() === label)?.nextElementSibling;
            const shippingDefinition = definitionFor('Shipping');
            const taxDefinition = form?.querySelector('[data-store-tax-amount]');
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

const installCheckoutShippingQuotes = () => {
    document.querySelectorAll('form[data-checkout-shipping-quote-url]').forEach((form) => {
        if (form.dataset.checkoutShippingQuoteBound === 'true') return;
        form.dataset.checkoutShippingQuoteBound = 'true';
        const quoteUrl = form.dataset.checkoutShippingQuoteUrl;
        let quoteTimer;
        let requestId = 0;
        const input = (name) => form.querySelector(`[name="${name}"]`);
        const quote = async () => {
            const country = input('country')?.value?.trim();
            const state = input('state')?.value?.trim() || '';
            const postalCode = input('postal_code')?.value?.trim();
            if (!quoteUrl || !country || country.length < 2 || !postalCode) return;
            const selected = form.querySelector('input[name="delivery"]:checked')?.value || '';
            const token = ++requestId;
            try {
                const response = await fetch(quoteUrl, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ country, state, postal_code: postalCode, delivery: selected }),
                });
                const payload = await response.json();
                if (!response.ok || token !== requestId || !payload?.data?.html) return;
                const matrix = form.querySelector('[data-delivery-matrix]');
                if (!matrix) return;
                matrix.outerHTML = payload.data.html;
                installDeliveryMatrix();
                form.querySelector('input[name="delivery"]:checked')?.dispatchEvent(new Event('change'));
            } catch (_) {
                // Authorization still revalidates shipping server-side; the existing form remains usable on a transient quote failure.
            }
        };
        const scheduleQuote = () => { window.clearTimeout(quoteTimer); quoteTimer = window.setTimeout(quote, 220); };
        ['country', 'state', 'postal_code'].forEach((name) => input(name)?.addEventListener('input', scheduleQuote));
        window.addEventListener('checkout-address-changed', scheduleQuote);
        if (form.dataset.checkoutInitialShippingQuote === 'true') scheduleQuote();
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

const storefrontCurrency = () => {
    const source = document.querySelector('[data-atelier-currency-options]');

    return {
        symbolLeft: source?.dataset.atelierCurrencySymbolLeft || '$',
        symbolRight: source?.dataset.atelierCurrencySymbolRight || '',
        decimals: Math.max(0, Number(source?.dataset.atelierCurrencyDecimals || 2)),
        exchangeRate: Number(source?.dataset.atelierCurrencyRate || 1),
    };
};

const storefrontMoney = (cents) => {
    const currency = storefrontCurrency();
    const amount = (Number(cents || 0) / 100) * currency.exchangeRate;
    const formatted = amount.toLocaleString('en-US', {
        minimumFractionDigits: currency.decimals,
        maximumFractionDigits: currency.decimals,
    });

    return `${currency.symbolLeft}${formatted}${currency.symbolRight}`;
};

const parseStorefrontVariants = (element) => {
    try {
        const parsed = JSON.parse(element.dataset.variants || element.dataset.cardVariants || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const parseStorefrontOptions = (element) => {
    try {
        const parsed = JSON.parse(element.dataset.options || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
};

const setPdpVisualImage = (image, alt = '') => {
    if (!image) return;

    document.querySelectorAll('[data-pdp-hero-image], [data-pdp-sticky-image]').forEach((target) => {
        target.src = image;
        if (alt) target.alt = alt;
    });

    document.querySelectorAll('[data-pdp-thumbnail]').forEach((thumbnail) => {
        const selected = thumbnail.dataset.pdpImagePath === image;
        ['border-amber-500', 'ring-2', 'ring-amber-500/40', 'dark:border-amber-400', 'dark:ring-amber-400/40', 'shadow-sm']
            .forEach((className) => thumbnail.classList.toggle(className, selected));
        ['border-zinc-200', 'dark:border-zinc-800', 'opacity-70', 'hover:opacity-100']
            .forEach((className) => thumbnail.classList.toggle(className, !selected));
        thumbnail.setAttribute('aria-current', String(selected));
    });
};

const installPdpThumbnailSwitching = () => {
    document.querySelectorAll('[data-pdp-thumbnail-switch]').forEach((thumbnail) => {
        thumbnail.addEventListener('click', (event) => {
            event.preventDefault();
            setPdpVisualImage(thumbnail.dataset.pdpImagePath, thumbnail.dataset.pdpImageAlt);
        });
    });
};

const installStorefrontVariantSelectors = () => {
    document.querySelectorAll('[data-storefront-variant-selector]').forEach((selector) => {
        const variants = parseStorefrontVariants(selector);
        const options = parseStorefrontOptions(selector);
        if (!variants.length || !options.length) return;

        const initial = variants.find((variant) => String(variant.id) === String(selector.dataset.selectedVariantId)) || variants[0];
        const selectedValues = Object.fromEntries(options.map((option) => [option.name, initial.options?.[option.name] || option.values?.[0] || '']));

        const matchesSelection = (variant, ignoredOption = null) => options.every((option) => {
            if (option.name === ignoredOption) return true;
            return String(variant.options?.[option.name] || '') === String(selectedValues[option.name] || '');
        });

        const resolveVariant = () => variants.find((variant) => matchesSelection(variant))
            || variants.find((variant) => matchesSelection(variant) && variant.enabled !== false)
            || variants[0];

        const update = (variant) => {
            const outOfStock = Number(variant.stock) < 1;
            const enabled = variant.enabled !== false;
            const label = variant.label || variant.title;
            const availability = outOfStock
                ? 'Out of Stock'
                : Number(variant.stock) <= 5
                    ? `Low Stock (Only ${variant.stock} remaining)`
                    : `In Stock (${variant.stock} units)`;

            selector.querySelector('[data-pdp-selected-label]')?.replaceChildren(label);
            selector.querySelector('[data-pdp-availability-text]')?.replaceChildren(availability);
            document.querySelectorAll('[data-pdp-breadcrumb-availability]').forEach((target) => target.replaceChildren(availability));
            const availabilityWrap = selector.querySelector('[data-pdp-availability]');
            availabilityWrap?.classList.toggle('text-rose-500', outOfStock);
            availabilityWrap?.classList.toggle('text-amber-500', !outOfStock && Number(variant.stock) <= 5);
            availabilityWrap?.classList.toggle('text-emerald-600', !outOfStock && Number(variant.stock) > 5);
            const dot = selector.querySelector('[data-pdp-availability-dot]');
            dot?.classList.toggle('bg-rose-500', outOfStock);
            dot?.classList.toggle('bg-amber-400', !outOfStock && Number(variant.stock) <= 5);
            dot?.classList.toggle('bg-emerald-400', !outOfStock && Number(variant.stock) > 5);

            document.querySelectorAll('[data-pdp-variant-input]').forEach((input) => { input.value = variant.id; });
            document.querySelectorAll('[data-pdp-sku], [data-pdp-sticky-sku]').forEach((target) => target.replaceChildren(variant.sku || ''));
            document.querySelectorAll('[data-pdp-price], [data-pdp-sticky-price]').forEach((target) => target.replaceChildren(storefrontMoney(variant.price)));
            document.querySelectorAll('[data-pdp-sticky-label]').forEach((target) => target.replaceChildren(label));
            const compareAt = document.querySelector('[data-pdp-compare-at]');
            const save = document.querySelector('[data-pdp-save]');
            if (compareAt) {
                compareAt.textContent = variant.compareAt ? storefrontMoney(variant.compareAt) : '';
                compareAt.classList.toggle('hidden', !variant.compareAt);
            }
            if (save) {
                save.textContent = variant.compareAt ? `SAVE ${storefrontMoney(Number(variant.compareAt) - Number(variant.price))}` : '';
                save.classList.toggle('hidden', !variant.compareAt);
            }

            const specs = selector.querySelector('[data-pdp-specs]');
            const specsValue = selector.querySelector('[data-pdp-specs-value]');
            const specText = options.map((option) => variant.options?.[option.name]).filter(Boolean).join(' • ');
            specs?.classList.toggle('hidden', !specText);
            specs?.classList.toggle('flex', Boolean(specText));
            if (specsValue) specsValue.textContent = specText;

            const image = variant.optionImage || variant.image;
            if (image) {
                setPdpVisualImage(image);
            }

            document.querySelectorAll('[data-pdp-add-to-cart], [data-pdp-instant-buy], [data-pdp-sticky-add]').forEach((button) => {
                button.disabled = outOfStock || !enabled;
                if (button.matches('[data-pdp-add-to-cart]')) button.textContent = outOfStock || !enabled ? 'Sold out' : `Add to Cart · ${storefrontMoney(variant.price)}`;
            });

            selector.querySelectorAll('[data-pdp-option-value]').forEach((button) => {
                const selected = String(button.dataset.pdpOptionValue) === String(selectedValues[button.dataset.pdpOptionName]);
                // A value remains selectable if any SKU owns it. Selecting a
                // correlated legacy combination will then promote the other
                // dimensions to that SKU in select(), rather than trapping the
                // customer on the initially selected diagonal combination.
                const matches = variants.filter((candidate) => String(candidate.options?.[button.dataset.pdpOptionName] || '') === String(button.dataset.pdpOptionValue));
                const unavailable = !matches.length || matches.every((candidate) => candidate.enabled === false);
                const outOfStockValue = matches.length > 0 && matches.every((candidate) => Number(candidate.stock) < 1 || candidate.enabled === false);

                button.disabled = unavailable;
                button.setAttribute('aria-checked', String(selected));
                button.setAttribute('aria-disabled', String(unavailable || outOfStockValue));
                const visual = button.dataset.pdpOptionVisual || 'pill';
                const activeClasses = {
                    color: ['ring-2', 'ring-amber-500', 'dark:ring-amber-400', 'ring-offset-2', 'ring-offset-zinc-50', 'dark:ring-offset-zinc-950', 'border-2', 'border-zinc-900', 'dark:border-white', 'shadow-lg', 'scale-105'],
                    image: ['border-amber-500', 'dark:border-amber-400', 'ring-2', 'ring-amber-500/40', 'dark:ring-amber-400/40', 'ring-offset-2', 'ring-offset-zinc-50', 'dark:ring-offset-zinc-950', 'shadow-md'],
                    pill: ['border-amber-500', 'dark:border-amber-400', 'bg-amber-500/10', 'dark:bg-amber-400/10', 'text-zinc-950', 'dark:text-white', 'ring-1', 'ring-amber-500/40', 'shadow-sm', 'font-semibold'],
                };
                const inactiveClasses = {
                    color: ['border-2', 'border-zinc-300', 'dark:border-zinc-700', 'hover:scale-105', 'hover:border-zinc-500', 'dark:hover:border-zinc-400', 'shadow-sm'],
                    image: ['border-zinc-200', 'dark:border-zinc-800', 'hover:border-zinc-400', 'dark:hover:border-zinc-600'],
                    pill: ['border-zinc-200', 'dark:border-zinc-800', 'bg-white', 'dark:bg-zinc-900/60', 'hover:bg-zinc-50', 'dark:hover:bg-zinc-900', 'text-zinc-700', 'dark:text-zinc-300', 'hover:border-zinc-300', 'dark:hover:border-zinc-700'],
                };
                (activeClasses[visual] || activeClasses.pill).forEach((className) => button.classList.toggle(className, selected));
                (inactiveClasses[visual] || inactiveClasses.pill).forEach((className) => button.classList.toggle(className, !selected));
                button.classList.toggle('opacity-25', unavailable);
                button.querySelectorAll('[data-pdp-option-check]').forEach((check) => check.classList.toggle('hidden', !selected));
            });
            selector.querySelectorAll('[data-pdp-option-dropdown]').forEach((dropdown) => {
                dropdown.value = selectedValues[dropdown.dataset.pdpOptionName] || '';
                Array.from(dropdown.options).forEach((optionElement) => {
                    const matches = variants.filter((candidate) => String(candidate.options?.[dropdown.dataset.pdpOptionName] || '') === optionElement.value);
                    optionElement.disabled = !matches.length || matches.every((candidate) => candidate.enabled === false);
                });
            });
            const url = new URL(window.location.href);
            url.searchParams.set('variant', variant.id);
            window.history.replaceState({}, '', url);
            renderIcons();
        };

        const select = (optionName, value) => {
            selectedValues[optionName] = value;
            let variant = resolveVariant();

            if (!variant || String(variant.options?.[optionName] || '') !== String(value)) {
                const replacement = variants.find((candidate) => candidate.enabled !== false && String(candidate.options?.[optionName] || '') === String(value))
                    || variants.find((candidate) => String(candidate.options?.[optionName] || '') === String(value));
                if (!replacement) return;
                options.forEach((option) => {
                    selectedValues[option.name] = replacement.options?.[option.name] || selectedValues[option.name];
                });
                variant = replacement;
            }

            update(variant);
        };

        selector.querySelectorAll('[data-pdp-option-value]').forEach((button) => {
            button.addEventListener('click', () => select(button.dataset.pdpOptionName, button.dataset.pdpOptionValue));
        });
        selector.querySelectorAll('[data-pdp-option-dropdown]').forEach((dropdown) => {
            dropdown.addEventListener('change', (event) => select(dropdown.dataset.pdpOptionName, event.target.value));
        });

        update(initial);
    });
};

const installProductCardVariantPreviews = () => {
    document.querySelectorAll('[data-card-image]').forEach((image) => {
        if (!image.getAttribute('src')) image.src = '/images/image-not-found.png';
    });

    document.querySelectorAll('[data-product-card-variant-preview]').forEach((preview) => {
        const variants = parseStorefrontVariants(preview);
        const card = preview.closest('[data-storefront-product-card]');
        if (!variants.length || !card) return;

        preview.querySelectorAll('[data-card-variant-id]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const variant = variants.find((candidate) => String(candidate.id) === String(button.dataset.cardVariantId));
                if (!variant || variant.enabled === false) return;
                const outOfStock = Number(variant.stock) < 1;
                card.querySelectorAll('[data-card-variant-input]').forEach((input) => { input.value = variant.id; });
                card.querySelectorAll('[data-card-price]').forEach((target) => target.replaceChildren(storefrontMoney(variant.price)));
                card.querySelectorAll('[data-card-compare-at]').forEach((target) => {
                    target.textContent = variant.compareAt ? storefrontMoney(variant.compareAt) : '';
                    target.classList.toggle('hidden', !variant.compareAt);
                });
                if (variant.optionImage) card.querySelectorAll('[data-card-image]').forEach((image) => { image.src = variant.optionImage; });
                preview.querySelectorAll('[data-card-variant-id]').forEach((candidate) => {
                    const selected = candidate === button;
                    candidate.classList.toggle('ring-2', selected);
                    candidate.classList.toggle('ring-amber-500', selected);
                    candidate.classList.toggle('scale-110', selected);
                    candidate.classList.toggle('opacity-40', !selected && Number(variants.find((item) => String(item.id) === String(candidate.dataset.cardVariantId))?.stock || 0) < 1);
                });
                card.querySelectorAll('form[action$="/cart/items"] button').forEach((cartButton) => { cartButton.disabled = outOfStock; });
            });
        });
    });
};

const installQuickAddVariantModals = () => {
    const drawer = document.getElementById('cart-drawer');
    const modals = [...document.querySelectorAll('[data-quick-add-modal]')];
    if (!modals.length) return;

    const parse = (modal, name) => {
        try {
            const parsed = JSON.parse(modal.dataset[name] || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch {
            return [];
        }
    };

    const setText = (root, selector, value) => root.querySelector(selector)?.replaceChildren(value ?? '');

    modals.forEach((modal) => {
        const variants = parse(modal, 'quickAddVariants');
        const options = parse(modal, 'quickAddOptions');
        if (!variants.length || !options.length) return;

        let selected = variants[0];
        let selectedValues = {};
        let quantity = 1;
        let opener = null;
        const form = modal.querySelector('[data-quick-add-form]');
        const variantInput = modal.querySelector('[data-quick-add-variant-input]');
        const quantityInput = modal.querySelector('[data-quick-add-quantity-input]');
        const close = (restoreFocus = true) => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            if (restoreFocus) opener?.focus();
        };
        const isOutOfStock = (variant) => !variant || variant.enabled === false || Number(variant.stock || 0) < 1;
        const availability = (variant) => {
            if (isOutOfStock(variant)) return { text: 'Out of Stock', tone: 'rose', dot: 'bg-rose-500' };
            if (Number(variant.stock) <= 5) return { text: `Low Stock (Only ${variant.stock} remaining)`, tone: 'amber', dot: 'bg-amber-400' };
            return { text: `In Stock (${variant.stock} units)`, tone: 'emerald', dot: 'bg-emerald-400' };
        };
        const showError = (message = '') => {
            const error = modal.querySelector('[data-quick-add-error]');
            if (!error) return;
            error.textContent = message;
            error.classList.toggle('hidden', !message);
        };
        const setConfirmState = (state) => {
            const confirm = modal.querySelector('[data-quick-add-confirm]');
            if (!confirm) return;
            const classes = {
                default: ['bg-zinc-900', 'text-white', 'dark:bg-zinc-100', 'dark:text-zinc-950'],
                loading: ['bg-zinc-900', 'text-white', 'dark:bg-zinc-100', 'dark:text-zinc-950'],
                success: ['bg-emerald-600', 'text-white', 'ring-2', 'ring-emerald-500', 'scale-[0.99]'],
                soldOut: ['bg-zinc-200', 'text-zinc-400', 'border', 'border-dashed', 'border-zinc-300', 'dark:bg-zinc-800', 'dark:text-zinc-600', 'dark:border-zinc-700'],
            };
            Object.values(classes).flat().forEach((className) => confirm.classList.remove(className));
            confirm.classList.add(...classes[state]);
            confirm.classList.toggle('cursor-not-allowed', state === 'soldOut');
            confirm.disabled = state === 'loading' || state === 'success' || state === 'soldOut';
            ['default', 'loading', 'success', 'sold-out'].forEach((name) => modal.querySelector(`[data-quick-add-confirm-${name}]`)?.classList.toggle('hidden', name !== (state === 'soldOut' ? 'sold-out' : state)));
            ['default', 'loading', 'success', 'sold-out'].forEach((name) => modal.querySelector(`[data-quick-add-confirm-${name}]`)?.classList.toggle('flex', name === (state === 'soldOut' ? 'sold-out' : state)));
        };
        const setHeroImage = (image) => {
            if (!image) return;
            const hero = modal.querySelector('[data-quick-add-hero]');
            if (hero) hero.src = image;
            modal.querySelectorAll('[data-quick-add-thumbnail]').forEach((thumbnail) => {
                const active = thumbnail.dataset.quickAddImage === image;
                thumbnail.classList.toggle('scale-105', active);
                thumbnail.classList.toggle('border-amber-500', active);
                thumbnail.classList.toggle('ring-2', active);
                thumbnail.classList.toggle('ring-amber-500/30', active);
                thumbnail.classList.toggle('shadow-md', active);
                thumbnail.classList.toggle('border-zinc-200', !active);
                thumbnail.classList.toggle('opacity-70', !active);
                thumbnail.classList.toggle('dark:border-zinc-800', !active);
            });
        };
        const matches = (variant, ignored = null) => options.every((option) => option.name === ignored || String(variant.options?.[option.name] || '') === String(selectedValues[option.name] || ''));
        const resolve = () => variants.find((variant) => matches(variant)) || variants[0];
        const updateQuantity = () => {
            const max = Math.max(1, Number(selected?.stock || 0));
            quantity = Math.min(Math.max(1, quantity), max);
            setText(modal, '[data-quick-add-quantity]', String(quantity));
            if (quantityInput) quantityInput.value = String(quantity);
            modal.querySelector('[data-quick-add-decrease]')?.toggleAttribute('disabled', quantity <= 1 || isOutOfStock(selected));
            modal.querySelector('[data-quick-add-increase]')?.toggleAttribute('disabled', quantity >= max || isOutOfStock(selected));
        };
        const update = (variant) => {
            selected = variant;
            showError();
            if (variantInput) variantInput.value = variant.id;
            setText(modal, '[data-quick-add-selected-label]', variant.title || '');
            setText(modal, '[data-quick-add-sku]', variant.sku || '');
            setText(modal, '[data-quick-add-price]', storefrontMoney(variant.price));
            const compare = modal.querySelector('[data-quick-add-compare]');
            if (compare) {
                compare.textContent = variant.compareAt ? storefrontMoney(variant.compareAt) : '';
                compare.classList.toggle('hidden', !variant.compareAt);
            }
            const savings = Math.max(0, Number(variant.compareAt || 0) - Number(variant.price || 0));
            ['[data-quick-add-save]', '[data-quick-add-saving]'].forEach((selector) => {
                const target = modal.querySelector(selector);
                if (!target) return;
                const savingsPercent = variant.compareAt ? Math.round((savings / Number(variant.compareAt)) * 100) : 0;
                target.textContent = savings ? (selector.includes('saving') ? `Save ${storefrontMoney(savings)} (${savingsPercent}% OFF)` : `SAVE ${storefrontMoney(savings)}`) : '';
                target.classList.toggle('hidden', !savings);
            });
            const state = availability(variant);
            const stateWrap = modal.querySelector('[data-quick-add-availability]');
            stateWrap?.classList.remove('text-rose-500', 'text-amber-500', 'text-emerald-600', 'dark:text-emerald-400');
            stateWrap?.classList.add(state.tone === 'rose' ? 'text-rose-500' : state.tone === 'amber' ? 'text-amber-500' : 'text-emerald-600', ...(state.tone === 'emerald' ? ['dark:text-emerald-400'] : []));
            setText(modal, '[data-quick-add-availability-text]', state.text);
            const dot = modal.querySelector('[data-quick-add-availability-dot]');
            dot?.classList.remove('bg-rose-500', 'bg-amber-400', 'bg-emerald-400');
            dot?.classList.add(state.dot);
            setText(modal, '[data-quick-add-stock-note]', Number(variant.stock) > 0 ? `${variant.stock} units ready` : 'Zero stock');
            setHeroImage(variant.optionImage || variant.image);
            modal.querySelector('[data-quick-add-sold-out-overlay]')?.classList.toggle('hidden', !isOutOfStock(variant));
            const confirmText = modal.querySelector('[data-quick-add-confirm-text]');
            setConfirmState(isOutOfStock(variant) ? 'soldOut' : 'default');
            if (confirmText) confirmText.textContent = isOutOfStock(variant) ? 'Sold Out • Join Restock List' : `Add ${quantity > 1 ? `${quantity} Items` : 'to Cart'} • ${storefrontMoney(Number(variant.price) * quantity)}`;
            modal.querySelectorAll('[data-quick-add-option-value]').forEach((button) => {
                const optionName = button.dataset.quickAddOptionName;
                const value = button.dataset.quickAddOptionValue;
                const selectedButton = String(selectedValues[optionName] || '') === String(value || '');
                const candidates = variants.filter((candidate) => String(candidate.options?.[optionName] || '') === String(value || ''));
                const out = candidates.length > 0 && candidates.every(isOutOfStock);
                button.setAttribute('aria-checked', String(selectedButton));
                button.setAttribute('aria-disabled', String(out || !candidates.length));
                button.classList.toggle('ring-2', selectedButton);
                button.classList.toggle('ring-amber-500', selectedButton);
                button.classList.toggle('scale-105', selectedButton);
                button.classList.toggle('opacity-40', out);
                button.querySelectorAll('[data-quick-add-option-check]').forEach((check) => check.classList.toggle('hidden', !selectedButton));
                button.querySelectorAll('[data-quick-add-option-sold-out]').forEach((soldOut) => soldOut.classList.toggle('hidden', !out));
            });
            modal.querySelectorAll('[data-quick-add-option-dropdown]').forEach((select) => { select.value = selectedValues[select.dataset.quickAddOptionName] || ''; });
            updateQuantity();
            const refreshedText = modal.querySelector('[data-quick-add-confirm-text]');
            if (refreshedText && !isOutOfStock(variant)) refreshedText.textContent = `Add ${quantity > 1 ? `${quantity} Items` : 'to Cart'} • ${storefrontMoney(Number(variant.price) * quantity)}`;
            renderIcons();
        };
        const choose = (name, value) => {
            selectedValues[name] = value;
            let next = resolve();
            if (String(next.options?.[name] || '') !== String(value)) {
                next = variants.find((candidate) => candidate.enabled !== false && String(candidate.options?.[name] || '') === String(value)) || variants.find((candidate) => String(candidate.options?.[name] || '') === String(value));
                if (!next) return;
                options.forEach((option) => { selectedValues[option.name] = next.options?.[option.name] || selectedValues[option.name]; });
            }
            update(next);
        };
        const open = (variantId, originatingTrigger) => {
            opener = originatingTrigger;
            selected = variants.find((variant) => String(variant.id) === String(variantId)) || variants[0];
            selectedValues = Object.fromEntries(options.map((option) => [option.name, selected.options?.[option.name] || option.values?.[0] || '']));
            quantity = 1;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            update(selected);
            modal.querySelector('[data-quick-add-close]')?.focus();
        };

        document.querySelectorAll(`[data-quick-add-trigger][data-quick-add-product-id="${modal.dataset.quickAddProductId}"]`).forEach((trigger) => trigger.addEventListener('click', (event) => {
            event.preventDefault();
            const card = trigger.closest('[data-storefront-product-card]');
            open(card?.querySelector('[data-card-variant-input]')?.value || variants[0].id, trigger);
        }));
        modal.querySelectorAll('[data-quick-add-close], [data-quick-add-backdrop]').forEach((button) => button.addEventListener('click', close));
        modal.querySelectorAll('[data-quick-add-thumbnail]').forEach((button) => button.addEventListener('click', () => {
            setHeroImage(button.dataset.quickAddImage);
        }));
        modal.querySelectorAll('[data-quick-add-option-value]').forEach((button) => button.addEventListener('click', () => choose(button.dataset.quickAddOptionName, button.dataset.quickAddOptionValue)));
        modal.querySelectorAll('[data-quick-add-option-dropdown]').forEach((select) => select.addEventListener('change', (event) => choose(select.dataset.quickAddOptionName, event.target.value)));
        modal.querySelector('[data-quick-add-decrease]')?.addEventListener('click', () => { if (quantity > 1) { quantity -= 1; update(selected); } });
        modal.querySelector('[data-quick-add-increase]')?.addEventListener('click', () => { if (quantity < Number(selected.stock || 0)) { quantity += 1; update(selected); } });
        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (isOutOfStock(selected)) return;
            const submitter = modal.querySelector('[data-quick-add-confirm]');
            setConfirmState('loading');
            try {
                const response = await fetch(form.action, { method: form.method || 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                if (!response.ok) {
                    const payload = await response.json().catch(() => null);
                    throw new Error(payload?.errors?.quantity?.[0] || payload?.errors?.variant_id?.[0] || 'Unable to add the item to the cart.');
                }
                setConfirmState('success');
                await new Promise((resolve) => window.setTimeout(resolve, 700));
                await refreshCartDrawer(drawer);
                close(false);
                openCartDrawer(drawer, opener || submitter);
            } catch (error) {
                update(selected);
                showError(error instanceof Error ? error.message : 'Unable to add the item to the cart.');
            }
        });
        document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && !modal.classList.contains('hidden')) close(); });
        update(selected);
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

const installOrderTracking = () => {
    document.querySelectorAll('[data-order-tracking-verify]').forEach((form) => {
        const cells = [...form.querySelectorAll('[data-order-tracking-code-cell]')];
        const code = form.querySelector('[data-order-tracking-code]');
        const countdown = form.querySelector('[data-order-tracking-resend-countdown]');
        const resendWrap = form.querySelector('[data-order-tracking-resend-wrap]');
        const resendForm = form.querySelector('[data-order-tracking-resend-form]');
        if (!cells.length || !code) return;

        const sync = () => { code.value = cells.map((cell) => cell.value.replace(/\D/g, '').slice(0, 1)).join(''); };
        cells.forEach((cell, index) => {
            cell.addEventListener('input', () => {
                cell.value = cell.value.replace(/\D/g, '').slice(0, 1);
                sync();
                if (cell.value && index < cells.length - 1) cells[index + 1].focus();
            });
            cell.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !cell.value && index > 0) cells[index - 1].focus();
                if (event.key === 'ArrowLeft' && index > 0) cells[index - 1].focus();
                if (event.key === 'ArrowRight' && index < cells.length - 1) cells[index + 1].focus();
            });
            cell.addEventListener('paste', (event) => {
                event.preventDefault();
                const digits = event.clipboardData?.getData('text').replace(/\D/g, '').slice(0, cells.length) || '';
                [...digits].forEach((digit, offset) => { if (cells[index + offset]) cells[index + offset].value = digit; });
                sync();
                cells[Math.min(index + digits.length, cells.length - 1)]?.focus();
            });
        });
        let remaining = 42;
        const timer = window.setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                window.clearInterval(timer);
                resendWrap?.classList.add('hidden');
                resendForm?.classList.remove('hidden');
                return;
            }
            if (countdown) countdown.textContent = `Resend code in 00:${String(remaining).padStart(2, '0')}`;
        }, 1000);
        cells[0]?.focus();
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
    installAtelierMemberMenu();
    installAtelierCurrency();
    installMegaNavigation();
    installDeliveryMatrix();
    installCheckoutShippingQuotes();
    installAdminExperience();
    installAdminSidebarDrawer();
    installSuccessReceipt();
    installPdpWishlist();
    installStorefrontVariantSelectors();
    installPdpThumbnailSwitching();
    installProductCardVariantPreviews();
    installQuickAddVariantModals();
    installCustomerAccountTabs();
    installOrderTracking();
});
