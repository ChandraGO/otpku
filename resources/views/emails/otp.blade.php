<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:0;background:#eef2ff;font-family:Arial,Helvetica,sans-serif;color:#172033;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#eef2ff;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;border-collapse:separate;overflow:hidden;border-radius:24px;background:#ffffff;box-shadow:0 18px 50px rgba(76,29,149,.16);">
                <tr>
                    <td style="padding:30px 34px;background:#6d28d9;background-image:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 50%,#06b6d4 100%);color:#ffffff;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="font-size:24px;font-weight:800;letter-spacing:.2px;">OTPKU</td>
                                <td align="right">
                                    <span style="display:inline-block;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.18);font-size:12px;font-weight:700;">SECURE OTP</span>
                                </td>
                            </tr>
                        </table>
                        <h1 style="margin:28px 0 8px;font-size:30px;line-height:1.2;">{{ $title }}</h1>
                        <p style="margin:0;color:#e0f2fe;font-size:15px;line-height:1.7;">Kode keamanan untuk melanjutkan proses akun Anda.</p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:34px;">
                        <p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
                            Halo{{ $recipientName !== '' ? ', '.$recipientName : '' }}!
                        </p>
                        <p style="margin:0 0 22px;color:#475569;font-size:15px;line-height:1.75;">
                            @if($isPasswordReset)
                                Gunakan kode berikut untuk membuat password baru pada akun Anda.
                            @else
                                Gunakan kode berikut untuk memverifikasi alamat email dan mengaktifkan akun Anda.
                            @endif
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px;border-radius:18px;background:#f5f3ff;border:1px solid #ddd6fe;">
                            <tr>
                                <td align="center" style="padding:26px 20px;">
                                    <div style="margin-bottom:8px;color:#7c3aed;font-size:12px;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;">Kode OTP Anda</div>
                                    <div style="font-family:'Courier New',monospace;font-size:38px;font-weight:900;letter-spacing:9px;color:#4c1d95;">{{ $code }}</div>
                                </td>
                            </tr>
                        </table>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 22px;border-radius:16px;background:#ecfeff;border:1px solid #a5f3fc;">
                            <tr>
                                <td style="padding:16px 18px;color:#155e75;font-size:14px;line-height:1.65;">
                                    <strong>Berlaku {{ $minutes }} menit.</strong> Kode hanya dapat dipakai satu kali. Jangan berikan kode ini kepada siapa pun, termasuk pihak yang mengaku sebagai admin.
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;color:#64748b;font-size:13px;line-height:1.7;">
                            Apabila Anda tidak melakukan permintaan ini, abaikan email ini. Tidak ada perubahan yang akan dilakukan pada akun Anda.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #e2e8f0;padding-top:20px;">
                            <tr>
                                <td style="color:#64748b;font-size:13px;line-height:1.6;">
                                    Website resmi:<br>
                                    <a href="{{ $websiteUrl }}" style="color:#7c3aed;font-weight:700;text-decoration:none;">{{ $websiteDomain }}</a>
                                </td>
                                <td align="right" style="color:#334155;font-size:13px;line-height:1.6;">
                                    Regards,<br>
                                    <strong style="color:#6d28d9;">OTPKU JagPro</strong>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:18px 24px;background:#0f172a;color:#94a3b8;font-size:11px;line-height:1.6;">
                        Email otomatis dari {{ $websiteDomain }}. Mohon tidak membalas email ini.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
