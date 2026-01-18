<?php

use App\Http\Controllers\AbteilungController;
use App\Http\Controllers\Abteilungsleiter\AbteilungsleiterController;
use App\Http\Controllers\Abteilungsleiter\StundensatzController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Geschaeftsstelle\GeschaeftsstelleController;
use App\Http\Controllers\StundeneintragController;
use App\Http\Controllers\Uebungsleiter\AbrechnungController;
use App\Http\Controllers\Uebungsleiter\StammdatenController;
use App\Http\Controllers\Uebungsleiter\LizenzController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LimitController;


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
    Route::get('/limits/trainer-overview', [LimitController::class, 'getUebungsleiterLimitOverview']);
    Route::get('/abrechnung/meine/{id}', [AbrechnungController::class, 'show']);
    Route::get('/meine-uel-abteilungen', [AbteilungController::class, 'getMeineUelAbteilungen']);
    Route::post('/stundeneintrag', [StundeneintragController::class, 'store']);
    Route::get('/entwuerfe', [StundeneintragController::class, 'getEntwuerfe']);
    Route::delete('/stundeneintrag/{id}', [StundeneintragController::class, 'deleteEintrag']);
    Route::get('/stundeneintrag/{id}', [StundeneintragController::class, 'show']);
    Route::put('/stundeneintrag/{id}', [StundeneintragController::class, 'update']);
    Route::post('/abrechnung/erstellen', [AbrechnungController::class, 'erstellen']);
    Route::get('/abrechnung/meine', [AbrechnungController::class, 'getMeineAbrechnungen']);
    Route::get('/uebungsleiter/profil', [StammdatenController::class, 'getProfile']);
    Route::post('/uebungsleiter/profil', [StammdatenController::class, 'updateProfile']);
    Route::get('/uebungsleiter/meine-saetze', [App\Http\Controllers\Uebungsleiter\AbrechnungController::class, 'getMeineSaetze']);
    Route::get('/uebungsleiter/lizenzen', [LizenzController::class, 'getLizenzen']);
    Route::post('/uebungsleiter/lizenzen', [LizenzController::class, 'saveLizenz']);
    Route::delete('/uebungsleiter/lizenzen/{id}', [LizenzController::class, 'deleteLizenz']);
    //Administrator ODER GS-Routen
    Route::group(['middleware' => ['admin_or_gs']], function () {
        Route::get('/admin/users', [AuthController::class, 'listUsers']);
        Route::put('/admin/users/{id}/roles', [AuthController::class, 'updateUserRoles']);
    });
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
        Route::get('/geschaeftsstelle/mitarbeiter', [GeschaeftsstelleController::class, 'getAllMitarbeiter']);
        Route::post('/geschaeftsstelle/stundensatz', [StundensatzController::class, 'updateStundensatz']);
        Route::get('/geschaeftsstelle/stundensatz-historie', [GeschaeftsstelleController::class, 'getStundensatzHistorie']);
        Route::get('/geschaeftsstelle/auszahlungen', [GeschaeftsstelleController::class, 'getAuszahlungen']);
        Route::post('/geschaeftsstelle/abrechnungen/finalize-bulk', [App\Http\Controllers\Geschaeftsstelle\GeschaeftsstelleController::class, 'finalizeBulk']);
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
        Route::get('/abteilungsleiter/mitarbeiter', [StundensatzController::class, 'getMitarbeiter']);
        Route::post('/abteilungsleiter/stundensatz', [StundensatzController::class, 'updateStundensatz']);
        Route::get('/meine-al-abteilungen', [AbteilungController::class, 'getMeineLeiterAbteilungen']);
        Route::get('/abteilungsleiter/stundensatz-historie', [StundensatzController::class, 'getStundensatzHistorie']);
        Route::get('/abteilungsleiter/abrechnungen-historie', [App\Http\Controllers\Abteilungsleiter\AbteilungsleiterController::class, 'getAbrechnungenHistorie']);
    });
    //Administrator-Routen
    Route::group(['middleware' => ['admin']], function ()
    {
        Route::post('/create-user', [AuthController::class, 'createUser']);
        Route::get('/admin/abteilungen', [App\Http\Controllers\AbteilungController::class, 'index']);
        Route::post('/admin/abteilungen', [App\Http\Controllers\AbteilungController::class, 'store']);
        Route::delete('/admin/abteilungen/{id}', [App\Http\Controllers\AbteilungController::class, 'destroy']);
        Route::put('/admin/abteilungen/{id}', [\App\Http\Controllers\AbteilungController::class, 'update']);
        Route::get('/admin/zuschlaege', [\App\Http\Controllers\Administrator\AdminZuschlagController::class, 'index']);
        Route::post('/admin/zuschlaege', [\App\Http\Controllers\Administrator\AdminZuschlagController::class, 'store']);
        Route::put('/admin/zuschlaege/{id}', [\App\Http\Controllers\Administrator\AdminZuschlagController::class, 'update']);
        Route::delete('/admin/zuschlaege/{id}', [\App\Http\Controllers\Administrator\AdminZuschlagController::class, 'destroy']);
        Route::get('/admin/limits', [\App\Http\Controllers\Administrator\AdminLimitController::class, 'index']);
        Route::post('/admin/limits', [\App\Http\Controllers\Administrator\AdminLimitController::class, 'store']);
        Route::put('/admin/limits/{id}', [\App\Http\Controllers\Administrator\AdminLimitController::class, 'update']);
        Route::delete('/admin/limits/{id}', [\App\Http\Controllers\Administrator\AdminLimitController::class, 'destroy']);
    });
});
