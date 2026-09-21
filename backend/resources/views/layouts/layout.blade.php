<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') - Mini Inventory</title>
    {{-- Set the theme before paint to avoid a flash --}}
    <script>
        try { var t = localStorage.getItem('admin-theme'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-bs-theme', t); } catch (e) {}
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
</head>
<body>
@php
    $me = auth()->user();
    $initial = strtoupper(mb_substr($me?->name ?? 'A', 0, 1));
    $statusDots = ['pending' => '#f59e0b', 'confirmed' => '#60a5fa', 'shipped' => '#a855f7', 'delivered' => '#22c55e', 'cancelled' => '#ef4444'];
    $catalogueOpen = request()->routeIs('admin.products.*', 'admin.categories.*');
    $ordersOpen = request()->routeIs('admin.orders.*');
    $currentStatus = request('status');
@endphp

<aside class="sidebar" id="sidebar" aria-label="Main menu">
    <a class="sidebar-brand" href="{{ route('admin.dashboard') }}">
        <span class="brand-icon"><i class="bi bi-box-seam-fill" aria-hidden="true"></i></span>
        Mini Inventory
    </a>

    <nav class="sidebar-nav">
        <div class="nav-label">Overview</div>
        <a class="side-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
            <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
        </a>

        <a class="side-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
            <i class="bi bi-people" aria-hidden="true"></i> Users
        </a>

        <a class="side-link {{ request()->routeIs('admin.returns.*') ? 'active' : '' }}" href="{{ route('admin.returns.index') }}">
            <i class="bi bi-arrow-return-left" aria-hidden="true"></i> Returns
        </a>

        <div class="nav-label">Manage</div>
        <button class="side-link {{ $catalogueOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menuCatalogue" aria-expanded="{{ $catalogueOpen ? 'true' : 'false' }}" aria-controls="menuCatalogue">
            <i class="bi bi-grid" aria-hidden="true"></i> Catalogue <i class="bi bi-chevron-down chev" aria-hidden="true"></i>
        </button>
        <div class="collapse {{ $catalogueOpen ? 'show' : '' }}" id="menuCatalogue">
            <ul class="side-sub">
                <li><a class="side-link {{ request()->routeIs('admin.products.index', 'admin.products.edit') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><i class="bi bi-basket" aria-hidden="true"></i> Products</a></li>
                <li><a class="side-link {{ request()->routeIs('admin.products.create') ? 'active' : '' }}" href="{{ route('admin.products.create') }}"><i class="bi bi-plus-circle" aria-hidden="true"></i> Add product</a></li>
                <li><a class="side-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><i class="bi bi-tags" aria-hidden="true"></i> Categories</a></li>
            </ul>
        </div>

        <button class="side-link {{ $ordersOpen ? 'active' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menuOrders" aria-expanded="{{ $ordersOpen ? 'true' : 'false' }}" aria-controls="menuOrders">
            <i class="bi bi-receipt" aria-hidden="true"></i> Orders <i class="bi bi-chevron-down chev" aria-hidden="true"></i>
        </button>
        <div class="collapse {{ $ordersOpen ? 'show' : '' }}" id="menuOrders">
            <ul class="side-sub">
                <li><a class="side-link {{ request()->routeIs('admin.orders.*') && ! $currentStatus ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><i class="bi bi-list-ul" aria-hidden="true"></i> All orders</a></li>
                @foreach (\App\Models\Order::STATUSES as $s)
                    <li><a class="side-link {{ request()->routeIs('admin.orders.index') && $currentStatus === $s ? 'active' : '' }}" href="{{ route('admin.orders.index', ['status' => $s]) }}"><span class="dot" style="background: {{ $statusDots[$s] }}"></span> {{ ucfirst($s) }}</a></li>
                @endforeach
            </ul>
        </div>
    </nav>

    <div class="sidebar-user">
        <span class="avatar" aria-hidden="true">{{ $initial }}</span>
        <div class="who"><strong>{{ $me?->name }}</strong><small>Administrator</small></div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button class="icon-btn" style="color:#94a3b8;border-color:rgba(148,163,184,.25)" aria-label="Logout" title="Logout"><i class="bi bi-box-arrow-right" aria-hidden="true"></i></button>
        </form>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="main">
    <header class="topbar">
        <button class="icon-btn d-lg-none" id="sidebarToggle" type="button" aria-label="Open menu" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list fs-5" aria-hidden="true"></i></button>
        <div>
            <h1 class="page-title">@yield('title', 'Admin')</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                    @hasSection('crumb_parent')
                        <li class="breadcrumb-item"><a href="{{ route('admin.orders.index') }}">@yield('crumb_parent')</a></li>
                    @endif
                    @hasSection('crumb')
                        <li class="breadcrumb-item active" aria-current="page">@yield('crumb')</li>
                    @endif
                </ol>
            </nav>
        </div>

        <form class="top-search" method="GET" action="{{ route('admin.products.index') }}" role="search">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" name="search" value="{{ request()->routeIs('admin.products.index') ? request('search') : '' }}" class="form-control" placeholder="Search products" aria-label="Search products" maxlength="100">
            </div>
        </form>

        <div class="actions d-flex align-items-center gap-2">
            <button class="icon-btn" id="themeToggle" type="button" aria-label="Toggle light or dark theme"><i class="bi bi-sun" aria-hidden="true"></i></button>
            <div class="dropdown">
                <button class="avatar" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">{{ $initial }}</button>
                <div class="dropdown-menu dropdown-menu-end shadow" style="min-width:230px">
                    <div class="px-3 py-2">
                        <div class="fw-semibold">{{ $me?->name }}</div>
                        <div class="small text-muted">{{ $me?->email }}</div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="content">
        {{-- Flash messages --}}
        <div class="toast-wrap" aria-live="polite">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i><div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger alert-dismissible d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i><div>{{ session('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif
        </div>

        @if ($errors->any() && ! View::hasSection('inline_errors'))
            <div class="alert alert-danger d-flex" role="alert">
                <i class="bi bi-exclamation-octagon-fill me-2" aria-hidden="true"></i>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
<script src="{{ asset('js/admin.js') }}"></script>
</body>
</html>
