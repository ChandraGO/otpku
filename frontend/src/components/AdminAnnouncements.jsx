import React, { useEffect, useState } from "react";
import { Megaphone, Plus, Trash2, Loader2 } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg } from "@/lib/api";

const LABELS = ["INFORMASI", "UPDATE", "PENTING", "PROMO", "MAINTENANCE"];
const TONE = {
  INFORMASI: "bg-sky-500/15 text-sky-500 border-sky-500/40",
  UPDATE: "bg-violet-500/15 text-violet-500 border-violet-500/40",
  PENTING: "bg-destructive/15 text-destructive border-destructive/40",
  PROMO: "bg-emerald-500/15 text-emerald-500 border-emerald-500/40",
  MAINTENANCE: "bg-amber-500/15 text-amber-500 border-amber-500/40",
};

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-5 ${className}`}>{children}</div>
);

export const AdminAnnouncements = () => {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState({ title: "", body: "", label: "INFORMASI", active: true });
  const [busy, setBusy] = useState(false);

  const load = () => http.get("/admin/announcements").then(({ data }) => setItems(data.items)).catch(() => {});
  useEffect(() => { load(); }, []);

  const create = async () => {
    if (!form.title) return toast.error("Judul wajib diisi");
    setBusy(true);
    try {
      await http.post("/admin/announcements", { ...form, color: "sky" });
      toast.success("Pengumuman dipublikasikan");
      setForm({ title: "", body: "", label: "INFORMASI", active: true });
      load();
    } catch (e) { toast.error(errMsg(e)); }
    setBusy(false);
  };

  return (
    <div data-testid="admin-announcements" className="space-y-5">
      <Card>
        <div className="flex items-center gap-2">
          <Megaphone className="h-4 w-4 text-primary" />
          <h2 className="text-xl font-extrabold">Pengumuman</h2>
        </div>
        <div className="mt-4 space-y-3">
          <input data-testid="ann-title" placeholder="Judul" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
          <textarea data-testid="ann-body" rows={3} placeholder="Isi pengumuman" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
          <div className="flex flex-wrap gap-2">
            {LABELS.map((l) => (
              <button
                key={l}
                data-testid={`ann-label-${l}`}
                onClick={() => setForm({ ...form, label: l })}
                className={`rounded-xl border px-3 py-1.5 text-[11px] font-bold ${form.label === l ? TONE[l] + " ring-1 ring-primary" : "border-border text-muted-foreground"}`}
              >{l}</button>
            ))}
          </div>
          <button data-testid="ann-submit" onClick={create} disabled={busy} className="flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-60">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Plus className="h-4 w-4" />} Publikasikan
          </button>
        </div>
      </Card>

      <div className="space-y-3">
        {items.map((a) => (
          <Card key={a.id} data-testid={`admin-ann-${a.id}`} className="flex flex-wrap items-center gap-3">
            <span className={`rounded-lg border px-2 py-0.5 text-[10px] font-bold ${TONE[a.label] || TONE.INFORMASI}`}>{a.label}</span>
            <div className="min-w-[180px] flex-1">
              <p className="text-sm font-bold">{a.title}</p>
              <p className="truncate text-xs text-muted-foreground">{a.body}</p>
            </div>
            <button
              data-testid={`admin-ann-toggle-${a.id}`}
              onClick={async () => { await http.put(`/admin/announcements/${a.id}`, { ...a, active: !a.active }); load(); }}
              className={`rounded-xl border px-3 py-1.5 text-[11px] font-bold ${a.active ? "border-emerald-500/40 text-emerald-500" : "border-border text-muted-foreground"}`}
            >{a.active ? "Aktif" : "Nonaktif"}</button>
            <button
              data-testid={`admin-ann-delete-${a.id}`}
              onClick={async () => { await http.delete(`/admin/announcements/${a.id}`); toast.success("Dihapus"); load(); }}
              className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-destructive hover:text-destructive"
            ><Trash2 className="h-4 w-4" /></button>
          </Card>
        ))}
        {items.length === 0 && <p className="text-sm text-muted-foreground">Belum ada pengumuman.</p>}
      </div>
    </div>
  );
};
