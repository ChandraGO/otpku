import React, { useEffect, useState } from "react";
import { Copy, KeyRound } from "lucide-react";
import { toast } from "sonner";
import { http } from "@/lib/api";
import { copyText } from "@/lib/clipboard";
import { useAuth } from "@/context/AuthContext";

const Block = ({ code }) => (
  <pre className="mono overflow-x-auto rounded-2xl border border-border bg-muted/60 p-4 text-xs leading-relaxed text-foreground">
    {code}
  </pre>
);

const ENDPOINTS = [
  { m: "GET", p: "/api/v1/profile", d: "Cek profil & saldo akun kamu." },
  { m: "GET", p: "/api/v1/countries", d: "Daftar negara yang tersedia." },
  { m: "GET", p: "/api/v1/services?country_id=<uuid>", d: "Daftar layanan + harga jual (sudah termasuk markup)." },
  { m: "POST", p: "/api/v1/orders", d: "Beli nomor. Body: { service_country_price_id, service_name, country_name }" },
  { m: "POST", p: "/api/v1/orders/{order_id}/ready", d: "Tandai nomor siap menerima OTP (lakukan sebelum minta OTP di aplikasi tujuan)." },
  { m: "GET", p: "/api/v1/orders/{order_id}", d: "Cek status pesanan dan kode OTP yang masuk." },
  { m: "POST", p: "/api/v1/topups", d: "Buat permintaan isi saldo otomatis. Body: { amount }" },
  { m: "GET", p: "/api/v1/topups/{topup_id}", d: "Cek status deposit; saldo otomatis masuk saat paid." },
];

export default function Docs() {
  const { user } = useAuth();
  const [key, setKey] = useState("");
  useEffect(() => { if (user?.api_key) setKey(user.api_key); }, [user]);

  const rotate = async () => {
    try {
      const { data } = await http.post("/auth/api-key/rotate");
      setKey(data.api_key);
      toast.success("API key diperbarui");
    } catch { toast.error("Gagal memperbarui API key"); }
  };

  const base = process.env.REACT_APP_BACKEND_URL;
  const shown = key || "dot_xxxxxxxxxxxxxxxxxxxx";

  return (
    <div data-testid="docs-page" className="mx-auto max-w-4xl px-6 py-14">
      <h1 className="text-4xl font-extrabold sm:text-5xl">Dokumentasi API</h1>
      <p className="mt-4 text-sm text-muted-foreground md:text-lg">
        Semua endpoint memakai header <code className="mono text-primary">x-api-key</code> berisi API key akun kamu.
        Base URL: <code className="mono text-primary">{base}</code>
      </p>
      <div data-testid="docs-apikey-card" className="mt-8 rounded-3xl border border-border bg-card p-6">
        <div className="flex flex-wrap items-center gap-3">
          <KeyRound className="h-5 w-5 text-primary" />
          <span className="font-bold">API Key kamu</span>
          <code data-testid="docs-apikey-value" className="mono flex-1 truncate rounded-xl bg-muted px-3 py-2 text-xs">{shown}</code>
          <button
            data-testid="docs-copy-apikey"
            onClick={() => copyText(shown, "API key dikopi")}
            className="flex items-center gap-2 rounded-xl border border-border px-4 py-2 text-xs font-bold hover:border-primary hover:text-primary"
          >
            <Copy className="h-3.5 w-3.5" /> Copy
          </button>
          {user && (
            <button data-testid="docs-rotate-apikey" onClick={rotate} className="rounded-xl bg-primary px-4 py-2 text-xs font-bold text-primary-foreground">
              Ganti key
            </button>
          )}
        </div>
        {!user && <p className="mt-3 text-xs text-muted-foreground">Masuk untuk melihat API key milikmu.</p>}
      </div>

      <h2 className="mt-12 text-base font-bold md:text-lg">Daftar Endpoint</h2>
      <div className="mt-4 space-y-2">
        {ENDPOINTS.map((e) => (
          <div key={e.p} data-testid={`docs-endpoint-${e.p}`} className="hover-lift rounded-2xl border border-border bg-card p-4">
            <div className="flex flex-wrap items-center gap-3">
              <span className={`mono rounded-lg px-2 py-1 text-[11px] font-bold ${e.m === "GET" ? "bg-primary/15 text-primary" : "bg-emerald-500/15 text-emerald-500"}`}>{e.m}</span>
              <code className="mono text-xs">{e.p}</code>
            </div>
            <p className="mt-2 text-sm text-muted-foreground">{e.d}</p>
          </div>
        ))}
      </div>

      <h2 className="mt-12 text-base font-bold md:text-lg">Contoh: beli nomor</h2>
      <Block code={`curl -X POST ${base}/api/v1/orders \\
  -H "x-api-key: ${shown}" \\
  -H "content-type: application/json" \\
  -d '{"service_country_price_id":"<uuid>","service_name":"WhatsApp","country_name":"Indonesia"}'`} />

      <h2 className="mt-10 text-base font-bold md:text-lg">Contoh: cek OTP</h2>
      <Block code={`curl ${base}/api/v1/orders/<order_id> -H "x-api-key: ${shown}"

# response
{ "data": { "phone_number": "+62812...", "status": "success", "otp_codes": ["482917"] } }`} />

      <h2 className="mt-10 text-base font-bold md:text-lg">Contoh: deposit saldo</h2>
      <Block code={`curl -X POST ${base}/api/v1/topups \\
  -H "x-api-key: ${shown}" -H "content-type: application/json" \\
  -d '{"amount":50000}'

# response berisi kode pembayaran`} />
    </div>
  );
}
