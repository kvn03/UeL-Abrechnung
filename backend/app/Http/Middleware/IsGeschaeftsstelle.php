<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class IsGeschaeftsstelle
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check: Eingeloggt?
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = Auth::user();

        // 2. Check: Ist Geschäftsstelle ODER Administrator?
        // (Admins sollten meistens alles dürfen, um Support zu leisten)
        if ($user->isGeschaeftsstelle || $user->isAdmin) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden: Geschäftsstelle only'], 403);
    }
}
