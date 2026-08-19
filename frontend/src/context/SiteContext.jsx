import React, { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import { http } from "@/lib/api";

const DEFAULT_SITE = {
  site_name: "dapetOTP",
  tagline: "Nomor virtual & OTP instan untuk semua layanan",
  business_email: "support@dapetotp.com",
  favicon_url: "",
  share_thumbnail_url: "",
  meta_title: "dapetOTP — Sewa Nomor Virtual & OTP Instan",
  meta_description: "Beli nomor virtual untuk verifikasi OTP ratusan layanan. Saldo fleksibel, API publik, dan dukungan 24/7.",
  meta_keywords: "otp, nomor virtual, sms virtual, verifikasi",
};

const SiteContext = createContext({
  site: DEFAULT_SITE,
  topup: null,
  loading: true,
  refreshSite: async () => {},
  applySite: () => {},
});

function ensureMeta(selector, attrs) {
  let el = document.head.querySelector(selector);
  if (!el) {
    el = document.createElement("meta");
    Object.entries(attrs).forEach(([k, v]) => el.setAttribute(k, v));
    document.head.appendChild(el);
  }
  return el;
}

function applyDocumentBranding(site = DEFAULT_SITE) {
  const name = String(site.site_name || DEFAULT_SITE.site_name).trim();
  const title = String(site.meta_title || name || DEFAULT_SITE.meta_title).trim();
  const description = String(site.meta_description || "").trim();
  const keywords = String(site.meta_keywords || "").trim();
  const image = String(site.share_thumbnail_url || "").trim();
  const favicon = String(site.favicon_url || "").trim();

  document.title = title;

  const descEl = ensureMeta('meta[name="description"]', { name: "description" });
  descEl.setAttribute("content", description);

  const keyEl = ensureMeta('meta[name="keywords"]', { name: "keywords" });
  keyEl.setAttribute("content", keywords);

  const ogTitle = ensureMeta('meta[property="og:title"]', { property: "og:title" });
  ogTitle.setAttribute("content", title);
  const ogDesc = ensureMeta('meta[property="og:description"]', { property: "og:description" });
  ogDesc.setAttribute("content", description);
  const ogType = ensureMeta('meta[property="og:type"]', { property: "og:type" });
  ogType.setAttribute("content", "website");

  const twitterCard = ensureMeta('meta[name="twitter:card"]', { name: "twitter:card" });
  twitterCard.setAttribute("content", image ? "summary_large_image" : "summary");
  const twitterTitle = ensureMeta('meta[name="twitter:title"]', { name: "twitter:title" });
  twitterTitle.setAttribute("content", title);
  const twitterDesc = ensureMeta('meta[name="twitter:description"]', { name: "twitter:description" });
  twitterDesc.setAttribute("content", description);

  let ogImage = document.head.querySelector('meta[property="og:image"]');
  let twitterImage = document.head.querySelector('meta[name="twitter:image"]');
  if (image) {
    if (!ogImage) {
      ogImage = document.createElement("meta");
      ogImage.setAttribute("property", "og:image");
      document.head.appendChild(ogImage);
    }
    ogImage.setAttribute("content", image);
    if (!twitterImage) {
      twitterImage = document.createElement("meta");
      twitterImage.setAttribute("name", "twitter:image");
      document.head.appendChild(twitterImage);
    }
    twitterImage.setAttribute("content", image);
  } else {
    ogImage?.remove();
    twitterImage?.remove();
  }

  let icon = document.head.querySelector('link[rel~="icon"]');
  if (!icon) {
    icon = document.createElement("link");
    icon.setAttribute("rel", "icon");
    document.head.appendChild(icon);
  }
  icon.setAttribute("href", favicon || "/favicon.svg");
}

export function SiteProvider({ children }) {
  const [site, setSite] = useState(DEFAULT_SITE);
  const [topup, setTopup] = useState(null);
  const [loading, setLoading] = useState(true);

  const applySite = useCallback((nextSite) => {
    const merged = { ...DEFAULT_SITE, ...(nextSite || {}) };
    setSite(merged);
    applyDocumentBranding(merged);
    return merged;
  }, []);

  const refreshSite = useCallback(async () => {
    try {
      const { data } = await http.get("/public/settings", {
        headers: { "Cache-Control": "no-cache" },
        params: { _ts: Date.now() },
      });
      applySite(data?.site || {});
      setTopup(data?.topup || null);
      return data;
    } catch (_) {
      applyDocumentBranding(site);
      return null;
    } finally {
      setLoading(false);
    }
  }, [applySite, site]);

  useEffect(() => {
    applyDocumentBranding(DEFAULT_SITE);
    refreshSite();
    // Refresh saat tab kembali aktif supaya perubahan branding dari sesi admin lain ikut terbaca.
    const onFocus = () => refreshSite();
    window.addEventListener("focus", onFocus);
    return () => window.removeEventListener("focus", onFocus);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const value = useMemo(() => ({ site, topup, loading, refreshSite, applySite }), [site, topup, loading, refreshSite, applySite]);
  return <SiteContext.Provider value={value}>{children}</SiteContext.Provider>;
}

export const useSite = () => useContext(SiteContext);
