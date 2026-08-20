import React, { useEffect, useState } from "react";
import { Wallet, BadgeCheck, ShoppingBag, PieChart, ShoppingCart, Plus, Megaphone, Crown, ChevronRight } from "lucide-react";
import { http, rupiah } from "@/lib/api";
import { AnnouncementContent } from "@/components/AnnouncementContent";

const LABEL_TONE = {
  INFORMASI: "bg-sky-500/15 text-sky-500 border-sky-500/40",
  UPDATE: "bg-violet-500/15 text-violet-500 border-violet-500/40",
  PENTING: "bg-destructive/15 text-destructive border-destructive/40",
  PROMO: "bg-emerald-500/15 text-emerald-500 border-emerald-500/40",
  MAINTENANCE: "bg-amber-500/15 text-amber-500 border-amber-500/40",
};

const TIER_TONE = {
  member: "bg-slate-500/15 text-slate-400 border-slate-500/40",
  reseller: "bg-sky-500/15 text-sky-500 border-sky-500/40",
  vip: "bg-amber-500/15 text-amber-500 border-amber-500/40",
};

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-5 ${className}`}>{children}</div>
);

export const Overview = ({ summary, onGo }) => {
  const [ann, setAnn] = useState([]);
  const [tiers, setTiers] = useState(null);
  const [prices, setPrices] = useState(null);
  const [openTier, setOpenTier] = useState(false);

  useEffect(() => {
    http.get("/announcements").then(({ data }) => setAnn(data.items)).catch(() => {});
    http.get("/public/tiers").then(({ data }) => setTiers(data)).catch(() => {});
  }, []);

  const loadTierPrices = async () => {
    setOpenTier((o) => !o);
    if (prices) return;
    try {
      const { data: c } = await http.get("/catalog/countries");
      const idn = c.items.find((x) => x.name === "Indonesia") || c.items[0];
      const { data } = await http.get("/catalog/tier-prices", { params: { country_id: idn.id } });
      setPrices(data);
    } catch { /* katalog belum tersedia */ }
  };

  const tier = summary?.tier || "member";

  const STATS = [
    { icon: Wallet, label: "Sisa Saldo", value: rupiah(summary?.balance), tone: "text-primary bg-primary/15" },
    { icon: BadgeCheck, label: "Level Akun", value: summary?.tier_label || "Member", tone: "text-sky-500 bg-sky-500/15" },
    { icon: ShoppingBag, label: "Pesanan Anda", value: `${summary?.orders ?? 0} Trx`, tone: "text-emerald-500 bg-emerald-500/15" },
    { icon: PieChart, label: "Pengeluaran", value: rupiah(summary?.spend), tone: "text-amber-500 bg-amber-500/15" },
  ];

  return (
    <div data-testid="dash-overview" className="space-y-5">
      <div data-testid="welcome-banner" className="rise flex flex-wrap items-center gap-4 rounded-3xl border border-primary/40 bg-primary/10 p-5">
        <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary text-primary-foreground">
          <BadgeCheck className="h-5 w-5" />
        </span>
        <div className="min-w-0">
          <p className="text-sm font-extrabold">Selamat datang, {summary?.name}</p>
          <p className="mt-0.5 text-xs text-muted-foreground">{summary?.email}</p>
        </div>
        <span className={`ml-auto rounded-xl border px-3 py-1.5 text-[11px] font-bold uppercase ${TIER_TONE[tier]}`}>
          {summary?.tier_label}
        </span>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {STATS.map((s, i) => (
          <Card key={s.label} data-testid={`overview-stat-${i}`} className="hover-lift">
            <span className={`grid h-10 w-10 place-items-center rounded-2xl ${s.tone}`}><s.icon className="h-4 w-4" /></span>
            <p className="mt-3 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{s.label}</p>
            <p className="mt-1 truncate text-xl font-extrabold">{s.value}</p>
          </Card>
        ))}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <button
          data-testid="overview-cta-order"
          onClick={() => onGo("beli")}
          className="flex items-center justify-center gap-2 rounded-2xl bg-primary py-4 text-sm font-bold text-primary-foreground transition-transform hover:scale-[1.02]"
        >
          <ShoppingCart className="h-4 w-4" /> Pemesanan
        </button>
        <button
          data-testid="overview-cta-topup"
          onClick={() => onGo("saldo")}
          className="flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-4 text-sm font-bold text-white transition-transform hover:scale-[1.02]"
        >
          <Plus className="h-4 w-4" /> Deposit
        </button>
      </div>

      <Card className="!p-0">
        <button data-testid="tier-toggle" onClick={loadTierPrices} className="flex w-full items-center gap-3 px-5 py-4 text-left">
          <Crown className="h-4 w-4 text-amber-500" />
          <span className="text-sm font-bold">Harga per Level Akun</span>
          <ChevronRight className={`ml-auto h-4 w-4 transition-transform ${openTier ? "rotate-90" : ""}`} />
        </button>
        {openTier && (
          <div className="border-t border-border p-5" data-testid="tier-prices">
            <div className="flex flex-wrap gap-2">
              {(tiers?.items || []).map((t) => (
                <span key={t.key} className={`rounded-xl border px-3 py-1.5 text-[11px] font-bold uppercase ${TIER_TONE[t.key]} ${tiers.current === t.key ? "ring-1 ring-primary" : ""}`}>
                  {t.label}{t.min_topup ? ` · min ${rupiah(t.min_topup)}` : ""}
                </span>
              ))}
            </div>
            {prices ? (
              <div className="mt-4 overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-[11px] uppercase text-muted-foreground">
                      <th className="py-2">Layanan</th>
                      <th className="py-2">Member</th>
                      <th className="py-2">Reseller</th>
                      <th className="py-2">VIP</th>
                    </tr>
                  </thead>
                  <tbody>
                    {prices.member.map((m, i) => (
                      <tr key={m.service_code} className="border-t border-border">
                        <td className="flex items-center gap-2 py-2.5">
                          {m.logo && <img src={m.logo} alt="" className="h-6 w-6 rounded-lg bg-muted object-contain p-0.5" onError={(e) => { e.target.style.display = "none"; }} />}
                          <span className="font-semibold">{m.service_name}</span>
                        </td>
                        <td className="py-2.5">{rupiah(m.price)}</td>
                        <td className="py-2.5 text-sky-500">{rupiah(prices.reseller[i]?.price)}</td>
                        <td className="py-2.5 font-bold text-amber-500">{rupiah(prices.vip[i]?.price)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="mt-4 h-16 animate-pulse rounded-2xl bg-muted" />
            )}
            {summary?.next_tier && (
              <p className="mt-4 text-xs text-muted-foreground">
                Naik ke <b className="uppercase text-primary">{summary.next_tier}</b> dengan total deposit {rupiah(summary.next_tier_min_topup)} · terkumpul {rupiah(summary.topup_total)}
              </p>
            )}
          </div>
        )}
      </Card>

      <Card>
        <div className="flex items-center gap-2">
          <Megaphone className="h-4 w-4 text-primary" />
          <p className="text-sm font-bold">Informasi Terbaru</p>
        </div>
        <div className="mt-4 space-y-3">
          {ann.map((a) => (
            <div key={a.id} data-testid={`announcement-${a.id}`} className="rounded-2xl border border-border bg-background p-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className={`rounded-lg border px-2 py-0.5 text-[10px] font-bold ${LABEL_TONE[a.label] || LABEL_TONE.INFORMASI}`}>{a.label}</span>
                <span className="text-[11px] text-muted-foreground">{new Date(a.created_at).toLocaleString("id-ID")}</span>
              </div>
              <p className="mt-2 text-sm font-bold">{a.title}</p>
              {(a.body || a.image_url) && <AnnouncementContent body={a.body} imageUrl={a.image_url} imageCaption={a.image_caption} className="mt-2" />}
            </div>
          ))}
          {ann.length === 0 && <p className="text-sm text-muted-foreground">—</p>}
        </div>
      </Card>
    </div>
  );
};
