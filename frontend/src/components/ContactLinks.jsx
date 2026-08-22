import React from "react";
import { ExternalLink, Globe2, Mail, Phone } from "lucide-react";

const stripAt = (value = "") => String(value || "").trim().replace(/^@+/, "");

const withProtocol = (value = "") => {
  const text = String(value || "").trim();
  if (!text) return "";
  return /^https?:\/\//i.test(text) ? text : `https://${text}`;
};

const socialUrl = (kind, value) => {
  const username = stripAt(value);
  if (!username) return "";
  const maps = {
    telegram: `https://t.me/${username}`,
    instagram: `https://instagram.com/${username}`,
    tiktok: `https://tiktok.com/@${username}`,
    x: `https://x.com/${username}`,
    facebook: `https://facebook.com/${username}`,
    youtube: `https://youtube.com/@${username}`,
  };
  return maps[kind] || "";
};

const SOCIAL_META = {
  telegram: { label: "Telegram", mark: "TG" },
  instagram: { label: "Instagram", mark: "IG" },
  tiktok: { label: "TikTok", mark: "TT" },
  x: { label: "X", mark: "X" },
  facebook: { label: "Facebook", mark: "f" },
  youtube: { label: "YouTube", mark: "YT" },
};

export const getVisibleContacts = (contact = {}) => {
  const items = [];
  const website = String(contact.website || "").trim();
  const phone = String(contact.phone || "").trim();
  const supportEmail = String(contact.support_email || "").trim();

  if (contact.website_enabled && website) {
    items.push({ key: "website", label: "Website", value: website, href: withProtocol(website), Icon: Globe2 });
  }
  if (contact.phone_enabled && phone) {
    items.push({ key: "phone", label: "Nomor HP", value: phone, href: `tel:${phone.replace(/[^+\d]/g, "")}`, Icon: Phone });
  }
  if (contact.support_email_enabled && supportEmail) {
    items.push({ key: "support_email", label: "Email Support", value: supportEmail, href: `mailto:${supportEmail}`, Icon: Mail });
  }

  Object.entries(SOCIAL_META).forEach(([key, meta]) => {
    const username = stripAt(contact[key]);
    if (contact[`${key}_enabled`] && username) {
      items.push({ key, label: meta.label, value: `@${username}`, href: socialUrl(key, username), mark: meta.mark });
    }
  });

  return items;
};

export const ContactLinks = ({ contact = {}, compact = false, className = "" }) => {
  const items = getVisibleContacts(contact);
  if (!items.length) return null;

  return (
    <div className={`${compact ? "space-y-2" : "grid gap-3 sm:grid-cols-2 lg:grid-cols-3"} ${className}`.trim()}>
      {items.map((item) => {
        const Icon = item.Icon;
        const external = /^https?:\/\//i.test(item.href);
        return (
          <a
            key={item.key}
            href={item.href}
            target={external ? "_blank" : undefined}
            rel={external ? "noreferrer noopener" : undefined}
            className={`group flex items-center gap-3 rounded-2xl border border-border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5 ${compact ? "px-3 py-2.5" : "p-4"}`}
          >
            <span className={`${compact ? "h-9 w-9 rounded-xl" : "h-11 w-11 rounded-2xl"} grid shrink-0 place-items-center border border-primary/20 bg-primary/10 font-black text-primary`}>
              {Icon ? <Icon className={compact ? "h-4 w-4" : "h-5 w-5"} /> : <span className={item.key === "facebook" ? "text-lg lowercase" : "text-[11px] tracking-tight"}>{item.mark}</span>}
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-[10px] font-bold uppercase tracking-[0.16em] text-muted-foreground">{item.label}</span>
              <span className="mt-0.5 block truncate text-sm font-bold">{item.value}</span>
            </span>
            {external && <ExternalLink className="h-4 w-4 shrink-0 text-muted-foreground transition-transform group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-primary" />}
          </a>
        );
      })}
    </div>
  );
};
