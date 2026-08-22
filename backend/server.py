from dotenv import load_dotenv
from pathlib import Path

ROOT_DIR = Path(__file__).parent
load_dotenv(ROOT_DIR / ".env")

import os
import math
import asyncio
import secrets
import logging
import re
from urllib.parse import urlparse
from datetime import datetime, timezone, timedelta
from typing import Optional, Any

import bcrypt
import jwt
import httpx
from bson import ObjectId
from fastapi import FastAPI, APIRouter, HTTPException, Request, Response, Depends, Header
from pydantic import BaseModel, EmailStr, Field
from motor.motor_asyncio import AsyncIOMotorClient
from starlette.middleware.cors import CORSMiddleware

from integrations import (
    IntegrationError, paykita_create_order, paykita_get_order, paykita_verify_signature,
    smsv_request, send_smtp_mail, telegram_send,
)

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger("dapetotp")

client = AsyncIOMotorClient(os.environ["MONGO_URL"])
db = client[os.environ["DB_NAME"]]

JWT_ALG = "HS256"
app = FastAPI(title="dapetOTP API")
api = APIRouter(prefix="/api")
SERVICE_LOGO_URLS: dict[str, str] = {}

# DapetOTP memakai 90% durasi provider; 10% sisanya disisakan untuk auto-cancel.
PROVIDER_CANCEL_BUFFER_RATIO = 0.10
ORDER_EXPIRY_SCAN_SECONDS = 3
_order_expiry_task = None


@app.middleware("http")
async def disable_pricing_cache(request: Request, call_next):
    """Harga katalog harus berubah langsung setelah admin menyimpan markup."""
    response = await call_next(request)
    path = request.url.path
    if (
        path == "/api/public/stats"
        or path.startswith("/api/catalog/services")
        or path.startswith("/api/catalog/tier-prices")
        or path.startswith("/api/admin/catalog/services")
    ):
        response.headers["Cache-Control"] = "no-store, max-age=0"
        response.headers["Pragma"] = "no-cache"
    return response


def now():
    return datetime.now(timezone.utc)


def oid(v: str) -> ObjectId:
    try:
        return ObjectId(v)
    except Exception:
        raise HTTPException(404, "Data tidak ditemukan")


def clean(doc: dict) -> dict:
    if not doc:
        return doc
    d = dict(doc)
    d["id"] = str(d.pop("_id"))
    d.pop("password_hash", None)
    for k, v in list(d.items()):
        if isinstance(v, ObjectId):
            d[k] = str(v)
        elif isinstance(v, datetime):
            d[k] = (v if v.tzinfo else v.replace(tzinfo=timezone.utc)).isoformat()
    return d


def order_brand_token(site_name: str) -> str:
    """Nama brand untuk nomor order: tanpa spasi/simbol dan selalu uppercase.

    Contoh: "KODE OTP" -> "KODEOTP".
    """
    token = re.sub(r"[^A-Za-z0-9]+", "", str(site_name or "")).upper()
    return token[:32] or "OTP"


def branded_order_no(site_name: str, provider_invoice: Optional[str] = None) -> str:
    """Buat kode order publik mengikuti Nama brand / navbar.

    Jika provider mengirim SMSVirtual-ORD-17872695871841, suffix provider tetap
    dipertahankan tetapi prefix diganti brand situs, misalnya:
    KODEOTP-ORD-17872695871841.
    """
    raw = str(provider_invoice or "").strip()
    suffix = ""
    if raw:
        match = re.search(r"(?:^|-)ORD-([A-Za-z0-9_-]+)$", raw, flags=re.IGNORECASE)
        if match:
            suffix = re.sub(r"[^A-Za-z0-9]+", "", match.group(1)).upper()
        if not suffix:
            digits = re.sub(r"\D+", "", raw)
            suffix = digits[-24:] if digits else re.sub(r"[^A-Za-z0-9]+", "", raw).upper()[-24:]
    if not suffix:
        # Fallback untuk order/ref internal yang tidak punya invoice provider.
        suffix = f"{int(now().timestamp() * 1000)}{secrets.randbelow(10)}"
    return f"{order_brand_token(site_name)}-ORD-{suffix}"


# ---------------- settings ----------------
DEFAULT_SETTINGS: dict[str, dict[str, Any]] = {
    "site": {
        "site_name": "dapetOTP", "tagline": "Nomor virtual & OTP instan untuk semua layanan",
        "business_email": "support@dapetotp.com", "favicon_url": "", "share_thumbnail_url": "",
        "meta_title": "dapetOTP — Sewa Nomor Virtual & OTP Instan",
        "meta_description": "Beli nomor virtual untuk verifikasi OTP ratusan layanan. Saldo fleksibel, API publik, dan dukungan 24/7.",
        "meta_keywords": "otp, nomor virtual, sms virtual, verifikasi",
    },
    "contact": {
        "website": "", "website_enabled": False,
        "phone": "", "phone_enabled": False,
        "support_email": "support@dapetotp.com", "support_email_enabled": True,
        "telegram": "", "telegram_enabled": False,
        "instagram": "", "instagram_enabled": False,
        "tiktok": "", "tiktok_enabled": False,
        "x": "", "x_enabled": False,
        "facebook": "", "facebook_enabled": False,
        "youtube": "", "youtube_enabled": False,
    },
    "verification": {"otp_length": 6, "otp_ttl_seconds": 600, "resend_cooldown_seconds": 60, "max_attempts": 5, "require_email_verification": True},
    "orders": {"order_expiry_seconds": 900, "auto_refund_on_expire": True, "refund_window_seconds": 300,
               "allow_manual_cancel": True, "cancel_cooldown_seconds": 120, "auto_refresh_seconds": 3},
    "pricing": {
        "markup_percent": 0, "fixed_fee": 0, "rounding_to": 100, "rate_to_idr": 1,
        # Screenshot pembelian provider: 50.000 coin = Rp68.000 sebelum pajak => Rp1,36/coin.
        # Field baru ini sengaja dipisah dari rate_to_idr supaya instalasi lama yang sudah
        # menyimpan rate_to_idr=1 langsung mendapatkan perhitungan modal yang benar.
        "provider_cost_multiplier": 1.36, "provider_tax_percent": 11,
        # Profit minimum lama tetap didukung untuk override. Safety floor global memastikan
        # harga jual tidak pernah berada di bawah modal setelah pajak + Rp5.000.
        "min_profit": 0, "safety_min_profit": 5000,
    },
    "smtp": {"host": "", "port": 587, "encryption": "tls", "username": "", "password": "", "from_email": "", "from_name": "dapetOTP", "enabled": False},
    "topup": {"min_amount": 10000, "max_amount": 5000000, "auto_approve": True, "note": "Saldo masuk otomatis setelah pembayaran terdeteksi."},
    "paykita": {"api_key": "", "webhook_secret": "", "order_ttl_seconds": 900, "enabled": False},
    "smsvirtual": {"api_key": "", "timeout_seconds": 30, "low_balance_threshold": 50000, "auto_search_operator": True, "auto_search_server": True, "enabled": False},
    "security": {"webhook_secret": "", "allow_public_api": True, "rate_limit_per_minute": 60, "ip_allowlist": ""},
    "notifications": {"telegram_bot_token": "", "telegram_chat_id": "", "notify_ticket": True, "notify_topup": True, "notify_order": True, "email_ticket_to": ""},
    "tiers": {
        "member_markup_percent": 20, "member_fixed_fee": 0,
        "reseller_markup_percent": 12, "reseller_fixed_fee": 0,
        "vip_markup_percent": 6, "vip_fixed_fee": 0,
        "reseller_min_topup": 500000, "vip_min_topup": 2000000,
        "member_benefits": "Akses seluruh layanan\nAPI key pribadi\nHarga level Member",
        "reseller_benefits": "Harga layanan lebih murah dari Member\nAPI key pribadi\nCocok untuk dijual kembali\nSemua benefit Member",
        "vip_benefits": "Harga level paling rendah\nAPI key pribadi\nCocok untuk volume transaksi tinggi\nSemua benefit Reseller",
    },
    "backup": {"auto_backup": False, "retention_days": 30, "last_backup_at": ""},
}
SECRET_KEYS = {"password", "api_key", "webhook_secret", "telegram_bot_token"}


async def get_settings(cat: str) -> dict:
    doc = await db.settings.find_one({"_id": cat})
    base = dict(DEFAULT_SETTINGS.get(cat, {}))
    if doc:
        base.update({k: v for k, v in doc.items() if k != "_id"})
    return base


async def all_settings() -> dict:
    out = {}
    for cat in DEFAULT_SETTINGS:
        out[cat] = await get_settings(cat)
    return out


def mask(cat: str, data: dict) -> dict:
    d = dict(data)
    for k in list(d.keys()):
        if k in SECRET_KEYS and d[k]:
            d[k] = "••••••••"
            d[f"{k}_set"] = True
    return d


# ---------------- auth ----------------
def hash_password(p: str) -> str:
    return bcrypt.hashpw(p.encode(), bcrypt.gensalt()).decode()


def verify_password(p: str, h: str) -> bool:
    try:
        return bcrypt.checkpw(p.encode(), h.encode())
    except Exception:
        return False


def create_access_token(uid: str, email: str) -> str:
    return jwt.encode({"sub": uid, "email": email, "type": "access", "exp": now() + timedelta(hours=12)}, os.environ["JWT_SECRET"], algorithm=JWT_ALG)


def create_refresh_token(uid: str) -> str:
    return jwt.encode({"sub": uid, "type": "refresh", "exp": now() + timedelta(days=7)}, os.environ["JWT_SECRET"], algorithm=JWT_ALG)


def set_auth_cookies(response: Response, uid: str, email: str):
    response.set_cookie("access_token", create_access_token(uid, email), httponly=True, secure=True, samesite="none", max_age=43200, path="/")
    response.set_cookie("refresh_token", create_refresh_token(uid), httponly=True, secure=True, samesite="none", max_age=604800, path="/")


async def get_current_user(request: Request) -> dict:
    token = request.cookies.get("access_token")
    if not token:
        h = request.headers.get("Authorization", "")
        if h.startswith("Bearer "):
            token = h[7:]
    if not token:
        raise HTTPException(401, "Belum masuk")
    try:
        payload = jwt.decode(token, os.environ["JWT_SECRET"], algorithms=[JWT_ALG])
    except jwt.ExpiredSignatureError:
        raise HTTPException(401, "Sesi kedaluwarsa")
    except jwt.InvalidTokenError:
        raise HTTPException(401, "Token tidak valid")
    user = await db.users.find_one({"_id": oid(payload["sub"])})
    if not user:
        raise HTTPException(401, "Pengguna tidak ditemukan")
    if user.get("suspended"):
        raise HTTPException(403, "Akun ditangguhkan")
    return user


async def require_admin(user: dict = Depends(get_current_user)) -> dict:
    if user.get("role") != "admin":
        raise HTTPException(403, "Khusus admin")
    return user


async def api_key_user(x_api_key: Optional[str] = Header(None)) -> dict:
    if not x_api_key:
        raise HTTPException(401, "Header x-api-key wajib")
    user = await db.users.find_one({"api_key": x_api_key})
    if not user:
        raise HTTPException(401, "API key tidak valid")
    if user.get("suspended"):
        raise HTTPException(403, "Akun ditangguhkan")
    return user


# ---------------- models ----------------
class RegisterIn(BaseModel):
    name: str
    email: EmailStr
    password: str = Field(min_length=6)


class LoginIn(BaseModel):
    email: EmailStr
    password: str


class OtpIn(BaseModel):
    email: EmailStr
    code: str


class EmailIn(BaseModel):
    email: EmailStr


class TopupIn(BaseModel):
    amount: int


class DirectOrderPaymentIn(BaseModel):
    service_country_price_id: str
    country_id: str
    service_name: Optional[str] = ""
    country_name: Optional[str] = ""
    operator_id: Optional[str] = None


class OrderIn(BaseModel):
    service_country_price_id: str
    service_name: Optional[str] = ""
    country_name: Optional[str] = ""
    operator_id: Optional[str] = None


class RatingIn(BaseModel):
    stars: int = Field(ge=1, le=5)
    comment: str = Field(default="", max_length=500)


class TicketIn(BaseModel):
    subject: str
    category: str = "umum"
    message: str


class ReplyIn(BaseModel):
    message: str


class SettingsIn(BaseModel):
    values: dict


class ApiKeyChangeIn(BaseModel):
    custom_key: Optional[str] = None


class TierUpgradeIn(BaseModel):
    tier: str


class BlogIn(BaseModel):
    title: str = Field(min_length=1, max_length=220)
    slug: str = Field(default="", max_length=220)
    excerpt: str = Field(default="", max_length=600)
    body: str = Field(default="", max_length=100000)
    cover_url: str = Field(default="", max_length=6000000)
    published: bool = True


class BlogCommentIn(BaseModel):
    body: str = Field(min_length=1, max_length=2000)
    parent_id: Optional[str] = None


# ---------------- helpers ----------------
async def log_activity(user_id, action, meta=None):
    await db.activity.insert_one({"user_id": str(user_id) if user_id else None, "action": action, "meta": meta or {}, "created_at": now()})


def pricing_breakdown(provider_price: float, p: dict) -> dict:
    """Hitung harga jual berdasarkan MODAL RIIL provider, bukan harga coin mentah.

    Rumus aman:
      modal = harga_provider × kurs × biaya_per_unit × (1 + pajak_provider)
      harga_markup = modal × (1 + markup) + biaya_tetap
      harga_jual = max(harga_markup, modal + profit_minimum_aman)

    Dengan setting bawaan saat ini, 5.000 coin =>
      5.000 × 1,36 × 1,11 = Rp7.548 modal riil.
    Harga jual minimal menjadi Rp12.548 (dibulatkan sesuai rounding_to), sehingga
    markup kecil tidak bisa membuat transaksi rugi atau profit di bawah Rp5.000.
    """
    provider_units = max(0.0, float(provider_price or 0))
    rate_to_idr = max(0.0, float(p.get("rate_to_idr") or 1))
    cost_multiplier = max(0.0, float(p.get("provider_cost_multiplier") or 1))
    tax_percent = max(0.0, float(p.get("provider_tax_percent") or 0))
    markup_percent = float(p.get("markup_percent") or 0)
    fixed_fee = float(p.get("fixed_fee") or 0)

    cost_before_tax = provider_units * rate_to_idr * cost_multiplier
    provider_tax = cost_before_tax * tax_percent / 100
    cost_after_tax = cost_before_tax + provider_tax

    markup_price = cost_after_tax * (1 + markup_percent / 100) + fixed_fee
    requested_min_profit = max(0.0, float(p.get("min_profit") or 0))
    safety_min_profit = max(0.0, float(p.get("safety_min_profit") or 0))
    effective_min_profit = max(requested_min_profit, safety_min_profit)
    safe_price = cost_after_tax + effective_min_profit

    raw_price = max(markup_price, safe_price)
    step = max(1, int(p.get("rounding_to") or 1))
    price = int(math.ceil(raw_price / step) * step)

    return {
        "provider_units": provider_units,
        "cost_before_tax": cost_before_tax,
        "provider_tax": provider_tax,
        "cost_after_tax": cost_after_tax,
        "markup_percent": markup_percent,
        "effective_min_profit": effective_min_profit,
        "price": price,
        "estimated_profit": price - cost_after_tax,
    }


def apply_pricing(provider_price: float, p: dict) -> int:
    return pricing_breakdown(provider_price, p)["price"]


async def pricing_for(code: str, base_pricing: dict, tier: str = "member") -> dict:
    """Markup global → override per layanan → markup level akun."""
    merged = dict(base_pricing)
    if code:
        ov = await db.service_pricing.find_one({"_id": code})
        if ov:
            for k in ("markup_percent", "fixed_fee", "rounding_to", "min_profit"):
                if ov.get(k) is not None:
                    merged[k] = ov[k]
    tiers = await get_settings("tiers")
    t = (tier or "member").lower()
    if f"{t}_markup_percent" in tiers:
        merged["markup_percent"] = float(merged.get("markup_percent") or 0) + float(tiers[f"{t}_markup_percent"] or 0)
        merged["fixed_fee"] = float(merged.get("fixed_fee") or 0) + float(tiers[f"{t}_fixed_fee"] or 0)
    return merged


async def send_otp_email(email: str, name: str, code: str, ttl: int):
    smtp = await get_settings("smtp")
    site = await get_settings("site")
    html = f"""<div style="font-family:Arial,sans-serif;background:#0b1220;padding:32px;color:#e8eefc">
      <h2 style="color:#4f8dfd;margin:0 0 8px">{site['site_name']}</h2>
      <p>Hai {name}, kode verifikasi kamu:</p>
      <p style="font-size:34px;letter-spacing:8px;font-weight:700;color:#fff">{code}</p>
      <p style="color:#9db0d4">Berlaku {ttl // 60} menit. Jangan bagikan kode ini ke siapa pun.</p>
    </div>"""
    if not smtp.get("enabled") or not smtp.get("host"):
        logger.info("[OTP-DEV] %s -> %s", email, code)
        return False
    try:
        send_smtp_mail(smtp, email, f"Kode verifikasi {site['site_name']}", html)
        return True
    except Exception as e:
        logger.error("SMTP gagal: %s", e)
        return False


async def notify_ticket(ticket: dict, user: dict, kind: str = "baru"):
    n = await get_settings("notifications")
    if n.get("notify_ticket"):
        text = (f"🎫 <b>Tiket {kind}</b>\n<b>{ticket['subject']}</b>\nKategori: {ticket.get('category')}\n"
                f"Dari: {user.get('email')}\n\n{ticket.get('message', '')[:500]}")
        try:
            await telegram_send(n.get("telegram_bot_token"), n.get("telegram_chat_id"), text)
        except Exception as e:
            logger.error("telegram gagal: %s", e)
    to = n.get("email_ticket_to")
    smtp = await get_settings("smtp")
    if to and smtp.get("enabled"):
        try:
            send_smtp_mail(smtp, to, f"[Tiket {kind}] {ticket['subject']}", f"<p>Dari {user.get('email')}</p><p>{ticket.get('message','')}</p>")
        except Exception as e:
            logger.error("email tiket gagal: %s", e)


# ---------------- auth routes ----------------
@api.post("/auth/register")
async def register(body: RegisterIn, response: Response):
    email = body.email.lower()
    if await db.users.find_one({"email": email}):
        raise HTTPException(400, "Email sudah terdaftar")
    v = await get_settings("verification")
    doc = {
        "name": body.name, "email": email, "password_hash": hash_password(body.password),
        "role": "user", "balance": 0, "api_key": "dot_" + secrets.token_hex(20),
        "email_verified": not v.get("require_email_verification", True),
        "auth_provider": "password", "created_at": now(),
    }
    res = await db.users.insert_one(doc)
    uid = str(res.inserted_id)
    if v.get("require_email_verification", True):
        code = "".join(secrets.choice("0123456789") for _ in range(int(v.get("otp_length") or 6)))
        ttl = int(v.get("otp_ttl_seconds") or 600)
        await db.email_otps.delete_many({"email": email})
        await db.email_otps.insert_one({"email": email, "code": code, "attempts": 0, "created_at": now(), "expires_at": now() + timedelta(seconds=ttl)})
        await send_otp_email(email, body.name, code, ttl)
        return {"needs_verification": True, "email": email, "resend_cooldown": v.get("resend_cooldown_seconds", 60)}
    set_auth_cookies(response, uid, email)
    return {"needs_verification": False, "user": clean({**doc, "_id": res.inserted_id})}


@api.post("/auth/verify-otp")
async def verify_otp(body: OtpIn, response: Response):
    email = body.email.lower()
    rec = await db.email_otps.find_one({"email": email})
    v = await get_settings("verification")
    if not rec:
        raise HTTPException(400, "Kode tidak ditemukan, kirim ulang")
    if rec["expires_at"].replace(tzinfo=timezone.utc) < now():
        raise HTTPException(400, "Kode kedaluwarsa")
    if rec.get("attempts", 0) >= int(v.get("max_attempts") or 5):
        raise HTTPException(429, "Terlalu banyak percobaan, kirim ulang kode")
    if rec["code"] != body.code.strip():
        await db.email_otps.update_one({"_id": rec["_id"]}, {"$inc": {"attempts": 1}})
        raise HTTPException(400, "Kode salah")
    await db.email_otps.delete_many({"email": email})
    await db.users.update_one({"email": email}, {"$set": {"email_verified": True}})
    user = await db.users.find_one({"email": email})
    set_auth_cookies(response, str(user["_id"]), email)
    return {"user": clean(user)}


@api.post("/auth/resend-otp")
async def resend_otp(body: EmailIn):
    email = body.email.lower()
    user = await db.users.find_one({"email": email})
    if not user:
        raise HTTPException(404, "Email belum terdaftar")
    v = await get_settings("verification")
    cooldown = int(v.get("resend_cooldown_seconds") or 60)
    existing = await db.email_otps.find_one({"email": email})
    if existing and (now() - existing["created_at"].replace(tzinfo=timezone.utc)).total_seconds() < cooldown:
        raise HTTPException(429, f"Tunggu {cooldown} detik sebelum kirim ulang")
    code = "".join(secrets.choice("0123456789") for _ in range(int(v.get("otp_length") or 6)))
    ttl = int(v.get("otp_ttl_seconds") or 600)
    await db.email_otps.delete_many({"email": email})
    await db.email_otps.insert_one({"email": email, "code": code, "attempts": 0, "created_at": now(), "expires_at": now() + timedelta(seconds=ttl)})
    sent = await send_otp_email(email, user.get("name", ""), code, ttl)
    return {"sent": sent, "cooldown": cooldown}


@api.post("/auth/login")
async def login(body: LoginIn, request: Request, response: Response):
    email = body.email.lower()
    fwd = (request.headers.get("x-forwarded-for") or "").split(",")[0].strip()
    ident = f"{fwd or (request.client.host if request.client else 'x')}:{email}"
    for key in (ident, f"email:{email}"):
        att = await db.login_attempts.find_one({"identifier": key})
        if att and att.get("count", 0) >= 5 and att.get("last").replace(tzinfo=timezone.utc) > now() - timedelta(minutes=15):
            raise HTTPException(429, "Terlalu banyak percobaan. Coba lagi 15 menit.")
    user = await db.users.find_one({"email": email})
    if not user or not verify_password(body.password, user.get("password_hash", "")):
        for key in (ident, f"email:{email}"):
            await db.login_attempts.update_one({"identifier": key}, {"$inc": {"count": 1}, "$set": {"last": now()}}, upsert=True)
        raise HTTPException(401, "Email atau kata sandi salah")
    if not user.get("email_verified"):
        raise HTTPException(403, "Email belum diverifikasi")
    if user.get("suspended"):
        raise HTTPException(403, "Akun ditangguhkan. Hubungi dukungan.")
    await db.login_attempts.delete_many({"identifier": {"$in": [ident, f"email:{email}"]}})
    set_auth_cookies(response, str(user["_id"]), email)
    await log_activity(user["_id"], "login")
    return {"user": clean(user)}


@api.get("/auth/me")
async def me(user: dict = Depends(get_current_user)):
    return clean(user)


@api.post("/auth/logout")
async def logout(response: Response):
    response.delete_cookie("access_token", path="/")
    response.delete_cookie("refresh_token", path="/")
    return {"ok": True}


async def set_user_api_key(user: dict, custom_key: Optional[str] = None) -> str:
    if custom_key is None or not str(custom_key).strip():
        key = "dot_" + secrets.token_hex(20)
    else:
        raw = str(custom_key).strip()
        key = raw if raw.startswith("dot_") else f"dot_{raw}"
        if len(key) < 12 or len(key) > 96:
            raise HTTPException(400, "API key custom harus 12-96 karakter")
        if not re.fullmatch(r"[A-Za-z0-9_-]+", key):
            raise HTTPException(400, "API key hanya boleh berisi huruf, angka, underscore, dan strip")

    exists = await db.users.find_one({"api_key": key, "_id": {"$ne": user["_id"]}}, {"_id": 1})
    if exists:
        raise HTTPException(409, "API key sudah digunakan akun lain")
    await db.users.update_one({"_id": user["_id"]}, {"$set": {"api_key": key}})
    await log_activity(user["_id"], "api_key.change", {"custom": bool(custom_key and str(custom_key).strip())})
    return key


@api.post("/auth/api-key/rotate")
async def rotate_key(user: dict = Depends(get_current_user)):
    return {"api_key": await set_user_api_key(user)}


@api.post("/auth/api-key")
async def change_api_key(body: ApiKeyChangeIn, user: dict = Depends(get_current_user)):
    return {"api_key": await set_user_api_key(user, body.custom_key)}


# ---------------- public ----------------
@api.get("/public/settings")
async def public_settings(response: Response):
    # Branding/SEO dapat diubah dari Admin, jadi jangan cache agar perubahan
    # navbar, title browser, favicon, dan metadata langsung terbaca frontend.
    response.headers["Cache-Control"] = "no-store, max-age=0"
    site = await get_settings("site")
    contact = await get_settings("contact")
    topup = await get_settings("topup")
    return {
        "site": site,
        "contact": contact,
        "topup": {"min_amount": topup["min_amount"], "max_amount": topup["max_amount"], "note": topup.get("note")},
    }


def public_service_logo(service_code: str, upstream_url: str) -> str:
    """Simpan URL logo upstream hanya di backend; browser menerima URL domain sendiri."""
    code = str(service_code or "").strip()
    url = str(upstream_url or "").strip()
    if not code or not url:
        return ""
    parsed = urlparse(url)
    if parsed.scheme != "https" or parsed.hostname not in {"minio.sms-virtuals.net"}:
        return ""
    SERVICE_LOGO_URLS[code] = url
    return f"/api/catalog/logo/{code}"


def service_logo_fallback(service_code: str) -> Response:
    """Kembalikan logo placeholder valid agar browser tidak membanjiri console dengan 404 logo provider."""
    code = re.sub(r"[^A-Za-z0-9]", "", str(service_code or ""))[:3].upper() or "OTP"
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96" viewBox="0 0 96 96">
<rect width="96" height="96" rx="20" fill="#172033"/>
<text x="48" y="55" text-anchor="middle" font-family="Arial,sans-serif" font-size="24" font-weight="700" fill="#60a5fa">{code}</text>
</svg>'''.encode("utf-8")
    return Response(
        content=svg,
        media_type="image/svg+xml",
        headers={"Cache-Control": "public, max-age=3600", "X-DapetOTP-Logo-Fallback": "1"},
    )


@api.get("/catalog/logo/{service_code}")
async def catalog_logo(service_code: str):
    url = SERVICE_LOGO_URLS.get(service_code)
    if not url:
        rec = await db.orders.find_one({"service_code": service_code, "service_logo_source": {"$exists": True}}, {"service_logo_source": 1})
        url = (rec or {}).get("service_logo_source")
        if url:
            SERVICE_LOGO_URLS[service_code] = url
    if not url:
        return service_logo_fallback(service_code)
    try:
        async with httpx.AsyncClient(timeout=10, follow_redirects=False) as c:
            r = await c.get(url)
        content_type = (r.headers.get("content-type") or "").split(";", 1)[0].strip().lower()
        if r.status_code != 200 or not content_type.startswith("image/"):
            logger.info("logo provider tidak tersedia untuk %s (status=%s)", service_code, r.status_code)
            return service_logo_fallback(service_code)
        return Response(content=r.content, media_type=content_type, headers={"Cache-Control": "public, max-age=86400"})
    except Exception as e:
        logger.info("logo provider gagal dimuat untuk %s: %s", service_code, e)
        return service_logo_fallback(service_code)


@api.get("/public/stats")
async def public_stats(request: Request, response: Response):
    # Statistik landing selalu memakai harga publik level Member, bukan tier dari cookie
    # admin/user yang kebetulan sedang login di browser. Cache juga dimatikan agar
    # perubahan markup langsung terlihat setelah disimpan.
    response.headers["Cache-Control"] = "no-store, max-age=0"
    cfg = await get_settings("smsvirtual")
    out = {"countries": 0, "services": 0, "cheapest": 0, "top": []}
    try:
        c = await countries()
        out["countries"] = len(c["items"])
        idn = next((x for x in c["items"] if x["name"] == "Indonesia"), c["items"][0] if c["items"] else None)
        if idn:
            s = await services(request, idn["id"], tier_override="member")
            items = s["items"]
            out["services"] = len(items)
            if items:
                cheap = sorted(items, key=lambda x: x["price"])
                out["cheapest"] = cheap[0]["price"]
                out["top"] = [{"name": i["service_name"], "logo": i["logo"], "price": i["price"]} for i in cheap[:6]]
    except HTTPException:
        pass
    except IntegrationError:
        pass
    return out


@api.get("/public/tiers")
async def public_tiers(request: Request):
    t = await get_settings("tiers")
    me = await optional_user(request)
    return {
        "current": (me or {}).get("tier") or "member",
        "items": [
            {"key": "member", "label": "Member", "color": "slate", "min_topup": 0,
             "markup_percent": t.get("member_markup_percent") or 0, "benefits": t.get("member_benefits") or ""},
            {"key": "reseller", "label": "Reseller", "color": "sky", "min_topup": t.get("reseller_min_topup") or 0,
             "markup_percent": t.get("reseller_markup_percent") or 0, "benefits": t.get("reseller_benefits") or ""},
            {"key": "vip", "label": "VIP", "color": "amber", "min_topup": t.get("vip_min_topup") or 0,
             "markup_percent": t.get("vip_markup_percent") or 0, "benefits": t.get("vip_benefits") or ""},
        ],
    }


@api.get("/announcements")
async def list_announcements():
    docs = await db.announcements.find({"active": True}).sort([("pinned", -1), ("created_at", -1)]).to_list(20)
    return {"items": [clean(d) for d in docs]}


@api.get("/me/summary")
async def me_summary(user: dict = Depends(get_current_user)):
    uid = str(user["_id"])
    orders = await db.orders.count_documents({"user_id": uid})
    success = await db.orders.count_documents({"user_id": uid, "status": "success"})
    spend = await db.transactions.aggregate([
        {"$match": {"user_id": uid, "type": "purchase"}},
        {"$group": {"_id": None, "t": {"$sum": "$amount"}}},
    ]).to_list(1)
    topup = await db.transactions.aggregate([
        {"$match": {"user_id": uid, "type": "topup"}},
        {"$group": {"_id": None, "t": {"$sum": "$amount"}}},
    ]).to_list(1)
    tcfg = await get_settings("tiers")
    tier = (user.get("tier") or "member").lower()
    return {
        "name": user.get("name"), "email": user.get("email"), "balance": user.get("balance", 0),
        "tier": tier, "tier_label": {"member": "Member", "reseller": "Reseller", "vip": "VIP"}.get(tier, "Member"),
        "orders": orders, "success_orders": success,
        "spend": abs(int(spend[0]["t"] if spend else 0)),
        "topup_total": int(topup[0]["t"] if topup else 0),
        "next_tier": ("reseller" if tier == "member" else "vip" if tier == "reseller" else None),
        "next_tier_min_topup": (tcfg.get("reseller_min_topup") if tier == "member" else tcfg.get("vip_min_topup") if tier == "reseller" else None),
    }


@api.post("/me/tier/upgrade")
async def upgrade_my_tier(body: TierUpgradeIn, user: dict = Depends(get_current_user)):
    target = str(body.tier or "").lower()
    rank = {"member": 0, "reseller": 1, "vip": 2}
    current = str(user.get("tier") or "member").lower()
    if target not in rank or target == "member":
        raise HTTPException(400, "Level upgrade tidak dikenal")
    if rank.get(current, 0) >= rank[target]:
        raise HTTPException(400, "Akun sudah berada di level tersebut atau lebih tinggi")

    tcfg = await get_settings("tiers")
    required = int(tcfg.get(f"{target}_min_topup") or 0)
    uid = str(user["_id"])
    rows = await db.transactions.aggregate([
        {"$match": {"user_id": uid, "type": "topup"}},
        {"$group": {"_id": None, "t": {"$sum": "$amount"}}},
    ]).to_list(1)
    total = int(rows[0]["t"] if rows else 0)
    if total < required:
        raise HTTPException(400, f"Total deposit belum cukup. Minimal Rp{required:,.0f}".replace(",", "."))

    await db.users.update_one({"_id": user["_id"]}, {"$set": {"tier": target, "tier_upgraded_at": now()}})
    await log_activity(user["_id"], "tier.upgrade", {"from": current, "to": target, "topup_total": total})
    return {"tier": target, "topup_total": total}


@api.get("/catalog/tier-prices")
async def tier_prices(request: Request, country_id: str, service_code: str = ""):
    """Perbandingan harga tiap level akun untuk beberapa layanan populer."""
    out = {}
    for t in ("member", "reseller", "vip"):
        data = await services(request, country_id, tier_override=t)
        items = data["items"]
        if service_code:
            items = [i for i in items if i.get("service_code") == service_code]
        else:
            items = sorted(items, key=lambda x: x["price"])[:5]
        out[t] = [{"service_name": i["service_name"], "service_code": i.get("service_code"), "logo": i.get("logo"), "price": i["price"]} for i in items]
    return out


@api.get("/catalog/countries")
async def countries():
    cfg = await get_settings("smsvirtual")
    try:
        data = await smsv_request(cfg.get("api_key"), "GET", "/v1/public/countries", params={"page": 1, "pageSize": 200}, timeout=int(cfg.get("timeout_seconds") or 30))
        items = data if isinstance(data, list) else data.get("data", [])
        return {"items": [{"id": c.get("id"), "code": c.get("code"), "name": c.get("name")} for c in items if c.get("isActive", True)]}
    except IntegrationError as e:
        logger.warning("catalog countries gagal: %s", e)
        raise HTTPException(503, "Katalog sementara tidak tersedia. Coba lagi sebentar.")


async def optional_user(request: Request):
    try:
        return await get_current_user(request)
    except HTTPException:
        return None


@api.get("/catalog/services")
async def services(request: Request, country_id: str, page: int = 1, page_size: int = 500, full: bool = False,
                   tier_override: Optional[str] = None):
    cfg = await get_settings("smsvirtual")
    pricing = await get_settings("pricing")
    tier_cfg = await get_settings("tiers")
    try:
        data = await smsv_request(cfg.get("api_key"), "GET", "/v1/public/services/list",
                                  params={"countryId": country_id, "page": page, "pageSize": page_size},
                                  timeout=int(cfg.get("timeout_seconds") or 30))
    except IntegrationError as e:
        logger.warning("catalog services gagal: %s", e)
        raise HTTPException(503, "Katalog sementara tidak tersedia. Coba lagi sebentar.")
    items = data if isinstance(data, list) else data.get("data", [])
    overrides = {d["_id"]: d for d in await db.service_pricing.find({}).to_list(2000)}
    me = await optional_user(request)
    tier = (tier_override or (me or {}).get("tier") or "member").lower()
    tier_add_pct = float(tier_cfg.get(f"{tier}_markup_percent") or 0)
    tier_add_fee = float(tier_cfg.get(f"{tier}_fixed_fee") or 0)
    out = []
    for it in items:
        svc = it.get("service") or it
        tiers = it.get("prices") or []

        def tier_price(t):
            return t.get("promoPrice") or t.get("price") or t.get("sellPrice") or 0

        tiers = [t for t in tiers if tier_price(t)]
        if not tiers:
            continue
        tiers = sorted(tiers, key=tier_price)
        best = tiers[0]
        code = svc.get("code")
        p = dict(pricing)
        ov = overrides.get(code)
        if ov:
            for k in ("markup_percent", "fixed_fee", "rounding_to", "min_profit"):
                if ov.get(k) is not None:
                    p[k] = ov[k]
        p["markup_percent"] = float(p.get("markup_percent") or 0) + tier_add_pct
        p["fixed_fee"] = float(p.get("fixed_fee") or 0) + tier_add_fee
        provider_price = tier_price(best)
        breakdown = pricing_breakdown(provider_price, p)
        out.append({
            "service_country_price_id": best.get("id"),
            "service_name": (svc.get("name") or "-").strip(),
            "service_code": code,
            "logo": public_service_logo(code, svc.get("imageUrl")),
            "country_name": (it.get("country") or {}).get("name"),
            "stock": best.get("stock") if best.get("stock") is not None else it.get("totalStock"),
            "provider_price": provider_price,
            "provider_price_min": tier_price(tiers[0]),
            "provider_price_max": tier_price(tiers[-1]),
            "provider_cost_before_tax": breakdown["cost_before_tax"],
            "provider_tax": breakdown["provider_tax"],
            "provider_cost_after_tax": breakdown["cost_after_tax"],
            "estimated_profit": breakdown["estimated_profit"],
            "minimum_profit": breakdown["effective_min_profit"],
            "markup_percent": p.get("markup_percent") or 0,
            "markup_source": "layanan" if ov else "global",
            "price": breakdown["price"],
            "tiers": [{"id": t.get("id"), "provider_price": tier_price(t), "price": apply_pricing(tier_price(t), p),
                       "stock": t.get("stock")} for t in tiers],
        })
    out.sort(key=lambda x: x["service_name"].lower())
    if not full:
        hide = (
            "provider_price", "provider_price_min", "provider_price_max",
            "provider_cost_before_tax", "provider_tax", "provider_cost_after_tax",
            "estimated_profit", "minimum_profit", "markup_percent", "markup_source",
        )
        out = [{**{k: v for k, v in i.items() if k not in hide},
                "tiers": [{"id": t["id"], "price": t["price"], "stock": t.get("stock")} for t in i["tiers"]]}
               for i in out]
    return {"items": out}


async def quote_direct_order(user: dict, body: DirectOrderPaymentIn) -> dict:
    """Ambil harga pilihan server langsung dari provider lalu terapkan pricing akun.

    Harga dari frontend tidak dipercaya. Ini membuat pembayaran langsung selalu memakai
    nominal layanan yang benar pada saat QR dibuat, tanpa melewati aturan minimum isi saldo.
    """
    cfg = await get_settings("smsvirtual")
    pricing = await get_settings("pricing")
    tier_cfg = await get_settings("tiers")
    try:
        data = await smsv_request(
            cfg.get("api_key"),
            "GET",
            "/v1/public/services/list",
            params={"countryId": body.country_id, "page": 1, "pageSize": 500},
            timeout=int(cfg.get("timeout_seconds") or 30),
        )
    except IntegrationError as e:
        logger.warning("direct payment quote gagal: %s", e)
        raise HTTPException(503, "Harga layanan sementara tidak dapat dicek. Coba lagi sebentar.")

    items = data if isinstance(data, list) else (data or {}).get("data", [])
    found = None
    for it in items:
        svc = it.get("service") or it
        for tier_price_row in it.get("prices") or []:
            if str(tier_price_row.get("id")) == str(body.service_country_price_id):
                provider_price = (
                    tier_price_row.get("promoPrice")
                    or tier_price_row.get("price")
                    or tier_price_row.get("sellPrice")
                    or 0
                )
                found = {
                    "provider_price": provider_price,
                    "service_code": svc.get("code"),
                    "service_name": (svc.get("name") or body.service_name or "-").strip(),
                    "country_name": ((it.get("country") or {}).get("name") or body.country_name or "-").strip(),
                }
                break
        if found:
            break

    if not found or not found["provider_price"]:
        raise HTTPException(400, "Pilihan harga/server layanan sudah tidak tersedia. Refresh katalog lalu coba lagi.")

    p = await pricing_for(found["service_code"], pricing, user.get("tier") or "member")
    found["price"] = apply_pricing(found["provider_price"], p)
    return found


# ---------------- orders ----------------
class DirectProviderOrderError(Exception):
    """Error asli provider saat fulfillment pembayaran langsung (disimpan internal, tidak dibuka ke user biasa)."""


async def resolve_activation(cfg: dict, provider_order_id: str, attempts: int = 4, activation_id: str = None):
    """Provider hanya mengembalikan envelope order; cari baris activation-nya.
    Jika activation_id diberikan, kembalikan False bila activation tidak ada lagi di daftar ongoing."""
    for i in range(attempts):
        try:
            data = await smsv_request(cfg.get("api_key"), "GET", "/v1/public/orders/ongoing-activation",
                                      params={"page": 1, "pageSize": 50}, timeout=int(cfg.get("timeout_seconds") or 30))
        except IntegrationError:
            return None
        rows = data if isinstance(data, list) else (data or {}).get("data", [])
        if activation_id:
            match = next((r for r in rows if r.get("id") == activation_id), None)
            return match if match else False
        for r in rows:
            if r.get("orderId") == provider_order_id:
                sc = r.get("serviceCountry") or {}
                inv = ((r.get("order") or {}).get("transaction") or {}).get("invoiceNo")
                return {"id": r.get("id"), "phoneNumber": r.get("phoneNumber"), "price": r.get("servicePrice"),
                        "service": sc.get("service") or {}, "country": sc.get("country") or {},
                        "invoiceNo": inv, "expiredTime": r.get("expiredTime")}
        if i < attempts - 1:
            await asyncio.sleep(1.5)
    return None


def parse_provider_expiry(value) -> Optional[datetime]:
    if isinstance(value, datetime):
        return value if value.tzinfo else value.replace(tzinfo=timezone.utc)
    if value:
        try:
            return datetime.fromisoformat(str(value).replace("Z", "+00:00"))
        except Exception:
            return None
    return None


def dapetotp_expiry(provider_expires_at: datetime, started_at: datetime) -> datetime:
    """Gunakan 90% durasi provider sehingga 10% terakhir aman untuk auto-cancel."""
    provider_expires_at = parse_provider_expiry(provider_expires_at) or provider_expires_at
    started_at = parse_provider_expiry(started_at) or now()
    duration_seconds = max(0.0, (provider_expires_at - started_at).total_seconds())
    buffer_seconds = duration_seconds * PROVIDER_CANCEL_BUFFER_RATIO
    return provider_expires_at - timedelta(seconds=buffer_seconds)


async def create_number_order(
    user: dict,
    body: OrderIn,
    source: str,
    *,
    charge_balance: bool = True,
    forced_price: Optional[int] = None,
    payment_method: str = "balance",
    payment_ref: Optional[str] = None,
):
    cfg = await get_settings("smsvirtual")
    pricing = await get_settings("pricing")
    ocfg = await get_settings("orders")
    try:
        listing = await smsv_request(cfg.get("api_key"), "POST", "/v1/public/orders/request-single-service",
                                     json_body={"serviceCountryPriceId": body.service_country_price_id, "quantity": 1,
                                                **({"operatorId": body.operator_id} if body.operator_id else {}),
                                                "autoSearchOperator": bool(cfg.get("auto_search_operator")),
                                                "autoSearchServer": bool(cfg.get("auto_search_server"))},
                                     timeout=int(cfg.get("timeout_seconds") or 30))
    except IntegrationError as e:
        logger.warning("order create gagal: %s", e)
        if source == "direct_payment":
            raise DirectProviderOrderError(str(e))
        raise HTTPException(400, "Layanan sementara tidak tersedia. Coba lagi sebentar.")
    details = listing.get("orderDetail") or []
    detail = details[0] if details else None
    if not detail or not detail.get("id") or not detail.get("phoneNumber"):
        detail = await resolve_activation(cfg, listing.get("id"))
    if not detail or not detail.get("id") or not detail.get("phoneNumber"):
        raise HTTPException(400, "Nomor belum tersedia untuk layanan ini. Coba layanan atau pilihan server lain.")
    activation_id = detail["id"]
    provider_price = detail.get("price") or listing.get("totalPrice") or listing.get("amount") or 0
    svc_code = (detail.get("service") or {}).get("code")
    p = await pricing_for(svc_code, pricing, user.get("tier") or "member")
    breakdown = pricing_breakdown(provider_price, p)
    calculated_price = breakdown["price"]
    if forced_price is not None and int(forced_price) < calculated_price:
        # Pembayaran langsung mungkin dibuat beberapa detik sebelum provider menaikkan
        # harga. Jangan penuhi order dengan nominal lama jika sudah berada di bawah
        # modal + profit minimum aman; batalkan aktivasi provider dan coba server lain.
        try:
            await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/cancel/{activation_id}")
        except Exception:
            pass
        raise DirectProviderOrderError(
            f"Harga provider berubah; pembayaran Rp{int(forced_price):,} di bawah harga aman Rp{calculated_price:,}"
        )
    price = int(forced_price) if forced_price is not None else calculated_price
    if charge_balance:
        fresh = await db.users.find_one({"_id": user["_id"]})
        if (fresh.get("balance") or 0) < price:
            try:
                await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/cancel/{activation_id}")
            except Exception:
                pass
            raise HTTPException(400, "Saldo tidak cukup")
        await db.users.update_one({"_id": user["_id"]}, {"$inc": {"balance": -price}})
    cooldown = int(ocfg.get("cancel_cooldown_seconds") or 120)
    created_at = now()
    provider_exp_at = parse_provider_expiry(detail.get("expiredTime"))
    if not provider_exp_at or provider_exp_at <= created_at:
        provider_exp_at = created_at + timedelta(seconds=int(ocfg.get("order_expiry_seconds") or 900))
    exp_at = dapetotp_expiry(provider_exp_at, created_at)
    if exp_at <= created_at:
        exp_at = created_at
    raw_service_logo = (detail.get("service") or {}).get("imageUrl")
    provider_invoice_no = detail.get("invoiceNo") or listing.get("invoiceNo")
    site = await get_settings("site")
    doc = {
        "user_id": str(user["_id"]), "activation_id": activation_id,
        "invoice_no": branded_order_no(site.get("site_name"), provider_invoice_no),
        "provider_invoice_no": provider_invoice_no,
        "phone_number": detail.get("phoneNumber"), "service_name": (detail.get("service") or {}).get("name") or body.service_name,
        "service_code": svc_code,
        "service_logo_source": raw_service_logo,
        "service_logo": public_service_logo(svc_code, raw_service_logo),
        "country_name": (detail.get("country") or {}).get("name") or body.country_name,
        "country_logo": (detail.get("country") or {}).get("imageUrl"),
        "price": price, "provider_price": provider_price,
        "provider_cost_after_tax": breakdown["cost_after_tax"],
        "estimated_profit": price - breakdown["cost_after_tax"],
        "status": "pending", "otp_codes": [],
        "source": source, "payment_method": payment_method, "payment_ref": payment_ref,
        "refunded": False, "created_at": created_at,
        "cancel_available_at": created_at + timedelta(seconds=cooldown),
        "expires_at": exp_at,
        "provider_expires_at": provider_exp_at,
    }
    res = await db.orders.insert_one(doc)
    await db.transactions.insert_one({"user_id": str(user["_id"]), "type": "purchase", "amount": -price,
                                      "ref": str(res.inserted_id), "note": f"Beli nomor {doc['service_name']}", "created_at": now()})
    await log_activity(user["_id"], "order.create", {"order_id": str(res.inserted_id)})
    n = await get_settings("notifications")
    if n.get("notify_order"):
        try:
            await telegram_send(n.get("telegram_bot_token"), n.get("telegram_chat_id"),
                                f"🛒 <b>Pembelian nomor</b>\n{doc['service_name']} · {doc['country_name']}\n"
                                f"Nomor: <code>{doc['phone_number']}</code>\nHarga: Rp{price:,}\nUser: {user.get('email')}")
        except Exception as e:
            logger.error("telegram order gagal: %s", e)
    return clean({**doc, "_id": res.inserted_id})


async def refund_order(order: dict, status: str, note: str):
    res = await db.orders.update_one({"_id": order["_id"], "refunded": {"$ne": True}},
                                     {"$set": {"status": status, "refunded": True}})
    if res.modified_count:
        await db.users.update_one({"_id": oid(order["user_id"])}, {"$inc": {"balance": int(order["price"])}})
        await db.transactions.insert_one({"user_id": order["user_id"], "type": "refund", "amount": int(order["price"]),
                                          "ref": str(order["_id"]), "note": note, "created_at": now()})
    else:
        await db.orders.update_one({"_id": order["_id"]}, {"$set": {"status": status}})
    order["status"] = status
    order["refunded"] = True
    return order


_ongoing_cache = {"at": None, "rows": []}


async def ongoing_rows(cfg: dict, force: bool = False):
    """Daftar activation berjalan di provider (di-cache 4 detik)."""
    if not force and _ongoing_cache["at"] and (now() - _ongoing_cache["at"]).total_seconds() < 4:
        return _ongoing_cache["rows"]
    try:
        data = await smsv_request(cfg.get("api_key"), "GET", "/v1/public/orders/ongoing-activation",
                                  params={"page": 1, "pageSize": 100}, timeout=int(cfg.get("timeout_seconds") or 30))
    except IntegrationError:
        return None
    rows = data if isinstance(data, list) else (data or {}).get("data", [])
    _ongoing_cache["at"] = now()
    _ongoing_cache["rows"] = rows
    return rows


def row_codes(row: dict) -> list:
    out = []
    for o in row.get("orderDetailOtp") or []:
        if isinstance(o, dict):
            code = o.get("otp") or o.get("otpCode")
            if code and code not in out:
                out.append(code)
    return out


async def auto_expire_order(order: dict, cfg: dict) -> dict:
    """Tandai expired di DapetOTP lalu batalkan activation provider selagi buffer 10% masih ada.

    Refund hanya dilakukan setelah cancel provider berhasil. Jika provider sedang error, status tetap
    `expired` untuk user dan worker akan mencoba cancel lagi pada scan berikutnya.
    """
    if order.get("otp_codes"):
        return order

    attempt_at = now()
    stale_before = attempt_at - timedelta(seconds=15)
    claim = await db.orders.update_one(
        {
            "_id": order["_id"],
            "status": {"$in": ["pending", "expired"]},
            "refunded": {"$ne": True},
            "$and": [
                {"$or": [{"otp_codes": {"$exists": False}}, {"otp_codes": []}]},
                {"$or": [
                    {"auto_cancel_claimed_at": {"$exists": False}},
                    {"auto_cancel_claimed_at": {"$lt": stale_before}},
                ]},
            ],
        },
        {"$set": {
            "status": "expired",
            "auto_cancel_claimed_at": attempt_at,
            "auto_cancel_attempt_at": attempt_at,
        }},
    )
    if not claim.modified_count:
        fresh = await db.orders.find_one({"_id": order["_id"]})
        return fresh or order

    fresh = await db.orders.find_one({"_id": order["_id"]})
    if not fresh:
        return order
    if fresh.get("otp_codes"):
        await db.orders.update_one({"_id": fresh["_id"]}, {"$unset": {"auto_cancel_claimed_at": ""}})
        return fresh

    try:
        if fresh.get("activation_id"):
            await smsv_request(
                cfg.get("api_key"),
                "PUT",
                f"/v1/public/orders/cancel/{fresh['activation_id']}",
                timeout=int(cfg.get("timeout_seconds") or 30),
            )
    except IntegrationError as e:
        logger.warning("auto cancel provider gagal untuk order %s: %s", fresh.get("_id"), e)
        await db.orders.update_one(
            {"_id": fresh["_id"]},
            {
                "$set": {"status": "expired", "auto_cancel_last_error": str(e), "auto_cancel_attempt_at": attempt_at},
                "$unset": {"auto_cancel_claimed_at": ""},
            },
        )
        fresh["status"] = "expired"
        fresh["auto_cancel_last_error"] = str(e)
        return fresh

    cancelled_at = now()
    await db.orders.update_one(
        {"_id": fresh["_id"]},
        {
            "$set": {"provider_cancelled_at": cancelled_at, "auto_cancel_attempt_at": attempt_at},
            "$unset": {"auto_cancel_claimed_at": "", "auto_cancel_last_error": ""},
        },
    )
    fresh["provider_cancelled_at"] = cancelled_at
    return await refund_order(fresh, "expired", "Refund otomatis (batas waktu DapetOTP, provider dibatalkan)")


async def refresh_order(order: dict) -> dict:
    # Hanya `expired` dari mekanisme auto-cancel baru yang boleh retry. Data expired lama tetap terminal.
    if order["status"] in ("success", "cancelled", "refunded"):
        return order
    if order["status"] == "expired" and (order.get("refunded") or not order.get("auto_cancel_attempt_at")):
        return order
    cfg = await get_settings("smsvirtual")
    rows = await ongoing_rows(cfg)

    if rows is not None:
        row = next((r for r in rows if r.get("id") == order["activation_id"]), None)
        if row:
            codes = row_codes(row)
            upd = {"otp_codes": codes, "phone_number": row.get("phoneNumber") or order.get("phone_number"),
                   "provider_status": row.get("status")}
            provider_exp_at = parse_provider_expiry(row.get("expiredTime"))
            if provider_exp_at:
                # Ikuti expiredTime provider secara dinamis, tetapi sisakan 10% untuk auto-cancel.
                upd["provider_expires_at"] = provider_exp_at
                upd["expires_at"] = dapetotp_expiry(provider_exp_at, order.get("created_at") or now())
            st = row.get("status")
            if st in (5, 8):
                await db.orders.update_one({"_id": order["_id"]}, {"$set": upd})
                order.update(upd)
                final_status = "expired" if order.get("status") == "expired" else "cancelled"
                note = "Refund otomatis (auto-cancel DapetOTP)" if final_status == "expired" else "Refund otomatis (dibatalkan di provider)"
                return await refund_order(order, final_status, note)
            if st == 6:
                await db.orders.update_one({"_id": order["_id"]}, {"$set": upd})
                order.update(upd)
                return await refund_order(order, "expired", "Refund order kedaluwarsa")
            await db.orders.update_one({"_id": order["_id"]}, {"$set": upd})
            order.update(upd)

            # Kalau OTP ternyata masuk tepat di sekitar cutoff, jangan cancel/refund order yang sudah punya OTP.
            if codes and order.get("status") == "expired" and not order.get("refunded"):
                await db.orders.update_one({"_id": order["_id"]}, {
                    "$set": {"status": "success"},
                    "$unset": {"auto_cancel_claimed_at": "", "auto_cancel_last_error": ""},
                })
                order["status"] = "success"
                return order
        else:
            # activation sudah keluar dari daftar berjalan
            if order.get("otp_codes"):
                await db.orders.update_one({"_id": order["_id"]}, {"$set": {"status": "success"}})
                order["status"] = "success"
                return order
            exp = order.get("expires_at")
            expired = bool(order.get("status") == "expired" or (exp and (exp if exp.tzinfo else exp.replace(tzinfo=timezone.utc)) <= now()))
            return await refund_order(
                order,
                "expired" if expired else "cancelled",
                "Refund otomatis (batas waktu DapetOTP)" if expired else "Refund otomatis (pesanan berakhir di provider)",
            )

    exp = order.get("expires_at")
    locally_expired = bool(exp and (exp if exp.tzinfo else exp.replace(tzinfo=timezone.utc)) <= now())
    if locally_expired and order.get("status") in ("pending", "expired") and not order.get("otp_codes"):
        return await auto_expire_order(order, cfg)
    return order


def public_order(doc: dict, site_name: Optional[str] = None) -> dict:
    """Data pesanan untuk pelanggan tanpa detail integrasi internal.

    site_name opsional juga membuat order lama langsung mengikuti brand navbar saat
    ditampilkan, tanpa perlu migrasi database.
    """
    d = clean(doc) if "_id" in doc else dict(doc)
    if site_name and d.get("invoice_no"):
        raw_for_suffix = d.get("provider_invoice_no") or d.get("invoice_no")
        d["invoice_no"] = branded_order_no(site_name, raw_for_suffix)
    for k in (
        "activation_id", "provider_price", "provider_invoice_no", "provider_status", "provider_expires_at", "provider_cancelled_at",
        "auto_cancel_claimed_at", "auto_cancel_attempt_at", "auto_cancel_last_error",
        "source", "country_logo", "service_logo_source", "service_code",
    ):
        d.pop(k, None)
    logo = str(d.get("service_logo") or "")
    if logo and not logo.startswith("/api/catalog/logo/"):
        d["service_logo"] = ""
    return d


@api.post("/orders")
async def post_order(body: OrderIn, user: dict = Depends(get_current_user)):
    site = await get_settings("site")
    return public_order(await create_number_order(user, body, "web"), site.get("site_name"))


@api.get("/orders")
async def list_orders(user: dict = Depends(get_current_user)):
    docs = await db.orders.find({"user_id": str(user["_id"])}).sort("created_at", -1).to_list(100)
    site = await get_settings("site")
    return {"items": [public_order(await refresh_order(d), site.get("site_name")) for d in docs]}


@api.get("/orders/{order_id}")
async def get_order(order_id: str, user: dict = Depends(get_current_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")
    site = await get_settings("site")
    return public_order(await refresh_order(d), site.get("site_name"))


@api.post("/orders/{order_id}/cancel")
async def cancel_order(order_id: str, user: dict = Depends(get_current_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")
    if d["status"] != "pending":
        raise HTTPException(400, "Order tidak dapat dibatalkan")
    ocfg = await get_settings("orders")
    if not ocfg.get("allow_manual_cancel", True):
        raise HTTPException(400, "Pembatalan manual dinonaktifkan admin")
    avail = d.get("cancel_available_at")
    if avail:
        left = (avail.replace(tzinfo=timezone.utc) - now()).total_seconds()
        if left > 0:
            raise HTTPException(400, f"Pembatalan baru tersedia dalam {int(left)} detik")
    if d.get("otp_codes"):
        raise HTTPException(400, "OTP sudah diterima, pesanan tidak bisa dibatalkan")
    cfg = await get_settings("smsvirtual")
    if d.get("activation_id"):
        try:
            await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/cancel/{d['activation_id']}")
        except IntegrationError as e:
            msg = str(e)
            if "not allow" in msg.lower() or "status" in msg.lower():
                raise HTTPException(400, "Nomor belum bisa dibatalkan sekarang. Saldo dikembalikan otomatis jika OTP tidak masuk sampai waktu habis.")
            logger.warning("order cancel gagal: %s", e)
            raise HTTPException(400, "Pesanan belum bisa dibatalkan sekarang. Coba lagi sebentar.")
    if not d.get("refunded"):
        await db.users.update_one({"_id": user["_id"]}, {"$inc": {"balance": d["price"]}})
        await db.transactions.insert_one({"user_id": str(user["_id"]), "type": "refund", "amount": d["price"],
                                          "ref": order_id, "note": "Refund pembatalan", "created_at": now()})
    await db.orders.update_one({"_id": d["_id"]}, {"$set": {"status": "cancelled", "refunded": True}})
    return {"ok": True}


@api.post("/orders/{order_id}/ready")
async def ready_order(order_id: str, user: dict = Depends(get_current_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")

    # Endpoint dibuat idempotent. Bila ready sudah pernah berhasil, klik ulang tidak perlu meneruskan ke provider.
    if d.get("ready"):
        return {"ok": True, "already_ready": True}

    # OTP yang sudah tersimpan berarti activation sudah melewati tahap ready; jangan panggil provider lagi.
    if d.get("otp_codes"):
        await db.orders.update_one({"_id": d["_id"]}, {"$set": {"ready": True}})
        return {"ok": True, "already_ready": True, "otp_received": True}

    if d.get("status") != "pending":
        raise HTTPException(400, "Pesanan ini sudah tidak menunggu OTP")

    cfg = await get_settings("smsvirtual")

    # Sinkronkan sekali sebelum PUT /ready. Ini menutup race saat OTP baru saja masuk di provider
    # tetapi polling 3 detik frontend belum sempat memperbarui database lokal.
    rows = await ongoing_rows(cfg, force=True)
    if rows is not None:
        row = next((r for r in rows if r.get("id") == d.get("activation_id")), None)
        if row:
            codes = row_codes(row)
            if codes:
                await db.orders.update_one(
                    {"_id": d["_id"]},
                    {"$set": {
                        "otp_codes": codes,
                        "phone_number": row.get("phoneNumber") or d.get("phone_number"),
                        "provider_status": row.get("status"),
                        "ready": True,
                    }},
                )
                return {"ok": True, "already_ready": True, "otp_received": True}

    try:
        await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/ready/{d['activation_id']}")
    except IntegrationError as e:
        # Provider dapat menolak /ready bila status activation berubah tepat saat request berjalan.
        # Cek ulang: jika OTP ternyata sudah ada, anggap operasi berhasil/idempotent.
        rows = await ongoing_rows(cfg, force=True)
        if rows is not None:
            row = next((r for r in rows if r.get("id") == d.get("activation_id")), None)
            if row:
                codes = row_codes(row)
                if codes:
                    await db.orders.update_one(
                        {"_id": d["_id"]},
                        {"$set": {
                            "otp_codes": codes,
                            "phone_number": row.get("phoneNumber") or d.get("phone_number"),
                            "provider_status": row.get("status"),
                            "ready": True,
                        }},
                    )
                    return {"ok": True, "already_ready": True, "otp_received": True}

        logger.warning("order ready gagal: %s", e)
        raise HTTPException(400, "Nomor belum bisa ditandai siap. Klik Cek; jika OTP sudah muncul, tombol Siap tidak diperlukan.")

    await db.orders.update_one({"_id": d["_id"]}, {"$set": {"ready": True}})
    return {"ok": True}


@api.post("/orders/{order_id}/complete")
async def complete_order(order_id: str, user: dict = Depends(get_current_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")
    cfg = await get_settings("smsvirtual")
    try:
        await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/complete/{d['activation_id']}")
    except IntegrationError as e:
        logger.warning("order complete gagal: %s", e)
        raise HTTPException(400, "Layanan sementara tidak tersedia. Coba lagi sebentar.")
    await db.orders.update_one({"_id": d["_id"]}, {"$set": {"status": "success"}})
    return {"ok": True}


@api.post("/orders/{order_id}/rating")
async def rate_order(order_id: str, body: RatingIn, user: dict = Depends(get_current_user)):
    order_oid = oid(order_id)
    uid = str(user["_id"])
    d = await db.orders.find_one({"_id": order_oid, "user_id": uid})
    if not d:
        raise HTTPException(404, "Pesanan tidak ditemukan")
    if d.get("status") != "success":
        raise HTTPException(400, "Feedback hanya tersedia untuk pesanan yang sudah selesai")
    if d.get("rating"):
        raise HTTPException(400, "Pesanan ini sudah diberi feedback")

    stars = int(body.stars)
    result = await db.orders.update_one(
        {"_id": order_oid, "user_id": uid, "status": "success", "rating": {"$exists": False}},
        {"$set": {
            "rating": stars,
            "rating_comment": body.comment.strip(),
            "rated_at": now(),
        }},
    )
    if not result.modified_count:
        raise HTTPException(400, "Pesanan ini sudah diberi feedback")

    await log_activity(user["_id"], "order.rating", {"order_id": order_id, "stars": stars})
    return {"ok": True, "stars": stars}


@api.post("/orders/{order_id}/resend")
async def resend_order(order_id: str, user: dict = Depends(get_current_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")
    cfg = await get_settings("smsvirtual")
    try:
        await smsv_request(cfg.get("api_key"), "PUT", f"/v1/public/orders/resend/{d['activation_id']}")
    except IntegrationError as e:
        logger.warning("order resend gagal: %s", e)
        raise HTTPException(400, "Layanan sementara tidak tersedia. Coba lagi sebentar.")
    return {"ok": True}


# ---------------- direct order payment ----------------
def public_direct_payment(doc: dict) -> dict:
    d = clean(doc) if "_id" in doc else dict(doc)
    if "qris" in d:
        d["payment_code"] = d.pop("qris")
    for k in (
        "paykita_id", "checkout_url", "gateway_ref", "user_id", "fulfilling", "provider_price",
        "service_code", "provider_order_id", "provider_error", "selected_service_country_price_id",
    ):
        d.pop(k, None)
    return d


def _as_aware_datetime(value):
    if isinstance(value, datetime):
        return value if value.tzinfo else value.replace(tzinfo=timezone.utc)
    if isinstance(value, str) and value:
        try:
            return datetime.fromisoformat(value.replace("Z", "+00:00"))
        except Exception:
            return None
    return None


def _direct_retry_delay(attempts: int) -> int:
    # Polling frontend boleh sering, tetapi provider jangan dihantam setiap 2-3 detik.
    return min(45, 5 * (2 ** max(0, min(int(attempts or 1) - 1, 3))))


async def direct_payment_live_candidates(payment: dict, user: dict) -> list[dict]:
    """Cari server/harga live untuk layanan yang SUDAH dibayar.

    Prioritas utama tetap serviceCountryPriceId yang dipilih saat checkout. Bila server itu
    habis setelah QR dibayar, pilih server lain untuk layanan+negara yang sama selama harga
    jual hasil kalkulasi tidak melebihi nominal yang sudah dibayar. Dengan begitu pembayaran
    langsung tidak berubah menjadi topup dan user tidak diminta membayar selisih lagi.
    """
    country_id = payment.get("country_id")
    if not country_id:
        return []

    cfg = await get_settings("smsvirtual")
    pricing = await get_settings("pricing")
    try:
        data = await smsv_request(
            cfg.get("api_key"),
            "GET",
            "/v1/public/services/list",
            params={"countryId": country_id, "page": 1, "pageSize": 500},
            timeout=int(cfg.get("timeout_seconds") or 30),
        )
    except IntegrationError as e:
        logger.warning("direct payment refresh katalog gagal: %s", e)
        return []

    items = data if isinstance(data, list) else (data or {}).get("data", [])
    wanted_code = str(payment.get("service_code") or "").strip().lower()
    wanted_name = str(payment.get("service_name") or "").strip().lower()
    original_id = str(payment.get("service_country_price_id") or "")
    paid_amount = int(payment.get("amount") or 0)
    tier = user.get("tier") or "member"

    candidates = []
    for it in items:
        svc = it.get("service") or it
        code = str(svc.get("code") or "").strip()
        name = str(svc.get("name") or "").strip()
        code_match = bool(wanted_code and code.lower() == wanted_code)
        name_match = bool(wanted_name and name.lower() == wanted_name)
        if not (code_match or name_match):
            continue

        p = await pricing_for(code, pricing, tier)
        for row in it.get("prices") or []:
            price_id = row.get("id")
            if not price_id:
                continue
            provider_price = row.get("promoPrice") or row.get("price") or row.get("sellPrice") or 0
            if not provider_price:
                continue
            stock = row.get("stock")
            # stock 0 jelas tidak bisa dipakai; None berarti provider tidak memberi angka stock.
            if stock is not None:
                try:
                    if float(stock) <= 0:
                        continue
                except Exception:
                    pass
            sale_price = apply_pricing(provider_price, p)
            if paid_amount and sale_price > paid_amount:
                continue
            candidates.append({
                "id": str(price_id),
                "provider_price": provider_price,
                "sale_price": int(sale_price),
                "service_code": code,
                "service_name": name,
                "stock": stock,
            })

    # Exact server dulu, lalu alternatif dengan harga jual paling dekat ke nominal yang dibayar.
    candidates.sort(key=lambda x: (
        0 if x["id"] == original_id else 1,
        abs(paid_amount - x["sale_price"]) if paid_amount else 0,
        -int(x["sale_price"]),
    ))

    seen = set()
    out = []
    for row in candidates:
        if row["id"] in seen:
            continue
        seen.add(row["id"])
        out.append(row)
    return out


async def fulfill_direct_payment(payment: dict) -> dict:
    """Buat pesanan setelah gateway menyatakan pembayaran langsung sudah lunas.

    Pembayaran yang sudah PAID tidak pernah diubah menjadi saldo. Jika server yang dipilih
    habis sesaat setelah checkout, backend akan mencari server live lain untuk layanan yang
    sama dengan harga yang masih tertutup nominal pembayaran, lalu mencoba otomatis dengan
    backoff agar provider tidak dihantam terus-menerus.
    """
    if payment.get("order_id"):
        return payment

    next_try = _as_aware_datetime(payment.get("next_fulfillment_attempt_at"))
    if next_try and next_try > now():
        return payment

    lock = await db.direct_payments.update_one(
        {"_id": payment["_id"], "order_id": {"$exists": False}, "fulfilling": {"$ne": True}},
        {"$set": {"fulfilling": True}},
    )
    if not lock.modified_count:
        return await db.direct_payments.find_one({"_id": payment["_id"]}) or payment

    attempts = int(payment.get("fulfillment_attempts") or 0) + 1
    last_public_error = "Pesanan belum berhasil dibuat. Sistem akan mencoba lagi otomatis."
    last_provider_error = None

    try:
        user = await db.users.find_one({"_id": oid(payment["user_id"])})
        if not user:
            raise HTTPException(404, "Pengguna pembayaran tidak ditemukan")

        # Refresh katalog setiap attempt. Ini mengatasi serviceCountryPriceId yang masih valid
        # saat QR dibuat tetapi stok/server-nya habis ketika webhook PAID masuk.
        candidates = await direct_payment_live_candidates(payment, user)
        original_id = str(payment.get("service_country_price_id") or "")
        if not candidates and original_id:
            # Tetap coba pilihan awal saat endpoint katalog provider sedang bermasalah.
            candidates = [{"id": original_id, "service_code": payment.get("service_code")}]

        # Maksimal 4 server per siklus; siklus berikutnya akan refresh katalog lagi.
        for candidate in candidates[:4]:
            candidate_id = str(candidate.get("id") or "")
            if not candidate_id:
                continue
            try:
                order = await create_number_order(
                    user,
                    OrderIn(
                        service_country_price_id=candidate_id,
                        service_name=payment.get("service_name") or "",
                        country_name=payment.get("country_name") or "",
                        operator_id=payment.get("operator_id"),
                    ),
                    "direct_payment",
                    charge_balance=False,
                    forced_price=int(payment["amount"]),
                    payment_method="direct",
                    payment_ref=str(payment["_id"]),
                )
                await db.direct_payments.update_one(
                    {"_id": payment["_id"]},
                    {"$set": {
                        "order_id": order["id"],
                        "fulfilled_at": now(),
                        "fulfilling": False,
                        "fulfillment_error": None,
                        "provider_error": None,
                        "selected_service_country_price_id": candidate_id,
                        "fulfillment_attempts": attempts,
                        "last_fulfillment_attempt_at": now(),
                    }, "$unset": {"next_fulfillment_attempt_at": ""}},
                )
                return await db.direct_payments.find_one({"_id": payment["_id"]}) or payment
            except DirectProviderOrderError as e:
                last_provider_error = str(e)
                low = last_provider_error.lower()
                logger.warning(
                    "direct payment provider menolak payment=%s candidate=%s: %s",
                    payment.get("_id"), candidate_id, last_provider_error,
                )
                if any(word in low for word in ("balance", "saldo", "credit", "kredit", "insufficient")):
                    # Ganti server tidak akan menyelesaikan saldo/kredit provider yang habis.
                    last_public_error = "Provider layanan belum dapat memproses pesanan. Admin perlu memeriksa saldo/kredit provider."
                    break
                last_public_error = "Layanan/server yang dipilih sedang tidak tersedia. Sistem mencari server lain otomatis."
                continue
            except HTTPException as e:
                last_public_error = str(e.detail)
                logger.warning(
                    "direct payment fulfillment gagal payment=%s candidate=%s: %s",
                    payment.get("_id"), candidate_id, e.detail,
                )
                continue
            except Exception as e:
                last_provider_error = str(e)
                logger.exception(
                    "direct payment fulfillment exception payment=%s candidate=%s: %s",
                    payment.get("_id"), candidate_id, e,
                )
                continue

        if not candidates:
            last_public_error = "Layanan yang dibayar sedang kehabisan server/nomor. Menunggu stok live berikutnya."

    except HTTPException as e:
        last_public_error = str(e.detail)
    except Exception as e:
        last_provider_error = str(e)
        logger.exception("fulfill direct payment gagal: %s", e)

    delay = _direct_retry_delay(attempts)
    update = {
        "fulfilling": False,
        "fulfillment_error": last_public_error,
        "fulfillment_attempts": attempts,
        "last_fulfillment_attempt_at": now(),
        "next_fulfillment_attempt_at": now() + timedelta(seconds=delay),
    }
    if last_provider_error:
        update["provider_error"] = last_provider_error[:500]
    await db.direct_payments.update_one({"_id": payment["_id"]}, {"$set": update})
    return await db.direct_payments.find_one({"_id": payment["_id"]}) or payment


async def mark_direct_payment_paid(payment: dict) -> dict:
    if payment.get("status") != "paid":
        await db.direct_payments.update_one(
            {"_id": payment["_id"]},
            {"$set": {"status": "paid", "paid_at": now()}},
        )
        payment = await db.direct_payments.find_one({"_id": payment["_id"]}) or payment
    return await fulfill_direct_payment(payment)


@api.post("/order-payments")
async def create_direct_payment(body: DirectOrderPaymentIn, user: dict = Depends(get_current_user)):
    quote = await quote_direct_order(user, body)
    amount = int(quote["price"])
    if amount <= 0:
        raise HTTPException(400, "Harga layanan tidak valid")

    pk = await get_settings("paykita")
    site = await get_settings("site")
    ref = branded_order_no(site.get("site_name"))
    ttl = int(pk.get("order_ttl_seconds") or 900)
    webhook = f"{os.environ.get('FRONTEND_URL', '')}/api/webhooks/paykita"
    try:
        data = await paykita_create_order(pk.get("api_key"), amount, ref, webhook, ttl)
    except IntegrationError as e:
        logger.warning("direct payment create gagal: %s", e)
        raise HTTPException(502, "Pembayaran sementara tidak tersedia. Coba lagi sebentar.")

    doc = {
        "user_id": str(user["_id"]),
        "reference": ref,
        "amount": amount,
        "pay_amount": data.get("pay_amount"),
        "paykita_id": data.get("id"),
        "qris": data.get("qris"),
        "checkout_url": data.get("checkout_url"),
        "gateway_ref": data.get("id"),
        "status": "pending",
        "service_country_price_id": body.service_country_price_id,
        "country_id": body.country_id,
        "service_name": quote.get("service_name") or body.service_name,
        "service_code": quote.get("service_code"),
        "country_name": quote.get("country_name") or body.country_name,
        "operator_id": body.operator_id,
        "provider_price": quote.get("provider_price"),
        "created_at": now(),
        "expires_at": now() + timedelta(seconds=ttl),
    }
    res = await db.direct_payments.insert_one(doc)
    return public_direct_payment({**doc, "_id": res.inserted_id})


@api.get("/order-payments/{payment_id}")
async def get_direct_payment(payment_id: str, user: dict = Depends(get_current_user)):
    d = await db.direct_payments.find_one({"_id": oid(payment_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Pembayaran tidak ditemukan")

    if d.get("status") == "pending":
        pk = await get_settings("paykita")
        try:
            remote = await paykita_get_order(pk.get("api_key"), d["paykita_id"])
            remote_status = remote.get("status")
            if remote_status == "paid":
                d = await mark_direct_payment_paid(d)
            elif remote_status in ("expired", "cancelled"):
                await db.direct_payments.update_one({"_id": d["_id"]}, {"$set": {"status": remote_status}})
                d["status"] = remote_status
        except IntegrationError:
            pass
    elif d.get("status") == "paid" and not d.get("order_id"):
        # Retry fulfillment ketika gateway sudah lunas tetapi provider sempat belum siap.
        d = await fulfill_direct_payment(d)

    return public_direct_payment(d)


@api.post("/order-payments/{payment_id}/cancel")
async def cancel_direct_payment(payment_id: str, user: dict = Depends(get_current_user)):
    d = await db.direct_payments.find_one({"_id": oid(payment_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Pembayaran tidak ditemukan")
    if d.get("status") != "pending":
        raise HTTPException(400, "Pembayaran ini tidak bisa dibatalkan")
    await db.direct_payments.update_one({"_id": d["_id"]}, {"$set": {"status": "cancelled"}})
    return {"ok": True}


# ---------------- topup ----------------
async def create_topup(user: dict, amount: int, source: str):
    t = await get_settings("topup")
    pk = await get_settings("paykita")
    if amount < int(t["min_amount"]) or amount > int(t["max_amount"]):
        raise HTTPException(400, f"Nominal harus antara Rp{int(t['min_amount']):,} dan Rp{int(t['max_amount']):,}".replace(",", "."))
    ref = "TOP-" + secrets.token_hex(5).upper()
    webhook = f"{os.environ.get('FRONTEND_URL', '')}/api/webhooks/paykita"
    try:
        data = await paykita_create_order(pk.get("api_key"), amount, ref, webhook, int(pk.get("order_ttl_seconds") or 900))
    except IntegrationError as e:
        logger.warning("topup create gagal: %s", e)
        raise HTTPException(502, "Pembayaran sementara tidak tersedia. Coba lagi sebentar.")
    doc = {"user_id": str(user["_id"]), "reference": ref, "amount": amount, "pay_amount": data.get("pay_amount"),
           "paykita_id": data.get("id"), "qris": data.get("qris"), "checkout_url": data.get("checkout_url"),
           "status": "pending", "source": source, "credited": False, "created_at": now(),
           "expires_at": now() + timedelta(seconds=int(pk.get("order_ttl_seconds") or 900)),
           "gateway_ref": data.get("id")}
    res = await db.topups.insert_one(doc)
    return clean({**doc, "_id": res.inserted_id})


async def credit_topup(topup: dict):
    if topup.get("credited"):
        return
    await db.topups.update_one({"_id": topup["_id"]}, {"$set": {"status": "paid", "credited": True, "paid_at": now()}})
    await db.users.update_one({"_id": oid(topup["user_id"])}, {"$inc": {"balance": int(topup["amount"])}})
    await db.transactions.insert_one({"user_id": topup["user_id"], "type": "topup", "amount": int(topup["amount"]),
                                      "ref": str(topup["_id"]), "note": f"Isi saldo {topup['reference']}", "created_at": now()})
    n = await get_settings("notifications")
    if n.get("notify_topup"):
        try:
            await telegram_send(n.get("telegram_bot_token"), n.get("telegram_chat_id"),
                                f"💰 <b>Isi saldo berhasil</b>\nRef: {topup['reference']}\nJumlah: Rp{int(topup['amount']):,}")
        except Exception:
            pass


def public_topup(doc: dict) -> dict:
    """Sembunyikan detail gateway dan nama field internal dari pengguna."""
    d = clean(doc) if "_id" in doc else dict(doc)
    if "qris" in d:
        d["payment_code"] = d.pop("qris")
    for k in ("paykita_id", "checkout_url", "source", "gateway_ref", "credited", "user_id"):
        d.pop(k, None)
    return d


@api.post("/topups")
async def post_topup(body: TopupIn, user: dict = Depends(get_current_user)):
    return public_topup(await create_topup(user, int(body.amount), "web"))


@api.get("/topups")
async def list_topups(user: dict = Depends(get_current_user)):
    docs = await db.topups.find({"user_id": str(user["_id"])}).sort("created_at", -1).to_list(50)
    return {"items": [public_topup(d) for d in docs]}


@api.get("/topups/{topup_id}")
async def get_topup(topup_id: str, user: dict = Depends(get_current_user)):
    d = await db.topups.find_one({"_id": oid(topup_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Tidak ditemukan")
    if d["status"] == "pending":
        pk = await get_settings("paykita")
        try:
            remote = await paykita_get_order(pk.get("api_key"), d["paykita_id"])
            if remote.get("status") == "paid":
                await credit_topup(d)
                d = await db.topups.find_one({"_id": d["_id"]})
            elif remote.get("status") in ("expired", "cancelled"):
                await db.topups.update_one({"_id": d["_id"]}, {"$set": {"status": remote["status"]}})
                d["status"] = remote["status"]
        except IntegrationError:
            pass
    return public_topup(d)


@api.post("/topups/{topup_id}/cancel")
async def cancel_topup(topup_id: str, user: dict = Depends(get_current_user)):
    d = await db.topups.find_one({"_id": oid(topup_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Tidak ditemukan")
    if d["status"] != "pending":
        raise HTTPException(400, "Pembayaran ini tidak bisa dibatalkan")
    await db.topups.update_one({"_id": d["_id"]}, {"$set": {"status": "cancelled"}})
    return {"ok": True}


@api.post("/webhooks/paykita")
async def paykita_webhook(request: Request):
    raw = await request.body()
    pk = await get_settings("paykita")
    sec = await get_settings("security")
    secret = pk.get("webhook_secret") or sec.get("webhook_secret")
    ts = request.headers.get("x-paykita-timestamp", "")
    sig = request.headers.get("x-paykita-signature", "")
    if not secret:
        raise HTTPException(400, "Webhook secret belum diatur di pengaturan PayKita/Keamanan")
    if not paykita_verify_signature(secret, ts, raw, sig):
        raise HTTPException(401, "Signature tidak valid")
    body = await request.json()
    data = body.get("data") or {}
    t = await db.topups.find_one({"paykita_id": data.get("order_id")})
    if t and data.get("status") == "paid":
        await credit_topup(t)
        return {"ok": True}

    direct = await db.direct_payments.find_one({"paykita_id": data.get("order_id")})
    if direct and data.get("status") == "paid":
        await mark_direct_payment_paid(direct)
    return {"ok": True}


@api.get("/transactions")
async def transactions(user: dict = Depends(get_current_user)):
    docs = await db.transactions.find({"user_id": str(user["_id"])}).sort("created_at", -1).to_list(100)
    return {"items": [clean(d) for d in docs]}


# ---------------- tickets ----------------
@api.post("/tickets")
async def create_ticket(body: TicketIn, user: dict = Depends(get_current_user)):
    doc = {"user_id": str(user["_id"]), "user_email": user["email"], "subject": body.subject, "category": body.category,
           "message": body.message, "status": "open", "replies": [], "created_at": now(), "updated_at": now()}
    res = await db.tickets.insert_one(doc)
    await notify_ticket(doc, user, "baru")
    return clean({**doc, "_id": res.inserted_id})


@api.get("/tickets")
async def my_tickets(user: dict = Depends(get_current_user)):
    docs = await db.tickets.find({"user_id": str(user["_id"])}).sort("updated_at", -1).to_list(100)
    return {"items": [clean(d) for d in docs]}


@api.post("/tickets/{ticket_id}/reply")
async def reply_ticket(ticket_id: str, body: ReplyIn, user: dict = Depends(get_current_user)):
    q = {"_id": oid(ticket_id)}
    if user.get("role") != "admin":
        q["user_id"] = str(user["_id"])
    t = await db.tickets.find_one(q)
    if not t:
        raise HTTPException(404, "Tiket tidak ditemukan")
    reply = {"by": user.get("role"), "name": user.get("name"), "message": body.message, "at": now().isoformat()}
    await db.tickets.update_one({"_id": t["_id"]}, {"$push": {"replies": reply},
                                                   "$set": {"updated_at": now(), "status": "answered" if user.get("role") == "admin" else "open"}})
    if user.get("role") != "admin":
        await notify_ticket({**t, "message": body.message}, user, "balasan")
    return {"ok": True}


# ---------------- public API v1 (x-api-key) ----------------
v1 = APIRouter(prefix="/api/v1")


@v1.get("/profile")
async def v1_profile(user: dict = Depends(api_key_user)):
    return {"data": {"name": user.get("name"), "email": user.get("email"), "balance": user.get("balance", 0)}, "message": "success"}


@v1.get("/countries")
async def v1_countries(user: dict = Depends(api_key_user)):
    return {"data": (await countries())["items"], "message": "success"}


@v1.get("/services")
async def v1_services(request: Request, country_id: str, user: dict = Depends(api_key_user)):
    return {"data": (await services(request, country_id, tier_override=user.get("tier") or "member"))["items"], "message": "success"}


@v1.post("/orders")
async def v1_order(body: OrderIn, user: dict = Depends(api_key_user)):
    return {"data": await create_number_order(user, body, "api"), "message": "success"}


@v1.get("/orders/{order_id}")
async def v1_order_status(order_id: str, user: dict = Depends(api_key_user)):
    d = await db.orders.find_one({"_id": oid(order_id), "user_id": str(user["_id"])})
    if not d:
        raise HTTPException(404, "Order tidak ditemukan")
    return {"data": clean(await refresh_order(d)), "message": "success"}


@v1.post("/orders/{order_id}/ready")
async def v1_ready(order_id: str, user: dict = Depends(api_key_user)):
    return {"data": await ready_order(order_id, user), "message": "success"}


@v1.post("/orders/{order_id}/cancel")
async def v1_cancel(order_id: str, user: dict = Depends(api_key_user)):
    return {"data": await cancel_order(order_id, user), "message": "success"}


@v1.post("/orders/{order_id}/resend")
async def v1_resend(order_id: str, user: dict = Depends(api_key_user)):
    return {"data": await resend_order(order_id, user), "message": "success"}


@v1.post("/orders/{order_id}/complete")
async def v1_complete(order_id: str, user: dict = Depends(api_key_user)):
    return {"data": await complete_order(order_id, user), "message": "success"}


@v1.post("/topups")
async def v1_topup(body: TopupIn, user: dict = Depends(api_key_user)):
    return {"data": public_topup(await create_topup(user, int(body.amount), "api")), "message": "success"}


@v1.get("/topups/{topup_id}")
async def v1_topup_status(topup_id: str, user: dict = Depends(api_key_user)):
    return {"data": await get_topup(topup_id, user), "message": "success"}


# ---------------- blog ----------------
def slugify_blog(value: str) -> str:
    slug = re.sub(r"[^a-z0-9]+", "-", str(value or "").strip().lower()).strip("-")
    return slug[:180] or f"artikel-{secrets.token_hex(3)}"


def validate_blog_cover(value: str):
    v = (value or "").strip()
    if not v:
        return
    if v.startswith("data:image/"):
        if len(v) > 5_700_000:
            raise HTTPException(400, "Ukuran cover maksimal 4 MB")
        return
    if not (v.startswith("https://") or v.startswith("http://")):
        raise HTTPException(400, "Cover harus berupa URL http(s) atau gambar lokal")


@api.get("/blog")
async def public_blog():
    docs = await db.blog_posts.find({"published": True}).sort("published_at", -1).to_list(100)
    return {"items": [clean(d) for d in docs]}


def public_blog_comment(doc: dict) -> dict:
    return {
        "id": str(doc.get("_id")),
        "parent_id": str(doc.get("parent_id")) if doc.get("parent_id") else None,
        "author_name": doc.get("author_name") or "Pengguna",
        "author_role": doc.get("author_role") or "user",
        "body": doc.get("body") or "",
        "created_at": (doc.get("created_at") if isinstance(doc.get("created_at"), str) else (doc.get("created_at") or now()).isoformat()),
    }


@api.get("/blog/{slug}/comments")
async def public_blog_comments(slug: str):
    post = await db.blog_posts.find_one({"slug": slug, "published": True}, {"_id": 1})
    if not post:
        raise HTTPException(404, "Artikel tidak ditemukan")
    docs = await db.blog_comments.find({"post_id": str(post["_id"])}).sort("created_at", 1).to_list(1000)
    return {"items": [public_blog_comment(d) for d in docs]}


@api.post("/blog/{slug}/comments")
async def create_blog_comment(slug: str, body: BlogCommentIn, user: dict = Depends(get_current_user)):
    post = await db.blog_posts.find_one({"slug": slug, "published": True}, {"_id": 1})
    if not post:
        raise HTTPException(404, "Artikel tidak ditemukan")

    text = body.body.strip()
    if not text:
        raise HTTPException(400, "Komentar tidak boleh kosong")

    parent_oid = None
    if body.parent_id:
        parent_oid = oid(body.parent_id)
        parent = await db.blog_comments.find_one({"_id": parent_oid, "post_id": str(post["_id"])}, {"_id": 1})
        if not parent:
            raise HTTPException(404, "Komentar yang dibalas tidak ditemukan")

    doc = {
        "post_id": str(post["_id"]),
        "parent_id": parent_oid,
        "user_id": str(user["_id"]),
        "author_name": (user.get("name") or "Pengguna").strip()[:120],
        "author_role": user.get("role") or "user",
        "body": text,
        "created_at": now(),
    }
    res = await db.blog_comments.insert_one(doc)
    await log_activity(user["_id"], "blog_comment", {"post_id": str(post["_id"]), "parent_id": str(parent_oid) if parent_oid else None})
    return public_blog_comment({**doc, "_id": res.inserted_id})


@api.get("/blog/{slug}")
async def public_blog_detail(slug: str):
    doc = await db.blog_posts.find_one({"slug": slug, "published": True})
    if not doc:
        raise HTTPException(404, "Artikel tidak ditemukan")
    return clean(doc)


# ---------------- admin ----------------
adm = APIRouter(prefix="/api/admin", dependencies=[Depends(require_admin)])


@adm.get("/settings")
async def adm_get_settings():
    return {k: mask(k, v) for k, v in (await all_settings()).items()}


@adm.put("/settings/{category}")
async def adm_put_settings(category: str, body: SettingsIn):
    if category not in DEFAULT_SETTINGS:
        raise HTTPException(404, "Kategori tidak dikenal")
    current = await get_settings(category)
    upd = {}
    for k, v in body.values.items():
        if k.endswith("_set"):
            continue
        if k in SECRET_KEYS and (v == "" or v == "••••••••"):
            continue
        upd[k] = v
    current.update(upd)
    await db.settings.update_one({"_id": category}, {"$set": upd}, upsert=True)
    return mask(category, current)


@adm.get("/stats")
async def adm_stats():
    users = await db.users.count_documents({})
    orders = await db.orders.count_documents({})
    success = await db.orders.count_documents({"status": "success"})
    tickets = await db.tickets.count_documents({"status": {"$ne": "closed"}})
    rev = await db.transactions.aggregate([{"$match": {"type": "topup"}}, {"$group": {"_id": None, "t": {"$sum": "$amount"}}}]).to_list(1)
    spent = await db.orders.aggregate([{"$group": {"_id": None, "t": {"$sum": "$price"}}}]).to_list(1)
    prov = await db.orders.aggregate([{"$group": {"_id": None, "t": {"$sum": "$provider_price"}}}]).to_list(1)
    daily = await db.orders.aggregate([
        {"$group": {"_id": {"$dateToString": {"format": "%Y-%m-%d", "date": "$created_at"}}, "orders": {"$sum": 1}, "revenue": {"$sum": "$price"}}},
        {"$sort": {"_id": 1}}, {"$limit": 30}]).to_list(30)
    provider_balance = None
    cfg = await get_settings("smsvirtual")
    try:
        b = await smsv_request(cfg.get("api_key"), "GET", "/v1/public/balance", timeout=15)
        provider_balance = b.get("balance") if isinstance(b, dict) else b
    except Exception:
        pass
    return {"users": users, "orders": orders, "success_orders": success, "open_tickets": tickets,
            "topup_total": (rev[0]["t"] if rev else 0), "sales_total": (spent[0]["t"] if spent else 0),
            "provider_cost": (prov[0]["t"] if prov else 0),
            "profit": (spent[0]["t"] if spent else 0) - (prov[0]["t"] if prov else 0),
            "provider_balance": provider_balance,
            "low_balance_threshold": cfg.get("low_balance_threshold"),
            "daily": [{"date": d["_id"], "orders": d["orders"], "revenue": d["revenue"]} for d in daily]}


@adm.get("/users")
async def adm_users(q: str = ""):
    flt = {"email": {"$regex": q, "$options": "i"}} if q else {}
    docs = await db.users.find(flt).sort("created_at", -1).to_list(200)
    return {"items": [clean(d) for d in docs]}


class BalanceIn(BaseModel):
    amount: int
    note: str = "Penyesuaian admin"


@adm.post("/users/{user_id}/balance")
async def adm_balance(user_id: str, body: BalanceIn):
    await db.users.update_one({"_id": oid(user_id)}, {"$inc": {"balance": int(body.amount)}})
    await db.transactions.insert_one({"user_id": user_id, "type": "adjustment", "amount": int(body.amount), "ref": "admin", "note": body.note, "created_at": now()})
    return {"ok": True}


@adm.post("/users/{user_id}/toggle")
async def adm_toggle(user_id: str):
    u = await db.users.find_one({"_id": oid(user_id)})
    if not u:
        raise HTTPException(404, "User tidak ditemukan")
    await db.users.update_one({"_id": u["_id"]}, {"$set": {"suspended": not u.get("suspended", False)}})
    return {"suspended": not u.get("suspended", False)}


@adm.get("/orders")
async def adm_orders():
    docs = await db.orders.find({}).sort("created_at", -1).to_list(200)
    return {"items": [clean(d) for d in docs]}


@adm.get("/topups")
async def adm_topups():
    docs = await db.topups.find({}).sort("created_at", -1).to_list(200)
    return {"items": [clean(d) for d in docs]}


@adm.get("/tickets")
async def adm_tickets():
    docs = await db.tickets.find({}).sort("updated_at", -1).to_list(200)
    return {"items": [clean(d) for d in docs]}


@adm.post("/tickets/{ticket_id}/close")
async def adm_close(ticket_id: str):
    await db.tickets.update_one({"_id": oid(ticket_id)}, {"$set": {"status": "closed", "updated_at": now()}})
    return {"ok": True}


class ServicePricingIn(BaseModel):
    service_name: str = ""
    markup_percent: Optional[float] = None
    fixed_fee: Optional[float] = None
    rounding_to: Optional[int] = None
    min_profit: Optional[float] = None


class AnnouncementIn(BaseModel):
    title: str = Field(min_length=1, max_length=180)
    body: str = Field(default="", max_length=30000)
    label: str = "INFORMASI"
    color: str = "sky"
    active: bool = True
    pinned: bool = False
    image_url: str = ""
    image_caption: str = Field(default="", max_length=500)


def validate_announcement_image(value: str):
    v = (value or "").strip()
    if not v:
        return
    if v.startswith("data:image/"):
        # 4 MB file menjadi sekitar 5,6 MB setelah base64.
        if len(v) > 5_700_000:
            raise HTTPException(400, "Ukuran gambar maksimal 4 MB")
        return
    if not (v.startswith("https://") or v.startswith("http://")):
        raise HTTPException(400, "URL gambar harus http(s) atau gambar lokal yang valid")
    if len(v) > 4096:
        raise HTTPException(400, "URL gambar terlalu panjang")


@adm.get("/announcements")
async def adm_announcements():
    docs = await db.announcements.find({}).sort([("pinned", -1), ("created_at", -1)]).to_list(100)
    return {"items": [clean(d) for d in docs]}


@adm.post("/announcements")
async def adm_create_announcement(body: AnnouncementIn):
    validate_announcement_image(body.image_url)
    doc = {**body.model_dump(), "created_at": now(), "updated_at": now()}
    res = await db.announcements.insert_one(doc)
    return clean({**doc, "_id": res.inserted_id})


@adm.put("/announcements/{ann_id}")
async def adm_update_announcement(ann_id: str, body: AnnouncementIn):
    validate_announcement_image(body.image_url)
    await db.announcements.update_one({"_id": oid(ann_id)}, {"$set": {**body.model_dump(), "updated_at": now()}})
    return {"ok": True}


@adm.delete("/announcements/{ann_id}")
async def adm_delete_announcement(ann_id: str):
    await db.announcements.delete_one({"_id": oid(ann_id)})
    return {"ok": True}


@adm.get("/blog")
async def adm_blog():
    docs = await db.blog_posts.find({}).sort("updated_at", -1).to_list(200)
    return {"items": [clean(d) for d in docs]}


@adm.post("/blog")
async def adm_create_blog(body: BlogIn):
    validate_blog_cover(body.cover_url)
    base = slugify_blog(body.slug or body.title)
    slug = base
    n = 2
    while await db.blog_posts.find_one({"slug": slug}, {"_id": 1}):
        slug = f"{base}-{n}"
        n += 1
    doc = {**body.model_dump(), "title": body.title.strip(), "slug": slug, "created_at": now(), "updated_at": now()}
    if body.published:
        doc["published_at"] = now()
    res = await db.blog_posts.insert_one(doc)
    return clean({**doc, "_id": res.inserted_id})


@adm.put("/blog/{post_id}")
async def adm_update_blog(post_id: str, body: BlogIn):
    validate_blog_cover(body.cover_url)
    old = await db.blog_posts.find_one({"_id": oid(post_id)})
    if not old:
        raise HTTPException(404, "Artikel tidak ditemukan")
    slug = slugify_blog(body.slug or body.title)
    conflict = await db.blog_posts.find_one({"slug": slug, "_id": {"$ne": old["_id"]}}, {"_id": 1})
    if conflict:
        raise HTTPException(409, "Slug artikel sudah digunakan")
    upd = {**body.model_dump(), "title": body.title.strip(), "slug": slug, "updated_at": now()}
    if body.published and not old.get("published_at"):
        upd["published_at"] = now()
    await db.blog_posts.update_one({"_id": old["_id"]}, {"$set": upd})
    return {"ok": True, "slug": slug}


@adm.delete("/blog/{post_id}")
async def adm_delete_blog(post_id: str):
    post_oid = oid(post_id)
    await db.blog_posts.delete_one({"_id": post_oid})
    await db.blog_comments.delete_many({"post_id": str(post_oid)})
    return {"ok": True}


class TierIn(BaseModel):
    tier: str


@adm.post("/users/{user_id}/tier")
async def adm_set_tier(user_id: str, body: TierIn):
    if body.tier not in ("member", "reseller", "vip"):
        raise HTTPException(400, "Level tidak dikenal")
    await db.users.update_one({"_id": oid(user_id)}, {"$set": {"tier": body.tier}})
    return {"tier": body.tier}


@adm.get("/catalog/services")
async def adm_catalog_services(request: Request, country_id: str):
    return await services(request, country_id, full=True)


@adm.get("/service-pricing")
async def adm_service_pricing():
    docs = await db.service_pricing.find({}).to_list(2000)
    return {"items": [{**{k: v for k, v in d.items() if k != "_id"}, "service_code": d["_id"]} for d in docs]}


@adm.put("/service-pricing/{code}")
async def adm_set_service_pricing(code: str, body: ServicePricingIn):
    # Nilai kosong berarti benar-benar hapus override. Versi sebelumnya hanya
    # mengabaikan None sehingga override lama (mis. markup 0%) tetap tersimpan
    # dan dapat menutupi markup global tanpa terlihat dari form.
    data = body.model_dump()
    custom_keys = ("markup_percent", "fixed_fee", "rounding_to", "min_profit")
    to_set = {"service_name": body.service_name}
    to_unset = {}
    for key in custom_keys:
        value = data.get(key)
        if value is None or value == "":
            to_unset[key] = ""
        else:
            to_set[key] = value

    if not any(k in to_set for k in custom_keys):
        await db.service_pricing.delete_one({"_id": code})
        return {"service_code": code, "reset": True}

    update = {"$set": to_set}
    if to_unset:
        update["$unset"] = to_unset
    await db.service_pricing.update_one({"_id": code}, update, upsert=True)
    return {"service_code": code, **to_set}


@adm.delete("/service-pricing/{code}")
async def adm_del_service_pricing(code: str):
    await db.service_pricing.delete_one({"_id": code})
    return {"ok": True}


@adm.get("/report")
async def adm_report(month: str = ""):
    """month = YYYY-MM (default bulan berjalan)."""
    m = month or now().strftime("%Y-%m")
    try:
        year, mon = int(m.split("-")[0]), int(m.split("-")[1])
        if not 1 <= mon <= 12 or not 2000 <= year <= 2999:
            raise ValueError
    except Exception:
        raise HTTPException(400, "Format bulan harus YYYY-MM (bulan 01-12)")
    start = datetime(year, mon, 1, tzinfo=timezone.utc)
    end = datetime(year + (mon == 12), (mon % 12) + 1, 1, tzinfo=timezone.utc)
    rows = await db.orders.aggregate([
        {"$match": {"created_at": {"$gte": start, "$lt": end}}},
        {"$group": {"_id": {"$dateToString": {"format": "%Y-%m-%d", "date": "$created_at"}},
                    "orders": {"$sum": 1},
                    "revenue": {"$sum": {"$cond": [{"$or": [
                        {"$in": ["$status", ["cancelled", "refunded"]]}, {"$eq": ["$refunded", True]}
                    ]}, 0, "$price"]}},
                    "cost": {"$sum": {"$cond": [{"$or": [
                        {"$in": ["$status", ["cancelled", "refunded"]]}, {"$eq": ["$refunded", True]}
                    ]}, 0, "$provider_price"]}}}},
        {"$sort": {"_id": 1}},
    ]).to_list(40)
    topups = await db.transactions.aggregate([
        {"$match": {"type": "topup", "created_at": {"$gte": start, "$lt": end}}},
        {"$group": {"_id": {"$dateToString": {"format": "%Y-%m-%d", "date": "$created_at"}}, "topup": {"$sum": "$amount"}}},
    ]).to_list(40)
    tmap = {t["_id"]: t["topup"] for t in topups}
    days = sorted({*[r["_id"] for r in rows], *tmap.keys()})
    rmap = {r["_id"]: r for r in rows}
    series = []
    for d in days:
        r = rmap.get(d, {"orders": 0, "revenue": 0, "cost": 0})
        series.append({"date": d, "orders": r["orders"], "revenue": int(r["revenue"] or 0),
                       "cost": int(r["cost"] or 0), "profit": int((r["revenue"] or 0) - (r["cost"] or 0)),
                       "topup": int(tmap.get(d, 0))})
    totals = {
        "orders": sum(s["orders"] for s in series), "revenue": sum(s["revenue"] for s in series),
        "cost": sum(s["cost"] for s in series), "profit": sum(s["profit"] for s in series),
        "topup": sum(s["topup"] for s in series),
    }
    return {"month": m, "series": series, "totals": totals}


@adm.get("/report.csv")
async def adm_report_csv(month: str = ""):
    rep = await adm_report(month)
    lines = ["tanggal,pesanan,penjualan,biaya_provider,laba,isi_saldo"]
    for s in rep["series"]:
        lines.append(f"{s['date']},{s['orders']},{s['revenue']},{s['cost']},{s['profit']},{s['topup']}")
    t = rep["totals"]
    lines.append(f"TOTAL,{t['orders']},{t['revenue']},{t['cost']},{t['profit']},{t['topup']}")
    return Response(content="\n".join(lines), media_type="text/csv",
                    headers={"Content-Disposition": f"attachment; filename=report-{rep['month']}.csv"})


@adm.get("/backup")
async def adm_backup():
    cols = ["users", "orders", "topups", "transactions", "tickets", "settings", "activity"]
    out = {}
    for c in cols:
        docs = await db[c].find({}).to_list(5000)
        out[c] = [clean(d) if "_id" in d and isinstance(d["_id"], ObjectId) else {**{k: (v.isoformat() if isinstance(v, datetime) else v) for k, v in d.items()}, "_id": str(d["_id"])} for d in docs]
    await db.settings.update_one({"_id": "backup"}, {"$set": {"last_backup_at": now().isoformat()}}, upsert=True)
    return {"generated_at": now().isoformat(), "collections": out}


@adm.post("/smtp/test")
async def adm_smtp_test(body: EmailIn):
    smtp = await get_settings("smtp")
    try:
        send_smtp_mail(smtp, body.email, "Uji SMTP dapetOTP", "<p>SMTP dapetOTP berfungsi ✅</p>")
    except Exception as e:
        raise HTTPException(400, f"Gagal kirim: {e}")
    return {"ok": True}


@adm.post("/telegram/test")
async def adm_tg_test():
    n = await get_settings("notifications")
    ok = await telegram_send(n.get("telegram_bot_token"), n.get("telegram_chat_id"), "🔔 Uji notifikasi dapetOTP berhasil.")
    if not ok:
        raise HTTPException(400, "Gagal kirim ke Telegram. Cek bot token & chat id.")
    return {"ok": True}


async def migrate_pending_order_expiry_buffer():
    """Samakan order pending lama maupun patch sebelumnya ke buffer dinamis 10%."""
    pending = await db.orders.find({
        "status": "pending",
        "expires_at": {"$exists": True},
    }).to_list(1000)
    for order in pending:
        provider_exp = parse_provider_expiry(order.get("provider_expires_at"))
        if not provider_exp:
            # Pada versi lama expires_at masih merupakan batas penuh dari provider.
            provider_exp = parse_provider_expiry(order.get("expires_at"))
        if not provider_exp:
            continue
        started_at = parse_provider_expiry(order.get("created_at")) or now()
        local_exp = dapetotp_expiry(provider_exp, started_at)
        if local_exp <= now():
            local_exp = now()
        await db.orders.update_one(
            {"_id": order["_id"], "status": "pending"},
            {"$set": {"provider_expires_at": provider_exp, "expires_at": local_exp}},
        )


async def order_expiry_worker():
    """Auto-cancel order jatuh tempo walau user menutup dashboard/browser."""
    while True:
        try:
            current = now()
            docs = await db.orders.find({
                "refunded": {"$ne": True},
                "$or": [
                    {"status": "pending", "expires_at": {"$lte": current}},
                    {"status": "expired", "auto_cancel_attempt_at": {"$exists": True}},
                ],
            }).sort("expires_at", 1).limit(100).to_list(100)
            for doc in docs:
                try:
                    await refresh_order(doc)
                except Exception as e:
                    logger.exception("worker auto-expire gagal untuk order %s: %s", doc.get("_id"), e)
        except asyncio.CancelledError:
            raise
        except Exception as e:
            logger.exception("worker auto-expire gagal: %s", e)
        await asyncio.sleep(ORDER_EXPIRY_SCAN_SECONDS)


app.include_router(api)
app.include_router(v1)
app.include_router(adm)

app.add_middleware(
    CORSMiddleware,
    allow_origins=[os.environ.get("FRONTEND_URL", "http://localhost:3000"), "http://localhost:3000"],
    allow_credentials=True, allow_methods=["*"], allow_headers=["*"],
)


@app.on_event("startup")
async def startup():
    await db.users.create_index("email", unique=True)
    await db.users.create_index("api_key")
    await db.login_attempts.create_index("identifier")
    await db.blog_comments.create_index([("post_id", 1), ("created_at", 1)])
    await db.blog_comments.create_index("parent_id")
    admin_email = os.environ["ADMIN_EMAIL"].lower()
    admin_password = os.environ["ADMIN_PASSWORD"]
    existing = await db.users.find_one({"email": admin_email})
    if not existing:
        await db.users.insert_one({"name": "Administrator", "email": admin_email, "password_hash": hash_password(admin_password),
                                   "role": "admin", "balance": 0, "email_verified": True, "auth_provider": "password",
                                   "api_key": "dot_" + secrets.token_hex(20), "created_at": now()})
    elif not verify_password(admin_password, existing.get("password_hash", "")):
        await db.users.update_one({"_id": existing["_id"]}, {"$set": {"password_hash": hash_password(admin_password), "role": "admin", "email_verified": True}})
    demo_email = "user@dapetotp.com"
    if not await db.users.find_one({"email": demo_email}):
        await db.users.insert_one({"name": "Demo User", "email": demo_email, "password_hash": hash_password("User#2026"),
                                  "role": "user", "balance": 250000, "email_verified": True, "auth_provider": "password",
                                  "api_key": "dot_demo_" + secrets.token_hex(12), "created_at": now()})

    await migrate_pending_order_expiry_buffer()

    global _order_expiry_task
    _order_expiry_task = asyncio.create_task(order_expiry_worker())


@app.on_event("shutdown")
async def shutdown():
    global _order_expiry_task
    if _order_expiry_task:
        _order_expiry_task.cancel()
        try:
            await _order_expiry_task
        except asyncio.CancelledError:
            pass
        _order_expiry_task = None
    client.close()
