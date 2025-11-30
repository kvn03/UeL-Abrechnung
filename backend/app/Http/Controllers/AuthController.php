<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Neuer Benutzer registrieren
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string',
            'vorname' => 'required|string',
            'email' => 'required|string|unique:user,email',
            'password' => 'required|string|confirmed'
        ]);

        // Benutzer erstellen (Hashing übernimmt der Mutator im User Model!)
        $user = User::create([
            'name' => $fields['name'],
            'vorname' => $fields['vorname'],
            'email' => $fields['email'],
            'password' => $fields['password']
        ]);

        $token = $user->createToken('myapptoken')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Login und Token-Ausgabe
     */
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response([
                'message' => 'Falsche Zugangsdaten (E-Mail oder Passwort)'
            ], 401);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;

        return response([
            'user' => $user,
            'token' => $token,
            'message' => 'Login erfolgreich'
        ], 200);
    }

    /**
     * Logout (Token löschen)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return [
            'message' => 'Erfolgreich ausgeloggt'
        ];
    }
}
