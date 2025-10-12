<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kode OTP Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2FA36B;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 0 0 8px 8px;
        }
        .otp-code {
            font-size: 32px;
            font-weight: bold;
            color: #2FA36B;
            background-color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            border: 2px dashed #2FA36B;
            margin: 20px 0;
            letter-spacing: 8px;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Permintaan Reset Password</h1>
    </div>
    
    <div class="content">
        <p>Halo <strong><?= esc($userName) ?></strong>,</p>
        
        <p>Anda telah meminta untuk mereset password akun Payoo App Anda. Silakan gunakan kode OTP (One-Time Password) berikut untuk melanjutkan:</p>
        
        <div class="otp-code">
            <?= esc($otpCode) ?>
        </div>
        
        <div class="warning">
            <strong>⚠️ Penting:</strong>
            <ul>
                <li>Kode OTP ini akan <strong>kadaluarsa dalam 15 menit</strong> untuk alasan keamanan</li>
                <li>Jangan bagikan kode ini kepada siapa pun</li>
                <li>Jika Anda tidak meminta reset password ini, silakan abaikan email ini</li>
            </ul>
        </div>
        
        <p>Untuk menyelesaikan reset password Anda, silakan:</p>
        <ol>
            <li>Kembali ke aplikasi atau website</li>
            <li>Masukkan kode OTP di atas</li>
            <li>Buat password baru Anda</li>
        </ol>
        
        <p>Jika Anda mengalami kesulitan dalam mereset password, silakan hubungi tim support kami.</p>
    </div>
    
    <div class="footer">
        <p>Salam hormat,<br>
        <strong>Tim Payoo App</strong></p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
        
        <p style="font-size: 12px; color: #999;">
            Ini adalah email otomatis. Mohon jangan membalas pesan ini.<br>
            © 2025 Payoo App. Semua hak dilindungi.
        </p>
    </div>
</body>
</html>