import React, { useEffect, useMemo, useState } from "react";
import { Loader2, Save, Search, Trash2 } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-6 ${className}`}>{children}</div>
);

export const AdminServicePricing = () => {
  const [countries, setCountries] = useState([]);
  const [countryId, setCountryId] = useState("");
  const [items, setItems] = useState([]);
  const [overrides, setOverrides] = useState({});
  const [q, setQ] = useState("");
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState({});
  const [busy, setBusy] = useState("");

  const loadOverrides = () =>
    http.get("/admin/service-pricing").then(({ data }) => {
      const m = {};
      data.items.forEach((o) => { m[o.service_code] = o; });
      setOverrides(m);
    }).catch(() => {});

  useEffect(() => {
    loadOverrides();
    http.get("/catalog/countries").then(({ data }) => {
      setCountries(data.items);
      const pref = data.items.find((c) => c.name === "Indonesia") || data.items[0];
      if (pref) setCountryId(pref.id);
      else setLoading(false);
    }).catch(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!countryId) return;
    setLoading(true);
    http.get("/admin/catalog/services", { params: { country_id: countryId } })
      .then(({ data }) => setItems(data.items))
      .catch((e) => toast.error(errMsg(e)))
      .finally(() => setLoading(false));
  }, [countryId]);

  const filtered = useMemo(
    () => items.filter((i) => i.service_name.toLowerCase().includes(q.toLowerCase())).slice(0, 60),
    [items, q]
  );

  const save = async (it) => {
    const d = draft[it.service_code] || {};
    const ov = overrides[it.service_code];
    setBusy(it.service_code);
    try {
      const valueFor = (key) => {
        if (Object.prototype.hasOwnProperty.call(d, key)) {
          return d[key] === "" ? null : Number(d[key]);
        }
        return ov?.[key] ?? null;
      };
      await http.put(`/admin/service-pricing/${it.service_code}`, {
        service_name: it.service_name,
        markup_percent: valueFor("markup_percent"),
        fixed_fee: valueFor("fixed_fee"),
        rounding_to: valueFor("rounding_to"),
        min_profit: valueFor("min_profit"),
      });
      toast.success(`Markup ${it.service_name} disimpan`);
      await loadOverrides();
      const { data } = await http.get("/admin/catalog/services", { params: { country_id: countryId } });
      setItems(data.items);
    } catch (e) { toast.error(errMsg(e)); }
    setBusy("");
  };

  const remove = async (code, name) => {
    try {
      await http.delete(`/admin/service-pricing/${code}`);
      toast.success(`Markup khusus ${name} dihapus`);
      await loadOverrides();
      const { data } = await http.get("/admin/catalog/services", { params: { country_id: countryId } });
      setItems(data.items);
    } catch (e) { toast.error(errMsg(e)); }
  };

  return (
    <div data-testid="admin-service-pricing" className="space-y-5">
      <Card>
        <h2 className="text-2xl font-extrabold">Harga per Layanan</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          Harga provider diubah menjadi modal riil setelah biaya beli coin dan pajak. Sistem anti-rugi global tetap berlaku pada semua layanan. Kosongkan nilai override lalu Simpan untuk kembali memakai pengaturan global.
        </p>
        <div className="mt-5 flex flex-wrap items-center gap-3">
          <div className="relative min-w-[220px] flex-1">
            <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <input
              data-testid="svcprice-search"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder="Cari layanan"
              className="w-full rounded-2xl border border-input bg-background py-3 pl-11 pr-4 text-sm outline-none focus:border-primary"
            />
          </div>
          <select
            data-testid="svcprice-country"
            value={countryId}
            onChange={(e) => setCountryId(e.target.value)}
            className="rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary"
          >
            {countries.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </select>
        </div>
      </Card>

      {loading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
      ) : (
        <div className="space-y-3">
          {filtered.map((it, i) => {
            const ov = overrides[it.service_code];
            const d = draft[it.service_code] || {};
            return (
              <Card key={it.service_code} data-testid={`svcprice-row-${i}`} className="flex flex-wrap items-end gap-4 !p-5">
                <div className="flex min-w-[200px] flex-1 items-center gap-3">
                  {it.logo && <img src={it.logo} alt="" className="h-10 w-10 rounded-xl bg-muted object-contain p-1" onError={(e) => { e.target.style.display = "none"; }} />}
                  <div className="min-w-0">
                    <p className="truncate text-sm font-bold">{it.service_name}</p>
                    <p className="text-xs text-muted-foreground">
                      provider {Number(it.provider_price || 0).toLocaleString("id-ID")} unit
                      {it.provider_cost_after_tax != null && <> → modal <b>{rupiah(it.provider_cost_after_tax)}</b></>}
                      {" → "}jual <b className="text-primary">{rupiah(it.price)}</b>
                      {it.estimated_profit != null && <> · profit ±<b className="text-emerald-500">{rupiah(it.estimated_profit)}</b></>}
                      {ov ? " · override layanan" : " · harga global"}
                    </p>
                  </div>
                </div>
                <label className="block w-28">
                  <span className="text-[10px] font-bold uppercase text-muted-foreground">Markup %</span>
                  <input
                    data-testid={`svcprice-markup-${i}`}
                    type="number"
                    value={d.markup_percent ?? ov?.markup_percent ?? ""}
                    onChange={(e) => setDraft({ ...draft, [it.service_code]: { ...d, markup_percent: e.target.value } })}
                    className="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                </label>
                <label className="block w-28">
                  <span className="text-[10px] font-bold uppercase text-muted-foreground">Biaya tetap</span>
                  <input
                    data-testid={`svcprice-fee-${i}`}
                    type="number"
                    value={d.fixed_fee ?? ov?.fixed_fee ?? ""}
                    onChange={(e) => setDraft({ ...draft, [it.service_code]: { ...d, fixed_fee: e.target.value } })}
                    className="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                </label>
                <label className="block w-28">
                  <span className="text-[10px] font-bold uppercase text-muted-foreground">Pembulatan</span>
                  <input
                    data-testid={`svcprice-round-${i}`}
                    type="number"
                    value={d.rounding_to ?? ov?.rounding_to ?? ""}
                    onChange={(e) => setDraft({ ...draft, [it.service_code]: { ...d, rounding_to: e.target.value } })}
                    className="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                </label>
                <label className="block w-28">
                  <span className="text-[10px] font-bold uppercase text-muted-foreground">Min profit</span>
                  <input
                    data-testid={`svcprice-minprofit-${i}`}
                    type="number"
                    value={d.min_profit ?? ov?.min_profit ?? ""}
                    placeholder="global"
                    onChange={(e) => setDraft({ ...draft, [it.service_code]: { ...d, min_profit: e.target.value } })}
                    className="mt-1 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                </label>
                <button
                  data-testid={`svcprice-save-${i}`}
                  onClick={() => save(it)}
                  disabled={busy === it.service_code}
                  className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground disabled:opacity-60"
                >
                  {busy === it.service_code ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Simpan
                </button>
                {ov && (
                  <button
                    data-testid={`svcprice-delete-${i}`}
                    onClick={() => remove(it.service_code, it.service_name)}
                    className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-destructive hover:text-destructive"
                    title="Hapus markup khusus"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </Card>
            );
          })}
        </div>
      )}
    </div>
  );
};
