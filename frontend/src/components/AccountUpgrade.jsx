import React, { useEffect, useMemo, useState } from "react";
import { Check, Crown, Sparkles, Wallet } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";

const RANK = { member: 0, reseller: 1, vip: 2 };
const TONE = {
  member: "border-slate-500/30 bg-slate-500/5",
  reseller: "border-sky-500/35 bg-sky-500/5",
  vip: "border-amber-500/35 bg-amber-500/5",
};

export const AccountUpgrade = ({ summary, onGo, onUpgraded }) => {
  const [tiers, setTiers] = useState([]);
  const [busy, setBusy] = useState("");

  useEffect(() => {
    http.get("/public/tiers").then(({ data }) => setTiers(data.items || [])).catch(() => {});
  }, []);

  const current = summary?.tier || "member";
  const totalTopup = Number(summary?.topup_total || 0);
  const plans = useMemo(() => tiers.filter((x) => x.key !== "member"), [tiers]);

  const upgrade = async (plan) => {
    const required = Number(plan.min_topup || 0);
    if (totalTopup < required) {
      toast.info(`Kurang ${rupiah(required - totalTopup)} total deposit untuk ${plan.label}`);
      onGo?.("saldo");
      return;
    }
    setBusy(plan.key);
    try {
      await http.post("/me/tier/upgrade", { tier: plan.key });
      toast.success(`Akun berhasil di-upgrade ke ${plan.label}`);
      onUpgraded?.();
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setBusy("");
    }
  };

  return (
    <div data-testid="account-upgrade" className="space-y-5">
      <div className="rounded-3xl border border-primary/30 bg-primary/10 p-5 sm:p-6">
        <div className="flex flex-wrap items-center gap-3">
          <span className="grid h-11 w-11 place-items-center rounded-2xl bg-primary text-primary-foreground"><Crown className="h-5 w-5" /></span>
          <div>
            <h2 className="text-xl font-extrabold">Upgrade Akun</h2>
            <p className="mt-1 text-sm text-muted-foreground">Level saat ini: <b className="text-foreground">{summary?.tier_label || "Member"}</b> · total deposit {rupiah(totalTopup)}</p>
          </div>
        </div>
        <p className="mt-4 text-xs leading-5 text-muted-foreground">Upgrade memakai syarat <b>total deposit</b>, bukan memotong saldo sebagai biaya tambahan. Setelah syarat tercapai, level bisa diaktifkan dari sini.</p>
      </div>

      <div className="grid gap-5 xl:grid-cols-2">
        {plans.map((plan) => {
          const required = Number(plan.min_topup || 0);
          const active = RANK[current] >= RANK[plan.key];
          const eligible = totalTopup >= required;
          const benefits = String(plan.benefits || "").split("\n").map((x) => x.trim()).filter(Boolean);
          return (
            <div key={plan.key} className={`rounded-3xl border p-5 sm:p-6 ${TONE[plan.key] || "border-border bg-card"}`}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <p className="text-xs font-bold uppercase tracking-[.18em] text-muted-foreground">Paket</p>
                  <h3 className="mt-1 text-2xl font-extrabold">{plan.label}</h3>
                </div>
                <Sparkles className={`h-5 w-5 ${plan.key === "vip" ? "text-amber-500" : "text-sky-500"}`} />
              </div>

              <div className="mt-5 rounded-2xl border border-border/70 bg-background/60 p-4">
                <p className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Harga / syarat upgrade</p>
                <p className="mt-1 text-2xl font-extrabold text-primary">{rupiah(required)}</p>
                <p className="mt-1 text-xs text-muted-foreground">Total deposit kumulatif · markup level {Number(plan.markup_percent || 0)}%</p>
              </div>

              <div className="mt-5 space-y-2.5">
                {benefits.map((benefit) => (
                  <div key={benefit} className="flex gap-2.5 text-sm">
                    <span className="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-primary/15 text-primary"><Check className="h-3 w-3" /></span>
                    <span>{benefit}</span>
                  </div>
                ))}
              </div>

              <button
                type="button"
                disabled={active || busy === plan.key}
                onClick={() => upgrade(plan)}
                className={`mt-6 flex w-full items-center justify-center gap-2 rounded-2xl px-4 py-3 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${active ? "border border-border bg-muted text-muted-foreground" : eligible ? "bg-primary text-primary-foreground" : "border border-border bg-background hover:border-primary hover:text-primary"}`}
              >
                <Wallet className="h-4 w-4" />
                {active ? "Sudah aktif" : busy === plan.key ? "Memproses…" : eligible ? `Aktifkan ${plan.label}` : `Tambah deposit ${rupiah(Math.max(0, required - totalTopup))}`}
              </button>
            </div>
          );
        })}
      </div>
    </div>
  );
};
