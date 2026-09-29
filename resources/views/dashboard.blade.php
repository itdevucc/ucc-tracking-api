<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            UCC Tracking App
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold">Dashboard seguro</h3>
                    <p class="mt-2 text-gray-600">
                        Sesión iniciada como <strong>{{ auth()->user()->email }}</strong>.
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        Las rutas del dashboard requieren autenticación y correo verificado.
                        La API usa tokens personales de Laravel Sanctum.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
