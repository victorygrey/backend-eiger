<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EIGER CMS Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --eiger-orange: #e8500a;
            --eiger-orange-dark: #c43e00;
            --eiger-dark: #121824;
            --eiger-card: #1c2436;
            --eiger-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f141e 0%, #1a2332 50%, #0d121c 100%);
            color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* Subtle background mountain glow */
        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(232, 80, 10, 0.15) 0%, transparent 70%);
            top: -100px;
            right: -100px;
            z-index: 0;
            pointer-events: none;
        }

        body::after {
            content: '';
            position: absolute;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(2, 132, 199, 0.1) 0%, transparent 70%);
            bottom: -100px;
            left: -100px;
            z-index: 0;
            pointer-events: none;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: var(--eiger-card);
            border: 1px solid var(--eiger-border);
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(16px);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon-box {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--eiger-orange), var(--eiger-orange-dark));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
            margin-bottom: 14px;
            box-shadow: 0 8px 20px rgba(232, 80, 10, 0.35);
        }

        .brand-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #fff;
            margin-bottom: 4px;
        }

        .brand-subtitle {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #cbd5e1;
            margin-bottom: 6px;
        }

        .form-control {
            background: rgba(15, 20, 30, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: #fff;
            padding: 12px 14px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            background: rgba(15, 20, 30, 0.9);
            border-color: var(--eiger-orange);
            color: #fff;
            box-shadow: 0 0 0 3px rgba(232, 80, 10, 0.2);
        }

        .form-control::placeholder {
            color: #64748b;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--eiger-orange), var(--eiger-orange-dark));
            border: none;
            color: #fff;
            padding: 12px;
            font-weight: 600;
            border-radius: 10px;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-shadow: 0 6px 16px rgba(232, 80, 10, 0.3);
            margin-top: 8px;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #f05a14, var(--eiger-orange));
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(232, 80, 10, 0.4);
            color: #fff;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .form-check-input {
            background-color: rgba(15, 20, 30, 0.8);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .form-check-input:checked {
            background-color: var(--eiger-orange);
            border-color: var(--eiger-orange);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.75rem;
            color: #64748b;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 10px;
            font-size: 0.85rem;
            padding: 12px 14px;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            border-radius: 10px;
            font-size: 0.85rem;
            padding: 12px 14px;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon-box">
                <i class="bi bi-triangle-fill"></i>
            </div>
            <div class="brand-title">EIGER CMS</div>
            <div class="brand-subtitle">Sistem Manajemen Konten Toko & Wahana Digital</div>
        </div>

        @if(session('status'))
            <div class="alert alert-success d-flex align-items-center gap-2 mb-3">
                <i class="bi bi-check-circle-fill"></i>
                <div>{{ session('status') }}</div>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger mb-3">
                <div class="d-flex align-items-center gap-2 mb-1 fw-semibold">
                    <i class="bi bi-exclamation-triangle-fill"></i> Gagal Masuk:
                </div>
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Alamat Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary" style="border-radius: 10px 0 0 10px; border-color: rgba(255,255,255,0.12) !important;">
                        <i class="bi bi-envelope"></i>
                    </span>
                    <input type="email" name="email" id="email" class="form-control" style="border-radius: 0 10px 10px 0;" placeholder="contoh: admin@eigeradventure.com" value="{{ old('email') }}" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Kata Sandi</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary text-secondary" style="border-radius: 10px 0 0 10px; border-color: rgba(255,255,255,0.12) !important;">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input type="password" name="password" id="password" class="form-control" style="border-radius: 0 10px 10px 0;" placeholder="••••••••" required>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                    <label class="form-check-label text-secondary" for="remember" style="font-size: 0.85rem;">
                        Ingat Saya
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Sistem
            </button>
        </form>
    </div>

    <div class="login-footer">
        <div>EIGER Interactive Store Management System &bull; v1.0</div>
        <div class="mt-1 text-muted">Hak Cipta &copy; {{ date('Y') }} PT Eigerindo Multi Produk Industri</div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
