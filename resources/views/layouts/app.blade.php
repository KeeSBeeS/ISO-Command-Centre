<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ISO Admin')</title>
    <style>
        :root{
            --bg:#061017;
            --bg-soft:#0a1720;
            --panel:#0d1b24;
            --panel2:#122633;
            --panel3:#183544;
            --line:rgba(255,255,255,.12);
            --line-strong:rgba(139,220,101,.25);
            --text:#edf7f3;
            --muted:#a9bbb6;
            --muted2:#78908a;
            --brand:#12a374;
            --brand2:#8bdc65;
            --brand3:#0e7f63;
            --danger:#e5484d;
            --warn:#f5b94c;
            --info:#66b6ff;
            --white:#fff;
            --shadow:0 22px 70px rgba(0,0,0,.32);
            --shadow-soft:0 14px 38px rgba(0,0,0,.22);
            --radius:22px;
            --radius-sm:14px;
        }
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:
            radial-gradient(circle at top left,rgba(18,163,116,.24),transparent 30%),
            radial-gradient(circle at 82% 12%,rgba(102,182,255,.13),transparent 26%),
            linear-gradient(135deg,#061017,#0b1821 55%,#061017);color:var(--text);min-height:100vh}
        body::before{content:"";position:fixed;inset:0;pointer-events:none;background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);background-size:36px 36px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.7),transparent 72%)}
        a{color:inherit;text-decoration:none}
        .app{min-height:100vh;display:grid;grid-template-columns:292px 1fr;position:relative}
        .sidebar{position:sticky;top:0;height:100vh;padding:18px;border-right:1px solid var(--line);background:linear-gradient(180deg,rgba(7,17,24,.92),rgba(7,17,24,.76));backdrop-filter:blur(18px);overflow:auto;z-index:30}
        .brand{display:flex;gap:12px;align-items:center;padding:12px 10px 22px;margin-bottom:6px}
        .brand-mark{width:46px;height:46px;border-radius:16px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:grid;place-items:center;color:#071118;font-weight:950;letter-spacing:-.06em;box-shadow:0 14px 28px rgba(18,163,116,.3)}
        .brand strong{display:block;font-size:17px;letter-spacing:.01em}.brand span{display:block;font-size:12px;color:var(--muted);margin-top:2px}
        .nav{display:grid;gap:7px}.nav-section-label{padding:14px 12px 5px;color:var(--muted2);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.12em}
        .nav a,.nav button{width:100%;border:1px solid transparent;background:transparent;color:var(--muted);font:inherit;text-align:left;padding:10px 11px;border-radius:16px;display:flex;gap:10px;align-items:center;cursor:pointer;transition:background .18s ease,border-color .18s ease,color .18s ease,transform .18s ease}
        .nav a:hover,.nav a.active,.nav button:hover{background:rgba(255,255,255,.068);border-color:var(--line);color:var(--white);transform:translateY(-1px)}
        .nav a.active{background:linear-gradient(135deg,rgba(18,163,116,.22),rgba(139,220,101,.08));border-color:var(--line-strong);box-shadow:inset 0 0 0 1px rgba(139,220,101,.06)}
        .nav-icon{flex:0 0 32px;width:32px;height:32px;border-radius:12px;display:grid;place-items:center;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.08);font-size:15px;line-height:1}
        .nav a.active .nav-icon{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#061017;border-color:transparent;box-shadow:0 10px 24px rgba(18,163,116,.26)}
        .nav-text{min-width:0;display:flex;flex-direction:column;gap:1px}.nav-text strong{font-size:14px;font-weight:850}.nav-text span{font-size:11px;color:var(--muted2)}
        .logout-form{margin-top:8px}.logout-form button{justify-content:flex-start}
        .main{min-width:0;position:relative}.topbar{position:sticky;top:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 22px;border-bottom:1px solid var(--line);background:rgba(7,17,24,.76);backdrop-filter:blur(18px)}
        .topbar-title{display:flex;align-items:center;gap:11px;min-width:0}.topbar-icon{width:40px;height:40px;border-radius:14px;display:grid;place-items:center;background:rgba(255,255,255,.07);border:1px solid var(--line);box-shadow:var(--shadow-soft)}.topbar h1{font-size:18px;margin:0;line-height:1.2}.topbar .sub{font-size:12px;color:var(--muted);margin-top:2px}.topbar .user{font-size:13px;color:var(--muted);text-align:right;display:flex;align-items:center;gap:10px}.user-avatar{width:38px;height:38px;border-radius:14px;background:linear-gradient(135deg,var(--panel3),var(--brand3));display:grid;place-items:center;color:#fff;font-weight:900}.user-meta strong{display:block;color:#fff;font-size:13px}.user-meta span{display:block;color:var(--muted);font-size:11px;margin-top:1px}.menu-btn{display:none;border:1px solid var(--line);background:rgba(255,255,255,.07);color:var(--white);border-radius:13px;padding:10px 12px;align-items:center;gap:8px;font-weight:850}.sidebar-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,.48);z-index:25}
        .content{width:min(1220px,100%);margin:0 auto;padding:24px;position:relative}.grid{display:grid;gap:16px}.grid.cols-4{grid-template-columns:repeat(4,1fr)}.grid.cols-2{grid-template-columns:repeat(2,1fr)}
        .card{border:1px solid var(--line);background:linear-gradient(180deg,rgba(255,255,255,.065),rgba(255,255,255,.04));border-radius:var(--radius);box-shadow:var(--shadow);padding:18px;position:relative;overflow:hidden}.card::before{content:"";position:absolute;inset:0 0 auto;height:1px;background:linear-gradient(90deg,transparent,rgba(139,220,101,.28),transparent);pointer-events:none}.card h2,.card h3{margin-top:0;letter-spacing:-.02em}.card h2{font-size:22px}.card h3{font-size:17px}
        .metric{min-height:120px}.metric span{color:var(--muted);font-size:13px}.metric strong{display:block;font-size:34px;margin-top:10px;letter-spacing:-.04em}.metric::after{content:"";position:absolute;right:18px;top:18px;width:42px;height:42px;border-radius:15px;background:linear-gradient(135deg,rgba(18,163,116,.28),rgba(139,220,101,.08));border:1px solid rgba(139,220,101,.18)}
        .muted{color:var(--muted)}.small{font-size:13px}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.actions.right{justify-content:flex-end}.btn{border:1px solid var(--line);background:rgba(255,255,255,.065);color:var(--white);padding:11px 14px;border-radius:14px;font-weight:850;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease}.btn:hover{transform:translateY(-1px);background:rgba(255,255,255,.095);border-color:rgba(255,255,255,.18)}.btn.primary{background:linear-gradient(135deg,var(--brand),var(--brand3));border-color:rgba(139,220,101,.34);box-shadow:0 12px 30px rgba(18,163,116,.25)}.btn.primary:hover{box-shadow:0 16px 34px rgba(18,163,116,.32)}.btn.danger{background:rgba(229,72,77,.14);border-color:rgba(229,72,77,.35);color:#ffd7d9}.btn.full{width:100%}.btn:disabled{opacity:.6;cursor:not-allowed;transform:none}
        .table-wrap{overflow:auto;border-radius:18px;border:1px solid var(--line);box-shadow:var(--shadow-soft)}table{width:100%;border-collapse:collapse;min-width:760px;background:rgba(255,255,255,.035)}th,td{padding:14px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{color:#dff8d6;font-size:12px;text-transform:uppercase;letter-spacing:.08em;background:rgba(18,163,116,.08)}td{color:var(--text)}tbody tr{transition:background .18s ease}tbody tr:hover{background:rgba(255,255,255,.035)}tr:last-child td{border-bottom:0}.pill{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(139,220,101,.25);background:rgba(139,220,101,.08);color:#dff8d6;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:850;margin:2px}.pill::before{content:"";width:6px;height:6px;border-radius:50%;background:var(--brand2);box-shadow:0 0 0 4px rgba(139,220,101,.1)}.pill.off{border-color:rgba(255,255,255,.15);color:var(--muted);background:rgba(255,255,255,.04)}.pill.off::before{background:var(--muted2);box-shadow:none}.pill.warn{border-color:rgba(245,185,76,.35);background:rgba(245,185,76,.1);color:#ffe4ad}.pill.warn::before{background:var(--warn);box-shadow:0 0 0 4px rgba(245,185,76,.12)}
        label{display:block;font-size:13px;font-weight:850;margin:0 0 7px;color:#dff8d6}input,select,textarea{width:100%;border:1px solid var(--line);background:rgba(255,255,255,.075);color:var(--white);border-radius:14px;padding:12px 13px;font:inherit;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease}input:focus,select:focus,textarea:focus{outline:0;border-color:rgba(139,220,101,.48);box-shadow:0 0 0 4px rgba(18,163,116,.14);background:rgba(255,255,255,.095)}textarea{min-height:110px}option{color:#071118}.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}.form-row{margin-bottom:14px}.checkbox-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.check{border:1px solid var(--line);background:rgba(255,255,255,.045);border-radius:14px;padding:10px;display:flex;align-items:flex-start;gap:9px}.check input{width:auto;margin-top:3px}.check:hover{border-color:rgba(139,220,101,.28);background:rgba(255,255,255,.065)}
        .alert{border-radius:16px;padding:13px 15px;margin-bottom:14px;border:1px solid var(--line);box-shadow:var(--shadow-soft)}.alert.success{background:rgba(18,163,116,.12);border-color:rgba(18,163,116,.3)}.alert.error{background:rgba(229,72,77,.12);border-color:rgba(229,72,77,.35)}.alert.warning{background:rgba(245,185,76,.12);border-color:rgba(245,185,76,.35)}.pagination{margin-top:14px}.pagination nav{display:flex;gap:8px;flex-wrap:wrap}.footer-note{padding:18px 0;color:var(--muted2);font-size:12px;text-align:center}
        .page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.page-head-main{display:flex;gap:12px;align-items:flex-start}.page-head-icon,.section-icon{width:48px;height:48px;border-radius:17px;display:grid;place-items:center;background:linear-gradient(135deg,rgba(18,163,116,.25),rgba(139,220,101,.08));border:1px solid var(--line-strong);box-shadow:var(--shadow-soft);font-size:21px}.page-head h2{margin:0 0 5px}.page-head p{margin:0;color:var(--muted)}.soft-divider{height:1px;background:linear-gradient(90deg,transparent,var(--line),transparent);margin:16px 0}.kv-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.kv{border:1px solid var(--line);border-radius:15px;padding:11px;background:rgba(255,255,255,.04)}.kv span{display:block;color:var(--muted);font-size:12px}.kv strong{display:block;margin-top:4px}
        @media(max-width:1080px){.app{grid-template-columns:260px 1fr}.grid.cols-4{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:960px){.app{grid-template-columns:1fr}.sidebar{display:none;position:fixed;z-index:40;left:10px;right:10px;top:70px;height:auto;max-height:calc(100vh - 90px);overflow:auto;border:1px solid var(--line);border-radius:22px}.sidebar.open{display:block}.sidebar-backdrop.open{display:block}.menu-btn{display:inline-flex}.topbar{padding:13px 14px}.topbar-icon{display:none}.content{padding:14px}.grid.cols-4,.grid.cols-2,.form-grid,.checkbox-grid,.kv-grid{grid-template-columns:1fr}.card{padding:15px}.actions .btn{flex:1 1 auto}.topbar .user .user-meta{display:none}table{min-width:680px}.page-head{display:block}.page-head .actions{margin-top:12px}.page-head-main{align-items:center}.nav-text span{display:none}}
        @media(max-width:560px){.topbar h1{font-size:16px}.user-avatar{width:34px;height:34px}.content{padding:12px}.card{border-radius:18px}.btn{width:100%}.actions.right{justify-content:stretch}.metric strong{font-size:28px}}
    </style>
</head>
<body>
@auth
    @php
        $currentUser = auth()->user();
        $topRole = optional($currentUser->roles()->orderByDesc('level')->first())->name ?? 'User';
        $initials = collect(explode(' ', trim($currentUser->name)))->filter()->map(fn($part) => strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('') ?: 'U';
        $routeName = request()->route()?->getName() ?? '';
        $pageIcon = match (true) {
            str_starts_with($routeName, 'profile') || str_starts_with($routeName, 'password') => '👤',
            str_starts_with($routeName, 'customers') => '🤝',
            str_starts_with($routeName, 'employees') => '👥',
            str_starts_with($routeName, 'departments') => '🏢',
            str_starts_with($routeName, 'roles') => '🛡️',
            str_starts_with($routeName, 'calendar') => '📅',
            str_starts_with($routeName, 'leave.') => '🗓️',
            str_starts_with($routeName, 'attendance') => '⏱️',
            str_starts_with($routeName, 'employee_documents') => '📄',
            str_starts_with($routeName, 'vehicle_tracking') => '🛰️',
            str_starts_with($routeName, 'vehicles') => '🚗',
            str_starts_with($routeName, 'cron_jobs') => '⚙️',
            str_starts_with($routeName, 'vehicle_tracking.settings') => '🛰️',
            str_starts_with($routeName, 'google_api_settings') => '🗺️',
            str_starts_with($routeName, 'core_settings') => '🔧',
            str_starts_with($routeName, 'leave_types') => '🗓️',
            str_starts_with($routeName, 'updates') => '⬆️',
            default => '⌂',
        };
    @endphp
@endauth
<div class="app">
    @auth
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar(false)"></div>
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">ISO</div>
                <div><strong>ISO Admin</strong><span>Central Command</span></div>
            </div>
            <nav class="nav" aria-label="Main navigation">
                <div class="nav-section-label">Workspace</div>
                @if($currentUser->hasPermission('dashboard.view'))
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><span class="nav-icon">⌂</span><span class="nav-text"><strong>Dashboard</strong><span>Command overview</span></span></a>
                @endif
                @if($currentUser->hasPermission('profile.view'))
                    <a href="{{ route('profile.show') }}" class="{{ request()->routeIs('profile.*') || request()->routeIs('password.*') ? 'active' : '' }}"><span class="nav-icon">👤</span><span class="nav-text"><strong>My Profile</strong><span>Password and account</span></span></a>
                @endif
                @if($currentUser->hasPermission('employees.view'))
                    <a href="{{ route('employees.index') }}" class="{{ request()->routeIs('employees.*') ? 'active' : '' }}"><span class="nav-icon">👥</span><span class="nav-text"><strong>Employees</strong><span>People and documents</span></span></a>
                @endif
                @if($currentUser->hasPermission('clients.view') && \Illuminate\Support\Facades\Schema::hasTable('customers'))
                    <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}"><span class="nav-icon">🤝</span><span class="nav-text"><strong>Customers</strong><span>Customer records</span></span></a>
                @endif
                @if($currentUser->hasPermission('departments.view'))
                    <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'active' : '' }}"><span class="nav-icon">🏢</span><span class="nav-text"><strong>Departments</strong><span>Company structure</span></span></a>
                @endif
                @if($currentUser->hasPermission('roles.view'))
                    <a href="{{ route('roles.index') }}" class="{{ request()->routeIs('roles.*') ? 'active' : '' }}"><span class="nav-icon">🛡️</span><span class="nav-text"><strong>Permissions</strong><span>Roles and access</span></span></a>
                @endif

                <div class="nav-section-label">Operations</div>
                @if($currentUser->hasPermission('calendar.view') && \Illuminate\Support\Facades\Schema::hasTable('leave_requests'))
                    <a href="{{ route('calendar.index') }}" class="{{ request()->routeIs('calendar.*') ? 'active' : '' }}"><span class="nav-icon">📅</span><span class="nav-text"><strong>Calendar</strong><span>Operational reminders</span></span></a>
                @endif
                @if($currentUser->hasPermission('leave.view') && \Illuminate\Support\Facades\Schema::hasTable('leave_requests'))
                    <a href="{{ route('leave.index') }}" class="{{ request()->routeIs('leave.*') ? 'active' : '' }}"><span class="nav-icon">🗓️</span><span class="nav-text"><strong>Leave</strong><span>Requests and approvals</span></span></a>
                @endif
                @if($currentUser->hasPermission('attendance.view') && \Illuminate\Support\Facades\Schema::hasTable('attendance_days'))
                    <a href="{{ route('attendance.index') }}" class="{{ request()->routeIs('attendance.*') ? 'active' : '' }}"><span class="nav-icon">⏱️</span><span class="nav-text"><strong>Time Attendance</strong><span>Check-ins and imports</span></span></a>
                @endif
                @if($currentUser->hasPermission('vehicle.view') && \Illuminate\Support\Facades\Schema::hasTable('vehicles'))
                    <a href="{{ route('vehicles.index') }}" class="{{ request()->routeIs('vehicles.*') ? 'active' : '' }}"><span class="nav-icon">🚗</span><span class="nav-text"><strong>Vehicles & Fuel</strong><span>Fleet, fuel and service</span></span></a>
                @endif
                @if($currentUser->hasPermission('vehicle_tracking.view') && \Illuminate\Support\Facades\Schema::hasTable('vehicle_tracking_snapshots'))
                    <a href="{{ route('vehicle_tracking.index') }}" class="{{ request()->routeIs('vehicle_tracking.index') ? 'active' : '' }}"><span class="nav-icon">🛰️</span><span class="nav-text"><strong>Vehicle Tracking</strong><span>Cartrack API sync</span></span></a>
                @endif
                @if($currentUser->hasPermission('employee_documents.view') && \Illuminate\Support\Facades\Schema::hasTable('employee_documents'))
                    <a href="{{ route('employee_documents.reminders') }}" class="{{ request()->routeIs('employee_documents.*') ? 'active' : '' }}"><span class="nav-icon">📄</span><span class="nav-text"><strong>Document Reminders</strong><span>Expiring records</span></span></a>
                @endif

                <div class="nav-section-label">Admin</div>
                @if($currentUser->hasPermission('cron_jobs.view'))
                    <a href="{{ route('cron_jobs.index') }}" class="{{ request()->routeIs('cron_jobs.*') ? 'active' : '' }}"><span class="nav-icon">⚙️</span><span class="nav-text"><strong>Cron Jobs</strong><span>Automation tasks</span></span></a>
                @endif
                @if($currentUser->hasAnyPermission(['core_settings.view','google_api_settings.view','vehicle_tracking.settings.view','leave_types.view','settings.manage']))
                    @if($currentUser->hasRole('system-administrator') && $currentUser->hasPermission('core_settings.view') && \Illuminate\Support\Facades\Schema::hasTable('system_settings'))
                        <a href="{{ route('core_settings.index') }}" class="{{ request()->routeIs('core_settings.*') ? 'active' : '' }}"><span class="nav-icon">🔧</span><span class="nav-text"><strong>System Settings</strong><span>Core settings</span></span></a>
                    @endif
                    @if($currentUser->hasRole('system-administrator') && $currentUser->hasPermission('google_api_settings.view') && \Illuminate\Support\Facades\Schema::hasTable('system_settings'))
                        <a href="{{ route('google_api_settings.index') }}" class="{{ request()->routeIs('google_api_settings.*') ? 'active' : '' }}"><span class="nav-icon">🗺️</span><span class="nav-text"><strong>Google API</strong><span>Maps settings</span></span></a>
                    @endif
                    @if($currentUser->hasRole('system-administrator') && $currentUser->hasPermission('vehicle_tracking.settings.view') && \Illuminate\Support\Facades\Schema::hasTable('system_settings'))
                        <a href="{{ route('vehicle_tracking.settings') }}" class="{{ request()->routeIs('vehicle_tracking.settings*') ? 'active' : '' }}"><span class="nav-icon">🛰️</span><span class="nav-text"><strong>Tracking API</strong><span>Cartrack settings</span></span></a>
                    @endif
                    @if($currentUser->hasPermission('leave_types.view') && \Illuminate\Support\Facades\Schema::hasTable('leave_types'))
                        <a href="{{ route('leave_types.index') }}" class="{{ request()->routeIs('leave_types.*') ? 'active' : '' }}"><span class="nav-icon">🗓️</span><span class="nav-text"><strong>Leave Settings</strong><span>Leave types</span></span></a>
                    @endif
                @endif
                <form class="logout-form" method="post" action="{{ route('logout') }}">@csrf<button type="submit"><span class="nav-icon">↩</span><span class="nav-text"><strong>Logout</strong><span>End session</span></span></button></form>
            </nav>
        </aside>
    @endauth

    <main class="main">
        @auth
            <header class="topbar">
                <div class="topbar-title">
                    <button class="menu-btn" type="button" onclick="toggleSidebar()"><span>☰</span><span>Menu</span></button>
                    <div class="topbar-icon" aria-hidden="true">{{ $pageIcon }}</div>
                    <div>
                        <h1>@yield('page_title', 'ISO Admin')</h1>
                        <div class="sub">{{ $topRole }} · isoadmin.co.za</div>
                    </div>
                </div>
                <div class="user">
                    <div class="user-meta"><strong>{{ $currentUser->name }}</strong><span>{{ $currentUser->email }}</span></div>
                    <div class="user-avatar" aria-hidden="true">{{ $initials }}</div>
                </div>
            </header>
        @endauth

        <section class="content">
            @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
            @if(session('warning'))<div class="alert warning">{{ session('warning') }}</div>@endif
            @if($errors->any())
                <div class="alert error">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif
            @yield('content')
            <div class="footer-note">ISO Admin Command Framework {{ \Illuminate\Support\Facades\Schema::hasTable('system_settings') ? 'v'.(\App\Models\SystemSetting::valueFor('platform_version', '2.6.10')) : 'v2.6.10' }} · isoadmin.co.za</div>
        </section>
    </main>
</div>
@auth
<script>
    function toggleSidebar(force) {
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebarBackdrop');
        if (!sidebar || !backdrop) return;
        var open = typeof force === 'boolean' ? force : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', open);
        backdrop.classList.toggle('open', open);
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') toggleSidebar(false);
    });
</script>
@endauth
</body>
</html>
