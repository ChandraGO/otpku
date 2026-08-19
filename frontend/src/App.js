import React from "react";
import "@/App.css";
import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { Toaster } from "sonner";
import { ThemeProvider } from "@/theme/ThemeProvider";
import { I18nProvider } from "@/lib/i18n";
import { AuthProvider, useAuth } from "@/context/AuthContext";
import { SiteProvider } from "@/context/SiteContext";
import { Navbar } from "@/components/Navbar";
import Landing from "@/pages/Landing";
import Docs from "@/pages/Docs";
import Catalog from "@/pages/Catalog";
import Faq from "@/pages/Faq";
import Auth from "@/pages/Auth";
import Dashboard from "@/pages/Dashboard";
import Admin from "@/pages/Admin";

const Guard = ({ children, adminOnly }) => {
  const { user, loading } = useAuth();
  if (loading) return <div className="py-24 text-center text-sm text-muted-foreground">Memuat…</div>;
  if (!user) return <Navigate to="/masuk" replace />;
  if (adminOnly && user.role !== "admin") return <Navigate to="/dasbor" replace />;
  return children;
};

export default function App() {
  return (
    <ThemeProvider>
      <I18nProvider>
        <SiteProvider>
        <BrowserRouter>
          <AuthProvider>
            <div className="min-h-screen bg-background">
              <Navbar />
              <Routes>
                <Route path="/" element={<Landing />} />
                <Route path="/docs" element={<Docs />} />
                <Route path="/harga" element={<Catalog />} />
                <Route path="/faq" element={<Faq />} />
                <Route path="/masuk" element={<Auth mode="login" />} />
                <Route path="/daftar" element={<Auth mode="register" />} />
                <Route path="/dasbor" element={<Guard><Dashboard /></Guard>} />
                <Route path="/admin" element={<Guard adminOnly><Admin /></Guard>} />
                <Route path="*" element={<Navigate to="/" replace />} />
              </Routes>
            </div>
            <Toaster position="top-right" richColors />
          </AuthProvider>
        </BrowserRouter>
        </SiteProvider>
      </I18nProvider>
    </ThemeProvider>
  );
}
