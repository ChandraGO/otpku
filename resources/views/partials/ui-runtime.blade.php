<script>
(() => {
    if (window.__otpkuUiRuntimeLoaded) return;
    window.__otpkuUiRuntimeLoaded = true;

    const root = document.documentElement;
    const allowedThemes = ['dark', 'light'];

    const currentTheme = () => {
        const active = root.dataset.theme;
        if (allowedThemes.includes(active)) return active;
        try {
            const saved = localStorage.getItem('theme');
            if (allowedThemes.includes(saved)) return saved;
        } catch (_) {}
        const fallback = root.dataset.defaultTheme;
        return allowedThemes.includes(fallback) ? fallback : 'dark';
    };

    const refreshThemeControls = () => {
        const theme = currentTheme();
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.querySelectorAll('[data-theme-icon]').forEach((icon) => {
                icon.hidden = icon.dataset.themeIcon !== theme;
            });
            const nextLabel = theme === 'dark' ? 'Aktifkan mode terang' : 'Aktifkan mode gelap';
            button.setAttribute('aria-label', nextLabel);
            button.setAttribute('title', nextLabel);
            button.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
        });
        document.querySelectorAll('[data-theme-select]').forEach((select) => {
            if (select.value !== theme) select.value = theme;
        });
    };

    let themeSyncTimer;
    const syncThemeToAccount = (theme) => {
        const endpoint = root.dataset.themeSyncUrl;
        if (!endpoint) return;
        window.clearTimeout(themeSyncTimer);
        themeSyncTimer = window.setTimeout(() => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(endpoint, {
                method: 'PUT', credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
                body: JSON.stringify({ theme }),
            }).catch(() => {});
        }, 180);
    };

    const applyTheme = (theme, persist = true, syncAccount = true) => {
        const safeTheme = allowedThemes.includes(theme) ? theme : 'dark';
        root.classList.toggle('dark', safeTheme === 'dark');
        root.dataset.theme = safeTheme;
        root.style.colorScheme = safeTheme;
        if (persist) {
            try { localStorage.setItem('theme', safeTheme); } catch (_) {}
        }
        refreshThemeControls();
        if (syncAccount) syncThemeToAccount(safeTheme);
    };

    const refreshPasswordField = (field) => {
        const input = field.querySelector('input');
        const button = field.querySelector('[data-password-toggle]');
        if (!input || !button) return;
        const visible = input.type === 'text';
        field.querySelectorAll('[data-password-icon]').forEach((icon) => {
            icon.hidden = icon.dataset.passwordIcon === (visible ? 'show' : 'hide');
        });
        const label = visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    };

    const setMobileMenu = (button, open) => {
        const menuId = button?.getAttribute('aria-controls');
        const menu = menuId ? document.getElementById(menuId) : document.querySelector('[data-mobile-menu]');
        if (!button || !menu) return;
        menu.hidden = !open;
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const setSidebar = (open) => {
        const panel = document.querySelector('[data-sidebar-panel]');
        const overlay = document.querySelector('[data-sidebar-overlay]');
        if (!panel) return;
        panel.style.transform = open ? 'translateX(0)' : '';
        panel.dataset.open = open ? 'true' : 'false';
        if (overlay) overlay.hidden = !open;
        document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
    };

    const setSidebarCollapsed = (collapsed, persist = true) => {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        document.querySelectorAll('[data-sidebar-collapse-toggle]').forEach((button) => {
            const label = collapsed ? 'Tampilkan sidebar' : 'Sembunyikan sidebar';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        });
        if (persist) {
            try { localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0'); } catch (_) {}
        }
    };

    const animateCounter = (element) => {
        const target = Number(element.dataset.countTo || 0);
        const duration = Number(element.dataset.countDuration || 1100);
        const suffix = element.dataset.countSuffix || '';
        const prefix = element.dataset.countPrefix || '';
        const formatter = new Intl.NumberFormat('id-ID');
        const started = performance.now();
        const frame = (now) => {
            const progress = Math.min((now - started) / duration, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = prefix + formatter.format(Math.round(target * eased)) + suffix;
            if (progress < 1) requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);
    };

    document.addEventListener('click', (event) => {
        const quickAmount = event.target.closest('[data-quick-amount]');
        if (quickAmount) {
            event.preventDefault();
            const form = quickAmount.closest('form');
            const input = form?.querySelector('[name=amount]');
            if (input) {
                input.value = quickAmount.dataset.quickAmount || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.focus({ preventScroll: true });
            }
            return;
        }

        const themeButton = event.target.closest('[data-theme-toggle]');
        if (themeButton) {
            event.preventDefault();
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
            return;
        }

        const passwordButton = event.target.closest('[data-password-toggle]');
        if (passwordButton) {
            event.preventDefault();
            const field = passwordButton.closest('[data-password-field]');
            const input = field?.querySelector('input');
            if (!field || !input) return;
            const selectionStart = input.selectionStart;
            const selectionEnd = input.selectionEnd;
            input.type = input.type === 'password' ? 'text' : 'password';
            refreshPasswordField(field);
            input.focus({ preventScroll: true });
            try { input.setSelectionRange(selectionStart, selectionEnd); } catch (_) {}
            return;
        }

        const mobileMenuButton = event.target.closest('[data-mobile-menu-toggle]');
        if (mobileMenuButton) {
            event.preventDefault();
            setMobileMenu(mobileMenuButton, mobileMenuButton.getAttribute('aria-expanded') !== 'true');
            return;
        }

        const mobileMenuClose = event.target.closest('[data-mobile-menu-close]');
        if (mobileMenuClose) {
            const menu = mobileMenuClose.closest('[data-mobile-menu]');
            const button = menu?.id ? document.querySelector(`[data-mobile-menu-toggle][aria-controls="${menu.id}"]`) : null;
            if (button) setMobileMenu(button, false);
            return;
        }

        if (event.target.closest('[data-sidebar-open]')) {
            event.preventDefault();
            setSidebar(true);
            return;
        }

        if (event.target.closest('[data-sidebar-close], [data-sidebar-overlay]')) {
            event.preventDefault();
            setSidebar(false);
            return;
        }

        const collapseButton = event.target.closest('[data-sidebar-collapse-toggle]');
        if (collapseButton) {
            event.preventDefault();
            setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
            return;
        }

        const flashClose = event.target.closest('[data-flash-close]');
        if (flashClose) {
            flashClose.closest('[data-flash-message]')?.remove();
        }
    });

    document.addEventListener('change', (event) => {
        const select = event.target.closest('[data-theme-select]');
        if (select && allowedThemes.includes(select.value)) applyTheme(select.value);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebar(false);
    });

    window.addEventListener('storage', (event) => {
        if (event.key === 'theme' && allowedThemes.includes(event.newValue)) applyTheme(event.newValue, false, false);
        if (event.key === 'sidebar-collapsed') setSidebarCollapsed(event.newValue === '1', false);
    });

    const init = () => {
        applyTheme(currentTheme(), false, false);
        document.querySelectorAll('[data-password-field]').forEach(refreshPasswordField);
        refreshThemeControls();
        if (document.querySelector('[data-force-sidebar-expanded]')) {
            setSidebarCollapsed(false, false);
        } else {
            try { setSidebarCollapsed(localStorage.getItem('sidebar-collapsed') === '1', false); } catch (_) {}
        }

        const counters = document.querySelectorAll('[data-count-to]');
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting || entry.target.dataset.counted === '1') return;
                    entry.target.dataset.counted = '1';
                    animateCounter(entry.target);
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.35 });
            counters.forEach((counter) => observer.observe(counter));
        } else {
            counters.forEach(animateCounter);
        }
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
</script>
