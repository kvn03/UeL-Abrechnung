<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsAbteilungsleiter
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Hier nutzen wir die Funktion aus Schritt 1
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->isAbteilungsleiter()) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden: Abteilungsleiter only'], 403);
    }
}
