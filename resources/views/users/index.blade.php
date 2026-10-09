<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Usuarios del servicio</h2></x-slot>
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
        @if (session('status')) <p role="status" class="text-sm text-gray-700">{{ session('status') }}</p> @endif
        <x-input-error :messages="$errors->get('user')" />
        <section class="bg-white shadow-sm rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Crear usuario interno</h3>
            <p class="text-sm text-gray-600 mb-4">La cuenta podrá consultar el tracking y obtener tokens del API. Solo los administradores configurados gestionan usuarios.</p>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf
                <div><x-input-label for="name" value="Nombre" /><x-text-input id="name" name="name" :value="old('name')" required maxlength="255" class="block w-full mt-1" /><x-input-error :messages="$errors->get('name')" /></div>
                <div><x-input-label for="email" value="Correo" /><x-text-input id="email" name="email" type="email" :value="old('email')" required maxlength="255" class="block w-full mt-1" /><x-input-error :messages="$errors->get('email')" /></div>
                <div><x-input-label for="password" value="Contraseña" /><x-text-input id="password" name="password" type="password" required autocomplete="new-password" class="block w-full mt-1" /><x-input-error :messages="$errors->get('password')" /></div>
                <div><x-input-label for="password_confirmation" value="Confirmar contraseña" /><x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="block w-full mt-1" /></div>
                <x-primary-button>Crear usuario</x-primary-button>
            </form>
        </section>
        <section class="bg-white shadow-sm rounded-lg p-6">
            <h3 class="text-lg font-semibold mb-4">Usuarios registrados</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead><tr class="border-b"><th class="p-3">Nombre</th><th class="p-3">Correo</th><th class="p-3">Tokens</th><th class="p-3">Acciones</th></tr></thead>
                    <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b">
                            <td class="p-3">{{ $user->name }}</td><td class="p-3">{{ $user->email }}</td><td class="p-3">{{ $user->tokens_count }}</td>
                            <td class="p-3"><div class="flex flex-wrap gap-3 items-center">
                                <a class="text-red-700 underline" href="{{ route('users.edit', $user) }}">Editar</a>
                                <form method="POST" action="{{ route('users.tokens.destroy', $user) }}" onsubmit="return confirm('¿Revocar todos los tokens de este usuario?');">@csrf @method('DELETE')<button class="text-gray-700 underline">Revocar tokens</button></form>
                                @if ($user->id !== auth()->id() && !in_array(strtolower($user->email), config('internal.administrators', []), true))
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('¿Eliminar este usuario y revocar su acceso?');">@csrf @method('DELETE')<button class="text-red-700 underline">Eliminar</button></form>
                                @endif
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-3">No hay usuarios.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $users->links() }}</div>
        </section>
    </div>
</x-app-layout>
