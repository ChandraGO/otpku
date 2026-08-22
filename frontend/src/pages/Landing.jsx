import React, { useEffect, useRef, useState } from "react";
import { Link } from "react-router-dom";
import { motion, useInView, useReducedMotion, useScroll, useSpring, useTransform } from "framer-motion";
import {
  ArrowRight, Zap, Wallet, Activity, MousePointerClick, KeyRound, Gauge, Code2,
  Globe2, ShieldCheck, LifeBuoy, Terminal, CheckCircle2,
  ArrowUpRight, Sparkles, Radio, Layers3, BookOpen, CalendarDays, ShoppingCart
} from "lucide-react";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { http, rupiah } from "@/lib/api";
import { useSite } from "@/context/SiteContext";
import { useI18n } from "@/lib/i18n";
import { ContactLinks, getVisibleContacts } from "@/components/ContactLinks";

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
  { icon: ShoppingCart, t_id: "Pilih layanan", t_en: "Choose a service", d_id: "Pilih layanan dan negara yang dibutuhkan, lalu cek harga sebelum membeli.", d_en: "Choose the service and country you need, then check the price before buying." },
  { icon: Wallet, t_id: "Bayar sesuai pilihan", t_en: "Pay your way", d_id: "Gunakan saldo atau Bayar Langsung tanpa harus deposit lebih dulu.", d_en: "Use your balance or Pay Directly without making a deposit first." },
  { icon: Activity, t_id: "Terima OTP otomatis", t_en: "Receive OTP automatically", d_id: "Nomor, status, OTP, dan refund diperbarui otomatis dari dashboard.", d_en: "Numbers, status, OTPs and refunds update automatically in your dashboard." },
];

const FAQ = [
  ["Apakah semua endpoint wajib pakai API key?", "Ya. Semua endpoint /api/v1 memerlukan header x-api-key milik akunmu. Key bisa diganti kapan saja dari dashboard."],
  ["Apakah wajib top up atau deposit dulu?", "Tidak. Kamu bisa langsung membeli layanan dengan opsi Bayar Langsung. Top up saldo hanya pilihan jika ingin transaksi berikutnya lebih cepat dari saldo akun."],
  ["Kalau OTP tidak masuk, saldo hangus?", "Tidak. Gunakan Kirim ulang (gratis selama masih dalam waktu proses) atau batalkan pesanan; saldo dikembalikan penuh untuk pesanan yang dibatalkan atau kedaluwarsa."],
  ["Bagaimana alur pesan nomor?", "Pilih layanan, klik Beli nomor, tekan Siap agar nomor mulai menerima OTP, lalu tunggu kode masuk dan klik Selesai setelah OTP terpakai."],
  ["Bagaimana cara isi saldo?", "Buat pembayaran dari dashboard. Setelah pembayaran terdeteksi, saldo bertambah otomatis — bisa juga dibuat langsung dari API."],
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

function CountUp({ value, format = (n) => Math.round(n).toLocaleString("id-ID"), duration = 1150 }) {
  const ref = useRef(null);
  const inView = useInView(ref, { once: true, amount: 0.55 });
  const reducedMotion = useReducedMotion();
  const [current, setCurrent] = useState(0);

  useEffect(() => {
    const numericValue = Number(value);
    if (!Number.isFinite(numericValue) || !inView) return undefined;
    if (reducedMotion) {
      setCurrent(numericValue);
      return undefined;
    }

    let frameId;
    const startedAt = performance.now();
    const tick = (now) => {
      const elapsed = Math.min(1, (now - startedAt) / duration);
      const eased = 1 - Math.pow(1 - elapsed, 3);
      setCurrent(numericValue * eased);
      if (elapsed < 1) frameId = requestAnimationFrame(tick);
    };
    frameId = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(frameId);
  }, [duration, inView, reducedMotion, value]);

  const numericValue = Number(value);
  return <span ref={ref}>{Number.isFinite(numericValue) ? format(current) : "—"}</span>;
}

function highlightCodeLine(line, index) {
  if (line.startsWith("$ curl")) {
    const match = line.match(/^(\$ )(curl)(.*)$/);
    return (
      <React.Fragment key={`code-${index}`}>
        <span className="text-slate-500">{match?.[1]}</span>
        <span className="font-semibold text-emerald-300">{match?.[2]}</span>
        <span className="text-sky-300">{match?.[3]}</span>
      </React.Fragment>
    );
  }
  if (line.trimStart().startsWith("-H")) {
    const marker = line.indexOf("-H");
    const indent = line.slice(0, marker);
    const rest = line.slice(marker + 2);
    return (
      <React.Fragment key={`code-${index}`}>
        {indent}<span className="font-semibold text-violet-300">-H</span><span className="text-amber-200">{rest}</span>
      </React.Fragment>
    );
  }
  if (line.trimStart().startsWith("-d")) {
    const marker = line.indexOf("-d");
    const indent = line.slice(0, marker);
    const rest = line.slice(marker + 2);
    return (
      <React.Fragment key={`code-${index}`}>
        {indent}<span className="font-semibold text-violet-300">-d</span><span className="text-fuchsia-200">{rest}</span>
      </React.Fragment>
    );
  }
  if (line.trim() === "200 OK") return <span key={`code-${index}`} className="font-bold text-emerald-300">{line}</span>;

  const jsonMatch = line.match(/^(\s*)("[^"]+")(\s*:\s*)(.*)$/);
  if (jsonMatch) {
    const [, indent, key, colon, tail] = jsonMatch;
    let tailClass = "text-slate-300";
    if (/^"/.test(tail.trim())) tailClass = "text-emerald-200";
    else if (/^\d/.test(tail.trim())) tailClass = "text-amber-300";
    else if (/^\[/.test(tail.trim())) tailClass = "text-fuchsia-300";
    return (
      <React.Fragment key={`code-${index}`}>
        {indent}<span className="text-sky-300">{key}</span><span className="text-slate-500">{colon}</span><span className={tailClass}>{tail}</span>
      </React.Fragment>
    );
  }

  return <span key={`code-${index}`} className="text-slate-400">{line}</span>;
}

function StaticHighlightedCode({ code }) {
  return (
    <div className="themed-scrollbar mono min-w-0 max-w-full overflow-x-auto text-xs leading-relaxed sm:text-[13px]">
      <pre data-testid="sample-code" aria-label={code} className="w-max min-w-full p-5 sm:p-6">
        {code.split("\n").map((line, index, lines) => (
          <React.Fragment key={`static-line-${index}`}>
            {highlightCodeLine(line, index)}
            {index < lines.length - 1 ? "\n" : null}
          </React.Fragment>
        ))}
      </pre>
    </div>
  );
}


/*
 * V11 scroll scene — komposisi dibuat seperti referensi MEGA:
 * - copy hero rata tengah dan menjadi fokus utama.
 * - badge tersebar mengelilingi hero (kiri/kanan/atas/bawah), bukan menumpuk di satu sisi.
 * - saat scroll dimulai copy naik + blur, seluruh badge bergerak menuju pusat.
 * - kartu final dirakit tepat di tengah viewport dan ditahan sebentar agar terlihat utuh.
 */
const ASSEMBLY_PIECES = [
  {
    icon: KeyRound,
    label: "API KEY",
    finalDesktop: "left-[28%] top-[2%]",
    finalMobile: "left-[11%] top-[1%]",
    desktop: [-150, -90, -12],
    mobile: [-38, -172, -10],
    entryDesktopY: 110,
    entryMobileY: 90,
  },
  {
    icon: Activity,
    label: "OTP LIVE",
    finalDesktop: "right-[28%] top-[2%]",
    finalMobile: "right-[11%] top-[1%]",
    desktop: [150, -85, 12],
    mobile: [4, -168, 10],
    entryDesktopY: 110,
    entryMobileY: 90,
  },
  {
    icon: Wallet,
    label: "SALDO",
    finalDesktop: "left-[2%] top-[54%]",
    finalMobile: "left-[-5%] top-[52%]",
    desktop: [-125, 40, 10],
    mobile: [-38, 28, 8],
  },
  {
    icon: CheckCircle2,
    label: "200 OK",
    finalDesktop: "right-[2%] top-[49%]",
    finalMobile: "right-[-5%] top-[52%]",
    desktop: [125, 48, -10],
    mobile: [38, 32, -8],
  },
  {
    icon: Layers3,
    label: "MULTI SERVER",
    finalDesktop: "left-[41%] bottom-[1%]",
    finalMobile: "left-[29%] bottom-[0%]",
    desktop: [0, 88, 7],
    mobile: [0, 72, 7],
  },
];

function AssemblyPiece({ item, progress, reducedMotion, mobile = false }) {
  const [startX, startY, startR] = mobile ? item.mobile : item.desktop;
  const entryY = mobile ? (item.entryMobileY || 0) : (item.entryDesktopY || 0);

  // Posisi CSS adalah SLOT FINAL. Saat load, dua badge bagian atas diberi safe-offset
  // tambahan supaya ikut pop-up bersama card tetapi tidak naik menutupi CTA/chip hero.
  // Offset ini dilepas bertahap begitu scroll dimulai sehingga posisi final assembly
  // dan seluruh efek scroll sesudahnya tetap sama.
  const x = useTransform(progress, [0, 0.1, 0.4, 0.68, 0.9, 1], [startX, startX, startX * 0.7, startX * 0.3, 0, 0]);
  const y = useTransform(progress, [0, 0.1, 0.4, 0.68, 0.9, 1], [startY + entryY, startY + entryY, startY * 0.7 + entryY * 0.5, startY * 0.3, 0, 0]);
  const rotate = useTransform(progress, [0, 0.44, 0.88, 1], [startR, startR * 0.52, 0, 0]);
  const scale = useTransform(progress, [0, 0.18, 0.62, 0.9, 1], [mobile ? 0.78 : 0.84, mobile ? 0.8 : 0.86, 0.94, 1, 1]);
  const opacity = useTransform(progress, [0, 0.06, 0.18, 1], [0.76, 0.82, 1, 1]);

  return (
    <motion.div
      aria-hidden="true"
      style={reducedMotion ? { opacity: 0.82 } : { x, y, rotate, scale, opacity }}
      className={`pointer-events-none absolute z-30 flex items-center rounded-2xl border border-border/90 bg-card/95 font-extrabold tracking-[0.08em] shadow-xl shadow-black/10 backdrop-blur-xl ${mobile ? "gap-1.5 px-2.5 py-2 text-[8px] sm:gap-2 sm:px-3 sm:py-2.5 sm:text-[9px]" : "gap-2 px-3.5 py-3 text-[11px]"} ${mobile ? item.finalMobile : item.finalDesktop}`}
    >
      <span className={`grid place-items-center rounded-xl border border-primary/20 bg-primary/10 text-primary ${mobile ? "h-6 w-6 sm:h-7 sm:w-7" : "h-8 w-8"}`}>
        <item.icon className={mobile ? "h-3 w-3" : "h-3.5 w-3.5"} />
      </span>
      <span>{item.label}</span>
    </motion.div>
  );
}

function AssemblyVisual({ progress, reducedMotion, stats, L, mobile = false }) {

  // V12: final dinaikkan sedikit dari V11 agar komposisinya lebih pas di tengah viewport.
  // Clipping tetap dimatikan, jadi seluruh kartu tetap utuh. Gerak vertikal dibuat lebih landai
  // agar rakitan terasa mengalir saat scroll, bukan meloncat di fase akhir.
  const stageY = useTransform(
    progress,
    [0, 0.22, 0.5, 0.7, 0.88, 1],
    [0, 0, mobile ? 16 : 28, mobile ? 54 : 86, mobile ? 94 : 150, mobile ? 110 : 175]
  );
  const cardY = useTransform(progress, [0, 0.36, 0.72, 1], [0, mobile ? 10 : 16, 0, 0]);
  const cardRotate = useTransform(progress, [0, 0.48, 0.88, 1], [0, 1.2, 0, 0]);
  const cardScale = useTransform(progress, [0, 0.42, 0.82, 1], [1, 0.965, 1, 1]);
  const haloRotate = useTransform(progress, [0, 0.48, 0.88, 1], [-16, -5, 14, 14]);
  const haloScale = useTransform(progress, [0, 0.45, 0.88, 1], [0.7, 0.88, 1, 1]);
  const haloOpacity = useTransform(progress, [0, 0.28, 0.56, 0.88, 1], [0, 0, 0.12, 0.28, 0.28]);
  const glowOpacity = useTransform(progress, [0, 0.34, 0.62, 1], [0, 0, 0.58, 0.58]);

  return (
    <motion.div
      style={reducedMotion ? undefined : { y: stageY }}
      className={`relative mx-auto w-full will-change-transform ${mobile ? "h-[360px] max-w-[390px]" : "h-[450px] max-w-[920px]"}`}
    >
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
        <motion.div
          aria-hidden="true"
          style={reducedMotion ? { opacity: 0 } : { rotate: haloRotate, scale: haloScale, opacity: haloOpacity }}
          className={`rounded-[38%] border border-primary/35 ${mobile ? "h-[235px] w-[235px]" : "h-[330px] w-[330px]"}`}
        />
      </div>
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
        <motion.div
          aria-hidden="true"
          style={reducedMotion ? { opacity: 0 } : { opacity: glowOpacity }}
          className={`rounded-full bg-primary/10 blur-3xl ${mobile ? "h-[205px] w-[270px]" : "h-[280px] w-[450px]"}`}
        />
      </div>

      {ASSEMBLY_PIECES.map((item) => (
        <AssemblyPiece key={item.label} item={item} progress={progress} reducedMotion={reducedMotion} mobile={mobile} />
      ))}

      <div className="absolute inset-0 z-20 flex items-center justify-center">
        <motion.div
          style={reducedMotion ? { opacity: 1 } : { y: cardY, rotate: cardRotate, scale: cardScale, opacity: 1 }}
          className={`w-full overflow-hidden rounded-[26px] border border-border bg-card/95 shadow-2xl shadow-black/15 backdrop-blur-xl ${mobile ? "max-w-[310px]" : "max-w-[560px]"}`}
        >
          <div className={`flex items-center gap-2 border-b border-border bg-muted/40 ${mobile ? "px-3 py-2.5" : "px-4 py-3"}`}>
            <span className="h-2.5 w-2.5 rounded-full bg-destructive/70" />
            <span className="h-2.5 w-2.5 rounded-full bg-amber-500/80" />
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-500/80" />
            <div className={`mono ml-2 flex items-center gap-2 text-muted-foreground ${mobile ? "text-[9px]" : "text-[11px]"}`}>
              <Terminal className="h-3.5 w-3.5" /> api.dapetotp
            </div>
            <span className={`ml-auto rounded-lg bg-emerald-500/10 font-bold text-emerald-500 ${mobile ? "px-1.5 py-1 text-[8px]" : "px-2 py-1 text-[10px]"}`}>ONLINE</span>
          </div>
          <div className={mobile ? "p-3.5" : "p-5 sm:p-6"}>
            <div className="grid grid-cols-2 gap-2 sm:gap-3">
              {[
                [CheckCircle2, L("API key per akun", "Per-account API key")],
                [Wallet, L("Pembayaran instan", "Instant payment")],
                [Activity, "OTP realtime"],
                [Layers3, L("Multi server", "Multi server")],
              ].map(([Icon, label]) => (
                <div key={label} className={`flex items-center rounded-2xl border border-border bg-background/70 ${mobile ? "gap-2 p-2" : "gap-2.5 p-3"}`}>
                  <span className={`grid shrink-0 place-items-center rounded-xl bg-primary/10 text-primary ${mobile ? "h-6 w-6" : "h-8 w-8"}`}><Icon className={mobile ? "h-3.5 w-3.5" : "h-4 w-4"} /></span>
                  <span className={`${mobile ? "text-[9px] leading-3" : "text-xs sm:text-sm"} font-bold`}>{label}</span>
                </div>
              ))}
            </div>
            <div className={`rounded-2xl border border-border bg-background ${mobile ? "mt-2 p-3" : "mt-3 p-4"}`}>
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className={`font-bold uppercase tracking-[0.18em] text-muted-foreground ${mobile ? "text-[8px]" : "text-[10px]"}`}>{L("Mulai dari", "Starting at")}</p>
                  <p className={`mt-1 font-extrabold text-primary ${mobile ? "text-lg" : "text-2xl"}`}>{stats?.cheapest ? rupiah(stats.cheapest) : "—"}</p>
                </div>
                <div className="text-right">
                  <p className={`font-bold uppercase tracking-[0.18em] text-muted-foreground ${mobile ? "text-[8px]" : "text-[10px]"}`}>{L("Layanan ID", "ID services")}</p>
                  <p className={`mt-1 font-extrabold ${mobile ? "text-lg" : "text-2xl"}`}>{stats?.services ?? "—"}</p>
                </div>
              </div>
            </div>
          </div>
        </motion.div>
      </div>
    </motion.div>
  );
}

function InitialFadeUp({ children, delay = 0, className = "", reducedMotion = false }) {
  return (
    <motion.div
      initial={reducedMotion ? false : { opacity: 0, y: 24, filter: "blur(6px)" }}
      animate={reducedMotion ? undefined : { opacity: 1, y: 0, filter: "blur(0px)" }}
      transition={{ duration: 0.58, delay, ease: [0.22, 1, 0.36, 1] }}
      className={className}
    >
      {children}
    </motion.div>
  );
}

function HeroCopy({ site, t, L, reducedMotion }) {
  return (
    <div className="mx-auto w-full max-w-5xl text-center">
      <InitialFadeUp delay={0.05} reducedMotion={reducedMotion}>
        <span data-testid="hero-badge" className="inline-flex items-center gap-2 rounded-full border border-primary/30 bg-primary/10 px-4 py-2 text-[11px] font-bold tracking-[0.22em] text-primary">
          <Radio className="h-3.5 w-3.5" /> {L("REST API PUBLIK", "PUBLIC REST API")}
        </span>
      </InitialFadeUp>

      <InitialFadeUp delay={0.16} reducedMotion={reducedMotion}>
        <h1 data-testid="hero-title" className="mx-auto mt-7 max-w-5xl text-4xl font-extrabold leading-[1.02] sm:text-5xl md:text-6xl lg:text-7xl">
          <span className="block">{site?.site_name || "dapetOTP"}.</span>
          <span className="mt-2 block text-primary">{L("Solusi verifikasi untuk semua layanan.", "Verification for every service.")}</span>
        </h1>
      </InitialFadeUp>

      <InitialFadeUp delay={0.28} reducedMotion={reducedMotion}>
        <p data-testid="hero-subtitle" className="mx-auto mt-7 max-w-3xl text-sm leading-7 text-muted-foreground sm:text-base lg:text-lg">
          {L(
            "Sewa nomor virtual, terima OTP realtime, isi saldo otomatis, dan otomatiskan semuanya lewat REST API dengan API key pribadi. Daftar, isi saldo, langsung pesan.",
            "Rent virtual numbers, receive OTPs in realtime, top up automatically and automate everything through a REST API with your own key."
          )}
        </p>
      </InitialFadeUp>

      <InitialFadeUp delay={0.4} reducedMotion={reducedMotion}>
        <div className="mt-9 flex flex-wrap items-center justify-center gap-3">
          <Link data-testid="hero-cta-login" to="/masuk" className="soft-float group flex items-center gap-2 rounded-2xl bg-primary px-6 py-3.5 text-sm font-bold text-primary-foreground shadow-lg shadow-primary/20 transition-all hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/25">
            {t("getStarted")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
          </Link>
          <Link data-testid="hero-cta-pricing" to="/harga" className="soft-float rounded-2xl border border-border bg-card/80 px-6 py-3.5 text-sm font-bold backdrop-blur transition-all duration-300 hover:-translate-y-0.5 hover:border-primary hover:text-primary hover:shadow-lg hover:shadow-primary/5">
            {L("Lihat Harga", "See Pricing")}
          </Link>
          <Link data-testid="hero-cta-docs" to="/docs" className="soft-float group flex items-center gap-1.5 rounded-2xl px-4 py-3.5 text-sm font-bold text-muted-foreground transition-all duration-300 hover:-translate-y-0.5 hover:text-primary">
            {t("docs")} <ArrowUpRight className="h-4 w-4 transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5" />
          </Link>
        </div>
      </InitialFadeUp>

      <div className="mt-3 flex flex-wrap justify-center gap-2 sm:mt-9">
        {CHIPS.map((c, i) => (
          <motion.span
            key={c.id}
            data-testid={`hero-chip-${i}`}
            initial={reducedMotion ? false : { opacity: 0, y: 18 }}
            animate={reducedMotion ? undefined : { opacity: 1, y: 0 }}
            transition={{ duration: 0.48, delay: 0.54 + i * 0.075, ease: [0.22, 1, 0.36, 1] }}
            style={{ "--float-delay": `${i * -0.65}s` }}
            className="soft-float flex items-center gap-2 rounded-xl border border-border/80 bg-card/60 px-3 py-2 text-xs font-semibold text-muted-foreground backdrop-blur transition-[border-color,color,box-shadow] duration-300 hover:border-primary/50 hover:text-foreground hover:shadow-lg hover:shadow-primary/5"
          >
            <c.icon className="h-3.5 w-3.5 text-primary" /> {L(c.id, c.en)}
          </motion.span>
        ))}
      </div>
    </div>
  );
}

const REVEAL_EASE = [0.22, 1, 0.36, 1];

function Reveal({ children, className = "", delay = 0, x = 0, y = 28, amount = 0.18, reducedMotion = false }) {
  return (
    <motion.div
      initial={reducedMotion ? false : { opacity: 0, x, y, scale: 0.985, filter: "blur(5px)" }}
      whileInView={reducedMotion ? undefined : { opacity: 1, x: 0, y: 0, scale: 1, filter: "blur(0px)" }}
      viewport={{ once: true, amount, margin: "0px 0px -7% 0px" }}
      transition={{ duration: 0.62, delay, ease: REVEAL_EASE }}
      className={`min-w-0 ${className}`}
    >
      {children}
    </motion.div>
  );
}

export default function Landing() {
  const { t, lang } = useI18n();
  const { site, contact } = useSite();
  const [stats, setStats] = useState(null);
  const [latestPosts, setLatestPosts] = useState([]);
  const heroSceneRef = useRef(null);
  const reducedMotion = useReducedMotion();

  const { scrollYProgress: heroProgress } = useScroll({
    target: heroSceneRef,
    offset: ["start start", "end end"],
  });

  // Scroll mentah browser bisa bergerak per langkah/wheel tick. Spring ini hanya menghaluskan
  // progress visual; arah tetap 100% mengikuti scroll dan otomatis mundur saat scroll ke atas.
  const smoothHeroProgress = useSpring(heroProgress, {
    stiffness: 115,
    damping: 30,
    mass: 0.22,
    restDelta: 0.0005,
  });

  /*
   * V13 timeline:
   * 0–10%   : copy hero masuk fade-up bergantian; card + badge pop-up bersama di area bawah.
   * 10–40%  : hero naik, blur, dan redup sehingga panggung tengah terbuka.
   * 10–72%  : badge bergerak perlahan dari tepi menuju pusat dan kartu dirakit.
   * 72–100% : rakitan final ditahan, sementara section berikutnya mulai naik dari bawah untuk menghilangkan dead-space.
   */
  const copyY = useTransform(smoothHeroProgress, [0, 0.1, 0.24, 0.4, 1], [0, 0, -78, -260, -260]);
  const copyOpacity = useTransform(smoothHeroProgress, [0, 0.1, 0.24, 0.4, 1], [1, 1, 0.78, 0.035, 0.035]);
  const copyScale = useTransform(smoothHeroProgress, [0, 0.12, 0.4, 1], [1, 1, 0.97, 0.97]);
  const copyFilter = useTransform(smoothHeroProgress, [0, 0.12, 0.25, 0.4, 1], [
    "blur(0px)",
    "blur(0px)",
    "blur(2px)",
    "blur(9px)",
    "blur(9px)",
  ]);
  const assemblyProgress = useTransform(smoothHeroProgress, [0, 0.085, 0.72, 1], [0, 0, 1, 1]);
  // Saat halaman pertama dimuat, seluruh assembly diparkir lebih bawah agar CTA dan
  // chip hero punya ruang aman. Assembly baru naik setelah copy hero mulai bergerak,
  // sehingga tidak ada fase card/floating badge menimpa konten utama.
  const assemblyDockYMobile = useTransform(smoothHeroProgress, [0, 0.08, 0.26, 1], [330, 330, 0, 0]);
  const assemblyDockYDesktop = useTransform(smoothHeroProgress, [0, 0.08, 0.26, 1], [360, 360, 0, 0]);
  const scrollHintOpacity = useTransform(smoothHeroProgress, [0, 0.055, 0.14, 1], [0.72, 0.72, 0, 0]);

  useEffect(() => {
    http.get("/public/stats").then(({ data }) => setStats(data)).catch(() => {});
    http.get("/blog").then(({ data }) => setLatestPosts((data?.items || []).slice(0, 3))).catch(() => {});
  }, []);

  const L = (id, en) => (lang === "id" ? id : en);
  const visibleContacts = getVisibleContacts(contact);

  return (
    <div data-testid="landing-page" className="overflow-hidden">
      {/*
        HERO V13 — komposisi center seperti referensi MEGA.
        Sticky memakai top-0 agar tidak menciptakan pita kosong di bawah navbar.
        Navbar tetap berada di atas karena z-index layout, sedangkan isi hero diberi safe padding.
      */}
      <section ref={heroSceneRef} className="relative z-10 h-[138svh] sm:h-[136svh] lg:h-[134vh]">
        <div className="sticky top-0 h-[100svh] overflow-visible">
          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_50%_22%,hsl(var(--primary)/0.17),transparent_34%),radial-gradient(circle_at_50%_82%,hsl(var(--primary)/0.07),transparent_30%)]" />
          <div className="pointer-events-none absolute inset-0 opacity-35 [background-image:linear-gradient(hsl(var(--border)/.35)_1px,transparent_1px),linear-gradient(90deg,hsl(var(--border)/.35)_1px,transparent_1px)] [background-size:48px_48px] [mask-image:linear-gradient(to_bottom,#000,transparent_94%)]" />

          <motion.div
            style={reducedMotion ? undefined : { y: copyY, opacity: copyOpacity, scale: copyScale, filter: copyFilter }}
            className="absolute inset-0 z-10 will-change-transform"
          >
            <div className="mx-auto flex h-full max-w-7xl items-center justify-center px-5 pb-[24vh] pt-[76px] sm:px-6 sm:pt-[82px] lg:px-8">
              <div className="w-full">
                <HeroCopy site={site} t={t} L={L} reducedMotion={reducedMotion} />
                <motion.div
                  style={reducedMotion ? { opacity: 0.72 } : { opacity: scrollHintOpacity }}
                  className="mx-auto mt-8 flex w-fit items-center gap-3 text-[10px] font-bold uppercase tracking-[0.2em] text-muted-foreground"
                >
                  <span className="h-7 w-px bg-primary/70" />
                  <span>{L("Scroll untuk merakit tampilan", "Scroll to assemble")}</span>
                </motion.div>
              </div>
            </div>
          </motion.div>

          {/*
            Assembly tetap memenuhi viewport. V13 mempertahankan overflow-visible pada sticky karena itulah
            yang memotong bagian bawah kartu saat sticky mulai lepas. Titik final juga diturunkan
            sedikit lagi dan scene dipendekkan agar section berikutnya masuk lebih cepat.
          */}
          <div className="pointer-events-none absolute inset-x-0 bottom-0 top-[70px] z-20 flex items-center justify-center px-2 sm:top-[76px] sm:px-5 lg:px-8">
            <motion.div style={reducedMotion ? undefined : { y: assemblyDockYMobile }} className="w-full lg:hidden">
              <motion.div
                initial={reducedMotion ? false : { opacity: 0, y: 58, scale: 0.97 }}
                animate={reducedMotion ? undefined : { opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.7, delay: 0.48, ease: [0.22, 1, 0.36, 1] }}
              >
                <AssemblyVisual progress={assemblyProgress} reducedMotion={reducedMotion} stats={stats} L={L} mobile />
              </motion.div>
            </motion.div>
            <motion.div style={reducedMotion ? undefined : { y: assemblyDockYDesktop }} className="hidden w-full lg:block">
              <motion.div
                initial={reducedMotion ? false : { opacity: 0, y: 66, scale: 0.97 }}
                animate={reducedMotion ? undefined : { opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.7, delay: 0.48, ease: [0.22, 1, 0.36, 1] }}
              >
                <AssemblyVisual progress={assemblyProgress} reducedMotion={reducedMotion} stats={stats} L={L} />
              </motion.div>
            </motion.div>
          </div>
        </div>
      </section>
      {/* SAMPLE REQUEST
          V13: section berikutnya ditarik naik ke fase akhir sticky hero.
          Ini menghapus dead-space besar (terutama di mobile) tanpa memendekkan
          timeline assembly secara ekstrem. Hero tetap z-10 agar final card tampil
          di atas background section berikutnya selama overlap.
      */}
      <section className="relative z-0 -mt-[18svh] bg-muted/30 sm:-mt-[20svh] lg:-mt-[20vh]">
        <div className="mx-auto grid min-w-0 max-w-7xl gap-8 px-6 pb-14 pt-24 sm:py-16 lg:grid-cols-[.72fr_1.28fr] lg:items-center">
          <div className="min-w-0">
            <Reveal reducedMotion={reducedMotion} y={24} delay={0}>
              <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("CONTOH REQUEST", "SAMPLE REQUEST")}</p>
            </Reveal>
            <Reveal reducedMotion={reducedMotion} y={24} delay={0.1}>
              <h2 className="mt-4 max-w-full break-words text-3xl font-extrabold leading-tight sm:text-4xl">{L("Satu request, langsung jalan.", "One request, ready to go.")}</h2>
            </Reveal>
            <Reveal reducedMotion={reducedMotion} y={24} delay={0.2}>
              <p className="mt-4 max-w-full text-sm leading-6 text-muted-foreground sm:max-w-md">{L("Alur API dibuat sederhana: autentikasi dengan API key, kirim ID harga layanan, lalu pantau status dan OTP dari endpoint yang sama.", "The API flow stays simple: authenticate, send a service price ID, then monitor status and OTP from the same API.")}</p>
            </Reveal>
            <div className="mt-6 space-y-3 text-sm">
              {[L("Header sederhana", "Simple headers"), L("Respons JSON konsisten", "Consistent JSON responses"), L("Siap dipakai dari backend apa pun", "Works from any backend")].map((x, i) => (
                <Reveal key={x} reducedMotion={reducedMotion} y={18} delay={0.3 + i * 0.09}>
                  <div className="flex items-center gap-3 font-semibold"><CheckCircle2 className="h-4 w-4 text-primary" /> {x}</div>
                </Reveal>
              ))}
            </div>
          </div>

          <Reveal reducedMotion={reducedMotion} y={24} delay={0.16}>
            <div className="w-full min-w-0 max-w-full overflow-hidden rounded-[28px] border border-border bg-[hsl(222_47%_6%)] text-slate-100 shadow-xl transition-transform duration-300 hover:-translate-y-1">
              <div className="flex flex-wrap items-center gap-3 border-b border-white/10 bg-white/5 px-5 py-3.5">
                <span className="mono rounded-lg bg-emerald-400/15 px-2 py-1 text-[11px] font-bold text-emerald-300">POST</span>
                <code className="mono text-xs text-slate-300">/api/v1/orders</code>
                <span className="mono ml-auto rounded-lg bg-blue-400/15 px-2 py-1 text-[11px] font-bold text-blue-300">200 OK</span>
              </div>
              <StaticHighlightedCode code={CODE} />
            </div>
          </Reveal>
        </div>
      </section>

      {/* WHY */}
      <section className="mx-auto max-w-7xl px-6 py-20">
        <div className="grid gap-8 lg:grid-cols-[.8fr_1.2fr] lg:items-end">
          <Reveal reducedMotion={reducedMotion} x={-22}>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("KENAPA DAPETOTP", "WHY DAPETOTP")}</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("API yang rapi untuk dipakai sehari-hari.", "A tidy API for everyday use.")}</h2>
          </Reveal>
          <Reveal reducedMotion={reducedMotion} x={22} delay={0.06} className="lg:justify-self-end">
            <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
            {L("Fokusnya bukan hanya membeli nomor, tetapi membuat seluruh alur verifikasi lebih mudah dipantau: harga, saldo, transaksi, OTP, dan integrasi API berada di satu tempat.", "More than buying numbers: pricing, balance, transactions, OTP and API integration stay visible in one place.")}
            </p>
          </Reveal>
        </div>

        <div className="mt-10 grid gap-5 lg:grid-cols-3">
          {WHY.map((w, i) => (
            <Reveal key={w.t_id} reducedMotion={reducedMotion} delay={i * 0.08} y={34} className="h-full">
              <article data-testid={`why-card-${i}`} className="group relative h-full overflow-hidden rounded-[28px] border border-border bg-card p-7 text-center transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/5">
                <div className="absolute right-0 top-0 h-32 w-32 translate-x-10 -translate-y-10 rounded-full bg-primary/12 blur-3xl" />
                <span className="relative mx-auto grid h-12 w-12 place-items-center rounded-2xl border border-primary/20 bg-primary/10 text-primary"><w.icon className="h-5 w-5" /></span>
                <h3 className="relative mt-8 text-xl font-extrabold">{L(w.t_id, w.t_en)}</h3>
                <p className="relative mt-3 text-sm leading-6 text-muted-foreground">{L(w.d_id, w.d_en)}</p>
                <div className="relative mt-7 h-px w-full bg-border" />
                <p className="relative mt-4 text-xs font-bold uppercase tracking-wider text-primary">0{i + 1}</p>
              </article>
            </Reveal>
          ))}
        </div>
      </section>

      {/* RUNTIME */}
      <section className="border-y border-border bg-card/50">
        <div className="mx-auto max-w-7xl px-6 py-20">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <Reveal reducedMotion={reducedMotion} x={-22}>
              <p className="text-xs font-bold tracking-[0.22em] text-primary">{L("CARA KERJA", "HOW IT WORKS")}</p>
              <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Dari pilih layanan sampai OTP, alurnya sederhana.", "From choosing a service to receiving an OTP, the flow stays simple.")}</h2>
            </Reveal>
            <Reveal reducedMotion={reducedMotion} x={22} delay={0.06}>
              <span className="inline-flex items-center gap-2 rounded-full border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-xs font-bold text-emerald-500"><span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> {L("OTOMATIS", "AUTOMATIC")}</span>
            </Reveal>
          </div>

          <div className="relative mt-10 grid gap-4 lg:grid-cols-3">
            <div className="pointer-events-none absolute left-[16.6%] right-[16.6%] top-7 hidden h-px bg-border lg:block" />
            {RUNTIME.map((r, i) => (
              <Reveal key={r.t_id} reducedMotion={reducedMotion} delay={i * 0.08} y={30} className="h-full">
                <article data-testid={`runtime-card-${i}`} className="relative h-full rounded-[26px] border border-border bg-background p-6 transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5">
                  <div className="relative z-10 flex items-center gap-4">
                    <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-primary/20 bg-primary/10 text-primary"><r.icon className="h-5 w-5" /></span>
                    <div>
                      <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">STEP 0{i + 1}</p>
                      <h3 className="mt-1 text-lg font-extrabold">{L(r.t_id, r.t_en)}</h3>
                    </div>
                  </div>
                  <p className="mt-5 text-sm leading-6 text-muted-foreground">{L(r.d_id, r.d_en)}</p>
                </article>
              </Reveal>
            ))}
          </div>
        </div>
      </section>

      {/* LIVE STATS */}
      <section className="mx-auto max-w-7xl px-6 py-20">
        <Reveal reducedMotion={reducedMotion} y={34}>
          <div className="relative overflow-hidden rounded-[34px] border border-border bg-card p-7 shadow-xl shadow-primary/5 transition-transform duration-300 hover:-translate-y-1 sm:p-10">
          <div className="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-primary/12 blur-3xl" />
          <div className="pointer-events-none absolute -bottom-28 left-1/3 h-64 w-64 rounded-full bg-primary/5 blur-3xl" />
          <div className="relative flex flex-wrap items-center gap-3">
            <span className="flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1.5 text-[11px] font-bold text-emerald-500"><span className="h-2 w-2 animate-pulse rounded-full bg-emerald-500" /> LIVE</span>
            <p className="text-lg font-extrabold">{L("Katalog layanan selalu diperbarui", "Service catalog stays up to date")}</p>
          </div>

          <div className="relative mt-8 grid gap-3 sm:grid-cols-3">
            {[
              [Globe2, L("Negara", "Countries"), stats?.countries, "number"],
              [ShieldCheck, L("Layanan Indonesia", "Indonesian services"), stats?.services, "number"],
              [Wallet, L("Mulai dari", "Starting at"), stats?.cheapest, "rupiah"],
            ].map(([Icon, label, value, kind], i) => (
              <Reveal key={label} reducedMotion={reducedMotion} delay={0.08 + i * 0.07} y={22}>
                <div data-testid={`live-stat-${i}`} className="rounded-2xl border border-border bg-background/70 p-5 backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5">
                  <span className="grid h-10 w-10 place-items-center rounded-xl border border-primary/20 bg-primary/10 text-primary"><Icon className="h-4 w-4" /></span>
                  <p className="mt-5 text-[10px] font-bold uppercase tracking-[0.18em] text-muted-foreground">{label}</p>
                  <p className="mt-1 text-3xl font-extrabold text-primary"><CountUp value={value} format={kind === "rupiah" ? (n) => rupiah(Math.round(n)) : (n) => Math.round(n).toLocaleString("id-ID")} /></p>
                </div>
              </Reveal>
            ))}
            </div>
          </div>
        </Reveal>
      </section>

      {/* PRICE LIST */}
      <section className="mx-auto max-w-7xl px-6 pb-20">
        <div className="flex flex-wrap items-end justify-between gap-5">
          <Reveal reducedMotion={reducedMotion} x={-22}>
            <p className="text-xs font-bold tracking-[0.22em] text-primary">PRICE LIST</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Layanan termurah hari ini.", "Cheapest services today.")}</h2>
            <p className="mt-3 text-sm text-muted-foreground">{L("Harga di bawah sudah memakai kalkulasi harga jual untuk pelanggan.", "Prices below already use the customer selling-price calculation.")}</p>
          </Reveal>
          <Reveal reducedMotion={reducedMotion} x={22} delay={0.06}>
            <Link to="/harga" data-testid="pricelist-all" className="group flex items-center gap-2 rounded-2xl border border-border bg-card px-5 py-3 text-sm font-bold transition-all duration-300 hover:-translate-y-0.5 hover:border-primary hover:text-primary hover:shadow-lg hover:shadow-primary/5">
              {L("Lihat semua harga", "See all prices")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
            </Link>
          </Reveal>
        </div>

        <div className="mt-9 grid gap-3 lg:grid-cols-2">
          {(stats?.top || []).map((s, i) => (
            <Reveal key={s.name + i} reducedMotion={reducedMotion} delay={(i % 6) * 0.045} y={22}>
              <div data-testid={`pricelist-item-${i}`} className="group flex items-center gap-4 rounded-2xl border border-border bg-card p-4 transition-all duration-300 hover:-translate-y-1 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5">
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
            </Reveal>
          ))}
          {!stats?.top?.length && <p className="text-sm text-muted-foreground">{L("Katalog belum tersedia.", "Catalog unavailable.")}</p>}
        </div>
      </section>

      {/* BLOG TERBARU */}
      <section className="border-t border-border bg-card/35">
        <div className="mx-auto max-w-7xl px-6 py-20">
          <div className="flex flex-wrap items-end justify-between gap-5">
            <Reveal reducedMotion={reducedMotion} x={-22}>
              <div className="flex items-center gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-primary/15 text-primary"><BookOpen className="h-5 w-5" /></span>
                <div>
                  <p className="text-xs font-bold tracking-[0.22em] text-primary">BLOG</p>
                  <h2 className="mt-1 text-3xl font-extrabold sm:text-4xl">{L("Artikel terbaru.", "Latest articles.")}</h2>
                </div>
              </div>
              <p className="mt-4 max-w-2xl text-sm leading-6 text-muted-foreground">{L("Panduan, informasi layanan, dan update terbaru dari kami.", "Guides, service information and the latest updates from us.")}</p>
            </Reveal>
            <Reveal reducedMotion={reducedMotion} x={22} delay={0.06}>
              <Link to="/blog" className="group flex items-center gap-2 rounded-2xl border border-border bg-background px-5 py-3 text-sm font-bold transition-all hover:-translate-y-0.5 hover:border-primary hover:text-primary">
                {L("Lihat artikel lainnya", "See more articles")} <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" />
              </Link>
            </Reveal>
          </div>

          <div className="mt-9 grid gap-5 lg:grid-cols-3">
            {latestPosts.map((post, i) => {
              const date = post.published_at || post.created_at;
              return (
                <Reveal key={post.id || post.slug} reducedMotion={reducedMotion} delay={i * 0.08} y={28} className="h-full">
                  <Link to={`/blog/${post.slug}`} className="group block h-full overflow-hidden rounded-[28px] border border-border bg-card transition-all hover:-translate-y-1 hover:border-primary/50 hover:shadow-xl hover:shadow-primary/5">
                    {post.cover_url ? (
                      <img src={post.cover_url} alt={post.title} className="h-48 w-full border-b border-border bg-muted object-cover" />
                    ) : (
                      <div className="grid h-48 w-full place-items-center border-b border-border bg-primary/5"><BookOpen className="h-9 w-9 text-primary/60" /></div>
                    )}
                    <div className="p-5">
                      <div className="flex items-center gap-2 text-[11px] font-semibold text-muted-foreground">
                        <CalendarDays className="h-3.5 w-3.5 text-primary" />
                        {date ? new Date(date).toLocaleDateString("id-ID", { day: "numeric", month: "long", year: "numeric" }) : L("Artikel", "Article")}
                      </div>
                      <h3 className="mt-3 line-clamp-2 text-xl font-extrabold transition-colors group-hover:text-primary">{post.title}</h3>
                      {post.excerpt && <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">{post.excerpt}</p>}
                      <p className="mt-5 flex items-center gap-2 text-xs font-bold text-primary">{L("Baca selengkapnya", "Read more")} <ArrowRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" /></p>
                    </div>
                  </Link>
                </Reveal>
              );
            })}
            {latestPosts.length === 0 && (
              <div className="lg:col-span-3 rounded-3xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">{L("Belum ada artikel yang dipublikasikan.", "No published articles yet.")}</div>
            )}
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section className="border-t border-border bg-muted/25">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 py-20 lg:grid-cols-[.7fr_1.3fr]">
          <Reveal reducedMotion={reducedMotion} x={-22}>
            <span className="inline-grid h-11 w-11 place-items-center rounded-2xl bg-primary/10 text-primary"><Sparkles className="h-5 w-5" /></span>
            <p className="mt-6 text-xs font-bold tracking-[0.22em] text-primary">FAQ</p>
            <h2 className="mt-4 text-3xl font-extrabold sm:text-4xl">{L("Pertanyaan yang sering ditanyakan.", "Frequently asked questions.")}</h2>
            <p className="mt-4 max-w-full text-sm leading-6 text-muted-foreground sm:max-w-md">{L("Masih ada yang belum jelas? Masuk ke Docs untuk contoh request lengkap atau hubungi support.", "Need more detail? Open Docs for complete request examples or contact support.")}</p>
          </Reveal>

          <Reveal reducedMotion={reducedMotion} x={22} delay={0.06}>
            <Accordion type="single" collapsible className="space-y-3">
            {FAQ.map(([q, a], i) => (
              <AccordionItem key={q} value={`f${i}`} className="overflow-hidden rounded-2xl border border-border bg-card px-5 transition-all duration-300 hover:border-primary/35 data-[state=open]:border-primary/40">
                <AccordionTrigger data-testid={`landing-faq-${i}`} className="text-left text-sm font-bold hover:no-underline">{q}</AccordionTrigger>
                <AccordionContent className="text-sm leading-6 text-muted-foreground">{a}</AccordionContent>
              </AccordionItem>
            ))}
            </Accordion>
          </Reveal>
        </div>
      </section>

      {visibleContacts.length > 0 && (
        <section id="contact" className="border-t border-border bg-card/45">
          <div className="mx-auto max-w-7xl px-6 py-20">
            <Reveal reducedMotion={reducedMotion} y={24}>
              <div className="mx-auto max-w-2xl text-center">
                <span className="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-primary/15 text-primary"><LifeBuoy className="h-5 w-5" /></span>
                <p className="mt-5 text-xs font-bold tracking-[0.22em] text-primary">CONTACT US</p>
                <h2 className="mt-3 text-3xl font-extrabold sm:text-4xl">{L("Butuh bantuan atau ingin terhubung?", "Need help or want to connect?")}</h2>
                <p className="mt-3 text-sm leading-6 text-muted-foreground">{L("Pilih salah satu kontak resmi yang sedang aktif di bawah ini.", "Choose one of the active official contacts below.")}</p>
              </div>
              <ContactLinks contact={contact} className="mt-9" />
            </Reveal>
          </div>
        </section>
      )}

      <footer className="border-t border-border bg-background">
        <div className="mx-auto max-w-7xl px-6 py-10">
          <div className="grid gap-7 sm:grid-cols-[1fr_auto] sm:items-end">
            <div>
              <p className="text-xl font-extrabold text-foreground">{site?.site_name || "dapetOTP"}</p>
              <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">{site?.tagline || L("Solusi nomor virtual dan OTP untuk kebutuhan verifikasi.", "Virtual number and OTP solutions for verification needs.")}</p>
            </div>
            <nav className="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-muted-foreground">
              <Link to="/harga" className="hover:text-primary">{L("Harga", "Pricing")}</Link>
              <Link to="/docs" className="hover:text-primary">Docs</Link>
              <Link to="/blog" className="hover:text-primary">Blog</Link>
              <Link to="/faq" className="hover:text-primary">FAQ</Link>
              {visibleContacts.length > 0 && <a href="#contact" className="hover:text-primary">Contact US</a>}
            </nav>
          </div>
          <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-6 text-xs text-muted-foreground">
            <p>© {new Date().getFullYear()} {site?.site_name || "dapetOTP"}. All rights reserved.</p>
            <p>REST API • OTP Realtime</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
