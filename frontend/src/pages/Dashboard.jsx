import React, { useCallback, useEffect, useRef, useState } from "react";
import { Wallet, Copy, Ticket, LayoutDashboard, ShoppingCart, ListOrdered, KeyRound, LifeBuoy, ChevronLeft, ChevronRight, Menu, X, Radio, Crown, BookOpen, CheckCircle2, Shuffle, MessageCircle } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";
import { copyText } from "@/lib/clipboard";
import { OrderCard } from "@/components/OrderCard";
import { Overview } from "@/components/Overview";
import { PaymentPanel, TopupForm } from "@/components/Payment";
import { useAuth } from "@/context/AuthContext";
import { ServiceCatalog } from "@/components/ServiceCatalog";
import { AccountUpgrade } from "@/components/AccountUpgrade";
import { BlogFeed } from "@/components/BlogFeed";
import { ContactLinks, getVisibleContacts } from "@/components/ContactLinks";
import { useSite } from "@/context/SiteContext";

const TABS = [
  { key: "ringkasan", label: "Ringkasan", icon: LayoutDashboard },
  { key: "beli", label: "Beli Nomor", icon: ShoppingCart },
  { key: "pesanan", label: "Pesanan", icon: ListOrdered },
  { key: "saldo", label: "Isi Saldo", icon: Wallet },
  { key: "upgrade", label: "Upgrade Akun", icon: Crown },
  { key: "blog", label: "Blog", icon: BookOpen },
  { key: "tiket", label: "Tiket", icon: LifeBuoy },
  { key: "api", label: "API Key", icon: KeyRound },
  { key: "contact", label: "Contact US", icon: MessageCircle },
];

const BOTTOM_KEYS = ["ringkasan", "beli", "pesanan", "saldo", "tiket"];

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-5 sm:p-6 ${className}`}>{children}</div>
);

const Badge = ({ s }) => {
  const map = {
    pending: "bg-amber-500/15 text-amber-500", paid: "bg-emerald-500/15 text-emerald-500",
    expired: "bg-destructive/15 text-destructive", cancelled: "bg-muted text-muted-foreground",
    open: "bg-amber-500/15 text-amber-500", answered: "bg-emerald-500/15 text-emerald-500", closed: "bg-muted text-muted-foreground",
  };
  return <span className={`rounded-lg px-2.5 py-1 text-[11px] font-bold uppercase ${map[s] || "bg-muted text-muted-foreground"}`}>{s}</span>;
};

const Skeleton = () => (
  <div className="space-y-3" data-testid="orders-skeleton">
    {[0, 1].map((i) => (
      <div key={i} className="rounded-3xl border border-border bg-card p-5">
        <div className="h-3 w-32 animate-pulse rounded-full bg-muted" />
        <div className="mt-4 flex flex-wrap gap-4">
          <div className="h-10 w-40 animate-pulse rounded-xl bg-muted" />
          <div className="h-10 w-24 animate-pulse rounded-xl bg-muted" />
          <div className="h-10 flex-1 animate-pulse rounded-xl bg-muted" />
        </div>
      </div>
    ))}
  </div>
);

export default function Dashboard() {
  const { user, setUser, refresh } = useAuth();
  const { contact } = useSite();
  const [tab, setTab] = useState("ringkasan");
  const [collapsed, setCollapsed] = useState(() => localStorage.getItem("dot_sidebar") === "1");
  const [drawer, setDrawer] = useState(false);
  const [orders, setOrders] = useState(null);
  const [topups, setTopups] = useState([]);
  const [tickets, setTickets] = useState([]);
  const [summary, setSummary] = useState(null);
  const [limits, setLimits] = useState({ min_amount: 10000, max_amount: 5000000 });
  const [tf, setTf] = useState({ subject: "", category: "umum", message: "" });
  const [replyFor, setReplyFor] = useState(null);
  const [replyMsg, setReplyMsg] = useState("");
  const [focusOrder, setFocusOrder] = useState(null);
  const [payment, setPayment] = useState(null);
  const [payNote, setPayNote] = useState("");
  const [synced, setSynced] = useState(null);
  const [apiCustom, setApiCustom] = useState("");
  const [apiBusy, setApiBusy] = useState(false);
  const [apiSuccess, setApiSuccess] = useState("");
  const topRef = useRef(null);

  const load = useCallback(async () => {
    const r = await Promise.allSettled([
      http.get("/orders"), http.get("/topups"), http.get("/tickets"), http.get("/me/summary"),
    ]);
    if (r[0].status === "fulfilled") setOrders(r[0].value.data.items);
    if (r[1].status === "fulfilled") setTopups(r[1].value.data.items);
    if (r[2].status === "fulfilled") setTickets(r[2].value.data.items);
    if (r[3].status === "fulfilled") setSummary(r[3].value.data);
    setSynced(new Date());
    refresh();
  }, [refresh]);

  useEffect(() => {
    load();
    http.get("/public/settings").then(({ data }) => setLimits(data.topup)).catch(() => {});
  }, []); // eslint-disable-line

  useEffect(() => { localStorage.setItem("dot_sidebar", collapsed ? "1" : "0"); }, [collapsed]);

  useEffect(() => {
    const iv = setInterval(() => {
      http.get("/orders").then(({ data }) => { setOrders(data.items); setSynced(new Date()); }).catch(() => {});
      http.get("/auth/me").then(({ data }) => setUser(data)).catch(() => {});
      http.get("/me/summary").then(({ data }) => setSummary(data)).catch(() => {});
      http.get("/topups").then(({ data }) => setTopups(data.items)).catch(() => {});
    }, 3000);
    return () => clearInterval(iv);
  }, [setUser]);

  const go = (key) => {
    setTab(key);
    setDrawer(false);
    topRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const act = async (fn, ok) => {
    try { await fn(); toast.success(ok); load(); } catch (e) { toast.error(errMsg(e)); }
  };

  const onBought = async (order) => {
    setFocusOrder(order?.id || null);
    await load();
    go("pesanan");
  };

  const onPayNow = async (item, priceId, price, countryId, countryName) => {
    try {
      const { data } = await http.post("/order-payments", {
        service_country_price_id: priceId,
        country_id: countryId,
        service_name: item.service_name,
        country_name: countryName,
      });
      setPayment({ ...data, payment_kind: "direct" });
      setPayNote(`Bayar langsung untuk ${item.service_name} · ${rupiah(data.amount || price)}. Pembayaran ini tidak mengisi saldo; setelah lunas pesanan dibuat otomatis.`);
      go("beli");
    } catch (e) { toast.error(errMsg(e)); }
  };

  const changeApiKey = async (customKey = null) => {
    setApiBusy(true);
    try {
      const { data } = await http.post("/auth/api-key", { custom_key: customKey || null });
      setUser((prev) => ({ ...(prev || {}), api_key: data.api_key }));
      setApiCustom("");
      setApiSuccess(data.api_key);
      toast.success("API key berhasil diperbarui");
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setApiBusy(false);
    }
  };

  const shownOrders = focusOrder && orders ? orders.filter((o) => o.id === focusOrder) : orders;
  const visibleContacts = getVisibleContacts(contact);

  const NavItems = ({ compact, includeContact = true }) => (
    <nav className="space-y-1">
      {TABS.filter((item) => includeContact || item.key !== "contact").map(({ key, label, icon: Icon }) => (
        <button
          key={key}
          data-testid={`dash-tab-${key}`}
          onClick={() => go(key)}
          title={label}
          className={`flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-sm font-semibold transition-colors duration-200 ${compact ? "justify-center" : ""} ${
            tab === key ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-accent hover:text-foreground"
          }`}
        >
          <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-xl ${tab === key ? "bg-primary-foreground/20" : "bg-muted"}`}>
            <Icon className="h-4 w-4" />
          </span>
          {!compact && <span className="truncate">{label}</span>}
        </button>
      ))}
    </nav>
  );

  const LiveChip = () => (
    <span data-testid="live-chip" className="flex items-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-[10px] font-bold text-emerald-500">
      <Radio className="h-3 w-3 animate-pulse" /> LIVE{synced ? ` ${synced.toLocaleTimeString("id-ID")}` : ""}
    </span>
  );

  return (
    <div data-testid="dashboard-page" className="mx-auto max-w-7xl px-4 pb-28 pt-4 sm:px-6 lg:pb-10 lg:pt-8">
      <span ref={topRef} />

      {/* MOBILE HEADER (satu bar saja) */}
      <div className="mb-4 flex items-center gap-3 rounded-3xl border border-border bg-card p-3 lg:hidden">
        <button data-testid="dash-drawer-toggle" onClick={() => setDrawer(true)} className="grid h-11 w-11 place-items-center rounded-2xl bg-accent">
          <Menu className="h-5 w-5" />
        </button>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-bold">{TABS.find((t) => t.key === tab)?.label}</p>
          <LiveChip />
        </div>
        <button onClick={() => go("saldo")} className="rounded-2xl bg-primary/10 px-3 py-2 text-right">
          <p className="text-[10px] font-bold uppercase text-muted-foreground">Saldo</p>
          <p data-testid="mobile-balance" className="text-sm font-extrabold text-primary">{rupiah(user?.balance)}</p>
        </button>
      </div>

      {drawer && (
        <div className="fixed inset-0 z-[70] lg:hidden" data-testid="dash-drawer">
          <div className="absolute inset-0 bg-background/80 backdrop-blur-sm" onClick={() => setDrawer(false)} />
          <div className="absolute left-0 top-0 h-full w-[80%] max-w-xs border-r border-border bg-card p-4">
            <div className="flex items-center justify-between">
              <div className="min-w-0">
                <p className="truncate font-extrabold">{user?.name}</p>
                <p className="truncate text-xs text-primary">{rupiah(user?.balance)}</p>
              </div>
              <button data-testid="dash-drawer-close" onClick={() => setDrawer(false)} className="grid h-10 w-10 place-items-center rounded-xl bg-accent"><X className="h-5 w-5" /></button>
            </div>
            <div className="mt-4"><NavItems /></div>
          </div>
        </div>
      )}

      <div className={`grid gap-6 ${collapsed ? "lg:grid-cols-[84px_1fr]" : "lg:grid-cols-[264px_1fr]"}`}>
        <aside data-testid="dash-sidebar" className="hidden h-fit rounded-3xl border border-border bg-card p-3 lg:sticky lg:top-24 lg:block">
          <div className={`mb-3 flex items-center gap-3 rounded-2xl bg-primary/10 p-3 ${collapsed ? "justify-center" : ""}`}>
            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-sm font-extrabold text-primary-foreground">
              {(user?.name || "U").slice(0, 1).toUpperCase()}
            </span>
            {!collapsed && (
              <div className="min-w-0">
                <p className="truncate text-sm font-bold">{user?.name}</p>
                <p data-testid="sidebar-balance" className="truncate text-xs font-bold text-primary">{rupiah(user?.balance)}</p>
              </div>
            )}
          </div>
          <NavItems compact={collapsed} includeContact={false} />
          {!collapsed && summary?.next_tier && (
            <button onClick={() => go("upgrade")} className="mt-3 w-full rounded-2xl border border-primary/30 bg-primary/10 p-3 text-left hover:border-primary">
              <div className="flex items-center gap-2 text-xs font-bold text-primary"><Crown className="h-4 w-4" /> Upgrade {summary.next_tier.toUpperCase()}</div>
              <p className="mt-1 text-[11px] leading-4 text-muted-foreground">Syarat total deposit {rupiah(summary.next_tier_min_topup)}</p>
            </button>
          )}
          <button
            data-testid="dash-sidebar-contact"
            onClick={() => go("contact")}
            title="Contact US"
            className={`mt-3 flex w-full items-center gap-3 rounded-2xl border px-3 py-3 text-sm font-semibold transition-colors ${collapsed ? "justify-center" : ""} ${tab === "contact" ? "border-primary bg-primary text-primary-foreground" : "border-border text-muted-foreground hover:border-primary hover:text-primary"}`}
          >
            <span className={`grid h-8 w-8 shrink-0 place-items-center rounded-xl ${tab === "contact" ? "bg-primary-foreground/20" : "bg-muted"}`}><MessageCircle className="h-4 w-4" /></span>
            {!collapsed && <span>Contact US</span>}
          </button>
          <button
            data-testid="dash-sidebar-toggle"
            onClick={() => setCollapsed((c) => !c)}
            className="mt-3 flex w-full items-center justify-center gap-2 rounded-2xl border border-border px-3 py-3 text-xs font-bold text-muted-foreground transition-colors hover:border-primary hover:text-primary"
          >
            {collapsed ? <ChevronRight className="h-4 w-4" /> : <><ChevronLeft className="h-4 w-4" /> Hide</>}
          </button>
        </aside>

        <main className="min-w-0">
          <div className="mb-5 hidden items-center gap-3 lg:flex">
            <h1 className="text-2xl font-extrabold">{TABS.find((t) => t.key === tab)?.label}</h1>
            <LiveChip />
            <div className="ml-auto flex items-center gap-3 rounded-2xl border border-border bg-card px-4 py-2.5" data-testid="balance-card">
              <Wallet className="h-4 w-4 text-primary" />
              <p data-testid="balance-value" className="text-lg font-extrabold">{rupiah(user?.balance)}</p>
            </div>
          </div>

          {tab === "ringkasan" && <Overview summary={summary} onGo={go} />}

          {tab === "beli" && (
            <div className="space-y-5">
              {payment?.payment_kind === "direct" && (
                <PaymentPanel
                  topup={payment}
                  mode="direct"
                  note={payNote}
                  onClose={() => { setPayment(null); setPayNote(""); }}
                  onChange={(d) => {
                    const next = { ...d, payment_kind: "direct" };
                    setPayment(next);
                    if (d.order_id) {
                      toast.success("Pembayaran berhasil, pesanan dibuat otomatis");
                      setPayment(null);
                      setPayNote("");
                      setFocusOrder(d.order_id);
                      load();
                      go("pesanan");
                    }
                  }}
                />
              )}
              <ServiceCatalog canBuy balance={user?.balance || 0} onBought={onBought} onPayNow={onPayNow} />
            </div>
          )}

          {tab === "pesanan" && (
            <div className="space-y-4">
              {focusOrder && (
                <button data-testid="orders-show-all" onClick={() => setFocusOrder(null)} className="rounded-2xl border border-border px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary">
                  ← Semua pesanan
                </button>
              )}
              {orders === null ? <Skeleton /> : (
                <>
                  {(shownOrders || []).map((o) => (
                    <OrderCard key={o.id} order={o} onChange={load} highlight={focusOrder === o.id} />
                  ))}
                  {(shownOrders || []).length === 0 && <p data-testid="orders-empty" className="text-sm text-muted-foreground">—</p>}
                </>
              )}
            </div>
          )}

          {tab === "saldo" && (
            <div className="space-y-5">
              {payment && payment?.payment_kind !== "direct" && (
                <PaymentPanel
                  topup={payment}
                  note={payNote}
                  onClose={() => { setPayment(null); setPayNote(""); load(); }}
                  onChange={(d) => {
                    const next = { ...d, payment_kind: "topup" };
                    setPayment(next);
                    if (d.status === "paid") { toast.success("Saldo bertambah"); load(); }
                  }}
                />
              )}
              <div className="grid gap-5 lg:grid-cols-2">
                <Card>
                  <p className="font-bold">Isi Saldo</p>
                  <div className="mt-4"><TopupForm limits={limits} onCreated={(d) => { setPayment({ ...d, payment_kind: "topup" }); setPayNote(""); setTopups([d, ...topups]); }} /></div>
                </Card>
                <Card>
                  <p className="font-bold">Riwayat</p>
                  <div className="mt-4 space-y-2">
                    {topups.map((t) => (
                      <div key={t.id} data-testid={`topup-${t.id}`} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-muted/50 px-4 py-3 text-sm">
                        <span className="mono">{t.reference}</span>
                        <span className="font-bold">{rupiah(t.amount)}</span>
                        <Badge s={t.status} />
                        {t.status === "pending" && (t.payment_code || t.qris) && (
                          <button data-testid={`topup-show-${t.id}`} onClick={() => { setPayment({ ...t, payment_kind: "topup" }); setPayNote(""); topRef.current?.scrollIntoView({ behavior: "smooth" }); }} className="rounded-lg bg-primary px-2 py-1 text-xs font-bold text-primary-foreground">Bayar</button>
                        )}
                        <button data-testid={`topup-check-${t.id}`} onClick={() => act(() => http.get(`/topups/${t.id}`), "Diperbarui")} className="rounded-lg border border-border px-2 py-1 text-xs font-bold hover:border-primary hover:text-primary">Cek</button>
                      </div>
                    ))}
                    {topups.length === 0 && <p className="text-sm text-muted-foreground">—</p>}
                  </div>
                </Card>
              </div>
            </div>
          )}

          {tab === "upgrade" && <AccountUpgrade summary={summary} onGo={go} onUpgraded={load} />}

          {tab === "blog" && <BlogFeed />}

          {tab === "contact" && (
            <Card>
              <div className="flex items-center gap-3">
                <span className="grid h-11 w-11 place-items-center rounded-2xl bg-primary/15 text-primary"><MessageCircle className="h-5 w-5" /></span>
                <div>
                  <p className="text-xl font-extrabold">Contact US</p>
                  <p className="text-sm text-muted-foreground">Hubungi kami melalui kontak resmi yang tersedia.</p>
                </div>
              </div>
              <ContactLinks contact={contact} className="mt-6" />
              {visibleContacts.length === 0 && <p className="mt-5 text-sm text-muted-foreground">Kontak belum diatur oleh admin.</p>}
            </Card>
          )}

          {tab === "tiket" && (
            <div className="grid gap-5 lg:grid-cols-2">
              <Card>
                <p className="font-bold">Tiket baru</p>
                <div className="mt-4 space-y-3">
                  <input data-testid="ticket-subject" placeholder="Judul" value={tf.subject} onChange={(e) => setTf({ ...tf, subject: e.target.value })} className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
                  <select data-testid="ticket-category" value={tf.category} onChange={(e) => setTf({ ...tf, category: e.target.value })} className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary">
                    {["umum", "pesanan", "saldo", "api", "lainnya"].map((c) => <option key={c} value={c}>{c}</option>)}
                  </select>
                  <textarea data-testid="ticket-message" rows={5} placeholder="Pesan" value={tf.message} onChange={(e) => setTf({ ...tf, message: e.target.value })} className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
                  <button
                    data-testid="ticket-submit"
                    onClick={async () => {
                      if (!tf.subject || !tf.message) return toast.error("Judul dan pesan wajib diisi");
                      await act(() => http.post("/tickets", tf), "Tiket terkirim");
                      setTf({ subject: "", category: "umum", message: "" });
                    }}
                    className="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground"
                  >
                    <Ticket className="h-4 w-4" /> Kirim
                  </button>
                </div>
              </Card>
              <Card>
                <p className="font-bold">Tiket saya</p>
                <div className="mt-4 space-y-3">
                  {tickets.map((t) => (
                    <div key={t.id} data-testid={`ticket-${t.id}`} className="rounded-2xl border border-border p-4">
                      <div className="flex items-center justify-between gap-2">
                        <p className="font-bold">{t.subject}</p>
                        <Badge s={t.status} />
                      </div>
                      <p className="mt-2 text-sm text-muted-foreground">{t.message}</p>
                      {t.replies?.map((r, i) => (
                        <p key={i} className="mt-2 rounded-xl bg-muted/60 px-3 py-2 text-sm"><b className="text-primary">{r.by === "admin" ? "Admin" : "Kamu"}:</b> {r.message}</p>
                      ))}
                      {replyFor === t.id ? (
                        <div className="mt-3 flex gap-2">
                          <input data-testid={`ticket-reply-input-${t.id}`} value={replyMsg} onChange={(e) => setReplyMsg(e.target.value)} className="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary" />
                          <button data-testid={`ticket-reply-send-${t.id}`} onClick={async () => { await act(() => http.post(`/tickets/${t.id}/reply`, { message: replyMsg }), "Balasan terkirim"); setReplyMsg(""); setReplyFor(null); }} className="rounded-xl bg-primary px-4 text-sm font-bold text-primary-foreground">Kirim</button>
                        </div>
                      ) : (
                        <button data-testid={`ticket-reply-${t.id}`} onClick={() => setReplyFor(t.id)} className="mt-3 text-sm font-bold text-primary">Balas</button>
                      )}
                    </div>
                  ))}
                  {tickets.length === 0 && <p className="text-sm text-muted-foreground">—</p>}
                </div>
              </Card>
            </div>
          )}

          {tab === "api" && (
            <div className="space-y-5">
              <Card>
                <p className="font-bold">API Key</p>
                <p className="mt-1 text-xs text-muted-foreground">Key aktif untuk header <code className="mono">x-api-key</code>.</p>
                <div className="mt-4 flex flex-wrap items-center gap-3">
                  <code data-testid="dash-apikey" className="mono min-w-0 flex-1 truncate rounded-xl bg-muted px-4 py-3 text-xs">{user?.api_key}</code>
                  <button data-testid="dash-copy-apikey" onClick={() => copyText(user?.api_key, "API key disalin")} className="flex items-center gap-2 rounded-xl border border-border px-4 py-3 text-xs font-bold hover:border-primary hover:text-primary"><Copy className="h-3.5 w-3.5" /> Copy</button>
                  <button data-testid="dash-rotate-apikey" disabled={apiBusy} onClick={() => changeApiKey(null)} className="flex items-center gap-2 rounded-xl bg-primary px-4 py-3 text-xs font-bold text-primary-foreground disabled:opacity-60"><Shuffle className="h-3.5 w-3.5" /> Buat Random</button>
                </div>
              </Card>

              <Card>
                <p className="font-bold">Custom API Key</p>
                <p className="mt-1 text-xs leading-5 text-muted-foreground">Boleh membuat key sendiri. Prefix <code className="mono">dot_</code> akan ditambahkan otomatis bila belum ditulis. Gunakan 12–96 karakter; huruf, angka, underscore, atau strip.</p>
                <div className="mt-4 flex flex-col gap-3 sm:flex-row">
                  <input data-testid="dash-custom-apikey" value={apiCustom} onChange={(e) => setApiCustom(e.target.value)} placeholder="contoh: toko_saya_2026" className="mono min-w-0 flex-1 rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
                  <button data-testid="dash-save-custom-apikey" disabled={apiBusy || !apiCustom.trim()} onClick={() => changeApiKey(apiCustom.trim())} className="rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-50">Simpan Custom Key</button>
                </div>
              </Card>
            </div>
          )}

          {apiSuccess && (
            <div className="fixed inset-0 z-[90] grid place-items-center bg-background/80 p-4 backdrop-blur-sm" data-testid="api-success-popup">
              <div className="w-full max-w-md rounded-3xl border border-primary/30 bg-card p-6 shadow-2xl">
                <span className="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-500/15 text-emerald-500"><CheckCircle2 className="h-6 w-6" /></span>
                <h3 className="mt-4 text-2xl font-extrabold">API Key berhasil diganti</h3>
                <p className="mt-2 text-sm text-muted-foreground">Simpan key baru ini. Key sebelumnya langsung tidak berlaku.</p>
                <code className="mono mt-4 block break-all rounded-2xl bg-muted p-4 text-xs">{apiSuccess}</code>
                <div className="mt-5 flex gap-2">
                  <button onClick={() => copyText(apiSuccess, "API key disalin")} className="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-border px-4 py-3 text-sm font-bold hover:border-primary hover:text-primary"><Copy className="h-4 w-4" /> Copy</button>
                  <button onClick={() => setApiSuccess("")} className="flex-1 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-primary-foreground">Selesai</button>
                </div>
              </div>
            </div>
          )}
        </main>
      </div>

      <nav data-testid="dash-bottom-nav" className="fixed bottom-0 left-0 right-0 z-50 border-t border-border glass px-2 pb-2 pt-2 lg:hidden">
        <div className="flex items-center justify-between">
          {TABS.filter((t) => BOTTOM_KEYS.includes(t.key)).map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              data-testid={`bottom-nav-${key}`}
              onClick={() => go(key)}
              className={`flex flex-1 flex-col items-center gap-1 rounded-2xl py-2 text-[10px] font-bold transition-colors ${tab === key ? "text-primary" : "text-muted-foreground"}`}
            >
              <span className={`grid h-9 w-9 place-items-center rounded-xl ${tab === key ? "bg-primary/15" : ""}`}>
                <Icon className="h-4 w-4" />
              </span>
              {label}
            </button>
          ))}
        </div>
      </nav>
    </div>
  );
}
