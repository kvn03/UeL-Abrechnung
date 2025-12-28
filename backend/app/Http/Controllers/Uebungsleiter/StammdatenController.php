<?php

namespace App\Http\Controllers\Uebungsleiter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserStammdaten;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StammdatenController extends Controller
{
    /**
     * GET: Lädt die aktuellen Profil-Daten für das Formular
     */
    public function getProfile()
    {
        $userId = Auth::id(); // Hole ID vom eingeloggten User

        // Suche den Eintrag, wo gueltigBis NULL ist (= aktuell)
        $currentData = UserStammdaten::where('fk_userID', $userId)
            ->whereNull('gueltigBis')
            ->first();

        if (!$currentData) {
            // Falls noch gar keine Daten existieren, leeres Objekt zurückgeben
            return response()->json([
                'plz' => '',
                'ort' => '',
                'strasse' => '',
                'hausnummer' => '', // Mapping beachten: DB = hausnr, Frontend = hausnummer
                'iban' => ''
            ]);
        }

        return response()->json([
            'plz' => $currentData->plz,
            'ort' => $currentData->ort,
            'strasse' => $currentData->strasse,
            'hausnummer' => $currentData->hausnr,
            'iban' => $currentData->iban
        ]);
    }

    /**
     * POST: Speichert neue Daten (Historisierung)
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'plz' => 'required|max:10',
            'ort' => 'required|string|max:255',
            'strasse' => 'required|string|max:255',
            'hausnummer' => 'required|string|max:20',
            'iban' => 'required|string|max:34',
        ]);

        $userId = Auth::id();
        // Das heutige Datum als String "2023-10-27"
        $todayString = now()->format('Y-m-d');

        DB::transaction(function () use ($userId, $validated, $todayString) {

            $activeEntry = UserStammdaten::where('fk_userID', $userId)
                ->whereNull('gueltigBis')
                ->first();

            // --- KORREKTUR START ---
            // Wir prüfen: Gibt es einen Eintrag UND ist sein Startdatum (formatiert als String) gleich heute?
            if ($activeEntry && $activeEntry->gueltigVon->format('Y-m-d') === $todayString) {

                // Ja: Eintrag ist von heute -> Update (Überschreiben)
                $activeEntry->update([
                    'plz' => $validated['plz'],
                    'ort' => $validated['ort'],
                    'strasse' => $validated['strasse'],
                    'hausnr' => $validated['hausnummer'], // Mapping beachten!
                    'iban' => $validated['iban']
                ]);

                return; // Wir brechen hier ab, damit KEIN neuer Eintrag erstellt wird
            }
            // --- KORREKTUR ENDE ---

            // Falls wir hier ankommen, ist der Eintrag alt (Gestern oder älter) oder existiert noch nicht.

            // 1. Alten Eintrag beenden (falls vorhanden)
            if ($activeEntry) {
                // Optional: Prüfen ob sich überhaupt was geändert hat, um DB-Müll zu sparen
                if (
                    $activeEntry->plz == $validated['plz'] &&
                    $activeEntry->ort == $validated['ort'] &&
                    $activeEntry->strasse == $validated['strasse'] &&
                    $activeEntry->hausnr == $validated['hausnummer'] &&
                    $activeEntry->iban == $validated['iban']
                ) {
                    return;
                }

                // Alten Eintrag beenden
                $activeEntry->update(['gueltigBis' => $todayString]);
            }

            // 2. Neuen Eintrag erstellen
            UserStammdaten::create([
                'fk_userID' => $userId,
                'plz' => $validated['plz'],
                'ort' => $validated['ort'],
                'strasse' => $validated['strasse'],
                'hausnr' => $validated['hausnummer'],
                'iban' => $validated['iban'],
                'gueltigVon' => $todayString,
                'gueltigBis' => null
            ]);
        });

        return response()->json(['message' => 'Profil erfolgreich aktualisiert'], 200);
    }
}
