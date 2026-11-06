<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1a16">
    <title>@yield('title', 'ISO Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
    {{-- The version in the address makes browsers fetch the new file after every update. --}}
    <link rel="stylesheet" href="{{ asset('css/iso.css') }}?v={{ config('iso.version') }}">
    @stack('head')
</head>
<body>
@auth
    @php
        $currentUser = auth()->user();
        $topRole = optional($currentUser->roles()->orderByDesc('level')->first())->name ?? 'User';
        $initials = collect(explode(' ', trim($currentUser->name)))->filter()->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('') ?: 'U';
        $availableUpdate = $currentUser->hasPermission('platform_updates.manage') ? app(\App\Updates\UpdateCheck::class)->available() : null;
        $has = fn (string $table) => \Illuminate\Support\Facades\Schema::hasTable($table);
        $can = fn (string $permission) => $currentUser->hasPermission($permission);
        $isAdmin = $currentUser->hasRole('system-administrator');

        // Each link keeps the permission (and table) check it had before v3.1.0.
        $navigation = [
            'Workspace' => [
                ['show' => $can('dashboard.view'), 'route' => 'dashboard', 'active' => request()->routeIs('dashboard', 'dashboard.*'), 'icon' => 'dashboard', 'label' => 'Dashboard'],
                ['show' => $can('employees.view'), 'route' => 'employees.index', 'active' => request()->routeIs('employees.*'), 'icon' => 'employees', 'label' => 'Employees'],
                ['show' => $can('clients.view') && $has('customers'), 'route' => 'customers.index', 'active' => request()->routeIs('customers.*'), 'icon' => 'customers', 'label' => 'Customers'],
                ['show' => $can('departments.view'), 'route' => 'departments.index', 'active' => request()->routeIs('departments.*'), 'icon' => 'departments', 'label' => 'Departments'],
                ['show' => $can('profile.view'), 'route' => 'profile.show', 'active' => request()->routeIs('profile.*', 'password.*'), 'icon' => 'profile', 'label' => 'My profile'],
            ],
            'Operations' => [
                ['show' => $can('attendance.view') && $has('attendance_days'), 'route' => 'attendance.index', 'active' => request()->routeIs('attendance.*'), 'icon' => 'attendance', 'label' => 'Time attendance'],
                ['show' => $can('leave.view') && $has('leave_requests'), 'route' => 'leave.index', 'active' => request()->routeIs('leave.*'), 'icon' => 'leave', 'label' => 'Leave'],
                ['show' => $can('calendar.view') && $has('leave_requests'), 'route' => 'calendar.index', 'active' => request()->routeIs('calendar.*'), 'icon' => 'calendar', 'label' => 'Calendar'],
                ['show' => $can('vehicle.view') && $has('vehicles'), 'route' => 'vehicles.index', 'active' => request()->routeIs('vehicles.*'), 'icon' => 'vehicles', 'label' => 'Vehicles & fuel'],
                ['show' => $can('vehicle_tracking.view') && $has('vehicle_tracking_snapshots'), 'route' => 'vehicle_tracking.index', 'active' => request()->routeIs('vehicle_tracking.index'), 'icon' => 'tracking', 'label' => 'Vehicle tracking'],
                ['show' => $can('employee_documents.view') && $has('employee_documents'), 'route' => 'employee_documents.reminders', 'active' => request()->routeIs('employee_documents.*'), 'icon' => 'documents', 'label' => 'Document reminders'],
            ],
            'Admin' => [
                ['show' => $can('roles.view'), 'route' => 'roles.index', 'active' => request()->routeIs('roles.*'), 'icon' => 'permissions', 'label' => 'Permissions'],
                ['show' => $can('cron_jobs.view'), 'route' => 'cron_jobs.index', 'active' => request()->routeIs('cron_jobs.*'), 'icon' => 'cron', 'label' => 'Cron jobs'],
                ['show' => $isAdmin && $can('core_settings.view') && $has('system_settings'), 'route' => 'core_settings.index', 'active' => request()->routeIs('core_settings.*'), 'icon' => 'settings', 'label' => 'System settings'],
                ['show' => $isAdmin && $can('google_api_settings.view') && $has('system_settings'), 'route' => 'google_api_settings.index', 'active' => request()->routeIs('google_api_settings.*'), 'icon' => 'map', 'label' => 'Google API'],
                ['show' => $isAdmin && $can('vehicle_tracking.settings.view') && $has('system_settings'), 'route' => 'vehicle_tracking.settings', 'active' => request()->routeIs('vehicle_tracking.settings*'), 'icon' => 'tracking', 'label' => 'Tracking API'],
                ['show' => $can('leave_types.view') && $has('leave_types'), 'route' => 'leave_types.index', 'active' => request()->routeIs('leave_types.*'), 'icon' => 'leave', 'label' => 'Leave settings'],
                ['show' => $can('platform_updates.manage'), 'route' => 'platform_updates.index', 'active' => request()->routeIs('platform_updates.*'), 'icon' => 'updates', 'label' => 'Updates', 'badge' => $availableUpdate ? '1' : null],
            ],
        ];
        $navigation = array_filter(array_map(fn ($items) => array_values(array_filter($items, fn ($item) => $item['show'] && \Illuminate\Support\Facades\Route::has($item['route']))), $navigation));
    @endphp
@endauth
<div class="app @guest guest @endguest">
    @auth
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
        <aside class="sidebar" id="sidebar" aria-label="Main menu">
            <a class="brand" href="{{ route('dashboard') }}">
                <span class="brand-mark">ISO</span>
                <span><strong>Command Centre</strong><span>isoadmin.co.za</span></span>
            </a>
            <nav class="nav" aria-label="Main navigation">
                @foreach($navigation as $group => $items)
                    <div class="nav-section-label">{{ $group }}</div>
                    @foreach($items as $item)
                        <a href="{{ route($item['route']) }}" class="{{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>
                            <span class="nav-icon"><x-icon :name="$item['icon']" /></span>
                            <span class="nav-text">{{ $item['label'] }}</span>
                            @if(! empty($item['badge']))<span class="nav-badge" title="An update is available">{{ $item['badge'] }}</span>@endif
                        </a>
                    @endforeach
                @endforeach
                <form class="logout-form" method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"><span class="nav-icon"><x-icon name="logout" /></span><span class="nav-text">Log out</span></button>
                </form>
            </nav>
            <div class="sidebar-foot">
                <span>ISO Command Centre {{ config('iso.version') }}</span>
                @if($can('platform_updates.manage') && \Illuminate\Support\Facades\Route::has('platform_updates.index'))
                    <a href="{{ route('platform_updates.index') }}">{{ $availableUpdate ? $availableUpdate->version.' is available' : 'View updates' }}</a>
                @endif
            </div>
        </aside>
    @endauth

    <div class="main">
        @auth
            <header class="topbar">
                <div class="topbar-title">
                    <button class="menu-btn" type="button" onclick="toggleSidebar()" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu" id="menuButton"><x-icon name="menu" /></button>
                    <div style="min-width: 0">
                        <h1>@yield('page_title', 'ISO Admin')</h1>
                        <div class="sub">{{ $topRole }}</div>
                    </div>
                </div>
                <div class="user">
                    <div class="user-meta"><strong>{{ $currentUser->name }}</strong><span>{{ $currentUser->email }}</span></div>
                    <a class="user-avatar" href="{{ \Illuminate\Support\Facades\Route::has('profile.show') ? route('profile.show') : '#' }}" aria-label="My profile">{{ $initials }}</a>
                </div>
            </header>
        @endauth

        <main class="content @yield('content_class')" id="content">
            @if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
            @auth
                @if($availableUpdate && ! request()->routeIs('platform_updates.*'))
                    <div class="alert info" role="status">ISO Command Centre {{ $availableUpdate->version }} is available. <a href="{{ route('platform_updates.index') }}">Open Updates</a></div>
                @endif
            @endauth
            @if(session('warning'))<div class="alert warning" role="status">{{ session('warning') }}</div>@endif
            @if($errors->any())
                <div class="alert error" role="alert">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @yield('content')
            @auth<div class="footer-note">ISO Command Centre {{ config('iso.version') }} · isoadmin.co.za</div>@endauth
        </main>
    </div>
</div>
@auth
<script>
    function toggleSidebar(force) {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        var button = document.getElementById('menuButton');
        if (!sidebar || !backdrop) return;
        var open = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
        if (button) button.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') toggleSidebar(false);
    });
</script>
@endauth
@stack('scripts')
</body>
</html>
