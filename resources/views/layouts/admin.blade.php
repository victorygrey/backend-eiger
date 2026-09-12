<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EIGER Backend Admin Console — Pusat manajemen data Digital Store EIGER">
    <title>@yield('title', 'Dashboard') — EIGER Admin Console</title>

    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --eiger-orange: #E8500A;
            --eiger-orange-dark: #C44008;
            --eiger-orange-light: #FF6B2B;
            --sidebar-width: 260px;
            --sidebar-bg: #1a1a2e;
            --sidebar-text: #cbd5e1;
            --sidebar-hover: #E8500A;
            --topbar-height: 64px;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 0;
        }

        /* ===== SIDEBAR ===== */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            text-decoration: none;
            display: block;
        }

        .sidebar-brand .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--eiger-orange);
            letter-spacing: 2px;
        }
        .sidebar-brand .brand-logo .brand-icon {
            width: 28px; height: 28px;
            display: inline-flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--eiger-orange), var(--eiger-orange-dark));
            color: #fff;
            border-radius: 6px;
            font-size: 0.85rem;
            box-shadow: 0 2px 6px rgba(232,80,10,0.4);
        }

        .sidebar-brand .brand-sub {
            font-size: 0.7rem;
            color: #64748b;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .sidebar-nav {
            padding: 16px 0;
            flex: 1;
            overflow-y: auto;
        }

        .nav-section-title {
            font-size: 0.65rem;
            font-weight: 600;
            color: #475569;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 12px 24px 6px;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 24px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 400;
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            color: #fff;
            background: rgba(232, 80, 10, 0.12);
            border-left-color: var(--eiger-orange);
        }

        .sidebar-nav .nav-link i {
            font-size: 1rem;
            width: 20px;2rem;
            color: #475569;
            line-height: 1.6nter;
            flex-shrink: 0;
        }

        .sidebar-nav .nav-link.active i {
            color: var(--eiger-orange);
        }

        .sidebar-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 0.75rem;
            color: #475569;
        }

        /* ===== MAIN CONTENT ===== */
        #main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== TOPBAR ===== */
        #topbar {
            height: var(--topbar-height);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }

        .topbar-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .topbar-title small {
            font-size: 0.8rem;
            font-weight: 400;
            color: #94a3b8;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            color: #22c55e;
            font-weight: 500;
            padding: 6px 12px;
            background: #f0fdf4;
            border-radius: 999px;
            border: 1px solid #dcfce7;
        }

        .topbar-clock {
            display: inline-flex;
            align-items: center;
            font-size: 0.78rem;
            color: #64748b;
            padding: 6px 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-weight: 500;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #22c55e;
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-green {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 0 5px rgba(34, 197, 94, 0); }
        }

        /* ===== PAGE CONTENT ===== */
        .page-content {
            padding: 28px;
            flex: 1;
        }

        /* ===== CARDS ===== */
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .card-header {
            background: #fff;
            border-bottom: 1px solid #f1f5f9;
            border-radius: 12px 12px 0 0 !important;
            padding: 16px 20px;
            font-weight: 600;
            color: #1e293b;
        }

        /* ===== STAT CARDS ===== */
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        /* ===== BUTTONS ===== */
        .btn-eiger {
            background: var(--eiger-orange);
            border: none;
            color: #fff;
            font-weight: 500;
            padding: 8px 18px;
            border-radius: 8px;
            transition: background 0.2s, transform 0.1s;
        }

        .btn-eiger:hover {
            background: var(--eiger-orange-dark);
            color: #fff;
            transform: translateY(-1px);
        }

        .btn-eiger:active {
            transform: translateY(0);
        }

        /* ===== TABLES ===== */
        .table thead th {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
            padding: 12px 16px;
        }

        .table tbody td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.875rem;
        }

        .table-hover tbody tr:hover {
            background: #f8fafc;
        }

        /* ===== BADGES (soft + solid variants) ===== */
        .badge-soft { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 0.72rem; letter-spacing: 0.3px; }
        .badge-success-soft { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        .badge-danger-soft  { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .badge-warning-soft { background: #fef9c3; color: #ca8a04; border: 1px solid #fde68a; }
        .badge-info-soft    { background: #dbeafe; color: #2563eb; border: 1px solid #bfdbfe; }
        .badge-gray-soft    { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }

        /* ===== STAT CARD GRADIENTS ===== */
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -15%;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            opacity: 0.06;
            background: var(--eiger-orange);
            z-index: 0;
        }
        .stat-card > * { position: relative; z-index: 1; }

        .stat-card .stat-icon {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* ===== EMPTY STATE ===== */
        .empty-state {
            padding: 48px 24px;
            text-align: center;
            color: #94a3b8;
        }
        .empty-state .empty-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            border-radius: 50%;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--eiger-orange);
            box-shadow: inset 0 0 0 1px rgba(232,80,10,0.1);
        }
        .empty-state .empty-title { font-weight: 600; color: #475569; margin-bottom: 4px; }
        .empty-state .empty-sub { font-size: 0.85rem; }

        /* ===== ACTION BUTTON GROUPS ===== */
        .btn-group-actions { display: inline-flex; gap: 4px; }
        .btn-icon {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            padding: 0; border-radius: 8px;
            transition: all 0.2s ease;
        }
        .btn-icon:hover { transform: translateY(-1px); }

        /* ===== HERO (welcome banner) ===== */
        .hero-banner {
            background: linear-gradient(135deg, #1a1a2e 0%, #2d2d4a 50%, var(--eiger-orange-dark) 100%);
            color: #fff;
            border-radius: 14px;
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(26, 26, 46, 0.15);
        }
        .hero-banner::after {
            content: '⛰';
            position: absolute;
            right: -20px;
            top: -20px;
            font-size: 180px;
            opacity: 0.08;
            color: #fff;
            font-family: serif;
            line-height: 1;
        }
        .hero-banner .hero-title { font-size: 1.45rem; font-weight: 700; margin-bottom: 4px; }
        .hero-banner .hero-sub { opacity: 0.85; font-size: 0.9rem; margin-bottom: 0; }
        .hero-banner .hero-pill { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.12); padding: 4px 12px; border-radius: 999px; font-size: 0.75rem; font-weight: 500; backdrop-filter: blur(8px); }

        /* ===== FORMS ===== */
        .form-control, .form-select {
            border-radius: 8px;
            border-color: #e2e8f0;
            font-size: 0.875rem;
            padding: 9px 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--eiger-orange);
            box-shadow: 0 0 0 3px rgba(232, 80, 10, 0.1);
        }

        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        /* ===== ALERTS ===== */
        .alert {
            border-radius: 10px;
            font-size: 0.875rem;
        }

        /* ===== BREADCRUMB ===== */
        .breadcrumb {
            font-size: 0.78rem;
            margin-bottom: 0;
        }
        .breadcrumb-item + .breadcrumb-item::before { content: '›'; color: #cbd5e1; font-weight: 600; padding: 0 6px; }
        .breadcrumb-item a:hover { color: var(--eiger-orange) !important; }
        .breadcrumb-item.active { color: #1e293b; font-weight: 500; }

        /* ===== PAGINATION (konsisten di seluruh halaman admin) ===== */
        .pagination {
            gap: 4px;
        }
        .pagination .page-link {
            font-size: 0.8rem;
            font-weight: 500;
            border-radius: 8px !important;
            margin: 0;
            border: 1px solid #e2e8f0;
            color: #475569;
            background: #ffffff;
            padding: 6px 12px;
            min-width: 36px;
            text-align: center;
            transition: all 0.2s ease;
        }
        .pagination .page-link:hover {
            background: #fff7ed;
            border-color: var(--eiger-orange);
            color: var(--eiger-orange);
        }
        .pagination .page-item.active .page-link {
            background: var(--eiger-orange);
            border-color: var(--eiger-orange);
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(232, 80, 10, 0.25);
        }
        .pagination .page-item.disabled .page-link {
            background: #f8fafc;
            color: #cbd5e1;
            border-color: #e2e8f0;
        }
        .pagination .page-item.disabled .page-link:hover {
            background: #f8fafc;
            color: #cbd5e1;
            border-color: #e2e8f0;
        }

        /* ===== TABLE FOOTER (info + pagination) ===== */
        .table-footer {
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
            border-radius: 0 0 12px 12px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #main-content { margin-left: 0; }
        }
    </style>

    @stack('styles')
</head>
<body>

    {{-- ===== SIDEBAR ===== --}}
    <nav id="sidebar">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand">
            <div class="brand-logo">
                <span class="brand-icon"><i class="bi bi-triangle-fill"></i></span>
                <span>EIGER</span>
            </div>
            <div class="brand-sub">Admin Console</div>
        </a>

        <div class="sidebar-nav">
            <div class="nav-section-title">Overview</div>
            <a href="{{ route('admin.dashboard') }}"
               class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-title">Manajemen Data</div>
            <a href="{{ route('admin.products.index') }}"
               class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}">
                <i class="bi bi-box-seam-fill"></i>
                <span>Products</span>
            </a>
            <a href="{{ route('admin.zones.index') }}"
               class="nav-link {{ request()->routeIs('admin.zones.*') ? 'active' : '' }}">
                <i class="bi bi-map-fill"></i>
                <span>Zones</span>
            </a>
            <a href="{{ route('admin.rfid-tags.index') }}"
               class="nav-link {{ request()->routeIs('admin.rfid-tags.*') ? 'active' : '' }}">
                <i class="bi bi-broadcast-pin"></i>
                <span>RFID Tags</span>
            </a>

            <div class="nav-section-title">Konfigurasi</div>
            <a href="{{ route('admin.fit-and-go.index') }}"
               class="nav-link {{ request()->routeIs('admin.fit-and-go.*') ? 'active' : '' }}">
                <i class="bi bi-person-bounding-box"></i>
                <span>AI Fit & Go</span>
            </a>
            <a href="{{ route('admin.tablets.index') }}"
               class="nav-link {{ request()->routeIs('admin.tablets.*') ? 'active' : '' }}">
                <i class="bi bi-tablet-landscape-fill"></i>
                <span>Interactive Tablets</span>
            </a>

            <div class="nav-section-title">Sistem</div>
            <a href="{{ route('admin.integrations.index') }}" class="nav-link {{ request()->routeIs('admin.integrations.*', 'admin.pim.*', 'admin.care.*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3-fill"></i> Integrasi PIM & CARE
            </a>
            <a href="{{ route('admin.sync-logs.index') }}"
               class="nav-link {{ request()->routeIs('admin.sync-logs.*') ? 'active' : '' }}">
                <i class="bi bi-arrow-repeat"></i>
                <span>Sync Logs</span>
            </a>
        </div>

        <div class="sidebar-footer">
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="status-dot" style="position:static"></span>
                <span style="color:#94a3b8">Online</span>
            </div>
            <div style="font-weight:600;color:#cbd5e1">EIGER Backend v1.0</div>
            <div style="color:#475569">Laravel {{ app()->version() }} • {{ PHP_VERSION }}</div>
        </div>
    </nav>

    {{-- ===== MAIN CONTENT ===== --}}
    <div id="main-content">

        {{-- TOPBAR --}}
        <header id="topbar">
            <div>
                <div class="topbar-title">@yield('page-title', 'Dashboard')</div>
                <nav aria-label="breadcrumb" class="mt-1">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none text-muted"><i class="bi bi-house-door"></i></a></li>
                        @yield('breadcrumb-items')
                    </ol>
                </nav>
            </div>
            <div class="topbar-right">
                <div class="status-badge d-none d-md-flex">
                    <div class="status-dot"></div>
                    Backend Running
                </div>
                <div class="topbar-clock">
                    <i class="bi bi-clock-history me-1"></i>
                    {{ now()->setTimezone('Asia/Jakarta')->format('d M Y • H:i') }} WIB
                </div>
            </div>
        </header>

        {{-- PAGE BODY --}}
        <div class="page-content">

            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')
        </div>

    </div>

    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert-dismissible').forEach(function (el) {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert.close();
            }, 5000);
        });
    </script>

    @stack('scripts')
</body>
</html>
