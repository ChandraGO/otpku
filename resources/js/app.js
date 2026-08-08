import Alpine from '@alpinejs/csp';
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


Alpine.data('orderStatus', () => ({
    url: '',
    data: {
        status: 'processing',
        phone_number: null,
        otp_code: null,
        message: null,
        expires_at: null,
        provider_activation_id: null,
        terminal: false,
    },
    can: {
        ready: false,
        resend: false,
        complete: false,
        cancel: true,
        reactivate: false,
    },
    countdown: 'Menunggu nomor dari penyedia',
    copied: false,
    timer: null,
    fetching: false,
    lastChecked: 'Belum diperbarui',
    init() {
        this.url = this.$root.dataset.statusUrl || '';

        const encoded = this.$root.dataset.initial || '';
        if (encoded) {
            try {
                const bytes = Uint8Array.from(window.atob(encoded), (char) => char.charCodeAt(0));
                const initial = JSON.parse(new TextDecoder().decode(bytes));
                this.applyPayload(initial);
            } catch (_) {
                // Server-rendered fallback remains visible when initial JSON fails.
            }
        }

        this.tick();
        this.fetch();
        this.timer = window.setInterval(() => {
            this.tick();
            if (!this.data.terminal || this.data.status === 'expired' || this.data.status === 'cancelled') {
                this.fetch();
            }
        }, 3000);
    },
    destroy() {
        if (this.timer) window.clearInterval(this.timer);
    },
    applyPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        this.data = { ...this.data, ...payload };
        this.can = payload.can || this.deriveActions(this.data);
        this.lastChecked = this.formatChecked(payload.last_checked_at);
        this.tick();
    },
    deriveActions(payload) {
        const hasActivation = Boolean(payload.provider_activation_id);
        const terminal = Boolean(payload.terminal);
        const hasOtp = Boolean(payload.otp_code);
        const status = String(payload.status || '');
        return {
            ready: hasActivation && !terminal,
            resend: hasActivation && !terminal,
            complete: hasActivation && !terminal,
            cancel: (!hasActivation && ['processing', 'provider_pending'].includes(status)) || (hasActivation && !terminal && !hasOtp),
            reactivate: hasActivation && ['cancelled', 'expired', 'failed'].includes(status),
        };
    },
    async fetch() {
        if (!this.url || this.fetching) return;
        this.fetching = true;
        try {
            const response = await axios.get(this.url, {
                headers: { Accept: 'application/json' },
            });
            this.applyPayload(response.data);
        } catch (_) {
            this.lastChecked = 'Gagal memperbarui, mencoba lagi otomatis…';
        } finally {
            this.fetching = false;
        }
    },
    tick() {
        if (!this.data.expires_at) {
            this.countdown = this.data.provider_activation_id
                ? 'Menunggu durasi dari penyedia'
                : 'Menunggu nomor dari penyedia';
            return;
        }

        const remaining = Math.max(0, Math.floor((new Date(this.data.expires_at).getTime() - Date.now()) / 1000));
        const minutes = Math.floor(remaining / 60);
        const seconds = remaining % 60;
        this.countdown = remaining > 0
            ? `${minutes}m ${String(seconds).padStart(2, '0')}s`
            : '00m 00s';
    },
    formatChecked(value) {
        if (!value) return this.lastChecked;
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return this.lastChecked;
        return `Terakhir dicek ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    },
    get statusLabel() {
        const status = String(this.data.status || 'processing');
        const labels = {
            completed: 'Selesai',
            success: 'Berhasil',
            paid: 'Dibayar',
            active: 'Aktif',
            ready: 'Siap',
            pending: 'Menunggu',
            processing: 'Diproses',
            provider_pending: 'Menunggu penyedia',
            creating: 'Membuat transaksi',
            waiting: 'Menunggu',
            expired: 'Kedaluwarsa',
            cancelled: 'Dibatalkan',
            failed: 'Gagal',
            refunded: 'Dikembalikan',
        };
        return labels[status] || status.replaceAll('_', ' ');
    },
    get waitingForActivation() {
        return !this.data.provider_activation_id && ['processing', 'provider_pending'].includes(String(this.data.status || ''));
    },
    async copy(value) {
        if (!value) return;
        await navigator.clipboard.writeText(value);
        this.copied = true;
        window.setTimeout(() => { this.copied = false; }, 1400);
    },
}));

document.addEventListener('change', (event) => {
    const field = event.target;
    if (!(field instanceof HTMLSelectElement || field instanceof HTMLInputElement)) return;

    const form = field.closest('form[data-auto-filter]');
    if (!form) return;

    const isFilterField = field instanceof HTMLSelectElement
        || ['checkbox', 'radio'].includes(field.type);
    if (!isFilterField) return;

    form.requestSubmit();
});

window.addEventListener('storage', (event) => {
    if (event.key === 'theme' && ['dark', 'light'].includes(event.newValue)) {
        Alpine.store('theme').set(event.newValue, false);
    }
});

Alpine.start();
