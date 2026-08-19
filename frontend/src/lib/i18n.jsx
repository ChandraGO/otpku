import React, { createContext, useContext, useEffect, useState } from "react";

const dict = {
  id: {
    home: "Home", docs: "Docs", pricing: "Harga", faq: "FAQ", dashboard: "Dasbor",
    login: "Masuk", register: "Daftar", logout: "Keluar", admin: "Admin",
    heroBadge: "REST API PUBLIK", getStarted: "Masuk & Mulai", seePricing: "Lihat Harga",
    balance: "Saldo", topup: "Isi Saldo", orders: "Pesanan", tickets: "Tiket & Bantuan",
    apikey: "API Key", services: "Layanan", theme: "Tema", language: "Bahasa",
    dark: "Gelap", light: "Terang", custom: "Custom warna",
  },
  en: {
    home: "Home", docs: "Docs", pricing: "Pricing", faq: "FAQ", dashboard: "Dashboard",
    login: "Sign in", register: "Sign up", logout: "Sign out", admin: "Admin",
    heroBadge: "PUBLIC REST API", getStarted: "Sign in & Start", seePricing: "See Pricing",
    balance: "Balance", topup: "Top Up", orders: "Orders", tickets: "Tickets & Support",
    apikey: "API Key", services: "Services", theme: "Theme", language: "Language",
    dark: "Dark", light: "Light", custom: "Custom color",
  },
};

const Ctx = createContext(null);

export function I18nProvider({ children }) {
  const [lang, setLang] = useState(() => localStorage.getItem("dot_lang") || "id");
  useEffect(() => localStorage.setItem("dot_lang", lang), [lang]);
  const t = (k) => dict[lang][k] ?? dict.id[k] ?? k;
  return <Ctx.Provider value={{ lang, setLang, t }}>{children}</Ctx.Provider>;
}

export const useI18n = () => useContext(Ctx);
