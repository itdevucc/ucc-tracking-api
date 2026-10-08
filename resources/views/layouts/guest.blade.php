<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>UCC Tracking API</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="ucc-auth min-h-screen flex items-center justify-center px-4 py-10">
            <div class="ucc-auth-shell grid w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-xl lg:grid-cols-2">
                <aside class="ucc-auth-intro hidden flex-col justify-between p-12 lg:flex">
                    <div class="rounded-xl bg-white p-5"><x-application-logo class="w-64 max-w-full" /></div>
                    <div class="py-16">
                        <p class="text-sm font-semibold uppercase tracking-widest text-white/70">United Cargo</p>
                        <h1 class="mt-4 text-4xl font-semibold leading-tight text-white">Tu carga.<br>Todo su recorrido.</h1>
                        <p class="mt-5 max-w-sm text-base leading-relaxed text-white/80">Consulta las rutas, fechas y eventos de tus contenedores en un solo lugar.</p>
                    </div>
                    <p class="text-xs tracking-widest text-white/60">UCC TRACKING API</p>
                </aside>
                <div class="px-6 py-10 sm:px-10 lg:p-12">
                    <a href="{{ url('/') }}" class="mb-8 block w-44 lg:hidden" aria-label="UCC Tracking API"><x-application-logo class="w-full" /></a>
                    <div class="mb-8 border-l-4 border-red-600 pl-4"><p class="text-xs font-semibold uppercase tracking-widest text-gray-500">UCC Tracking API</p><p class="mt-2 text-lg font-semibold text-gray-800">Seguimiento marítimo</p></div>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
