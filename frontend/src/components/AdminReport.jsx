import React, { useEffect, useState } from "react";
import { Download, Loader2, TrendingUp } from "lucide-react";
import { toast } from "sonner";
import { AreaChart, Area, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid, BarChart, Bar, Legend } from "recharts";
import { http, errMsg, rupiah, API } from "@/lib/api";

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-6 ${className}`}>{children}</div>
);

export const AdminReport = () => {
  const [month, setMonth] = useState(new Date().toISOString().slice(0, 7));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    http.get("/admin/report", { params: { month } })
      .then(({ data }) => setData(data))
      .catch((e) => toast.error(errMsg(e)))
      .finally(() => setLoading(false));
  }, [month]);

  const download = async () => {
    try {
      const res = await http.get("/admin/report.csv", { params: { month }, responseType: "blob" });
      const url = URL.createObjectURL(res.data);
      const a = document.createElement("a");
      a.href = url;
      a.download = `report-${month}.csv`;
      a.click();
      URL.revokeObjectURL(url);
      toast.success("Laporan diunduh");
    } catch (e) { toast.error(errMsg(e)); }
  };

  const t = data?.totals;

  return (
    <div data-testid="admin-report" className="space-y-5">
      <Card className="flex flex-wrap items-center gap-3">
        <TrendingUp className="h-5 w-5 text-primary" />
        <p className="font-bold">Laporan untung rugi</p>
        <input
          data-testid="report-month"
          type="month"
          value={month}
          onChange={(e) => setMonth(e.target.value)}
          className="ml-auto rounded-xl border border-input bg-background px-3 py-2 text-sm outline-none focus:border-primary"
        />
        <button data-testid="report-download" onClick={download} className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-primary-foreground transition-transform hover:scale-[1.02]">
          <Download className="h-4 w-4" /> Unduh CSV
        </button>
      </Card>

      {loading ? (
        <div className="flex justify-center py-16"><Loader2 className="h-6 w-6 animate-spin text-primary" /></div>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            {[["Pesanan", t.orders], ["Penjualan", rupiah(t.revenue)], ["Biaya provider", rupiah(t.cost)], ["Laba bersih", rupiah(t.profit)], ["Isi saldo", rupiah(t.topup)]].map(([l, v], i) => (
              <Card key={l} data-testid={`report-stat-${i}`} className="hover-lift !p-5">
                <p className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{l}</p>
                <p className={`mt-2 text-2xl font-extrabold ${l === "Laba bersih" ? (t.profit >= 0 ? "text-emerald-500" : "text-destructive") : ""}`}>{v}</p>
              </Card>
            ))}
          </div>

          <Card>
            <p className="font-bold">Tren penjualan & laba</p>
            {data.series.length === 0 ? (
              <p className="mt-4 text-sm text-muted-foreground">Belum ada data untuk bulan ini.</p>
            ) : (
              <div className="mt-5 h-72" data-testid="report-chart">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={data.series}>
                    <defs>
                      <linearGradient id="gRev" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="hsl(var(--primary))" stopOpacity={0.6} />
                        <stop offset="100%" stopColor="hsl(var(--primary))" stopOpacity={0.05} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="date" stroke="hsl(var(--muted-foreground))" fontSize={11} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={11} />
                    <Tooltip
                      contentStyle={{ background: "hsl(var(--popover))", border: "1px solid hsl(var(--border))", borderRadius: 12, color: "hsl(var(--foreground))" }}
                      formatter={(v, n) => [rupiah(v), n]}
                    />
                    <Area type="monotone" dataKey="revenue" name="Penjualan" stroke="hsl(var(--primary))" fill="url(#gRev)" strokeWidth={2} />
                    <Area type="monotone" dataKey="profit" name="Laba" stroke="#22c55e" fill="transparent" strokeWidth={2} />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            )}
          </Card>

          {data.series.length > 0 && (
            <Card>
              <p className="font-bold">Biaya provider vs isi saldo</p>
              <div className="mt-5 h-64">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={data.series}>
                    <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                    <XAxis dataKey="date" stroke="hsl(var(--muted-foreground))" fontSize={11} />
                    <YAxis stroke="hsl(var(--muted-foreground))" fontSize={11} />
                    <Tooltip
                      contentStyle={{ background: "hsl(var(--popover))", border: "1px solid hsl(var(--border))", borderRadius: 12, color: "hsl(var(--foreground))" }}
                      formatter={(v, n) => [rupiah(v), n]}
                    />
                    <Legend />
                    <Bar dataKey="cost" name="Biaya provider" fill="#f59e0b" radius={[6, 6, 0, 0]} />
                    <Bar dataKey="topup" name="Isi saldo" fill="hsl(var(--primary))" radius={[6, 6, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card>
          )}
        </>
      )}
    </div>
  );
};
