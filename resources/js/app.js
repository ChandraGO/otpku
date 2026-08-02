import Alpine from 'alpinejs';
import axios from 'axios';
import QRCode from 'qrcode';

window.Alpine = Alpine;
window.axios = axios;
window.QRCode = QRCode;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

const readSavedTheme = () => {
    try {
        const saved = localStorage.getItem('theme');
        return saved === 'dark' || saved === 'light' ? saved : null;
    } catch (_) {
        return null;
    }
};

const resolveTheme = () => {
    const saved = readSavedTheme();
    if (saved) return saved;

    const preferred = document.documentElement.dataset.defaultTheme;
    if (preferred === 'dark' || preferred === 'light') return preferred;

    return 'dark';
};

const applyTheme = (theme) => {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
};

let themeSyncTimer;
const syncThemeToAccount = (theme) => {
    const url = document.documentElement.dataset.themeSyncUrl;
    if (!url) return;

    window.clearTimeout(themeSyncTimer);
    themeSyncTimer = window.setTimeout(() => {
        axios.put(url, { theme }).catch(() => {
            // Tampilan lokal tetap dipertahankan ketika sinkronisasi akun gagal.
        });
    }, 200);
};

Alpine.store('theme', {
    current: resolveTheme(),
    init() {
        applyTheme(this.current);
    },
    set(theme, persist = true) {
        if (!['dark', 'light'].includes(theme)) return;

        this.current = theme;
        try {
            localStorage.setItem('theme', theme);
        } catch (_) {}
        applyTheme(theme);

        if (persist) syncThemeToAccount(theme);
    },
    toggle() {
        this.set(this.current === 'dark' ? 'light' : 'dark');
    },
});

Alpine.data('themeSwitcher', () => ({
    get theme() {
        return Alpine.store('theme').current;
    },
    toggle() {
        Alpine.store('theme').toggle();
    },
}));

Alpine.data('liveServiceSearch', ({ endpoint, initialQuery = '', initialSort = 'popular' }) => ({
    query: initialQuery,
    sort: initialSort,
    loading: false,
    controller: null,
    timer: null,
    init() {
        this.$watch('query', () => this.schedule());
        this.$watch('sort', () => this.search());
    },
    schedule() {
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.search(), 250);
    },
    async search(url = null) {
        this.controller?.abort();
        const controller = new AbortController();
        this.controller = controller;
        this.loading = true;

        const target = new URL(url || endpoint, window.location.origin);
        target.searchParams.set('q', this.query.trim());
        target.searchParams.set('sort', this.sort);
        target.searchParams.set('partial', '1');

        try {
            const response = await fetch(target, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            this.$refs.results.innerHTML = await response.text();

            const browserUrl = new URL(target);
            browserUrl.searchParams.delete('partial');
            if (!this.query.trim()) browserUrl.searchParams.delete('q');
            if (this.sort === 'popular') browserUrl.searchParams.delete('sort');
            window.history.replaceState({}, '', browserUrl);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.$dispatch('search-error');
            }
        } finally {
            if (this.controller === controller) this.loading = false;
        }
    },
    follow(event) {
        const link = event.target.closest('a');
        if (!link || !link.closest('[data-live-pagination]')) return;
        event.preventDefault();
        this.search(link.href);
    },
}));

Alpine.data('qrCode', (value) => ({
    src: '',
    async init() {
        if (value) {
            this.src = await QRCode.toDataURL(value, {
                width: 280,
                margin: 1,
                errorCorrectionLevel: 'M',
            });
        }
    },
}));

Alpine.data('copyText', (value) => ({
    copied: false,
    async copy() {
        await navigator.clipboard.writeText(value);
        this.copied = true;
        window.setTimeout(() => { this.copied = false; }, 1600);
    },
}));

window.addEventListener('storage', (event) => {
    if (event.key === 'theme' && ['dark', 'light'].includes(event.newValue)) {
        Alpine.store('theme').set(event.newValue, false);
    }
});

Alpine.start();
