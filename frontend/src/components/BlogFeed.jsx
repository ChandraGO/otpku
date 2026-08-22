import React, { useEffect, useState } from "react";
import { Link } from "react-router-dom";
import { ArrowLeft, BookOpen, CalendarDays, Link2, Share2 } from "lucide-react";
import { toast } from "sonner";
import { http } from "@/lib/api";
import { copyText } from "@/lib/clipboard";
import { AnnouncementContent } from "@/components/AnnouncementContent";
import { BlogComments } from "@/components/BlogComments";

const articleUrl = (slug) => `${window.location.origin}/blog/${encodeURIComponent(slug)}`;

export const BlogFeed = ({ initialSlug = "" }) => {
  const [items, setItems] = useState([]);
  const [selected, setSelected] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;
    setLoading(true);
    Promise.all([
      http.get("/blog"),
      initialSlug ? http.get(`/blog/${initialSlug}`).catch(() => null) : Promise.resolve(null),
    ]).then(([list, detail]) => {
      if (!alive) return;
      setItems(list?.data?.items || []);
      setSelected(detail?.data || null);
    }).finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [initialSlug]);

  const shareArticle = async (post) => {
    const url = articleUrl(post.slug);
    try {
      if (navigator.share) {
        await navigator.share({ title: post.title, text: post.excerpt || post.title, url });
      } else {
        await copyText(url, "Link artikel disalin");
      }
    } catch (err) {
      if (err?.name !== "AbortError") toast.error("Gagal membagikan artikel");
    }
  };

  if (loading) return <div className="h-40 animate-pulse rounded-3xl bg-muted" />;

  if (initialSlug && !selected) {
    return (
      <div className="rounded-3xl border border-dashed border-border p-8 text-center">
        <p className="font-bold">Artikel tidak ditemukan.</p>
        <Link to="/blog" className="mt-3 inline-flex items-center gap-2 text-sm font-bold text-primary"><ArrowLeft className="h-4 w-4" /> Kembali ke Blog</Link>
      </div>
    );
  }

  if (selected) {
    const date = selected.published_at || selected.created_at;
    return (
      <article data-testid="blog-detail" className="rounded-3xl border border-border bg-card p-5 sm:p-8">
        <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
          <Link to="/blog" className="flex items-center gap-2 text-sm font-bold text-primary"><ArrowLeft className="h-4 w-4" /> Kembali ke Blog</Link>
          <button onClick={() => shareArticle(selected)} className="flex items-center gap-2 rounded-xl border border-border px-4 py-2.5 text-xs font-bold transition hover:border-primary hover:text-primary">
            <Share2 className="h-4 w-4" /> Bagikan artikel
          </button>
        </div>
        {selected.cover_url && <img src={selected.cover_url} alt={selected.title} className="max-h-[440px] w-full rounded-2xl border border-border bg-muted object-cover" />}
        <div className="mt-6 flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
          <span className="flex items-center gap-2"><CalendarDays className="h-3.5 w-3.5" />{date ? new Date(date).toLocaleDateString("id-ID", { dateStyle: "long" }) : ""}</span>
          <span className="flex items-center gap-2"><Link2 className="h-3.5 w-3.5" /> /blog/{selected.slug}</span>
        </div>
        <h1 className="mt-3 text-3xl font-extrabold sm:text-4xl">{selected.title}</h1>
        {selected.excerpt && <p className="mt-3 text-base leading-7 text-muted-foreground">{selected.excerpt}</p>}
        <AnnouncementContent body={selected.body} className="mt-7 text-[15px] leading-7" />
        <div className="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-5">
          <p className="text-xs text-muted-foreground">Bagikan link artikel ini agar bisa dibuka langsung.</p>
          <button onClick={() => shareArticle(selected)} className="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground"><Share2 className="h-4 w-4" /> Share</button>
        </div>
        <div className="mt-8">
          <BlogComments slug={selected.slug} />
        </div>
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
          <article key={post.id} className="group overflow-hidden rounded-3xl border border-border bg-card transition hover:-translate-y-0.5 hover:border-primary/50">
            <Link to={`/blog/${post.slug}`} className="block">
              {post.cover_url && <img src={post.cover_url} alt={post.title} className="h-48 w-full border-b border-border bg-muted object-cover" />}
              <div className="p-5">
                <p className="text-[11px] font-bold uppercase tracking-wider text-primary">Artikel</p>
                <h3 className="mt-2 text-xl font-extrabold group-hover:text-primary">{post.title}</h3>
                {post.excerpt && <p className="mt-2 line-clamp-3 text-sm leading-6 text-muted-foreground">{post.excerpt}</p>}
                <p className="mt-4 text-xs font-bold text-primary">Baca artikel →</p>
              </div>
            </Link>
            <div className="border-t border-border px-5 py-3">
              <button onClick={() => shareArticle(post)} className="flex items-center gap-2 text-xs font-bold text-muted-foreground hover:text-primary"><Share2 className="h-3.5 w-3.5" /> Bagikan link</button>
            </div>
          </article>
        ))}
      </div>
      {items.length === 0 && <div className="rounded-3xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">Belum ada artikel yang dipublikasikan.</div>}
    </div>
  );
};
