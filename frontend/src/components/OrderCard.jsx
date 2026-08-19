import React, { useEffect, useState } from "react";
import { RefreshCw, Send, XCircle, Copy, Clock, CheckCircle2, Loader2, PlayCircle, BadgeCheck } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg, rupiah } from "@/lib/api";
import { copyText } from "@/lib/clipboard";

const STATUS = {
  pending: ["bg-amber-500/15 text-amber-500 border-amber-500/40", "MENUNGGU OTP"],
  success: ["bg-emerald-500/15 text-emerald-500 border-emerald-500/40", "SELESAI"],
  refunded: ["bg-sky-500/15 text-sky-500 border-sky-500/40", "DIKEMBALIKAN"],
  cancelled: ["bg-muted text-muted-foreground border-border", "DIBATALKAN"],
  expired: ["bg-destructive/15 text-destructive border-destructive/40", "KEDALUWARSA"],
};

const mmss = (sec) => {
  const s = Math.max(0, Math.floor(sec));
  return `${String(Math.floor(s / 60)).padStart(2, "0")}:${String(s % 60).padStart(2, "0")}`;
};

export const OrderCard = ({ order, onChange, highlight }) => {
  const [tick, setTick] = useState(Date.now());
  const [busy, setBusy] = useState("");

  useEffect(() => {
    const iv = setInterval(() => setTick(Date.now()), 1000);
    return () => clearInterval(iv);
  }, []);

  const isPending = order.status === "pending";
  const expLeft = order.expires_at ? (new Date(order.expires_at).getTime() - tick) / 1000 : 0;
  const cancelLeft = order.cancel_available_at ? (new Date(order.cancel_available_at).getTime() - tick) / 1000 : 0;
  const hasOtp = (order.otp_codes || []).length > 0;
  const canCancel = isPending && cancelLeft <= 0 && !hasOtp && expLeft > 0;
  const [style, label] = STATUS[order.status] || STATUS.cancelled;

  const run = async (key, fn, ok) => {
    setBusy(key);
    try {
      await fn();
      toast.success(ok);
      onChange?.();
    } catch (e) {
      toast.error(errMsg(e));
    }
    setBusy("");
  };

  const Btn = ({ id, icon: Icon, label: l, onClick, disabled, tone = "ghost", testid }) => (
    <button
      data-testid={testid}
      onClick={onClick}
      disabled={disabled || busy === id}
      className={`flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-bold transition-colors ${
        tone === "danger"
          ? disabled
            ? "cursor-not-allowed border border-border text-muted-foreground opacity-50"
            : "border border-destructive/60 bg-destructive/10 text-destructive hover:bg-destructive hover:text-destructive-foreground"
          : tone === "primary"
          ? "bg-primary text-primary-foreground hover:opacity-90"
          : "border border-border hover:border-primary hover:text-primary"
      } disabled:opacity-50`}
    >
      {busy === id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Icon className="h-4 w-4" />} {l}
    </button>
  );

  return (
    <article
      data-testid={`order-${order.id}`}
      className={`overflow-hidden rounded-3xl border bg-card transition-all ${highlight ? "border-primary ring-2 ring-primary/40" : "border-border"}`}
    >
      <div className="flex flex-wrap items-center gap-3 border-b border-border bg-muted/40 px-4 py-3 sm:px-5">
        <span className="mono text-[11px] text-muted-foreground">{order.invoice_no || order.id}</span>
        <span data-testid={`order-status-${order.id}`} className={`ml-auto rounded-lg border px-2.5 py-1 text-[11px] font-bold ${style}`}>{label}</span>
        <span className="font-bold">{rupiah(order.price)}</span>
      </div>

      <div className="grid gap-5 px-4 py-5 sm:px-5 lg:grid-cols-[1.1fr_1fr_auto]">
        <div>
          <div className="flex items-center gap-2">
            {order.service_logo && <img src={order.service_logo} alt="" className="h-8 w-8 rounded-lg bg-muted object-contain p-1" onError={(e) => { e.target.style.display = "none"; }} />}
            <p className="font-bold">{order.service_name} · <span className="text-muted-foreground">{order.country_name}</span></p>
          </div>
          <div className="mt-3 flex items-center gap-2">
            <code data-testid={`order-phone-${order.id}`} className="mono rounded-xl bg-muted px-3 py-2 text-sm font-bold">{order.phone_number || "—"}</code>
            <button
              data-testid={`order-copy-phone-${order.id}`}
              onClick={() => copyText(order.phone_number, "Nomor dikopi")}
              className="rounded-xl border border-border p-2 transition-colors hover:border-primary hover:text-primary"
            >
              <Copy className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>

        <div>
          {hasOtp ? (
            <div className="flex items-center gap-2">
              <p data-testid={`order-otp-${order.id}`} className="mono text-2xl font-extrabold tracking-[0.2em] text-emerald-500">{order.otp_codes.join(" · ")}</p>
              <button
                data-testid={`order-copy-otp-${order.id}`}
                onClick={() => copyText(order.otp_codes[order.otp_codes.length - 1], "Kode OTP dikopi")}
                className="rounded-xl border border-border p-2 transition-colors hover:border-primary hover:text-primary"
              >
                <Copy className="h-3.5 w-3.5" />
              </button>
            </div>
          ) : isPending ? (
            <div className="flex items-center gap-3">
              <span className="relative flex h-3 w-3">
                <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-70" />
                <span className="relative inline-flex h-3 w-3 rounded-full bg-primary" />
              </span>
              <div className="h-3 w-32 animate-pulse rounded-full bg-muted" />
            </div>
          ) : (
            <p className="text-sm text-muted-foreground">—</p>
          )}

          {isPending && !hasOtp && (
            <div className="mt-3 space-y-1.5 text-xs">
              <p data-testid={`order-timeleft-${order.id}`} className={`flex items-center gap-1.5 font-bold ${expLeft < 60 ? "text-destructive" : "text-muted-foreground"}`}>
                <Clock className="h-3.5 w-3.5" /> {mmss(expLeft)}
              </p>
              {cancelLeft > 0 ? (
                <p data-testid={`order-cancel-countdown-${order.id}`} className="flex items-center gap-1.5 text-amber-500">
                  <Clock className="h-3.5 w-3.5" /> {mmss(cancelLeft)}
                </p>
              ) : (
                <p className="flex items-center gap-1.5 text-emerald-500"><CheckCircle2 className="h-3.5 w-3.5" /> Batal aktif</p>
              )}
            </div>
          )}
        </div>

        {isPending && (
          <div className="flex flex-wrap items-start gap-2">
            {!order.ready && (
              <Btn id="y" testid={`order-ready-${order.id}`} icon={PlayCircle} label="Siap" tone="primary"
                onClick={() => run("y", () => http.post(`/orders/${order.id}/ready`), "Nomor siap menerima OTP")} />
            )}
            <Btn id="r" testid={`order-refresh-${order.id}`} icon={RefreshCw} label="Cek"
              onClick={() => run("r", () => http.get(`/orders/${order.id}`), "Diperbarui")} />
            <Btn id="s" testid={`order-resend-${order.id}`} icon={Send} label="Kirim ulang"
              onClick={() => run("s", () => http.post(`/orders/${order.id}/resend`), "Permintaan OTP baru dikirim")} />
            {hasOtp && (
              <Btn id="k" testid={`order-complete-${order.id}`} icon={BadgeCheck} label="Selesai" tone="primary"
                onClick={() => run("k", () => http.post(`/orders/${order.id}/complete`), "Pesanan diselesaikan")} />
            )}
            {!hasOtp && (
              <Btn id="c" testid={`order-cancel-${order.id}`} icon={XCircle} tone="danger" disabled={!canCancel}
                label={canCancel ? "Batalkan" : mmss(cancelLeft)}
                onClick={() => run("c", () => http.post(`/orders/${order.id}/cancel`), "Dibatalkan, saldo kembali")} />
            )}
          </div>
        )}
      </div>
    </article>
  );
};
