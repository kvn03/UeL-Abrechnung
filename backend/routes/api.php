<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\StundeneintragController;
use App\Http\Controllers\AbrechnungController;
use App\Http\Controllers\AbteilungsleiterController;

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
    Route::get('/meine-uel-abteilungen', [AbteilungController::class, 'getMeineUelAbteilungen']);
    Route::post('/stundeneintrag', [StundeneintragController::class, 'store']);
    Route::get('/entwuerfe', [StundeneintragController::class, 'getEntwuerfe']);
    Route::delete('/stundeneintrag/{id}', [StundeneintragController::class, 'deleteEintrag']);
    Route::get('/stundeneintrag/{id}', [StundeneintragController::class, 'show']);
    Route::put('/stundeneintrag/{id}', [StundeneintragController::class, 'update']);
    Route::post('/abrechnung/erstellen', [AbrechnungController::class, 'erstellen']);
    Route::get('/abrechnung/summary', [AbrechnungController::class, 'getSummary']);
    //Geschäftsstelle-Routen
    Route::group(['middleware' => ['gs']], function ()
    {
        Route::get('/geschaeftsstelle/abrechnungen', [AbrechnungController::class, 'getAbrechnungenFuerGeschaeftsstelle']);
        Route::post('/geschaeftsstelle/abrechnungen/{id}/finalize', [AbrechnungController::class, 'finalize']);
        Route::get('/geschaeftsstelle/abrechnungen-historie', [AbrechnungController::class, 'getAbrechnungenHistorieFuerGeschaeftsstelle']);
    });
    //Abteilungsleiter-Routen
    Route::group(['middleware' => ['al']], function ()
    {
        Route::get('/abteilungsleiter/abrechnungen', [AbteilungsleiterController::class, 'getOffeneAbrechnungen']);
        Route::post('/abteilungsleiter/abrechnungen/{id}/approve', [AbteilungsleiterController::class, 'approve']);
        Route::post('/abteilungsleiter/stundeneintrag', [AbteilungsleiterController::class, 'addEntry']);
        Route::put('/abteilungsleiter/stundeneintrag/{id}', [AbteilungsleiterController::class, 'updateEntry']);
        Route::delete('/abteilungsleiter/stundeneintrag/{id}', [AbteilungsleiterController::class, 'deleteEntry']);
    });
    //Admin-Routen
    Route::group(['middleware' => ['admin']], function ()
    {
        Route::post('/create-user', [AuthController::class, 'createUser']);
    });
});
