import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { ArrowRight, Zap, Wallet, Activity, MousePointerClick, KeyRound, Gauge, Code2, Server, Cpu, Database, Globe2, ShieldCheck, LifeBuoy } from "lucide-react";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { http, rupiah } from "@/lib/api";
import { useI18n } from "@/lib/i18n";

const CHIPS = [
  { icon: Zap, id: "Cepat", en: "Fast" },
  { icon: Wallet, id: "Terjangkau", en: "Affordable" },
  { icon: Activity, id: "Realtime", en: "Realtime" },
  { icon: MousePointerClick, id: "Mudah digunakan", en: "Easy to use" },
];

const WHY = [
  { icon: KeyRound, t_id: "API key wajib", t_en: "API key required", d_id: "Autentikasi per akun menekan penyalahgunaan dan memudahkan pemantauan pemakaian tiap pelanggan.", d_en: "Per-account auth reduces abuse and makes usage easy to monitor." },
  { icon: Gauge, t_id: "Saldo & riwayat jelas", t_en: "Clear balance & history", d_id: "Saldo, pembelian, refund, dan isi saldo tercatat rapi sehingga kamu tahu kapan perlu top up.", d_en: "Balance, purchases, refunds and top-ups are all logged clearly." },
  { icon: Code2, t_id: "Contoh kode lengkap", t_en: "Full code samples", d_id: "Setiap endpoint punya contoh cURL siap pakai untuk deposit, order nomor, dan cek OTP.", d_en: "Every endpoint ships with ready-to-use cURL examples." },
];

const RUNTIME = [
  { icon: Server, t: "Satu origin", d_id: "Frontend dan API berjalan pada satu origin agar jalur request lebih singkat.", d_en: "Frontend and API share one origin for shorter request paths." },
  { icon: Cpu, t: "FastAPI persisten", d_id: "Backend FastAPI berjalan sebagai service persisten di belakang reverse proxy.", d_en: "FastAPI runs as a persistent service behind a reverse proxy." },
  { icon: Database, t: "Sinkron provider", d_id: "Status pesanan, OTP, dan refund disinkronkan langsung dari provider.", d_en: "Order status, OTP and refunds sync straight from the provider." },
];

const FAQ = [
  ["Apakah semua endpoint wajib pakai API key?", "Ya. Semua endpoint /api/v1 memerlukan header x-api-key milik akunmu. Key bisa diganti kapan saja dari dasbor."],
  ["Kalau OTP tidak masuk, saldo hangus?", "Tidak. Gunakan Kirim ulang (gratis selama masih dalam waktu proses) atau batalkan pesanan; saldo dikembalikan penuh untuk pesanan yang dibatalkan atau kedaluwarsa."],
  ["Bagaimana alur pesan nomor?", "Pilih layanan, klik Beli nomor, tekan Siap agar nomor mulai menerima OTP, lalu tunggu kode masuk dan klik Selesai setelah OTP terpakai."],
  ["Bagaimana cara isi saldo?", "Scan QRIS yang muncul di dasbor. Setelah pembayaran terdeteksi, saldo bertambah otomatis — bisa juga dibuat langsung dari API."],
  ["Apakah saldo bisa dikembalikan setelah pesanan selesai?", "Tidak. Saldo hanya dikembalikan untuk pesanan yang dibatalkan atau kedaluwarsa; pesanan selesai dianggap terpakai."],
  ["Bisa pilih server?", "Bisa. Setiap layanan punya beberapa pilihan server, pilih sebelum memesan nomor."],
];

const CODE = `$ curl "${process.env.REACT_APP_BACKEND_URL}/api/v1/orders" \\
   -H "x-api-key: YOUR_API_KEY" \\
   -H "content-type: application/json" \\
   -d '{"service_country_price_id":"<uuid>"}'

200 OK
{
  "message": "success",
  "data": {
    "phone_number": "6281333263607",
    "status": "pending",
    "price": 1600,
    "otp_codes": []
  }
}`;

export default function Landing() {
  const { t, lang } = useI18n();
  const [site, setSite] = useState(null);
  const [stats, setStats] = useState(null);

  useEffect(() => {
    http.get("/public/settings").then(({ data }) => setSite(data.site)).catch(() => {});
    http.get("/public/stats").then(({ data }) => setStats(data)).catch(() => {});
  }, []);

  const L = (id, en) => (lang === "id" ? id : en);

  return (
    <div data-testid="landing-page">
      {/* HERO */}
      <section className="relative overflow-hidden grain">
        <div className="pointer-events-none absolute -top-48 left-1/2 h-[560px] w-[900px] -translate-x-1/2 rounded-full bg-primary/25 blur-[140px]" />
        <div className="relative mx-auto max-w-5xl px-6 py-24 text-center sm:py-28">
          <span data-testid="hero-badge" className="rise inline-flex items-center gap-2 rounded-full border border-primary/40 bg-primary/10 px-5 py-2 text-xs font-bold tracking-[0.22em] text-primary">
            <span className="h-2 w-2 animate-pulse rounded-full bg-primary" /> {L("REST API PUBLIK", "PUBLIC REST API")}
          </span>
          <h1 data-testid="hero-title" className="rise mt-8 text-4xl font-extrabold leading-[0.95] sm:text-5xl lg:text-6xl" style={{ animationDelay: "80ms" }}>
            {site?.site_name || "dapetOTP"}.
            <br />
            <span className="text-primary">{L("Solusi verifikasi untuk semua layanan.", "Verification for every service.")}</span>
          </h1>
          <p data-testid="hero-subtitle" className="rise mx-auto mt-7 max-w-2xl text-sm text-muted-foreground md:text-lg" style={{ animationDelay: "150ms" }}>
            {L(
              "Sewa nomor virtual, terima OTP realtime, isi saldo QRIS otomatis, dan otomatiskan semuanya lewat REST API dengan API key pribadi. Daftar, isi saldo, langsung pesan.",
              "Rent virtual numbers, receive OTPs in realtime, top up via QRIS and automate everything through a REST API with your own key."
            )}
          </p>
          <div className="rise mt-10 flex flex-wrap items-center justify-center gap-3" style={{ animationDelay: "220ms" }}>
            <Link data-testid="hero-cta-login" to="/masuk" className="group flex items-center gap-2 rounded-2xl bg-primary px-7 py-4 text-sm font-bold text-primary-foreground transition-transform duration-200 hover:scale-[1.03] brand-glow">
              {t("getStarted")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
            </Link>
            <Link data-testid="hero-cta-pricing" to="/harga" className="rounded-2xl border border-border bg-card px-7 py-4 text-sm font-bold transition-colors duration-200 hover:border-primary hover:text-primary">
              {L("Lihat Harga", "See Pricing")}
            </Link>
            <Link data-testid="hero-cta-docs" to="/docs" className="rounded-2xl px-5 py-4 text-sm font-bold text-muted-foreground transition-colors hover:text-primary">
              {t("docs")} →
            </Link>
          </div>
          <div className="rise mt-12 flex flex-wrap items-center justify-center gap-2" style={{ animationDelay: "300ms" }}>
            {CHIPS.map((c, i) => (
              <span key={c.id} data-testid={`hero-chip-${i}`} className="flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-xs font-bold text-muted-foreground">
                <c.icon className="h-3.5 w-3.5 text-primary" /> {L(c.id, c.en)}
              </span>
            ))}
          </div>
        </div>
      </section>

      {/* CONTOH REQUEST */}
      <section className="mx-auto max-w-6xl px-6 py-16">
        <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("CONTOH REQUEST", "SAMPLE REQUEST")}</p>
        <h2 className="mt-3 text-2xl font-extrabold sm:text-3xl">{L("Satu request, langsung jalan.", "One request, ready to go.")}</h2>
        <div className="mt-7 overflow-hidden rounded-3xl border border-border bg-card">
          <div className="flex flex-wrap items-center gap-3 border-b border-border bg-muted/50 px-5 py-3">
            <span className="mono rounded-lg bg-emerald-500/15 px-2 py-1 text-[11px] font-bold text-emerald-500">POST</span>
            <code className="mono text-xs">/api/v1/orders</code>
            <span className="mono ml-auto rounded-lg bg-primary/15 px-2 py-1 text-[11px] font-bold text-primary">200 OK</span>
          </div>
          <pre data-testid="sample-code" className="mono overflow-x-auto p-5 text-xs leading-relaxed">{CODE}</pre>
        </div>
      </section>

      {/* KENAPA */}
      <section className="mx-auto max-w-6xl px-6 py-16">
        <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("KENAPA DAPETOTP", "WHY DAPETOTP")}</p>
        <h2 className="mt-3 text-2xl font-extrabold sm:text-3xl">{L("API yang rapi untuk dipakai sehari-hari.", "A tidy API for everyday use.")}</h2>
        <div className="mt-8 grid gap-5 md:grid-cols-3">
          {WHY.map((w, i) => (
            <article key={w.t_id} data-testid={`why-card-${i}`} className="hover-lift rounded-3xl border border-border bg-card p-7">
              <span className="grid h-12 w-12 place-items-center rounded-2xl bg-primary/15 text-primary"><w.icon className="h-5 w-5" /></span>
              <h3 className="mt-5 text-base font-bold md:text-lg">{L(w.t_id, w.t_en)}</h3>
              <p className="mt-2 text-sm text-muted-foreground">{L(w.d_id, w.d_en)}</p>
            </article>
          ))}
        </div>
      </section>

      {/* RUNTIME */}
      <section className="mx-auto max-w-6xl px-6 py-16">
        <p className="text-xs font-bold tracking-[0.22em] text-primary">RUNTIME</p>
        <h2 className="mt-3 text-2xl font-extrabold sm:text-3xl">{L("Dibuat untuk akses ringan dan stabil.", "Built for light, stable access.")}</h2>
        <div className="mt-8 grid gap-5 md:grid-cols-3">
          {RUNTIME.map((r, i) => (
            <article key={r.t} data-testid={`runtime-card-${i}`} className="hover-lift rounded-3xl border border-border bg-card p-7">
              <span className="grid h-12 w-12 place-items-center rounded-2xl bg-primary/15 text-primary"><r.icon className="h-5 w-5" /></span>
              <h3 className="mt-5 text-base font-bold md:text-lg">{r.t}</h3>
              <p className="mt-2 text-sm text-muted-foreground">{L(r.d_id, r.d_en)}</p>
            </article>
          ))}
        </div>
      </section>

      {/* LIVE STATS */}
      <section className="mx-auto max-w-6xl px-6 py-16">
        <div className="rounded-3xl border border-border bg-card p-8">
          <div className="flex flex-wrap items-center gap-3">
            <span className="flex items-center gap-2 rounded-full bg-emerald-500/15 px-3 py-1 text-[11px] font-bold text-emerald-500">
              <span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> LIVE
            </span>
            <p className="text-base font-bold md:text-lg">{L("Katalog langsung dari provider", "Catalog straight from the provider")}</p>
          </div>
          <div className="mt-7 grid gap-5 sm:grid-cols-3">
            {[
              [Globe2, L("Negara", "Countries"), stats?.countries ?? "—"],
              [ShieldCheck, L("Layanan Indonesia", "Indonesian services"), stats?.services ?? "—"],
              [Wallet, L("Mulai dari", "Starting at"), stats?.cheapest ? rupiah(stats.cheapest) : "—"],
            ].map(([Icon, l, v], i) => (
              <div key={l} data-testid={`live-stat-${i}`} className="rounded-2xl border border-border bg-background p-5">
                <span className="grid h-10 w-10 place-items-center rounded-xl bg-primary/15 text-primary"><Icon className="h-4 w-4" /></span>
                <p className="mt-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">{l}</p>
                <p className="mt-1 text-3xl font-extrabold">{v}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PRICE LIST */}
      <section className="mx-auto max-w-6xl px-6 py-16">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">PRICE LIST</p>
            <h2 className="mt-3 text-2xl font-extrabold sm:text-3xl">{L("Layanan termurah hari ini.", "Cheapest services today.")}</h2>
          </div>
          <Link to="/harga" data-testid="pricelist-all" className="rounded-2xl border border-border px-5 py-3 text-sm font-bold transition-colors hover:border-primary hover:text-primary">
            {L("Lihat semua harga", "See all prices")} →
          </Link>
        </div>
        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(stats?.top || []).map((s, i) => (
            <div key={s.name + i} data-testid={`pricelist-item-${i}`} className="hover-lift flex items-center gap-4 rounded-3xl border border-border bg-card p-5">
              {s.logo ? (
                <img src={s.logo} alt="" className="h-11 w-11 rounded-xl bg-muted object-contain p-1.5" onError={(e) => { e.target.style.display = "none"; }} />
              ) : (
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary/15 font-bold text-primary">{s.name.slice(0, 2)}</span>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold">{s.name}</p>
                <p className="text-xs text-muted-foreground">{L("harga", "price")}</p>
              </div>
              <p className="text-lg font-extrabold text-primary">{rupiah(s.price)}</p>
            </div>
          ))}
          {!stats?.top?.length && <p className="text-sm text-muted-foreground">{L("Katalog belum tersedia.", "Catalog unavailable.")}</p>}
        </div>
      </section>

      {/* FAQ */}
      <section className="mx-auto max-w-3xl px-6 py-16">
        <p className="text-xs font-bold tracking-[0.22em] text-primary">FAQ</p>
        <h2 className="mt-3 text-2xl font-extrabold sm:text-3xl">{L("Pertanyaan yang sering ditanyakan.", "Frequently asked questions.")}</h2>
        <Accordion type="single" collapsible className="mt-8">
          {FAQ.map(([q, a], i) => (
            <AccordionItem key={q} value={`f${i}`} className="mb-3 rounded-2xl border border-border bg-card px-5">
              <AccordionTrigger data-testid={`landing-faq-${i}`} className="text-left text-sm font-bold hover:no-underline">{q}</AccordionTrigger>
              <AccordionContent className="text-sm text-muted-foreground">{a}</AccordionContent>
            </AccordionItem>
          ))}
        </Accordion>
      </section>

      <footer className="border-t border-border py-10 text-center text-sm text-muted-foreground">
        <p className="flex flex-wrap items-center justify-center gap-2">
          <LifeBuoy className="h-4 w-4 text-primary" />
          © {new Date().getFullYear()} {site?.site_name || "dapetOTP"} — {site?.business_email || "support@dapetotp.com"}
        </p>
      </footer>
    </div>
  );
}
