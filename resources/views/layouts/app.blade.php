<!doctype html>
<html lang="id" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $pageTitle ?? 'ClipTrend AI — AI Shorts Studio' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@if(auth()->check())
<div class="ct-shell">
    <aside class="ct-sidebar">
        <a href="{{ route('dashboard') }}" class="ct-brand">
            <div class="ct-brand-mark">CT</div>
            <div>
                <div class="ct-brand-title">ClipTrend AI</div>
                <div class="ct-brand-subtitle">AI Shorts Studio</div>
            </div>
        </a>
        @php
            $navItems = [
                ['⌁', 'Workspace', 'dashboard', 'dashboard'],
                ['◎', 'Projects', 'projects.index', 'projects.*'],
                ['↗', 'Trend Intelligence', 'trends.index', 'trends.*'],
                ['⇪', 'Output Library', 'outputs.index', 'outputs.*'],
            ];
        @endphp
        <nav class="ct-nav">
            @foreach($navItems as [$icon, $label, $route, $pattern])
                <a href="{{ route($route) }}" class="ct-nav-item {{ request()->routeIs($pattern) ? 'ct-nav-item-active' : '' }}">
                    <span class="ct-nav-icon">{{ $icon }}</span>
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            @if(auth()->user()->hasRole('admin'))
                <div class="mt-7 px-3 text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Admin Control</div>
                <a href="{{ route('admin.dashboard') }}" class="ct-nav-item {{ request()->routeIs('admin.dashboard') ? 'ct-nav-item-active' : '' }}"><span class="ct-nav-icon">✦</span><span>Admin Dashboard</span></a>
                <a href="{{ route('admin.users.index') }}" class="ct-nav-item {{ request()->routeIs('admin.users.*') ? 'ct-nav-item-active' : '' }}"><span class="ct-nav-icon">◌</span><span>Users</span></a>
                <a href="{{ route('admin.projects.index') }}" class="ct-nav-item {{ request()->routeIs('admin.projects.*') ? 'ct-nav-item-active' : '' }}"><span class="ct-nav-icon">▣</span><span>All Projects</span></a>
                <a href="{{ route('admin.settings.edit') }}" class="ct-nav-item {{ request()->routeIs('admin.settings.*') ? 'ct-nav-item-active' : '' }}"><span class="ct-nav-icon">⚙</span><span>Settings</span></a>
            @endif
        </nav>

        <div class="ct-sidebar-card">
            <p class="ct-eyebrow">Mode Produk</p>
            <h3 class="mt-1 font-black text-white">Creator Pro</h3>
            <p class="mt-2 text-sm leading-6 text-slate-400">Optimized for podcast, motivasi, edukasi, event, bola, dan konten lokal Indonesia.</p>
        </div>
    </aside>

    <main class="ct-main">
        <header class="ct-topbar">
            <div>
                <p class="ct-eyebrow">Smart Repurposing Platform</p>
                <h1>{{ $pageTitle ?? 'Ubah video panjang menjadi Shorts, TikTok, dan Reels yang siap upload.' }}</h1>
            </div>
            <div class="flex items-center gap-4">
                <a href="{{ route('projects.create') }}" class="ct-button">New Project</a>
                <form method="POST" action="{{ route('logout') }}" title="Logout">
                    @csrf
                    <button class="flex h-12 w-12 items-center justify-center rounded-[16px] bg-white/5 text-slate-400 border border-white/5 transition-all duration-300 hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/20 active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </button>
                </form>
            </div>
        </header>

        @if(session('success'))
            <div class="mb-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/10 p-4 text-sm text-emerald-200">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-red-300/20 bg-red-300/10 p-4 text-sm text-red-200">{{ $errors->first() }}</div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>
</div>
@else
    @if(session('success'))
        <div class="mx-auto mb-5 max-w-md rounded-2xl border border-emerald-300/20 bg-emerald-300/10 p-4 text-sm text-emerald-200">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mx-auto mb-5 max-w-md rounded-2xl border border-red-300/20 bg-red-300/10 p-4 text-sm text-red-200">{{ $errors->first() }}</div>
    @endif
    @yield('content')
@endif
</body>
</html>
