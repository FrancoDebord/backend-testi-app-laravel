<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — TestiApp</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f3f9; }
        .sidebar { width: 240px; min-height: 100vh; background: #1e1b4b; position: fixed; top: 0; left: 0; z-index: 100; overflow-y: auto; }
        .sidebar-brand { padding: 1.2rem 1.5rem; border-bottom: 1px solid rgba(255,255,255,.08); }
        .sidebar-nav .nav-link { color: rgba(255,255,255,.65); padding: .65rem 1.25rem; border-radius: 8px; margin: .1rem .5rem; font-size: .875rem; font-weight: 500; }
        .sidebar-nav .nav-link:hover, .sidebar-nav .nav-link.active { color: #fff; background: rgba(255,255,255,.12); }
        .sidebar-nav .nav-link i { width: 20px; }
        .main-content { margin-left: 240px; }
        .topbar { background: white; border-bottom: 1px solid #e5e7eb; padding: .9rem 1.5rem; position: sticky; top: 0; z-index: 50; }
        .stat-card { border-left: 4px solid #6366f1; border-radius: 10px; }
        .stat-card.green { border-left-color: #10b981; }
        .stat-card.red { border-left-color: #ef4444; }
        .stat-card.blue { border-left-color: #3b82f6; }
        .table th { font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; }
        .badge { font-weight: 600; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; }
        .btn-primary { background: #6366f1; border-color: #6366f1; }
        .btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }
        @media (max-width: 991px) { .sidebar { transform: translateX(-100%); transition: transform .3s; }
            .sidebar.show { transform: translateX(0); } .main-content { margin-left: 0; } }
    </style>
</head>
<body>

{{-- Mobile sidebar backdrop --}}
<div id="sidebarBackdrop" class="d-none d-lg-none position-fixed top-0 start-0 w-100 h-100"
     style="background:rgba(0,0,0,.45);z-index:99;"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand d-flex align-items-center gap-2">
        <div style="width:32px;height:32px;background:#6366f1;border-radius:8px;display:flex;align-items:center;justify-content:center;">
            <span class="text-white fw-bold" style="font-size:14px;">T</span>
        </div>
        <div>
            <div class="text-white fw-bold" style="font-size:.9rem;">TestiApp</div>
            <div class="text-muted" style="font-size:.7rem;">Administration</div>
        </div>
    </div>

    <nav class="sidebar-nav py-3">
        <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2 me-2"></i>Tableau de bord
        </a>
        <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
            <i class="bi bi-people me-2"></i>Utilisateurs
        </a>
        <a href="{{ route('admin.content.index') }}" class="nav-link {{ request()->routeIs('admin.content.*') ? 'active' : '' }}">
            <i class="bi bi-journal-text me-2"></i>Contenu
        </a>
        <a href="{{ route('admin.categories.index') }}" class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
            <i class="bi bi-tags me-2"></i>Catégories
        </a>
        <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
            <i class="bi bi-gear me-2"></i>Paramètres
        </a>
        <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem;">
        <a href="{{ route('moderation.index') }}" class="nav-link {{ request()->routeIs('moderation.*') ? 'active' : '' }}">
            <i class="bi bi-shield-check me-2"></i>Modération
        </a>
        <hr style="border-color:rgba(255,255,255,.1);margin:.5rem 1rem;">
        <a href="{{ route('home') }}" class="nav-link">
            <i class="bi bi-arrow-left me-2"></i>Retour au site
        </a>
    </nav>

    <div class="mt-auto p-3 border-top" style="border-color:rgba(255,255,255,.08)!important;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <div style="width:36px;height:36px;background:#6366f1;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.8rem;">
                {{ Auth::user()->initials }}
            </div>
            <div class="overflow-hidden">
                <p class="text-white mb-0 small fw-semibold text-truncate">{{ Auth::user()->display_name }}</p>
                <p class="text-muted mb-0" style="font-size:.7rem;">{{ Auth::user()->role->label() }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-sm w-100 text-muted" style="font-size:.8rem;">
                <i class="bi bi-box-arrow-right me-1"></i>Déconnexion
            </button>
        </form>
    </div>
</aside>

<div class="main-content">
    <div class="topbar d-flex align-items-center gap-3">
        <button class="btn btn-light btn-sm d-lg-none" data-sidebar-toggle>
            <i class="bi bi-list fs-5"></i>
        </button>
        <h5 class="mb-0 fw-semibold">@yield('page-title', 'Administration')</h5>
        <div class="ms-auto d-flex gap-2">
            @if(session('success'))
            <span class="badge bg-success px-3 py-2"><i class="bi bi-check me-1"></i>{{ session('success') }}</span>
            @endif
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger mx-4 mt-3 mb-0">
        @foreach($errors->all() as $error)<p class="mb-0 small"><i class="bi bi-exclamation-circle me-1"></i>{{ $error }}</p>@endforeach
    </div>
    @endif

    <div class="p-4">
        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Nettoie tout backdrop coincé
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';

    // Sidebar mobile : backdrop pour fermer en cliquant dehors
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('d-none');
        });
    });
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('show');
            backdrop.classList.add('d-none');
        });
    }
});
</script>
@stack('scripts')
</body>
</html>
