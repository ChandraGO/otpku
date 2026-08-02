import Alpine from 'alpinejs';
import axios from 'axios';
import QRCode from 'qrcode';

window.Alpine = Alpine;
window.axios = axios;
window.QRCode = QRCode;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const resolveTheme = () => {
    const saved = localStorage.getItem('theme');
    if (saved === 'dark' || saved === 'light') return saved;

    const preferred = document.documentElement.dataset.defaultTheme;
    if (preferred === 'dark' || preferred === 'light') return preferred;

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
};

const applyTheme = (theme) => {
    document.documentElement.classList.toggle('dark', theme === 'dark');
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
};

Alpine.store('theme', {
    current: resolveTheme(),
    init() {
        applyTheme(this.current);
    },
    set(theme) {
        if (!['dark', 'light'].includes(theme)) return;
        this.current = theme;
        localStorage.setItem('theme', theme);
        applyTheme(theme);
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
        Alpine.store('theme').current = event.newValue;
        applyTheme(event.newValue);
    }
});

Alpine.start();
