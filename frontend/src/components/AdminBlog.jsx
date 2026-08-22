import React, { useEffect, useRef, useState } from "react";
import { Bold, BookOpen, Code2, Eye, EyeOff, ExternalLink, Image as ImageIcon, Italic, Link2, List, ListOrdered, Loader2, Pencil, Plus, Quote, Save, Trash2, Upload, X } from "lucide-react";
import { toast } from "sonner";
import { http, errMsg } from "@/lib/api";
import { AnnouncementContent } from "@/components/AnnouncementContent";

const EMPTY = { title: "", slug: "", excerpt: "", body: "", cover_url: "", published: true };
const MAX_IMAGE = 4 * 1024 * 1024;

const Card = ({ children, className = "", ...rest }) => <div {...rest} className={`rounded-3xl border border-border bg-card p-5 ${className}`}>{children}</div>;

export const AdminBlog = () => {
  const [items, setItems] = useState([]);
  const [form, setForm] = useState(EMPTY);
  const [editingId, setEditingId] = useState(null);
  const [busy, setBusy] = useState(false);
  const fileRef = useRef(null);
  const bodyRef = useRef(null);

  const load = () => http.get("/admin/blog").then(({ data }) => setItems(data.items || [])).catch(() => {});
  useEffect(() => { load(); }, []);

  const reset = () => {
    setForm(EMPTY);
    setEditingId(null);
    if (fileRef.current) fileRef.current.value = "";
  };

  const formatBody = (kind) => {
    const el = bodyRef.current;
    if (!el) return;
    const value = form.body || "";
    const start = el.selectionStart ?? value.length;
    const end = el.selectionEnd ?? value.length;
    const selected = value.slice(start, end);

    const update = (next, selStart, selEnd) => {
      setForm((f) => ({ ...f, body: next }));
      requestAnimationFrame(() => {
        bodyRef.current?.focus();
        bodyRef.current?.setSelectionRange(selStart, selEnd);
      });
    };
    const wrap = (left, right = left, placeholder = "teks") => {
      const middle = selected || placeholder;
      update(value.slice(0, start) + left + middle + right + value.slice(end), start + left.length, start + left.length + middle.length);
    };
    const lines = (prefix) => {
      const lineStart = value.lastIndexOf("\n", Math.max(0, start - 1)) + 1;
      const lineEndPos = value.indexOf("\n", end);
      const lineEnd = lineEndPos === -1 ? value.length : lineEndPos;
      const block = value.slice(lineStart, lineEnd);
      const changed = block.split("\n").map((line, i) => `${prefix(i)}${line}`).join("\n");
      update(value.slice(0, lineStart) + changed + value.slice(lineEnd), lineStart, lineStart + changed.length);
    };

    if (kind === "bold") wrap("**", "**");
    if (kind === "italic") wrap("*", "*");
    if (kind === "code") wrap("`", "`");
    if (kind === "quote") lines(() => "> ");
    if (kind === "bullet") lines(() => "- ");
    if (kind === "number") lines((i) => `${i + 1}. `);
    if (kind === "link") {
      const url = window.prompt("URL link (https://...)", "https://");
      if (!url) return;
      const text = selected || "teks link";
      const markup = `[${text}](${url})`;
      update(value.slice(0, start) + markup + value.slice(end), start + 1, start + 1 + text.length);
    }
  };

  const acceptImage = (file) => {
    if (!file || !String(file.type || "").startsWith("image/")) return toast.error("File harus berupa gambar");
    if (file.size > MAX_IMAGE) return toast.error("Ukuran cover maksimal 4 MB");
    const reader = new FileReader();
    reader.onload = () => setForm((f) => ({ ...f, cover_url: String(reader.result || "") }));
    reader.onerror = () => toast.error("Cover gagal dibaca");
    reader.readAsDataURL(file);
  };

  const submit = async () => {
    if (!form.title.trim()) return toast.error("Judul artikel wajib diisi");
    setBusy(true);
    try {
      const payload = { ...form, title: form.title.trim(), slug: form.slug.trim() };
      if (editingId) {
        await http.put(`/admin/blog/${editingId}`, payload);
        toast.success("Artikel diperbarui");
      } else {
        await http.post("/admin/blog", payload);
        toast.success(form.published ? "Artikel dipublikasikan" : "Draft disimpan");
      }
      reset();
      load();
    } catch (e) {
      toast.error(errMsg(e));
    } finally {
      setBusy(false);
    }
  };

  const edit = (post) => {
    setEditingId(post.id);
    setForm({
      title: post.title || "",
      slug: post.slug || "",
      excerpt: post.excerpt || "",
      body: post.body || "",
      cover_url: post.cover_url || "",
      published: post.published !== false,
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <div data-testid="admin-blog" className="space-y-5">
      <Card>
        <div className="flex flex-wrap items-center gap-3">
          <BookOpen className="h-5 w-5 text-primary" />
          <div>
            <h2 className="text-xl font-extrabold">Blog / Artikel</h2>
            <p className="mt-1 text-xs text-muted-foreground">Buat artikel seperti posting blog: judul, slug, ringkasan, cover, isi, dan status publikasi.</p>
          </div>
          {editingId && <span className="ml-auto rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-bold text-primary">Mode edit</span>}
        </div>

        <div className="mt-5 grid gap-4 sm:grid-cols-2">
          <label className="block sm:col-span-2">
            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Judul artikel</span>
            <input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} placeholder="Judul artikel" className="mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
          </label>
          <label className="block">
            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Slug URL</span>
            <input value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} placeholder="otomatis-dari-judul" className="mono mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
          </label>
          <label className="block">
            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Status</span>
            <button type="button" onClick={() => setForm({ ...form, published: !form.published })} className={`mt-2 flex w-full items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-bold ${form.published ? "border-emerald-500/40 bg-emerald-500/10 text-emerald-500" : "border-border text-muted-foreground"}`}>
              {form.published ? <Eye className="h-4 w-4" /> : <EyeOff className="h-4 w-4" />} {form.published ? "Publikasikan" : "Simpan sebagai draft"}
            </button>
          </label>
          <label className="block sm:col-span-2">
            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Ringkasan</span>
            <textarea rows={3} value={form.excerpt} onChange={(e) => setForm({ ...form, excerpt: e.target.value })} placeholder="Ringkasan singkat yang tampil di daftar Blog" className="mt-2 w-full resize-y rounded-2xl border border-input bg-background px-4 py-3 text-sm leading-6 outline-none focus:border-primary" />
          </label>
          <div className="sm:col-span-2">
            <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Isi artikel</span>
            <div className="mt-2 flex flex-wrap gap-2 rounded-t-2xl border border-b-0 border-input bg-muted/30 p-2">
              {[
                [Bold, "Bold", "bold"], [Italic, "Italic", "italic"], [Quote, "Quote", "quote"],
                [List, "Bullet", "bullet"], [ListOrdered, "Nomor", "number"], [Code2, "Code", "code"], [Link2, "Link", "link"],
              ].map(([Icon, label, kind]) => (
                <button key={kind} type="button" onClick={() => formatBody(kind)} title={label} className="grid h-9 w-9 place-items-center rounded-xl border border-border bg-background text-muted-foreground hover:border-primary hover:text-primary">
                  <Icon className="h-4 w-4" />
                </button>
              ))}
            </div>
            <textarea ref={bodyRef} rows={14} value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} placeholder="Tulis artikel. Pilih teks lalu gunakan toolbar untuk format seperti editor blog." className="themed-scrollbar min-h-[340px] w-full resize-y rounded-b-2xl border border-input bg-background px-4 py-4 text-sm leading-6 outline-none focus:border-primary" />
          </div>
        </div>

        <div className="mt-4 grid gap-4 lg:grid-cols-[1fr_.9fr]">
          <div>
            <label className="block">
              <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">Cover artikel</span>
              <input value={form.cover_url} onChange={(e) => setForm({ ...form, cover_url: e.target.value })} placeholder="https://... atau upload gambar" className="mono mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none focus:border-primary" />
            </label>
            <input ref={fileRef} type="file" accept="image/*" className="hidden" onChange={(e) => acceptImage(e.target.files?.[0])} />
            <div className="mt-3 flex flex-wrap gap-2">
              <button type="button" onClick={() => fileRef.current?.click()} className="flex items-center gap-2 rounded-xl border border-border px-4 py-2.5 text-xs font-bold hover:border-primary hover:text-primary"><Upload className="h-4 w-4" /> Upload cover</button>
              {form.cover_url && <button type="button" onClick={() => setForm({ ...form, cover_url: "" })} className="flex items-center gap-2 rounded-xl border border-destructive/40 px-4 py-2.5 text-xs font-bold text-destructive"><X className="h-4 w-4" /> Hapus cover</button>}
            </div>
          </div>
          <div className="rounded-2xl border border-border bg-background p-4">
            <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-muted-foreground"><ImageIcon className="h-4 w-4" /> Preview</div>
            {form.cover_url && <img src={form.cover_url} alt="" className="mt-3 max-h-52 w-full rounded-xl border border-border bg-muted object-cover" />}
            <p className="mt-4 text-lg font-extrabold">{form.title || "Judul artikel"}</p>
            {form.excerpt && <p className="mt-2 text-sm text-muted-foreground">{form.excerpt}</p>}
            <AnnouncementContent body={form.body || "Isi artikel akan tampil di sini."} className="mt-4 max-h-56 overflow-hidden" />
          </div>
        </div>

        <div className="mt-6 flex flex-wrap gap-2">
          <button onClick={submit} disabled={busy} className="flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-primary-foreground disabled:opacity-60">
            {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : editingId ? <Save className="h-4 w-4" /> : <Plus className="h-4 w-4" />} {editingId ? "Simpan perubahan" : form.published ? "Publikasikan artikel" : "Simpan draft"}
          </button>
          {editingId && <button type="button" onClick={reset} className="flex items-center gap-2 rounded-2xl border border-border px-5 py-3 text-sm font-bold hover:border-primary hover:text-primary"><X className="h-4 w-4" /> Batal edit</button>}
        </div>
      </Card>

      <div className="space-y-3">
        {items.map((post) => (
          <Card key={post.id} className="flex flex-wrap items-start gap-4">
            {post.cover_url && <img src={post.cover_url} alt="" className="h-20 w-28 rounded-xl border border-border bg-muted object-cover" />}
            <div className="min-w-[220px] flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className={`rounded-lg border px-2 py-0.5 text-[10px] font-bold ${post.published ? "border-emerald-500/40 text-emerald-500" : "border-border text-muted-foreground"}`}>{post.published ? "PUBLISHED" : "DRAFT"}</span>
                <span className="mono text-[10px] text-muted-foreground">/blog/{post.slug}</span>
              </div>
              <p className="mt-2 font-bold">{post.title}</p>
              {post.excerpt && <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">{post.excerpt}</p>}
            </div>
            <div className="flex gap-2">
              {post.published && <a href={`/blog/${post.slug}`} target="_blank" rel="noreferrer noopener" className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-primary hover:text-primary" title="Buka artikel"><ExternalLink className="h-4 w-4" /></a>}
              <button onClick={() => edit(post)} className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-primary hover:text-primary" title="Edit"><Pencil className="h-4 w-4" /></button>
              <button onClick={async () => { if (!window.confirm("Hapus artikel ini?")) return; await http.delete(`/admin/blog/${post.id}`); toast.success("Artikel dihapus"); if (editingId === post.id) reset(); load(); }} className="rounded-xl border border-border p-2.5 text-muted-foreground hover:border-destructive hover:text-destructive" title="Hapus"><Trash2 className="h-4 w-4" /></button>
            </div>
          </Card>
        ))}
        {items.length === 0 && <p className="text-sm text-muted-foreground">Belum ada artikel.</p>}
      </div>
    </div>
  );
};
