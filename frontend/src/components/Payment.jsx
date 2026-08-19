import React, { useEffect, useState } from "react";
import { QRCodeCanvas } from "qrcode.react";
import { Loader2, Plus, Clock, CheckCircle2, XCircle, AlertTriangle, Copy } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";
import { copyText } from "@/lib/clipboard";

const digits = (v) => String(v ?? "").replace(/\D/g, "");
export const formatRupiahInput = (v) => {
  const d = digits(v);
  return d ? Number(d).toLocaleString("id-ID") : "";
};

const STATE = {
  pending: { tone: "bg-amber-500/15 text-amber-500 border-amber-500/40", icon: Clock, text: "Menunggu pembayaran" },
  paid: { tone: "bg-emerald-500/15 text-emerald-500 border-emerald-500/40", icon: CheckCircle2, text: "Pembayaran berhasil" },
  expired: { tone: "bg-destructive/15 text-destructive border-destructive/40", icon: AlertTriangle, text: "Kedaluwarsa" },
  cancelled: { tone: "bg-muted text-muted-foreground border-border", icon: XCircle, text: "Dibatalkan" },
};

const mmss = (s) => `${String(Math.max(0, Math.floor(s / 60))).padStart(2, "0")}:${String(Math.max(0, Math.floor(s % 60))).padStart(2, "0")}`;

export const PaymentPanel = ({ topup, onClose, onChange, note }) => {
  const [tick, setTick] = useState(Date.now());
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    const iv = setInterval(() => setTick(Date.now()), 1000);
    return () => clearInterval(iv);
  }, []);

  useEffect(() => {
    const iv = setInterval(() => {
      http.get(`/topups/${topup.id}`).then(({ data }) => onChange?.(data)).catch(() => {});
    }, 4000);
    return () => clearInterval(iv);
  }, [topup.id]); // eslint-disable-line

  const left = topup.expires_at ? (new Date(topup.expires_at).getTime() - tick) / 1000 : 0;
  const st = STATE[topup.status] || STATE.pending;
  const amount = topup.pay_amount || topup.amount;

  const cancel = async () => {
    setBusy(true);
    try {
      await http.post(`/topups/${topup.id}/cancel`);
      toast.success("Pembayaran dibatalkan");
      onClose?.();
    } catch (e) { toast.error(errMsg(e)); }
    setBusy(false);
  };

  return (
    <div data-testid="topup-qris" className="rise rounded-3xl border border-primary/40 bg-card p-5">
      <div className="flex flex-wrap items-center gap-3">
        <span className={`flex items-center gap-2 rounded-xl border px-3 py-1.5 text-[11px] font-bold uppercase ${st.tone}`} data-testid="payment-status">
          <st.icon className="h-3.5 w-3.5" /> {st.text}
        </span>
        {topup.status === "pending" && (
          <span data-testid="payment-timer" className={`ml-auto flex items-center gap-2 rounded-xl border border-border px-3 py-1.5 text-sm font-extrabold ${left < 120 ? "text-destructive" : "text-foreground"}`}>
            <Clock className="h-4 w-4" /> {mmss(left)}
          </span>
        )}
      </div>

      {note && <p className="mt-4 text-sm font-semibold">{note}</p>}

      {topup.status === "pending" && topup.qris ? (
        <div className="mt-4 text-center">
          <p data-testid="payment-amount" className="text-3xl font-extrabold text-primary">{rupiah(amount)}</p>
          <p className="mt-1 text-xs font-bold text-amber-500">Bayar tepat sesuai nominal di atas</p>
          <div className="mt-4 inline-block rounded-2xl bg-white p-3">
            <QRCodeCanvas value={topup.qris} size={200} includeMargin={false} />
          </div>
          <p className="mono mt-3 text-xs text-muted-foreground">{topup.reference}</p>
          <div className="mt-4 flex flex-wrap justify-center gap-2">
            <button data-testid="topup-copy-qris" onClick={() => copyText(topup.qris, "Kode QRIS dikopi")} className="flex items-center gap-2 rounded-xl border border-border px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary">
              <Copy className="h-3.5 w-3.5" /> Copy kode
            </button>
            <button data-testid="topup-cancel" onClick={cancel} disabled={busy} className="flex items-center gap-2 rounded-xl border border-destructive/60 px-4 py-2.5 text-xs font-bold text-destructive hover:bg-destructive hover:text-destructive-foreground disabled:opacity-60">
              {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <XCircle className="h-3.5 w-3.5" />} Batalkan
            </button>
            <button data-testid="topup-close-qris" onClick={onClose} className="rounded-xl border border-border px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary">Tutup</button>
          </div>
        </div>
      ) : (
        <div className="mt-4 text-center">
          <p className="text-2xl font-extrabold">{rupiah(amount)}</p>
          <button data-testid="topup-close-qris" onClick={onClose} className="mt-4 rounded-xl border border-border px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary">Tutup</button>
        </div>
      )}
    </div>
  );
};

export const TopupForm = ({ limits, onCreated }) => {
  const [raw, setRaw] = useState("50000");
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      const { data } = await http.post("/topups", { amount: Number(digits(raw) || 0) });
      onCreated?.(data);
      toast.success("Kode pembayaran siap");
    } catch (e) { toast.error(errMsg(e)); }
    setBusy(false);
  };

  return (
    <div>
      <div className="relative">
        <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">Rp</span>
        <input
          data-testid="topup-amount"
          inputMode="numeric"
          value={formatRupiahInput(raw)}
          onChange={(e) => setRaw(digits(e.target.value))}
          className="w-full rounded-2xl border border-input bg-background py-3.5 pl-11 pr-4 text-lg font-extrabold outline-none focus:border-primary"
        />
      </div>
      <div className="mt-3 flex flex-wrap gap-2">
        {[20000, 50000, 100000, 250000].map((a) => (
          <button key={a} data-testid={`topup-preset-${a}`} onClick={() => setRaw(String(a))} className="rounded-xl border border-border px-3 py-2 text-xs font-bold hover:border-primary hover:text-primary">
            {rupiah(a)}
          </button>
        ))}
      </div>
      <p className="mt-3 text-xs text-muted-foreground">{rupiah(limits.min_amount)} – {rupiah(limits.max_amount)}</p>
      <button data-testid="topup-submit" onClick={submit} disabled={busy} className="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground disabled:opacity-60">
        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />} Buat pembayaran
      </button>
    </div>
  );
};
