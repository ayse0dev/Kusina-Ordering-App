<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Kusina') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=lato:300,400,700|josefin-sans:400,600,700|great-vibes:400&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-stone-800 antialiased">
        <div class="pattern-banig min-h-screen flex flex-col items-center justify-center px-4 py-10 relative">
            <div class="pattern-sinag absolute inset-0 pointer-events-none"></div>

            <a href="/" class="relative flex flex-col items-center text-center">
                <span class="grid place-items-center w-16 h-16 rounded-2xl border border-sun-400/80">
                    <x-sinag class="w-10 h-10 text-sun-400" />
                </span>
                <span class="mt-2 font-script text-5xl text-sun-300 leading-none">Kusina</span>
                <span class="font-display text-[11px] uppercase tracking-[0.3em] text-white/60 font-semibold mt-2">Staff portal</span>
            </a>

            <div class="relative w-full sm:max-w-md mt-6 bg-white shadow-lift rounded-2xl overflow-hidden">
                <div class="ribbon"></div>
                <div class="px-6 py-6">{{ $slot }}</div>
            </div>
            <a href="/" class="relative mt-5 text-sm text-navy-200 hover:text-white">← Back to the restaurant</a>
        </div>
    </body>
</html>
