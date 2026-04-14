<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — scaMaria</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .sidebar {
            min-height: 100vh;
            width: 240px;
            background: #1a2332;
            position: fixed;
            top: 0; left: 0;
            z-index: 100;
            padding-top: 60px;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: .6rem 1.25rem;
            border-radius: 6px;
            margin: 2px 8px;
            font-size: .9rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: #2c3e50;
            color: #fff;
        }
        .sidebar .nav-link i { margin-right: 8px; }
        .sidebar-brand {
            position: fixed;
            top: 0; left: 0;
            width: 240px;
            height: 56px;
            background: #111d2b;
            display: flex;
            align-items: center;
            padding: 0 1.25rem;
            font-weight: 700;
            font-size: 1.1rem;
            color: #f0c040;
            z-index: 101;
            text-decoration: none;
        }
        .main-content {
            margin-left: 240px;
        }
        .topbar {
            height: 56px;
            background: #fff;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
        }
        .content-area { padding: 1.75rem 1.5rem; }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <a href="{{ route('admin.roles.index') }}" class="sidebar-brand">
        <i class="bi bi-shield-shaded"></i></i> scaMaria
    </a>
    <nav class="mt-2">
        <ul class="nav flex-column">
            <li class="nav-item">
                <span class="nav-link text-uppercase fw-semibold" style="font-size:.7rem;color:#6c757d;cursor:default;">
                    Seguridad
                </span>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                   href="{{ route('admin.roles.index') }}">
                    <i class="bi bi-shield-lock"></i> Roles
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.usuarios.*') ? 'active' : '' }}"
                   href="{{ route('admin.usuarios.index') }}">
                    <i class="bi bi-people"></i> Usuarios
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.permisos.*') ? 'active' : '' }}"
                   href="{{ route('admin.permisos.index') }}">
                    <i class="bi bi-key"></i> Permisos
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Main -->
<div class="main-content">
    <header class="topbar">
        <h6 class="mb-0 fw-semibold text-muted">@yield('header', 'Panel de Administración')</h6>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small"><i class="bi bi-person-circle me-1"></i>{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i>Salir
                </button>
            </form>
        </div>
    </header>

    <main class="content-area">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
