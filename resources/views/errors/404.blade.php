<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="refresh" content="7;url={{ route('dashboard') }}">
    <title>Error 404 (not found)</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; color: #f8fafc; background: #070b16; }
        .glow { position: fixed; inset: 0; pointer-events: none; background: radial-gradient(circle at 20% 10%, rgba(124,58,237,.28), transparent 34%), radial-gradient(circle at 85% 80%, rgba(6,182,212,.22), transparent 30%); }
        .card { position: relative; width: min(680px, 100%); padding: clamp(28px, 6vw, 54px); border: 1px solid rgba(255,255,255,.12); border-radius: 32px; background: rgba(15,23,41,.86); box-shadow: 0 28px 90px rgba(0,0,0,.4); backdrop-filter: blur(22px); }
        .code { display: inline-flex; padding: 8px 14px; border-radius: 999px; background: rgba(124,58,237,.18); color: #c4b5fd; font-weight: 800; letter-spacing: .08em; }
        h1 { margin: 20px 0 12px; font-size: clamp(34px, 7vw, 58px); line-height: 1; letter-spacing: -.04em; }
        p { margin: 0; color: #cbd5e1; font-size: 16px; line-height: 1.75; }
        .by { margin-top: 28px; color: #94a3b8; font-size: 14px; }
        .by strong { display: block; margin-top: 4px; color: #e2e8f0; }
        .timer { margin-top: 24px; padding: 14px 16px; border-radius: 18px; background: rgba(255,255,255,.06); color: #e2e8f0; font-size: 14px; }
        a { color: #67e8f9; font-weight: 800; text-decoration: none; }
    </style>
</head>
<body>
<div class="glow"></div>
<main class="card" role="main">
    <span class="code">ERROR 404</span>
    <h1>Error 404 <span style="color:#94a3b8">(not found)</span></h1>
    <p>Mohon maaf, sepertinya kamu tersesat di halaman ini nih. Kami akan mengalihkan kamu kembali ke dashboard awal.</p>
    <div class="timer">Kembali ke dashboard dalam <strong id="countdown">7</strong> detik. <a href="{{ route('dashboard') }}">Kembali sekarang</a></div>
    <div class="by">by Manajemen Jagpro<strong>Chandra Dwi Hafiluddin</strong></div>
</main>
<script>
    (() => {
        let remaining = 7;
        const node = document.getElementById('countdown');
        const target = @json(route('dashboard'));
        const timer = window.setInterval(() => {
            remaining -= 1;
            if (node) node.textContent = String(Math.max(remaining, 0));
            if (remaining <= 0) {
                window.clearInterval(timer);
                window.location.replace(target);
            }
        }, 1000);
    })();
</script>
</body>
</html>
