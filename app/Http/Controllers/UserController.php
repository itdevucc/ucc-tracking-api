<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        return view('users.index', ['users' => User::query()->withCount('tokens')->orderBy('name')->paginate(20)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $user = new User($data);
        // La cuenta es autorizada por el administrador interno.
        $user->email_verified_at = now();
        $user->save();

        return redirect()->route('users.index')->with('status', 'Usuario creado.');
    }

    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        if (in_array(strtolower($user->email), config('internal.administrators', []), true)) {
            if ($data['email'] !== $user->email) {
                throw ValidationException::withMessages(['email' => 'El correo del administrador se gestiona en INTERNAL_ADMIN_EMAILS.']);
            }
        }
        $credentialsChanged = $data['email'] !== $user->email || ! empty($data['password']);
        if (empty($data['password'])) unset($data['password']);

        DB::transaction(function () use ($user, $data, $credentialsChanged) {
            $user->fill($data);
            $user->email_verified_at = now();
            if ($credentialsChanged) $user->setRememberToken(null);
            $user->save();
            if ($credentialsChanged) {
                $user->tokens()->delete();
                $this->clearSessions($user);
            }
        });

        return redirect()->route('users.index')->with('status', 'Usuario actualizado. Si cambió sus credenciales, se revocaron sus tokens y sesiones de base de datos.');
    }

    public function revokeTokens(User $user)
    {
        $user->tokens()->delete();
        return back()->with('status', 'Tokens revocados.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user()) || in_array(strtolower($user->email), config('internal.administrators', []), true)) {
            throw ValidationException::withMessages(['user' => 'No puedes eliminar tu cuenta ni una cuenta administradora.']);
        }
        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $this->clearSessions($user);
            $user->delete();
        });
        return redirect()->route('users.index')->with('status', 'Usuario eliminado y tokens revocados.');
    }

    private function clearSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
