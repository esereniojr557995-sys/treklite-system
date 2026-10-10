<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Treklite Outdoor') &mdash; Sales &amp; Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 240px;
        }

        body {
            background: #f4f6f5;
        }

        /* --- Sidebar --- */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: #1e5b31;
            color: #fff;
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: transform 0.2s ease-in-out;
        }

        .sidebar .brand {
            padding: 1.25rem 1rem;
            font-weight: 700;
            font-size: 1.15rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.65rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link i {
            font-size: 1.05rem;
            width: 1.2rem;
            text-align: center;
        }

        .sidebar .nav-link:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            border-left-color: #ffc107;
            color: #fff;
            font-weight: 600;
        }

        .sidebar .nav-section-label {
            padding: 0.9rem 1.25rem 0.3rem;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255, 255, 255, 0.55);
        }

        .sidebar .sidebar-footer {
            margin-top: auto;
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
        }

        /* --- Main content area --- */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #e3e6e4;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-toggle {
            display: none;
        }
        .sidebar .profile { display: flex; align-items: center; gap: .6rem; padding: .45rem .55rem; border-radius: .5rem; color: #fff; text-decoration: none; }
        .sidebar a.profile:hover, .sidebar .profile.active { background: rgba(255, 255, 255, .14); }
        .sidebar .profile .bi-person-circle { font-size: 2rem; line-height: 1; }

        /* --- Mobile: collapse sidebar off-canvas --- */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-wrapper {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: inline-flex;
            }

            .sidebar-backdrop {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.35);
                z-index: 1030;
            }

            .sidebar-backdrop.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    
    @php
    $isOwner = auth()->user()?->hasFullAccess();

    // Dashboard first, then the major transactions (Sales, Inventory), then master data and reports
    $navItems = $isOwner
        ? [
            ['route' => 'dashboard',       'label' => 'Dashboard',     'icon' => 'bi-speedometer2', 'match' => 'dashboard'],
            ['route' => 'sales.create',    'label' => 'New Sale',      'icon' => 'bi-cart-plus',    'match' => 'sales.create'],
            ['route' => 'sales.index',     'label' => 'Sales Records', 'icon' => 'bi-receipt',      'match' => 'sales.index'],
               ['route' => 'products.index',  'label' => 'Products',      'icon' => 'bi-tags',         'match' => 'products.*'],
            ['route' => 'inventory.index', 'label' => 'Inventory',     'icon' => 'bi-box-seam',     'match' => 'inventory.*'],
            ['route' => 'reports.sales',   'label' => 'Reports',       'icon' => 'bi-graph-up',     'match' => 'reports.*'],
            ['route' => 'backup.index', 'label' => 'Backup', 'icon' => 'bi-cloud-arrow-down','match' => 'backup.*'],
        ]
        : [
            ['route' => 'sales.create',    'label' => 'New Sale',      'icon' => 'bi-cart-plus',    'match' => 'sales.create'],
            ['route' => 'sales.index',     'label' => 'Sales Records', 'icon' => 'bi-receipt',      'match' => 'sales.index'],
            ['route' => 'inventory.index', 'label' => 'Stock Check',   'icon' => 'bi-box-seam',     'match' => 'inventory.*'],
        ];
@endphp

<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<aside class="sidebar" id="sidebar">
    <div class="brand">Treklite Outdoor</div>
    <nav class="nav flex-column mt-2">
        @foreach ($navItems as $item)
            <a href="{{ route($item['route']) }}"
               class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="sidebar-footer">
    <div class="small text-white-50 mb-2">Signed in as</div>

    @php $profileTag = $isOwner ? 'a' : 'div'; @endphp
    <{{ $profileTag }} @if ($isOwner) href="{{ route('users.index') }}" title="Manage user accounts" @endif
        class="profile mb-2 {{ request()->routeIs('users.*') ? 'active' : '' }}">
        <i class="bi bi-person-circle"></i>
        <span class="flex-grow-1 lh-sm">
            <span class="d-block fw-semibold">{{ auth()->user()?->name }}</span>
            <span class="badge bg-light text-dark">{{ auth()->user()?->role }}</span>
        </span>
        @if ($isOwner) <i class="bi bi-chevron-right small text-white-50"></i> @endif
    </{{ $profileTag }}>

    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button class="btn btn-outline-light btn-sm w-100">
            <i class="bi bi-box-arrow-right"></i> Log out
        </button>
    </form>
</div>
</aside>

<div class="main-wrapper">
    <div class="topbar">
        <button class="btn btn-outline-secondary sidebar-toggle" id="sidebar-toggle" type="button">
            <i class="bi bi-list"></i>
        </button>
        <span class="fw-semibold">@yield('title', 'Treklite Outdoor')</span>
        <span></span>
    </div>

    <div class="container-fluid py-4 px-4">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>
</div>

<script>
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggleBtn = document.getElementById('sidebar-toggle');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
    }

    toggleBtn?.addEventListener('click', toggleSidebar);
    backdrop?.addEventListener('click', toggleSidebar);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
