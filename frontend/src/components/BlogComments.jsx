import React, { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { Loader2, LogIn, MessageCircle, Reply, Send } from "lucide-react";
import { toast } from "sonner";
import { useAuth } from "@/context/AuthContext";
import { errMsg, http } from "@/lib/api";

const formatDate = (value) => {
  if (!value) return "";
  try {
    return new Date(value).toLocaleString("id-ID", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  } catch {
    return "";
  }
};

const initials = (name) => {
  const parts = String(name || "U").trim().split(/\s+/).filter(Boolean);
  return parts.slice(0, 2).map((part) => part[0]?.toUpperCase()).join("") || "U";
};

function CommentNode({ comment, user, loginTo, replyingTo, setReplyingTo, replyDraft, setReplyDraft, busy, onSubmit }) {
  const isReplying = replyingTo === comment.id;

  return (
    <div className="rounded-2xl border border-border bg-background/60 p-4 sm:p-5">
      <div className="flex items-start gap-3">
        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-primary/15 text-xs font-extrabold text-primary">
          {initials(comment.author_name)}
        </div>
        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            <p className="font-bold">{comment.author_name || "Pengguna"}</p>
            {comment.author_role === "admin" && (
              <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-primary">Admin</span>
            )}
            <span className="text-xs text-muted-foreground">{formatDate(comment.created_at)}</span>
          </div>
          <p className="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-foreground/90">{comment.body}</p>
          <div className="mt-3">
            {user ? (
              <button
                type="button"
                onClick={() => {
                  setReplyingTo(isReplying ? null : comment.id);
                  setReplyDraft("");
                }}
                className="inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground transition hover:text-primary"
              >
                <Reply className="h-3.5 w-3.5" /> {isReplying ? "Batal membalas" : "Balas"}
              </button>
            ) : (
              <Link to={loginTo} className="inline-flex items-center gap-1.5 text-xs font-bold text-muted-foreground transition hover:text-primary">
                <LogIn className="h-3.5 w-3.5" /> Masuk untuk membalas
              </Link>
            )}
          </div>

          {isReplying && user && (
            <form
              className="mt-4 rounded-2xl border border-border bg-card p-3"
              onSubmit={(e) => {
                e.preventDefault();
                onSubmit(replyDraft, comment.id, () => {
                  setReplyDraft("");
                  setReplyingTo(null);
                });
              }}
            >
              <p className="mb-2 text-xs text-muted-foreground">Membalas <span className="font-bold text-foreground">{comment.author_name}</span></p>
              <textarea
                autoFocus
                rows={3}
                maxLength={2000}
                value={replyDraft}
                onChange={(e) => setReplyDraft(e.target.value)}
                placeholder="Tulis balasan…"
                className="w-full resize-y rounded-xl border border-input bg-background px-3 py-2.5 text-sm outline-none transition focus:border-primary"
              />
              <div className="mt-2 flex items-center justify-between gap-3">
                <span className="text-[11px] text-muted-foreground">{replyDraft.length}/2000</span>
                <button
                  disabled={busy || !replyDraft.trim()}
                  className="inline-flex items-center gap-2 rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
                >
                  {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Send className="h-3.5 w-3.5" />}
                  Kirim balasan
                </button>
              </div>
            </form>
          )}
        </div>
      </div>

      {comment.children?.length > 0 && (
        <div className="ml-3 mt-4 space-y-3 border-l border-border pl-3 sm:ml-5 sm:pl-5">
          {comment.children.map((child) => (
            <CommentNode
              key={child.id}
              comment={child}
              user={user}
              loginTo={loginTo}
              replyingTo={replyingTo}
              setReplyingTo={setReplyingTo}
              replyDraft={replyDraft}
              setReplyDraft={setReplyDraft}
              busy={busy}
              onSubmit={onSubmit}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function BlogComments({ slug }) {
  const { user, loading: authLoading } = useAuth();
  const [comments, setComments] = useState([]);
  const [loading, setLoading] = useState(true);
  const [draft, setDraft] = useState("");
  const [replyDraft, setReplyDraft] = useState("");
  const [replyingTo, setReplyingTo] = useState(null);
  const [busy, setBusy] = useState(false);

  const returnTo = `/blog/${encodeURIComponent(slug)}#komentar`;
  const loginTo = `/masuk?next=${encodeURIComponent(returnTo)}`;
  const registerTo = `/daftar?next=${encodeURIComponent(returnTo)}`;

  useEffect(() => {
    let alive = true;
    setLoading(true);
    http.get(`/blog/${encodeURIComponent(slug)}/comments`)
      .then(({ data }) => alive && setComments(data?.items || []))
      .catch((err) => alive && toast.error(errMsg(err)))
      .finally(() => alive && setLoading(false));
    return () => { alive = false; };
  }, [slug]);

  useEffect(() => {
    if (!loading && window.location.hash === "#komentar") {
      window.setTimeout(() => document.getElementById("komentar")?.scrollIntoView({ behavior: "smooth", block: "start" }), 80);
    }
  }, [loading]);

  const tree = useMemo(() => {
    const map = new Map(comments.map((item) => [item.id, { ...item, children: [] }]));
    const roots = [];
    for (const item of map.values()) {
      if (item.parent_id && map.has(item.parent_id)) map.get(item.parent_id).children.push(item);
      else roots.push(item);
    }
    return roots;
  }, [comments]);

  const submitComment = async (text, parentId = null, done) => {
    const value = String(text || "").trim();
    if (!user || !value || busy) return;
    setBusy(true);
    try {
      const { data } = await http.post(`/blog/${encodeURIComponent(slug)}/comments`, {
        body: value,
        parent_id: parentId || null,
      });
      setComments((prev) => [...prev, data]);
      done?.();
      toast.success(parentId ? "Balasan terkirim" : "Komentar terkirim");
    } catch (err) {
      toast.error(errMsg(err));
    } finally {
      setBusy(false);
    }
  };

  return (
    <section id="komentar" className="scroll-mt-28 border-t border-border pt-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <div className="flex items-center gap-2 text-primary">
            <MessageCircle className="h-5 w-5" />
            <span className="text-xs font-extrabold uppercase tracking-[0.16em]">Diskusi</span>
          </div>
          <h2 className="mt-2 text-2xl font-extrabold">Komentar <span className="text-muted-foreground">({comments.length})</span></h2>
          <p className="mt-1 text-sm text-muted-foreground">Masuk untuk ikut berdiskusi atau membalas komentar pengguna lain.</p>
        </div>
        {!authLoading && !user && (
          <div className="flex items-center gap-2">
            <Link to={loginTo} className="rounded-xl border border-border px-3.5 py-2 text-xs font-bold transition hover:border-primary hover:text-primary">Masuk</Link>
            <Link to={registerTo} className="rounded-xl bg-primary px-3.5 py-2 text-xs font-bold text-primary-foreground">Daftar</Link>
          </div>
        )}
      </div>

      {authLoading ? (
        <div className="mt-5 h-24 animate-pulse rounded-2xl bg-muted" />
      ) : user ? (
        <form
          className="mt-5 rounded-2xl border border-border bg-background/60 p-4"
          onSubmit={(e) => {
            e.preventDefault();
            submitComment(draft, null, () => setDraft(""));
          }}
        >
          <div className="flex items-center gap-3">
            <div className="grid h-9 w-9 place-items-center rounded-full bg-primary/15 text-xs font-extrabold text-primary">{initials(user.name)}</div>
            <div>
              <p className="text-sm font-bold">{user.name || "Pengguna"}</p>
              <p className="text-[11px] text-muted-foreground">Komentar akan tampil menggunakan nama akun Anda.</p>
            </div>
          </div>
          <textarea
            rows={4}
            maxLength={2000}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            placeholder="Tulis komentar…"
            className="mt-4 w-full resize-y rounded-xl border border-input bg-background px-3.5 py-3 text-sm outline-none transition focus:border-primary"
          />
          <div className="mt-2 flex items-center justify-between gap-3">
            <span className="text-[11px] text-muted-foreground">{draft.length}/2000</span>
            <button
              disabled={busy || !draft.trim()}
              className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-50"
            >
              {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
              Kirim komentar
            </button>
          </div>
        </form>
      ) : (
        <div className="mt-5 rounded-2xl border border-dashed border-border bg-muted/30 p-5 text-center">
          <p className="text-sm font-bold">Ingin ikut berkomentar?</p>
          <p className="mt-1 text-xs text-muted-foreground">Komentar dan balasan hanya tersedia untuk pengguna yang sudah login.</p>
          <div className="mt-4 flex justify-center gap-2">
            <Link to={loginTo} className="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground"><LogIn className="h-4 w-4" /> Masuk</Link>
            <Link to={registerTo} className="rounded-xl border border-border px-4 py-2.5 text-xs font-bold">Daftar akun</Link>
          </div>
        </div>
      )}

      <div className="mt-7">
        {loading ? (
          <div className="space-y-3">
            <div className="h-28 animate-pulse rounded-2xl bg-muted" />
            <div className="h-24 animate-pulse rounded-2xl bg-muted" />
          </div>
        ) : tree.length ? (
          <div className="space-y-4">
            {tree.map((comment) => (
              <CommentNode
                key={comment.id}
                comment={comment}
                user={user}
                loginTo={loginTo}
                replyingTo={replyingTo}
                setReplyingTo={setReplyingTo}
                replyDraft={replyDraft}
                setReplyDraft={setReplyDraft}
                busy={busy}
                onSubmit={submitComment}
              />
            ))}
          </div>
        ) : (
          <div className="rounded-2xl border border-dashed border-border p-6 text-center text-sm text-muted-foreground">Belum ada komentar. Jadilah yang pertama berdiskusi.</div>
        )}
      </div>
    </section>
  );
}
