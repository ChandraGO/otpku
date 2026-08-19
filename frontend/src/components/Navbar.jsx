import React, { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Home, BookOpen, Tag, HelpCircle, LayoutDashboard, ShieldCheck, LogOut, Menu, X } from "lucide-react";
import { ThemeSwitcher } from "@/components/ThemeSwitcher";
import { useAuth } from "@/context/AuthContext";
import { useI18n } from "@/lib/i18n";

const Brand = () => (
  <Link to="/" data-testid="brand-logo" className="flex items-center gap-3">
    <span className="grid h-11 w-11 place-items-center rounded-xl bg-primary text-lg font-extrabold text-primary-foreground brand-glow">
      d
    </span>
    <span className="display text-xl font-extrabold">
      dapet<span className="text-primary">OTP</span>
    </span>
  </Link>
);

export const Navbar = () => {
  const { t } = useI18n();
  const { user, logout } = useAuth();
  const loc = useLocation();
  const nav = useNavigate();
  const [open, setOpen] = useState(false);
  const appShell = loc.pathname.startsWith("/dasbor") || loc.pathname.startsWith("/admin");

  const links = [
    { to: "/", label: t("home"), icon: Home },
    { to: "/docs", label: t("docs"), icon: BookOpen },
    { to: "/harga", label: t("pricing"), icon: Tag },
    { to: "/faq", label: t("faq"), icon: HelpCircle },
  ];

  return (
    <header className={`sticky top-0 z-[60] border-b border-border/70 glass ${appShell ? "hidden lg:block" : ""}`}>
      <div className="mx-auto flex max-w-7xl items-center gap-4 px-5 py-3">
        <Brand />
        <nav className="ml-6 hidden items-center gap-1 lg:flex">
          {links.map(({ to, label, icon: Icon }) => {
            const active = loc.pathname === to;
            return (
              <Link
                key={to}
                to={to}
                data-testid={`nav-${to === "/" ? "home" : to.slice(1)}`}
                className={`flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors duration-200 ${
                  active ? "bg-primary/15 text-primary" : "text-muted-foreground hover:bg-accent hover:text-foreground"
                }`}
              >
                <Icon className="h-4 w-4" />
                {label}
              </Link>
            );
          })}
        </nav>

        <div className="ml-auto flex items-center gap-2">
          {user ? (
            <>
              <Link
                to="/dasbor"
                data-testid="nav-dashboard"
                className="hidden items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:flex"
              >
                <LayoutDashboard className="h-4 w-4" /> {t("dashboard")}
              </Link>
              {user.role === "admin" && (
                <Link
                  to="/admin"
                  data-testid="nav-admin"
                  className="hidden items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-primary transition-colors hover:bg-primary/15 sm:flex"
                >
                  <ShieldCheck className="h-4 w-4" /> {t("admin")}
                </Link>
              )}
              <button
                data-testid="nav-logout"
                onClick={async () => { await logout(); nav("/"); }}
                className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-muted-foreground hover:text-destructive sm:flex"
              >
                <LogOut className="h-4 w-4" />
              </button>
            </>
          ) : (
            <Link
              to="/masuk"
              data-testid="nav-login"
              className="hidden rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground transition-transform duration-200 hover:scale-[1.03] sm:block"
            >
              {t("login")}
            </Link>
          )}
          <ThemeSwitcher />
          <button data-testid="nav-mobile-toggle" className="grid h-11 w-11 place-items-center rounded-xl bg-accent lg:hidden" onClick={() => setOpen((o) => !o)}>
            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </button>
        </div>
      </div>

      {open && (
        <div className="border-t border-border px-5 py-3 lg:hidden" data-testid="nav-mobile-menu">
          {[...links, ...(user ? [{ to: "/dasbor", label: t("dashboard"), icon: LayoutDashboard }] : [{ to: "/masuk", label: t("login"), icon: LayoutDashboard }]),
            ...(user?.role === "admin" ? [{ to: "/admin", label: t("admin"), icon: ShieldCheck }] : [])].map(({ to, label, icon: Icon }) => (
            <Link key={to} to={to} onClick={() => setOpen(false)} className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-muted-foreground hover:bg-accent hover:text-foreground">
              <Icon className="h-4 w-4" /> {label}
            </Link>
          ))}
        </div>
      )}
    </header>
  );
};
