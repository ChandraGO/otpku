import React, { useEffect, useState } from "react";
import { Loader2, Save, Send, Mail } from "lucide-react";
import { PasswordInput } from "@/components/ui/password-input";
import { Switch } from "@/components/ui/switch";
import { toast } from "sonner";
import { http, errMsg } from "@/lib/api";

export const CATEGORIES = [
  { key: "site", label: "Situs & SEO", desc: "Identitas bisnis, favicon, thumbnail berbagi, dan metadata mesin pencarian." },
  { key: "contact", label: "Contact & Sosmed", desc: "Atur kontak dan username sosial media yang tampil di landing page serta Dashboard. Isi username tanpa @; setiap item bisa ditampilkan atau disembunyikan." },
  { key: "verification", label: "Verifikasi", desc: "Atur masa berlaku OTP email dan jeda kirim ulang." },
  { key: "orders", label: "Pesanan", desc: "Atur batas kedaluwarsa dan kebijakan pengembalian pesanan." },
  { key: "pricing", label: "Harga", desc: "Harga jual dihitung dari modal riil provider setelah biaya beli coin dan pajak. Sistem lalu mengambil nilai yang lebih tinggi antara hasil markup atau modal + profit minimum aman, sehingga harga tidak jatuh di bawah target keuntungan." },
  { key: "smtp", label: "SMTP", desc: "Pengiriman email sistem dan konfigurasi server SMTP." },
  { key: "topup", label: "Isi Saldo", desc: "Batas minimum dan maksimum isi saldo pelanggan." },
  { key: "paykita", label: "Gateway Pembayaran", desc: "Kredensial gateway QRIS untuk checkout dan isi saldo (tidak terlihat oleh pengguna)." },
  { key: "smsvirtual", label: "Provider Nomor", desc: "Koneksi provider nomor, timeout, dan pengawasan saldo penyedia." },
  { key: "security", label: "Keamanan", desc: "Rahasia webhook dan pengamanan integrasi backend." },
  { key: "notifications", label: "Notifikasi & Telegram", desc: "Bot Telegram dan email tujuan untuk tiket bantuan." },
  { key: "tiers", label: "Level Akun & Markup", desc: "Tambahan markup untuk Member, Reseller, dan VIP di atas markup global, serta syarat deposit naik level." },
  { key: "backup", label: "Backup Data", desc: "Ekspor seluruh data dan kebijakan retensi backup." },
];

const LABELS = {
  site_name: "Nama brand / navbar", tagline: "Tagline", business_email: "Email bisnis", favicon_url: "URL favicon",
  share_thumbnail_url: "URL thumbnail berbagi", meta_title: "Judul tab browser / SEO", meta_description: "Deskripsi SEO", meta_keywords: "Kata kunci SEO",
  otp_length: "Panjang kode OTP", otp_ttl_seconds: "Masa berlaku OTP (detik)", resend_cooldown_seconds: "Jeda kirim ulang (detik)",
  max_attempts: "Maks percobaan", require_email_verification: "Wajib verifikasi email",
  order_expiry_seconds: "Kedaluwarsa pesanan (detik)", auto_refund_on_expire: "Refund otomatis saat kedaluwarsa",
  cancel_cooldown_seconds: "Jeda sebelum bisa batal (detik)", auto_refresh_seconds: "Auto-refresh dasbor (detik)",
  refund_window_seconds: "Jendela refund (detik)", allow_manual_cancel: "Izinkan batal manual",
  markup_percent: "Markup (%)", fixed_fee: "Biaya tetap (Rp)", rounding_to: "Pembulatan ke (Rp)", rate_to_idr: "Kurs dasar provider → IDR", provider_cost_multiplier: "Biaya beli per 1 unit / coin (Rp)", provider_tax_percent: "Pajak provider (%)", min_profit: "Profit minimum override (Rp)", safety_min_profit: "Profit minimum aman global (Rp)",
  host: "SMTP host", port: "Port", encryption: "Enkripsi (tls/ssl/none)", username: "Username", password: "Password", from_email: "Email pengirim", from_name: "Nama pengirim", enabled: "Aktif",
  min_amount: "Minimum isi saldo (Rp)", max_amount: "Maksimum isi saldo (Rp)", auto_approve: "Setujui otomatis", note: "Catatan untuk pelanggan",
  api_key: "API Key", webhook_secret: "Webhook secret", order_ttl_seconds: "Masa aktif order (detik)",
  timeout_seconds: "Timeout (detik)", low_balance_threshold: "Ambang saldo provider rendah", auto_search_operator: "Auto cari operator", auto_search_server: "Auto cari server",
  allow_public_api: "Izinkan API publik", rate_limit_per_minute: "Rate limit / menit", ip_allowlist: "IP allowlist (pisah koma)",
  telegram_bot_token: "Telegram bot token", telegram_chat_id: "Telegram chat ID", notify_ticket: "Notifikasi tiket", notify_topup: "Notifikasi isi saldo", email_ticket_to: "Email tujuan tiket",
  auto_backup: "Backup otomatis", retention_days: "Retensi (hari)", last_backup_at: "Backup terakhir",
  member_markup_percent: "Markup Member (%)", member_fixed_fee: "Biaya tetap Member (Rp)",
  reseller_markup_percent: "Markup Reseller (%)", reseller_fixed_fee: "Biaya tetap Reseller (Rp)",
  vip_markup_percent: "Markup VIP (%)", vip_fixed_fee: "Biaya tetap VIP (Rp)",
  reseller_min_topup: "Min deposit Reseller (Rp)", vip_min_topup: "Min deposit VIP (Rp)",
  member_benefits: "Benefit Member", reseller_benefits: "Benefit Reseller", vip_benefits: "Benefit VIP",
};


const CONTACT_FIELDS = [
  { key: "website", enabled: "website_enabled", label: "Link Website", placeholder: "contoh: dapetotp.com" },
  { key: "phone", enabled: "phone_enabled", label: "Nomor HP", placeholder: "contoh: +6281234567890" },
  { key: "support_email", enabled: "support_email_enabled", label: "Email Support", placeholder: "contoh: support@dapetotp.com" },
  { key: "telegram", enabled: "telegram_enabled", label: "Username Telegram", placeholder: "contoh: chandrahafi" },
  { key: "instagram", enabled: "instagram_enabled", label: "Username Instagram", placeholder: "contoh: chandrahafi" },
  { key: "tiktok", enabled: "tiktok_enabled", label: "Username TikTok", placeholder: "contoh: chandrahafi" },
  { key: "x", enabled: "x_enabled", label: "Username X", placeholder: "contoh: chandrahafi" },
  { key: "facebook", enabled: "facebook_enabled", label: "Username Facebook", placeholder: "contoh: chandrahafi" },
  { key: "youtube", enabled: "youtube_enabled", label: "Username YouTube", placeholder: "contoh: chandrahafi" },
];

const SECRET = ["password", "api_key", "webhook_secret", "telegram_bot_token"];
const LONG_TEXT = new Set(["meta_description", "note", "member_benefits", "reseller_benefits", "vip_benefits"]);

export const AdminSettings = ({ category, values, onSaved }) => {
  const [form, setForm] = useState(values || {});
  const [busy, setBusy] = useState(false);
  const [testEmail, setTestEmail] = useState("");

  useEffect(() => setForm(values || {}), [values, category]);

  const meta = CATEGORIES.find((c) => c.key === category);

  const save = async () => {
    setBusy(true);
    try {
      const { data } = await http.put(`/admin/settings/${category}`, { values: form });
      toast.success(`${meta.label} disimpan`);
      onSaved?.(category, data);
    } catch (e) { toast.error(errMsg(e)); }
    setBusy(false);
  };

  const entries = Object.entries(form).filter(([k]) => !k.endsWith("_set"));

  const pricingPreview = (() => {
    if (category !== "pricing") return null;
    const sampleProviderPrice = 5000;
    const rate = Math.max(0, Number(form.rate_to_idr ?? 1) || 0);
    const multiplier = Math.max(0, Number(form.provider_cost_multiplier ?? 1) || 0);
    const taxPercent = Math.max(0, Number(form.provider_tax_percent ?? 0) || 0);
    const markupPercent = Number(form.markup_percent ?? 0) || 0;
    const fixedFee = Number(form.fixed_fee ?? 0) || 0;
    const minProfit = Math.max(0, Number(form.min_profit ?? 0) || 0);
    const safetyMinProfit = Math.max(0, Number(form.safety_min_profit ?? 0) || 0);
    const step = Math.max(1, Number(form.rounding_to ?? 1) || 1);
    const beforeTax = sampleProviderPrice * rate * multiplier;
    const tax = beforeTax * taxPercent / 100;
    const cost = beforeTax + tax;
    const markupPrice = cost * (1 + markupPercent / 100) + fixedFee;
    const floor = cost + Math.max(minProfit, safetyMinProfit);
    const sale = Math.ceil(Math.max(markupPrice, floor) / step) * step;
    return { sampleProviderPrice, beforeTax, tax, cost, sale, profit: sale - cost };
  })();

  const idr = (n) => `Rp${Math.round(Number(n || 0)).toLocaleString("id-ID")}`;

  return (
    <div data-testid={`settings-panel-${category}`}>
      <h2 className="text-2xl font-extrabold">{meta?.label}</h2>
      <p className="mt-2 text-sm text-muted-foreground">{meta?.desc}</p>

      {category === "pricing" && pricingPreview && (
        <div className="mt-5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm">
          <p className="font-bold text-emerald-500">Proteksi anti-rugi aktif</p>
          <p className="mt-1 text-xs leading-5 text-muted-foreground">
            Rumus: modal setelah pajak → hitung markup → bandingkan dengan modal + profit minimum aman → pakai harga yang paling tinggi.
          </p>
          <div className="mt-3 grid gap-2 text-xs sm:grid-cols-4">
            <div><span className="text-muted-foreground">Contoh provider</span><p className="mono font-bold">{pricingPreview.sampleProviderPrice.toLocaleString("id-ID")} unit</p></div>
            <div><span className="text-muted-foreground">Modal + pajak</span><p className="mono font-bold">{idr(pricingPreview.cost)}</p></div>
            <div><span className="text-muted-foreground">Harga jual aman</span><p className="mono font-bold text-primary">{idr(pricingPreview.sale)}</p></div>
            <div><span className="text-muted-foreground">Estimasi profit</span><p className="mono font-bold text-emerald-500">{idr(pricingPreview.profit)}</p></div>
          </div>
          <p className="mt-3 text-[11px] text-muted-foreground">
            Untuk contoh pembelianmu 50.000 coin = Rp68.000 sebelum pajak, isi <b>Biaya beli per 1 unit / coin = 1,36</b>, <b>Pajak provider = 11%</b>, dan <b>Profit minimum aman = Rp5.000</b>.
          </p>
        </div>
      )}

      {category === "contact" ? (
        <div className="mt-7 grid gap-4 lg:grid-cols-2">
          {CONTACT_FIELDS.map((item) => (
            <div key={item.key} className="rounded-2xl border border-border bg-card p-4">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-bold">{item.label}</p>
                  <p className="mt-0.5 text-[11px] text-muted-foreground">Button OFF = tidak tampil ke user.</p>
                </div>
                <Switch
                  data-testid={`setting-contact-${item.enabled}`}
                  checked={Boolean(form[item.enabled])}
                  onCheckedChange={(checked) => setForm({ ...form, [item.enabled]: checked })}
                  aria-label={`Tampilkan ${item.label}`}
                />
              </div>
              <input
                data-testid={`setting-contact-${item.key}`}
                type={item.key === "support_email" ? "email" : "text"}
                autoComplete="off"
                value={form[item.key] ?? ""}
                placeholder={item.placeholder}
                onChange={(e) => setForm({ ...form, [item.key]: e.target.value })}
                className="mono mt-3 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none transition-colors focus:border-primary"
              />
              {["telegram", "instagram", "tiktok", "x", "facebook", "youtube"].includes(item.key) && (
                <p className="mt-2 text-[11px] text-muted-foreground">Cukup isi username, misalnya <span className="mono">chandrahafi</span>. Tampilan ke user otomatis menjadi <span className="mono">@chandrahafi</span>.</p>
              )}
            </div>
          ))}
        </div>
      ) : (
        <div className="mt-7 grid gap-4 sm:grid-cols-2">
          {entries.map(([k, v]) => {
            const isBool = typeof v === "boolean";
            const isNum = typeof v === "number";
            const isSecret = SECRET.includes(k);
            if (isBool) {
              return (
                <label key={k} className="flex items-center justify-between gap-3 rounded-2xl border border-border bg-card px-4 py-3.5">
                  <span className="text-sm font-semibold">{LABELS[k] || k}</span>
                  <Switch
                    data-testid={`setting-${category}-${k}`}
                    checked={v}
                    onCheckedChange={(checked) => setForm({ ...form, [k]: checked })}
                    aria-label={LABELS[k] || k}
                  />
                </label>
              );
            }
            return (
              <label key={k} className="block">
                <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{LABELS[k] || k}</span>
                {isSecret ? (
                  <PasswordInput
                    data-testid={`setting-${category}-${k}`}
                    autoComplete="new-password"
                    value={v === "••••••••" ? "" : (v ?? "")}
                    placeholder={form[`${k}_set`] ? "tersimpan — isi untuk mengganti" : ""}
                    onChange={(e) => setForm({ ...form, [k]: e.target.value })}
                    className="mt-2"
                    inputClassName="mono rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none transition-colors focus:border-primary"
                  />
                ) : LONG_TEXT.has(k) ? (
                  <textarea
                    data-testid={`setting-${category}-${k}`}
                    rows={5}
                    autoComplete="off"
                    value={v ?? ""}
                    onChange={(e) => setForm({ ...form, [k]: e.target.value })}
                    className="themed-scrollbar mt-2 min-h-[140px] w-full resize-y rounded-2xl border border-input bg-background px-4 py-3 text-sm leading-6 outline-none transition-colors focus:border-primary"
                  />
                ) : (
                  <input
                    data-testid={`setting-${category}-${k}`}
                    type={isNum ? "number" : "text"}
                    autoComplete="off"
                    value={v ?? ""}
                    onChange={(e) => setForm({ ...form, [k]: isNum ? Number(e.target.value) : e.target.value })}
                    className="mono mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none transition-colors focus:border-primary"
                  />
                )}
              </label>
            );
          })}
        </div>
      )}

      <div className="mt-7 flex flex-wrap items-center gap-3">
        <button data-testid={`settings-save-${category}`} onClick={save} disabled={busy} className="flex items-center gap-2 rounded-2xl bg-primary px-6 py-3 text-sm font-bold text-primary-foreground transition-transform hover:scale-[1.02] disabled:opacity-60">
          {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Simpan
        </button>

        {category === "smtp" && (
          <div className="flex flex-wrap items-center gap-2">
            <input data-testid="smtp-test-email" placeholder="email tujuan uji" value={testEmail} onChange={(e) => setTestEmail(e.target.value)} className="rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
            <button data-testid="smtp-test-send" onClick={async () => {
              try { await http.post("/admin/smtp/test", { email: testEmail }); toast.success("Email uji terkirim"); }
              catch (e) { toast.error(errMsg(e)); }
            }} className="flex items-center gap-2 rounded-2xl border border-border px-5 py-3 text-sm font-bold hover:border-primary hover:text-primary">
              <Mail className="h-4 w-4" /> Uji SMTP
            </button>
          </div>
        )}

        {category === "notifications" && (
          <button data-testid="telegram-test" onClick={async () => {
            try { await http.post("/admin/telegram/test"); toast.success("Notifikasi Telegram terkirim"); }
            catch (e) { toast.error(errMsg(e)); }
          }} className="flex items-center gap-2 rounded-2xl border border-border px-5 py-3 text-sm font-bold hover:border-primary hover:text-primary">
            <Send className="h-4 w-4" /> Uji Telegram
          </button>
        )}

        {category === "backup" && (
          <button data-testid="backup-download" onClick={async () => {
            try {
              const { data } = await http.get("/admin/backup");
              const url = URL.createObjectURL(new Blob([JSON.stringify(data, null, 2)], { type: "application/json" }));
              const a = document.createElement("a");
              a.href = url; a.download = `dapetotp-backup-${Date.now()}.json`; a.click();
              URL.revokeObjectURL(url);
              toast.success("Backup diunduh");
            } catch (e) { toast.error(errMsg(e)); }
          }} className="rounded-2xl border border-border px-5 py-3 text-sm font-bold hover:border-primary hover:text-primary">
            Unduh backup JSON
          </button>
        )}
      </div>
    </div>
  );
};
