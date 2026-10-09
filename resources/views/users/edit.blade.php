<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800">Editar usuario</h2></x-slot>
    <div class="max-w-3xl mx-auto py-8 px-4">
        <section class="bg-white shadow-sm rounded-lg p-6">
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                @csrf @method('PATCH')
                <div><x-input-label for="name" value="Nombre" /><x-text-input id="name" name="name" :value="old('name', $user->name)" required maxlength="255" class="block w-full mt-1" /><x-input-error :messages="$errors->get('name')" /></div>
                <div><x-input-label for="email" value="Correo" /><x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required maxlength="255" class="block w-full mt-1" /><x-input-error :messages="$errors->get('email')" /></div>
                <div><x-input-label for="password" value="Nueva contraseña (opcional)" /><x-text-input id="password" name="password" type="password" autocomplete="new-password" class="block w-full mt-1" /><x-input-error :messages="$errors->get('password')" /></div>
                <div><x-input-label for="password_confirmation" value="Confirmar nueva contraseña" /><x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="block w-full mt-1" /></div>
                <p class="text-sm text-gray-600">Cambiar el correo o contraseña revoca los tokens y las sesiones del usuario.</p>
                <div class="flex gap-4 items-center"><x-primary-button>Guardar</x-primary-button><a class="text-gray-600 underline" href="{{ route('users.index') }}">Volver</a></div>
            </form>
        </section>
    </div>
</x-app-layout>
