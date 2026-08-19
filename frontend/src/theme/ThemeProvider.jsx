import React, { createContext, useContext, useEffect, useState, useCallback } from "react";

export const ACCENTS = {
  ungu: { label: "Ungu", dot: "#8b5cf6", dark: "263 85% 66%", light: "263 70% 55%" },
  biru: { label: "Biru", dot: "#3b82f6", dark: "217 91% 60%", light: "217 88% 52%" },
  hijau: { label: "Hijau", dot: "#22c55e", dark: "142 71% 45%", light: "142 65% 38%" },
  merah: { label: "Merah", dot: "#ef4444", dark: "0 84% 60%", light: "0 74% 48%" },
};

function relLuminance(r, g, b) {
  const f = (c) => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}

function hslToRgb(h, s, l) {
  s /= 100; l /= 100;
  const k = (n) => (n + h / 30) % 12;
  const a = s * Math.min(l, 1 - l);
  const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, Math.min(9 - k(n), 1)));
  return [f(0), f(8), f(4)];
}

function contrast(l1, l2) {
  const [a, b] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (a + 0.05) / (b + 0.05);
}

function pickForeground(hsl) {
  const [h, s, l] = hsl.split(" ").map((v) => Number(v.replace("%", "")));
  const [r, g, b] = hslToRgb(h, s, l);
  const bg = relLuminance(r, g, b);
  const white = relLuminance(1, 1, 1);
  const dark = relLuminance(...hslToRgb(222, 47, 10));
  return contrast(bg, white) >= contrast(bg, dark) ? "0 0% 100%" : "222 47% 10%";
}

const Ctx = createContext(null);

export function ThemeProvider({ children }) {
  const [mode, setMode] = useState(() => localStorage.getItem("dot_mode") || "dark");
  const [accent, setAccent] = useState(() => {
    const a = localStorage.getItem("dot_accent");
    return ACCENTS[a] ? a : "biru";
  });

  const apply = useCallback(() => {
    const root = document.documentElement;
    root.classList.toggle("dark", mode === "dark");
    const hsl = (ACCENTS[accent] || ACCENTS.biru)[mode === "dark" ? "dark" : "light"];
    root.style.setProperty("--primary", hsl);
    root.style.setProperty("--ring", hsl);
    root.style.setProperty("--primary-foreground", pickForeground(hsl));
  }, [mode, accent]);

  useEffect(() => {
    apply();
    localStorage.setItem("dot_mode", mode);
    localStorage.setItem("dot_accent", accent);
  }, [apply, mode, accent]);

  return (
    <Ctx.Provider value={{ mode, setMode, accent, setAccent }}>
      {children}
    </Ctx.Provider>
  );
}

export const useTheme = () => useContext(Ctx);
