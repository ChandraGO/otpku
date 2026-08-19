import hmac
import hashlib
import smtplib
import ssl
from email.message import EmailMessage

import httpx

PAYKITA_BASE = "https://pay.digikita.id/api"
SMSV_BASE = "https://api.sms-virtuals.net"


class IntegrationError(Exception):
    pass


async def paykita_create_order(api_key: str, base_amount: int, reference: str, webhook_url: str, ttl_seconds: int = 900):
    if not api_key:
        raise IntegrationError("PayKita API key belum dikonfigurasi di admin")
    payload = {"base_amount": int(base_amount), "reference": reference, "ttl_seconds": int(ttl_seconds)}
    if webhook_url and webhook_url.startswith("https://"):
        payload["webhook_url"] = webhook_url
    async with httpx.AsyncClient(timeout=30) as c:
        r = await c.post(f"{PAYKITA_BASE}/orders", json=payload, headers={"x-api-key": api_key})
    body = r.json()
    if not body.get("ok"):
        raise IntegrationError(body.get("error", {}).get("message", "PayKita error"))
    return body["data"]


async def paykita_get_order(api_key: str, order_id: str):
    if not api_key:
        raise IntegrationError("PayKita API key belum dikonfigurasi di admin")
    async with httpx.AsyncClient(timeout=30) as c:
        r = await c.get(f"{PAYKITA_BASE}/orders/{order_id}", headers={"x-api-key": api_key})
    body = r.json()
    if not body.get("ok"):
        raise IntegrationError(body.get("error", {}).get("message", "PayKita error"))
    return body["data"]


def paykita_verify_signature(secret: str, timestamp: str, raw_body: bytes, signature: str) -> bool:
    if not secret:
        return False
    expected = hmac.new(secret.encode(), f"{timestamp}.".encode() + raw_body, hashlib.sha256).hexdigest()
    got = (signature or "").replace("v1=", "")
    return hmac.compare_digest(expected, got)


async def smsv_request(api_key: str, method: str, path: str, params=None, json_body=None, timeout: int = 30):
    if not api_key:
        raise IntegrationError("SMS Virtual API key belum dikonfigurasi di admin")
    async with httpx.AsyncClient(timeout=timeout) as c:
        r = await c.request(
            method, f"{SMSV_BASE}{path}", params=params, json=json_body,
            headers={"x-api-key": api_key, "content-type": "application/json"},
        )
    try:
        body = r.json()
    except Exception:
        raise IntegrationError(f"Provider response invalid ({r.status_code})")
    if r.status_code >= 400:
        raise IntegrationError(body.get("message") or body.get("error") or "Provider error")
    return body.get("data", body)


def send_smtp_mail(cfg: dict, to_email: str, subject: str, html: str):
    host = cfg.get("host")
    if not host:
        raise IntegrationError("SMTP belum dikonfigurasi di admin")
    msg = EmailMessage()
    msg["Subject"] = subject
    msg["From"] = f"{cfg.get('from_name') or 'dapetOTP'} <{cfg.get('from_email') or cfg.get('username')}>"
    msg["To"] = to_email
    msg.set_content("Aktifkan HTML untuk melihat email ini.")
    msg.add_alternative(html, subtype="html")
    port = int(cfg.get("port") or 587)
    if cfg.get("encryption") == "ssl":
        with smtplib.SMTP_SSL(host, port, context=ssl.create_default_context(), timeout=20) as s:
            if cfg.get("username"):
                s.login(cfg["username"], cfg.get("password") or "")
            s.send_message(msg)
    else:
        with smtplib.SMTP(host, port, timeout=20) as s:
            if cfg.get("encryption") == "tls":
                s.starttls(context=ssl.create_default_context())
            if cfg.get("username"):
                s.login(cfg["username"], cfg.get("password") or "")
            s.send_message(msg)


async def telegram_send(bot_token: str, chat_id: str, text: str):
    if not bot_token or not chat_id:
        return False
    async with httpx.AsyncClient(timeout=20) as c:
        r = await c.post(
            f"https://api.telegram.org/bot{bot_token}/sendMessage",
            json={"chat_id": chat_id, "text": text, "parse_mode": "HTML"},
        )
    return r.status_code == 200
