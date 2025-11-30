<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Öffentliche Routen (kein Login nötig)
Route::post('/create-user', [AuthController::class, 'store']);
Route::post('/login', [AuthController::class, 'login']);

// Geschützte Routen (nur mit gültigem Bearer Token zugänglich)
Route::group(['middleware' => ['auth:sanctum']], function () {

    // Logout funktioniert nur, wenn man eingeloggt ist
    Route::post('/logout', [AuthController::class, 'logout']);

    // Beispiel: Aktuellen Benutzer abrufen
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
