import Alpine from 'alpinejs';
import axios from 'axios';
import QRCode from 'qrcode';

window.Alpine = Alpine;
window.axios = axios;
window.QRCode = QRCode;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

Alpine.data('themeSwitcher', () => ({
    theme: localStorage.getItem('theme') || document.documentElement.dataset.defaultTheme || 'dark',
    init() { this.apply(); },
    toggle() { this.theme = this.theme === 'dark' ? 'light' : 'dark'; localStorage.setItem('theme', this.theme); this.apply(); },
    apply() { document.documentElement.classList.toggle('dark', this.theme === 'dark'); },
}));

Alpine.data('qrCode', (value) => ({
    src: '',
    async init() { if (value) this.src = await QRCode.toDataURL(value, { width: 280, margin: 1, errorCorrectionLevel: 'M' }); },
}));

Alpine.start();
