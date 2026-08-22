import React, { useState } from "react";
import { useNavigate, Link } from "react-router-dom";
import { toast } from "sonner";
import { Loader2 } from "lucide-react";
import { http, errMsg } from "@/lib/api";
import { useAuth } from "@/context/AuthContext";
import { PasswordInput } from "@/components/ui/password-input";

const Field = ({ label, testid, type, ...rest }) => (
  <label className="block">
    <span className="text-xs font-bold uppercase tracking-wider text-muted-foreground">{label}</span>
    {type === "password" ? (
      <PasswordInput
        data-testid={testid}
        {...rest}
        className="mt-2"
        inputClassName="rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none transition-colors duration-200 focus:border-primary"
      />
    ) : (
      <input
        data-testid={testid}
        type={type}
        {...rest}
        className="mt-2 w-full rounded-2xl border border-input bg-background px-4 py-3 text-sm outline-none transition-colors duration-200 focus:border-primary"
      />
    )}
  </label>
);

export default function Auth({ mode }) {
  const isLogin = mode === "login";
  const nav = useNavigate();
  const { setUser } = useAuth();
  const [form, setForm] = useState({ name: "", email: "", password: "" });
  const [otp, setOtp] = useState("");
  const [stage, setStage] = useState("form");
  const [busy, setBusy] = useState(false);

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      if (isLogin) {
        const { data } = await http.post("/auth/login", { email: form.email, password: form.password });
        setUser(data.user);
        toast.success("Berhasil masuk");
        nav(data.user.role === "admin" ? "/admin" : "/dashboard");
      } else {
        const { data } = await http.post("/auth/register", form);
        if (data.needs_verification) { setStage("otp"); toast.success("Kode verifikasi dikirim ke email"); }
        else { setUser(data.user); nav("/dashboard"); }
      }
    } catch (err) { toast.error(errMsg(err)); }
    setBusy(false);
  };

  const verify = async (e) => {
    e.preventDefault();
    setBusy(true);
    try {
      const { data } = await http.post("/auth/verify-otp", { email: form.email, code: otp });
      setUser(data.user);
      toast.success("Email terverifikasi");
      nav("/dashboard");
    } catch (err) { toast.error(errMsg(err)); }
    setBusy(false);
  };

  const resend = async () => {
    try { await http.post("/auth/resend-otp", { email: form.email }); toast.success("Kode dikirim ulang"); }
    catch (err) { toast.error(errMsg(err)); }
  };

  return (
    <div data-testid="auth-page" className="relative mx-auto flex max-w-md flex-col px-6 py-16">
      <div className="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-primary/25 blur-[110px]" />
      <div className="relative rounded-3xl border border-border bg-card p-8 brand-glow">
        {stage === "form" ? (
          <>
            <h1 className="text-3xl font-extrabold">{isLogin ? "Masuk" : "Buat akun"}</h1>
            <p className="mt-2 text-sm text-muted-foreground">
              {isLogin ? "Kelola pesanan, saldo, dan API key kamu." : "Verifikasi email lewat kode OTP untuk mulai."}
            </p>
            <form onSubmit={submit} className="mt-7 space-y-4">
              {!isLogin && <Field label="Nama" testid="auth-name" value={form.name} onChange={set("name")} required />}
              <Field label="Email" testid="auth-email" type="email" value={form.email} onChange={set("email")} required />
              <Field label="Kata sandi" testid="auth-password" type="password" value={form.password} onChange={set("password")} required minLength={6} />
              <button
                data-testid="auth-submit"
                disabled={busy}
                className="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground transition-transform duration-200 hover:scale-[1.02] disabled:opacity-60"
              >
                {busy && <Loader2 className="h-4 w-4 animate-spin" />}
                {isLogin ? "Masuk" : "Daftar"}
              </button>
            </form>
            <p className="mt-6 text-center text-sm text-muted-foreground">
              {isLogin ? "Belum punya akun? " : "Sudah punya akun? "}
              <Link data-testid="auth-switch" to={isLogin ? "/daftar" : "/masuk"} className="font-bold text-primary">
                {isLogin ? "Daftar" : "Masuk"}
              </Link>
            </p>
          </>
        ) : (
          <>
            <h1 className="text-3xl font-extrabold">Verifikasi email</h1>
            <p className="mt-2 text-sm text-muted-foreground">Masukkan kode yang dikirim ke {form.email}.</p>
            <form onSubmit={verify} className="mt-7 space-y-4">
              <Field label="Kode OTP" testid="auth-otp" value={otp} onChange={(e) => setOtp(e.target.value)} required />
              <button data-testid="auth-verify" disabled={busy} className="w-full rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground disabled:opacity-60">
                Verifikasi
              </button>
            </form>
            <button data-testid="auth-resend" onClick={resend} className="mt-4 w-full text-sm font-bold text-primary">Kirim ulang kode</button>
          </>
        )}
      </div>
    </div>
  );
}
