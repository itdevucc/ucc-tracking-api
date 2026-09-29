<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends Controller
{
    private const ALLOWED_ABILITIES = [
        'tracking:read',
        'tracking:write',
    ];

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->tokens()
                ->latest()
                ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
            'abilities' => ['sometimes', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(self::ALLOWED_ABILITIES)],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Debes verificar tu correo antes de crear un token.',
            ]);
        }

        $abilities = $credentials['abilities'] ?? self::ALLOWED_ABILITIES;
        $expiration = (int) config('sanctum.expiration', 60);
        $expiresAt = $expiration > 0 ? now()->addMinutes($expiration) : null;
        $token = $user->createToken($credentials['device_name'], $abilities, $expiresAt);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toIso8601String(),
        ], 201);
    }

    public function destroyCurrent(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Token revocado correctamente.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Todos los tokens fueron revocados.']);
    }
}
