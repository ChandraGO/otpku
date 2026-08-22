import React, { useEffect, useState } from "react";
import { ArrowLeft, BookOpen, CalendarDays } from "lucide-react";
import { http } from "@/lib/api";
import { AnnouncementContent } from "@/components/AnnouncementContent";

export const BlogFeed = ({ initialSlug = "" }) => {
  const [items, setItems] = useState([]);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    Promise.all([
      http.get("/blog"),
      initialSlug ? http.get(`/blog/${initialSlug}`).catch(() => null) : Promise.resolve(null),
    ]).then(([list, detail]) => {
      if (!alive) return;
      setItems(list?.data?.items || []);
      if (detail?.data) setSelected(detail.data);
    }).finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [initialSlug]);

  if (loading) return <div className="h-40 animate-pulse rounded-3xl bg-muted" />;

  if (selected) {
    return (
      <article data-testid="blog-detail" className="rounded-3xl border border-border bg-card p-5 sm:p-8">
        <button onClick={() => setSelected(null)} className="mb-5 flex items-center gap-2 text-sm font-bold text-primary"><ArrowLeft className="h-4 w-4" /> Kembali ke Blog</button>
        {selected.cover_url && <img src={selected.cover_url} alt="" className="max-h-[440px] w-full rounded-2xl border border-border bg-muted object-cover" />}
        <div className="mt-6 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
          <CalendarDays className="h-3.5 w-3.5" />
          {selected.published_at || selected.created_at ? new Date(selected.published_at || selected.created_at).toLocaleDateString("id-ID", { dateStyle: "long" }) : ""}
        </div>
        <h1 className="mt-2 text-3xl font-extrabold sm:text-4xl">{selected.title}</h1>
        {selected.excerpt && <p className="mt-3 text-base leading-7 text-muted-foreground">{selected.excerpt}</p>}
        <AnnouncementContent body={selected.body} className="mt-7 text-[15px] leading-7" />
      </article>
    );
  }

  return (
    <div data-testid="blog-feed" className="space-y-5">
      <div className="flex items-center gap-3">
        <span className="grid h-11 w-11 place-items-center rounded-2xl bg-primary/15 text-primary"><BookOpen className="h-5 w-5" /></span>
        <div><h2 className="text-2xl font-extrabold">Blog</h2><p className="text-sm text-muted-foreground">Artikel, panduan, dan update terbaru.</p></div>
      </div>
      <div className="grid gap-5 md:grid-cols-2">
        {items.map((post) => (
          <button key={post.id} onClick={() => setSelected(post)} className="overflow-hidden rounded-3xl border border-border bg-card text-left transition hover:-translate-y-0.5 hover:border-primary/50">
            {post.cover_url && <img src={post.cover_url} alt="" className="h-48 w-full border-b border-border bg-muted object-cover" />}
            <div className="p-5">
              <p className="text-[11px] font-bold uppercase tracking-wider text-primary">Artikel</p>
              <h3 className="mt-2 text-xl font-extrabold">{post.title}</h3>
              {post.excerpt && <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">{post.excerpt}</p>}
              <p className="mt-4 text-xs font-bold text-primary">Baca artikel →</p>
            </div>
          </button>
        ))}
      </div>
      {items.length === 0 && <div className="rounded-3xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Belum ada artikel yang dipublikasikan.</div>}
    </div>
  );
};
