import React from "react";
import { LayoutGrid, Check } from "lucide-react";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ACCENTS, useTheme } from "@/theme/ThemeProvider";
import { useI18n } from "@/lib/i18n";

export const ThemeSwitcher = () => {
  const { mode, setMode, accent, setAccent } = useTheme();
  const { t, lang, setLang } = useI18n();

  return (
    <Popover>
      <PopoverTrigger asChild>
        <button
          data-testid="theme-switcher-trigger"
          aria-label={t("theme")}
          className="grid h-11 w-11 place-items-center rounded-xl bg-primary text-primary-foreground transition-transform duration-200 hover:scale-105 active:scale-95"
        >
          <LayoutGrid className="h-5 w-5" />
        </button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-64 rounded-2xl border-border bg-popover p-3 shadow-2xl">
        <div className="mb-3 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
          {["dark", "light"].map((m) => (
            <button
              key={m}
              data-testid={`theme-mode-${m}`}
              onClick={() => setMode(m)}
              className={`rounded-lg px-3 py-2 text-sm font-semibold transition-colors duration-200 ${
                mode === m ? "bg-card text-primary shadow" : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {m === "dark" ? t("dark") : t("light")}
            </button>
          ))}
        </div>

        <div className="space-y-1">
          {Object.entries(ACCENTS).map(([key, a]) => (
            <button
              key={key}
              data-testid={`theme-accent-${key}`}
              onClick={() => setAccent(key)}
              className={`flex w-full items-center justify-between rounded-xl px-3 py-2 text-sm font-semibold transition-colors duration-200 ${
                accent === key ? "bg-accent text-foreground ring-1 ring-primary" : "text-muted-foreground hover:bg-accent hover:text-foreground"
              }`}
            >
              <span>{a.label}</span>
              <span className="h-4 w-4 rounded-full" style={{ background: a.dot }} />
            </button>
          ))}
        </div>

        <div className="mt-3 grid grid-cols-2 gap-1 rounded-xl bg-muted p-1">
          {["id", "en"].map((l) => (
            <button
              key={l}
              data-testid={`lang-${l}`}
              onClick={() => setLang(l)}
              className={`flex items-center justify-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold transition-colors ${
                lang === l ? "bg-card text-primary shadow" : "text-muted-foreground hover:text-foreground"
              }`}
            >
              {lang === l && <Check className="h-3 w-3" />} {l.toUpperCase()}
            </button>
          ))}
        </div>
      </PopoverContent>
    </Popover>
  );
};
