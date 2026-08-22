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

const BrandIcon = ({ kind, className = "h-5 w-5" }) => {
  const common = {
    className,
    viewBox: "0 0 24 24",
    fill: "none",
    xmlns: "http://www.w3.org/2000/svg",
    "aria-hidden": true,
  };

  if (kind === "youtube") {
    return (
      <svg {...common}>
        <path d="M21.25 7.15a2.9 2.9 0 0 0-2.04-2.05C17.42 4.6 12 4.6 12 4.6s-5.42 0-7.21.5A2.9 2.9 0 0 0 2.75 7.15C2.25 8.95 2.25 12 2.25 12s0 3.05.5 4.85a2.9 2.9 0 0 0 2.04 2.05c1.79.5 7.21.5 7.21.5s5.42 0 7.21-.5a2.9 2.9 0 0 0 2.04-2.05c.5-1.8.5-4.85.5-4.85s0-3.05-.5-4.85Z" fill="currentColor" />
        <path d="m10.2 15.2 5-3.2-5-3.2v6.4Z" fill="currentColor" className="text-background" />
      </svg>
    );
  }

  if (kind === "instagram") {
    return (
      <svg {...common} stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="5" />
        <circle cx="12" cy="12" r="4.1" />
        <circle cx="17.5" cy="6.6" r="1" fill="currentColor" stroke="none" />
      </svg>
    );
  }

  if (kind === "tiktok") {
    return (
      <svg {...common} fill="currentColor">
        <path d="M14.3 3.2c.55 2.4 1.9 3.85 4.5 4.02v2.7a8.16 8.16 0 0 1-4.48-1.35v6.02a5.67 5.67 0 1 1-4.9-5.62v2.8a2.91 2.91 0 1 0 2.08 2.8V3.2h2.8Z" />
      </svg>
    );
  }

  if (kind === "telegram") {
    return (
      <svg {...common} fill="currentColor">
        <path d="M21.6 3.6 18.5 19c-.23 1.09-.85 1.36-1.72.85l-4.72-3.48-2.28 2.2c-.25.25-.46.46-.95.46l.34-4.81 8.76-7.91c.38-.34-.08-.53-.59-.19L6.51 12.95l-4.67-1.46c-1.02-.32-1.04-1.02.21-1.51L20.3 2.94c.85-.31 1.59.2 1.3.66Z" />
      </svg>
    );
  }

  if (kind === "facebook") {
    return (
      <svg {...common} fill="currentColor">
        <path d="M13.6 21v-8h2.7l.4-3.15h-3.1V7.84c0-.91.25-1.53 1.56-1.53h1.67V3.5a22.2 22.2 0 0 0-2.44-.13c-2.42 0-4.08 1.48-4.08 4.2v2.28H7.57V13h2.74v8h3.29Z" />
      </svg>
    );
  }

  if (kind === "x") {
    return (
      <svg {...common} fill="currentColor">
        <path d="M4.2 3h4.55l4.13 5.52L17.7 3h2.1l-5.95 6.98L20.6 21h-4.55l-4.62-6.17L6.1 21H4l6.46-7.63L4.2 3Zm3.56 1.55 9.06 14.9h2.2L9.96 4.55h-2.2Z" />
      </svg>
    );
  }

  return null;
};

const SOCIAL_META = {
  telegram: { label: "Telegram" },
  instagram: { label: "Instagram" },
  tiktok: { label: "TikTok" },
  x: { label: "X" },
  facebook: { label: "Facebook" },
  youtube: { label: "YouTube" },
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
      items.push({ key, label: meta.label, value: `@${username}`, href: socialUrl(key, username), brand: key });
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
            aria-label={`${item.label}: ${item.value}`}
            className={`group flex items-center gap-3 rounded-2xl border border-border bg-card transition-all hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-lg hover:shadow-primary/5 ${compact ? "px-3 py-2.5" : "p-4"}`}
          >
            <span className={`${compact ? "h-9 w-9 rounded-xl" : "h-11 w-11 rounded-2xl"} grid shrink-0 place-items-center border border-primary/20 bg-primary/10 text-primary`}>
              {Icon ? (
                <Icon className={compact ? "h-4 w-4" : "h-5 w-5"} />
              ) : (
                <BrandIcon kind={item.brand} className={compact ? "h-4 w-4" : "h-5 w-5"} />
              )}
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
