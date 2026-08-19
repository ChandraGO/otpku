import React, { useCallback, useEffect, useRef, useState } from "react";
import { Wallet, Copy, Ticket, LayoutDashboard, ShoppingCart, ListOrdered, KeyRound, LifeBuoy, ChevronLeft, ChevronRight, Menu, X, Radio } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";
import { copyText } from "@/lib/clipboard";
import { OrderCard } from "@/components/OrderCard";
import { Overview } from "@/components/Overview";
import { PaymentPanel, TopupForm } from "@/components/Payment";
import { useAuth } from "@/context/AuthContext";
import { ServiceCatalog } from "@/components/ServiceCatalog";

const TABS = [
  { key: "ringkasan", label: "Ringkasan", icon: LayoutDashboard },
  { key: "beli", label: "Beli Nomor", icon: ShoppingCart },
  { key: "pesanan", label: "Pesanan", icon: ListOrdered },
  { key: "saldo", label: "Isi Saldo", icon: Wallet },
  { key: "tiket", label: "Tiket", icon: LifeBuoy },
  { key: "api", label: "API Key", icon: KeyRound },
];

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

  const onPayNow = async (item, price) => {
    try {
      const { data } = await http.post("/topups", { amount: Number(price) });
      setPayment(data);
      setPayNote(`Bayar untuk ${item.service_name} · ${rupiah(price)}. Setelah lunas, saldo masuk otomatis dan pesanan bisa dibuat.`);
      go("saldo");
    } catch (e) { toast.error(errMsg(e)); }
  };

  const shownOrders = focusOrder && orders ? orders.filter((o) => o.id === focusOrder) : orders;

  const NavItems = ({ compact }) => (
    <nav className="space-y-1">
      {TABS.map(({ key, label, icon: Icon }) => (
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
          <NavItems compact={collapsed} />
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

          {tab === "beli" && <ServiceCatalog canBuy balance={user?.balance || 0} onBought={onBought} onPayNow={onPayNow} />}

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
              {payment && (
                <PaymentPanel
                  topup={payment}
                  note={payNote}
                  onClose={() => { setPayment(null); setPayNote(""); load(); }}
                  onChange={(d) => { setPayment(d); if (d.status === "paid") { toast.success("Saldo bertambah"); load(); } }}
                />
              )}
              <div className="grid gap-5 lg:grid-cols-2">
                <Card>
                  <p className="font-bold">Isi Saldo</p>
                  <div className="mt-4"><TopupForm limits={limits} onCreated={(d) => { setPayment(d); setPayNote(""); setTopups([d, ...topups]); }} /></div>
                </Card>
                <Card>
                  <p className="font-bold">Riwayat</p>
                  <div className="mt-4 space-y-2">
                    {topups.map((t) => (
                      <div key={t.id} data-testid={`topup-${t.id}`} className="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-muted/50 px-4 py-3 text-sm">
                        <span className="mono">{t.reference}</span>
                        <span className="font-bold">{rupiah(t.amount)}</span>
                        <Badge s={t.status} />
                        {t.status === "pending" && t.qris && (
                          <button data-testid={`topup-show-${t.id}`} onClick={() => { setPayment(t); setPayNote(""); topRef.current?.scrollIntoView({ behavior: "smooth" }); }} className="rounded-lg bg-primary px-2 py-1 text-xs font-bold text-primary-foreground">Bayar</button>
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
            <Card>
              <p className="font-bold">API Key</p>
              <div className="mt-4 flex flex-wrap items-center gap-3">
                <code data-testid="dash-apikey" className="mono min-w-0 flex-1 truncate rounded-xl bg-muted px-4 py-3 text-xs">{user?.api_key}</code>
                <button data-testid="dash-copy-apikey" onClick={() => copyText(user?.api_key)} className="flex items-center gap-2 rounded-xl border border-border px-4 py-3 text-xs font-bold hover:border-primary hover:text-primary"><Copy className="h-3.5 w-3.5" /> Copy</button>
                <button data-testid="dash-rotate-apikey" onClick={() => act(() => http.post("/auth/api-key/rotate"), "API key diperbarui")} className="rounded-xl bg-primary px-4 py-3 text-xs font-bold text-primary-foreground">Ganti</button>
              </div>
            </Card>
          )}
        </main>
      </div>

      <nav data-testid="dash-bottom-nav" className="fixed bottom-0 left-0 right-0 z-50 border-t border-border glass px-2 pb-2 pt-2 lg:hidden">
        <div className="flex items-center justify-between">
          {TABS.slice(0, 5).map(({ key, label, icon: Icon }) => (
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
