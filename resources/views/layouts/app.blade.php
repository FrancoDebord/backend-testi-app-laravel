<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TestiApp') — Partagez votre témoignage</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bs-primary: #6366f1;
            --bs-primary-rgb: 99, 102, 241;
        }
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .navbar-brand { font-weight: 800; font-size: 1.3rem;
            background: linear-gradient(135deg, #6366f1, #7c3aed);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logo-icon { width: 32px; height: 32px; background: linear-gradient(135deg, #6366f1, #7c3aed);
            border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;
            color: white; font-weight: 800; font-size: 14px; }
        .btn-primary { background: #6366f1; border-color: #6366f1; }
        .btn-primary:hover { background: #4f46e5; border-color: #4f46e5; }
        .btn-outline-primary { color: #6366f1; border-color: #6366f1; }
        .btn-outline-primary:hover { background: #6366f1; border-color: #6366f1; }
        .text-primary { color: #6366f1 !important; }
        .bg-primary { background-color: #6366f1 !important; }
        .border-primary { border-color: #6366f1 !important; }
        .nav-link.active, .nav-link:hover { color: #6366f1 !important; }
        .card { border: 1px solid #e5e7eb; border-radius: 12px; }
        .testimony-card { transition: transform .15s, box-shadow .15s; }
        .testimony-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.1) !important; }
        .badge-type { font-size: .7rem; padding: .3em .6em; border-radius: 6px; }
        .avatar-sm { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; }
        .avatar-lg { width: 80px; height: 80px; border-radius: 50%; font-weight: 700; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; }
        .cover-img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px 10px 0 0; }
        .verse-banner { background: linear-gradient(135deg, #6366f1, #7c3aed); border-radius: 12px; }
        .navbar { box-shadow: 0 1px 8px rgba(0,0,0,.06); }
        .stat-card { border-left: 4px solid #6366f1; }
        .notification-dot { width: 10px; height: 10px; background: #ef4444; border-radius: 50%; position: absolute; top: 4px; right: 4px; }
        .dropdown-item:active { background: #6366f1; }
        .category-chip { cursor: pointer; border-radius: 20px; padding: .35rem .9rem; font-size: .85rem; }
        .category-chip.active { background: #6366f1; color: white; border-color: #6366f1; }
    </style>
    @stack('styles')
</head>
<body>

<nav class="navbar navbar-expand-lg bg-white sticky-top">
    <div class="container-fluid px-4">

        <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
            <span class="logo-icon">T</span> TestiApp
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link fw-medium {{ request()->routeIs('home') ? 'text-primary' : 'text-secondary' }}" href="{{ route('home') }}">
                        <i class="bi bi-house me-1"></i>Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-medium {{ request()->routeIs('explore') ? 'text-primary' : 'text-secondary' }}" href="{{ route('explore') }}">
                        <i class="bi bi-search me-1"></i>Explorer
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-medium {{ request()->routeIs('bible.*') ? 'text-primary' : 'text-secondary' }}" href="{{ route('bible.reader') }}">
                        <i class="bi bi-book me-1"></i>Bible
                    </a>
                </li>
                @auth
                <li class="nav-item">
                    <a class="btn btn-primary btn-sm px-3 ms-2 mt-1 mt-lg-0" href="{{ route('publish') }}">
                        <i class="bi bi-plus-lg me-1"></i>Publier
                    </a>
                </li>
                @endauth
            </ul>

            <div class="d-flex align-items-center gap-2">
                @auth
                    <a href="{{ route('notifications.index') }}" class="btn btn-light btn-sm position-relative">
                        <i class="bi bi-bell fs-6"></i>
                    </a>

                    <div class="dropdown">
                        <button class="btn btn-light btn-sm d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown">
                            <div class="avatar-sm bg-primary text-white" style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;">
                                {{ Auth::user()->initials }}
                            </div>
                            <span class="d-none d-lg-inline fw-medium small">{{ Auth::user()->display_name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="min-width:220px;border-radius:12px;">
                            <li class="px-3 py-2 border-bottom">
                                <p class="fw-semibold mb-0 small">{{ Auth::user()->display_name }}</p>
                                <p class="text-muted mb-0" style="font-size:.75rem;">{{ Auth::user()->role->label() }}</p>
                            </li>
                            <li><a class="dropdown-item py-2" href="{{ route('profiles.show', Auth::id()) }}"><i class="bi bi-person me-2 text-primary"></i>Mon profil</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('testimonies.mine') }}"><i class="bi bi-journal-text me-2 text-primary"></i>Mes témoignages</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('profile.saved') }}"><i class="bi bi-bookmark me-2 text-primary"></i>Sauvegardes</a></li>
                            <li><a class="dropdown-item py-2" href="{{ route('profile.settings') }}"><i class="bi bi-gear me-2 text-primary"></i>Paramètres</a></li>
                            @if(Auth::user()->canModerate())
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-primary" href="{{ route('moderation.index') }}"><i class="bi bi-shield-check me-2"></i>Modération</a></li>
                            @endif
                            @if(Auth::user()->isAdmin())
                            <li><a class="dropdown-item py-2 text-primary" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i>Administration</a></li>
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>Déconnexion
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-light btn-sm fw-medium">Connexion</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">S'inscrire</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

@if(session('success'))
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;margin-top:70px">
    <div class="toast show align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
@endif
@if(session('error'))
<div class="position-fixed top-0 end-0 p-3" style="z-index:9999;margin-top:70px">
    <div class="toast show align-items-center text-bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-x-circle me-2"></i>{{ session('error') }}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
@endif

<main>
    @yield('content')
</main>

<footer class="bg-white border-top mt-5 py-4">
    <div class="container-fluid px-4 d-flex flex-column flex-md-row align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="logo-icon" style="width:24px;height:24px;font-size:11px;">T</span>
            <span class="fw-semibold text-dark">TestiApp</span>
        </div>
        <p class="text-muted small mb-0">© {{ date('Y') }} TestiApp — Partagez la gloire de Dieu</p>
        <div class="d-flex gap-3">
            <a href="{{ route('home') }}" class="text-muted small text-decoration-none">Accueil</a>
            <a href="{{ route('explore') }}" class="text-muted small text-decoration-none">Explorer</a>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Nettoie les backdrops de modals éventuellement coincés au chargement de la page
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';

    // Auto-dismiss des toasts après 4 secondes
    document.querySelectorAll('.toast.show').forEach(function (toastEl) {
        setTimeout(function () {
            var t = bootstrap.Toast.getOrCreateInstance(toastEl);
            t.hide();
        }, 4000);
    });
});
</script>
@stack('scripts')
</body>
</html>
