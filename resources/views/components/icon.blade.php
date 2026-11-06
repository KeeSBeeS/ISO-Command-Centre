@props(['name', 'size' => 20])
@php
    // Simple line icons in one style (24×24, 1.8 stroke), drawn here so no
    // icon service or font is needed. Add new ones to this list.
    $icons = [
        'dashboard' => ['M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
        'profile' => ['M12 4a4 4 0 1 1 0 8a4 4 0 1 1 0-8z', 'M4 21c1-4 4.2-6 8-6s7 2 8 6'],
        'employees' => ['M9 4.5a3.5 3.5 0 1 1 0 7a3.5 3.5 0 1 1 0-7z', 'M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5', 'M16 4.5a3.5 3.5 0 0 1 0 7', 'M18 14.8c2 .7 3.2 2.6 3.5 5.2'],
        'customers' => ['M5 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2z', 'M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2', 'M3 13h18'],
        'departments' => ['M5 3h14v18H5z', 'M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2'],
        'permissions' => ['M12 3l7 3v6c0 4.5-3 7.7-7 9c-4-1.3-7-4.5-7-9V6z', 'M9 12l2 2 4-4'],
        'calendar' => ['M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z', 'M3 10h18M8 3v4M16 3v4'],
        'leave' => ['M12 3c3 3 4.5 6 4.5 9a4.5 4.5 0 0 1-9 0c0-3 1.5-6 4.5-9z', 'M12 16.5V21'],
        'attendance' => ['M12 3a9 9 0 1 1 0 18a9 9 0 1 1 0-18z', 'M12 7v5l3 2'],
        'vehicles' => ['M5 10h14a2 2 0 0 1 2 2v5H3v-5a2 2 0 0 1 2-2z', 'M6 10l2-4h8l2 4', 'M7.5 17a1.5 1.5 0 1 0 0 .01', 'M16.5 17a1.5 1.5 0 1 0 0 .01'],
        'tracking' => ['M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z', 'M12 7.5a2.5 2.5 0 1 1 0 5a2.5 2.5 0 1 1 0-5z'],
        'documents' => ['M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z', 'M14 3v5h5', 'M9 13h6M9 17h4'],
        'cron' => ['M12 5a8 8 0 1 1 0 16a8 8 0 1 1 0-16z', 'M12 9v4l2 2', 'M9 2h6'],
        'settings' => ['M12 9a3 3 0 1 1 0 6a3 3 0 1 1 0-6z', 'M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1'],
        'map' => ['M9 4L3 6v14l6-2 6 2 6-2V4l-6 2z', 'M9 4v14M15 6v14'],
        'updates' => ['M12 19V5', 'M5 12l7-7 7 7'],
        'logout' => ['M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3', 'M10 16l-4-4 4-4', 'M6 12h10'],
        'menu' => ['M4 6h16M4 12h16M4 18h16'],
        'close' => ['M6 6l12 12M18 6L6 18'],
        'upload' => ['M12 16V4', 'M7 9l5-5 5 5', 'M4 20h16'],
        'download' => ['M12 4v12', 'M7 11l5 5 5-5', 'M4 20h16'],
        'plus' => ['M12 5v14M5 12h14'],
        'check' => ['M5 12l5 5 9-10'],
        'alert' => ['M12 3l9.5 17h-19z', 'M12 10v4M12 17.5v.01'],
        'report' => ['M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z', 'M14 3v5h5', 'M9 17v-3M12 17v-6M15 17v-2'],
        'fuel' => ['M4 20V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v14', 'M3 20h12', 'M4 10h10', 'M14 8l3 2v7a1.5 1.5 0 0 0 3 0V9l-3-3'],
        'edit' => ['M4 20h4L19 9l-4-4L4 16z', 'M13.5 6.5l4 4'],
        'bell' => ['M6 8a6 6 0 0 1 12 0c0 7 3 8 3 8H3s3-1 3-8', 'M10 20a2 2 0 0 0 4 0'],
        'arrow-right' => ['M5 12h14', 'M13 6l6 6-6 6'],
    ];
    $paths = $icons[$name] ?? $icons['dashboard'];
@endphp
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">@foreach($paths as $d)<path d="{{ $d }}"/>@endforeach</svg>
