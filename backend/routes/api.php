<?php

use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\Abteilungsleiter\AbteilungsleiterController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Geschaeftsstelle\GeschaeftsstelleController;
use App\Http\Controllers\StundeneintragController;
use App\Http\Controllers\Uebungsleiter\AbrechnungController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Öffentliche Routen (kein Login nötig)
Route::post('/login', [AuthController::class, 'login']);
Route::get('/abteilungen', [AbteilungController::class, 'getAbteilung']);
Route::post('/set-password', [AuthController::class, 'setNewPassword']);

// Geschützte Routen (nur mit gültigem Bearer Token zugänglich)
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/dashboard', [AuthController::class, 'dashboard']);
    Route::get('/abrechnung/meine/{id}', [AbrechnungController::class, 'show']);
    Route::get('/meine-uel-abteilungen', [AbteilungController::class, 'getMeineUelAbteilungen']);
    Route::post('/stundeneintrag', [StundeneintragController::class, 'store']);
    Route::get('/entwuerfe', [StundeneintragController::class, 'getEntwuerfe']);
    Route::delete('/stundeneintrag/{id}', [StundeneintragController::class, 'deleteEintrag']);
    Route::get('/stundeneintrag/{id}', [StundeneintragController::class, 'show']);
    Route::put('/stundeneintrag/{id}', [StundeneintragController::class, 'update']);
    Route::post('/abrechnung/erstellen', [AbrechnungController::class, 'erstellen']);
    Route::get('/abrechnung/meine', [AbrechnungController::class, 'getMeineAbrechnungen']);
    //Geschäftsstelle-Routen
    Route::group(['middleware' => ['gs']], function ()
    {
        Route::get('/geschaeftsstelle/abrechnungen', [GeschaeftsstelleController::class, 'getAbrechnungenFuerGeschaeftsstelle']);
        Route::post('/geschaeftsstelle/abrechnungen/{id}/finalize', [GeschaeftsstelleController::class, 'finalize']);
        Route::get('/geschaeftsstelle/abrechnungen-historie', [GeschaeftsstelleController::class, 'getAbrechnungenHistorieFuerGeschaeftsstelle']);
        Route::post('/geschaeftsstelle/stundeneintrag', [GeschaeftsstelleController::class, 'addEntry']);
        Route::put('/geschaeftsstelle/stundeneintrag/{id}', [GeschaeftsstelleController::class, 'updateEntry']);
        Route::delete('/geschaeftsstelle/stundeneintrag/{id}', [GeschaeftsstelleController::class, 'deleteEntry']);
        Route::post('/geschaeftsstelle/abrechnungen/{id}/reject', [GeschaeftsstelleController::class, 'reject']);
    });
    //Abteilungsleiter-Routen
    Route::group(['middleware' => ['al']], function ()
    {
        Route::get('/abteilungsleiter/abrechnungen', [AbteilungsleiterController::class, 'getOffeneAbrechnungen']);
        Route::post('/abteilungsleiter/abrechnungen/{id}/approve', [AbteilungsleiterController::class, 'approve']);
        Route::post('/abteilungsleiter/stundeneintrag', [AbteilungsleiterController::class, 'addEntry']);
        Route::put('/abteilungsleiter/stundeneintrag/{id}', [AbteilungsleiterController::class, 'updateEntry']);
        Route::delete('/abteilungsleiter/stundeneintrag/{id}', [AbteilungsleiterController::class, 'deleteEntry']);
        Route::post('/abteilungsleiter/abrechnungen/{id}/reject', [AbteilungsleiterController::class, 'reject']);
    });
    //Admin-Routen
    Route::group(['middleware' => ['admin']], function ()
    {
        Route::post('/create-user', [AuthController::class, 'createUser']);
        Route::get('/admin/users', [AuthController::class, 'listUsers']);
        Route::put('/admin/users/{id}/roles', [AuthController::class, 'updateUserRoles']);
    });
});
