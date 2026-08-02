import Alpine from 'alpinejs';
import axios from 'axios';
import QRCode from 'qrcode';

window.Alpine = Alpine;
window.axios = axios;
window.QRCode = QRCode;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const THEME_KEY = 'kodeotp.theme';

const savedTheme = () => {
    try {
        const value = localStorage.getItem(THEME_KEY) || localStorage.getItem('theme');
        return ['dark', 'light'].includes(value) ? value : 'dark';
    } catch (_) {
        return 'dark';
    }
};

const applyTheme = (theme) => {
    const normalized = theme === 'light' ? 'light' : 'dark';
    document.documentElement.classList.toggle('dark', normalized === 'dark');
    document.documentElement.dataset.theme = normalized;
    document.documentElement.style.colorScheme = normalized;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', normalized === 'dark' ? 'true' : 'false');
        button.setAttribute('aria-label', normalized === 'dark' ? 'Gunakan tema terang' : 'Gunakan tema gelap');
    });
    return normalized;
};

window.KodeOtpTheme = {
    current: savedTheme(),
    set(theme) {
        this.current = applyTheme(theme);
        try {
            localStorage.setItem(THEME_KEY, this.current);
            localStorage.removeItem('theme');
        } catch (_) {}
        if (window.Alpine?.store('theme')) {
            window.Alpine.store('theme').current = this.current;
        }
    },
    toggle() {
        this.set(this.current === 'dark' ? 'light' : 'dark');
    },
};

applyTheme(window.KodeOtpTheme.current);

Alpine.store('theme', {
    current: window.KodeOtpTheme.current,
    init() {
        this.current = applyTheme(window.KodeOtpTheme.current);
    },
    set(theme) {
        window.KodeOtpTheme.set(theme);
        this.current = window.KodeOtpTheme.current;
    },
    toggle() {
        window.KodeOtpTheme.toggle();
        this.current = window.KodeOtpTheme.current;
    },
});

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

const initPasswordToggles = () => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        if (button.dataset.ready === '1') return;
        button.dataset.ready = '1';
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;
            const visible = input.type === 'text';
            input.type = visible ? 'password' : 'text';
            button.setAttribute('aria-pressed', visible ? 'false' : 'true');
            button.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
            button.querySelector('[data-eye-open]')?.classList.toggle('hidden', !visible);
            button.querySelector('[data-eye-closed]')?.classList.toggle('hidden', visible);
        });
    });
};

const formatCountdown = (seconds) => {
    const value = Math.max(0, Math.floor(seconds));
    const minutes = Math.floor(value / 60);
    const remainder = value % 60;
    return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
};

const initOtpTimers = () => {
    document.querySelectorAll('[data-otp-timer]').forEach((root) => {
        if (root.dataset.ready === '1') return;
        root.dataset.ready = '1';
        const expiryOutput = root.querySelector('[data-otp-expiry]');
        const resendOutput = root.querySelector('[data-otp-resend]');
        const resendButton = root.querySelector('[data-otp-resend-button]');
        const expiresAt = Number(root.dataset.expiresAt || 0);
        let resendRemaining = Number(root.dataset.resendRemaining || 0);

        const render = () => {
            if (expiryOutput) {
                if (expiresAt > 0) {
                    const expiryRemaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
                    expiryOutput.textContent = expiryRemaining > 0 ? formatCountdown(expiryRemaining) : 'Kedaluwarsa';
                } else {
                    expiryOutput.textContent = 'Belum terkirim';
                }
            }

            if (resendOutput) {
                resendOutput.textContent = resendRemaining > 0 ? formatCountdown(resendRemaining) : 'Siap dikirim';
            }

            if (resendButton) {
                resendButton.disabled = resendRemaining > 0;
            }

            if (resendRemaining > 0) resendRemaining -= 1;
        };

        render();
        window.setInterval(render, 1000);
    });
};

const initLiveCatalog = () => {
    document.querySelectorAll('[data-live-catalog-form]').forEach((form) => {
        if (form.dataset.ready === '1') return;
        form.dataset.ready = '1';
        let timer = null;
        let controller = null;
        const status = document.querySelector('[data-live-catalog-status]');

        const run = async () => {
            controller?.abort();
            controller = new AbortController();
            const params = new URLSearchParams(new FormData(form));
            const url = `${form.action || window.location.pathname}?${params.toString()}`;
            if (status) status.textContent = 'Mencari layanan…';
            form.setAttribute('aria-busy', 'true');

            try {
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const html = await response.text();
                const documentResult = new DOMParser().parseFromString(html, 'text/html');
                const next = documentResult.querySelector('[data-live-catalog-results]');
                const current = document.querySelector('[data-live-catalog-results]');
                if (!next || !current) throw new Error('Hasil layanan tidak ditemukan.');
                current.innerHTML = next.innerHTML;
                window.history.replaceState({}, '', url);
                if (status) status.textContent = 'Hasil diperbarui otomatis.';
            } catch (error) {
                if (error.name !== 'AbortError' && status) {
                    status.textContent = 'Pencarian gagal. Silakan coba lagi.';
                }
            } finally {
                form.removeAttribute('aria-busy');
            }
        };

        const schedule = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(run, 350);
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            run();
        });
        form.querySelectorAll('input[type="search"], input[name="q"]').forEach((input) => input.addEventListener('input', schedule));
        form.querySelectorAll('select, input[type="checkbox"]').forEach((input) => input.addEventListener('change', run));
    });
};

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-theme-toggle]');
    if (button) window.KodeOtpTheme.toggle();
});

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
    initOtpTimers();
    initLiveCatalog();
});

window.addEventListener('storage', (event) => {
    if (event.key === THEME_KEY && ['dark', 'light'].includes(event.newValue)) {
        window.KodeOtpTheme.current = applyTheme(event.newValue);
        Alpine.store('theme').current = event.newValue;
    }
});

Alpine.start();
