import React, { useCallback, useEffect, useState } from "react";
import { Loader2, Users, ShoppingBag, Wallet, LifeBuoy, TrendingUp, AlertTriangle } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";
import { AdminSettings, CATEGORIES } from "@/components/AdminSettings";
import { AdminReport } from "@/components/AdminReport";
import { AdminServicePricing } from "@/components/AdminServicePricing";
import { AdminAnnouncements } from "@/components/AdminAnnouncements";

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-6 ${className}`}>{children}</div>
);

const SECTIONS = [
  { key: "overview", label: "Ringkasan" },
  { key: "report", label: "Laporan Untung Rugi" },
  { key: "svcprice", label: "Harga per Layanan" },
  { key: "announcements", label: "Pengumuman" },
  { key: "users", label: "Pengguna" },
  { key: "orders", label: "Pesanan" },
  { key: "topups", label: "Isi Saldo" },
  { key: "tickets", label: "Tiket" },
  ...CATEGORIES.map((c) => ({ key: `set:${c.key}`, label: c.label, group: "Pengaturan" })),
];

export default function Admin() {
  const [section, setSection] = useState("overview");
  const [stats, setStats] = useState(null);
  const [settings, setSettings] = useState(null);
  const [users, setUsers] = useState([]);
  const [orders, setOrders] = useState([]);
  const [topups, setTopups] = useState([]);
  const [tickets, setTickets] = useState([]);
  const [reply, setReply] = useState({});

  const load = useCallback(async () => {
    const r = await Promise.allSettled([
      http.get("/admin/stats"), http.get("/admin/settings"), http.get("/admin/users"),
      http.get("/admin/orders"), http.get("/admin/topups"), http.get("/admin/tickets"),
    ]);
    if (r[0].status === "fulfilled") setStats(r[0].value.data);
    if (r[1].status === "fulfilled") setSettings(r[1].value.data);
    if (r[2].status === "fulfilled") setUsers(r[2].value.data.items);
    if (r[3].status === "fulfilled") setOrders(r[3].value.data.items);
    if (r[4].status === "fulfilled") setTopups(r[4].value.data.items);
    if (r[5].status === "fulfilled") setTickets(r[5].value.data.items);
  }, []);

  useEffect(() => { load(); }, [load]);

  useEffect(() => {
    const iv = setInterval(() => {
      http.get("/admin/stats").then(({ data }) => setStats(data)).catch(() => {});
      http.get("/admin/users").then(({ data }) => setUsers(data.items)).catch(() => {});
      http.get("/admin/orders").then(({ data }) => setOrders(data.items)).catch(() => {});
      http.get("/admin/topups").then(({ data }) => setTopups(data.items)).catch(() => {});
      http.get("/admin/tickets").then(({ data }) => setTickets(data.items)).catch(() => {});
    }, 5000);
    return () => clearInterval(iv);
  }, []);

  const act = async (fn, ok) => {
    try { await fn(); toast.success(ok); load(); } catch (e) { toast.error(errMsg(e)); }
  };

  if (!settings) return <div className="flex justify-center py-24"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>;

  return (
    <div data-testid="admin-page" className="mx-auto max-w-7xl px-6 py-10">
      <h1 className="text-3xl font-extrabold sm:text-4xl">Admin dapetOTP</h1>
      <p className="mt-2 text-sm text-muted-foreground">Pilih kategori pengaturan atau kelola data operasional.</p>

      <div className="mt-8 grid gap-6 lg:grid-cols-[280px_1fr]">
        <aside className="h-fit rounded-3xl border border-border bg-card p-3 lg:sticky lg:top-24">
          <nav className="max-h-[70vh] space-y-1 overflow-y-auto">
            {SECTIONS.map((s, i) => (
              <React.Fragment key={s.key}>
                {s.group && SECTIONS[i - 1] && !SECTIONS[i - 1].group && (
                  <p className="px-3 pb-1 pt-4 text-[11px] font-bold uppercase tracking-widest text-muted-foreground">Pengaturan</p>
                )}
                <button
                  data-testid={`admin-nav-${s.key}`}
                  onClick={() => setSection(s.key)}
                  className={`w-full rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors duration-200 ${
                    section === s.key ? "bg-primary text-primary-foreground" : "text-muted-foreground hover:bg-accent hover:text-foreground"
                  }`}
                >{s.label}</button>
              </React.Fragment>
            ))}
          </nav>
        </aside>

        <main className="min-w-0">
          {section.startsWith("set:") && (
            <Card>
              <AdminSettings
                category={section.slice(4)}
                values={settings[section.slice(4)]}
                onSaved={(cat, data) => setSettings({ ...settings, [cat]: data })}
              />
            </Card>
          )}

          {section === "report" && <AdminReport />}
          {section === "svcprice" && <AdminServicePricing />}
          {section === "announcements" && <AdminAnnouncements />}

          {section === "overview" && stats && (
            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {[
                [Users, "Pengguna", stats.users], [ShoppingBag, "Pesanan", stats.orders],
                [TrendingUp, "OTP sukses", stats.success_orders], [Wallet, "Total isi saldo", rupiah(stats.topup_total)],
                [TrendingUp, "Penjualan", rupiah(stats.sales_total)], [TrendingUp, "Estimasi profit", rupiah(stats.profit)],
                [LifeBuoy, "Tiket terbuka", stats.open_tickets],
              ].map(([Icon, l, v], i) => (
                <Card key={l} data-testid={`admin-stat-${i}`} className="hover-lift">
                  <span className="grid h-11 w-11 place-items-center rounded-2xl bg-primary/15 text-primary"><Icon className="h-5 w-5" /></span>
                  <p className="mt-4 text-xs font-bold uppercase tracking-wider text-muted-foreground">{l}</p>
                  <p className="mt-1 text-3xl font-extrabold">{v}</p>
                </Card>
              ))}
              <Card data-testid="admin-provider-balance" className="sm:col-span-2 lg:col-span-3">
                <p className="font-bold">Saldo provider nomor</p>
                {stats.provider_balance === null ? (
                  <p className="mt-2 flex items-center gap-2 text-sm text-muted-foreground"><AlertTriangle className="h-4 w-4 text-amber-500" /> Tidak terhubung — isi API key di pengaturan Provider Nomor.</p>
                ) : (
                  <p className={`mt-2 text-3xl font-extrabold ${Number(stats.provider_balance) < Number(stats.low_balance_threshold) ? "text-destructive" : "text-emerald-500"}`}>
                    {Number(stats.provider_balance).toLocaleString("id-ID")}
                  </p>
                )}
                <div className="mt-6 space-y-2">
                  <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Report harian (30 hari)</p>
                  {stats.daily.length === 0 && <p className="text-sm text-muted-foreground">Belum ada data.</p>}
                  {stats.daily.map((d) => (
                    <div key={d.date} className="flex items-center justify-between rounded-xl bg-muted/50 px-4 py-2.5 text-sm">
                      <span className="mono">{d.date}</span>
                      <span>{d.orders} pesanan</span>
                      <span className="font-bold text-primary">{rupiah(d.revenue)}</span>
                    </div>
                  ))}
                </div>
              </Card>
            </div>
          )}

          {section === "users" && (
            <div className="space-y-3">
              {users.map((u) => (
                <Card key={u.id} data-testid={`admin-user-${u.id}`} className="flex flex-wrap items-center gap-4">
                  <div className="min-w-[200px] flex-1">
                    <p className="font-bold">{u.name} {u.role === "admin" && <span className="ml-1 rounded-md bg-primary/15 px-2 py-0.5 text-[11px] font-bold text-primary">ADMIN</span>}</p>
                    <p className="text-sm text-muted-foreground">{u.email}</p>
                  </div>
                  <span className="font-bold">{rupiah(u.balance)}</span>
                  <select
                    data-testid={`admin-user-tier-${u.id}`}
                    value={u.tier || "member"}
                    onChange={(e) => act(() => http.post(`/admin/users/${u.id}/tier`, { tier: e.target.value }), "Level diperbarui")}
                    className="rounded-xl border border-input bg-background px-3 py-2 text-xs font-bold outline-none focus:border-primary"
                  >
                    {["member", "reseller", "vip"].map((t) => <option key={t} value={t}>{t.toUpperCase()}</option>)}
                  </select>
                  <button data-testid={`admin-user-add-${u.id}`} onClick={() => {
                    const v = window.prompt("Tambah/kurangi saldo (Rp, boleh minus)");
                    if (v) act(() => http.post(`/admin/users/${u.id}/balance`, { amount: Number(v) }), "Saldo diperbarui");
                  }} className="rounded-xl border border-border px-4 py-2 text-xs font-bold hover:border-primary hover:text-primary">Atur saldo</button>
                  <button data-testid={`admin-user-toggle-${u.id}`} onClick={() => act(() => http.post(`/admin/users/${u.id}/toggle`), "Status diperbarui")} className="rounded-xl border border-border px-4 py-2 text-xs font-bold hover:border-destructive hover:text-destructive">
                    {u.suspended ? "Aktifkan" : "Suspend"}
                  </button>
                </Card>
              ))}
            </div>
          )}

          {section === "orders" && (
            <div className="space-y-3">
              {orders.map((o) => (
                <Card key={o.id} data-testid={`admin-order-${o.id}`} className="flex flex-wrap items-center gap-4 text-sm">
                  <span className="mono flex-1">{o.phone_number || "-"}</span>
                  <span className="flex-1 font-bold">{o.service_name}</span>
                  <span>{o.country_name}</span>
                  <span className="mono text-primary">{(o.otp_codes || []).join(", ") || "-"}</span>
                  <span className="font-bold">{rupiah(o.price)}</span>
                  <span className="rounded-lg bg-muted px-2 py-1 text-[11px] font-bold uppercase">{o.status}</span>
                </Card>
              ))}
              {orders.length === 0 && <p className="text-sm text-muted-foreground">Belum ada pesanan.</p>}
            </div>
          )}

          {section === "topups" && (
            <div className="space-y-3">
              {topups.map((t) => (
                <Card key={t.id} data-testid={`admin-topup-${t.id}`} className="flex flex-wrap items-center gap-4 text-sm">
                  <span className="mono flex-1">{t.reference}</span>
                  <span className="font-bold">{rupiah(t.amount)}</span>
                  <span className="rounded-lg bg-muted px-2 py-1 text-[11px] font-bold uppercase">{t.status}</span>
                </Card>
              ))}
              {topups.length === 0 && <p className="text-sm text-muted-foreground">Belum ada isi saldo.</p>}
            </div>
          )}

          {section === "tickets" && (
            <div className="space-y-3">
              {tickets.map((t) => (
                <Card key={t.id} data-testid={`admin-ticket-${t.id}`}>
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-bold">{t.subject}</p>
                    <span className="rounded-lg bg-muted px-2 py-1 text-[11px] font-bold uppercase">{t.status}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">{t.user_email} · {t.category}</p>
                  <p className="mt-3 text-sm">{t.message}</p>
                  {t.replies?.map((r, i) => (
                    <p key={i} className="mt-2 rounded-xl bg-muted/60 px-3 py-2 text-sm"><b className="text-primary">{r.by === "admin" ? "Admin" : r.name}:</b> {r.message}</p>
                  ))}
                  <div className="mt-4 flex flex-wrap gap-2">
                    <input data-testid={`admin-ticket-reply-input-${t.id}`} value={reply[t.id] || ""} onChange={(e) => setReply({ ...reply, [t.id]: e.target.value })} placeholder="Balasan admin" className="flex-1 rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary" />
                    <button data-testid={`admin-ticket-reply-${t.id}`} onClick={() => act(() => http.post(`/tickets/${t.id}/reply`, { message: reply[t.id] }), "Balasan terkirim")} className="rounded-xl bg-primary px-4 py-2 text-sm font-bold text-primary-foreground">Balas</button>
                    <button data-testid={`admin-ticket-close-${t.id}`} onClick={() => act(() => http.post(`/admin/tickets/${t.id}/close`), "Tiket ditutup")} className="rounded-xl border border-border px-4 py-2 text-sm font-bold hover:border-destructive hover:text-destructive">Tutup</button>
                  </div>
                </Card>
              ))}
              {tickets.length === 0 && <p className="text-sm text-muted-foreground">Belum ada tiket.</p>}
            </div>
          )}
        </main>
      </div>
    </div>
  );
}
