import React, { useEffect, useRef, useState } from "react";
import {
  Megaphone, Plus, Trash2, Loader2, Pencil, X, Save, Upload, Image as ImageIcon,
  Bold, Italic, Underline, Strikethrough, Quote, EyeOff, List, ListOrdered, Code2, Link2, Pin,
} from "lucide-react";
import { toast } from "sonner";
import { http, errMsg } from "@/lib/api";
import { AnnouncementContent } from "@/components/AnnouncementContent";

const LABELS = ["INFORMASI", "UPDATE", "PENTING", "PROMO", "MAINTENANCE"];
const TONE = {
  INFORMASI: "bg-sky-500/15 text-sky-500 border-sky-500/40",
  UPDATE: "bg-violet-500/15 text-violet-500 border-violet-500/40",
  PENTING: "bg-destructive/15 text-destructive border-destructive/40",
  PROMO: "bg-emerald-500/15 text-emerald-500 border-emerald-500/40",
  MAINTENANCE: "bg-amber-500/15 text-amber-500 border-amber-500/40",
};
const EMPTY = { title: "", body: "", label: "INFORMASI", active: true, pinned: false, image_url: "", image_caption: "" };
const MAX_IMAGE = 4 * 1024 * 1024;

const Card = ({ children, className = "", ...rest }) => (
  <div {...rest} className={`rounded-3xl border border-border bg-card p-5 ${className}`}>{children}</div>
);

const Tool = ({ title, icon: Icon, onClick }) => (
  <button
    type="button"
    title={title}
    aria-label={title}
    onClick={onClick}
    className="grid h-9 w-9 place-items-center rounded-xl border border-border bg-background text-muted-foreground transition-colors hover:border-primary hover:text-primary"
  >
    <Icon className="h-4 w-4" />
  </button>
);

export const AdminAnnouncements = () => {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(EMPTY);
  const [editingId, setEditingId] = useState(null);
  const [busy, setBusy] = useState(false);
  const textareaRef = useRef(null);
  const fileRef = useRef(null);

  const load = () => http.get("/admin/announcements").then(({ data }) => setItems(data.items)).catch(() => {});
  useEffect(() => { load(); }, []);

  const reset = () => {
    setForm(EMPTY);
    setEditingId(null);
    if (fileRef.current) fileRef.current.value = "";
  };

  const setBodyAndSelection = (next, start, end) => {
    setForm((f) => ({ ...f, body: next }));
    requestAnimationFrame(() => {
      const el = textareaRef.current;
      if (!el) return;
      el.focus();
      el.setSelectionRange(start, end);
    });
  };

  const applyFormat = (kind) => {
    const el = textareaRef.current;
    if (!el) return;
    const value = form.body || "";
    const start = el.selectionStart ?? value.length;
    const end = el.selectionEnd ?? value.length;
    const selected = value.slice(start, end);

    const wrap = (left, right = left, placeholder = "teks") => {
      const middle = selected || placeholder;
      const next = value.slice(0, start) + left + middle + right + value.slice(end);
      const selStart = start + left.length;
      setBodyAndSelection(next, selStart, selStart + middle.length);
    };

    const prefixLines = (makePrefix) => {
      const lineStart = value.lastIndexOf("\n", Math.max(0, start - 1)) + 1;
      const lineEndPos = value.indexOf("\n", end);
      const lineEnd = lineEndPos === -1 ? value.length : lineEndPos;
      const block = value.slice(lineStart, lineEnd);
      const changed = block.split("\n").map((line, i) => `${makePrefix(i)}${line}`).join("\n");
      const next = value.slice(0, lineStart) + changed + value.slice(lineEnd);
      setBodyAndSelection(next, lineStart, lineStart + changed.length);
    };

    if (kind === "bold") wrap("**", "**");
    if (kind === "italic") wrap("*", "*");
    if (kind === "underline") wrap("++", "++");
    if (kind === "strike") wrap("~~", "~~");
    if (kind === "spoiler") wrap("||", "||");
    if (kind === "code") wrap("`", "`");
    if (kind === "quote") prefixLines(() => "> ");
    if (kind === "bullet") prefixLines(() => "- ");
    if (kind === "number") prefixLines((i) => `${i + 1}. `);
    if (kind === "link") {
      const url = window.prompt("URL link (https://...)", "https://");
      if (!url) return;
      const text = selected || "teks link";
      const markup = `[${text}](${url})`;
      const next = value.slice(0, start) + markup + value.slice(end);
      setBodyAndSelection(next, start + 1, start + 1 + text.length);
    }
  };

  const acceptImage = (file) => {
    if (!file || !String(file.type || "").startsWith("image/")) return toast.error("File harus berupa gambar");
    if (file.size > MAX_IMAGE) return toast.error("Ukuran gambar maksimal 4 MB");
    const reader = new FileReader();
    reader.onload = () => setForm((f) => ({ ...f, image_url: String(reader.result || "") }));
    reader.onerror = () => toast.error("Gambar gagal dibaca");
    reader.readAsDataURL(file);
  };

  const onPasteImage = (e) => {
    const items = Array.from(e.clipboardData?.items || []);
    const imageItem = items.find((x) => x.kind === "file" && x.type.startsWith("image/"));
    if (!imageItem) return;
    e.preventDefault();
    acceptImage(imageItem.getAsFile());
  };

  const submit = async () => {
    if (!form.title.trim()) return toast.error("Judul wajib diisi");
    setBusy(true);
    try {
      const payload = { ...form, title: form.title.trim(), color: "sky" };
      if (editingId) {
        await http.put(`/admin/announcements/${editingId}`, payload);
        toast.success("Changelog diperbarui");
      } else {
        await http.post("/admin/announcements", payload);
        toast.success("Changelog dipublikasikan");
      }
      reset();
      load();
    } catch (e) { toast.error(errMsg(e)); }
    setBusy(false);
  };

  const edit = (item) => {
    setEditingId(item.id);
    setForm({
      title: item.title || "",
      body: item.body || "",
      label: item.label || "INFORMASI",
      active: item.active !== false,
      pinned: item.pinned === true,
      image_url: item.image_url || "",
      image_caption: item.image_caption || "",
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div data-testid="admin-announcements" className="space-y-5">
      <Card>
        <div className="flex flex-wrap items-center gap-2">
          <Megaphone className="h-4 w-4 text-primary" />
          <h2 className="text-xl font-extrabold">Pengumuman / Changelog</h2>
          {editingId && (
            <span className="ml-auto rounded-xl border border-primary/30 bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary">Mode edit</span>
          )}
        </div>

        <div className="mt-4 space-y-4">
          <input
            data-testid="ann-title"
            placeholder="Judul changelog"
            value={form.title}
            onChange={(e) => setForm({ ...form, title: e.target.value })}
            className="w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary"
          />

          <div>
            <div className="mb-2 flex flex-wrap items-center gap-2">
              <span className="mr-1 text-xs font-bold uppercase tracking-wider text-muted-foreground">Isi changelog</span>
              <Tool title="Bold" icon={Bold} onClick={() => applyFormat("bold")} />
              <Tool title="Italic" icon={Italic} onClick={() => applyFormat("italic")} />
              <Tool title="Underline" icon={Underline} onClick={() => applyFormat("underline")} />
              <Tool title="Coret" icon={Strikethrough} onClick={() => applyFormat("strike")} />
              <Tool title="Quote" icon={Quote} onClick={() => applyFormat("quote")} />
              <Tool title="Spoiler" icon={EyeOff} onClick={() => applyFormat("spoiler")} />
              <Tool title="Bullet" icon={List} onClick={() => applyFormat("bullet")} />
              <Tool title="Nomor" icon={ListOrdered} onClick={() => applyFormat("number")} />
              <Tool title="Code" icon={Code2} onClick={() => applyFormat("code")} />
              <Tool title="Link" icon={Link2} onClick={() => applyFormat("link")} />
            </div>
            <textarea
              ref={textareaRef}
              data-testid="ann-body"
              rows={11}
              placeholder="Tulis isi changelog di sini... Pilih teks lalu gunakan toolbar seperti Telegram."
              value={form.body}
              onPaste={onPasteImage}
              onChange={(e) => setForm({ ...form, body: e.target.value })}
              className="themed-scrollbar min-h-[280px] w-full resize-y rounded-2xl border border-input bg-background px-4 py-4 text-sm leading-6 outline-none focus:border-primary"
            />
          </div>

          <div className="grid gap-4 lg:grid-cols-[1fr_.9fr]">
            <div className="space-y-3">
              <label className="block">
                <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Gambar changelog (opsional)</span>
                <input
                  data-testid="ann-image-url"
                  value={form.image_url}
                  onChange={(e) => setForm({ ...form, image_url: e.target.value })}
                  placeholder="https://... atau pilih/paste gambar"
                  className="mono mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary"
                />
              </label>

              <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => acceptImage(e.target.files?.[0])} />
              <div
                tabIndex={0}
                onPaste={onPasteImage}
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => { e.preventDefault(); acceptImage(e.dataTransfer.files?.[0]); }}
                className="rounded-2xl border border-dashed border-border bg-muted/25 p-4 outline-none transition-colors focus:border-primary"
              >
                <div className="flex flex-wrap items-center gap-3">
                  <button type="button" onClick={() => fileRef.current?.click()} className="flex items-center gap-2 rounded-xl border border-border bg-background px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary">
                    <Upload className="h-4 w-4" /> Pilih File
                  </button>
                  {form.image_url && (
                    <button type="button" onClick={() => setForm({ ...form, image_url: "", image_caption: "" })} className="flex items-center gap-2 rounded-xl border border-destructive/40 px-3 py-2.5 text-xs font-bold text-destructive">
                      <X className="h-4 w-4" /> Hapus gambar
                    </button>
                  )}
                </div>
                <p className="mt-3 text-xs leading-5 text-muted-foreground">Paste gambar dengan <b>Ctrl+V</b>, drag & drop, pilih file lokal, atau isi URL di atas. Maks. 4 MB.</p>
              </div>

              <label className="block">
                <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Caption gambar (opsional)</span>
                <input
                  data-testid="ann-image-caption"
                  value={form.image_caption}
                  onChange={(e) => setForm({ ...form, image_caption: e.target.value })}
                  placeholder="Keterangan gambar"
                  className="mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary"
                />
              </label>
            </div>

            <div className="rounded-2xl border border-border bg-background p-4">
              <div className="mb-3 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground"><ImageIcon className="h-4 w-4" /> Preview</div>
              <p className="text-sm font-bold">{form.title || "Judul changelog"}</p>
              <AnnouncementContent body={form.body || "Isi changelog akan tampil di sini."} imageUrl={form.image_url} imageCaption={form.image_caption} className="mt-2" />
            </div>
          </div>

          <div className="flex flex-wrap gap-2">
            {LABELS.map((l) => (
              <button
                type="button"
                key={l}
                data-testid={`ann-label-${l}`}
                onClick={() => setForm({ ...form, label: l })}
                className={`rounded-xl border px-3 py-1.5 text-[11px] font-bold ${form.label === l ? TONE[l] + " ring-1 ring-primary" : "border-border text-muted-foreground"}`}
              >{l}</button>
            ))}
          </div>

          <button type="button" onClick={() => setForm({ ...form, pinned: !form.pinned })} className={`flex items-center gap-2 rounded-2xl border px-4 py-2.5 text-xs font-bold ${form.pinned ? "border-primary/50 bg-primary/10 text-primary" : "border-border text-muted-foreground"}`}>
            <Pin className={`h-4 w-4 ${form.pinned ? "fill-current" : ""}`} /> {form.pinned ? "Pengumuman dipin" : "Pin pengumuman"}
          </button>

          <div className="flex flex-wrap gap-2">
            <button data-testid="ann-submit" onClick={submit} disabled={busy} className="flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-60">
              {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : editingId ? <Save className="h-4 w-4" /> : <Plus className="h-4 w-4" />}
              {editingId ? "Simpan perubahan" : "Publikasikan"}
            </button>
            {editingId && (
              <button type="button" onClick={reset} className="flex items-center gap-2 rounded-2xl border border-border px-5 py-3 text-sm font-bold hover:border-primary hover:text-primary">
                <X className="h-4 w-4" /> Batal edit
              </button>
            )}
          </div>
        </div>
      </Card>

      <div className="space-y-3">
        {items.map((a) => (
          <Card key={a.id} data-testid={`admin-ann-${a.id}`}>
            <div className="flex flex-wrap items-start gap-3">
              <span className={`rounded-lg border px-2 py-0.5 text-[10px] font-bold ${TONE[a.label] || TONE.INFORMASI}`}>{a.label}</span>
              {a.pinned && <span className="flex items-center gap-1 rounded-lg border border-primary/40 bg-primary/10 px-2 py-0.5 text-[10px] font-bold text-primary"><Pin className="h-3 w-3 fill-current" /> PIN</span>}
              <div className="min-w-[180px] flex-1">
                <p className="text-sm font-bold">{a.title}</p>
                <AnnouncementContent body={a.body} imageUrl={a.image_url} imageCaption={a.image_caption} className="mt-2 max-w-3xl" />
              </div>
              <div className="flex flex-wrap gap-2">
                <button type="button" onClick={async () => { await http.put(`/admin/announcements/${a.id}`, { ...a, pinned: !a.pinned }); load(); }} className={`rounded-xl border p-2.5 ${a.pinned ? "border-primary/40 bg-primary/10 text-primary" : "border-border text-muted-foreground hover:border-primary hover:text-primary"}`} title={a.pinned ? "Lepas pin" : "Pin"}>
                  <Pin className={`h-4 w-4 ${a.pinned ? "fill-current" : ""}`} />
                </button>
                <button type="button" onClick={() => edit(a)} className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-primary hover:text-primary" title="Edit">
                  <Pencil className="h-4 w-4" />
                </button>
                <button
                  data-testid={`admin-ann-toggle-${a.id}`}
                  onClick={async () => { await http.put(`/admin/announcements/${a.id}`, { ...a, active: !a.active }); load(); }}
                  className={`rounded-xl border px-3 py-1.5 text-[11px] font-bold ${a.active ? "border-emerald-500/40 text-emerald-500" : "border-border text-muted-foreground"}`}
                >{a.active ? "Aktif" : "Nonaktif"}</button>
                <button
                  data-testid={`admin-ann-delete-${a.id}`}
                  onClick={async () => { await http.delete(`/admin/announcements/${a.id}`); toast.success("Dihapus"); if (editingId === a.id) reset(); load(); }}
                  className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-destructive hover:text-destructive"
                ><Trash2 className="h-4 w-4" /></button>
              </div>
            </div>
          </Card>
        ))}
        {items.length === 0 && <p className="text-sm text-muted-foreground">Belum ada pengumuman.</p>}
      </div>
    </div>
  );
};
