import React, { useEffect, useMemo, useState } from "react";
import { Loader2, Search, ShoppingCart, AlertTriangle, RefreshCw, Wallet, CreditCard } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";

export const ServiceCatalog = ({ canBuy = false, onBought, onPayNow, balance = 0 }) => {
  const [countries, setCountries] = useState([]);
  const [countryId, setCountryId] = useState("");
  const [items, setItems] = useState([]);
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [buying, setBuying] = useState("");
  const [broken, setBroken] = useState({});
  const [tierPick, setTierPick] = useState({});
  const [confirm, setConfirm] = useState(null);
  

  useEffect(() => {
    http.get("/catalog/countries")
      .then(({ data }) => {
        setCountries(data.items);
        const pref = data.items.find((c) => c.name === "Indonesia") || data.items[0];
        if (pref) setCountryId(pref.id);
        else setLoading(false);
      })
      .catch((e) => { setError(errMsg(e)); setLoading(false); });
  }, []);

  const fetchServices = (cid, silent = false) => {
    if (!cid) return;
    if (!silent) setLoading(true);
    setError("");
    http.get("/catalog/services", { params: { country_id: cid } })
      .then(({ data }) => setItems(data.items))
      .catch((e) => setError(errMsg(e)))
      .finally(() => setLoading(false));
  };

  useEffect(() => { fetchServices(countryId); }, [countryId]); // eslint-disable-line

  useEffect(() => {
    if (!countryId) return;
    const iv = setInterval(() => fetchServices(countryId, true), 30000);
    return () => clearInterval(iv);
  }, [countryId]); // eslint-disable-line

  const filtered = useMemo(
    () => items.filter((i) => i.service_name.toLowerCase().includes(q.toLowerCase())),
    [items, q]
  );

  const countryName = countries.find((c) => c.id === countryId)?.name || "-";

  const buy = async (it, pid) => {
    setBuying(it.service_country_price_id);
    try {
      const { data } = await http.post("/orders", { service_country_price_id: pid, service_name: it.service_name, country_name: countryName });
      toast.success(`Nomor ${it.service_name} siap`);
      setConfirm(null);
      onBought?.(data);
    } catch (e) { toast.error(errMsg(e)); }
    setBuying("");
  };

  return (
    <div data-testid="service-catalog">
      <div className="flex flex-wrap items-center gap-3">
        <div className="relative min-w-[220px] flex-1">
          <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <input
            data-testid="catalog-search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Cari layanan (WhatsApp, Telegram...)"
            className="w-full rounded-2xl border border-input bg-background py-3 pl-11 pr-4 text-sm outline-none focus:border-primary"
          />
        </div>
        <select
          data-testid="catalog-country"
          value={countryId}
          onChange={(e) => setCountryId(e.target.value)}
          className="rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary"
        >
          {countries.length === 0 && <option value="">Negara belum tersedia</option>}
          {countries.map((c) => (<option key={c.id} value={c.id}>{c.name}</option>))}
        </select>
        <button
          data-testid="catalog-refresh"
          onClick={() => fetchServices(countryId)}
          className="flex items-center gap-2 rounded-2xl border border-border px-4 py-3 text-xs font-bold transition-colors hover:border-primary hover:text-primary"
        >
          <RefreshCw className="h-4 w-4" /> Refresh
        </button>
      </div>

      {error && (
        <div data-testid="catalog-error" className="mt-6 flex items-start gap-3 rounded-2xl border border-destructive/40 bg-destructive/10 p-5 text-sm">
          <AlertTriangle className="mt-0.5 h-4 w-4 text-destructive" />
          <div>
            <p className="font-bold text-destructive">Katalog tidak dapat dimuat</p>
            <p className="mt-1 text-muted-foreground">{error}</p>
          </div>
        </div>
      )}

      {loading ? (
        <div className="mt-10 flex justify-center"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {filtered.map((it, i) => {
            const picked = tierPick[it.service_country_price_id] || it.service_country_price_id;
            const tier = (it.tiers || []).find((t) => t.id === picked) || {};
            const shownPrice = tier.price ?? it.price;
            return (
              <article key={it.service_country_price_id + i} data-testid={`service-card-${i}`} className="hover-lift flex flex-col rounded-3xl border border-border bg-card p-5">
                <div className="flex items-center gap-3">
                  {it.logo && !broken[it.service_country_price_id] ? (
                    <img
                      src={it.logo}
                      alt={it.service_name}
                      onError={() => setBroken((b) => ({ ...b, [it.service_country_price_id]: true }))}
                      className="h-11 w-11 rounded-xl bg-muted object-contain p-1.5"
                    />
                  ) : (
                    <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary/15 font-bold text-primary">
                      {it.service_name.slice(0, 2).toUpperCase()}
                    </span>
                  )}
                  <div className="min-w-0">
                    <p className="truncate text-sm font-bold">{it.service_name}</p>
                    <p className="text-xs text-muted-foreground">
                      {countryName}
                    </p>
                  </div>
                </div>

                <div className="mt-4 flex items-end justify-between gap-2">
                  <p data-testid={`service-price-${i}`} className="text-2xl font-extrabold text-primary">{rupiah(shownPrice)}</p>
                </div>

                {(it.tiers || []).length > 1 && (
                  <label className="mt-3 block">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Server</span>
                    <select
                      data-testid={`tier-select-${i}`}
                      value={picked}
                      onChange={(e) => setTierPick({ ...tierPick, [it.service_country_price_id]: e.target.value })}
                      className="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-xs outline-none focus:border-primary"
                    >
                      {it.tiers.map((t, ti) => (
                        <option key={t.id} value={t.id}>{`Server ${ti + 1} — ${rupiah(t.price)}${ti === 0 ? " (termurah)" : ""}`}</option>
                      ))}
                    </select>
                  </label>
                )}

                {canBuy && (
                  <button
                    data-testid={`buy-service-${i}`}
                    onClick={() => setConfirm({ item: it, pid: picked, price: shownPrice })}
                    disabled={buying === it.service_country_price_id}
                    className="mt-4 flex items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-bold text-primary-foreground transition-transform duration-200 hover:scale-[1.02] disabled:opacity-60"
                  >
                    {buying === it.service_country_price_id ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShoppingCart className="h-4 w-4" />}
                    Beli nomor
                  </button>
                )}
              </article>
            );
          })}
          {filtered.length === 0 && !error && (
            <p data-testid="catalog-empty" className="text-sm text-muted-foreground">Tidak ada layanan yang cocok.</p>
          )}
        </div>
      )}

      {confirm && (
        <div className="fixed inset-0 z-[80] grid place-items-center p-4" data-testid="order-confirm">
          <div className="absolute inset-0 bg-background/80 backdrop-blur-sm" onClick={() => setConfirm(null)} />
          <div className="relative w-full max-w-sm rounded-3xl border border-border bg-card p-6">
            <div className="flex items-center gap-3">
              {confirm.item.logo ? (
                <img src={confirm.item.logo} alt="" className="h-11 w-11 rounded-xl bg-muted object-contain p-1.5" onError={(e) => { e.target.style.display = "none"; }} />
              ) : (
                <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary/15 font-bold text-primary">{confirm.item.service_name.slice(0, 2)}</span>
              )}
              <div className="min-w-0">
                <p className="truncate font-bold">{confirm.item.service_name}</p>
                <p className="text-xs text-muted-foreground">{countryName}</p>
              </div>
              <p className="ml-auto text-lg font-extrabold text-primary">{rupiah(confirm.price)}</p>
            </div>

            <div className="mt-5 space-y-2">
              <button
                data-testid="confirm-use-balance"
                onClick={() => buy(confirm.item, confirm.pid)}
                disabled={balance < confirm.price || buying}
                className="flex w-full items-center justify-between gap-3 rounded-2xl bg-primary px-4 py-3.5 text-sm font-bold text-primary-foreground disabled:opacity-50"
              >
                <span className="flex items-center gap-2"><Wallet className="h-4 w-4" /> Pakai saldo</span>
                <span>{rupiah(balance)}</span>
              </button>
              <button
                data-testid="confirm-pay-now"
                onClick={() => { onPayNow?.(confirm.item, confirm.price); setConfirm(null); }}
                className="flex w-full items-center justify-between gap-3 rounded-2xl border border-border px-4 py-3.5 text-sm font-bold hover:border-primary hover:text-primary"
              >
                <span className="flex items-center gap-2"><CreditCard className="h-4 w-4" /> Bayar sekarang</span>
                <span>Pembayaran otomatis</span>
              </button>
              {balance < confirm.price && (
                <p className="text-xs font-bold text-amber-500">Saldo kurang {rupiah(confirm.price - balance)} — pilih Bayar sekarang</p>
              )}
              <button data-testid="confirm-close" onClick={() => setConfirm(null)} className="w-full rounded-2xl px-4 py-2.5 text-xs font-bold text-muted-foreground hover:text-foreground">
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
