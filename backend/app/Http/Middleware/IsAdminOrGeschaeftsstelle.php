<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminOrGeschaeftsstelle
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Prüfe auf Administrator ODER Geschäftsstelle
        if ($user && ($user->isAdmin || $user->isGeschaeftsstelle)) {
            return $next($request);
        }

        return response()->json(['message' => 'Zugriff verweigert.'], 403);
    }
}
