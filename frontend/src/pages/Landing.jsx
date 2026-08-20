import React, { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { motion, useReducedMotion, useScroll, useTransform } from "framer-motion";
import {
  ArrowRight, Zap, Wallet, Activity, MousePointerClick, KeyRound, Gauge, Code2,
  Server, Cpu, Database, Globe2, ShieldCheck, LifeBuoy, Terminal, CheckCircle2,
  ArrowUpRight, Sparkles, Radio, Layers3
} from "lucide-react";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { http, rupiah } from "@/lib/api";
import { useSite } from "@/context/SiteContext";
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
  { icon: Database, t: "Sinkron otomatis", d_id: "Status pesanan, OTP, dan pengembalian saldo diperbarui otomatis tanpa langkah tambahan.", d_en: "Order status, OTP and balance returns update automatically." },
];

const FAQ = [
  ["Apakah semua endpoint wajib pakai API key?", "Ya. Semua endpoint /api/v1 memerlukan header x-api-key milik akunmu. Key bisa diganti kapan saja dari dasbor."],
  ["Kalau OTP tidak masuk, saldo hangus?", "Tidak. Gunakan Kirim ulang (gratis selama masih dalam waktu proses) atau batalkan pesanan; saldo dikembalikan penuh untuk pesanan yang dibatalkan atau kedaluwarsa."],
  ["Bagaimana alur pesan nomor?", "Pilih layanan, klik Beli nomor, tekan Siap agar nomor mulai menerima OTP, lalu tunggu kode masuk dan klik Selesai setelah OTP terpakai."],
  ["Bagaimana cara isi saldo?", "Buat pembayaran dari dasbor. Setelah pembayaran terdeteksi, saldo bertambah otomatis — bisa juga dibuat langsung dari API."],
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

const ASSEMBLY_PIECES = [
  {
    icon: KeyRound,
    label: "API KEY",
    desktopPos: "left-[1%] top-[8%]",
    mobilePos: "left-[1%] top-[2%]",
    desktop: [-170, -95, -18],
    mobile: [-80, -68, -15],
  },
  {
    icon: Activity,
    label: "OTP LIVE",
    desktopPos: "right-[2%] top-[16%]",
    mobilePos: "right-[0%] top-[10%]",
    desktop: [175, -125, 16],
    mobile: [82, -62, 13],
  },
  {
    icon: Wallet,
    label: "SALDO",
    desktopPos: "-left-[3%] bottom-[21%]",
    mobilePos: "left-[0%] bottom-[15%]",
    desktop: [-210, 120, 13],
    mobile: [-82, 72, 11],
  },
  {
    icon: CheckCircle2,
    label: "200 OK",
    desktopPos: "right-[0%] bottom-[26%]",
    mobilePos: "right-[0%] bottom-[10%]",
    desktop: [190, 145, -14],
    mobile: [86, 72, -11],
  },
  {
    icon: Layers3,
    label: "MULTI SERVER",
    desktopPos: "left-[31%] bottom-[1%]",
    mobilePos: "left-[29%] bottom-[0%]",
    desktop: [-35, 190, 9],
    mobile: [0, 90, 8],
  },
];

function AssemblyPiece({ item, progress, reducedMotion, mobile = false }) {
  const [startX, startY, startR] = mobile ? item.mobile : item.desktop;

  // Selesai di ~62% lalu ditahan sampai section benar-benar lepas.
  // Ini penting agar state final sempat dilihat, bukan baru selesai saat sticky unpin.
  const x = useTransform(progress, [0, 0.18, 0.52, 0.62, 1], [startX, startX * 0.72, startX * 0.16, 0, 0]);
  const y = useTransform(progress, [0, 0.18, 0.52, 0.62, 1], [startY, startY * 0.7, startY * 0.14, 0, 0]);
  const rotate = useTransform(progress, [0, 0.34, 0.62, 1], [startR, startR * 0.55, 0, 0]);
  const scale = useTransform(progress, [0, 0.4, 0.62, 1], [mobile ? 0.72 : 0.82, 0.92, 1, 1]);
  const opacity = useTransform(progress, [0, 0.08, 0.24, 1], [0.28, 0.78, 1, 1]);

  return (
    <motion.div
      aria-hidden="true"
      style={reducedMotion ? undefined : { x, y, rotate, scale, opacity }}
      className={`pointer-events-none absolute z-30 flex items-center rounded-2xl border border-border/90 bg-card/90 font-extrabold tracking-[0.08em] shadow-xl shadow-black/10 backdrop-blur-xl ${mobile ? "gap-1.5 px-2.5 py-2 text-[8px] sm:gap-2 sm:px-3 sm:py-2.5 sm:text-[9px]" : "gap-2 px-3.5 py-3 text-[11px]"} ${mobile ? item.mobilePos : item.desktopPos}`}
    >
      <span className={`grid place-items-center rounded-xl border border-primary/20 bg-primary/10 text-primary ${mobile ? "h-6 w-6 sm:h-7 sm:w-7" : "h-8 w-8"}`}>
        <item.icon className={mobile ? "h-3 w-3" : "h-3.5 w-3.5"} />
      </span>
      {item.label}
    </motion.div>
  );
}

function AssemblyVisual({ progress, reducedMotion, stats, L, mobile = false }) {
  // Semua transform utama juga mencapai posisi final lebih awal dan stay di sana.
  const visualX = useTransform(progress, [0, 0.62, 1], [mobile ? 34 : 76, 0, 0]);
  const visualY = useTransform(progress, [0, 0.62, 1], [mobile ? 70 : 58, 0, 0]);
  const visualRotate = useTransform(progress, [0, 0.62, 1], [mobile ? 6 : 4.5, 0, 0]);
  const visualScale = useTransform(progress, [0, 0.62, 1], [mobile ? 0.84 : 0.9, 1, 1]);
  const haloRotate = useTransform(progress, [0, 0.62, 1], [-22, 24, 24]);
  const haloScale = useTransform(progress, [0, 0.48, 0.62, 1], [0.8, 1.08, 1, 1]);
  const progressScaleX = useTransform(progress, [0, 0.62, 1], [0.04, 1, 1]);
  const finalGlow = useTransform(progress, [0, 0.48, 0.62, 1], [0.18, 0.24, 0.42, 0.42]);

  return (
    <div className={`relative mx-auto w-full ${mobile ? "h-[540px] max-w-[430px] sm:h-[590px]" : "min-h-[540px]"}`}>
      <motion.div
        aria-hidden="true"
        style={reducedMotion ? undefined : { rotate: haloRotate, scale: haloScale, opacity: finalGlow }}
        className={`pointer-events-none absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-[38%] border border-primary/35 ${mobile ? "h-[310px] w-[310px] sm:h-[360px] sm:w-[360px]" : "h-[430px] w-[430px]"}`}
      />
      <div className={`pointer-events-none absolute rounded-[40px] bg-primary/10 blur-3xl ${mobile ? "inset-x-7 top-[18%] h-[58%]" : "-inset-8"}`} />

      {ASSEMBLY_PIECES.map((item) => (
        <AssemblyPiece key={item.label} item={item} progress={progress} reducedMotion={reducedMotion} mobile={mobile} />
      ))}

      <motion.div
        style={reducedMotion ? undefined : { x: visualX, y: visualY, rotate: visualRotate, scale: visualScale }}
        className={`absolute z-20 overflow-hidden rounded-[26px] border border-border bg-card/90 shadow-2xl shadow-black/10 backdrop-blur-xl ${mobile ? "left-[5%] right-[5%] top-[19%]" : "left-0 right-0 top-[9%]"}`}
      >
        <div className={`flex items-center gap-2 border-b border-border bg-muted/40 ${mobile ? "px-3.5 py-3" : "px-5 py-4"}`}>
          <span className="h-2.5 w-2.5 rounded-full bg-destructive/70" />
          <span className="h-2.5 w-2.5 rounded-full bg-amber-500/80" />
          <span className="h-2.5 w-2.5 rounded-full bg-emerald-500/80" />
          <div className={`mono ml-2 flex items-center gap-2 text-muted-foreground ${mobile ? "text-[9px]" : "text-[11px]"}`}>
            <Terminal className="h-3.5 w-3.5" /> api.dapetotp
          </div>
          <span className={`ml-auto rounded-lg bg-emerald-500/10 font-bold text-emerald-500 ${mobile ? "px-1.5 py-1 text-[8px]" : "px-2 py-1 text-[10px]"}`}>ONLINE</span>
        </div>
        <div className={mobile ? "p-3.5 sm:p-4" : "p-5 sm:p-6"}>
          <div className="grid grid-cols-2 gap-2 sm:gap-3">
            {[
              [CheckCircle2, L("API key per akun", "Per-account API key")],
              [Wallet, L("Pembayaran instan", "Instant payment")],
              [Activity, "OTP realtime"],
              [Layers3, L("Multi server", "Multi server")],
            ].map(([Icon, label]) => (
              <div key={label} className={`flex items-center rounded-2xl border border-border bg-background/70 ${mobile ? "gap-2 p-2.5" : "gap-3 p-4"}`}>
                <span className={`grid shrink-0 place-items-center rounded-xl bg-primary/10 text-primary ${mobile ? "h-7 w-7" : "h-9 w-9"}`}><Icon className={mobile ? "h-3.5 w-3.5" : "h-4 w-4"} /></span>
                <span className={`${mobile ? "text-[9px] leading-3 sm:text-[10px]" : "text-xs sm:text-sm"} font-bold`}>{label}</span>
              </div>
            ))}
          </div>
          <div className={`rounded-2xl border border-border bg-background ${mobile ? "mt-2.5 p-3.5" : "mt-4 p-5"}`}>
            <div className="flex items-center justify-between gap-3">
              <div>
                <p className={`font-bold uppercase tracking-[0.18em] text-muted-foreground ${mobile ? "text-[8px]" : "text-[10px]"}`}>{L("Mulai dari", "Starting at")}</p>
                <p className={`mt-1 font-extrabold text-primary ${mobile ? "text-xl sm:text-2xl" : "text-3xl"}`}>{stats?.cheapest ? rupiah(stats.cheapest) : "—"}</p>
              </div>
              <div className="text-right">
                <p className={`font-bold uppercase tracking-[0.18em] text-muted-foreground ${mobile ? "text-[8px]" : "text-[10px]"}`}>{L("Layanan ID", "ID services")}</p>
                <p className={`mt-1 font-extrabold ${mobile ? "text-xl sm:text-2xl" : "text-3xl"}`}>{stats?.services ?? "—"}</p>
              </div>
            </div>
          </div>
        </div>
      </motion.div>

      <div className={`pointer-events-none absolute left-1/2 -translate-x-1/2 ${mobile ? "bottom-[4%] w-[86%]" : "bottom-0 w-[82%]"}`}>
        <div className="mb-2 flex items-center justify-between text-[8px] font-bold uppercase tracking-[0.22em] text-muted-foreground sm:text-[9px]">
          <span>SCATTER</span>
          <span>ASSEMBLE</span>
        </div>
        <div className="h-px overflow-hidden bg-border">
          <motion.div style={reducedMotion ? { scaleX: 1 } : { scaleX: progressScaleX }} className="h-full origin-left bg-primary" />
        </div>
        {mobile && <p className="mt-3 text-center text-[9px] font-semibold uppercase tracking-[0.15em] text-muted-foreground">Scroll untuk merakit • scroll naik untuk membalik</p>}
      </div>
    </div>
  );
}

function HeroCopy({ site, t, L, testSuffix = "" }) {
  return (
    <div>
      <span data-testid={`hero-badge${testSuffix}`} className="rise inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-2 text-[11px] font-bold tracking-[0.22em] text-primary">
        <Radio className="h-3.5 w-3.5" /> {L("REST API PUBLIK", "PUBLIC REST API")}
      </span>

      <h1 data-testid={`hero-title${testSuffix}`} className="rise mt-7 max-w-4xl text-4xl font-extrabold leading-[1.02] sm:text-5xl lg:text-7xl" style={{ animationDelay: "70ms" }}>
        {site?.site_name || "dapetOTP"}.
        <span className="mt-2 block text-primary">{L("Solusi verifikasi untuk semua layanan.", "Verification for every service.")}</span>
      </h1>

      <p data-testid={`hero-subtitle${testSuffix}`} className="rise mt-7 max-w-2xl text-sm leading-7 text-muted-foreground sm:text-base lg:text-lg" style={{ animationDelay: "130ms" }}>
        {L(
          "Sewa nomor virtual, terima OTP realtime, isi saldo otomatis, dan otomatiskan semuanya lewat REST API dengan API key pribadi. Daftar, isi saldo, langsung pesan.",
          "Rent virtual numbers, receive OTPs in realtime, top up automatically and automate everything through a REST API with your own key."
        )}
      </p>

      <div className="rise mt-9 flex flex-wrap items-center gap-3" style={{ animationDelay: "190ms" }}>
        <Link data-testid={`hero-cta-login${testSuffix}`} to="/masuk" className="soft-float group flex items-center gap-2 rounded-2xl bg-primary px-6 py-3.5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/25">
          {t("getStarted")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
        </Link>
        <Link data-testid={`hero-cta-pricing${testSuffix}`} to="/harga" className="soft-float rounded-2xl border border-border bg-card/80 px-6 py-3.5 text-sm font-bold backdrop-blur transition-colors hover:border-primary hover:text-primary">
          {L("Lihat Harga", "See Pricing")}
        </Link>
        <Link data-testid={`hero-cta-docs${testSuffix}`} to="/docs" className="soft-float group flex items-center gap-1.5 rounded-2xl px-4 py-3.5 text-sm font-bold text-muted-foreground transition-colors hover:text-primary">
          {t("docs")} <ArrowUpRight className="h-4 w-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
        </Link>
      </div>

      <div className="rise mt-9 flex flex-wrap gap-2" style={{ animationDelay: "250ms" }}>
        {CHIPS.map((c, i) => (
          <span key={c.id} data-testid={`hero-chip-${i}${testSuffix}`} style={{ "--float-delay": `${i * -0.65}s` }} className="soft-float flex items-center gap-2 rounded-xl border border-border/80 bg-card/60 px-3 py-2 text-xs font-semibold text-muted-foreground backdrop-blur">
            <c.icon className="h-3.5 w-3.5 text-primary" /> {L(c.id, c.en)}
          </span>
        ))}
      </div>
    </div>
  );
}

export default function Landing() {
  const { t, lang } = useI18n();
  const { site } = useSite();
  const [stats, setStats] = useState(null);
  const desktopHeroRef = useRef(null);
  const mobileAssemblyRef = useRef(null);
  const reducedMotion = useReducedMotion();

  const { scrollYProgress: desktopProgress } = useScroll({
    target: desktopHeroRef,
    offset: ["start start", "end end"],
  });

  const { scrollYProgress: mobileProgress } = useScroll({
    target: mobileAssemblyRef,
    offset: ["start start", "end end"],
  });

  const copyY = useTransform(desktopProgress, [0, 0.56, 0.72, 1], [0, -12, -18, -18]);
  const copyOpacity = useTransform(desktopProgress, [0, 0.62, 1], [1, 1, 0.9]);

  useEffect(() => {
    http.get("/public/stats").then(({ data }) => setStats(data)).catch(() => {});
  }, []);

  const L = (id, en) => (lang === "id" ? id : en);

  return (
    <div data-testid="landing-page" className="overflow-hidden">
      {/* HERO */}
      <section className="relative border-b border-border/70">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_12%_15%,hsl(var(--primary)/0.18),transparent_32%),radial-gradient(circle_at_88%_12%,hsl(var(--primary)/0.10),transparent_26%)]" />
        <div className="pointer-events-none absolute inset-0 opacity-40 [background-image:linear-gradient(hsl(var(--border)/.35)_1px,transparent_1px),linear-gradient(90deg,hsl(var(--border)/.35)_1px,transparent_1px)] [background-size:48px_48px] [mask-image:linear-gradient(to_bottom,#000,transparent_85%)]" />

        {/* MOBILE: copy normal, lalu panggung assembly sendiri yang sticky. Dengan ini animasi tidak hilang di HP. */}
        <div className="relative mx-auto max-w-7xl px-5 pb-8 pt-14 sm:px-6 sm:pt-16 lg:hidden">
          <HeroCopy site={site} t={t} L={L} testSuffix="-mobile" />
        </div>

        <div ref={mobileAssemblyRef} className="relative h-[145svh] lg:hidden">
          <div className="sticky top-[64px] flex min-h-[calc(100svh-64px)] items-center px-3 py-4 sm:top-[69px] sm:min-h-[calc(100svh-69px)] sm:px-5">
            <AssemblyVisual progress={mobileProgress} reducedMotion={reducedMotion} stats={stats} L={L} mobile />
          </div>
        </div>

        {/* DESKTOP: final assembly selesai lebih awal lalu ditahan sebelum sticky dilepas. */}
        <div ref={desktopHeroRef} className="relative hidden h-[172vh] lg:block">
          <div className="sticky top-[69px] mx-auto grid min-h-[calc(100svh-69px)] max-w-7xl grid-cols-[1.05fr_.95fr] items-center gap-12 px-6 py-12">
            <motion.div style={reducedMotion ? undefined : { y: copyY, opacity: copyOpacity }}>
              <HeroCopy site={site} t={t} L={L} />
            </motion.div>

            <div className="relative">
              <AssemblyVisual progress={desktopProgress} reducedMotion={reducedMotion} stats={stats} L={L} />
            </div>
          </div>
        </div>
      </section>

      {/* SAMPLE REQUEST */}
      <section className="scroll-reveal relative bg-muted/30">
        <div className="mx-auto grid max-w-7xl gap-8 px-6 py-20 lg:grid-cols-[.72fr_1.28fr] lg:items-center">
          <div>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("CONTOH REQUEST", "SAMPLE REQUEST")}</p>
            <h2 className="mt-4 text-3xl font-extrabold leading-tight sm:text-4xl">{L("Satu request, langsung jalan.", "One request, ready to go.")}</h2>
            <p className="mt-4 max-w-md text-sm leading-6 text-muted-foreground">
              {L("Alur API dibuat sederhana: autentikasi dengan API key, kirim ID harga layanan, lalu pantau status dan OTP dari endpoint yang sama.", "The API flow stays simple: authenticate, send a service price ID, then monitor status and OTP from the same API.")}
            </p>
            <div className="mt-6 space-y-3 text-sm">
              {[L("Header sederhana", "Simple headers"), L("Respons JSON konsisten", "Consistent JSON responses"), L("Siap dipakai dari backend apa pun", "Works from any backend")].map((x) => (
                <div key={x} className="flex items-center gap-3 font-semibold"><CheckCircle2 className="h-4 w-4 text-primary" /> {x}</div>
              ))}
            </div>
          </div>

          <div className="overflow-hidden rounded-[28px] border border-border bg-[hsl(222_47%_6%)] text-slate-100 shadow-xl">
            <div className="flex flex-wrap items-center gap-3 border-b border-white/10 bg-white/5 px-5 py-3.5">
              <span className="mono rounded-lg bg-emerald-400/15 px-2 py-1 text-[11px] font-bold text-emerald-300">POST</span>
              <code className="mono text-xs text-slate-300">/api/v1/orders</code>
              <span className="mono ml-auto rounded-lg bg-blue-400/15 px-2 py-1 text-[11px] font-bold text-blue-300">200 OK</span>
            </div>
            <pre data-testid="sample-code" className="themed-scrollbar mono overflow-x-auto p-5 text-xs leading-relaxed text-slate-300 sm:p-6">{CODE}</pre>
          </div>
        </div>
      </section>

      {/* WHY */}
      <section className="scroll-reveal mx-auto max-w-7xl px-6 py-20">
        <div className="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
          <div>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("KENAPA DAPETOTP", "WHY DAPETOTP")}</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("API yang rapi untuk dipakai sehari-hari.", "A tidy API for everyday use.")}</h2>
          </div>
          <p className="max-w-2xl text-sm leading-6 text-muted-foreground lg:justify-self-end">
            {L("Fokusnya bukan hanya membeli nomor, tetapi membuat seluruh alur verifikasi lebih mudah dipantau: harga, saldo, transaksi, OTP, dan integrasi API berada di satu tempat.", "More than buying numbers: pricing, balance, transactions, OTP and API integration stay visible in one place.")}
          </p>
        </div>

        <div className="mt-10 grid gap-5 lg:grid-cols-3">
          {WHY.map((w, i) => (
            <article key={w.t_id} data-testid={`why-card-${i}`} className="group relative overflow-hidden rounded-[28px] border border-border bg-card p-7 transition-all hover:-translate-y-1 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/5">
              <div className="absolute right-0 top-0 h-32 w-32 translate-x-10 -translate-y-10 rounded-full bg-primary/12 blur-3xl" />
              <span className="relative grid h-12 w-12 place-items-center rounded-2xl border border-primary/20 bg-primary/10 text-primary"><w.icon className="h-5 w-5" /></span>
              <h3 className="relative mt-8 text-xl font-extrabold">{L(w.t_id, w.t_en)}</h3>
              <p className="relative mt-3 text-sm leading-6 text-muted-foreground">{L(w.d_id, w.d_en)}</p>
              <div className="relative mt-7 h-px w-full bg-border" />
              <p className="relative mt-4 text-xs font-bold uppercase tracking-wider text-primary">0{i + 1}</p>
            </article>
          ))}
        </div>
      </section>

      {/* RUNTIME */}
      <section className="scroll-reveal border-y border-border bg-card/50">
        <div className="mx-auto max-w-7xl px-6 py-20">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <p className="text-xs font-bold tracking-[0.22em] text-primary">RUNTIME</p>
              <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Dibuat untuk akses ringan dan stabil.", "Built for light, stable access.")}</h2>
            </div>
            <span className="inline-flex items-center gap-2 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-500"><span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> LIVE STACK</span>
          </div>

          <div className="relative mt-10 grid gap-4 lg:grid-cols-3">
            <div className="pointer-events-none absolute left-[16.6%] right-[16.6%] top-7 hidden h-px bg-border lg:block" />
            {RUNTIME.map((r, i) => (
              <article key={r.t} data-testid={`runtime-card-${i}`} className="relative rounded-[26px] border border-border bg-background p-6">
                <div className="relative z-10 flex items-center gap-4">
                  <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-primary/20 bg-primary/10 text-primary"><r.icon className="h-5 w-5" /></span>
                  <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">STEP 0{i + 1}</p>
                    <h3 className="mt-1 text-lg font-extrabold">{r.t}</h3>
                  </div>
                </div>
                <p className="mt-5 text-sm leading-6 text-muted-foreground">{L(r.d_id, r.d_en)}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* LIVE STATS */}
      <section className="scroll-reveal mx-auto max-w-7xl px-6 py-20">
        <div className="relative overflow-hidden rounded-[34px] border border-border bg-card p-7 shadow-xl shadow-primary/5 sm:p-10">
          <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-primary/12 blur-3xl" />
          <div className="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-primary/5 blur-3xl" />
          <div className="relative flex flex-wrap items-center gap-3">
            <span className="flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-bold text-emerald-500"><span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> LIVE</span>
            <p className="text-lg font-extrabold">{L("Katalog layanan selalu diperbarui", "Service catalog stays up to date")}</p>
          </div>

          <div className="relative mt-8 grid gap-3 sm:grid-cols-3">
            {[
              [Globe2, L("Negara", "Countries"), stats?.countries ?? "—"],
              [ShieldCheck, L("Layanan Indonesia", "Indonesian services"), stats?.services ?? "—"],
              [Wallet, L("Mulai dari", "Starting at"), stats?.cheapest ? rupiah(stats.cheapest) : "—"],
            ].map(([Icon, label, value], i) => (
              <div key={label} data-testid={`live-stat-${i}`} className="rounded-2xl border border-border bg-background/70 p-5 backdrop-blur-sm transition-colors hover:border-primary/50">
                <span className="grid h-10 w-10 place-items-center rounded-xl border border-primary/20 bg-primary/10 text-primary"><Icon className="h-4 w-4" /></span>
                <p className="mt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
                <p className="mt-1 text-3xl font-extrabold">{value}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* PRICE LIST */}
      <section className="scroll-reveal mx-auto max-w-7xl px-6 pb-20">
        <div className="flex flex-wrap items-end justify-between gap-5">
          <div>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">PRICE LIST</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Layanan termurah hari ini.", "Cheapest services today.")}</h2>
            <p className="mt-3 text-sm text-muted-foreground">{L("Harga di bawah sudah memakai kalkulasi harga jual untuk pelanggan.", "Prices below already use the customer selling-price calculation.")}</p>
          </div>
          <Link to="/harga" data-testid="pricelist-all" className="group flex items-center gap-2 rounded-2xl border border-border bg-card px-5 py-3 text-sm font-bold transition-colors hover:border-primary hover:text-primary">
            {L("Lihat semua harga", "See all prices")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
          </Link>
        </div>

        <div className="mt-9 grid gap-3 lg:grid-cols-2">
          {(stats?.top || []).map((s, i) => (
            <div key={s.name + i} data-testid={`pricelist-item-${i}`} className="group flex items-center gap-4 rounded-2xl border border-border bg-card p-4 transition-all hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5">
              <span className="mono w-8 text-center text-xs font-bold text-muted-foreground">{String(i + 1).padStart(2, "0")}</span>
              {s.logo ? (
                <img src={s.logo} alt="" className="h-11 w-11 rounded-xl border border-border bg-muted object-contain p-1.5" onError={(e) => { e.target.style.display = "none"; }} />
              ) : (
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 font-bold text-primary">{s.name.slice(0, 2)}</span>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-bold">{s.name}</p>
                <p className="mt-0.5 text-xs text-muted-foreground">{L("harga pelanggan", "customer price")}</p>
              </div>
              <p className="text-lg font-extrabold text-primary">{rupiah(s.price)}</p>
              <ArrowUpRight className="h-4 w-4 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary" />
            </div>
          ))}
          {!stats?.top?.length && <p className="text-sm text-muted-foreground">{L("Katalog belum tersedia.", "Catalog unavailable.")}</p>}
        </div>
      </section>

      {/* FAQ */}
      <section className="scroll-reveal border-t border-border bg-muted/25">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 py-20 lg:grid-cols-[.7fr_1.3fr]">
          <div>
            <span className="inline-grid h-11 w-11 place-items-center rounded-2xl bg-primary/10 text-primary"><Sparkles className="h-5 w-5" /></span>
            <p className="mt-6 text-xs font-bold tracking-[0.22em] text-primary">FAQ</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Pertanyaan yang sering ditanyakan.", "Frequently asked questions.")}</h2>
            <p className="mt-4 max-w-md text-sm leading-6 text-muted-foreground">{L("Masih ada yang belum jelas? Masuk ke Docs untuk contoh request lengkap atau hubungi support.", "Need more detail? Open Docs for complete request examples or contact support.")}</p>
          </div>

          <Accordion type="single" collapsible className="space-y-3">
            {FAQ.map(([q, a], i) => (
              <AccordionItem key={q} value={`f${i}`} className="overflow-hidden rounded-2xl border border-border bg-card px-5 data-[state=open]:border-primary/40">
                <AccordionTrigger data-testid={`landing-faq-${i}`} className="text-left text-sm font-bold hover:no-underline">{q}</AccordionTrigger>
                <AccordionContent className="text-sm leading-6 text-muted-foreground">{a}</AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </div>
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
