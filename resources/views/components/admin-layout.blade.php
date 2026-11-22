<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="reverb-key" content="{{ config('reverb.apps.apps.0.key') }}">
    <title>{{ $title ?? 'Admin' }} · Kusi Nna Restaurant Admin</title>
    <link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=lato:300,400,700|josefin-sans:400,600,700|great-vibes:400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-cream-dark text-stone-800 antialiased min-h-screen">
<div class="ribbon"></div>
<header class="bg-navy-900 text-navy-100">
    <div class="max-w-[1400px] mx-auto px-4 h-14 flex items-center gap-4">
        <a href="{{ route('admin.orders') }}" class="flex items-center gap-2 text-white"><x-sinag class="w-5 h-5 text-sun-400" /><span class="font-script text-3xl leading-none pt-1 text-shine animate-shine-text">Kusi Nna</span><span class="font-display text-[10px] font-semibold uppercase tracking-[0.25em] text-navy-300">Admin</span></a>
        <nav class="flex items-center gap-1 text-sm overflow-x-auto">
            @php
                $links = [
                    ['admin.orders', 'Orders', true], ['admin.orders.history', 'History', true],
                    ['admin.reservations', 'Reservations', true], ['admin.deliveries', 'Deliveries', true],
                    ['admin.menu', 'Menu', auth()->user()->isAdmin()], ['admin.floor', 'Floor plan', auth()->user()->isAdmin()],
                    ['admin.gallery', 'Gallery', auth()->user()->isAdmin()], ['admin.vouchers', 'Vouchers', auth()->user()->isAdmin()], ['admin.messages', 'Messages', true],
                ];
            @endphp
            @foreach ($links as [$route, $label, $show])
                @if ($show)
                    <a href="{{ route($route) }}" class="px-3 py-1.5 rounded-lg whitespace-nowrap hover:bg-navy-800 {{ request()->routeIs($route) ? 'bg-navy-700' : '' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="ml-auto flex items-center gap-3 text-sm">
            <a href="{{ route('menu') }}" target="_blank" class="text-navy-300 hover:text-white hidden sm:inline">View site ↗</a>
            <a href="{{ route('profile.edit') }}" class="text-navy-300 hover:text-white hidden sm:inline">{{ auth()->user()->name }}</a>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="rounded-lg bg-navy-700 px-3 py-1.5 hover:bg-navy-600">Log out</button></form>
        </div>
    </div>
</header>

<main class="max-w-[1400px] mx-auto px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">
            <ul class="list-disc list-inside">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    {{ $slot }}
</main>
</body>
</html>
