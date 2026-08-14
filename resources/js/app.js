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
    actionUrl: '',
    data: {
        status: 'processing',
        payment_channel: 'balance',
        payment_status: 'paid',
        payment_pay_amount: null,
        payment_expires_at: null,
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
    paymentCountdown: '--:--',
    paymentRemaining: null,
    paymentExpiryRefreshTriggered: false,
    copied: false,
    clockTimer: null,
    fetchTimer: null,
    fetching: false,
    actionBusy: '',
    actionMessage: '',
    actionError: false,
    lastChecked: 'Belum diperbarui',
    init() {
        this.url = this.$root.dataset.statusUrl || '';
        this.actionUrl = this.$root.dataset.actionUrl || '';

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

        // Countdown runs every second so the QRIS expiry timer feels immediate.
        this.clockTimer = window.setInterval(() => this.tick(), 1000);

        // Remote status polling remains lighter and runs every three seconds.
        this.fetchTimer = window.setInterval(() => {
            if (!this.data.terminal || this.data.status === 'expired' || this.data.status === 'cancelled') {
                this.fetch();
            }
        }, 3000);
    },
    destroy() {
        if (this.clockTimer) window.clearInterval(this.clockTimer);
        if (this.fetchTimer) window.clearInterval(this.fetchTimer);
    },
    applyPayload(payload) {
        if (!payload || typeof payload !== 'object') return;
        const previousPaymentExpiry = this.data.payment_expires_at;
        this.data = { ...this.data, ...payload };
        if (previousPaymentExpiry !== this.data.payment_expires_at) {
            this.paymentExpiryRefreshTriggered = false;
        }
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
            cancel: (payload.payment_channel === 'paykita' && payload.payment_status === 'pending' && status === 'awaiting_payment') || (!hasActivation && ['processing', 'provider_pending'].includes(status)) || (hasActivation && !terminal && !hasOtp),
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
    async refreshStatus() {
        this.actionMessage = '';
        this.actionError = false;
        await this.fetch();
    },
    async runAction(action) {
        if (!this.actionUrl || this.actionBusy) return;

        this.actionBusy = action;
        this.actionMessage = '';
        this.actionError = false;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await axios.post(this.actionUrl, { action }, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                },
            });

            const payload = response.data?.data;
            if (payload) this.applyPayload(payload);

            this.actionMessage = response.data?.message || 'Aksi berhasil diproses.';
            await this.fetch();
        } catch (error) {
            const response = error?.response?.data;
            const validationMessage = response?.errors
                ? Object.values(response.errors).flat().filter(Boolean)[0]
                : null;

            this.actionError = true;
            this.actionMessage = response?.message || validationMessage || 'Aksi gagal diproses. Silakan coba lagi.';
            await this.fetch();
        } finally {
            this.actionBusy = '';
        }
    },
    tick() {
        this.tickPayment();
        this.tickProvider();
    },
    tickPayment() {
        const paymentStatus = String(this.data.payment_status || '');
        const isDirectPayment = this.data.payment_channel === 'paykita';

        if (!isDirectPayment) {
            this.paymentRemaining = null;
            this.paymentCountdown = '';
            return;
        }

        if (['expired', 'cancelled', 'failed'].includes(paymentStatus)) {
            this.paymentRemaining = 0;
            this.paymentCountdown = 'Kedaluwarsa';
            return;
        }

        if (paymentStatus === 'paid') {
            this.paymentRemaining = null;
            this.paymentCountdown = 'Terbayar';
            return;
        }

        if (paymentStatus !== 'pending' || !this.data.payment_expires_at) {
            this.paymentRemaining = null;
            this.paymentCountdown = 'Menunggu';
            return;
        }

        const remaining = this.secondsUntil(this.data.payment_expires_at);
        this.paymentRemaining = remaining;

        if (remaining <= 0) {
            this.paymentCountdown = 'Kedaluwarsa';
            if (!this.paymentExpiryRefreshTriggered) {
                this.paymentExpiryRefreshTriggered = true;
                window.setTimeout(() => this.fetch(), 0);
            }
            return;
        }

        this.paymentExpiryRefreshTriggered = false;
        this.paymentCountdown = this.formatDuration(remaining);
    },
    tickProvider() {
        if (this.data.payment_status !== 'paid') {
            this.countdown = 'Menunggu pembayaran';
            return;
        }

        if (!this.data.expires_at) {
            this.countdown = this.data.provider_activation_id
                ? 'Menunggu durasi dari penyedia'
                : 'Menunggu nomor dari penyedia';
            return;
        }

        const remaining = this.secondsUntil(this.data.expires_at);
        this.countdown = remaining > 0 ? this.formatDuration(remaining) : '00:00';
    },
    secondsUntil(value) {
        const target = new Date(value).getTime();
        if (!Number.isFinite(target)) return 0;
        return Math.max(0, Math.floor((target - Date.now()) / 1000));
    },
    formatDuration(totalSeconds) {
        const seconds = Math.max(0, Number(totalSeconds) || 0);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const remainder = seconds % 60;
        if (hours > 0) {
            return `${hours}:${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
        }
        return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
    },
    formatChecked(value) {
        if (!value) return this.lastChecked;
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return this.lastChecked;
        return `Terakhir dicek ${date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    },
    get paymentActive() {
        return this.data.payment_channel === 'paykita'
            && this.data.payment_status === 'pending'
            && (this.paymentRemaining === null || this.paymentRemaining > 0);
    },
    get paymentExpired() {
        if (this.data.payment_channel !== 'paykita') return false;
        if (['expired', 'cancelled', 'failed'].includes(String(this.data.payment_status || ''))) return true;
        return this.data.payment_status === 'pending' && this.paymentRemaining === 0;
    },
    get paymentDeadlineLabel() {
        if (!this.data.payment_expires_at) return '—';
        const date = new Date(this.data.payment_expires_at);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleString('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
    },
    get statusLabel() {
        if (this.paymentExpired && String(this.data.status || '') === 'awaiting_payment') {
            return 'QRIS kedaluwarsa';
        }

        const status = String(this.data.status || 'processing');
        const labels = {
            completed: 'Selesai',
            success: 'Berhasil',
            paid: 'Dibayar',
            active: 'Aktif',
            ready: 'Siap',
            pending: 'Menunggu',
            awaiting_payment: 'Menunggu pembayaran',
            payment_failed: 'Pembayaran gagal',
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
        return this.data.payment_status === 'paid'
            && !this.data.provider_activation_id
            && ['processing', 'provider_pending'].includes(String(this.data.status || ''));
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
