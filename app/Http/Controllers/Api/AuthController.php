<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // Registar um novo utilizador
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('campus-encontra')->plainTextToken;

        return response()->json([
            'message' => 'Utilizador registado com sucesso.',
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    // Iniciar sessão
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas são inválidas.'],
            ]);
        }

        $token = $user->createToken('campus-encontra')->plainTextToken;

        return response()->json([
            'message' => 'Login efectuado com sucesso.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // Terminar sessão: revogar o token utilizado
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sessão terminada com sucesso.',
        ]);
    }
}
