<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Prüfen, ob User eingeloggt ist
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 2. Prüfen, ob die Spalte 'isAdmin' wahr ist
        // (Laravel castet 1/0 aus der DB automatisch zu true/false, wenn im Model 'boolean' gecastet wird,
        // oder man prüft einfach auf == 1)
        if (Auth::user()->isAdmin) {
            return $next($request); // User darf passieren
        }

        // 3. Zugriff verweigern, wenn kein Administrator
        return response()->json(['message' => 'Forbidden: Admins only'], 403);
    }
}
