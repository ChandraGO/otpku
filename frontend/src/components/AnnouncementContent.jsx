import React from "react";

const INLINE = /(\*\*[^*\n]+\*\*|\+\+[^+\n]+\+\+|~~[^~\n]+~~|\|\|[^|\n]+\|\||`[^`\n]+`|\[[^\]\n]+\]\(https?:\/\/[^\s)]+\)|\*[^*\n]+\*)/g;

const inlineNodes = (text, keyPrefix = "t") => {
  const input = String(text || "");
  const nodes = [];
  let last = 0;
  let idx = 0;

  for (const match of input.matchAll(INLINE)) {
    const start = match.index ?? 0;
    if (start > last) nodes.push(input.slice(last, start));
    const token = match[0];
    const key = `${keyPrefix}-${idx++}`;

    if (token.startsWith("**")) nodes.push(<strong key={key}>{token.slice(2, -2)}</strong>);
    else if (token.startsWith("++")) nodes.push(<u key={key}>{token.slice(2, -2)}</u>);
    else if (token.startsWith("~~")) nodes.push(<s key={key}>{token.slice(2, -2)}</s>);
    else if (token.startsWith("||")) nodes.push(<span key={key} className="announcement-spoiler" title="Arahkan kursor / tap untuk membuka spoiler">{token.slice(2, -2)}</span>);
    else if (token.startsWith("`")) nodes.push(<code key={key} className="rounded-md bg-muted px-1.5 py-0.5 text-[.9em]">{token.slice(1, -1)}</code>);
    else if (token.startsWith("[")) {
      const m = token.match(/^\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)$/);
      nodes.push(m ? <a key={key} href={m[2]} target="_blank" rel="noreferrer" className="font-semibold text-primary underline underline-offset-2">{m[1]}</a> : token);
    } else if (token.startsWith("*")) nodes.push(<em key={key}>{token.slice(1, -1)}</em>);
    else nodes.push(token);

    last = start + token.length;
  }
  if (last < input.length) nodes.push(input.slice(last));
  return nodes;
};

export const AnnouncementContent = ({ body = "", imageUrl = "", imageCaption = "", className = "" }) => {
  const lines = String(body || "").split(/\r?\n/);
  const safeImage = /^(https?:\/\/|data:image\/)/i.test(String(imageUrl || "")) ? imageUrl : "";

  return (
    <div className={className}>
      {lines.length > 0 && (
        <div className="space-y-1.5 text-sm leading-6 text-muted-foreground">
          {lines.map((line, i) => {
            if (!line.trim()) return <div key={`blank-${i}`} className="h-2" />;
            if (/^>\s?/.test(line)) {
              return <blockquote key={`q-${i}`} className="border-l-2 border-primary/60 bg-primary/5 px-3 py-2 text-foreground/85">{inlineNodes(line.replace(/^>\s?/, ""), `q${i}`)}</blockquote>;
            }
            if (/^-\s+/.test(line)) {
              return <div key={`b-${i}`} className="flex gap-2"><span className="mt-[1px] font-bold text-primary">•</span><span>{inlineNodes(line.replace(/^-\s+/, ""), `b${i}`)}</span></div>;
            }
            const numbered = line.match(/^(\d+)\.\s+(.*)$/);
            if (numbered) {
              return <div key={`n-${i}`} className="flex gap-2"><span className="mono min-w-5 text-xs font-bold text-primary">{numbered[1]}.</span><span>{inlineNodes(numbered[2], `n${i}`)}</span></div>;
            }
            return <p key={`p-${i}`} className="whitespace-pre-wrap">{inlineNodes(line, `p${i}`)}</p>;
          })}
        </div>
      )}

      {safeImage && (
        <figure className="mt-4 overflow-hidden rounded-2xl border border-border bg-muted/30">
          <img src={safeImage} alt={imageCaption || "Gambar changelog"} loading="lazy" className="max-h-[520px] w-full object-contain" />
          {imageCaption && <figcaption className="border-t border-border px-4 py-2.5 text-xs text-muted-foreground">{imageCaption}</figcaption>}
        </figure>
      )}
    </div>
  );
};
